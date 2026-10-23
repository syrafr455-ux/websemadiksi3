<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Controllers\LupaKataSandiController;
use App\Mail\ResetKataSandiMail;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lupa kata sandi (semua role): respons netral, rate limit, link berbatas waktu, akun diblokir ditolak.
 */
class LupaKataSandiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function minta(string $identitas, string $ip = '10.1.1.1')
    {
        return $this->from(route('password.request'))
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('password.email'), ['identity' => $identitas]);
    }

    /** URL atur ulang dari email terakhir yang dikirim ke $email. */
    protected function urlDariEmail(string $email): string
    {
        $url = null;
        Mail::assertSent(ResetKataSandiMail::class, function (ResetKataSandiMail $mail) use ($email, &$url) {
            if ($mail->hasTo($email)) {
                $url = $mail->url;

                return true;
            }

            return false;
        });

        return $url;
    }

    protected function tokenDari(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    protected function simpan(string $email, string $token, string $password = 'SandiBaru2026')
    {
        return $this->from(route('password.reset', ['token' => $token, 'email' => $email]))
            ->post(route('password.update'), [
                'token' => $token, 'email' => $email, 'password' => $password, 'password_confirmation' => $password,
            ]);
    }

    public function test_login_pages_link_to_the_forgot_password_page(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
        $this->get(route('admin.login'))->assertOk()->assertSee(route('password.request', ['dari' => 'admin']), false);
        $this->get(route('password.request'))->assertOk()->assertSee('Lupa Kata Sandi')->assertSee(route('login'));
        $this->get(route('password.request', ['dari' => 'admin']))->assertOk()->assertSee(route('admin.login'));
    }

    public function test_response_is_neutral_for_registered_unknown_and_blocked_accounts(): void
    {
        User::factory()->userUmum()->create(['email' => 'ada@gmail.com']);
        User::factory()->userUmum()->create(['email' => 'blokir@gmail.com', 'is_blocked' => true]);

        $respons = [];
        foreach (['ada@gmail.com', 'tidak.ada@gmail.com', 'blokir@gmail.com'] as $i => $email) {
            $r = $this->minta($email, "10.1.1.{$i}")->assertRedirect(route('password.request'))->assertSessionHasNoErrors();
            $respons[] = [$r->getStatusCode(), $r->headers->get('Location'), session('status')];
        }

        $this->assertCount(1, array_unique(array_map('serialize', $respons)), 'Respons ketiga kasus harus identik.');
        $this->assertSame(LupaKataSandiController::PESAN_TERKIRIM, $respons[0][2]);
        $this->get(route('password.request'))->assertSee('link atur ulang kata sandi telah dikirim', false);

        Mail::assertSent(ResetKataSandiMail::class, 1);
        Mail::assertSent(ResetKataSandiMail::class, fn ($m) => $m->hasTo('ada@gmail.com'));
    }

    public function test_unusa_student_can_request_with_nim_and_link_goes_to_recorded_email(): void
    {
        User::factory()->userUmum()->create(['nim' => '2241010001', 'email' => 'fauzan@student.unusa.ac.id', 'is_unusa' => true]);
        // Peserta kampus lain dengan NIM yang sama tidak dicocokkan lewat NIM.
        User::factory()->userUmum()->create(['nim' => '2241010001', 'email' => 'lain@gmail.com']);

        $this->minta('2241010001')->assertSessionHas('status', LupaKataSandiController::PESAN_TERKIRIM);

        Mail::assertSent(ResetKataSandiMail::class, 1);
        $url = $this->urlDariEmail('fauzan@student.unusa.ac.id');
        $this->assertStringContainsString('email='.urlencode('fauzan@student.unusa.ac.id'), $url);
    }

    #[DataProvider('semuaRole')]
    public function test_reset_link_changes_password_for_every_role(UserRole $role, string $halamanMasuk): void
    {
        $user = User::factory()->role($role)->create(['email' => 'akun@unusa.ac.id', 'password' => 'SandiLama2026']);

        $this->minta('AKUN@unusa.ac.id');
        $url = $this->urlDariEmail('akun@unusa.ac.id');
        $this->get($url)->assertOk()->assertSee('Atur Ulang Kata Sandi')->assertSee('akun@unusa.ac.id');

        $this->simpan('akun@unusa.ac.id', $this->tokenDari($url))
            ->assertRedirect(route($halamanMasuk))
            ->assertSessionHas('success', 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru Anda.');

        $user->refresh();
        $this->assertTrue(Hash::check('SandiBaru2026', $user->password));
        $this->assertFalse(Hash::check('SandiLama2026', $user->password));
        $this->assertTrue(AuditLog::where('action', 'reset_password')->where('user_id', $user->id)->exists());

        // Link hanya bisa dipakai sekali.
        $this->simpan('akun@unusa.ac.id', $this->tokenDari($url), 'SandiLain2026')->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('SandiBaru2026', $user->fresh()->password));
    }

    public static function semuaRole(): array
    {
        return [
            'super_admin' => [UserRole::SuperAdmin, 'admin.login'],
            'verifikator_berkas' => [UserRole::VerifikatorBerkas, 'admin.login'],
            'penilai_karya' => [UserRole::PenilaiKarya, 'admin.login'],
            'mahasiswa_kipk' => [UserRole::MahasiswaKipk, 'login'],
            'user_umum' => [UserRole::UserUmum, 'login'],
        ];
    }

    public function test_expired_or_forged_links_are_rejected(): void
    {
        $user = User::factory()->userUmum()->create(['email' => 'budi@gmail.com', 'password' => 'SandiLama2026']);
        $lain = User::factory()->userUmum()->create(['email' => 'citra@gmail.com', 'password' => 'SandiLama2026']);

        $this->minta('budi@gmail.com');
        $token = $this->tokenDari($this->urlDariEmail('budi@gmail.com'));

        // Token palsu, dan token milik akun lain.
        $this->simpan('budi@gmail.com', str_repeat('a', 64))->assertSessionHasErrors(['email' => LupaKataSandiController::PESAN_LINK_TIDAK_VALID]);
        $this->simpan('citra@gmail.com', $token)->assertSessionHasErrors('email');

        // Kedaluwarsa: lewat dari masa berlaku (config auth.passwords.users.expire = 60 menit).
        $this->travel(61)->minutes();
        $this->simpan('budi@gmail.com', $token)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('SandiLama2026', $user->fresh()->password));
        $this->assertTrue(Hash::check('SandiLama2026', $lain->fresh()->password));
    }

    public function test_blocked_account_cannot_reset_even_with_a_valid_token(): void
    {
        $user = User::factory()->userUmum()->create(['email' => 'dina@gmail.com', 'password' => 'SandiLama2026']);
        $token = Password::broker()->createToken($user);
        $user->update(['is_blocked' => true]);

        $this->minta('dina@gmail.com')->assertSessionHas('status', LupaKataSandiController::PESAN_TERKIRIM);
        Mail::assertNothingSent();

        $this->simpan('dina@gmail.com', $token)->assertSessionHasErrors(['email' => LupaKataSandiController::PESAN_LINK_TIDAK_VALID]);
        $this->assertTrue(Hash::check('SandiLama2026', $user->fresh()->password));
    }

    public function test_new_password_must_follow_password_rules(): void
    {
        $user = User::factory()->userUmum()->create(['email' => 'eko@gmail.com', 'password' => 'SandiLama2026']);
        $token = Password::broker()->createToken($user);

        $this->simpan('eko@gmail.com', $token, 'pendek')->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('SandiLama2026', $user->fresh()->password));
    }

    public function test_requests_are_rate_limited_per_email_and_per_ip(): void
    {
        User::factory()->userUmum()->create(['email' => 'fajar@gmail.com']);

        // Per email/NIM: 3 permintaan per jam, dari IP berbeda-beda; berlaku sama untuk email yang tidak terdaftar.
        foreach (['fajar@gmail.com', 'tidak.terdaftar@gmail.com'] as $email) {
            foreach (range(1, LupaKataSandiController::MAKS_PER_IDENTITAS) as $i) {
                $this->minta($email, "10.2.{$i}.1")->assertSessionHasNoErrors();
            }
            $this->minta($email, '10.2.9.9')->assertSessionHasErrors('identity');
        }

        // Per IP: 10 permintaan per jam untuk email apa pun.
        foreach (range(1, LupaKataSandiController::MAKS_PER_IP) as $i) {
            $this->minta("orang{$i}@gmail.com", '10.3.3.3')->assertSessionHasNoErrors();
        }
        $this->minta('orang.baru@gmail.com', '10.3.3.3')->assertSessionHasErrors('identity');

        // Setelah satu jam boleh lagi.
        $this->travel(61)->minutes();
        $this->minta('fajar@gmail.com', '10.3.3.3')->assertSessionHasNoErrors();
    }

    public function test_registration_page_tells_active_accounts_to_sign_in_or_reset_password(): void
    {
        $halaman = $this->halamanInertia($this->withSession(['daftar_unusa' => ['status' => 'sudah_aktif', 'pesan' => 'NIM ini sudah memiliki akun SEMADIKSI.', 'kartu' => null]])
            ->get(route('register'))
            ->assertOk());

        $this->assertSame('Auth/Daftar', $halaman['component']);
        $this->assertSame('sudah_aktif', $halaman['props']['hasil']['status']);
        // Tautan Masuk & Lupa Kata Sandi ada di halaman React untuk status ini; rutenya harus ada.
        $this->assertSame('/lupa-kata-sandi', parse_url(route('password.request'), PHP_URL_PATH));
        $this->assertSame('/masuk', parse_url(route('login'), PHP_URL_PATH));
    }
}
