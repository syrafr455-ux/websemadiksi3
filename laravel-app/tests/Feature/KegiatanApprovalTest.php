<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\UserRole;
use App\Mail\KegiatanDiajukanMail;
use App\Models\Kegiatan;
use App\Models\User;
use App\Services\EmailProtectionService;
use App\Support\Wib;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\MengauditKolomSql;
use Tests\TestCase;

class KegiatanApprovalTest extends TestCase
{
    use MengauditKolomSql, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function createSuperAdmin(array $attributes = []): User
    {
        return User::factory()->superAdmin()->create($attributes);
    }

    protected function createDraftKegiatan(User $creator, array $attributes = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'created_by' => $creator->id,
            'nama_kegiatan' => 'Workshop Tata Kelola IT',
            'deskripsi' => 'Deskripsi workshop tata kelola IT.',
            'kategori' => 'workshop',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => Wib::keUtc('2026-11-20 09:00'),
            'tanggal_selesai' => Wib::keUtc('2026-11-20 12:00'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower UNUSA Lt 9',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Draft,
            'is_seat_booking_active' => false,
            'is_submission_active' => false,
        ], $attributes));
    }

    public function test_kegiatan_diajukan_hanya_oleh_pembuat_dan_kirim_email_ke_penyetuju_wajib(): void
    {
        $creator = $this->createSuperAdmin(['name' => 'Admin Pembuat']);
        $approver1 = $this->createSuperAdmin(['name' => 'Admin Penyetuju 1']);
        $approver2 = $this->createSuperAdmin(['name' => 'Admin Penyetuju 2']);

        $kegiatan = $this->createDraftKegiatan($creator);

        // Bukan pembuat mencoba mengajukan
        $this->actingAs($approver1)->post(route('admin.kegiatan.ajukan', $kegiatan->id))
            ->assertForbidden();

        // Pembuat mengajukan kegiatan
        $this->actingAs($creator)->post(route('admin.kegiatan.ajukan', $kegiatan->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(KegiatanStatus::Diajukan, $kegiatan->fresh()->status);

        // Email dikirim ke approver1 dan approver2, tetapi bukan ke creator
        Mail::assertQueued(KegiatanDiajukanMail::class, 2);
        Mail::assertQueued(KegiatanDiajukanMail::class, fn ($mail) => $mail->hasTo($approver1->email));
        Mail::assertQueued(KegiatanDiajukanMail::class, fn ($mail) => $mail->hasTo($approver2->email));
        Mail::assertNotQueued(KegiatanDiajukanMail::class, fn ($mail) => $mail->hasTo($creator->email));

        // Tercatat di audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $creator->id,
            'action' => 'submit_kegiatan',
        ]);
    }

    public function test_pembuat_tidak_bisa_menyetujui_kegiatannya_sendiri(): void
    {
        $creator = $this->createSuperAdmin();
        $otherAdmin = $this->createSuperAdmin();

        $kegiatan = $this->createDraftKegiatan($creator, ['status' => KegiatanStatus::Diajukan]);

        $this->actingAs($creator)->post(route('admin.kegiatan.setujui', $kegiatan->id))
            ->assertForbidden();

        $this->assertDatabaseMissing('kegiatan_persetujuan', [
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $creator->id,
        ]);
    }

    public function test_alur_dengan_1_super_admin_tidak_bisa_terbit(): void
    {
        $singleAdmin = $this->createSuperAdmin();
        $kegiatan = $this->createDraftKegiatan($singleAdmin);

        $this->actingAs($singleAdmin)->post(route('admin.kegiatan.ajukan', $kegiatan->id))
            ->assertRedirect();

        $this->assertSame(KegiatanStatus::Diajukan, $kegiatan->fresh()->status);
        $this->assertEmpty($kegiatan->fresh()->penyetujuWajib());
        $this->assertFalse($kegiatan->fresh()->cekDanPerbaruiStatusPersetujuan());
        $this->assertSame(KegiatanStatus::Diajukan, $kegiatan->fresh()->status);
    }

    public function test_alur_dengan_2_super_admin_otomatis_terbit_setelah_1_penyetuju_setuju(): void
    {
        $creator = $this->createSuperAdmin();
        $approver = $this->createSuperAdmin();

        $kegiatan = $this->createDraftKegiatan($creator, ['status' => KegiatanStatus::Diajukan]);

        $this->actingAs($approver)->post(route('admin.kegiatan.setujui', $kegiatan->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('kegiatan_persetujuan', [
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $approver->id,
            'keputusan' => 'setuju',
        ]);

        $this->assertSame(KegiatanStatus::Published, $kegiatan->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'publish_kegiatan_otomatis',
        ]);
    }

    public function test_alur_dengan_3_super_admin_butuh_semua_penyetuju_setuju_baru_terbit(): void
    {
        $creator = $this->createSuperAdmin();
        $approver1 = $this->createSuperAdmin();
        $approver2 = $this->createSuperAdmin();

        $kegiatan = $this->createDraftKegiatan($creator, ['status' => KegiatanStatus::Diajukan]);

        // Approver 1 menyetujui
        $this->actingAs($approver1)->post(route('admin.kegiatan.setujui', $kegiatan->id))
            ->assertRedirect();

        // Status masih diajukan karena approver2 belum setuju
        $this->assertSame(KegiatanStatus::Diajukan, $kegiatan->fresh()->status);

        // Approver 2 menyetujui
        $this->actingAs($approver2)->post(route('admin.kegiatan.setujui', $kegiatan->id))
            ->assertRedirect();

        // Sekarang otomatis terbit
        $this->assertSame(KegiatanStatus::Published, $kegiatan->fresh()->status);
    }

    public function test_penolakan_dengan_catatan_mengembalikan_ke_draft_dan_menghapus_persetujuan(): void
    {
        $creator = $this->createSuperAdmin();
        $approver1 = $this->createSuperAdmin();
        $approver2 = $this->createSuperAdmin();

        $kegiatan = $this->createDraftKegiatan($creator, ['status' => KegiatanStatus::Diajukan]);

        // Approver 1 setuju dulu
        $this->actingAs($approver1)->post(route('admin.kegiatan.setujui', $kegiatan->id));
        $this->assertDatabaseCount('kegiatan_persetujuan', 1);

        // Approver 2 tolak tanpa catatan -> gagal validasi
        $this->actingAs($approver2)->post(route('admin.kegiatan.tolak', $kegiatan->id), [
            'catatan' => '',
        ])->assertSessionHasErrors('catatan');

        // Approver 2 tolak dengan catatan sah
        $this->actingAs($approver2)->post(route('admin.kegiatan.tolak', $kegiatan->id), [
            'catatan' => 'Ruangan bentrok dengan acara Dies Natalis, mohon jadwalkan ulang.',
        ])->assertRedirect()
            ->assertSessionHas('success');

        // Status kembali ke draft dan semua persetujuan terhapus
        $this->assertSame(KegiatanStatus::Draft, $kegiatan->fresh()->status);
        $this->assertDatabaseCount('kegiatan_persetujuan', 0);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $approver2->id,
            'action' => 'reject_kegiatan',
        ]);
    }

    public function test_edit_kegiatan_diajukan_menghapus_semua_persetujuan(): void
    {
        $creator = $this->createSuperAdmin();
        $approver1 = $this->createSuperAdmin();
        $approver2 = $this->createSuperAdmin();

        $kegiatan = $this->createDraftKegiatan($creator, ['status' => KegiatanStatus::Diajukan]);

        // Approver 1 sudah setuju
        $this->actingAs($approver1)->post(route('admin.kegiatan.setujui', $kegiatan->id));
        $this->assertDatabaseCount('kegiatan_persetujuan', 1);

        // Creator mengedit deskripsi kegiatan
        $this->actingAs($creator)->put(route('admin.kegiatan.update', $kegiatan->id), [
            'deskripsi' => 'Deskripsi diperbarui dengan silabus lengkap.',
        ])->assertRedirect();

        // Persetujuan terhapus agar disetujui ulang dari data versi baru
        $this->assertDatabaseCount('kegiatan_persetujuan', 0);
    }

    public function test_akun_super_admin_diblokir_tidak_dihitung_sebagai_penyetuju_wajib(): void
    {
        $creator = $this->createSuperAdmin();
        $activeApprover = $this->createSuperAdmin();
        $blockedApprover = $this->createSuperAdmin(['is_blocked' => true]);

        $kegiatan = $this->createDraftKegiatan($creator, ['status' => KegiatanStatus::Diajukan]);

        $penyetujuWajib = $kegiatan->penyetujuWajib();
        $this->assertCount(1, $penyetujuWajib);
        $this->assertTrue($penyetujuWajib->contains('id', $activeApprover->id));
        $this->assertFalse($penyetujuWajib->contains('id', $blockedApprover->id));

        // Dengan hanya 1 approver aktif, saat dia setuju, kegiatan langsung terbit
        $this->actingAs($activeApprover)->post(route('admin.kegiatan.setujui', $kegiatan->id));
        $this->assertSame(KegiatanStatus::Published, $kegiatan->fresh()->status);
    }

    public function test_kegiatan_draft_dan_diajukan_tidak_tampil_untuk_publik_dan_peserta(): void
    {
        $creator = $this->createSuperAdmin();
        $draft = $this->createDraftKegiatan($creator, ['nama_kegiatan' => 'Kegiatan Rahasia Draft', 'status' => KegiatanStatus::Draft]);
        $diajukan = $this->createDraftKegiatan($creator, ['nama_kegiatan' => 'Kegiatan Rahasia Diajukan', 'status' => KegiatanStatus::Diajukan]);
        $published = $this->createDraftKegiatan($creator, ['nama_kegiatan' => 'Kegiatan Publik Terbit', 'status' => KegiatanStatus::Published]);

        $mahasiswa = User::factory()->role(UserRole::MahasiswaKipk)->create();

        // Halaman landing publik
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Kegiatan Publik Terbit')
            ->assertDontSee('Kegiatan Rahasia Draft')
            ->assertDontSee('Kegiatan Rahasia Diajukan');

        // Halaman detail untuk peserta / tamu
        $this->get(route('kegiatan.detail', $draft->id))->assertNotFound();
        $this->get(route('kegiatan.detail', $diajukan->id))->assertNotFound();
        $this->get(route('kegiatan.detail', $published->id))->assertOk();

        // Super admin tetap bisa pratinjau detail di panel admin
        $this->actingAs($creator)->get(route('admin.kegiatan.show', $draft->id))->assertOk();
        $this->actingAs($creator)->get(route('admin.kegiatan.show', $diajukan->id))->assertOk();
    }

    public function test_kegiatan_terbit_mengunci_field_kritis_tetapi_mengizinkan_deskripsi_dan_narasumber(): void
    {
        $creator = $this->createSuperAdmin();
        $approver = $this->createSuperAdmin();
        $kegiatan = $this->createDraftKegiatan($creator);

        // Ajukan dan setujui hingga otomatis terbit
        $this->actingAs($creator)->post(route('admin.kegiatan.ajukan', $kegiatan->id));
        $this->actingAs($approver)->post(route('admin.kegiatan.setujui', $kegiatan->id));
        $this->assertSame(KegiatanStatus::Published, $kegiatan->fresh()->status);

        // Coba ubah tanggal mulai (field kritis) -> ditolak
        $this->actingAs($creator)->put(route('admin.kegiatan.update', $kegiatan->id), [
            'tanggal_mulai' => '2026-11-21T10:00',
        ])->assertSessionHasErrors('tanggal_mulai');

        // Coba ubah kuota (field kritis) -> ditolak
        $this->actingAs($creator)->put(route('admin.kegiatan.update', $kegiatan->id), [
            'kuota' => 200,
        ])->assertSessionHasErrors('kuota');

        // Ubah deskripsi & narasumber (non-kritis) -> diizinkan
        $this->actingAs($creator)->put(route('admin.kegiatan.update', $kegiatan->id), [
            'deskripsi' => 'Deskripsi diperbarui tanpa menyentuh field kritis.',
            'narasumber' => [
                ['nama' => 'Prof. Dr. Ir. Budi', 'gelar' => 'M.T.', 'instansi' => 'UNUSA'],
            ],
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Deskripsi diperbarui tanpa menyentuh field kritis.', $kegiatan->fresh()->deskripsi);
        $this->assertCount(1, $kegiatan->fresh()->narasumber);
    }

    public function test_ajukan_kegiatan_daring_tetap_tersimpan_saat_email_gagal(): void
    {
        $creator = $this->createSuperAdmin(['name' => 'Creator Daring']);
        $approver = $this->createSuperAdmin(['name' => 'Approver Daring']);

        $kegiatan = $this->createDraftKegiatan($creator, [
            'nama_kegiatan' => 'Lomba Essay Nasional',
            'kategori' => 'lomba',
            'metode_pelaksanaan' => 'online',
            'platform_daring' => 'zoom',
            'tautan_rapat' => 'https://zoom.us/j/999888777',
        ]);

        // Simulasikan kegagalan pada pengiriman email
        $protectionMock = $this->createMock(EmailProtectionService::class);
        $protectionMock->expects($this->once())
            ->method('kirim')
            ->willThrowException(new \RuntimeException('Koneksi relay email terputus'));
        $this->app->instance(EmailProtectionService::class, $protectionMock);

        $response = $this->actingAs($creator)->post(route('admin.kegiatan.ajukan', $kegiatan->id));

        $response->assertRedirect()
            ->assertSessionHas('warning', 'Pengajuan tersimpan, tetapi email pemberitahuan ke penyetuju gagal dikirim. Beri tahu penyetuju secara langsung.');

        $this->assertSame(KegiatanStatus::Diajukan, $kegiatan->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $creator->id,
            'action' => 'submit_kegiatan',
        ]);
    }

    public function test_audit_semua_metode_kegiatan_offline_daring_hybrid_alur_persetujuan_lengkap_dan_kolom_sql(): void
    {
        $creator = $this->createSuperAdmin(['name' => 'Creator Audit']);
        $approver1 = $this->createSuperAdmin(['name' => 'Approver 1 Audit']);
        $approver2 = $this->createSuperAdmin(['name' => 'Approver 2 Audit']);

        $metodeList = [
            'offline' => [
                'nama_kegiatan' => 'Seminar Offline Kampus',
                'metode_pelaksanaan' => 'offline',
                'lokasi_type' => 'unusa_campus',
                'lokasi_nama' => 'Auditorium Tower UNUSA',
            ],
            'online' => [
                'nama_kegiatan' => 'Webinar Daring Nasional',
                'metode_pelaksanaan' => 'online',
                'lokasi_type' => 'online',
                'lokasi_nama' => 'Daring (Zoom)',
                'platform_daring' => 'zoom',
                'tautan_rapat' => 'https://zoom.us/j/123456789',
            ],
            'hybrid' => [
                'nama_kegiatan' => 'Konferensi Hybrid UNUSA',
                'metode_pelaksanaan' => 'hybrid',
                'lokasi_type' => 'unusa_campus',
                'lokasi_nama' => 'Auditorium Lt 9 & Zoom',
                'lokasi_kampus' => 'Kampus B (Jemursari)',
                'lokasi_gedung' => 'Tower UNUSA',
                'lokasi_ruangan' => 'Auditorium Lt. 9',
                'platform_daring' => 'zoom',
                'tautan_rapat' => 'https://zoom.us/j/987654321',
                'kuota_onsite' => 40,
            ],
        ];

        foreach ($metodeList as $metode => $attrs) {
            $kegiatan = $this->createDraftKegiatan($creator, $attrs);

            $this->mulaiAuditKolom();

            // 1. Aksi: Ajukan
            $resAjukan = $this->actingAs($creator)->post(route('admin.kegiatan.ajukan', $kegiatan->id));
            $resAjukan->assertRedirect()->assertSessionHas('success');
            $this->assertSame(KegiatanStatus::Diajukan, $kegiatan->fresh()->status);
            $this->assertTidakAdaKolomTidakDikenal("Aksi ajukan metode {$metode}");

            // 2. Aksi: Approver 1 menyetujui
            $resSetujui1 = $this->actingAs($approver1)->post(route('admin.kegiatan.setujui', $kegiatan->id));
            $resSetujui1->assertRedirect()->assertSessionHas('success');
            $this->assertDatabaseHas('kegiatan_persetujuan', [
                'kegiatan_id' => $kegiatan->id,
                'user_id' => $approver1->id,
                'keputusan' => 'setuju',
            ]);
            $this->assertTidakAdaKolomTidakDikenal("Aksi setujui 1 metode {$metode}");

            // 3. Aksi: Tarik kembali ke draft
            $resTarik = $this->actingAs($creator)->post(route('admin.kegiatan.tarik-draft', $kegiatan->id));
            $resTarik->assertRedirect()->assertSessionHas('success');
            $this->assertSame(KegiatanStatus::Draft, $kegiatan->fresh()->status);
            $this->assertSame(0, $kegiatan->persetujuan()->count());
            $this->assertTidakAdaKolomTidakDikenal("Aksi tarik-draft metode {$metode}");

            // 4. Aksi: Ajukan ulang lalu tolak dengan catatan
            $this->actingAs($creator)->post(route('admin.kegiatan.ajukan', $kegiatan->id))->assertRedirect();
            $resTolak = $this->actingAs($approver2)->post(route('admin.kegiatan.tolak', $kegiatan->id), [
                'catatan' => 'Perbaiki jadwal agar tidak bentrok dengan perkuliahan.',
            ]);
            $resTolak->assertRedirect()->assertSessionHas('success');
            $this->assertSame(KegiatanStatus::Draft, $kegiatan->fresh()->status);
            $this->assertSame('Perbaiki jadwal agar tidak bentrok dengan perkuliahan.', $kegiatan->fresh()->catatan_penolakan);
            $this->assertTidakAdaKolomTidakDikenal("Aksi tolak metode {$metode}");

            // 5. Aksi: Ajukan ulang dan setujui hingga terbit otomatis
            $this->actingAs($creator)->post(route('admin.kegiatan.ajukan', $kegiatan->id))->assertRedirect();
            $this->actingAs($approver1)->post(route('admin.kegiatan.setujui', $kegiatan->id))->assertRedirect();
            $this->actingAs($approver2)->post(route('admin.kegiatan.setujui', $kegiatan->id))->assertRedirect();
            $this->assertSame(KegiatanStatus::Published, $kegiatan->fresh()->status);
            $this->assertTidakAdaKolomTidakDikenal("Aksi terbit otomatis metode {$metode}");
        }
    }

    public function test_migrasi_dan_skema_status_kegiatan_dapat_menyimpan_semua_nilai_enum(): void
    {
        $creator = $this->createSuperAdmin();
        $kegiatan = $this->createDraftKegiatan($creator);

        foreach (KegiatanStatus::cases() as $status) {
            $kegiatan->status = $status;
            $kegiatan->save();
            $this->assertSame($status, $kegiatan->fresh()->status);
        }
    }
}
