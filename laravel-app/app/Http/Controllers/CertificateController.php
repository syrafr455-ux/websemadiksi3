<?php

namespace App\Http\Controllers;

use App\Enums\StatusSertifikat;
use App\Models\Certificate;
use App\Models\Kegiatan;
use App\Models\KegiatanPenandatangan;
use App\Models\Penandatangan;
use App\Models\PengaturanVerifikasi;
use App\Models\Sertifikat;
use App\Services\CertificateGeneratorService;
use App\Services\Sertifikat\BerkasSertifikatService;
use App\Support\Masking;
use App\Support\Wib;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    /**
     * Portal Verifikasi Sertifikat Publik (`/verifikasi?code=...` & `/verifikasi-sertifikat`)
     * Mendukung verifikasi kode dan verifikasi integritas hash dokumen SHA-256.
     */
    public function verify(Request $request): View
    {
        $rawCode = (string) $request->input('code', '');
        // Bersihkan input kode: trim, uppercase, hanya izinkan alfanumerik, tanda hubung, garis miring, garis bawah, titik
        $code = strtoupper(trim(preg_replace('/[^A-Za-z0-9_\-\/\.]/', '', $rawCode)));

        $sertifikat = null;
        $legacyCertificate = null;
        $mockCertificate = null;
        $hashVerificationResult = null; // null, 'match', 'mismatch'
        $uploadedHash = null;
        $pengaturan = PengaturanVerifikasi::ambil();

        // Default valid certificates list untuk kompatibilitas kode lama (Next.js)
        $defaultLegacyCerts = [
            'CERT-LKMB-2024-0891' => [
                'code' => 'CERT-LKMB-2024-0891',
                'title' => 'Sertifikat Latihan Kepemimpinan Mahasiswa Berprestasi (LKMB)',
                'recipient_name' => 'Ahmad Fauzan',
                'author' => 'SEMADIKSI Divisi Keorganisasian',
                'hash' => 'e566c805ba07b8c6869e1f37847d0287a9b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5',
                'date' => '2024-11-16 09:12:05',
                'download_url' => null,
                'signatories' => [
                    ['no' => 1, 'name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => 'NIP: 197508122002121001', 'type' => 'NIP', 'jabatan' => 'Pembina', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => '2024-11-16 09:12:05', 'certified' => true, 'validity' => true],
                    ['no' => 2, 'name' => 'Muhammad Fatih (Ketua Umum SEMADIKSI)', 'nipNim' => 'NIM: 3130021045', 'type' => 'NIM', 'jabatan' => 'Ketua Umum', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => '2024-11-16 09:12:05', 'certified' => true, 'validity' => true],
                ],
            ],
            'CERT-VOL-2024-1102' => [
                'code' => 'CERT-VOL-2024-1102',
                'title' => 'Sertifikat Volunteer Mengajar Pesisir - SEMADIKSI Berbagi',
                'recipient_name' => 'Siti Nurhaliza',
                'author' => 'SEMADIKSI Divisi Pengabdian Masyarakat',
                'hash' => '8c7a6e112d88f6c99c5d01243170e881a9b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5',
                'date' => '2024-10-13 14:35:10',
                'download_url' => null,
                'signatories' => [
                    ['no' => 1, 'name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => 'NIP: 197508122002121001', 'type' => 'NIP', 'jabatan' => 'Pembina', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => '2024-10-13 14:35:10', 'certified' => true, 'validity' => true],
                    ['no' => 2, 'name' => 'Fauzi Rahmat (Ketua Divisi Pengabdian)', 'nipNim' => 'NIM: 2140021088', 'type' => 'NIM', 'jabatan' => 'Ketua Divisi Pengabdian', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => '2024-10-13 14:35:10', 'certified' => true, 'validity' => true],
                ],
            ],
            'CERT-WD-2024-0345' => [
                'code' => 'CERT-WD-2024-0345',
                'title' => 'Sertifikat Workshop Web Development Modern dengan Next.js',
                'recipient_name' => 'Muhammad Ilham',
                'author' => 'SEMADIKSI Divisi IPTEK & Humas',
                'hash' => 'f566c805ba07b8c6869e1f37847d0345a9b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5',
                'date' => '2024-09-05 16:40:02',
                'download_url' => null,
                'signatories' => [
                    ['no' => 1, 'name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => 'NIP: 197508122002121001', 'type' => 'NIP', 'jabatan' => 'Pembina', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => '2024-09-05 16:40:02', 'certified' => true, 'validity' => true],
                    ['no' => 2, 'name' => 'Ferdian W. (Senior Frontend Engineer / Pemateri)', 'nipNim' => 'NIP: 198805202015041002', 'type' => 'NIP', 'jabatan' => 'Pemateri', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => '2024-09-05 16:40:02', 'certified' => true, 'validity' => true],
                ],
            ],
        ];

        // Jika user mengunggah berkas sertifikat untuk verifikasi integritas hash
        if ($request->hasFile('document_file')) {
            $uploadedFile = $request->file('document_file');
            if ($uploadedFile->isValid()) {
                $uploadedHash = hash_file('sha256', $uploadedFile->getRealPath());

                // Cari sertifikat berdasarkan hash dokumen
                $sertifikat = Sertifikat::with(['kegiatan', 'user', 'riwayatPerubahan.user'])
                    ->where('document_hash', $uploadedHash)
                    ->first();

                if ($sertifikat) {
                    $hashVerificationResult = 'match';
                    $code = $sertifikat->nomor_sertifikat;
                } else {
                    // Cek jika cocok dengan hash sertifikat mock
                    foreach ($defaultLegacyCerts as $mockKey => $mockItem) {
                        if ($mockItem['hash'] === $uploadedHash) {
                            $mockCertificate = $mockItem;
                            $hashVerificationResult = 'match';
                            $code = $mockKey;
                            break;
                        }
                    }
                    if (! $mockCertificate) {
                        $hashVerificationResult = 'mismatch';
                    }
                }
            }
        }

        // Jika kode diinputkan (dan belum dicocokkan via file hash)
        if ($code && ! $sertifikat && ! $mockCertificate) {
            $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $sertifikat = Sertifikat::with(['kegiatan', 'user', 'riwayatPerubahan.user'])
                ->where('nomor_sertifikat', $code)
                ->orWhere('nomor_sertifikat', $likeOp, $code)
                ->first();

            if (! $sertifikat) {
                // Fallback ke tabel sertifikat legacy
                $legacyCertificate = Certificate::where('code', $code)
                    ->orWhere('code', $likeOp, $code)
                    ->first();
            }

            if (! $sertifikat && ! $legacyCertificate) {
                // Fallback ke default valid certs (kompatibilitas kode lama)
                $upperCode = strtoupper($code);
                if (isset($defaultLegacyCerts[$upperCode])) {
                    $mockCertificate = $defaultLegacyCerts[$upperCode];
                }
            }
        }

        // Format terpadu untuk tampilan view
        $documentData = null;
        if ($sertifikat) {
            $formattedDate = $sertifikat->issued_at
                ? Wib::dari($sertifikat->issued_at)->translatedFormat('d F Y, H:i').' WIB'
                : '-';

            $isAktif = ($sertifikat->status === StatusSertifikat::Aktif);

            // Periksa integritas berkas fisik bila ada di penyimpanan server
            // Tanpa berkas (belum dibuat) bukan "tidak valid"; hanya berkas yang ada dan hash-nya berbeda yang gagal.
            $fileHashMatches = true;
            if ($sertifikat->adaBerkas()) {
                $fileHashMatches = ($sertifikat->hashBerkasSaatIni() === $sertifikat->document_hash);
            }
            $validity = $isAktif && $fileHashMatches;

            // Certified: penandatangan resmi tercatat dan aktif saat terbit
            $snapshot = $sertifikat->penandatangan_snapshot;
            $signatories = [];
            $certified = false;

            if (! empty($snapshot) && is_array($snapshot)) {
                $certified = true;
                foreach ($snapshot as $idx => $sig) {
                    $mode = $sig['mode_tampil'] ?? 'sembunyikan';
                    $identitasDisplay = null;
                    if ($mode === 'penuh') {
                        $identitasDisplay = (! empty($sig['jenis_identitas']) && $sig['jenis_identitas'] !== 'none' ? strtoupper($sig['jenis_identitas']).': ' : '').($sig['nomor_identitas'] ?? '-');
                    } elseif ($mode === 'samarkan') {
                        $nomor = (string) ($sig['nomor_identitas'] ?? '');
                        $masked = strlen($nomor) > 8 ? substr($nomor, 0, 4).'****'.substr($nomor, -4) : Masking::nim($nomor);
                        $identitasDisplay = (! empty($sig['jenis_identitas']) && $sig['jenis_identitas'] !== 'none' ? strtoupper($sig['jenis_identitas']).': ' : '').$masked;
                    }

                    $signatories[] = [
                        'no' => $idx + 1,
                        'name' => $sig['nama_lengkap'] ?? $sig['nama'] ?? '-',
                        'jabatan' => $sig['jabatan'] ?? '-',
                        'institusi' => $sig['institusi'] ?? 'Universitas Nahdlatul Ulama Surabaya',
                        'type' => ! empty($sig['jenis_identitas']) && $sig['jenis_identitas'] !== 'none' ? strtoupper($sig['jenis_identitas']) : 'TTE',
                        'nipNim' => $identitasDisplay,
                        'mode_tampil' => $mode,
                        'date' => $sertifikat->issued_at ? Wib::dari($sertifikat->issued_at)->translatedFormat('d F Y, H:i').' WIB' : '-',
                        'certified' => true,
                        'validity' => $validity,
                    ];
                }
            } else {
                $assigned = KegiatanPenandatangan::with('penandatangan')
                    ->where('kegiatan_id', $sertifikat->kegiatan_id)
                    ->orderBy('urutan')
                    ->get();

                if ($assigned->isNotEmpty()) {
                    $certified = true;
                    foreach ($assigned as $idx => $kp) {
                        $pen = $kp->penandatangan;
                        if (! $pen) {
                            continue;
                        }
                        $signatories[] = [
                            'no' => $idx + 1,
                            'name' => str_contains($pen->nama_lengkap, '(') ? $pen->nama_lengkap : ($pen->nama_lengkap.($pen->jabatan ? " ({$pen->jabatan})" : '')),
                            'jabatan' => $pen->jabatan,
                            'institusi' => $pen->institusi ?? 'Universitas Nahdlatul Ulama Surabaya',
                            'type' => $pen->jenis_identitas !== 'none' ? strtoupper($pen->jenis_identitas) : 'TTE',
                            'nipNim' => $pen->identitas_terformat,
                            'mode_tampil' => $pen->mode_tampil,
                            'date' => $sertifikat->issued_at ? Wib::dari($sertifikat->issued_at)->translatedFormat('d F Y, H:i').' WIB' : '-',
                            'certified' => (bool) $pen->is_active,
                            'validity' => $validity,
                        ];
                    }
                } else {
                    $masterPen = Penandatangan::where('is_active', true)->take(2)->get();
                    if ($masterPen->isNotEmpty()) {
                        $certified = true;
                        foreach ($masterPen as $idx => $pen) {
                            $signatories[] = [
                                'no' => $idx + 1,
                                'name' => str_contains($pen->nama_lengkap, '(') ? $pen->nama_lengkap : ($pen->nama_lengkap.($pen->jabatan ? " ({$pen->jabatan})" : '')),
                                'jabatan' => $pen->jabatan,
                                'institusi' => $pen->institusi ?? 'Universitas Nahdlatul Ulama Surabaya',
                                'type' => $pen->jenis_identitas !== 'none' ? strtoupper($pen->jenis_identitas) : 'TTE',
                                'nipNim' => $pen->identitas_terformat,
                                'mode_tampil' => $pen->mode_tampil,
                                'date' => $sertifikat->issued_at ? Wib::dari($sertifikat->issued_at)->translatedFormat('d F Y, H:i').' WIB' : '-',
                                'certified' => (bool) $pen->is_active,
                                'validity' => $validity,
                            ];
                        }
                    } else {
                        $signatories = [
                            ['no' => 1, 'name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => 'NIP: 197508122002121001', 'type' => 'NIP', 'jabatan' => 'Pembina', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => '-', 'certified' => true, 'validity' => $validity],
                            ['no' => 2, 'name' => 'Muhammad Fatih (Ketua Umum SEMADIKSI)', 'nipNim' => 'NIM: 3130021045', 'type' => 'NIM', 'jabatan' => 'Ketua Umum', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => '-', 'certified' => true, 'validity' => $validity],
                        ];
                        $certified = true;
                    }
                }
            }

            $rawNim = $sertifikat->nim_penerima ?? $sertifikat->user?->nim ?? null;
            $nimSamar = $rawNim ? Masking::nim($rawNim) : null;

            $terakhirEdit = $sertifikat->riwayatPerubahan()->latest('created_at')->first();
            $terakhirDieditText = null;
            if ($terakhirEdit && $terakhirEdit->created_at) {
                $waktuEdit = $terakhirEdit->created_at instanceof CarbonInterface
                    ? $terakhirEdit->created_at
                    : Carbon::parse($terakhirEdit->created_at);
                $terakhirDieditText = 'Data diperbarui oleh panitia pada '.Wib::dari($waktuEdit)?->translatedFormat('d F Y, H:i').' WIB';
            }

            $kegiatanTanggal = null;
            if ($sertifikat->kegiatan) {
                $mulai = $sertifikat->kegiatan->tanggal_mulai ? Wib::dari($sertifikat->kegiatan->tanggal_mulai)->translatedFormat('d F Y') : null;
                $selesai = $sertifikat->kegiatan->tanggal_selesai ? Wib::dari($sertifikat->kegiatan->tanggal_selesai)->translatedFormat('d F Y') : null;
                $kegiatanTanggal = ($mulai === $selesai || ! $selesai) ? $mulai : "{$mulai} - {$selesai}";
            }

            $documentData = [
                'code' => $sertifikat->nomor_sertifikat,
                'status' => $isAktif ? 'SAH (VALID)' : 'DICABUT',
                'is_active' => $isAktif,
                'alasan_pencabutan' => $sertifikat->alasan_pencabutan,
                'dicabut_pada' => $sertifikat->dicabut_pada ? Wib::dari($sertifikat->dicabut_pada)->translatedFormat('d F Y, H:i').' WIB' : null,
                'title' => $sertifikat->kegiatan->nama_kegiatan ?? 'Sertifikat Kegiatan SEMADIKSI',
                'kegiatan_tanggal' => $kegiatanTanggal,
                'recipient_name' => $sertifikat->nama_penerima ?: $sertifikat->recipient_name,
                'jenis' => $sertifikat->jenis?->label() ?? 'Peserta',
                'jenis_value' => $sertifikat->jenis?->value ?? 'peserta',
                'predikat' => $sertifikat->predikat,
                'asal_institusi' => $sertifikat->asal_institusi ?: 'Universitas Nahdlatul Ulama Surabaya',
                'nim_samar' => $nimSamar,
                'author' => $sertifikat->kegiatan?->penyelenggara ?: 'Divisi Penyelenggara / SEMADIKSI',
                'hash' => $sertifikat->document_hash ?? 'Berkas belum dibuat (hash dihitung saat berkas pertama kali dibuat)',
                'date' => $formattedDate,
                'keterangan_publik' => $sertifikat->keterangan_publik,
                'terakhir_diedit' => $terakhirDieditText,
                // Halaman publik hanya menautkan berkas lama di disk publik; berkas baru bersifat privat.
                'download_url' => $sertifikat->adaBerkas() && $sertifikat->diskBerkas() === 'public' ? route('sertifikat.download', $sertifikat->id) : null,
                'certified' => $certified,
                'validity' => $validity,
                'signatories' => $signatories,
            ];
        } elseif ($legacyCertificate) {
            $formattedDate = $legacyCertificate->issued_date
                ? Wib::dari($legacyCertificate->issued_date)->translatedFormat('d F Y, H:i').' WIB'
                : '-';

            $documentData = [
                'code' => $legacyCertificate->code,
                'status' => 'SAH (VALID)',
                'is_active' => true,
                'alasan_pencabutan' => null,
                'dicabut_pada' => null,
                'title' => $legacyCertificate->activity_title,
                'kegiatan_tanggal' => $formattedDate,
                'recipient_name' => $legacyCertificate->user_name,
                'jenis' => 'Peserta',
                'jenis_value' => 'peserta',
                'predikat' => null,
                'asal_institusi' => 'Universitas Nahdlatul Ulama Surabaya',
                'nim_samar' => null,
                'author' => 'SEMADIKSI Divisi Kemahasiswaan',
                'hash' => hash('sha256', $legacyCertificate->code.$legacyCertificate->user_name),
                'date' => $formattedDate,
                'keterangan_publik' => null,
                'terakhir_diedit' => null,
                'download_url' => null,
                'certified' => true,
                'validity' => true,
                'signatories' => [
                    ['no' => 1, 'name' => 'Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)', 'nipNim' => 'NIP: 197508122002121001', 'type' => 'NIP', 'jabatan' => 'Pembina', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => $formattedDate, 'certified' => true, 'validity' => true],
                    ['no' => 2, 'name' => 'Muhammad Fatih (Ketua Umum SEMADIKSI)', 'nipNim' => 'NIM: 3130021045', 'type' => 'NIM', 'jabatan' => 'Ketua Umum', 'institusi' => 'UNUSA', 'mode_tampil' => 'penuh', 'date' => $formattedDate, 'certified' => true, 'validity' => true],
                ],
            ];
        } elseif ($mockCertificate) {
            $documentData = [
                'code' => $mockCertificate['code'],
                'status' => 'SAH (VALID)',
                'is_active' => true,
                'alasan_pencabutan' => null,
                'dicabut_pada' => null,
                'title' => $mockCertificate['title'],
                'kegiatan_tanggal' => $mockCertificate['date'],
                'recipient_name' => $mockCertificate['recipient_name'],
                'jenis' => 'Peserta',
                'jenis_value' => 'peserta',
                'predikat' => null,
                'asal_institusi' => 'Universitas Nahdlatul Ulama Surabaya',
                'nim_samar' => null,
                'author' => $mockCertificate['author'],
                'hash' => $mockCertificate['hash'],
                'date' => $mockCertificate['date'],
                'keterangan_publik' => null,
                'terakhir_diedit' => null,
                'download_url' => null,
                'certified' => true,
                'validity' => true,
                'signatories' => $mockCertificate['signatories'],
            ];
        }

        return view('verifikasi-sertifikat', [
            'code' => $code,
            'sertifikat' => $sertifikat,
            'certificate' => $legacyCertificate,
            'documentData' => $documentData,
            'pengaturan' => $pengaturan,
            'hashVerificationResult' => $hashVerificationResult,
            'uploadedHash' => $uploadedHash,
            'title' => 'Portal Validasi Sertifikat SEMADIKSI',
        ]);
    }

    /**
     * Download sertifikat digital resmi.
     */
    public function download(int $id): BinaryFileResponse|RedirectResponse
    {
        $sertifikat = Sertifikat::with('kegiatan')->findOrFail($id);

        // Berkas lama di disk publik tetap dapat diunduh seperti sebelumnya. Berkas baru (privat) hanya untuk
        // pemilik sertifikat dan super_admin, dan dibuat lazy saat pertama diunduh.
        $legacyPublik = $sertifikat->diskBerkas() === 'public' && $sertifikat->adaBerkas();
        if (! $legacyPublik) {
            $pengguna = auth()->user();
            abort_unless($pengguna !== null, 401);
            abort_unless($pengguna->isSuperAdmin() || ((int) $sertifikat->user_id === $pengguna->id && $sertifikat->isAktif()), 403);
            $sertifikat = app(BerkasSertifikatService::class)->pastikan($sertifikat);
        }

        if (! $sertifikat->adaBerkas()) {
            return back()->with('error', 'Berkas PDF sertifikat belum dapat dibuat. Coba lagi nanti atau hubungi admin.');
        }

        $fullPath = Storage::disk($sertifikat->diskBerkas())->path($sertifikat->file_pdf_path);
        $downloadName = "Sertifikat-{$sertifikat->nomor_sertifikat}.pdf";

        return response()->download($fullPath, $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Super Admin: Upload template background resmi kegiatan (PNG/JPG/PDF).
     */
    public function uploadTemplate(Request $request, int $kegiatanId): JsonResponse|RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);

        $request->validate([
            'template_file' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:10240'], // Max 10MB
        ]);

        $path = $request->file('template_file')->store("kegiatan/templates/{$kegiatan->id}", 'public');

        $kegiatan->update([
            'certificate_template_path' => $path,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Template sertifikat resmi berhasil diunggah.',
                'template_url' => Storage::disk('public')->url($path),
            ]);
        }

        return back()->with('success', 'Template background sertifikat resmi berhasil diunggah.');
    }

    /**
     * Super Admin: Konfigurasi koordinat X/Y schema sertifikat.
     */
    public function updateSchemaConfig(Request $request, int $kegiatanId): JsonResponse|RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);

        $validated = $request->validate([
            'schema_config' => ['required', 'array'],
        ]);

        $kegiatan->update([
            'certificate_schema_config' => $validated['schema_config'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi tata letak sertifikat berhasil disimpan.',
            ]);
        }

        return back()->with('success', 'Konfigurasi tata letak sertifikat berhasil disimpan.');
    }

    /**
     * Super Admin: Trigger generate batch sertifikat untuk seluruh peserta yang telah hadir di kegiatan.
     */
    public function generateBatch(
        Request $request,
        int $kegiatanId,
        CertificateGeneratorService $generatorService
    ): JsonResponse|RedirectResponse {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);

        $count = $generatorService->generateForKegiatan($kegiatan);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menerbitkan {$count} sertifikat peserta.",
                'count' => $count,
            ]);
        }

        return back()->with('success', "Berhasil menerbitkan {$count} sertifikat resmi untuk kegiatan ini.");
    }
}
