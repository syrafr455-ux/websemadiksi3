<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\User;
use App\Services\Kegiatan\DenahKursiService;
use App\Services\Kegiatan\PetaKursiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SeatNonPesertaRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected Kegiatan $kegiatan;

    protected User $pesertaA;

    protected User $pesertaB;

    protected User $admin;

    protected KegiatanSeat $seatVip;

    protected KegiatanSeat $seatPanitia;

    protected KegiatanSeat $seatBlocked;

    protected KegiatanSeat $seatReguler;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00', 'Asia/Jakarta'));

        $this->pesertaA = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'name' => 'Peserta A',
            'email' => 'peserta.a@unusa.ac.id',
            'nim' => '2240021001',
        ]);

        $this->pesertaB = User::factory()->create([
            'role' => UserRole::UserUmum,
            'name' => 'Peserta B',
            'email' => 'peserta.b@gmail.com',
            'nim' => '2240021002',
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'name' => 'Super Admin',
            'email' => 'admin@unusa.ac.id',
        ]);

        $this->kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Teknologi Masa Depan',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addWeek(),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => true,
            'booking_opens_at' => now()->subDay(),
            'booking_closes_at' => now()->addDays(5),
        ]);

        // Denah:
        // Baris A: VIP
        // Baris B: Reguler (dengan B04 diblokir)
        // Baris C: Panitia
        $layout = app(DenahKursiService::class)->buat($this->kegiatan, [
            'rows' => ['A', 'B', 'C'],
            'cols_per_row' => 4,
            'aisle_after_col' => 2,
            'vip_rows' => ['A'],
            'panitia_rows' => ['C'],
            'blocked_seats' => ['B04'],
        ]);
        $this->kegiatan->update(['seat_layout_config' => $layout]);

        $this->seatVip = $this->kegiatan->seats()->where('seat_code', 'A01')->firstOrFail();
        $this->seatReguler = $this->kegiatan->seats()->where('seat_code', 'B01')->firstOrFail();
        $this->seatBlocked = $this->kegiatan->seats()->where('seat_code', 'B04')->firstOrFail();
        $this->seatPanitia = $this->kegiatan->seats()->where('seat_code', 'C01')->firstOrFail();
    }

    /**
     * 1. Peserta tidak bisa memesan kursi VIP, Panitia, atau Diblokir via checkout.
     * Harus ditolak dengan status 422 dan pesan netral:
     * "Kursi ini tidak tersedia. Silakan pilih kursi lain."
     */
    public function test_peserta_checkout_kursi_non_peserta_ditolak_422(): void
    {
        // A. Pesan kursi VIP A01
        $resVip = $this->actingAs($this->pesertaA)
            ->post(route('kegiatan.checkout', $this->kegiatan->id), [
                'delegates' => [
                    [
                        'name' => $this->pesertaA->name,
                        'email' => $this->pesertaA->email,
                        'seat_id' => $this->seatVip->id,
                    ],
                ],
            ]);
        $resVip->assertSessionHasErrors(['delegates.0.seat_id' => 'Kursi ini tidak tersedia. Silakan pilih kursi lain.']);
        $this->assertDatabaseMissing('kegiatan_registrations', [
            'kegiatan_id' => $this->kegiatan->id,
            'seat_id' => $this->seatVip->id,
        ]);

        // B. Pesan kursi Panitia C01
        $resPanitia = $this->actingAs($this->pesertaA)
            ->post(route('kegiatan.checkout', $this->kegiatan->id), [
                'delegates' => [
                    [
                        'name' => $this->pesertaA->name,
                        'email' => $this->pesertaA->email,
                        'seat_id' => $this->seatPanitia->id,
                    ],
                ],
            ]);
        $resPanitia->assertSessionHasErrors(['delegates.0.seat_id' => 'Kursi ini tidak tersedia. Silakan pilih kursi lain.']);

        // C. Pesan kursi Diblokir B04
        $resBlocked = $this->actingAs($this->pesertaA)
            ->post(route('kegiatan.checkout', $this->kegiatan->id), [
                'delegates' => [
                    [
                        'name' => $this->pesertaA->name,
                        'email' => $this->pesertaA->email,
                        'seat_id' => $this->seatBlocked->id,
                    ],
                ],
            ]);
        $resBlocked->assertSessionHasErrors(['delegates.0.seat_id' => 'Kursi ini tidak tersedia. Silakan pilih kursi lain.']);
    }

    /**
     * 2. Peserta tidak bisa memesan kursi VIP, Panitia, atau Diblokir via book-seats / alokasi-kursi.
     * Ditolak dengan 422 dan pesan netral: "Kursi ini tidak tersedia. Silakan pilih kursi lain."
     */
    public function test_peserta_book_seats_kursi_non_peserta_ditolak_422(): void
    {
        // Daftarkan peserta A tanpa kursi terlebih dahulu
        $regA = KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id,
            'booker_user_id' => $this->pesertaA->id,
            'participant_user_id' => $this->pesertaA->id,
            'nomor_tiket' => 'TKT-TEMP-001',
            'participant_name' => $this->pesertaA->name,
            'participant_identity' => $this->pesertaA->nim,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
        ]);

        // Coba pilih kursi VIP A01 via Json
        $res = $this->actingAs($this->pesertaA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regA->id, 'seat_id' => $this->seatVip->id],
                ],
            ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['allocations.0.seat_id']);
        $this->assertStringContainsString('Kursi ini tidak tersedia. Silakan pilih kursi lain.', $res->json('errors')['allocations.0.seat_id'][0]);

        // Coba pilih kursi Panitia C01
        $resPanitia = $this->actingAs($this->pesertaA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regA->id, 'seat_id' => $this->seatPanitia->id],
                ],
            ]);
        $resPanitia->assertStatus(422);
        $this->assertStringContainsString('Kursi ini tidak tersedia. Silakan pilih kursi lain.', $resPanitia->json('errors')['allocations.0.seat_id'][0]);

        // Coba pilih kursi Blocked B04
        $resBlocked = $this->actingAs($this->pesertaA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regA->id, 'seat_id' => $this->seatBlocked->id],
                ],
            ]);
        $resBlocked->assertStatus(422);
        $this->assertStringContainsString('Kursi ini tidak tersedia. Silakan pilih kursi lain.', $resBlocked->json('errors')['allocations.0.seat_id'][0]);
    }

    /**
     * 3. Peserta tidak bisa memilih ulang (reselect) ke kursi VIP, Panitia, atau Diblokir via reselect-seat.
     */
    public function test_peserta_reselect_kursi_non_peserta_ditolak_422(): void
    {
        $regA = KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id,
            'booker_user_id' => $this->pesertaA->id,
            'participant_user_id' => $this->pesertaA->id,
            'nomor_tiket' => 'TKT-TEMP-001',
            'participant_name' => $this->pesertaA->name,
            'participant_identity' => $this->pesertaA->nim,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => true,
        ]);

        $res = $this->actingAs($this->pesertaA)
            ->post(route('kegiatan.reselect-seat', $this->kegiatan->id), [
                'registration_id' => $regA->id,
                'seat_id' => $this->seatVip->id,
            ]);

        $res->assertSessionHasErrors(['seat_id' => 'Kursi ini tidak tersedia. Silakan pilih kursi lain.']);
        $this->assertNull($regA->fresh()->seat_id);
    }

    /**
     * 4. Kursi reguler tersedia TETAP BISA dipesan oleh peserta.
     */
    public function test_kursi_reguler_tersedia_tetap_bisa_dipesan(): void
    {
        $res = $this->actingAs($this->pesertaA)
            ->post(route('kegiatan.checkout', $this->kegiatan->id), [
                'delegates' => [
                    [
                        'name' => $this->pesertaA->name,
                        'email' => $this->pesertaA->email,
                        'seat_id' => $this->seatReguler->id,
                    ],
                ],
            ]);

        $res->assertRedirect(route('dashboard.kegiatan.saya'));
        $res->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kegiatan_registrations', [
            'kegiatan_id' => $this->kegiatan->id,
            'participant_user_id' => $this->pesertaA->id,
            'seat_id' => $this->seatReguler->id,
        ]);
        $this->assertEquals(SeatStatus::Booked, $this->seatReguler->fresh()->status);
    }

    /**
     * 5. Payload peta kursi untuk peserta tidak memuat kategori kursi (VIP/Panitia/Diblokir)
     * dan menampilkan status 'tidak_tersedia' untuk kursi non-peserta.
     */
    public function test_payload_peserta_tidak_membocorkan_kategori_dan_status_tidak_tersedia(): void
    {
        $petaService = app(PetaKursiService::class);
        $kursiPeserta = collect($petaService->untukPeserta($this->kegiatan, $this->pesertaA))->keyBy('kode');

        // Kursi Reguler B01 yang belum dipesan: status 'tersedia'
        $this->assertEquals('tersedia', $kursiPeserta['B01']['status']);

        // Kursi VIP A01: status 'tidak_tersedia' (bukan 'tersedia', dan bukan 'vip')
        $this->assertEquals('tidak_tersedia', $kursiPeserta['A01']['status']);

        // Kursi Panitia C01: status 'tidak_tersedia' (bukan 'panitia')
        $this->assertEquals('tidak_tersedia', $kursiPeserta['C01']['status']);

        // Kursi Blocked B04: status 'tidak_tersedia' (bukan 'diblokir')
        $this->assertEquals('tidak_tersedia', $kursiPeserta['B04']['status']);

        // Pastikan tidak ada key 'tier' di payload peserta (atau bernilai null)
        foreach ($kursiPeserta as $k) {
            $this->assertTrue(! isset($k['tier']) || $k['tier'] === null, "Payload kursi {$k['kode']} membocorkan tier.");
        }
    }

    /**
     * 6. Penghitungan sisa kursi atau kapasitas yang tampil ke peserta (kursi non-peserta tidak ikut terhitung sebagai tersedia).
     */
    public function test_kapasitas_denah_tidak_menghitung_kursi_non_peserta(): void
    {
        // 3 baris x 4 kolom = 12 total kursi
        // Baris A = VIP (4 kursi) -> tidak_tersedia
        // Baris C = Panitia (4 kursi) -> tidak_tersedia
        // B04 = Diblokir (1 kursi) -> tidak_tersedia
        // Sisa reguler yang tersedia untuk peserta = B01, B02, B03 = 3 kursi!
        $petaPeserta = app(PetaKursiService::class)->untukPeserta($this->kegiatan, $this->pesertaA);
        $kursiTersedia = collect($petaPeserta)->where('status', 'tersedia')->count();
        $this->assertEquals(3, $kursiTersedia);

        $ringkas = app(PetaKursiService::class)->statusRingkas($this->kegiatan, $this->pesertaA);
        $ringkasTersedia = collect($ringkas)->where('status', 'tersedia')->count();
        $this->assertEquals(3, $ringkasTersedia);
    }

    /**
     * 7. Admin (Super Admin) tetap bisa menempatkan peserta di kursi VIP atau Panitia via book-seats.
     * Peta admin tidak berubah (menampilkan status asli dan pemegang).
     */
    public function test_admin_tetap_bisa_menempatkan_peserta_di_kursi_vip_dan_panitia(): void
    {
        $regB = KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id,
            'booker_user_id' => $this->pesertaB->id,
            'participant_user_id' => $this->pesertaB->id,
            'nomor_tiket' => 'TKT-TEMP-002',
            'participant_name' => $this->pesertaB->name,
            'participant_identity' => $this->pesertaB->nim,
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'status_kehadiran' => AttendanceStatus::Registered,
        ]);

        // Super admin menempatkan peserta B di kursi VIP A01
        $res = $this->actingAs($this->admin)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regB->id, 'seat_id' => $this->seatVip->id],
                ],
            ]);

        $res->assertOk();
        $this->assertEquals($this->seatVip->id, $regB->fresh()->seat_id);
        $this->assertEquals(SeatStatus::Booked, $this->seatVip->fresh()->status);

        // Peta admin tetap menampilkan detail lengkap
        $petaAdmin = collect(app(PetaKursiService::class)->untukAdmin($this->kegiatan))->keyBy('kode');
        $this->assertEquals('booked', $petaAdmin['A01']['status']);
        $this->assertEquals('vip', $petaAdmin['A01']['tier']);
        $this->assertEquals($this->pesertaB->name, $petaAdmin['A01']['pemegang']);
    }

    /**
     * 8. Race condition: Dua peserta berebut kursi reguler tersedia yang sama, hanya satu yang berhasil.
     */
    public function test_dua_peserta_berebut_kursi_yang_sama_hanya_satu_berhasil(): void
    {
        $regA = KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id,
            'booker_user_id' => $this->pesertaA->id,
            'participant_user_id' => $this->pesertaA->id,
            'nomor_tiket' => 'TKT-TEMP-001',
            'participant_name' => $this->pesertaA->name,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
        ]);

        $regB = KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id,
            'booker_user_id' => $this->pesertaB->id,
            'participant_user_id' => $this->pesertaB->id,
            'nomor_tiket' => 'TKT-TEMP-002',
            'participant_name' => $this->pesertaB->name,
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'status_kehadiran' => AttendanceStatus::Registered,
        ]);

        // Peserta A berhasil memilih B02
        $seatB02 = $this->kegiatan->seats()->where('seat_code', 'B02')->firstOrFail();
        $resA = $this->actingAs($this->pesertaA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regA->id, 'seat_id' => $seatB02->id],
                ],
            ]);
        $resA->assertOk();
        $this->assertEquals($seatB02->id, $regA->fresh()->seat_id);

        // Peserta B mencoba memilih B02 yang baru saja diambil -> ditolak 422
        $resB = $this->actingAs($this->pesertaB)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regB->id, 'seat_id' => $seatB02->id],
                ],
            ]);
        $resB->assertStatus(422);
        $resB->assertJsonValidationErrors(['allocations.0.seat_id']);
        $this->assertStringContainsString('Kursi ini tidak tersedia. Silakan pilih kursi lain.', $resB->json('errors')['allocations.0.seat_id'][0]);
    }
}
