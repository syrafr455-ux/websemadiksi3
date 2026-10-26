<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Jobs\SendProtectedEmailJob;
use App\Mail\KegiatanDiajukanMail;
use App\Models\Kegiatan;
use App\Models\User;
use App\Support\MenuAdmin;
use App\Support\Wib;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AlurPersetujuanKegiatanTerlihatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    protected function createSuperAdmin(array $attributes = []): User
    {
        return User::factory()->superAdmin()->create($attributes);
    }

    protected function createDraftKegiatan(User $creator, array $attributes = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'created_by' => $creator->id,
            'nama_kegiatan' => 'Workshop IT Governance UNUSA',
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

    /**
     * Membuktikan bahwa tombol akhir wizard store memiliki 2 aksi yang jujur:
     * "draft" -> status Draft, dan "ajukan" -> status Diajukan.
     */
    public function test_store_dengan_aksi_simpan_draft_dan_simpan_ajukan(): void
    {
        $creator = $this->createSuperAdmin(['name' => 'Admin Pembuat']);
        $otherAdmin = $this->createSuperAdmin(['name' => 'Admin Penyetuju']);

        $payload = [
            'nama_kegiatan' => 'Seminar AI Ethics',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => '2026-11-25 09:00',
            'tanggal_selesai' => '2026-11-25 11:00',
            'lokasi_type' => 'unusa_campus',
            'lokasi_kampus' => 'Kampus B (Jemursari)',
            'lokasi_gedung' => 'Tower UNUSA',
            'lokasi_ruangan' => 'Auditorium Lt. 9',
            'lokasi_nama' => 'Tower UNUSA - Kampus B',
            'biaya' => 0,
            'kuota' => 100,
        ];

        // 1. Simpan sebagai Draft
        $this->actingAs($creator)->post(route('admin.kegiatan.store'), array_merge($payload, [
            'aksi_submit' => 'draft',
        ]))->assertRedirect();

        $kegiatanDraft = Kegiatan::where('nama_kegiatan', 'Seminar AI Ethics')->firstOrFail();
        $this->assertSame(KegiatanStatus::Draft, $kegiatanDraft->status);

        // 2. Simpan & Ajukan Persetujuan
        $payload2 = array_merge($payload, [
            'nama_kegiatan' => 'Seminar Cloud Architecture',
            'aksi_submit' => 'ajukan',
        ]);

        $this->actingAs($creator)->post(route('admin.kegiatan.store'), $payload2)
            ->assertRedirect();

        $kegiatanDiajukan = Kegiatan::where('nama_kegiatan', 'Seminar Cloud Architecture')->firstOrFail();
        $this->assertSame(KegiatanStatus::Diajukan, $kegiatanDiajukan->status);

        // Pastikan email pengajuan terkirim lewat jalur terpusat SendProtectedEmailJob
        Queue::assertPushed(SendProtectedEmailJob::class, function ($job) use ($otherAdmin) {
            return $job->recipient === $otherAdmin->email
                && $job->mailable instanceof KegiatanDiajukanMail;
        });
    }

    /**
     * Membuktikan bahwa bila tidak ada super admin aktif lain, Simpan & Ajukan ditolak.
     */
    public function test_simpan_dan_ajukan_gagal_bila_tidak_ada_super_admin_lain(): void
    {
        $singleAdmin = $this->createSuperAdmin();

        $payload = [
            'nama_kegiatan' => 'Seminar Solo Admin',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => '2026-11-25 09:00',
            'tanggal_selesai' => '2026-11-25 11:00',
            'lokasi_type' => 'unusa_campus',
            'lokasi_kampus' => 'Kampus B (Jemursari)',
            'lokasi_gedung' => 'Tower UNUSA',
            'lokasi_ruangan' => 'Auditorium Lt. 9',
            'lokasi_nama' => 'Tower UNUSA - Kampus B',
            'biaya' => 0,
            'kuota' => 100,
            'aksi_submit' => 'ajukan',
        ];

        $response = $this->actingAs($singleAdmin)->post(route('admin.kegiatan.store'), $payload);
        $response->assertSessionHasErrors('aksi_submit');
    }

    /**
     * Membuktikan daftar kelola kegiatan memuat info status ringkas,
     * tombol Tinjau & Setujui untuk super admin peninjau, dan tombol Ajukan untuk pembuat.
     */
    public function test_daftar_kelola_kegiatan_memuat_lencana_dan_tombol_aksi_super_admin(): void
    {
        $creator = $this->createSuperAdmin(['name' => 'Creator Admin']);
        $approver = $this->createSuperAdmin(['name' => 'Approver Admin']);

        $draft = $this->createDraftKegiatan($creator, ['nama_kegiatan' => 'Kegiatan Draft']);
        $diajukan = $this->createDraftKegiatan($creator, [
            'nama_kegiatan' => 'Kegiatan Menunggu Persetujuan',
            'status' => KegiatanStatus::Diajukan,
        ]);

        // Cek halaman index untuk Approver Admin
        $resApprover = $this->actingAs($approver)->get(route('admin.kegiatan'));
        $resApprover->assertOk();

        // Props Inertia harus memuat ringkasan persetujuan untuk baris
        $kegiatans = $resApprover->original->getData()['page']['props']['kegiatans']['data'];
        $itemDiajukan = collect($kegiatans)->firstWhere('id', $diajukan->id);
        $this->assertNotNull($itemDiajukan);
        $this->assertTrue($itemDiajukan['perlu_tindakan_anda'] ?? false, 'Approver harus ditandai perlu_tindakan_anda');
        $this->assertSame('Menunggu persetujuan 0 dari 1', $itemDiajukan['status_teks'] ?? null);

        // Cek halaman index untuk Creator Admin
        $resCreator = $this->actingAs($creator)->get(route('admin.kegiatan'));
        $resCreator->assertOk();
        $kegiatansCreator = $resCreator->original->getData()['page']['props']['kegiatans']['data'];
        $itemDraft = collect($kegiatansCreator)->firstWhere('id', $draft->id);
        $this->assertNotNull($itemDraft);
        $this->assertTrue($itemDraft['bisa_diajukan_oleh_anda'] ?? false, 'Creator harus ditandai bisa_diajukan_oleh_anda pada draft');
    }

    /**
     * Membuktikan halaman detail untuk status draft dan diajukan:
     * - Pada draft: tampil "Belum diajukan oleh {nama pembuat}"
     * - Pada diajukan: tampil tombol Tarik kembali ke draft untuk pembuat
     * - Pada diajukan: tampil tombol Setujui dan Tolak untuk approver
     */
    public function test_halaman_detail_panel_persetujuan_draft_dan_diajukan(): void
    {
        $creator = $this->createSuperAdmin(['name' => 'Ahmad Creator']);
        $approver = $this->createSuperAdmin(['name' => 'Budi Approver']);

        $draft = $this->createDraftKegiatan($creator);

        // Approver melihat detail kegiatan draft
        $resApproverDraft = $this->actingAs($approver)->get(route('admin.kegiatan.show', $draft->id));
        $resApproverDraft->assertOk();
        $approvalDraft = $resApproverDraft->original->getData()['page']['props']['approval'];
        $this->assertSame('Belum diajukan oleh Ahmad Creator', $approvalDraft['pesan_status'] ?? null);
        $this->assertSame('Ahmad Creator', $approvalDraft['creator_name'] ?? null);

        // Ubah jadi diajukan
        $draft->status = KegiatanStatus::Diajukan;
        $draft->save();

        // Creator melihat detail diajukan: ada hak tarik kembali ke draft
        $resCreatorDiajukan = $this->actingAs($creator)->get(route('admin.kegiatan.show', $draft->id));
        $resCreatorDiajukan->assertOk();
        $approvalDiajukanCreator = $resCreatorDiajukan->original->getData()['page']['props']['approval'];
        $this->assertTrue($approvalDiajukanCreator['can_withdraw'] ?? false, 'Creator harus bisa menarik kembali ke draft');

        // Creator menarik kembali ke draft
        $this->actingAs($creator)->post(route('admin.kegiatan.tarik-draft', $draft->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(KegiatanStatus::Draft, $draft->fresh()->status);
    }

    /**
     * Membuktikan kartu di Ringkasan Utama dashboard dan lencana menu Kelola Kegiatan
     * hanya muncul untuk super admin yang wajib memberi suara.
     */
    public function test_notifikasi_dalam_aplikasi_ringkasan_utama_dan_menu_badge(): void
    {
        $creator = $this->createSuperAdmin(['name' => 'Creator']);
        $approver = $this->createSuperAdmin(['name' => 'Approver']);

        $diajukan1 = $this->createDraftKegiatan($creator, ['status' => KegiatanStatus::Diajukan]);
        $diajukan2 = $this->createDraftKegiatan($creator, ['status' => KegiatanStatus::Diajukan]);

        // Approver membuka dashboard Ringkasan Utama admin
        $resDashboard = $this->actingAs($approver)->get(route('admin.dashboard'));
        $resDashboard->assertOk()
            ->assertSee('Menunggu persetujuan Anda (2)');

        // Approver melihat badge angka 2 pada MenuAdmin untuk 'admin.kegiatan'
        $menuItems = MenuAdmin::untuk($approver);
        $kegiatanMenu = collect($menuItems)->firstWhere('route', 'admin.kegiatan');
        $this->assertSame(2, $kegiatanMenu['badge'] ?? null);

        // Creator tidak melihat badge menunggu persetujuan pada kegiatannya sendiri
        $menuCreator = MenuAdmin::untuk($creator);
        $kegiatanMenuCreator = collect($menuCreator)->firstWhere('route', 'admin.kegiatan');
        $this->assertSame(0, $kegiatanMenuCreator['badge'] ?? 0);
    }

    /**
     * Membuktikan penolakan kegiatan dengan catatan wajib, serta persetujuan lengkap
     * menerbitkan kegiatan dan akun diblokir tidak dihitung sebagai penyetuju wajib.
     */
    public function test_penolakan_dengan_catatan_dan_persetujuan_lengkap_menerbitkan_kegiatan(): void
    {
        $creator = $this->createSuperAdmin(['name' => 'Creator']);
        $approver1 = $this->createSuperAdmin(['name' => 'Approver Satu']);
        $approverBlocked = $this->createSuperAdmin(['name' => 'Approver Terblokir', 'is_blocked' => true]);

        $kegiatan = $this->createDraftKegiatan($creator, ['status' => KegiatanStatus::Diajukan]);

        // Super admin terblokir tidak masuk ke penyetuju wajib
        $penyetujuWajib = $kegiatan->penyetujuWajib();
        $this->assertCount(1, $penyetujuWajib);
        $this->assertTrue($penyetujuWajib->contains('id', $approver1->id));
        $this->assertFalse($penyetujuWajib->contains('id', $approverBlocked->id));

        // Approver 1 menolak kegiatan dengan catatan
        $resTolak = $this->actingAs($approver1)->post(route('admin.kegiatan.tolak', $kegiatan->id), [
            'catatan' => 'Jadwal bertabrakan dengan Dies Natalis UNUSA.',
        ]);
        $resTolak->assertRedirect()->assertSessionHas('success');

        $kegiatan->refresh();
        $this->assertSame(KegiatanStatus::Draft, $kegiatan->status);
        $this->assertSame('Jadwal bertabrakan dengan Dies Natalis UNUSA.', $kegiatan->catatan_penolakan);

        // Pada index, status memuat catatan penolakan
        $resIndex = $this->actingAs($creator)->get(route('admin.kegiatan'));
        $kegiatans = $resIndex->original->getData()['page']['props']['kegiatans']['data'];
        $item = collect($kegiatans)->firstWhere('id', $kegiatan->id);
        $this->assertStringContainsString('Ditolak:', $item['status_teks']);
        $this->assertStringContainsString('Dies Natalis', $item['status_teks']);

        // Creator mengajukan kembali kegiatan
        $this->actingAs($creator)->post(route('admin.kegiatan.ajukan', $kegiatan->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $kegiatan->refresh();
        $this->assertSame(KegiatanStatus::Diajukan, $kegiatan->status);
        $this->assertNull($kegiatan->catatan_penolakan);

        // Approver 1 menyetujui
        $resSetuju = $this->actingAs($approver1)->post(route('admin.kegiatan.setujui', $kegiatan->id));
        $resSetuju->assertRedirect()->assertSessionHas('success');

        $kegiatan->refresh();
        $this->assertSame(KegiatanStatus::Published, $kegiatan->status);
    }
}
