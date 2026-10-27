<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisSertifikat;
use App\Enums\PeranSertifikat;
use App\Enums\StatusSertifikat;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\SertifikatTerbitMail;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanPenandatangan;
use App\Models\KegiatanRegistration;
use App\Models\Penandatangan;
use App\Models\PengaturanVerifikasi;
use App\Models\Sertifikat;
use App\Models\SertifikatRiwayatPerubahan;
use App\Models\User;
use App\Services\CertificateGeneratorService;
use App\Services\EmailProtectionService;
use App\Services\Kegiatan\KelayakanSertifikatService;
use App\Services\SimpleXlsxService;
use App\Support\CsvSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SertifikatDigitalController extends Controller
{
    /**
     * Pusat Manajemen Sertifikat Digital (/admin/sertifikat).
     * Sub-navigasi: Terbitkan (default), Arsip, Penandatangan, Pengaturan Verifikasi.
     * Hanya Super Admin yang diizinkan (SertifikatPolicy).
     */
    public function index(Request $request, KelayakanSertifikatService $kelayakanService): View
    {
        Gate::authorize('viewAny', Sertifikat::class);

        $tab = $request->input('tab', 'terbitkan');
        if (! in_array($tab, ['terbitkan', 'arsip', 'penandatangan', 'pengaturan'], true)) {
            $tab = 'terbitkan';
        }

        $kegiatans = Kegiatan::with([
            'presensis:id,kegiatan_id,user_id,nama_lengkap,email',
            'registrations' => fn ($q) => $q->active()->select('id', 'kegiatan_id', 'participant_user_id', 'participant_name', 'participant_identity', 'status_pembayaran', 'status'),
        ])->latest('tanggal_mulai')->get();

        $students = User::whereIn('role', [UserRole::MahasiswaKipk->value, UserRole::UserUmum->value])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'nim']);

        $viewData = [
            'activeTab' => $tab,
            'kegiatans' => $kegiatans,
            'students' => $students,
        ];

        if ($tab === 'terbitkan') {
            $selectedKegiatanId = $request->input('kegiatan_id');
            $selectedKegiatan = $selectedKegiatanId
                ? Kegiatan::find($selectedKegiatanId)
                : $kegiatans->first();

            $selectedJenis = JenisSertifikat::tryFrom((string) $request->input('jenis', 'peserta')) ?? JenisSertifikat::Peserta;

            $waktuPenerbitan = $selectedKegiatan
                ? $kelayakanService->cekWaktuPenerbitan($selectedKegiatan, $selectedJenis)
                : ['bisa' => false, 'alasan' => 'Kegiatan belum dipilih.'];

            $recipientsData = [
                'peserta_layak' => collect(),
                'peserta_terbit' => collect(),
                'peserta_tidak_layak' => collect(),
                'juri_list' => collect(),
                'pembicara_terbit' => collect(),
                'juara_usulan' => collect(),
                'ada_karya_belum_dinilai' => false,
                'jumlah_belum_dinilai' => 0,
            ];

            if ($selectedKegiatan) {
                if ($selectedJenis === JenisSertifikat::Peserta) {
                    $allPeserta = $kelayakanService->evaluasiSemuaPeserta($selectedKegiatan);
                    $recipientsData['peserta_layak'] = $allPeserta->filter(fn ($p) => $p['layak'] && ! $p['sudah_terbit'])->values();
                    $recipientsData['peserta_terbit'] = $allPeserta->filter(fn ($p) => $p['sudah_terbit'])->values();
                    $recipientsData['peserta_tidak_layak'] = $allPeserta->filter(fn ($p) => ! $p['layak'])->values();
                } elseif ($selectedJenis === JenisSertifikat::Juri) {
                    $recipientsData['juri_list'] = $kelayakanService->evaluasiJuri($selectedKegiatan);
                } elseif ($selectedJenis === JenisSertifikat::Pembicara) {
                    $recipientsData['pembicara_terbit'] = Sertifikat::where('kegiatan_id', $selectedKegiatan->id)
                        ->where('jenis', JenisSertifikat::Pembicara)
                        ->where('status', StatusSertifikat::Aktif)
                        ->get();
                } elseif ($selectedJenis === JenisSertifikat::Juara) {
                    $evalJuara = $kelayakanService->evaluasiJuara($selectedKegiatan);
                    $recipientsData['juara_usulan'] = $evalJuara['usulan'];
                    $recipientsData['ada_karya_belum_dinilai'] = $evalJuara['ada_karya_belum_dinilai'];
                    $recipientsData['jumlah_belum_dinilai'] = $evalJuara['jumlah_belum_dinilai'];
                }
            }

            // Penandatangan yang ditugaskan untuk kegiatan & jenis ini
            $assignedSignatories = collect();
            if ($selectedKegiatan) {
                $assignedSignatories = KegiatanPenandatangan::with('penandatangan')
                    ->where('kegiatan_id', $selectedKegiatan->id)
                    ->where(function ($q) use ($selectedJenis) {
                        $q->where('jenis_sertifikat', $selectedJenis->value)
                            ->orWhere('jenis_sertifikat', 'semua');
                    })
                    ->orderBy('urutan')
                    ->get();
            }

            // Fallback jika belum ada yang ditugaskan: 2 master penandatangan aktif pertama
            if ($assignedSignatories->isEmpty()) {
                $assignedSignatories = Penandatangan::where('is_active', true)->take(2)->get();
            }

            $viewData = array_merge($viewData, [
                'selectedKegiatan' => $selectedKegiatan,
                'selectedJenis' => $selectedJenis,
                'waktuPenerbitan' => $waktuPenerbitan,
                'recipientsData' => $recipientsData,
                'assignedSignatories' => $assignedSignatories,
                'masterPenandatangan' => Penandatangan::where('is_active', true)->orderBy('nama')->get(),
            ]);
        } elseif ($tab === 'arsip') {
            $query = Sertifikat::with(['kegiatan', 'user', 'riwayatPerubahan.user'])->latest('issued_at');

            if ($kId = $request->input('kegiatan_id')) {
                $query->where('kegiatan_id', $kId);
            }
            if ($jns = $request->input('jenis')) {
                $query->where('jenis', $jns);
            }
            if ($sts = $request->input('status')) {
                $query->where('status', $sts);
            }
            if ($q = trim((string) $request->input('q'))) {
                $query->where(function ($w) use ($q) {
                    $w->where('nomor_sertifikat', 'like', "%{$q}%")
                        ->orWhere('nama_penerima', 'like', "%{$q}%")
                        ->orWhere('asal_institusi', 'like', "%{$q}%")
                        ->orWhere('nim_penerima', 'like', "%{$q}%");
                });
            }

            $sertifikats = $query->paginate(15)->withQueryString();
            $viewData['sertifikats'] = $sertifikats;
            $viewData['filters'] = $request->only(['kegiatan_id', 'jenis', 'status', 'q']);
        } elseif ($tab === 'penandatangan') {
            $masterPenandatangan = Penandatangan::withCount('kegiatan')->latest()->get();
            $kegiatanOptions = Kegiatan::latest('tanggal_mulai')->get(['id', 'nama_kegiatan']);
            $viewData['masterPenandatangan'] = $masterPenandatangan;
            $viewData['kegiatanOptions'] = $kegiatanOptions;
        } elseif ($tab === 'pengaturan') {
            $pengaturan = PengaturanVerifikasi::ambil();
            $viewData['pengaturan'] = $pengaturan;
        }

        // Simpan variabel lama $sertifikats untuk view agar aman
        if (! isset($viewData['sertifikats'])) {
            $viewData['sertifikats'] = Sertifikat::with(['kegiatan', 'user'])->latest('id')->paginate(15);
        }

        return view('admin.certificates.index', $viewData);
    }

    /**
     * Penerbitan Massal Sertifikat (Chunk 50 dalam transaksi DB, idempoten, re-evaluasi server).
     */
    public function batchIssue(
        Request $request,
        KelayakanSertifikatService $kelayakanService,
        CertificateGeneratorService $generatorService
    ): RedirectResponse {
        Gate::authorize('create', Sertifikat::class);

        $validated = $request->validate([
            'kegiatan_id' => ['required', 'exists:kegiatan,id'],
            'jenis' => ['required', 'in:peserta,juri,pembicara,juara'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.penerima_key' => ['nullable', 'string'],
            'recipients.*.user_id' => ['nullable', 'integer'],
            'recipients.*.registration_id' => ['nullable', 'integer'],
            'recipients.*.nama' => ['required', 'string', 'max:255'],
            'recipients.*.asal_institusi' => ['required', 'string', 'max:255'],
            'recipients.*.predikat' => ['nullable', 'string', 'max:100'],
            'recipients.*.email' => ['nullable', 'email'],
            'recipients.*.nim' => ['nullable', 'string', 'max:50'],
        ]);

        $kegiatan = Kegiatan::findOrFail($validated['kegiatan_id']);
        $jenisEnum = JenisSertifikat::from($validated['jenis']);

        // 1. Verifikasi aturan waktu
        $cekWaktu = $kelayakanService->cekWaktuPenerbitan($kegiatan, $jenisEnum);
        if (! $cekWaktu['bisa']) {
            return back()->with('error', $cekWaktu['alasan']);
        }

        // 2. Bekukan snapshot penandatangan saat ini untuk kegiatan dan jenis ini
        $penandatanganSnapshot = $this->getPenandatanganSnapshot($kegiatan, $jenisEnum);

        $recipients = $validated['recipients'];
        $berhasil = 0;
        $dilewati = 0;
        $alasanDilewati = [];
        $daftarId = [];

        // 3. Proses dalam potongan 50 untuk menghemat memori
        $chunks = array_chunk($recipients, 50);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use (
                $chunk,
                $kegiatan,
                $jenisEnum,
                $penandatanganSnapshot,
                $kelayakanService,
                $generatorService,
                &$berhasil,
                &$dilewati,
                &$alasanDilewati,
                &$daftarId
            ) {
                foreach ($chunk as $item) {
                    $userId = ! empty($item['user_id']) ? (int) $item['user_id'] : null;
                    $regId = ! empty($item['registration_id']) ? (int) $item['registration_id'] : null;
                    $nama = trim($item['nama']);
                    $institusi = trim($item['asal_institusi'] ?: 'Universitas Nahdlatul Ulama Surabaya');
                    $predikat = ! empty($item['predikat']) ? trim($item['predikat']) : null;
                    $email = ! empty($item['email']) ? trim($item['email']) : null;
                    $nim = ! empty($item['nim']) ? trim($item['nim']) : null;

                    // Wajib predikat untuk juara
                    if ($jenisEnum === JenisSertifikat::Juara && empty($predikat)) {
                        $dilewati++;
                        $alasanDilewati[] = "{$nama}: Predikat juara wajib diisi.";

                        continue;
                    }

                    $penerimaKey = Sertifikat::hitungPenerimaKey($userId, $nama, $institusi);

                    // Idempoten: Cek apakah sudah terbit sertifikat aktif untuk (kegiatan, penerima, jenis)
                    $sudahAda = Sertifikat::where('kegiatan_id', $kegiatan->id)
                        ->where('penerima_key', $penerimaKey)
                        ->where('jenis', $jenisEnum)
                        ->where('status', StatusSertifikat::Aktif)
                        ->exists();

                    if ($sudahAda) {
                        $dilewati++;
                        $alasanDilewati[] = "{$nama}: Sertifikat {$jenisEnum->label()} aktif sudah pernah diterbitkan.";

                        continue;
                    }

                    // Server re-evaluation: Evaluasi ulang kelayakan di sisi server
                    if ($jenisEnum === JenisSertifikat::Peserta) {
                        $reg = null;
                        if ($regId) {
                            $reg = KegiatanRegistration::find($regId);
                        } elseif ($userId) {
                            $reg = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
                                ->where('participant_user_id', $userId)
                                ->first();
                        }

                        if (! $reg) {
                            $dilewati++;
                            $alasanDilewati[] = "{$nama}: Data registrasi kegiatan tidak ditemukan.";

                            continue;
                        }

                        $eval = $kelayakanService->evaluasiPeserta($kegiatan, $reg);
                        if (! $eval['layak']) {
                            $dilewati++;
                            $alasanDilewati[] = "{$nama}: Tidak layak ({$eval['alasan']}).";

                            continue;
                        }
                    } elseif ($jenisEnum === JenisSertifikat::Juri) {
                        if ($userId) {
                            $isPenilai = $kegiatan->penilai()->where('users.id', $userId)->exists();
                            if (! $isPenilai) {
                                $dilewati++;
                                $alasanDilewati[] = "{$nama}: Tidak terdaftar sebagai penilai pada kegiatan ini.";

                                continue;
                            }
                        }
                    }

                    // Penomoran seri atomik
                    $nomorData = Sertifikat::generateNomorSertifikatAtomik($kegiatan);
                    $nomorSertifikat = $nomorData['nomor_sertifikat'];
                    $nomorUrut = $nomorData['nomor_urut'];

                    $user = $userId ? User::find($userId) : null;

                    // Terbitkan sertifikat
                    $sertifikat = $generatorService->publishFromAdmin(
                        kegiatan: $kegiatan,
                        namaPenerima: $nama,
                        emailPenerima: $email,
                        user: $user,
                        customNomor: $nomorSertifikat,
                        uploadedPdf: null,
                        peran: PeranSertifikat::tryFrom($jenisEnum->value) ?? PeranSertifikat::Peserta,
                        jenis: $jenisEnum,
                        predikat: $predikat,
                        asalInstitusi: $institusi,
                        nimPenerima: $nim,
                        registrationId: $regId,
                        penandatanganSnapshot: $penandatanganSnapshot,
                        nomorUrut: $nomorUrut
                    );

                    $berhasil++;
                    $daftarId[] = $sertifikat->id;

                    // Kirim email notifikasi (kategori massal lewat pintu terpusat)
                    if (! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        EmailProtectionService::send($email, new SertifikatTerbitMail($sertifikat), false);
                    }
                }
            });
        }

        // Catat Audit Log
        AuditLog::record(
            auth()->id(),
            'sertifikat_batch_terbit',
            "Penerbitan massal {$berhasil} sertifikat {$jenisEnum->label()} untuk kegiatan '{$kegiatan->nama_kegiatan}'.",
            $kegiatan,
            [
                'kegiatan_id' => $kegiatan->id,
                'jenis' => $jenisEnum->value,
                'berhasil' => $berhasil,
                'dilewati' => $dilewati,
                'daftar_id' => $daftarId,
                'alasan_dilewati' => array_slice($alasanDilewati, 0, 10),
            ]
        );

        $pesan = "Berhasil menerbitkan {$berhasil} sertifikat {$jenisEnum->label()}.";
        if ($dilewati > 0) {
            $pesan .= " ({$dilewati} dilewati: ".implode('; ', array_slice($alasanDilewati, 0, 3)).')';
        }

        return redirect()->route('admin.certificates', [
            'tab' => 'terbitkan',
            'kegiatan_id' => $kegiatan->id,
            'jenis' => $jenisEnum->value,
        ])->with($berhasil > 0 ? 'success' : 'error', $pesan);
    }

    /**
     * Tambah Penerima Manual Tunggal (Pembicara, Juri luar, Peserta luar).
     */
    public function storeManual(
        Request $request,
        CertificateGeneratorService $generatorService
    ): RedirectResponse {
        Gate::authorize('create', Sertifikat::class);

        $validated = $request->validate([
            'kegiatan_id' => ['required', 'exists:kegiatan,id'],
            'nama_penerima' => ['required', 'string', 'max:255'],
            'asal_institusi' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'in:peserta,juri,pembicara,juara'],
            'predikat' => ['nullable', 'string', 'max:100'],
            'email_penerima' => ['nullable', 'email'],
            'nim_penerima' => ['nullable', 'string', 'max:50'],
            'keterangan_publik' => ['nullable', 'string', 'max:500'],
            'certificate_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $kegiatan = Kegiatan::findOrFail($validated['kegiatan_id']);
        $jenisEnum = JenisSertifikat::from($validated['jenis']);
        $nama = trim($validated['nama_penerima']);
        $institusi = trim($validated['asal_institusi']);
        $predikat = ! empty($validated['predikat']) ? trim($validated['predikat']) : null;
        $email = ! empty($validated['email_penerima']) ? trim($validated['email_penerima']) : null;
        $nim = ! empty($validated['nim_penerima']) ? trim($validated['nim_penerima']) : null;
        $keterangan = ! empty($validated['keterangan_publik']) ? trim($validated['keterangan_publik']) : null;

        if ($jenisEnum === JenisSertifikat::Juara && empty($predikat)) {
            return back()->withInput()->with('error', 'Predikat wajib diisi untuk sertifikat jenis Juara.');
        }

        $penerimaKey = Sertifikat::hitungPenerimaKey(null, $nama, $institusi);

        // Cek duplikat
        $sudahAda = Sertifikat::where('kegiatan_id', $kegiatan->id)
            ->where('penerima_key', $penerimaKey)
            ->where('jenis', $jenisEnum)
            ->where('status', StatusSertifikat::Aktif)
            ->exists();

        if ($sudahAda) {
            return back()->withInput()->with('error', "Penerima '{$nama}' dari '{$institusi}' sudah memiliki sertifikat {$jenisEnum->label()} aktif.");
        }

        $penandatanganSnapshot = $this->getPenandatanganSnapshot($kegiatan, $jenisEnum);
        $nomorData = Sertifikat::generateNomorSertifikatAtomik($kegiatan);

        $sertifikat = $generatorService->publishFromAdmin(
            kegiatan: $kegiatan,
            namaPenerima: $nama,
            emailPenerima: $email,
            user: null,
            customNomor: $nomorData['nomor_sertifikat'],
            uploadedPdf: $request->file('certificate_file'),
            peran: PeranSertifikat::tryFrom($jenisEnum->value) ?? PeranSertifikat::Peserta,
            jenis: $jenisEnum,
            predikat: $predikat,
            asalInstitusi: $institusi,
            nimPenerima: $nim,
            registrationId: null,
            penandatanganSnapshot: $penandatanganSnapshot,
            keteranganPublik: $keterangan,
            nomorUrut: $nomorData['nomor_urut']
        );

        // Kirim email jika ada
        if (! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            EmailProtectionService::send($email, new SertifikatTerbitMail($sertifikat), false);
        }

        AuditLog::record(
            auth()->id(),
            'sertifikat_manual_terbit',
            "Penerbitan manual sertifikat {$jenisEnum->label()} untuk '{$nama}' ({$sertifikat->nomor_sertifikat}).",
            $sertifikat,
            [
                'kegiatan_id' => $kegiatan->id,
                'jenis' => $jenisEnum->value,
                'penerima' => $nama,
                'institusi' => $institusi,
            ]
        );

        return redirect()->route('admin.certificates', [
            'tab' => 'terbitkan',
            'kegiatan_id' => $kegiatan->id,
            'jenis' => $jenisEnum->value,
        ])->with('success', "Sertifikat manual untuk {$nama} ({$sertifikat->nomor_sertifikat}) berhasil diterbitkan.");
    }

    /**
     * Impor Massal Penerima dari berkas .xlsx atau .csv (maksimal 500 baris, 2 MB, formula neutralization).
     */
    public function importRecipients(
        Request $request,
        SimpleXlsxService $xlsxService,
        CertificateGeneratorService $generatorService
    ): RedirectResponse {
        Gate::authorize('create', Sertifikat::class);

        $validated = $request->validate([
            'kegiatan_id' => ['required', 'exists:kegiatan,id'],
            'file' => ['required', 'file', 'max:2048', 'mimes:xlsx,csv,txt'],
        ]);

        $kegiatan = Kegiatan::findOrFail($validated['kegiatan_id']);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $realPath = $file->getRealPath();

        $rows = [];
        if ($ext === 'xlsx') {
            try {
                $rows = $xlsxService->readXlsxFile($realPath);
            } catch (\Throwable $e) {
                return back()->with('error', 'Gagal membaca berkas XLSX: '.$e->getMessage());
            }
        } else {
            // CSV / TXT
            if (($handle = fopen($realPath, 'r')) !== false) {
                while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        }

        if (empty($rows) || count($rows) < 2) {
            return back()->with('error', 'Berkas kosong atau tidak memuat baris data.');
        }

        // Baris 1: Header
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
        $dataRows = array_slice($rows, 1);

        // Batas aman 500 baris
        if (count($dataRows) > 500) {
            return back()->with('error', 'Jumlah baris melebihi batas maksimal 500 baris data per impor.');
        }

        // Cari indeks kolom
        $colMap = [
            'nama' => false,
            'asal_institusi' => false,
            'jenis' => false,
            'predikat' => false,
            'email' => false,
            'nim' => false,
        ];

        foreach ($header as $idx => $colName) {
            $cleaned = preg_replace('/[^a-z_]/', '', str_replace([' ', '-'], '_', $colName));
            if (in_array($cleaned, ['nama', 'nama_lengkap', 'name'], true)) {
                $colMap['nama'] = $idx;
            } elseif (in_array($cleaned, ['asal_institusi', 'institusi', 'instansi', 'kampus', 'institution'], true)) {
                $colMap['asal_institusi'] = $idx;
            } elseif (in_array($cleaned, ['jenis', 'peran', 'type', 'role'], true)) {
                $colMap['jenis'] = $idx;
            } elseif (in_array($cleaned, ['predikat', 'juara', 'peringkat'], true)) {
                $colMap['predikat'] = $idx;
            } elseif (in_array($cleaned, ['email', 'email_penerima'], true)) {
                $colMap['email'] = $idx;
            } elseif (in_array($cleaned, ['nim', 'identitas', 'nip', 'nim_penerima'], true)) {
                $colMap['nim'] = $idx;
            }
        }

        if ($colMap['nama'] === false || $colMap['asal_institusi'] === false) {
            return back()->with('error', 'Kolom wajib "nama" dan "asal_institusi" tidak ditemukan pada header berkas.');
        }

        $berhasil = 0;
        $dilewati = 0;
        $alasanDilewati = [];
        $daftarId = [];
        $seenKeys = [];

        DB::transaction(function () use (
            $dataRows,
            $colMap,
            $kegiatan,
            $generatorService,
            &$berhasil,
            &$dilewati,
            &$alasanDilewati,
            &$daftarId,
            &$seenKeys
        ) {
            foreach ($dataRows as $rowIdx => $row) {
                $barisNo = $rowIdx + 2;
                $rawNama = $colMap['nama'] !== false ? ($row[$colMap['nama']] ?? '') : '';
                $rawInstitusi = $colMap['asal_institusi'] !== false ? ($row[$colMap['asal_institusi']] ?? '') : '';
                $rawJenis = $colMap['jenis'] !== false ? ($row[$colMap['jenis']] ?? 'peserta') : 'peserta';
                $rawPredikat = $colMap['predikat'] !== false ? ($row[$colMap['predikat']] ?? '') : '';
                $rawEmail = $colMap['email'] !== false ? ($row[$colMap['email']] ?? '') : '';
                $rawNim = $colMap['nim'] !== false ? ($row[$colMap['nim']] ?? '') : '';

                // Netralkan serangan formula injection
                $nama = trim(SimpleXlsxService::sanitizeFormulaOnImport($rawNama));
                $institusi = trim(SimpleXlsxService::sanitizeFormulaOnImport($rawInstitusi)) ?: 'Universitas Nahdlatul Ulama Surabaya';
                $jenisStr = strtolower(trim(SimpleXlsxService::sanitizeFormulaOnImport($rawJenis)));
                $predikat = trim(SimpleXlsxService::sanitizeFormulaOnImport($rawPredikat));
                $email = trim(SimpleXlsxService::sanitizeFormulaOnImport($rawEmail));
                $nim = trim(SimpleXlsxService::sanitizeFormulaOnImport($rawNim));

                if (empty($nama)) {
                    $dilewati++;
                    $alasanDilewati[] = "Baris {$barisNo}: Nama penerima kosong.";

                    continue;
                }

                $jenisEnum = JenisSertifikat::tryFrom($jenisStr) ?? JenisSertifikat::Peserta;
                if ($jenisEnum === JenisSertifikat::Juara && empty($predikat)) {
                    $dilewati++;
                    $alasanDilewati[] = "Baris {$barisNo} ({$nama}): Predikat juara wajib diisi.";

                    continue;
                }

                $penerimaKey = Sertifikat::hitungPenerimaKey(null, $nama, $institusi);

                // Cek duplikat dalam berkas
                if (isset($seenKeys[$penerimaKey.'|'.$jenisEnum->value])) {
                    $dilewati++;
                    $alasanDilewati[] = "Baris {$barisNo} ({$nama}): Duplikat di dalam berkas impor.";

                    continue;
                }
                $seenKeys[$penerimaKey.'|'.$jenisEnum->value] = true;

                // Cek duplikat di database
                $sudahAda = Sertifikat::where('kegiatan_id', $kegiatan->id)
                    ->where('penerima_key', $penerimaKey)
                    ->where('jenis', $jenisEnum)
                    ->where('status', StatusSertifikat::Aktif)
                    ->exists();

                if ($sudahAda) {
                    $dilewati++;
                    $alasanDilewati[] = "Baris {$barisNo} ({$nama}): Sudah memiliki sertifikat {$jenisEnum->label()} aktif.";

                    continue;
                }

                $penandatanganSnapshot = $this->getPenandatanganSnapshot($kegiatan, $jenisEnum);
                $nomorData = Sertifikat::generateNomorSertifikatAtomik($kegiatan);

                $sertifikat = $generatorService->publishFromAdmin(
                    kegiatan: $kegiatan,
                    namaPenerima: $nama,
                    emailPenerima: $email ?: null,
                    user: null,
                    customNomor: $nomorData['nomor_sertifikat'],
                    uploadedPdf: null,
                    peran: PeranSertifikat::tryFrom($jenisEnum->value) ?? PeranSertifikat::Peserta,
                    jenis: $jenisEnum,
                    predikat: $predikat ?: null,
                    asalInstitusi: $institusi,
                    nimPenerima: $nim ?: null,
                    registrationId: null,
                    penandatanganSnapshot: $penandatanganSnapshot,
                    nomorUrut: $nomorData['nomor_urut']
                );

                $berhasil++;
                $daftarId[] = $sertifikat->id;

                if (! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    EmailProtectionService::send($email, new SertifikatTerbitMail($sertifikat), false);
                }
            }
        });

        AuditLog::record(
            auth()->id(),
            'sertifikat_impor_massal',
            "Impor massal sertifikat untuk '{$kegiatan->nama_kegiatan}': {$berhasil} diterbitkan, {$dilewati} dilewati.",
            $kegiatan,
            [
                'kegiatan_id' => $kegiatan->id,
                'berhasil' => $berhasil,
                'dilewati' => $dilewati,
                'daftar_id' => $daftarId,
                'alasan_dilewati' => array_slice($alasanDilewati, 0, 10),
            ]
        );

        $pesan = "Impor selesai: {$berhasil} sertifikat berhasil diterbitkan.";
        if ($dilewati > 0) {
            $pesan .= " ({$dilewati} baris dilewati: ".implode('; ', array_slice($alasanDilewati, 0, 3)).')';
        }

        return redirect()->route('admin.certificates', [
            'tab' => 'terbitkan',
            'kegiatan_id' => $kegiatan->id,
        ])->with($berhasil > 0 ? 'success' : 'error', $pesan);
    }

    /**
     * Unduh templat berkas impor penerima (.csv).
     */
    public function downloadTemplate(): StreamedResponse
    {
        Gate::authorize('viewAny', Sertifikat::class);

        $headers = [
            'nama',
            'asal_institusi',
            'jenis',
            'predikat',
            'email',
            'nim',
        ];

        $sampleRows = [
            ['Dr. Budi Santoso, M.Kom.', 'Universitas Airlangga', 'pembicara', 'Narasumber Utama', 'budi@example.com', ''],
            ['Prof. Siti Rahmawati', 'Institut Teknologi Sepuluh Nopember', 'juri', 'Juri Lomba UI/UX', 'siti@example.com', ''],
            ['Ahmad Fauzi', 'Universitas Nahdlatul Ulama Surabaya', 'juara', 'Juara 1', 'ahmad@example.com', '3130021001'],
            ['Dewi Lestari', 'Universitas Brawijaya', 'peserta', '', 'dewi@example.com', '210100234'],
        ];

        $filename = 'templat_impor_penerima_sertifikat.csv';

        return response()->streamDownload(function () use ($headers, $sampleRows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            foreach ($sampleRows as $row) {
                $sanitized = array_map(fn ($val) => CsvSanitizer::sanitize((string) $val), $row);
                fputcsv($output, $sanitized);
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Edit Data Tampilan Sertifikat (dengan alasan wajib & riwayat perubahan).
     * Berkas PDF fisik dan document_hash tidak berubah.
     */
    public function updateDisplay(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('update', Sertifikat::class);

        $validated = $request->validate([
            'nama_penerima' => ['required', 'string', 'max:255'],
            'asal_institusi' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'in:peserta,juri,pembicara,juara'],
            'predikat' => ['nullable', 'string', 'max:100'],
            'keterangan_publik' => ['nullable', 'string', 'max:500'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $sertifikat = Sertifikat::findOrFail($id);
        $alasan = trim($validated['alasan']);
        $ip = $request->ip();
        $userId = auth()->id();

        $perubahan = [];
        $fieldsToCheck = [
            'nama_penerima' => trim($validated['nama_penerima']),
            'asal_institusi' => trim($validated['asal_institusi']),
            'jenis' => $validated['jenis'],
            'predikat' => ! empty($validated['predikat']) ? trim($validated['predikat']) : null,
            'keterangan_publik' => ! empty($validated['keterangan_publik']) ? trim($validated['keterangan_publik']) : null,
        ];

        foreach ($fieldsToCheck as $col => $newVal) {
            $oldVal = (string) ($sertifikat->{$col}?->value ?? $sertifikat->{$col} ?? '');
            $checkNewVal = (string) ($newVal ?? '');

            if ($oldVal !== $checkNewVal) {
                SertifikatRiwayatPerubahan::create([
                    'sertifikat_id' => $sertifikat->id,
                    'user_id' => $userId,
                    'ip_address' => $ip,
                    'kolom_diubah' => $col,
                    'nilai_lama' => $oldVal,
                    'nilai_baru' => $checkNewVal,
                    'alasan' => $alasan,
                    'created_at' => now(),
                ]);

                $sertifikat->{$col} = $newVal;
                $perubahan[$col] = ['lama' => $oldVal, 'baru' => $checkNewVal];
            }
        }

        if (! empty($perubahan)) {
            // Perbarui juga penerima_key jika nama atau institusi berubah
            $sertifikat->penerima_key = Sertifikat::hitungPenerimaKey(
                $sertifikat->user_id,
                $sertifikat->nama_penerima,
                $sertifikat->asal_institusi
            );
            $sertifikat->save();

            AuditLog::record(
                $userId,
                'sertifikat_edit_tampilan',
                "Perubahan data tampilan sertifikat {$sertifikat->nomor_sertifikat}. Alasan: {$alasan}",
                $sertifikat,
                ['perubahan' => $perubahan, 'alasan' => $alasan]
            );
        }

        $notice = 'Data tampilan sertifikat berhasil diperbarui.';
        if ($sertifikat->file_pdf_path) {
            $notice .= ' Catatan: Berkas PDF belum diperbarui (dokumen fisik dan hash tetap sesuai saat terbit).';
        }

        return back()->with('success', $notice);
    }

    /**
     * Cabut Sertifikat (Soft revoke dengan alasan wajib).
     * Menggantikan penghapusan keras; status verifikasi menjadi Dicabut.
     */
    public function revoke(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('revoke', Sertifikat::class);

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $sertifikat = Sertifikat::findOrFail($id);

        if ($sertifikat->isDicabut()) {
            return back()->with('error', "Sertifikat {$sertifikat->nomor_sertifikat} sudah dalam status dicabut.");
        }

        $alasan = trim($validated['alasan']);

        $sertifikat->update([
            'status' => StatusSertifikat::Dicabut,
            'alasan_pencabutan' => $alasan,
            'dicabut_pada' => now(),
            'dicabut_oleh' => auth()->id(),
        ]);

        AuditLog::record(
            auth()->id(),
            'sertifikat_dicabut',
            "Pencabutan sertifikat {$sertifikat->nomor_sertifikat}. Alasan: {$alasan}",
            $sertifikat,
            ['alasan' => $alasan]
        );

        return back()->with('success', "Sertifikat {$sertifikat->nomor_sertifikat} resmi dicabut.");
    }

    /**
     * Master Data Penandatangan: Tambah baru.
     */
    public function storePenandatangan(Request $request): RedirectResponse
    {
        Gate::authorize('managePenandatangan', Sertifikat::class);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'gelar' => ['nullable', 'string', 'max:50'],
            'jabatan' => ['required', 'string', 'max:255'],
            'jenis_identitas' => ['required', 'in:NIP,NIM,NIDN,NUPTK,tanpa'],
            'nomor_identitas' => ['nullable', 'string', 'max:100'],
            'institusi' => ['required', 'string', 'max:255'],
            'mode_tampil' => ['required', 'in:sembunyikan,samarkan,penuh'],
            'is_active' => ['nullable', 'boolean'],
            'tanda_tangan' => ['nullable', 'image', 'mimes:png,webp', 'max:1024'],
        ]);

        if ($validated['jenis_identitas'] !== 'tanpa' && empty($validated['nomor_identitas'])) {
            return back()->withInput()->with('error', 'Nomor identitas wajib diisi jika jenis identitas bukan "tanpa".');
        }

        $ttdPath = null;
        if ($request->hasFile('tanda_tangan')) {
            $ttdPath = $request->file('tanda_tangan')->store('private/signatures');
        }

        $penandatangan = Penandatangan::create([
            'nama' => trim($validated['nama']),
            'gelar' => ! empty($validated['gelar']) ? trim($validated['gelar']) : null,
            'jabatan' => trim($validated['jabatan']),
            'jenis_identitas' => $validated['jenis_identitas'],
            'nomor_identitas' => ! empty($validated['nomor_identitas']) ? trim($validated['nomor_identitas']) : null,
            'institusi' => trim($validated['institusi']),
            'mode_tampil' => $validated['mode_tampil'] ?? 'sembunyikan',
            'is_active' => $request->boolean('is_active', true),
            'tanda_tangan_path' => $ttdPath,
        ]);

        AuditLog::record(
            auth()->id(),
            'penandatangan_dibuat',
            "Menambahkan master penandatangan baru: {$penandatangan->nama_lengkap}",
            $penandatangan
        );

        return redirect()->route('admin.certificates', ['tab' => 'penandatangan'])
            ->with('success', "Penandatangan {$penandatangan->nama_lengkap} berhasil ditambahkan.");
    }

    /**
     * Master Data Penandatangan: Perbarui data.
     */
    public function updatePenandatangan(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('managePenandatangan', Sertifikat::class);

        $penandatangan = Penandatangan::findOrFail($id);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'gelar' => ['nullable', 'string', 'max:50'],
            'jabatan' => ['required', 'string', 'max:255'],
            'jenis_identitas' => ['required', 'in:NIP,NIM,NIDN,NUPTK,tanpa'],
            'nomor_identitas' => ['nullable', 'string', 'max:100'],
            'institusi' => ['required', 'string', 'max:255'],
            'mode_tampil' => ['required', 'in:sembunyikan,samarkan,penuh'],
            'is_active' => ['nullable', 'boolean'],
            'tanda_tangan' => ['nullable', 'image', 'mimes:png,webp', 'max:1024'],
        ]);

        if ($validated['jenis_identitas'] !== 'tanpa' && empty($validated['nomor_identitas'])) {
            return back()->withInput()->with('error', 'Nomor identitas wajib diisi jika jenis identitas bukan "tanpa".');
        }

        $ttdPath = $penandatangan->tanda_tangan_path;
        if ($request->hasFile('tanda_tangan')) {
            if ($ttdPath && Storage::disk('local')->exists($ttdPath)) {
                Storage::disk('local')->delete($ttdPath);
            }
            $ttdPath = $request->file('tanda_tangan')->store('private/signatures');
        }

        $penandatangan->update([
            'nama' => trim($validated['nama']),
            'gelar' => ! empty($validated['gelar']) ? trim($validated['gelar']) : null,
            'jabatan' => trim($validated['jabatan']),
            'jenis_identitas' => $validated['jenis_identitas'],
            'nomor_identitas' => ! empty($validated['nomor_identitas']) ? trim($validated['nomor_identitas']) : null,
            'institusi' => trim($validated['institusi']),
            'mode_tampil' => $validated['mode_tampil'],
            'is_active' => $request->boolean('is_active', true),
            'tanda_tangan_path' => $ttdPath,
        ]);

        AuditLog::record(
            auth()->id(),
            'penandatangan_diubah',
            "Memperbarui data master penandatangan: {$penandatangan->nama_lengkap}",
            $penandatangan
        );

        return redirect()->route('admin.certificates', ['tab' => 'penandatangan'])
            ->with('success', "Data penandatangan {$penandatangan->nama_lengkap} berhasil diperbarui.");
    }

    /**
     * Penugasan Penandatangan per Kegiatan (1 sampai 4 pejabat dengan urutan).
     */
    public function assignPenandatangan(Request $request): RedirectResponse
    {
        Gate::authorize('managePenandatangan', Sertifikat::class);

        $validated = $request->validate([
            'kegiatan_id' => ['required', 'exists:kegiatan,id'],
            'jenis_sertifikat' => ['required', 'string'],
            'penandatangan_ids' => ['required', 'array', 'min:1', 'max:4'],
            'penandatangan_ids.*' => ['required', 'exists:penandatangan,id'],
        ]);

        $kegiatan = Kegiatan::findOrFail($validated['kegiatan_id']);
        $jenis = $validated['jenis_sertifikat'];
        $ids = $validated['penandatangan_ids'];

        // Hapus penugasan lama untuk kegiatan dan jenis ini
        KegiatanPenandatangan::where('kegiatan_id', $kegiatan->id)
            ->where('jenis_sertifikat', $jenis)
            ->delete();

        // Tambah penugasan baru berurutan
        foreach ($ids as $index => $pId) {
            KegiatanPenandatangan::create([
                'kegiatan_id' => $kegiatan->id,
                'penandatangan_id' => $pId,
                'jenis_sertifikat' => $jenis,
                'urutan' => $index + 1,
            ]);
        }

        AuditLog::record(
            auth()->id(),
            'penandatangan_ditugaskan',
            'Menugaskan '.count($ids)." penandatangan untuk kegiatan '{$kegiatan->nama_kegiatan}' (jenis: {$jenis}).",
            $kegiatan,
            ['jenis' => $jenis, 'penandatangan_ids' => $ids]
        );

        return back()->with('success', 'Penugasan penandatangan kegiatan berhasil disimpan.');
    }

    /**
     * Pembaruan Pengaturan Halaman Verifikasi Publik.
     */
    public function updatePengaturan(Request $request): RedirectResponse
    {
        Gate::authorize('managePengaturan', Sertifikat::class);

        $validated = $request->validate([
            'teks_pengantar' => ['nullable', 'string', 'max:500'],
        ]);

        $pengaturan = PengaturanVerifikasi::ambil();
        $pengaturan->update([
            'tampilkan_asal_institusi' => $request->boolean('tampilkan_asal_institusi'),
            'tampilkan_jenis_predikat' => $request->boolean('tampilkan_jenis_predikat'),
            'tampilkan_nim_samar' => $request->boolean('tampilkan_nim_samar'),
            'tampilkan_penyelenggara' => $request->boolean('tampilkan_penyelenggara'),
            'tampilkan_keterangan' => $request->boolean('tampilkan_keterangan'),
            'tampilkan_tabel_penandatangan' => $request->boolean('tampilkan_tabel_penandatangan'),
            'teks_pengantar' => ! empty($validated['teks_pengantar']) ? trim($validated['teks_pengantar']) : $pengaturan->teks_pengantar,
            'updated_by' => auth()->id(),
        ]);

        AuditLog::record(
            auth()->id(),
            'pengaturan_verifikasi_diubah',
            'Memperbarui konfigurasi tampilan halaman verifikasi sertifikat publik.',
            $pengaturan,
            $pengaturan->toArray()
        );

        return redirect()->route('admin.certificates', ['tab' => 'pengaturan'])
            ->with('success', 'Pengaturan halaman verifikasi berhasil diperbarui.');
    }

    /**
     * Helper: Ambil snapshot penandatangan aktif untuk kegiatan dan jenis tertentu.
     */
    private function getPenandatanganSnapshot(Kegiatan $kegiatan, JenisSertifikat $jenis): array
    {
        $penugasan = KegiatanPenandatangan::with('penandatangan')
            ->where('kegiatan_id', $kegiatan->id)
            ->where(function ($q) use ($jenis) {
                $q->where('jenis_sertifikat', $jenis->value)
                    ->orWhere('jenis_sertifikat', 'semua');
            })
            ->orderBy('urutan')
            ->get();

        $list = collect();
        foreach ($penugasan as $item) {
            if ($item->penandatangan && $item->penandatangan->is_active) {
                $list->push($item->penandatangan);
            }
        }

        // Fallback jika tidak ada yang ditugaskan khusus: ambil 2 penandatangan aktif pertama
        if ($list->isEmpty()) {
            $list = Penandatangan::where('is_active', true)->take(2)->get();
        }

        return $list->map(function (Penandatangan $p) {
            return [
                'id' => $p->id,
                'nama' => $p->nama,
                'gelar' => $p->gelar,
                'nama_lengkap' => $p->nama_lengkap,
                'jabatan' => $p->jabatan,
                'jenis_identitas' => $p->jenis_identitas,
                'nomor_identitas' => $p->nomor_identitas,
                'institusi' => $p->institusi,
                'mode_tampil' => $p->mode_tampil,
                'identitas_terformat' => $p->identitas_terformat,
            ];
        })->all();
    }
}
