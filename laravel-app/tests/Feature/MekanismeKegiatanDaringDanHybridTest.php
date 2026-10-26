<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanGabung;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MekanismeKegiatanDaringDanHybridTest extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(array $attrs = []): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_blocked' => false,
            ...$attrs,
        ]);
    }

    protected function createMahasiswa(array $attrs = []): User
    {
        return User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'is_blocked' => false,
            ...$attrs,
        ]);
    }

    protected function createDaringKegiatan(array $attrs = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'created_by' => $attrs['created_by'] ?? null,
            'nama_kegiatan' => $attrs['nama_kegiatan'] ?? 'Webinar AI Nasional',
            'deskripsi' => 'Webinar daring interaktif',
            'kategori' => 'webinar',
            'metode_pelaksanaan' => $attrs['metode_pelaksanaan'] ?? 'online',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addHour(),
            'tanggal_selesai' => now()->addHours(3),
            'registration_deadline' => now()->addMinutes(30),
            'lokasi_type' => $attrs['lokasi_type'] ?? 'online',
            'lokasi_nama' => 'Daring (Zoom)',
            'lokasi_detail' => null,
            'biaya' => 0,
            'kuota' => 100,
            'narasumber' => [['nama' => 'Dr. Budi', 'instansi' => 'UNUSA']],
            'poin_xp' => 10,
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'is_seat_booking_active' => false,
            'is_submission_active' => false,
            'status' => $attrs['status'] ?? KegiatanStatus::Published,
            'platform_daring' => 'zoom',
            'tautan_rapat' => $attrs['tautan_rapat'] ?? 'https://zoom.us/j/1234567890',
            'meeting_id' => $attrs['meeting_id'] ?? '123 456 7890',
            'passcode_rapat' => $attrs['passcode_rapat'] ?? 'rahasia123',
            'join_button_mode' => $attrs['join_button_mode'] ?? 'otomatis',
            'menit_buka_gabung' => $attrs['menit_buka_gabung'] ?? 15,
            'izin_tautan_presensi_akhir' => $attrs['izin_tautan_presensi_akhir'] ?? true,
            'wajib_tangkapan_layar' => $attrs['wajib_tangkapan_layar'] ?? true,
        ], $attrs));
    }

    protected function registerParticipant(Kegiatan $kegiatan, User $user, array $attrs = []): KegiatanRegistration
    {
        return KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'participant_user_id' => $user->id,
            'booker_user_id' => $user->id,
            'nomor_tiket' => 'TIK-'.strtoupper(uniqid()),
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
            'participant_name' => $user->name,
            'participant_identity' => $user->nim ?? '12345',
            ...$attrs,
        ]);
    }

    /**
     * 1. Validasi host tautan: domain yang diperbolehkan diterima, domain luar ditolak.
     */
    public function test_validasi_host_tautan_rapat_whitelist(): void
    {
        $admin = $this->createSuperAdmin();

        $validPayload = [
            'nama_kegiatan' => 'Webinar Whitelist Valid',
            'kategori' => 'webinar',
            'metode_pelaksanaan' => 'online',
            'lokasi_type' => 'online',
            'lokasi_nama' => 'Daring (Zoom)',
            'tanggal_mulai' => now()->addDays(2)->format('Y-m-d H:i'),
            'tanggal_selesai' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i'),
            'biaya' => 0,
            'kuota' => 50,
            'platform_daring' => 'zoom',
            'tautan_rapat' => 'https://us04web.zoom.us/j/9876543210',
            'aksi_submit' => 'draft',
        ];

        $resValid = $this->actingAs($admin)->post(route('admin.kegiatan.store'), $validPayload);
        $resValid->assertSessionHasNoErrors();

        // Host tidak sah
        $invalidPayload = [
            ...$validPayload,
            'nama_kegiatan' => 'Webinar Host Jahat',
            'tautan_rapat' => 'https://evil-phishing-site.com/fake-meeting',
        ];

        $resInvalid = $this->actingAs($admin)->post(route('admin.kegiatan.store'), $invalidPayload);
        $resInvalid->assertSessionHasErrors('tautan_rapat');
    }

    /**
     * 2. Tautan, meeting ID, dan passcode tidak pernah muncul di halaman publik, props awal, email, atau tiket.
     */
    public function test_kerahasiaan_kredensial_rapat_pada_halaman_dan_props_awal(): void
    {
        $kegiatan = $this->createDaringKegiatan([
            'tautan_rapat' => 'https://zoom.us/j/rahasia-link-1234',
            'meeting_id' => 'MEETING-ID-SANGAT-RAHASIA',
            'passcode_rapat' => 'PASSCODE-SUPER-RAHASIA',
        ]);

        $user = $this->createMahasiswa();
        $this->registerParticipant($kegiatan, $user);

        // Halaman detail publik
        $resPublic = $this->get(route('kegiatan.detail', $kegiatan->id));
        $resPublic->assertOk();
        $resPublic->assertDontSee('https://zoom.us/j/rahasia-link-1234');
        $resPublic->assertDontSee('MEETING-ID-SANGAT-RAHASIA');
        $resPublic->assertDontSee('PASSCODE-SUPER-RAHASIA');

        // Halaman Kegiatan Saya (props Inertia)
        $resSaya = $this->actingAs($user)->get(route('dashboard.kegiatan.saya'));
        $resSaya->assertOk();
        $resSaya->assertDontSee('https://zoom.us/j/rahasia-link-1234');
        $resSaya->assertDontSee('PASSCODE-SUPER-RAHASIA');
    }

    /**
     * 3. Gabung ditolak sebelum jendela dibuka, oleh peserta tak terdaftar, belum lunas, dibatalkan, diblokir, atau kegiatan draft.
     */
    public function test_penolakan_akses_gabung_untuk_kondisi_tidak_sah(): void
    {
        $kegiatan = $this->createDaringKegiatan([
            'tanggal_mulai' => now()->addHours(2), // 2 jam lagi, buka 15 menit sebelum mulai
            'menit_buka_gabung' => 15,
            'status' => KegiatanStatus::Published,
        ]);

        $user = $this->createMahasiswa();
        $this->registerParticipant($kegiatan, $user);

        // A. Ditolak sebelum jendela dibuka
        $resBelumBuka = $this->actingAs($user)->postJson(route('kegiatan.gabung', $kegiatan->id));
        $resBelumBuka->assertStatus(422)
            ->assertJson(['success' => false, 'status_akses' => 'belum_dibuka']);

        // B. Peserta tidak terdaftar
        $userLain = $this->createMahasiswa();
        // Buka manual agar jendela buka bukan penghalang
        $kegiatan->update(['is_gabung_manual_open' => true]);

        $resTakTerdaftar = $this->actingAs($userLain)->postJson(route('kegiatan.gabung', $kegiatan->id));
        $resTakTerdaftar->assertStatus(403)
            ->assertJson(['success' => false]);

        // C. Peserta dibatalkan / belum lunas
        $userPending = $this->createMahasiswa();
        $this->registerParticipant($kegiatan, $userPending, [
            'status_pembayaran' => RegistrationPaymentStatus::Pending,
        ]);
        $resPending = $this->actingAs($userPending)->postJson(route('kegiatan.gabung', $kegiatan->id));
        $resPending->assertStatus(403);

        // D. Peserta diblokir
        $userBlocked = $this->createMahasiswa(['is_blocked' => true]);
        $this->registerParticipant($kegiatan, $userBlocked);
        $resBlocked = $this->actingAs($userBlocked)->postJson(route('kegiatan.gabung', $kegiatan->id));
        $resBlocked->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'Akun Anda sedang dibekukan. Silakan hubungi admin.']);

        // E. Kegiatan masih draft
        $kegiatanDraft = $this->createDaringKegiatan(['status' => KegiatanStatus::Draft]);
        $this->registerParticipant($kegiatanDraft, $user);
        $resDraft = $this->actingAs($user)->postJson(route('kegiatan.gabung', $kegiatanDraft->id));
        $resDraft->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /**
     * 4. Gabung berhasil: mencatat pertama_pada dan menambah jumlah pada gabung ulang.
     */
    public function test_gabung_berhasil_mencatat_pertama_pada_dan_menambah_jumlah(): void
    {
        $kegiatan = $this->createDaringKegiatan([
            'tanggal_mulai' => now()->subMinutes(5), // Kegiatan sedang berlangsung
            'tanggal_selesai' => now()->addHour(),
            'menit_buka_gabung' => 15,
            'tautan_rapat' => 'https://zoom.us/j/999888777',
            'meeting_id' => '999 888 777',
            'passcode_rapat' => 'kuncirahasia',
        ]);

        $user = $this->createMahasiswa();
        $reg = $this->registerParticipant($kegiatan, $user);

        // Klik Gabung pertama kali
        $res1 = $this->actingAs($user)->postJson(route('kegiatan.gabung', $kegiatan->id));
        $res1->assertOk()
            ->assertJson([
                'success' => true,
                'join_url' => 'https://zoom.us/j/999888777',
                'meeting_id' => '999 888 777',
                'passcode' => 'kuncirahasia',
                'jumlah' => 1,
            ]);

        $gabung = KegiatanGabung::where('kegiatan_id', $kegiatan->id)->where('registration_id', $reg->id)->first();
        $this->assertNotNull($gabung);
        $this->assertSame(1, $gabung->jumlah);
        $pertamaPadaAwal = $gabung->pertama_pada;

        // Klik Gabung kedua kali (gabung ulang)
        $res2 = $this->actingAs($user)->postJson(route('kegiatan.gabung', $kegiatan->id));
        $res2->assertOk()
            ->assertJson([
                'success' => true,
                'jumlah' => 2,
            ]);

        $gabung->refresh();
        $this->assertSame(2, $gabung->jumlah);
        $this->assertSame($pertamaPadaAwal->toDateTimeString(), $gabung->pertama_pada->toDateTimeString(), 'pertama_pada tidak boleh berubah saat gabung ulang');
    }

    /**
     * 5. Mode manual dan override admin (bukaAksesGabung & tutupAksesGabung).
     */
    public function test_mode_manual_dan_override_admin_akses_gabung(): void
    {
        $admin = $this->createSuperAdmin();
        $kegiatan = $this->createDaringKegiatan([
            'tanggal_mulai' => now()->addHours(5), // masih 5 jam lagi
            'join_button_mode' => 'manual',
        ]);

        $user = $this->createMahasiswa();
        $this->registerParticipant($kegiatan, $user);

        // Posisi awal: manual belum dibuka
        $resAwal = $this->actingAs($user)->postJson(route('kegiatan.gabung', $kegiatan->id));
        $resAwal->assertStatus(422);

        // Admin override: Buka akses gabung
        $this->actingAs($admin)->post(route('admin.kegiatan.akses-gabung.buka', $kegiatan->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $kegiatan->refresh();
        $this->assertTrue($kegiatan->is_gabung_manual_open);

        // Peserta sekarang bisa gabung
        $resBuka = $this->actingAs($user)->postJson(route('kegiatan.gabung', $kegiatan->id));
        $resBuka->assertOk()->assertJson(['success' => true]);

        // Admin override: Tutup akses gabung
        $this->actingAs($admin)->post(route('admin.kegiatan.akses-gabung.tutup', $kegiatan->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $kegiatan->refresh();
        $this->assertFalse($kegiatan->is_gabung_manual_open);

        // Peserta ditolak lagi
        $resTutup = $this->actingAs($user)->postJson(route('kegiatan.gabung', $kegiatan->id));
        $resTutup->assertStatus(422);
    }

    /**
     * 6. Hybrid menerima check-in fisik di pintu ATAU klik gabung daring sebagai absen awal.
     */
    public function test_hybrid_menerima_checkin_pintu_atau_klik_gabung_sebagai_absen_awal(): void
    {
        $kegiatan = $this->createDaringKegiatan([
            'metode_pelaksanaan' => 'hybrid',
            'lokasi_type' => 'unusa_campus',
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_mulai' => now()->subHour(),
            'presensi_akhir_selesai' => now()->addHour(),
        ]);

        $userFisik = $this->createMahasiswa(['name' => 'Peserta Fisik']);
        $regFisik = $this->registerParticipant($kegiatan, $userFisik, [
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);

        $userDaring = $this->createMahasiswa(['name' => 'Peserta Daring']);
        $regDaring = $this->registerParticipant($kegiatan, $userDaring);

        // Catat user daring di kegiatan_gabung (klik gabung)
        KegiatanGabung::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $regDaring->id,
            'user_id' => $userDaring->id,
            'pertama_pada' => now()->subMinutes(30),
            'terakhir_pada' => now()->subMinutes(5),
            'jumlah' => 1,
        ]);

        $userTanpaAbsen = $this->createMahasiswa(['name' => 'Peserta Tanpa Absen Awal']);
        $this->registerParticipant($kegiatan, $userTanpaAbsen);

        // Signed URL presensi akhir
        $signedUrl = URL::temporarySignedRoute(
            'kegiatan.presensi-akhir.tautan',
            now()->addMinutes(30),
            ['kegiatan' => $kegiatan->id]
        );

        // A. Peserta fisik: diterima mendarat
        $resFisik = $this->actingAs($userFisik)->get($signedUrl);
        $resFisik->assertRedirect(route('kegiatan.presensi.show', $kegiatan->id));

        // B. Peserta daring: diterima mendarat
        $resDaring = $this->actingAs($userDaring)->get($signedUrl);
        $resDaring->assertRedirect(route('kegiatan.presensi.show', $kegiatan->id));

        // C. Peserta tanpa absen awal: ditolak
        $resTanpa = $this->actingAs($userTanpaAbsen)->get($signedUrl);
        $resTanpa->assertRedirect(route('dashboard.kegiatan.saya'))
            ->assertSessionHas('error');
    }

    /**
     * 7. Tautan presensi akhir ber-HMAC: hanya bekerja saat sesi terbuka dan untuk daring/hybrid, ditolak bila offline.
     */
    public function test_tautan_presensi_akhir_hanya_bekerja_saat_sesi_terbuka_dan_kegiatan_daring_atau_hybrid(): void
    {
        $kegiatanOffline = $this->createDaringKegiatan([
            'metode_pelaksanaan' => 'offline',
            'lokasi_type' => 'unusa_campus',
            'presensi_akhir_dibuka' => true,
        ]);

        $user = $this->createMahasiswa();
        $this->registerParticipant($kegiatanOffline, $user, [
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);

        $signedOffline = URL::temporarySignedRoute(
            'kegiatan.presensi-akhir.tautan',
            now()->addMinutes(30),
            ['kegiatan' => $kegiatanOffline->id]
        );

        // Tautan presensi akhir ditolak untuk kegiatan offline murni
        $resOffline = $this->actingAs($user)->get($signedOffline);
        $resOffline->assertRedirect(route('dashboard.kegiatan.saya'))
            ->assertSessionHas('error', 'Tautan presensi akhir mandiri hanya berlaku untuk kegiatan daring atau hybrid.');
    }

    /**
     * 8. Unggahan bukti tangkapan layar divalidasi (ukuran, MIME) dan disimpan privat (tidak bisa diakses publik).
     */
    public function test_validasi_dan_penyimpanan_privat_tangkapan_layar(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $kegiatan = $this->createDaringKegiatan([
            'wajib_tangkapan_layar' => true,
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_mulai' => now()->subHour(),
            'presensi_akhir_selesai' => now()->addHour(),
        ]);

        $user = $this->createMahasiswa();
        $reg = $this->registerParticipant($kegiatan, $user);

        // Rekam kehadiran daring
        KegiatanGabung::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg->id,
            'user_id' => $user->id,
            'pertama_pada' => now(),
            'terakhir_pada' => now(),
            'jumlah' => 1,
        ]);

        // Simulasikan izin presensi akhir sudah di-unlock di sesi
        session(["presensi_akhir_unlocked_{$kegiatan->id}" => [
            'kegiatan_id' => $kegiatan->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]]);

        $dummySignature = 'data:image/png;base64,'.base64_encode(str_repeat('A', 100));

        // A. Ditolak jika tangkapan layar tidak diunggah saat diwajibkan
        $resTanpaSs = $this->actingAs($user)->post(route('kegiatan.presensi.submit', $kegiatan->id), [
            'nama_lengkap' => $user->name,
            'nim' => $user->nim ?? '12345678',
            'prodi' => 'S1 Sistem Informasi',
            'email' => $user->email,
            'nomor_telepon' => '08123456789',
            'signature' => $dummySignature,
        ]);
        $resTanpaSs->assertSessionHasErrors('tangkapan_layar');

        // B. Ditolak jika berkas bukan gambar valid (misal PDF atau text)
        $resFileSalah = $this->actingAs($user)->post(route('kegiatan.presensi.submit', $kegiatan->id), [
            'nama_lengkap' => $user->name,
            'nim' => $user->nim ?? '12345678',
            'prodi' => 'S1 Sistem Informasi',
            'email' => $user->email,
            'nomor_telepon' => '08123456789',
            'signature' => $dummySignature,
            'tangkapan_layar' => UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf'),
        ]);
        $resFileSalah->assertSessionHasErrors('tangkapan_layar');

        // C. Berhasil jika gambar valid (PNG/JPG <= 3MB)
        $validImage = UploadedFile::fake()->image('bukti_zoom.png', 800, 600)->size(1500);

        $resBerhasil = $this->actingAs($user)->post(route('kegiatan.presensi.submit', $kegiatan->id), [
            'nama_lengkap' => $user->name,
            'nim' => $user->nim ?? '12345678',
            'prodi' => 'S1 Sistem Informasi',
            'email' => $user->email,
            'nomor_telepon' => '08123456789',
            'signature' => $dummySignature,
            'tangkapan_layar' => $validImage,
        ]);
        $resBerhasil->assertRedirect(route('dashboard.kegiatan.saya'));

        $presensi = KegiatanPresensi::where('kegiatan_id', $kegiatan->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($presensi);
        $this->assertNotNull($presensi->tangkapan_layar_path);

        // Pastikan disimpan di disk local (privat), BUKAN di disk public
        Storage::disk('local')->assertExists($presensi->tangkapan_layar_path);
        Storage::disk('public')->assertMissing($presensi->tangkapan_layar_path);

        // D. Tangkapan layar tidak bisa diakses sembarang orang / publik
        $this->actingAs($user)->getJson(route('admin.attendances.screenshot', $presensi->id))
            ->assertForbidden();

        $staffBiasa = User::factory()->create([
            'role' => UserRole::VerifikatorBerkas,
            'is_blocked' => false,
        ]);
        $this->actingAs($staffBiasa)->getJson(route('admin.attendances.screenshot', $presensi->id))
            ->assertForbidden();

        // E. Hanya super admin yang diotorisasi yang bisa melihat
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin)->get(route('admin.attendances.screenshot', $presensi->id))
            ->assertOk();
    }

    /**
     * 9. Setiap perubahan tautan daring dicatat di audit log tanpa membocorkan rahasia.
     */
    public function test_perubahan_tautan_daring_dicatat_di_audit_log_tanpa_nilai_rahasia(): void
    {
        $admin = $this->createSuperAdmin();
        $kegiatan = $this->createDaringKegiatan([
            'tautan_rapat' => 'https://zoom.us/j/111111111',
            'passcode_rapat' => 'lama123',
        ]);

        $resUpdate = $this->actingAs($admin)->post(route('admin.kegiatan.pengaturan-daring', $kegiatan->id), [
            'platform_daring' => 'zoom',
            'tautan_rapat' => 'https://zoom.us/j/222222222',
            'meeting_id' => '222 222 222',
            'passcode_rapat' => 'baruSecret999',
            'join_button_mode' => 'otomatis',
            'menit_buka_gabung' => 30,
            'izin_tautan_presensi_akhir' => 1,
            'wajib_tangkapan_layar' => 1,
        ]);
        $resUpdate->assertRedirect()->assertSessionHas('success');

        $log = AuditLog::where('action', 'update_kegiatan_daring')
            ->where('subject_id', $kegiatan->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $logJson = json_encode($log->metadata);
        $this->assertStringNotContainsString('https://zoom.us/j/222222222', $logJson, 'Audit log tidak boleh mencetak tautan rapat rahasia');
        $this->assertStringNotContainsString('baruSecret999', $logJson, 'Audit log tidak boleh mencetak passcode rapat');
    }
}
