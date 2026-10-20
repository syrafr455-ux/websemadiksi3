<?php

namespace App\Http\Controllers;

use App\Contracts\KipRecipientProvider;
use App\Enums\UserRole;
use App\Http\Requests\Pengguna\PenggunaRequest;
use App\Http\Requests\Pengguna\RegisterUserUmumRequest;
use App\Mail\AktivasiAkunUnusaMail;
use App\Models\User;
use App\Services\Kip\StatusKipService;
use App\Services\UnusaAcademicService;
use App\Support\Samaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pendaftaran akun dua jalur:
 * - Peserta Umum: isi manual (aturan UserUmumRequest), wajib verifikasi email standar.
 * - Mahasiswa UNUSA: NIM -> link aktivasi bertanda tangan ke email kampus -> data otomatis (tidak bisa diubah)
 *   + WhatsApp & kata sandi. Role ditentukan sistem dari daftar penerima KIP-K.
 */
class PendaftaranController extends Controller
{
    public const MAKS_CEK_PER_IP = 10;

    public const MAKS_CEK_PER_NIM = 3;

    public const JENDELA_DETIK = 3600;

    public const UNIVERSITAS = 'Universitas Nahdlatul Ulama Surabaya';

    public function __construct(
        private readonly UnusaAcademicService $akademik,
        private readonly KipRecipientProvider $kip,
        private readonly StatusKipService $statusKip,
    ) {}

    public function show(): View
    {
        return view('auth.register');
    }

