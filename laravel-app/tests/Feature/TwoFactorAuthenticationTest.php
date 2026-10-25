<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_blocked' => false,
            'email_verified_at' => now(),
        ]);
    }

    protected function createUserUmum(): User
    {
        return User::factory()->create([
            'role' => UserRole::UserUmum,
            'is_blocked' => false,
            'email_verified_at' => now(),
        ]);
    }

    protected function createPenilaiKarya(): User
    {
        return User::factory()->create([
            'role' => UserRole::PenilaiKarya,
            'is_blocked' => false,
            'email_verified_at' => now(),
        ]);
    }

    protected function createMahasiswaKipk(): User
    {
        return User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'is_blocked' => false,
            'email_verified_at' => now(),
        ]);
    }

    protected function createVerifikatorBerkas(): User
    {
        return User::factory()->create([
            'role' => UserRole::VerifikatorBerkas,
            'is_blocked' => false,
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_umum_and_penilai_karya_forced_to_2fa(): void
    {
        $userUmum = $this->createUserUmum();
        $penilai = $this->createPenilaiKarya();

        // user_umum yang belum mengaktifkan 2FA saat sesi password login harus dialihkan ke setup
        $responseUmum = $this->actingAs($userUmum)
            ->withSession(['two_factor_required' => true])
            ->get(route('dashboard.index'));
        $responseUmum->assertRedirect(route('two-factor.setup'));

        // penilai_karya yang belum mengaktifkan 2FA saat sesi password login harus dialihkan ke setup
        $responsePenilai = $this->actingAs($penilai)
            ->withSession(['two_factor_required' => true])
            ->get(route('admin.penilaian'));
        $responsePenilai->assertRedirect(route('two-factor.setup'));

        // Bila sudah mengaktifkan 2FA tapi belum lulus verifikasi di sesi ini, dialihkan ke challenge
        $totp = new TotpService;
        $userUmum->update([
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $totp->generateRecoveryCodes(8)['hashed'],
        ]);

        $responseChallenge = $this->actingAs($userUmum)
            ->withSession(['two_factor_required' => true])
            ->get(route('dashboard.index'));
        $responseChallenge->assertRedirect(route('two-factor.challenge'));
    }

    public function test_other_roles_do_not_require_2fa(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $mahasiswaKipk = $this->createMahasiswaKipk();
        $verifikator = $this->createVerifikatorBerkas();

        // super_admin tidak dialihkan ke 2FA
        $this->actingAs($superAdmin)->get(route('admin.dashboard'))->assertOk();

        // mahasiswa_kipk tidak dialihkan ke 2FA
        $this->actingAs($mahasiswaKipk)->get(route('dashboard.index'))->assertOk();

        // verifikator_berkas tidak dialihkan ke 2FA
        $this->actingAs($verifikator)->get(route('admin.berkas'))->assertOk();
    }

    public function test_sso_login_bypasses_2fa(): void
    {
        $userUmum = $this->createUserUmum();

        // Saat session memiliki flag auth_via_sso, 2FA dilewati
        $response = $this->actingAs($userUmum)
            ->withSession(['auth_via_sso' => true])
            ->get(route('dashboard.index'));

        $response->assertOk();
    }

    public function test_setup_and_confirmation_flow_generates_recovery_codes(): void
    {
        $userUmum = $this->createUserUmum();

        // Buka halaman setup
        $setupResponse = $this->actingAs($userUmum)->get(route('two-factor.setup'));
        $setupResponse->assertOk();
        $setupResponse->assertSee('Aktivasi Autentikasi Dua Faktor');

        $userUmum->refresh();
        $this->assertNotEmpty($userUmum->two_factor_secret);
        $this->assertNull($userUmum->two_factor_confirmed_at);

        // Buat kode TOTP yang valid
        $totp = new TotpService;
        $validCode = $totp->generateCode($userUmum->two_factor_secret);

        // Konfirmasi dengan kode yang valid
        $confirmResponse = $this->actingAs($userUmum)->post(route('two-factor.confirm'), [
            'code' => $validCode,
        ]);

        $confirmResponse->assertRedirect(route('two-factor.recovery-codes'));
        $confirmResponse->assertSessionHas('two_factor_authenticated', true);
        $confirmResponse->assertSessionHas('recovery_codes_plain');

        $userUmum->refresh();
        $this->assertNotNull($userUmum->two_factor_confirmed_at);
        $this->assertCount(8, $userUmum->two_factor_recovery_codes);

        // Halaman recovery codes menampilkan 8 kode
        $recoveryResponse = $this->actingAs($userUmum)->get(route('two-factor.recovery-codes'));
        $recoveryResponse->assertOk();
        $recoveryResponse->assertSee('Simpan Kode Pemulihan 2FA');
    }

    public function test_invalid_code_is_rejected_with_neutral_error(): void
    {
        $totp = new TotpService;
        $userUmum = $this->createUserUmum();
        $userUmum->update([
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $totp->generateRecoveryCodes(8)['hashed'],
        ]);

        // Coba kode salah
        $response = $this->actingAs($userUmum)->post(route('two-factor.challenge.verify'), [
            'code' => '999999',
        ]);

        $response->assertSessionHasErrors(['code' => 'Kode autentikasi tidak valid.']);
        $this->assertFalse(session('two_factor_authenticated', false));
    }

    public function test_recovery_code_is_single_use(): void
    {
        $totp = new TotpService;
        $userUmum = $this->createUserUmum();
        $recoveryData = $totp->generateRecoveryCodes(8);

        $userUmum->update([
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryData['hashed'],
        ]);

        $codeToUse = $recoveryData['plain'][0];

        // Pakai recovery code pertama kali
        $response1 = $this->actingAs($userUmum)->post(route('two-factor.challenge.verify'), [
            'recovery_code' => $codeToUse,
        ]);

        $response1->assertRedirect(route('dashboard.index'));
        $this->assertTrue(session('two_factor_authenticated'));

        $userUmum->refresh();
        $this->assertCount(7, $userUmum->two_factor_recovery_codes);

        // Reset status sesi untuk uji coba pemakaian ulang
        session()->forget('two_factor_authenticated');

        // Coba pakai recovery code yang sama kedua kali (harus ditolak)
        $response2 = $this->actingAs($userUmum)->post(route('two-factor.challenge.verify'), [
            'recovery_code' => $codeToUse,
        ]);

        $response2->assertSessionHasErrors(['code' => 'Kode autentikasi tidak valid.']);
        $this->assertFalse(session('two_factor_authenticated', false));
    }

    public function test_throttle_rate_limiting_after_5_attempts(): void
    {
        $totp = new TotpService;
        $userUmum = $this->createUserUmum();
        $userUmum->update([
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $totp->generateRecoveryCodes(8)['hashed'],
        ]);

        // 5 kali percobaan salah
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($userUmum)->post(route('two-factor.challenge.verify'), [
                'code' => '000000',
            ]);
        }

        // Percobaan ke-6 harus kena rate limit throttle
        $response = $this->actingAs($userUmum)->post(route('two-factor.challenge.verify'), [
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors(['code']);
        $errorMessage = session('errors')->first('code');
        $this->assertStringContainsString('Terlalu banyak percobaan', $errorMessage);
    }

    public function test_super_admin_can_reset_user_2fa_with_mandatory_reason(): void
    {
        $admin = $this->createSuperAdmin();
        $userUmum = $this->createUserUmum();

        $totp = new TotpService;
        $userUmum->update([
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $totp->generateRecoveryCodes(8)['hashed'],
        ]);

        // Non-admin tidak boleh mereset
        $this->actingAs($this->createMahasiswaKipk())
            ->postJson(route('admin.users.reset-2fa', $userUmum->id), ['reason' => 'Reset permintaan'])
            ->assertForbidden();

        // Super admin tanpa alasan harus ditolak validasi
        $this->actingAs($admin)
            ->post(route('admin.users.reset-2fa', $userUmum->id), ['reason' => ''])
            ->assertSessionHasErrors(['reason']);

        // Super admin dengan alasan valid
        $response = $this->actingAs($admin)
            ->post(route('admin.users.reset-2fa', $userUmum->id), [
                'reason' => 'Pengguna kehilangan ponsel dan kunci pemulihan.',
            ]);

        $response->assertSessionHas('success');

        $userUmum->refresh();
        $this->assertNull($userUmum->two_factor_secret);
        $this->assertNull($userUmum->two_factor_confirmed_at);
        $this->assertNull($userUmum->two_factor_recovery_codes);

        // Verifikasi pencatatan audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'reset_two_factor',
        ]);
    }
}
