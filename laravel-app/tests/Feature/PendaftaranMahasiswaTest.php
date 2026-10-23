<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\ActivationLinkMail;
use App\Models\AktivasiMahasiswa;
use App\Models\AuditLog;
use App\Models\MasterMahasiswaUnusa;
use App\Models\PenerimaKipk;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\AktivasiMahasiswaService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PendaftaranMahasiswaTest extends TestCase
{
    use RefreshDatabase;

    protected ProgramStudi $gizi;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->gizi = ProgramStudi::where('kode', 'FKES-S1-GIZI')->firstOrFail();

        foreach ([
            ['2241010001', 'Fauzan Hidayat', 'fauzan.hidayat@student.unusa.ac.id', '081234567890', 5, ['Juara 1 Lomba Karya Tulis', 'Juara 2 Debat']],
            ['2241010002', 'Siti Aminah', 'siti.aminah@student.unusa.ac.id', null, 3, []],
        ] as [$nim, $nama, $email, $telepon, $semester, $prestasi]) {
            MasterMahasiswaUnusa::create([
                'nim' => $nim, 'nama_lengkap' => $nama, 'email_kampus' => $email, 'no_telepon' => $telepon,
                'semester' => $semester, 'prestasi' => $prestasi,
                'program_studi_id' => $this->gizi->id, 'angkatan' => '2024', 'status_aktif' => 'aktif',
            ]);
        }

        // Siti tercantum di daftar penerima KIP-K (berdasarkan NIM).
        (new PenerimaKipk(['nama_lengkap' => 'Siti Aminah', 'nim' => '2241010002']))->aturNomor('1234.567.89012.1766.761')->save();
    }

    protected function cari(string $nim, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('daftar.unusa.cek'), ['nim' => $nim]);
    }

    protected function kirim(string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('daftar.unusa.kirim'));
    }

    /** Alur penuh sampai link terkirim; mengembalikan URL aktivasi dari email. */
    protected function linkDariEmail(string $nim = '2241010001'): string
    {
        $this->cari($nim);
        $this->kirim();
        $url = null;
        Mail::assertQueued(ActivationLinkMail::class, function ($mail) use (&$url) {
            $url = $mail->url;

            return true;
        });

        return $url;
    }

    protected function passwordBaru(string $sandi = 'RahasiaKuat1'): array
    {
        return ['password' => $sandi, 'password_confirmation' => $sandi];
    }

    // ---- Pencarian NIM & kartu tersamar --------------------------------------------------------

    public function test_known_nim_shows_masked_card_and_sends_nothing_yet(): void
    {
        $this->cari('2241010001')->assertRedirect(route('register'));

        $halaman = $this->halamanInertia($this->get(route('register'))->assertOk());
        $this->assertSame('Auth/Daftar', $halaman['component']);
        $this->assertSame('siap', $halaman['props']['hasil']['status']);
        $this->assertSame([
            'nim' => '2241XXXX01',
            'nama' => 'FaXXXX HiXXXXX',
            'program_studi' => 'S1 Gizi',
            'semester' => 5,
            'email' => 'faXXXXXXXXXXat@student.unusa.ac.id',
            'telepon' => '0812XXXXXX90',
            'jumlah_prestasi' => 2,
        ], $halaman['props']['hasil']['kartu']);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_props_never_contain_full_personal_data(): void
    {
        $this->cari('2241010001');
        $json = json_encode($this->halamanInertia($this->get(route('register'))));

        foreach (['2241010001', 'Fauzan', 'Hidayat', 'fauzan.hidayat', '081234567890', 'Juara', 'Fakultas Kesehatan', 'Lomba Karya Tulis'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json, "Props memuat data lengkap: {$rahasia}");
        }
        $this->assertStringNotContainsString('fakultas', strtolower($json));
    }

    public function test_activation_page_props_are_masked_too(): void
    {
        $halaman = $this->halamanInertia($this->get($this->linkDariEmail())->assertOk());

        $this->assertSame('Auth/AktivasiUnusa', $halaman['component']);
        $json = json_encode($halaman['props']);
        foreach (['2241010001', 'Fauzan', 'Hidayat', 'fauzan.hidayat', '081234567890'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json);
        }
        $this->assertSame('2241XXXX01', $halaman['props']['mahasiswa']['nim']);
    }

    public function test_unknown_nim_gets_neutral_message_and_no_mail(): void
    {
        $this->cari('9999999999');
        $hasil = $this->halamanInertia($this->get(route('register')))['props']['hasil'];
        $this->assertSame('tidak_ditemukan', $hasil['status']);
        $this->assertSame('NIM tidak ditemukan. Periksa kembali atau hubungi admin.', $hasil['pesan']);
        $this->assertNull($hasil['kartu']);

        // NIM ada di master tetapi tanpa email: pesan sama persis (tidak membocorkan alasan lain).
        MasterMahasiswaUnusa::create(['nim' => '2241010009', 'nama_lengkap' => 'Tanpa Email', 'program_studi_id' => $this->gizi->id]);
        $this->cari('2241010009');
        $this->assertSame($hasil['pesan'], $this->halamanInertia($this->get(route('register')))['props']['hasil']['pesan']);

        // Tanpa pencarian sukses, mengirim tidak mungkin (NIM tidak diterima dari browser).
        $this->kirim();
        Mail::assertNothingQueued();
    }

    public function test_nim_with_existing_account_points_to_login_or_forgot_password(): void
    {
        User::factory()->create(['nim' => '2241010001', 'email' => 'lain@gmail.com', 'is_unusa' => true]);

        $this->cari('2241010001');
        $hasil = $this->halamanInertia($this->get(route('register')))['props']['hasil'];
        $this->assertSame('sudah_aktif', $hasil['status']);
        $this->assertStringContainsString('sudah memiliki akun', $hasil['pesan']);
        $this->assertNull($hasil['kartu']);
        $this->kirim();
        Mail::assertNothingQueued();
    }

    public function test_email_outside_allowed_domains_is_not_mailed(): void
    {
        MasterMahasiswaUnusa::where('nim', '2241010001')->update(['email_kampus' => 'fauzan@gmail.com']);

        $this->cari('2241010001');
        $hasil = $this->halamanInertia($this->get(route('register')))['props']['hasil'];
        $this->assertSame('email_tidak_diizinkan', $hasil['status']);
        $this->assertStringContainsString('Hubungi admin', $hasil['pesan']);
        $this->kirim();
        Mail::assertNothingQueued();

        // Domain dibaca dari config: setelah ditambahkan, NIM yang sama bisa diproses.
        config(['unusa.student_email_domains' => ['student.unusa.ac.id', 'gmail.com']]);
        $this->cari('2241010001');
        $this->assertSame('siap', $this->halamanInertia($this->get(route('register')))['props']['hasil']['status']);
    }

    // ---- Email: hanya ke alamat di master ------------------------------------------------------

    public function test_mail_goes_only_to_the_address_in_the_master_even_if_request_tries_to_change_it(): void
    {
        $this->cari('2241010001');
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->post(route('daftar.unusa.kirim'), ['email' => 'penyerang@gmail.com', 'nim' => '2241010002', 'to' => 'penyerang@gmail.com'])
            ->assertRedirect(route('register'));

        Mail::assertQueued(ActivationLinkMail::class, 1);
        Mail::assertQueued(ActivationLinkMail::class, fn ($mail) => $mail->hasTo('fauzan.hidayat@student.unusa.ac.id')
            && ! $mail->hasTo('penyerang@gmail.com') && ! $mail->hasTo('siti.aminah@student.unusa.ac.id'));
        $this->assertSame('terkirim', $this->halamanInertia($this->get(route('register')))['props']['hasil']['status']);
    }

    public function test_activation_mail_is_queued_with_text_and_html_in_indonesian(): void
    {
        $mail = new ActivationLinkMail('Fauzan Hidayat', 'https://contoh.test/aktivasi', 60);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $mail);
        $mail->assertSeeInHtml('Buat Kata Sandi');
        $mail->assertSeeInText('Link berlaku 60 menit');
        $mail->assertSeeInText('https://contoh.test/aktivasi');
    }

    // ---- Token ---------------------------------------------------------------------------------

    public function test_token_is_64_chars_stored_only_as_sha256_hash_bound_to_nim(): void
    {
        $url = $this->linkDariEmail();
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->assertSame(64, strlen($token));
        $baris = AktivasiMahasiswa::firstOrFail();
        $this->assertSame(hash('sha256', $token), $baris->token_hash);
        $this->assertSame('2241010001', $baris->nim);
        $this->assertNull($baris->dipakai_pada);
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($baris->kedaluwarsa_pada), 1);
        $this->assertStringNotContainsString($token, json_encode(DB::table('aktivasi_mahasiswa')->get()));
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_expired_forged_and_unsigned_links_are_rejected(): void
    {
        $url = $this->linkDariEmail();
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->get(route('daftar.unusa.aktivasi', ['token' => $token]))->assertForbidden();
        $this->get(str_replace($token, str_repeat('a', 64), $url))->assertForbidden();
        $this->get(URL::temporarySignedRoute('daftar.unusa.aktivasi', now()->addHour(), ['token' => str_repeat('b', 64)]))->assertForbidden();

        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden()->assertSee('tidak valid, sudah kedaluwarsa');
        $this->post($url, $this->passwordBaru())->assertForbidden();
        $this->assertSame(0, User::where('nim', '2241010001')->count());
    }

    public function test_token_expiry_is_enforced_by_the_database_row_even_with_a_valid_signature(): void
    {
        $url = $this->linkDariEmail();
        AktivasiMahasiswa::query()->update(['kedaluwarsa_pada' => now()->subSecond()]);

        $this->get($url)->assertForbidden();
    }

    public function test_token_is_single_use_and_reuse_is_rejected(): void
    {
        $url = $this->linkDariEmail();

        $this->post($url, $this->passwordBaru())->assertRedirect(route('login'));
        $this->assertNotNull(AktivasiMahasiswa::firstOrFail()->dipakai_pada);

        $this->get($url)->assertStatus(403);
        $this->post($url, $this->passwordBaru('SandiLain12345'))->assertStatus(403);
        $this->assertSame(1, User::where('nim', '2241010001')->count());
    }

    public function test_new_link_cancels_the_previous_one(): void
    {
        $lama = $this->linkDariEmail();
        $this->travel(61)->seconds();
        $this->kirim();
        $baru = null;
        Mail::assertQueued(ActivationLinkMail::class, function ($mail) use (&$baru, $lama) {
            if ($mail->url !== $lama) {
                $baru = $mail->url;
            }

            return true;
        });

        $this->get($lama)->assertForbidden();
        $this->get($baru)->assertOk();
    }

    // ---- Kirim ulang & rate limit --------------------------------------------------------------

    public function test_resend_has_60_second_gap_and_three_per_day_limit(): void
    {
        $this->cari('2241010001');
        $this->kirim();
        Mail::assertQueued(ActivationLinkMail::class, 1);

        // Jeda 60 detik.
        $this->kirim();
        Mail::assertQueued(ActivationLinkMail::class, 1);
        $this->assertSame('jeda', $this->halamanInertia($this->get(route('register')))['props']['hasil']['status']);

        $this->travel(61)->seconds();
        $this->kirim();
        $this->travel(61)->seconds();
        $this->kirim();
        Mail::assertQueued(ActivationLinkMail::class, 3);

        // Batas 3 per hari per NIM.
        $this->travel(61)->seconds();
        $this->kirim();
        Mail::assertQueued(ActivationLinkMail::class, 3);
        $this->assertSame('harian', $this->halamanInertia($this->get(route('register')))['props']['hasil']['status']);

        // Setelah 24 jam boleh lagi.
        $this->travel(25)->hours();
        $this->kirim();
        Mail::assertQueued(ActivationLinkMail::class, 4);
    }

    public function test_nim_lookup_rate_limit_is_10_per_minute_per_ip(): void
    {
        foreach (range(1, 10) as $i) {
            $this->cari("88000000{$i}", '10.9.9.9');
        }
        $this->assertNotSame('terlalu_banyak', $this->halamanInertia($this->get(route('register')))['props']['hasil']['status']);

        $this->cari('2241010002', '10.9.9.9');
        $hasil = $this->halamanInertia($this->get(route('register')))['props']['hasil'];
        $this->assertSame('terlalu_banyak', $hasil['status']);
        $this->assertNull($hasil['kartu']);

        // IP lain tidak terpengaruh; setelah semenit IP yang sama boleh lagi.
        $this->cari('2241010002', '10.8.8.8');
        $this->assertSame('siap', $this->halamanInertia($this->get(route('register')))['props']['hasil']['status']);
        $this->travel(61)->seconds();
        $this->cari('2241010002', '10.9.9.9');
        $this->assertSame('siap', $this->halamanInertia($this->get(route('register')))['props']['hasil']['status']);
    }

    public function test_nim_lookup_rate_limit_is_30_per_hour_per_ip(): void
    {
        // 3 gelombang x 10 percobaan, masing-masing dipisah lebih dari semenit (batas per menit tidak tercapai).
        foreach (range(1, 3) as $gelombang) {
            foreach (range(1, 10) as $i) {
                $this->cari("77{$gelombang}00000{$i}", '10.7.7.7');
            }
            $this->travel(61)->seconds();
        }

        $this->cari('2241010002', '10.7.7.7');
        $this->assertSame('terlalu_banyak', $this->halamanInertia($this->get(route('register')))['props']['hasil']['status']);

        $this->travel(60)->minutes();
        $this->cari('2241010002', '10.7.7.7');
        $this->assertSame('siap', $this->halamanInertia($this->get(route('register')))['props']['hasil']['status']);
    }

    // ---- Aktivasi & login ----------------------------------------------------------------------

    public function test_activation_creates_verified_account_from_master_and_redirects_to_login(): void
    {
        $url = $this->linkDariEmail();

        $this->post($url, $this->passwordBaru() + [
            // Upaya manipulasi: harus diabaikan.
            'name' => 'Nama Palsu', 'nim' => '000', 'email' => 'palsu@gmail.com', 'role' => 'super_admin', 'phone' => '0800',
        ])->assertRedirect(route('login'));

        $user = User::where('nim', '2241010001')->firstOrFail();
        $this->assertSame('Fauzan Hidayat', $user->name);
        $this->assertSame('fauzan.hidayat@student.unusa.ac.id', $user->email);
        $this->assertSame('081234567890', $user->phone);
        $this->assertSame(UserRole::UserUmum, $user->role);
        $this->assertSame($this->gizi->id, $user->program_studi_id);
        $this->assertSame($this->gizi->fakultas_id, $user->fakultas_id);
        $this->assertTrue($user->is_unusa);
        $this->assertSame('unusa', $user->jalur_pendaftaran);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse(User::where('email', 'palsu@gmail.com')->exists());
        $this->assertGuest();
    }

    public function test_activated_student_can_log_in_with_nim_and_with_email(): void
    {
        $this->post($this->linkDariEmail(), $this->passwordBaru())->assertRedirect(route('login'));
        $user = User::where('nim', '2241010001')->firstOrFail();

        // Metode (a): NIM + kata sandi.
        $this->post(route('login.post'), ['identity' => '2241010001', 'password' => 'RahasiaKuat1'])->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard.index'))->assertOk();
        $this->post(route('logout'));
        $this->assertGuest();

        // Metode (a): email kampus + kata sandi.
        $this->post(route('login.post'), ['identity' => 'fauzan.hidayat@student.unusa.ac.id', 'password' => 'RahasiaKuat1'])->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_student_who_has_not_activated_cannot_log_in_and_gets_neutral_message(): void
    {
        $pesan = 'Email/NIM atau kata sandi yang Anda masukkan tidak sesuai.';

        // NIM ada di master tetapi belum aktivasi; kata sandi apa pun ditolak.
        $this->post(route('login.post'), ['identity' => '2241010001', 'password' => 'RahasiaKuat1'])
            ->assertSessionHasErrors(['identity' => $pesan]);
        $this->post(route('login.post'), ['identity' => 'fauzan.hidayat@student.unusa.ac.id', 'password' => 'RahasiaKuat1'])
            ->assertSessionHasErrors(['identity' => $pesan]);
        // Pesan identik dengan NIM yang sama sekali tidak ada.
        $this->post(route('login.post'), ['identity' => '9999999999', 'password' => 'RahasiaKuat1'])
            ->assertSessionHasErrors(['identity' => $pesan]);
        $this->assertGuest();
    }

    public function test_password_must_be_at_least_10_chars_with_letters_and_digits(): void
    {
        $url = $this->linkDariEmail();

        foreach (['Pendek12', 'hanyahurufpanjang', '12345678901234', 'RahasiaKuat1'] as $i => $sandi) {
            $respons = $this->from($url)->post($url, ['password' => $sandi, 'password_confirmation' => $sandi]);
            if ($sandi === 'RahasiaKuat1') {
                $respons->assertRedirect(route('login'));
            } else {
                $respons->assertSessionHasErrors('password');
                $this->assertSame(0, User::count(), "Sandi '{$sandi}' seharusnya ditolak");
            }
        }

        $url2 = $this->linkDariEmail('2241010002');
        $this->from($url2)->post($url2, ['password' => 'RahasiaKuat1', 'password_confirmation' => 'beda'])->assertSessionHasErrors('password');
    }

    public function test_nim_on_recipient_list_becomes_mahasiswa_kipk_and_is_linked(): void
    {
        $this->post($this->linkDariEmail('2241010002'), $this->passwordBaru())->assertRedirect(route('login'));

        $user = User::where('nim', '2241010002')->firstOrFail();
        $this->assertSame(UserRole::MahasiswaKipk, $user->role);
        $this->assertNull($user->phone);

        $penerima = PenerimaKipk::where('nim', '2241010002')->first();
        $this->assertSame($user->id, $penerima->user_id);
        $this->assertSame('nim_saat_daftar', $penerima->cara_terhubung);

        $audit = AuditLog::where('action', 'status_kip_berubah')->firstOrFail();
        $this->assertStringNotContainsString('1234567890121766761', json_encode($audit->toArray()));
    }

    // ---- Data master terenkripsi ---------------------------------------------------------------

    public function test_phone_and_achievements_are_encrypted_at_rest(): void
    {
        $mentah = DB::table('master_mahasiswa_unusa')->where('nim', '2241010001')->first();

        $this->assertNotSame('081234567890', $mentah->no_telepon);
        $this->assertStringNotContainsString('081234567890', $mentah->no_telepon);
        $this->assertStringNotContainsString('Juara', $mentah->prestasi);
        $this->assertSame('081234567890', MasterMahasiswaUnusa::where('nim', '2241010001')->first()->no_telepon);
        $this->assertSame(['Juara 1 Lomba Karya Tulis', 'Juara 2 Debat'], MasterMahasiswaUnusa::where('nim', '2241010001')->first()->prestasi);
    }

    // ---- Jalur umum disembunyikan --------------------------------------------------------------

    public function test_register_page_has_no_general_path_and_umum_routes_return_404_by_default(): void
    {
        $this->assertFalse(config('unusa.registrasi_umum_aktif'));

        $halaman = $this->halamanInertia($this->get(route('register'))->assertOk());
        $this->assertNull($halaman['props']['urlUmum']);

        $this->get(route('register.umum'))->assertNotFound();
        $this->post(route('register.post'), [
            'name' => 'Rina Kartika', 'asal_kampus' => 'Universitas Airlangga', 'nim' => '187009', 'program_studi_teks' => 'S1 Biologi',
            'email' => 'rina.kartika@gmail.com', 'phone' => '081298765432', 'password' => 'RahasiaKuat1', 'password_confirmation' => 'RahasiaKuat1',
        ])->assertNotFound();
        $this->assertFalse(User::where('email', 'rina.kartika@gmail.com')->exists());
    }

    public function test_role_user_umum_and_admin_add_user_form_still_work_while_public_path_is_hidden(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'role' => 'user_umum', 'name' => 'Peserta Umum', 'asal_kampus' => 'Universitas Airlangga', 'nim' => '187010',
            'program_studi_teks' => 'S1 Biologi', 'email' => 'peserta.umum@gmail.com', 'phone' => '081298765433', 'password' => 'RahasiaKuat123',
        ])->assertSessionHasNoErrors();

        $this->assertSame(UserRole::UserUmum, User::where('email', 'peserta.umum@gmail.com')->firstOrFail()->role);
    }

    public function test_general_path_works_when_flag_is_enabled_and_requires_email_verification(): void
    {
        config(['unusa.registrasi_umum_aktif' => true]);
        Notification::fake();

        $halaman = $this->halamanInertia($this->get(route('register')));
        $this->assertSame(route('register.umum'), $halaman['props']['urlUmum']);
        $this->get(route('register.umum'))->assertOk()->assertSee('Daftar sebagai Peserta Umum');

        $this->post(route('register.post'), [
            'jalur' => 'umum', 'name' => 'Rina Kartika', 'asal_kampus' => 'Universitas Airlangga', 'nim' => '187009',
            'program_studi_teks' => 'S1 Biologi', 'email' => 'rina.kartika@gmail.com', 'phone' => '081298765432',
            'password' => 'RahasiaKuat1', 'password_confirmation' => 'RahasiaKuat1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'rina.kartika@gmail.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertSame(UserRole::UserUmum, $user->role);
        $this->get(route('dashboard.index'))->assertRedirect(route('verification.notice'));

        $verifikasi = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($verifikasi)->assertRedirect(route('dashboard.index'));
        $this->get(route('dashboard.index'))->assertOk();
    }

    public function test_accounts_not_from_public_umum_path_are_not_blocked_by_email_verification(): void
    {
        $lama = User::factory()->role(UserRole::MahasiswaKipk)->create(['email_verified_at' => null, 'jalur_pendaftaran' => null]);

        $this->actingAs($lama)->get(route('dashboard.index'))->assertOk();
    }

    public function test_service_hashes_tokens_with_sha256(): void
    {
        $this->assertSame(hash('sha256', 'abc'), AktivasiMahasiswaService::hash('abc'));
    }
}
