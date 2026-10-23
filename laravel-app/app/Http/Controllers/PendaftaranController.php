<?php

namespace App\Http\Controllers;

use App\Contracts\KipRecipientProvider;
use App\Enums\UserRole;
use App\Http\Requests\Pengguna\RegisterUserUmumRequest;
use App\Mail\ActivationLinkMail;
use App\Models\AktivasiMahasiswa;
use App\Models\User;
use App\Services\AktivasiMahasiswaService;
use App\Services\Kip\StatusKipService;
use App\Services\UnusaAcademicService;
use App\Support\Masking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pendaftaran akun:
 * - Mahasiswa UNUSA: NIM -> kartu konfirmasi tersamar -> link aktivasi ke email kampus di data master
 *   -> buat kata sandi -> /masuk. Data lengkap tidak pernah dikirim ke browser.
 * - Peserta Umum: disembunyikan sementara (config unusa.registrasi_umum_aktif), 404 bila nonaktif.
 */
class PendaftaranController extends Controller
{
    public const MAKS_CARI_PER_MENIT = 10;

    public const MAKS_CARI_PER_JAM = 30;

    public const UNIVERSITAS = 'Universitas Nahdlatul Ulama Surabaya';

    private const SESI_NIM = 'daftar_unusa_nim';

    public function __construct(
        private readonly UnusaAcademicService $akademik,
        private readonly KipRecipientProvider $kip,
        private readonly StatusKipService $statusKip,
        private readonly AktivasiMahasiswaService $aktivasi,
    ) {}

    public function show(): InertiaResponse
    {
        return Inertia::render('Auth/Daftar', [
            'hasil' => session('daftar_unusa'),
            'urlUmum' => config('unusa.registrasi_umum_aktif') ? route('register.umum') : null,
        ]);
    }

    public function showUmum(): View
    {
        $this->pastikanJalurUmumAktif();

        return view('auth.register-umum');
    }

