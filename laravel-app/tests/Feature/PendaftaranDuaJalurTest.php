<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\AktivasiAkunUnusaMail;
use App\Models\AuditLog;
use App\Models\MasterMahasiswaUnusa;
use App\Models\PenerimaKipk;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PendaftaranDuaJalurTest extends TestCase
{
    use RefreshDatabase;

    protected ProgramStudi $gizi;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->gizi = ProgramStudi::where('kode', 'FKES-S1-GIZI')->firstOrFail();

        foreach ([
            ['2241010001', 'Fauzan Hidayat', 'fauzan.hidayat@student.unusa.ac.id'],
            ['2241010002', 'Siti Aminah', 'siti.aminah@student.unusa.ac.id'],
        ] as [$nim, $nama, $email]) {
            MasterMahasiswaUnusa::create([
                'nim' => $nim, 'nama_lengkap' => $nama, 'email_kampus' => $email,
                'program_studi_id' => $this->gizi->id, 'angkatan' => '2024', 'status_aktif' => 'aktif',
            ]);
        }

        // Siti tercantum di daftar penerima KIP-K (berdasarkan NIM).
        (new PenerimaKipk(['nama_lengkap' => 'Siti Aminah', 'nim' => '2241010002']))->aturNomor('1234.567.89012.1766.761')->save();
    }

    protected function cekNim(string $nim, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('daftar.unusa.cek'), ['nim' => $nim, 'jalur' => 'unusa']);
    }

    protected function linkAktivasi(string $nim, int $menit = 60): string
    {
        return URL::temporarySignedRoute('daftar.unusa.aktivasi', now()->addMinutes($menit), ['nim' => $nim]);
    }

    public function test_register_page_offers_two_paths_without_self_selected_kip_category(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('Daftar Akun SEMADIKSI')
            ->assertSee('Mahasiswa UNUSA')
            ->assertSee('Peserta Umum')
            ->assertDontSee('Kategori Penerima')
            ->assertDontSee('name="kip_status"', false);
    }

    public function test_known_nim_gets_masked_email_and_activation_link_without_revealing_data(): void
    {
        $this->cekNim('2241010001')->assertRedirect(route('register'));

        Mail::assertSent(AktivasiAkunUnusaMail::class, fn ($mail) => $mail->hasTo('fauzan.hidayat@student.unusa.ac.id')
            && str_contains($mail->url, '/daftar/unusa/aktivasi/2241010001') && str_contains($mail->url, 'signature='));

        $halaman = $this->get(route('register'))->assertOk();
        $halaman->assertSee('fa****@student.unusa.ac.id')
            ->assertDontSee('fauzan.hidayat@student.unusa.ac.id')
            ->assertDontSee('Fauzan Hidayat')
            ->assertDontSee('S1 Gizi');
    }

    public function test_unknown_nim_gets_neutral_message_and_no_mail(): void
    {
        $this->cekNim('9999999999');
        $this->get(route('register'))->assertSee('Kami belum dapat memproses pendaftaran untuk NIM tersebut')->assertSee('hubungi admin');

        // NIM terdaftar tetapi tanpa email kampus: pesan sama persis (tidak membocorkan keberadaan NIM).
        MasterMahasiswaUnusa::create(['nim' => '2241010009', 'nama_lengkap' => 'Tanpa Email', 'program_studi_id' => $this->gizi->id]);
        $this->cekNim('2241010009');
        $this->get(route('register'))->assertSee('Kami belum dapat memproses pendaftaran untuk NIM tersebut');

        Mail::assertNothingSent();
    }

    public function test_rate_limit_per_nim_and_per_ip(): void
    {
        foreach (range(1, 3) as $i) {
            $this->cekNim('2241010001', "10.0.0.{$i}");
        }
        $this->cekNim('2241010001', '10.0.0.9');
        $this->get(route('register'))->assertSee('Terlalu banyak percobaan');
        Mail::assertSent(AktivasiAkunUnusaMail::class, 3);

        foreach (range(1, 10) as $i) {
            $this->cekNim("88000000{$i}", '10.9.9.9');
        }
        $this->cekNim('2241010002', '10.9.9.9');
        $this->get(route('register'))->assertSee('Terlalu banyak percobaan');
        Mail::assertNotSent(AktivasiAkunUnusaMail::class, fn ($mail) => $mail->hasTo('siti.aminah@student.unusa.ac.id'));
    }

    public function test_activation_link_shows_auto_filled_data_and_rejects_expired_or_forged_links(): void
    {
        $this->get($this->linkAktivasi('2241010001'))->assertOk()
            ->assertSee('Fauzan Hidayat')->assertSee('2241010001')->assertSee('S1 Gizi')
            ->assertSee('Fakultas Kesehatan')->assertSee('2024')->assertSee('fauzan.hidayat@student.unusa.ac.id')
            ->assertDontSee('name="name"', false)->assertDontSee('name="email"', false);

        $kedaluwarsa = $this->linkAktivasi('2241010001', 60);
        $this->travel(61)->minutes();
        $this->get($kedaluwarsa)->assertForbidden()->assertSee('tidak valid atau sudah kedaluwarsa');
        $this->travelBack();

        $palsu = str_replace('/aktivasi/2241010001', '/aktivasi/2241010002', $this->linkAktivasi('2241010001'));
        $this->get($palsu)->assertForbidden();
        $this->get('/daftar/unusa/aktivasi/2241010001')->assertForbidden();
        $this->post($palsu, ['phone' => '081234567890', 'password' => 'RahasiaKuat1', 'password_confirmation' => 'RahasiaKuat1'])->assertForbidden();
        $this->assertSame(0, User::where('nim', '2241010002')->count());
    }

    public function test_activation_ignores_manipulated_fields_and_creates_user_umum(): void
    {
        $this->post($this->linkAktivasi('2241010001'), [
            'phone' => '+62 812-3456-7890',
            'password' => 'RahasiaKuat1',
            'password_confirmation' => 'RahasiaKuat1',
            // Upaya manipulasi: harus diabaikan.
            'name' => 'Nama Palsu', 'nim' => '000', 'email' => 'palsu@gmail.com', 'role' => 'super_admin',
            'program_studi_id' => ProgramStudi::where('kode', 'FK-S1-PDOK')->value('id'),
        ])->assertRedirect(route('dashboard.index'));

        $user = User::where('nim', '2241010001')->firstOrFail();
        $this->assertSame('Fauzan Hidayat', $user->name);
        $this->assertSame('fauzan.hidayat@student.unusa.ac.id', $user->email);
        $this->assertSame(UserRole::UserUmum, $user->role);
        $this->assertSame($this->gizi->id, $user->program_studi_id);
        $this->assertSame($this->gizi->fakultas_id, $user->fakultas_id);
        $this->assertSame('Universitas Nahdlatul Ulama Surabaya', $user->asal_kampus);
        $this->assertSame('S1 Gizi', $user->program_studi_teks);
        $this->assertSame('081234567890', $user->phone);
        $this->assertSame('unusa', $user->jalur_pendaftaran);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse(User::where('email', 'palsu@gmail.com')->exists());
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard.index'))->assertOk();
    }

    public function test_nim_on_recipient_list_becomes_mahasiswa_kipk_and_is_linked(): void
    {
        $this->post($this->linkAktivasi('2241010002'), [
            'phone' => '081234567891', 'password' => 'RahasiaKuat1', 'password_confirmation' => 'RahasiaKuat1',
        ])->assertRedirect(route('dashboard.index'));

        $user = User::where('nim', '2241010002')->firstOrFail();
        $this->assertSame(UserRole::MahasiswaKipk, $user->role);
        $this->assertSame('KIP UNUSA', $user->kip_status);

        $penerima = PenerimaKipk::where('nim', '2241010002')->first();
        $this->assertSame($user->id, $penerima->user_id);
        $this->assertSame('nim_saat_daftar', $penerima->cara_terhubung);
        $this->assertNotNull($penerima->dicocokkan_pada);

        $audit = AuditLog::where('action', 'status_kip_berubah')->firstOrFail();
        $this->assertStringNotContainsString('1234567890121766761', json_encode($audit->toArray()));
    }

    public function test_activation_validates_phone_and_password(): void
    {
        $link = $this->linkAktivasi('2241010001');
        $this->from($link)->post($link, ['phone' => '0212345678', 'password' => 'pendek', 'password_confirmation' => 'beda'])
            ->assertRedirect($link)
            ->assertSessionHasErrors(['phone', 'password']);
        $this->assertSame(0, User::count());
    }

    public function test_nim_that_already_has_account_is_rejected(): void
    {
        User::factory()->create(['nim' => '2241010001', 'email' => 'lain@gmail.com']);

        $this->cekNim('2241010001');
        $this->get(route('register'))->assertSee('sudah memiliki akun SEMADIKSI')->assertSee(route('login'));
        Mail::assertNothingSent();

        $this->get($this->linkAktivasi('2241010001'))->assertStatus(409)->assertSee('sudah memiliki akun');
        $this->post($this->linkAktivasi('2241010001'), ['phone' => '081234567890', 'password' => 'RahasiaKuat1', 'password_confirmation' => 'RahasiaKuat1'])->assertStatus(409);
        $this->assertSame(1, User::where('nim', '2241010001')->count());
    }

    public function test_peserta_umum_must_verify_email_before_using_dashboard(): void
    {
        Notification::fake();

        $this->post(route('register.post'), [
            'jalur' => 'umum', 'name' => 'Rina Kartika', 'asal_kampus' => 'Universitas Airlangga', 'nim' => '187009',
            'program_studi_teks' => 'S1 Biologi', 'email' => 'rina.kartika@gmail.com', 'phone' => '081298765432',
            'password' => 'RahasiaKuat1', 'password_confirmation' => 'RahasiaKuat1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'rina.kartika@gmail.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertSame(UserRole::UserUmum, $user->role);

        $this->get(route('dashboard.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('rina.kartika@gmail.com');

        $verifikasi = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($verifikasi)->assertRedirect(route('dashboard.index'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get(route('dashboard.index'))->assertOk();
    }

    public function test_accounts_not_from_public_umum_path_are_not_blocked_by_email_verification(): void
    {
        $lama = User::factory()->role(UserRole::MahasiswaKipk)->create(['email_verified_at' => null, 'jalur_pendaftaran' => null]);

        $this->actingAs($lama)->get(route('dashboard.index'))->assertOk();
    }
}
