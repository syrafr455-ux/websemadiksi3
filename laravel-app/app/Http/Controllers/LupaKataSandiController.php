<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pengguna\PenggunaRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Lupa kata sandi untuk semua role.
 *
 * - Respons permintaan selalu netral: tidak membedakan akun terdaftar, tidak terdaftar, atau diblokir.
 * - Rate limit per IP dan per identitas (email/NIM), dihitung untuk setiap permintaan.
 * - Link dikirim ke email yang tercatat di akun (akun Mahasiswa UNUSA boleh meminta dengan NIM).
 * - Token memakai password broker Laravel (tersimpan ter-hash, berlaku config auth.passwords.users.expire menit).
 * - Akun yang diblokir tidak dikirimi link dan tidak bisa mengatur ulang kata sandi.
 */
class LupaKataSandiController extends Controller
{
    public const MAKS_PER_IP = 10;

    public const MAKS_PER_IDENTITAS = 3;

    public const JENDELA_DETIK = 3600;

    public const PESAN_TERKIRIM = 'Jika data yang Anda masukkan cocok dengan akun aktif, link atur ulang kata sandi telah dikirim ke email yang tercatat di akun tersebut. Periksa kotak masuk dan folder spam Anda.';

    public const PESAN_LINK_TIDAK_VALID = 'Link atur ulang kata sandi tidak valid atau sudah kedaluwarsa. Silakan minta link baru.';

    public function form(Request $request): View
    {
        return view('auth.lupa-kata-sandi', [
            'dariAdmin' => $request->query('dari') === 'admin',
            'berlakuMenit' => $this->berlakuMenit(),
        ]);
    }

    public function kirim(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['identity' => ['required', 'string', 'max:255']],
            ['identity.required' => 'Email atau NIM wajib diisi.']
        );
        $identitas = Str::lower(trim($data['identity']));

        $kunciIp = 'lupa-sandi:ip:'.$request->ip();
        $kunciIdentitas = 'lupa-sandi:id:'.hash('sha256', $identitas);
        foreach ([[$kunciIp, self::MAKS_PER_IP], [$kunciIdentitas, self::MAKS_PER_IDENTITAS]] as [$kunci, $maks]) {
            if (RateLimiter::tooManyAttempts($kunci, $maks)) {
                $menit = (int) ceil(RateLimiter::availableIn($kunci) / 60);

                return back()->withInput($request->only('identity'))
                    ->withErrors(['identity' => "Terlalu banyak permintaan. Silakan coba lagi dalam {$menit} menit."]);
            }
        }
        RateLimiter::hit($kunciIp, self::JENDELA_DETIK);
        RateLimiter::hit($kunciIdentitas, self::JENDELA_DETIK);

        $user = $this->cariAkun($identitas);
        if ($user && ! $user->is_blocked) {
            // Broker membuat token baru (ter-hash) lalu memanggil User::sendPasswordResetNotification().
            // Status (termasuk RESET_THROTTLED) sengaja tidak ditampilkan agar respons tetap netral.
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', self::PESAN_TERKIRIM);
    }

    public function formAturUlang(Request $request, string $token): View
    {
        return view('auth.atur-ulang-kata-sandi', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc'],
            'password' => [...PenggunaRequest::aturanPassword(true), 'confirmed'],
        ], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        $tidakValid = fn () => back()->withInput($request->only('email'))->withErrors(['email' => self::PESAN_LINK_TIDAK_VALID]);

        $user = User::whereRaw('LOWER(email) = ?', [Str::lower($data['email'])])->first();
        if (! $user || $user->is_blocked) {
            return $tidakValid();
        }

        $status = Password::broker()->reset(
            ['email' => $user->email, 'token' => $data['token'], 'password' => $data['password']],
            function (User $akun, string $password) {
                $akun->forceFill([
                    'password' => $password, // cast 'hashed'
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($akun));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $tidakValid();
        }

        AuditLog::record($user->id, 'reset_password', "Atur ulang kata sandi lewat link email (ID: {$user->id})", $user);

        return redirect()->route($user->isStaff() ? 'admin.login' : 'login')
            ->with('success', 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru Anda.');
    }

    /**
     * Akun dari email, atau dari NIM untuk akun Mahasiswa UNUSA (NIM hanya unik di antara akun UNUSA).
     */
    protected function cariAkun(string $identitas): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [$identitas])->first()
            ?? User::where('nim', $identitas)->where('is_unusa', true)->first();
    }

    protected function berlakuMenit(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);
    }
}