    /**
     * Jalur Peserta Umum.
     */
    public function daftarUmum(Request $request): RedirectResponse
    {
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
     * Jalur Mahasiswa UNUSA, langkah 1: cek NIM lalu kirim link aktivasi ke email kampus.
     * Demi privasi, data lengkap TIDAK ditampilkan sebelum pemilik email kampus membuka link.
     */
    public function cekNimUnusa(Request $request): RedirectResponse
    {
        $data = $request->validate(['nim' => ['required', 'string', 'max:30']], ['nim.required' => 'NIM wajib diisi.']);
        $nim = trim($data['nim']);

        $kunciIp = 'daftar-unusa:ip:'.$request->ip();
        $kunciNim = 'daftar-unusa:nim:'.hash('sha256', mb_strtolower($nim));

        foreach ([[$kunciIp, self::MAKS_CEK_PER_IP], [$kunciNim, self::MAKS_CEK_PER_NIM]] as [$kunci, $maks]) {
            if (RateLimiter::tooManyAttempts($kunci, $maks)) {
                $menit = (int) ceil(RateLimiter::availableIn($kunci) / 60);

                return $this->hasilUnusa('terlalu_banyak', "Terlalu banyak percobaan. Coba lagi dalam {$menit} menit.", $nim);
            }
        }
        RateLimiter::hit($kunciIp, self::JENDELA_DETIK);
        RateLimiter::hit($kunciNim, self::JENDELA_DETIK);

        $mahasiswa = $this->akademik->cariMahasiswaByNim($nim);

        if (! $mahasiswa || ! $mahasiswa['email_kampus']) {
            return $this->hasilUnusa('tidak_ditemukan', 'Kami belum dapat memproses pendaftaran untuk NIM tersebut secara otomatis. Periksa kembali NIM Anda, atau hubungi admin SEMADIKSI / Biro Kemahasiswaan UNUSA untuk bantuan.', $nim);
        }

        if ($this->sudahPunyaAkun($mahasiswa)) {
            return $this->hasilUnusa('sudah_terdaftar', 'NIM ini sudah memiliki akun SEMADIKSI. Silakan masuk; bila lupa kata sandi, hubungi admin SEMADIKSI.', $nim);
        }

        $berlaku = (int) config('unusa.aktivasi_berlaku_menit', 60);
        $url = URL::temporarySignedRoute('daftar.unusa.aktivasi', now()->addMinutes($berlaku), ['nim' => $mahasiswa['nim']]);

        Mail::to($mahasiswa['email_kampus'])->send(new AktivasiAkunUnusaMail($mahasiswa['nama_lengkap'], $url, $berlaku));

        return $this->hasilUnusa('terkirim', 'Link aktivasi telah dikirim ke '.Samaran::email($mahasiswa['email_kampus']).". Buka email kampus Anda; link berlaku {$berlaku} menit.", $nim);
    }

    /**
     * Langkah 2: halaman aktivasi (dari link email). Data akademik tampil terisi otomatis dan tidak bisa diubah.
     */
    public function aktivasi(Request $request, string $nim): View|Response
    {
        $mahasiswa = $this->mahasiswaDariLink($request, $nim);
        if ($mahasiswa instanceof Response) {
            return $mahasiswa;
        }

        return view('auth.aktivasi-unusa', ['mahasiswa' => $mahasiswa, 'aksi' => $request->fullUrl()]);
    }

    /**
     * Langkah 3: simpan akun. Hanya WhatsApp & kata sandi yang diambil dari input; sisanya dari master.
     */
    public function selesaikanAktivasi(Request $request, string $nim): RedirectResponse|Response
    {
        $mahasiswa = $this->mahasiswaDariLink($request, $nim);
        if ($mahasiswa instanceof Response) {
            return $mahasiswa;
        }

        if ($request->filled('phone')) {
            $request->merge(['phone' => PenggunaRequest::normalisasiNomorHp((string) $request->input('phone'))]);
        }
        $data = $request->validate([
            'phone' => PenggunaRequest::aturanNomorHp(true),
            'password' => [...PenggunaRequest::aturanPassword(true), 'confirmed'],
        ], [
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'phone.regex' => 'Nomor WhatsApp harus nomor Indonesia, contoh 081234567890.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user = DB::transaction(function () use ($mahasiswa, $data) {
            $penerima = $this->kip->cariByNim($mahasiswa['nim']);
            $penerimaBebas = $penerima && ! $penerima->user_id;

            $user = new User([
                'name' => $mahasiswa['nama_lengkap'],
                'email' => strtolower($mahasiswa['email_kampus']),
                'nim' => $mahasiswa['nim'],
                'phone' => $data['phone'],
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
                // Kepemilikan email kampus sudah terbukti lewat link aktivasi.
                'email_verified_at' => now(),
            ])->save();

            if ($penerimaBebas) {
                $this->statusKip->hubungkan($penerima, $user, StatusKipService::CARA_NIM_SAAT_DAFTAR, beriNotifikasi: false);
            }

            return $user->fresh();
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard.index')->with('success', $user->role === UserRole::MahasiswaKipk
            ? 'Akun aktif. Anda tercatat sebagai penerima KIP Kuliah.'
            : 'Akun aktif. Selamat datang di SEMADIKSI!');
    }

    /**
     * Validasi link aktivasi (tanda tangan, masa berlaku, NIM masih ada & belum punya akun).
     */
    private function mahasiswaDariLink(Request $request, string $nim): array|Response
    {
        if (! $request->hasValidSignature()) {
            return response()->view('auth.aktivasi-tidak-valid', [
                'pesan' => 'Link aktivasi tidak valid atau sudah kedaluwarsa. Minta link baru dari halaman pendaftaran.',
            ], 403);
        }

        $mahasiswa = $this->akademik->cariMahasiswaByNim($nim);
        if (! $mahasiswa || ! $mahasiswa['email_kampus']) {
            return response()->view('auth.aktivasi-tidak-valid', [
                'pesan' => 'Data mahasiswa untuk link ini tidak ditemukan. Hubungi admin SEMADIKSI.',
            ], 404);
        }

        if ($this->sudahPunyaAkun($mahasiswa)) {
            return response()->view('auth.aktivasi-tidak-valid', [
                'pesan' => 'NIM ini sudah memiliki akun SEMADIKSI. Silakan masuk.',
                'tautanMasuk' => true,
            ], 409);
        }

        return $mahasiswa;
    }

    private function sudahPunyaAkun(array $mahasiswa): bool
    {
        // NIM hanya unik di antara akun UNUSA; peserta umum kampus lain boleh punya NIM yang sama.
        return User::where(fn ($q) => $q->where('nim', $mahasiswa['nim'])->where('is_unusa', true))
            ->orWhere('email', strtolower((string) $mahasiswa['email_kampus']))
            ->exists();
    }

    private function hasilUnusa(string $status, string $pesan, string $nim): RedirectResponse
    {
        return redirect()->route('register')
            ->withInput(['jalur' => 'unusa', 'nim' => $nim])
            ->with('unusa_status', $status)
            ->with('unusa_pesan', $pesan);
    }
}