    /**
     * Jalur Peserta Umum (disembunyikan sementara).
     */
    public function daftarUmum(Request $request): RedirectResponse
    {
        $this->pastikanJalurUmumAktif();

        $form = app(RegisterUserUmumRequest::class);

        $user = new User($form->atributPengguna());
        $user->forceFill([
            'role' => UserRole::UserUmum,
            'verification_status' => 'Pending',
            'is_blocked' => false,
            'jalur_pendaftaran' => User::JALUR_UMUM,
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')
            ->with('success', 'Akun dibuat. Buka email Anda dan klik link verifikasi untuk mengaktifkan akun.');
    }

    /**
     * Langkah 1: cari NIM di data master dan tampilkan kartu konfirmasi tersamar.
     */
    public function cekNimUnusa(Request $request): RedirectResponse
    {
        $data = $request->validate(['nim' => ['required', 'string', 'max:30']], ['nim.required' => 'NIM wajib diisi.']);
        $nim = trim($data['nim']);

        if ($pesan = $this->batasIpTercapai($request)) {
            return $this->hasil('terlalu_banyak', $pesan);
        }

        $periksa = $this->periksaNim($nim);
        if ($periksa['status'] !== 'siap') {
            $request->session()->forget(self::SESI_NIM);

            return $this->hasil($periksa['status'], $periksa['pesan']);
        }

        // NIM disimpan di sesi server: langkah kirim tidak menerima NIM atau alamat dari browser.
        $request->session()->put(self::SESI_NIM, $periksa['mahasiswa']['nim']);

        return $this->hasil('siap', '', $this->kartu($periksa['mahasiswa']));
    }

    /**
     * Langkah 2: kirim link aktivasi, otomatis hanya ke email kampus di data master.
     */
    public function kirimLink(Request $request): RedirectResponse
    {
        $nim = (string) $request->session()->get(self::SESI_NIM, '');
        if ($nim === '') {
            return $this->hasil('sesi_habis', 'Sesi pendaftaran berakhir. Masukkan NIM Anda lagi.');
        }

        if ($pesan = $this->batasIpTercapai($request)) {
            return $this->hasil('terlalu_banyak', $pesan);
        }

        $periksa = $this->periksaNim($nim);
        if ($periksa['status'] !== 'siap') {
            return $this->hasil($periksa['status'], $periksa['pesan']);
        }
        $mahasiswa = $periksa['mahasiswa'];
        $kartu = $this->kartu($mahasiswa);

        if ($hambatan = $this->aktivasi->hambatanKirim($mahasiswa['nim'])) {
            $pesan = $hambatan['alasan'] === 'jeda'
                ? "Link baru baru saja dikirim. Tunggu {$hambatan['detik']} detik sebelum mengirim ulang."
                : 'Batas pengiriman link hari ini sudah tercapai. Coba lagi besok atau hubungi admin.';

            return $this->hasil($hambatan['alasan'], $pesan, $kartu);
        }

        $url = $this->aktivasi->buatLink($mahasiswa['nim']);
        $berlaku = (int) config('unusa.aktivasi_berlaku_menit', 60);

        Mail::to($mahasiswa['email_kampus'])->send(new ActivationLinkMail($mahasiswa['nama_lengkap'], $url, $berlaku));

        return $this->hasil('terkirim', 'Link aktivasi dikirim ke '.$kartu['email'].". Buka email kampus Anda; link berlaku {$berlaku} menit.", $kartu);
    }

    /**
     * Halaman membuat kata sandi (dari link email).
     */
    public function aktivasi(Request $request, string $token): InertiaResponse|Response
    {
        $konteks = $this->konteksLink($request, $token);
        if ($konteks instanceof Response) {
            return $konteks;
        }

        return Inertia::render('Auth/AktivasiUnusa', [
            'mahasiswa' => ['nim' => Masking::nim($konteks['nim']), 'nama' => Masking::nama($konteks['mahasiswa']['nama_lengkap'])],
            'aksi' => $request->fullUrl(),
        ]);
    }

    /**
     * Simpan akun: hanya kata sandi yang diambil dari input; data lain dari master.
     */
    public function selesaikanAktivasi(Request $request, string $token): RedirectResponse|Response
    {
        $konteks = $this->konteksLink($request, $token);
        if ($konteks instanceof Response) {
            return $konteks;
        }

        $data = $request->validate([
            'password' => ['required', 'string', Password::min(10)->letters()->numbers(), 'confirmed'],
        ], [
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 10 karakter.',
            'password.letters' => 'Kata sandi harus mengandung huruf.',
            'password.numbers' => 'Kata sandi harus mengandung angka.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $mahasiswa = $konteks['mahasiswa'];

        $berhasil = DB::transaction(function () use ($konteks, $mahasiswa, $data) {
            // Sekali pakai: klaim token secara atomik; permintaan paralel kedua kalah.
            $diklaim = AktivasiMahasiswa::whereKey($konteks['baris']->id)
                ->whereNull('dipakai_pada')
                ->update(['dipakai_pada' => now()]);
            if (! $diklaim) {
                return false;
            }

            $penerima = $this->kip->cariByNim($mahasiswa['nim']);
            $penerimaBebas = $penerima && ! $penerima->user_id;

            $user = new User([
                'name' => $mahasiswa['nama_lengkap'],
                'email' => strtolower($mahasiswa['email_kampus']),
                'nim' => $mahasiswa['nim'],
                'phone' => $mahasiswa['no_telepon'] ?? null,
                'angkatan' => $mahasiswa['angkatan'],
                'prodi' => $mahasiswa['program_studi'],
                'university' => self::UNIVERSITAS,
                'asal_kampus' => self::UNIVERSITAS,
                'program_studi_teks' => $mahasiswa['program_studi'],
                'program_studi_id' => $mahasiswa['program_studi_id'],
                'fakultas_id' => $mahasiswa['fakultas_id'],
                'kip_status' => 'Umum',
                'password' => Hash::make($data['password']),
            ]);
            // Role ditentukan sistem: penerima KIP-K (berdasarkan NIM di daftar) atau user_umum.
            $user->forceFill([
                'role' => UserRole::UserUmum,
                'verification_status' => 'Verified',
                'is_blocked' => false,
                'jalur_pendaftaran' => User::JALUR_UNUSA,
                'is_unusa' => true,
                // Kepemilikan email kampus terbukti lewat link aktivasi.
                'email_verified_at' => now(),
            ])->save();

            if ($penerimaBebas) {
                $this->statusKip->hubungkan($penerima, $user, StatusKipService::CARA_NIM_SAAT_DAFTAR, beriNotifikasi: false);
            }

            return true;
        });

        if (! $berhasil) {
            return $this->linkTidakValid();
        }

        return redirect()->route('login')->with('success', 'Akun aktif. Silakan masuk dengan NIM atau email kampus dan kata sandi yang baru dibuat.');
    }

    /**
     * Validasi link: tanda tangan, token (hash, belum dipakai, belum kedaluwarsa), data master, dan belum ada akun.
     *
     * @return array{baris: AktivasiMahasiswa, nim: string, mahasiswa: array}|Response
     */
    private function konteksLink(Request $request, string $token): array|Response
    {
        if (! $request->hasValidSignature()) {
            return $this->linkTidakValid();
        }

        $baris = $this->aktivasi->temukan($token);
        if (! $baris) {
            return $this->linkTidakValid();
        }

        $mahasiswa = $this->akademik->cariMahasiswaByNim($baris->nim);
        if (! $mahasiswa || ! $mahasiswa['email_kampus'] || ! $this->domainDiizinkan($mahasiswa['email_kampus'])) {
            return $this->linkTidakValid();
        }

        if ($this->sudahPunyaAkun($mahasiswa)) {
            return response()->view('auth.aktivasi-tidak-valid', [
                'pesan' => 'NIM ini sudah memiliki akun SEMADIKSI. Silakan masuk.',
                'tautanMasuk' => true,
            ], 409);
        }

        return ['baris' => $baris, 'nim' => $baris->nim, 'mahasiswa' => $mahasiswa];
    }

    private function linkTidakValid(): Response
    {
        return response()->view('auth.aktivasi-tidak-valid', [
            'pesan' => 'Link aktivasi tidak valid, sudah kedaluwarsa, atau sudah dipakai. Minta link baru dari halaman pendaftaran.',
        ], 403);
    }

    /**
     * @return array{status: string, pesan: string, mahasiswa?: array}
     */
    private function periksaNim(string $nim): array
    {
        $mahasiswa = $this->akademik->cariMahasiswaByNim($nim);

        if (! $mahasiswa || ! $mahasiswa['email_kampus']) {
            return ['status' => 'tidak_ditemukan', 'pesan' => 'NIM tidak ditemukan. Periksa kembali atau hubungi admin.'];
        }

        if ($this->sudahPunyaAkun($mahasiswa)) {
            return ['status' => 'sudah_aktif', 'pesan' => 'NIM ini sudah memiliki akun SEMADIKSI. Silakan masuk; bila lupa kata sandi, atur ulang lewat halaman Lupa Kata Sandi.'];
        }

        if (! $this->domainDiizinkan($mahasiswa['email_kampus'])) {
            return ['status' => 'email_tidak_diizinkan', 'pesan' => 'Email kampus untuk NIM ini tidak dapat dipakai untuk aktivasi otomatis. Hubungi admin SEMADIKSI.'];
        }

        return ['status' => 'siap', 'pesan' => '', 'mahasiswa' => $mahasiswa];
    }

    /**
     * Kartu konfirmasi: hanya nilai tersamar (tanpa fakultas, tanpa detail prestasi).
     *
     * @return array<string, mixed>
     */
    private function kartu(array $mahasiswa): array
    {
        return [
            'nim' => Masking::nim($mahasiswa['nim']),
            'nama' => Masking::nama($mahasiswa['nama_lengkap']),
            'program_studi' => $mahasiswa['program_studi'] ?? '-',
            'semester' => $mahasiswa['semester'] ?? null,
            'email' => Masking::email($mahasiswa['email_kampus']),
            'telepon' => ! empty($mahasiswa['no_telepon']) ? Masking::telepon($mahasiswa['no_telepon']) : '-',
            'jumlah_prestasi' => count($mahasiswa['prestasi'] ?? []),
        ];
    }

    private function domainDiizinkan(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        return $domain !== '' && in_array($domain, config('unusa.student_email_domains', []), true);
    }

    private function sudahPunyaAkun(array $mahasiswa): bool
    {
        // NIM hanya unik di antara akun UNUSA; peserta umum kampus lain boleh punya NIM yang sama.
        return User::where(fn ($q) => $q->where('nim', $mahasiswa['nim'])->where('is_unusa', true))
            ->orWhere('email', strtolower((string) $mahasiswa['email_kampus']))
            ->exists();
    }

    /**
     * Batas pencarian per IP: 10 per menit dan 30 per jam. Mengembalikan pesan bila terlampaui.
     */
    private function batasIpTercapai(Request $request): ?string
    {
        $kunci = [
            ['daftar-unusa:menit:'.$request->ip(), self::MAKS_CARI_PER_MENIT, 60],
            ['daftar-unusa:jam:'.$request->ip(), self::MAKS_CARI_PER_JAM, 3600],
        ];

        foreach ($kunci as [$k, $maks]) {
            if (RateLimiter::tooManyAttempts($k, $maks)) {
                $menit = max(1, (int) ceil(RateLimiter::availableIn($k) / 60));

                return "Terlalu banyak percobaan. Coba lagi dalam {$menit} menit.";
            }
        }
        foreach ($kunci as [$k, , $detik]) {
            RateLimiter::hit($k, $detik);
        }

        return null;
    }

    private function hasil(string $status, string $pesan, ?array $kartu = null): RedirectResponse
    {
        return redirect()->route('register')->with('daftar_unusa', [
            'status' => $status,
            'pesan' => $pesan,
            'kartu' => $kartu,
        ]);
    }

    private function pastikanJalurUmumAktif(): void
    {
        abort_unless(config('unusa.registrasi_umum_aktif'), 404);
    }
}
