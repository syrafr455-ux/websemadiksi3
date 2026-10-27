<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\CustomFormSubmission;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSubmission;
use App\Models\KipkDocument;
use App\Models\Sertifikat;
use App\Models\User;
use App\Services\CertificateGeneratorService;
use App\Services\EmailProtectionService;
use App\Services\Kegiatan\KelayakanSertifikatService;
use App\Support\CsvSanitizer;
use App\Support\MenuAdmin;
use App\Support\Wib;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /**
     * Admin Overview Dashboard.
     */
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user && $user->role === UserRole::PenilaiKarya) {
            return redirect()->route('admin.penilaian');
        }

        if ($user && $user->role === UserRole::VerifikatorBerkas) {
            return redirect()->route('admin.berkas');
        }

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Akses terbatas untuk Super Administrator.');
        }

        $mahasiswa = User::where('role', UserRole::MahasiswaKipk->value);

        // Sumber tunggal pencairan: submisi formulir 'pencairan', difilter per semester berjalan (created_at).
        $periode = $this->periodeSemesterBerjalan();
        $pencairan = fn () => CustomFormSubmission::whereHas('form', fn ($q) => $q->where('form_key', 'pencairan'));
        $dalamPeriode = fn () => $pencairan()
            ->where('created_at', '>=', $periode['mulai']->copy()->utc())
            ->where('created_at', '<', $periode['selesai']->copy()->utc());

        $stats = [
            'total_students' => (clone $mahasiswa)->count(),
            'active_students' => (clone $mahasiswa)->where('is_blocked', false)->count(),
            'pending_verifications' => User::where('verification_status', 'Pending')->count(),
            'total_kegiatan' => Kegiatan::count(),
            'kegiatan_aktif' => Kegiatan::bookable()->count(),
            'sertifikat_terbit' => Sertifikat::where('is_valid', true)->count(),
            'sertifikat_dicabut' => Sertifikat::where('is_valid', false)->count(),
            'presensi_akhir' => KegiatanPresensi::count(),
            'tiket_hadir' => KegiatanRegistration::where('status_kehadiran', AttendanceStatus::CheckedIn->value)->count(),
            'pending_documents' => KipkDocument::where('status', 'Menunggu Review')->count(),
            'total_submissions' => CustomFormSubmission::count(),
            'pencairan' => [
                'periode' => $periode['label'],
                'mulai' => $periode['mulai'],
                'selesai' => $periode['selesai']->copy()->subSecond(),
                'menunggu' => $dalamPeriode()->where('status', 'Menunggu Review')->count(),
                'disetujui' => $dalamPeriode()->where('status', 'Disetujui')->count(),
                'menunggu_sebelumnya' => $pencairan()
                    ->where('status', 'Menunggu Review')
                    ->where('created_at', '<', $periode['mulai']->copy()->utc())
                    ->count(),
            ],
            // Tabel model warisan yang masih ditulis fitur lama; ditampilkan terpisah, tidak dijumlahkan.
            'legacy' => [
                'kegiatan' => Activity::count(),
                'sertifikat' => Certificate::count(),
                'presensi' => Attendance::count(),
            ],
        ];

        $recentStudents = (clone $mahasiswa)->latest('id')->take(5)->get();
        $recentPresensi = KegiatanPresensi::with('kegiatan')->latest('check_in_at')->latest('id')->take(5)->get();
        $recentDocs = KipkDocument::latest('id')->take(5)->get();
        $emailQuotaStatus = app(EmailProtectionService::class)->getQuotaStatus();
        $menungguPersetujuanAnda = MenuAdmin::hitungMenungguPersetujuan($user);

        return view('admin.dashboard', compact('stats', 'recentStudents', 'recentPresensi', 'recentDocs', 'emailQuotaStatus', 'menungguPersetujuanAnda'));
    }

    /**
     * Semester akademik yang sedang berjalan menurut config('unusa.semester').
     *
     * @return array{label: string, mulai: Carbon, selesai: Carbon} 'selesai' eksklusif (awal semester berikutnya).
     */
    private function periodeSemesterBerjalan(): array
    {
        $tz = config('unusa.semester.timezone', 'Asia/Jakarta');
        $gasalMonth = (int) config('unusa.semester.gasal_start_month', 8);
        $genapMonth = (int) config('unusa.semester.genap_start_month', 2);
        $now = Carbon::now($tz);

        $awal = collect([$now->year - 1, $now->year, $now->year + 1])
            ->flatMap(fn (int $year) => [
                ['nama' => 'Gasal', 'mulai' => Carbon::create($year, $gasalMonth, 1, 0, 0, 0, $tz)],
                ['nama' => 'Genap', 'mulai' => Carbon::create($year, $genapMonth, 1, 0, 0, 0, $tz)],
            ])
            ->sortBy(fn (array $s) => $s['mulai']->getTimestamp())
            ->values();

        // Rentang tahun-1..tahun+1 menjamin selalu ada semester berjalan (index >= 1) dan semester berikutnya.
        $index = $awal->filter(fn (array $s) => $s['mulai']->lte($now))->keys()->last();
        $berjalan = $awal[$index];

        // Tahun akademik dimulai pada semester Gasal; Genap ikut tahun akademik Gasal tepat sebelumnya.
        $tahunAwal = $berjalan['nama'] === 'Gasal' ? $berjalan['mulai']->year : $awal[$index - 1]['mulai']->year;

        return [
            'label' => sprintf('%s %d/%d', $berjalan['nama'], $tahunAwal, $tahunAwal + 1),
            'mulai' => $berjalan['mulai'],
            'selesai' => $awal[$index + 1]['mulai'],
        ];
    }

    /**
     * Manage Students / Users (/admin/pengguna - route name: admin.users).
     * Compatible with PostgreSQL & Supabase schema.
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Search text case-insensitively using PostgreSQL 'ilike'
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($w) use ($q) {
                $w->where('name', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%")
                    ->orWhere('nim', 'ilike', "%{$q}%")
                    ->orWhere('prodi', 'ilike', "%{$q}%");
            });
        }

        // Hanya nilai enum UserRole yang dipakai sebagai filter.
        if ($role = UserRole::tryFrom((string) $request->input('role'))) {
            $query->where('role', $role->value);
        }

        // Safe order by existing primary key or created_at
        $users = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        // If Inertia is installed/used, render Inertia component
        if (class_exists(Inertia::class)) {
            return Inertia::render('Admin/Pengguna', [
                'title' => 'Data Pengguna',
                'users' => $users,
                'filters' => $request->only(['q', 'role']),
            ]);
        }

        return view('admin.users.index', compact('users'));
    }

    public function toggleBlockUser(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin() && $user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $user->is_blocked = ! (bool) $user->is_blocked;
        $user->save();

        $status = $user->is_blocked ? 'dinonaktifkan' : 'diaktifkan kembali';

        return back()->with('success', "Akun {$user->name} berhasil {$status}.");
    }

    public function verifyUser(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $user->verification_status = 'Verified';
        $user->save();

        return back()->with('success', "Akun {$user->name} berhasil diverifikasi.");
    }

    /**
     * Manage Activities (Kegiatan).
     */
    public function kegiatan(): View
    {
        $activities = Activity::withCount('registrations')->latest('id')->paginate(10);

        return view('admin.kegiatan.index', compact('activities'));
    }

    public function storeKegiatan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'type' => ['required', 'string'],
            'date_text' => ['required', 'string'],
            'location' => ['required', 'string'],
            'price' => ['required', 'string'],
            'quota' => ['required', 'integer', 'min:1'],
            'xp_points' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'speaker' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $imageUrl = 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&auto=format&fit=crop&q=80';
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('activities', 'public');
            $imageUrl = Storage::url($path);
        }

        Activity::create([
            'title' => trim($validated['title']),
            'category' => $validated['category'],
            'type' => $validated['type'],
            'date_text' => trim($validated['date_text']),
            'location' => trim($validated['location']),
            'price' => trim($validated['price']),
            'quota' => (int) $validated['quota'],
            'xp_points' => (int) $validated['xp_points'],
            'description' => $validated['description'],
            'speaker' => $validated['speaker'],
            'image_url' => $imageUrl,
            'is_latest' => true,
            'is_closed' => false,
            'enable_seat_booking' => $request->boolean('enable_seat_booking'),
            'require_file_upload' => $request->boolean('require_file_upload'),
        ]);

        return back()->with('success', 'Kegiatan baru berhasil ditambahkan.');
    }

    public function deleteKegiatan(int $id): RedirectResponse
    {
        $activity = Activity::findOrFail($id);
        $activity->delete();

        return back()->with('success', 'Kegiatan berhasil dihapus.');
    }

    /**
     * Manage Certificates (Alur Baru - Tabel sertifikat & Kegiatan).
     */
    public function certificates(): View
    {
        $sertifikats = Sertifikat::with(['kegiatan', 'user'])->latest('id')->paginate(15);
        $kegiatans = Kegiatan::with([
            'presensis:id,kegiatan_id,user_id,nama_lengkap,email',
            'registrations' => fn ($q) => $q->active()->select('id', 'kegiatan_id', 'participant_user_id', 'participant_name', 'participant_identity'),
        ])->orderBy('nama_kegiatan')->get();
        // Penerima sertifikat = mahasiswa_kipk & user_umum (tidak membaca role 'student')
        $students = User::whereIn('role', [UserRole::MahasiswaKipk->value, UserRole::UserUmum->value])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'nim']);

        return view('admin.certificates.index', compact('sertifikats', 'kegiatans', 'students'));
    }

    public function storeCertificate(Request $request, CertificateGeneratorService $generatorService): RedirectResponse
    {
        $validated = $request->validate([
            'kegiatan_id' => ['required', 'exists:kegiatan,id'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', Rule::exists('users', 'id')->whereIn('role', UserRole::participantValues())],
            'code' => ['nullable', 'string', 'max:100'],
            'nomor_sertifikat' => ['nullable', 'string', 'max:100'],
            'certificate_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $kegiatan = Kegiatan::findOrFail($validated['kegiatan_id']);
        $namaPenerima = trim($validated['nama_lengkap']);
        $nomorSeri = trim($validated['nomor_sertifikat'] ?? $validated['code'] ?? '');

        // Cari user jika ada user_id atau berdasarkan nama
        $user = null;
        if (! empty($validated['user_id']) && is_numeric($validated['user_id'])) {
            $user = User::find($validated['user_id']);
        }
        if (! $user) {
            $user = User::where('name', $namaPenerima)->first();
        }

        // Validasi keunikan nomor sertifikat jika diisi manual
        if ($nomorSeri && Sertifikat::where('nomor_sertifikat', $nomorSeri)->exists()) {
            return back()->withInput()->with('error', "Nomor seri sertifikat {$nomorSeri} sudah pernah digunakan.");
        }

        $sertifikat = $generatorService->publishFromAdmin(
            kegiatan: $kegiatan,
            namaPenerima: $namaPenerima,
            emailPenerima: $user?->email,
            user: $user,
            customNomor: $nomorSeri ?: null,
            uploadedPdf: $request->file('certificate_file')
        );

        return back()->with('success', "Sertifikat resmi untuk {$namaPenerima} ({$sertifikat->nomor_sertifikat}) berhasil diterbitkan dan distamp dengan QR validasi.");
    }

    public function deleteCertificate(int $id): RedirectResponse
    {
        $sertifikat = Sertifikat::find($id);
        if ($sertifikat) {
            if ($sertifikat->file_pdf_path && Storage::disk('public')->exists($sertifikat->file_pdf_path)) {
                Storage::disk('public')->delete($sertifikat->file_pdf_path);
            }
            $sertifikat->delete();

            return back()->with('success', 'Sertifikat resmi berhasil dihapus.');
        }

        // Fallback jika menghapus ID legacy Certificate
        $legacy = Certificate::find($id);
        if ($legacy) {
            $legacy->delete();

            return back()->with('success', 'Sertifikat berhasil dihapus.');
        }

        return back()->with('error', 'Sertifikat tidak ditemukan.');
    }

    /**
     * Presensi & Kehadiran Akhir Kegiatan (Super Admin Only).
     * Menampilkan rekap check-in fase 1, presensi akhir fase 2, tanda tangan digital, dan sertifikat dalam WIB.
     */
    public function attendances(Request $request): View
    {
        Gate::authorize('viewAny', KegiatanPresensi::class);

        $kegiatanList = Kegiatan::latest('tanggal_mulai')->get(['id', 'nama_kegiatan', 'tanggal_mulai', 'status']);

        $selectedKegiatanId = $request->input('kegiatan_id');
        $selectedKegiatan = null;

        if ($selectedKegiatanId) {
            $selectedKegiatan = Kegiatan::find($selectedKegiatanId);
        } elseif ($kegiatanList->isNotEmpty()) {
            $selectedKegiatan = $kegiatanList->first();
            $selectedKegiatanId = $selectedKegiatan->id;
        }

        $participants = collect();
        $stats = [
            'total_registered' => 0,
            'total_checkin_or_gabung' => 0,
            'phase1_checked_in' => 0,
            'phase2_present' => 0,
            'total_karya' => 0,
            'total_layak' => 0,
            'certificates_issued' => 0,
            'total_gabung' => 0,
        ];

        if ($selectedKegiatan) {
            $allParticipants = $this->getAttendanceParticipants($selectedKegiatan, new Request);
            $stats['total_registered'] = $allParticipants->count();
            $stats['total_checkin_or_gabung'] = $allParticipants->filter(fn ($p) => $p->is_phase1 || $p->is_gabung)->count();
            $stats['phase1_checked_in'] = $allParticipants->where('is_phase1', true)->count();
            $stats['phase2_present'] = $allParticipants->where('is_phase2', true)->count();
            $stats['total_karya'] = $allParticipants->where('has_submission', true)->count();
            $stats['total_layak'] = $allParticipants->where('is_layak', true)->count();
            $stats['certificates_issued'] = $allParticipants->where('is_certificate', true)->count();
            $stats['total_gabung'] = $allParticipants->where('is_gabung', true)->count();

            $participants = $this->getAttendanceParticipants($selectedKegiatan, $request);
        }

        return view('admin.attendances.index', [
            'kegiatanList' => $kegiatanList,
            'selectedKegiatan' => $selectedKegiatan,
            'participants' => $participants,
            'stats' => $stats,
        ]);
    }

    /**
     * Ekspor Rekapitulasi Presensi Kegiatan ke CSV / Excel (WIB & formula neutralization).
     */
    public function exportAttendances(Request $request): StreamedResponse|RedirectResponse
    {
        Gate::authorize('export', KegiatanPresensi::class);

        $selectedKegiatanId = $request->input('kegiatan_id');
        $selectedKegiatan = $selectedKegiatanId ? Kegiatan::find($selectedKegiatanId) : Kegiatan::latest('tanggal_mulai')->first();

        if (! $selectedKegiatan) {
            return back()->with('error', 'Pilih kegiatan terlebih dahulu untuk mengekspor data.');
        }

        $participants = $this->getAttendanceParticipants($selectedKegiatan, $request);
        $filename = 'rekap_presensi_'.Str::slug($selectedKegiatan->nama_kegiatan).'_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($participants, $selectedKegiatan) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 agar Excel membuka dengan encoding yang benar
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            $headers = [
                'No',
                'Nama Peserta',
                'NIM / Identitas',
                'Email',
                'Program Studi / Institusi',
                'Nomor Tiket',
                'Kursi',
                'Status Check-In Fase 1',
                'Waktu Check-In (WIB)',
                'Status Presensi Akhir Fase 2',
                'Waktu Presensi Akhir (WIB)',
                'Status Tanda Tangan Digital',
            ];

            if ($selectedKegiatan->isLomba()) {
                $headers[] = 'Status Pengumpulan Karya';
            }

            $headers[] = 'Status Kelayakan Sertifikat';
            $headers[] = 'Alasan Kelayakan';
            $headers[] = 'Status Sertifikat';
            $headers[] = 'Nomor Sertifikat';

            fputcsv($handle, CsvSanitizer::netralkanBaris($headers));

            $no = 1;
            foreach ($participants as $p) {
                $row = [
                    $no++,
                    $p->name,
                    $p->nim,
                    $p->email,
                    $p->prodi,
                    $p->ticket_number,
                    $p->seat_code,
                    $p->phase1_status,
                    $p->phase1_time ?? '-',
                    $p->phase2_status,
                    $p->phase2_time ?? '-',
                    $p->presensi ? 'Tersedia (Terverifikasi)' : 'Belum Ada',
                ];

                if ($selectedKegiatan->isLomba()) {
                    $row[] = $p->has_submission ? 'Sudah Mengumpulkan' : 'Belum Mengumpulkan';
                }

                $row[] = $p->is_layak ? 'Layak' : 'Belum Layak';
                $row[] = $p->alasan_kelayakan;
                $row[] = $p->certificate_status;
                $row[] = $p->certificate_number ?? '-';

                fputcsv($handle, CsvSanitizer::netralkanBaris($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Pratinjau tanda tangan digital aman (Hanya Super Admin via Policy).
     */
    public function signaturePreview(int $id)
    {
        $presensi = KegiatanPresensi::findOrFail($id);
        Gate::authorize('viewSignature', $presensi);

        $path = $presensi->signature_path;
        if (empty($path)) {
            abort(404, 'Tanda tangan tidak ditemukan.');
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path);
        }

        if (file_exists($path)) {
            return response()->file($path);
        }

        abort(404, 'Berkas tanda tangan tidak ditemukan di penyimpanan.');
    }

    /**
     * Helper koleksi peserta kegiatan beserta status fase 1, fase 2, tanda tangan, kelayakan, dan sertifikat (WIB).
     */
    protected function getAttendanceParticipants(Kegiatan $selectedKegiatan, Request $request)
    {
        $registrations = KegiatanRegistration::with(['participantUser', 'bookerUser', 'seat', 'gabung'])
            ->where('kegiatan_id', $selectedKegiatan->id)
            ->get();

        $presensis = KegiatanPresensi::with('sertifikat')
            ->where('kegiatan_id', $selectedKegiatan->id)
            ->get();

        $sertifikats = Sertifikat::where('kegiatan_id', $selectedKegiatan->id)->get();
        $submissions = KegiatanSubmission::where('kegiatan_id', $selectedKegiatan->id)->get()->keyBy('registration_id');
        $kelayakanService = app(KelayakanSertifikatService::class);

        $presensiByRegId = $presensis->whereNotNull('registration_id')->keyBy('registration_id');
        $presensiByNim = $presensis->filter(fn ($p) => ! empty($p->nim))->keyBy('nim');
        $presensiByEmail = $presensis->filter(fn ($p) => ! empty($p->email))->keyBy('email');

        $sertifikatByPresensiId = $sertifikats->whereNotNull('presensi_id')->keyBy('presensi_id');
        $sertifikatByUserId = $sertifikats->whereNotNull('user_id')->keyBy('user_id');
        $sertifikatByEmail = $sertifikats->filter(fn ($s) => ! empty($s->email_penerima))->keyBy('email_penerima');

        $handledPresensiIds = collect();
        $participants = collect();

        foreach ($registrations as $reg) {
            $user = $reg->participantUser ?: $reg->bookerUser;
            $nim = $reg->participant_identity ?: ($user?->nim ?? '-');
            $email = $reg->participant_email ?: ($user?->email ?? '-');
            $name = $reg->participant_name ?: ($user?->name ?? 'Peserta');

            $presensi = $presensiByRegId->get($reg->id)
                ?? ($nim !== '-' ? $presensiByNim->get($nim) : null)
                ?? ($email !== '-' ? $presensiByEmail->get($email) : null);

            if ($presensi) {
                $handledPresensiIds->push($presensi->id);
            }

            $sertifikat = null;
            if ($presensi && $sertifikatByPresensiId->has($presensi->id)) {
                $sertifikat = $sertifikatByPresensiId->get($presensi->id);
            } elseif ($user && $sertifikatByUserId->has($user->id)) {
                $sertifikat = $sertifikatByUserId->get($user->id);
            } elseif ($email !== '-' && $sertifikatByEmail->has($email)) {
                $sertifikat = $sertifikatByEmail->get($email);
            }

            $isPhase1CheckedIn = ($reg->status_kehadiran === AttendanceStatus::CheckedIn || $reg->status_kehadiran?->value === 'checked_in');
            $isPhase2Present = $presensi !== null;
            $isCertificateIssued = $sertifikat !== null;

            $phase1Time = ($isPhase1CheckedIn && $reg->checked_in_at)
                ? Wib::dari($reg->checked_in_at)->translatedFormat('d M Y, H:i').' WIB'
                : null;

            $phase2Time = ($presensi && $presensi->check_in_at)
                ? Wib::dari($presensi->check_in_at)->translatedFormat('d M Y, H:i').' WIB'
                : null;

            $gabung = $reg->gabung;
            $isGabung = $gabung !== null;
            $gabungTime = $gabung?->pertama_pada ? Wib::dari($gabung->pertama_pada)->translatedFormat('d M Y, H:i').' WIB' : null;
            $gabungCount = (int) ($gabung?->jumlah ?? 0);

            $submission = $submissions->get($reg->id);
            $hasSubmission = $submission !== null;

            $evaluasi = $kelayakanService->evaluasiPeserta($selectedKegiatan, $reg, $presensi, $hasSubmission);

            $participants->push((object) [
                'id' => 'reg-'.$reg->id,
                'registration_id' => $reg->id,
                'presensi_id' => $presensi?->id,
                'name' => $name,
                'nim' => $nim,
                'email' => $email,
                'prodi' => $presensi?->prodi ?? ($user?->programStudi?->nama ?? ($user?->university ?? 'UNUSA')),
                'ticket_number' => $reg->nomor_tiket ?: ($reg->ticket_code ?: '-'),
                'seat_code' => $reg->seat?->seat_code ?? 'Tanpa Kursi',
                'phase1_status' => $isPhase1CheckedIn ? 'Sudah Check-In' : 'Belum Check-In',
                'is_phase1' => $isPhase1CheckedIn,
                'phase1_time' => $phase1Time,
                'phase2_status' => $isPhase2Present ? 'Sudah Presensi Akhir' : 'Belum Presensi',
                'is_phase2' => $isPhase2Present,
                'phase2_time' => $phase2Time,
                'is_gabung' => $isGabung,
                'gabung_time' => $gabungTime,
                'gabung_count' => $gabungCount,
                'presensi' => $presensi,
                'signature_url' => $presensi ? route('admin.attendances.signature', $presensi->id) : null,
                'screenshot_url' => ($presensi && $presensi->tangkapan_layar_path) ? route('admin.attendances.screenshot', $presensi->id) : null,
                'certificate_status' => $isCertificateIssued ? 'Sudah Terbit' : 'Belum Terbit',
                'is_certificate' => $isCertificateIssued,
                'certificate_number' => $sertifikat?->nomor_sertifikat,
                'certificate_download_url' => $sertifikat ? route('sertifikat.download', $sertifikat->id) : null,
                'is_layak' => $evaluasi['layak'],
                'alasan_kelayakan' => $evaluasi['alasan'],
                'has_submission' => $hasSubmission,
                'submission' => $submission,
            ]);
        }

        foreach ($presensis as $p) {
            if ($handledPresensiIds->contains($p->id)) {
                continue;
            }

            $sertifikat = $sertifikatByPresensiId->get($p->id)
                ?? ($p->user_id ? $sertifikatByUserId->get($p->user_id) : null);

            $phase2Time = $p->check_in_at
                ? Wib::dari($p->check_in_at)->translatedFormat('d M Y, H:i').' WIB'
                : null;

            $participants->push((object) [
                'id' => 'pres-'.$p->id,
                'registration_id' => null,
                'presensi_id' => $p->id,
                'name' => $p->nama_lengkap,
                'nim' => $p->nim,
                'email' => $p->email,
                'prodi' => $p->prodi,
                'ticket_number' => '-',
                'seat_code' => 'Tanpa Kursi',
                'phase1_status' => 'Presensi Mandiri Langsung',
                'is_phase1' => false,
                'phase1_time' => null,
                'phase2_status' => 'Sudah Presensi Akhir',
                'is_phase2' => true,
                'phase2_time' => $phase2Time,
                'is_gabung' => false,
                'gabung_time' => null,
                'gabung_count' => 0,
                'presensi' => $p,
                'signature_url' => route('admin.attendances.signature', $p->id),
                'screenshot_url' => $p->tangkapan_layar_path ? route('admin.attendances.screenshot', $p->id) : null,
                'certificate_status' => $sertifikat ? 'Sudah Terbit' : 'Belum Terbit',
                'is_certificate' => $sertifikat !== null,
                'certificate_number' => $sertifikat?->nomor_sertifikat,
                'certificate_download_url' => $sertifikat ? route('sertifikat.download', $sertifikat->id) : null,
                'is_layak' => false,
                'alasan_kelayakan' => 'Tidak memiliki data registrasi resmi',
                'has_submission' => false,
                'submission' => null,
            ]);
        }

        // Pencarian
        if ($request->filled('q')) {
            $q = strtolower(trim($request->input('q')));
            $participants = $participants->filter(function ($item) use ($q) {
                return str_contains(strtolower($item->name), $q)
                    || str_contains(strtolower($item->nim), $q)
                    || str_contains(strtolower($item->email), $q)
                    || str_contains(strtolower($item->ticket_number), $q)
                    || str_contains(strtolower($item->seat_code), $q);
            });
        }

        // Filter Fase 1
        if ($request->filled('fase1') && $request->input('fase1') !== 'all') {
            $fase1 = $request->input('fase1');
            if ($fase1 === 'checked_in') {
                $participants = $participants->where('is_phase1', true);
            } elseif ($fase1 === 'not_checked_in') {
                $participants = $participants->where('is_phase1', false);
            }
        }

        // Filter Fase 2
        if ($request->filled('fase2') && $request->input('fase2') !== 'all') {
            $fase2 = $request->input('fase2');
            if ($fase2 === 'present') {
                $participants = $participants->where('is_phase2', true);
            } elseif ($fase2 === 'not_present') {
                $participants = $participants->where('is_phase2', false);
            }
        }

        // Filter Sertifikat
        if ($request->filled('sertifikat') && $request->input('sertifikat') !== 'all') {
            $sert = $request->input('sertifikat');
            if ($sert === 'issued') {
                $participants = $participants->where('is_certificate', true);
            } elseif ($sert === 'not_issued') {
                $participants = $participants->where('is_certificate', false);
            }
        }

        // Filter Kelayakan Sertifikat & Karya
        if ($request->filled('kelayakan') && $request->input('kelayakan') !== 'all') {
            $kel = $request->input('kelayakan');
            if ($kel === 'layak') {
                $participants = $participants->where('is_layak', true);
            } elseif ($kel === 'belum_layak') {
                $participants = $participants->where('is_layak', false);
            } elseif ($kel === 'terbit') {
                $participants = $participants->where('is_certificate', true);
            } elseif ($kel === 'belum_terbit') {
                $participants = $participants->where('is_certificate', false);
            } elseif ($kel === 'karya_terkumpul') {
                $participants = $participants->where('has_submission', true);
            } elseif ($kel === 'karya_belum') {
                $participants = $participants->where('has_submission', false);
            }
        }

        return $participants;
    }

    public function updateAttendanceStatus(int $id, Request $request): RedirectResponse
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->status = $request->input('status', 'Hadir');
        $attendance->notes = $request->input('notes');
        $attendance->save();

        return back()->with('success', 'Status presensi diperbarui.');
    }

    /**
     * Manage KIP-K Documents.
     */
    public function berkas(): View
    {
        Gate::authorize('viewAny', KipkDocument::class);
        // Cakupan ditegakkan di query: verifikator hanya melihat berkas mahasiswa fakultas/prodinya.
        $documents = KipkDocument::terlihatOleh(auth()->user())
            ->with('user.programStudi')
            ->latest('id')
            ->paginate(15);

        return view('admin.berkas.index', compact('documents'));
    }

    public function downloadDoc(int $id)
    {
        $doc = KipkDocument::findOrFail($id);
        Gate::authorize('download', $doc);

        $relativePath = $doc->file_url ? str_replace('/storage/', '', parse_url($doc->file_url, PHP_URL_PATH)) : null;
        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->download($relativePath, $doc->file_name ?? 'berkas-kipk.pdf');
        }

        if ($doc->file_url) {
            return redirect($doc->file_url);
        }

        return back()->with('error', 'File berkas tidak ditemukan.');
    }

    public function updateDocStatus(int $id, Request $request): RedirectResponse
    {
        $doc = KipkDocument::findOrFail($id);
        Gate::authorize('verify', $doc);

        $doc->status = $request->input('status', 'Disetujui');
        $doc->score = (int) $request->input('score', 100);
        $doc->notes = $request->input('notes');
        $doc->verified_at = now();
        $doc->verified_by = auth()->user()->name;
        $doc->save();

        return back()->with('success', 'Status dan penilaian berkas berhasil disimpan.');
    }

    /**
     * Manage Custom Form Submissions.
     */
    public function submissions(): View
    {
        Gate::authorize('viewAny', CustomFormSubmission::class);
        // Verifikator: hanya formulir monev & pelaporan (config unusa.verifikator_form_keys) dari mahasiswa dalam cakupannya.
        $submissions = CustomFormSubmission::terlihatOleh(auth()->user())
            ->with(['form', 'user.programStudi'])
            ->latest('id')
            ->paginate(15);

        return view('admin.submissions.index', compact('submissions'));
    }

    public function updateSubmissionStatus(int $id, Request $request): RedirectResponse
    {
        $sub = CustomFormSubmission::findOrFail($id);
        Gate::authorize('review', $sub);
        $sub->status = $request->input('status', 'Disetujui');
        $sub->admin_notes = $request->input('admin_notes');
        $sub->save();

        return back()->with('success', 'Status formulir berhasil diperbarui.');
    }
}
