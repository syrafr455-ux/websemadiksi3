<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Mail\TicketIssuedMail;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\User;
use App\Services\Kegiatan\DenahKursiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CoParticipantSeatBookingTest extends TestCase
{
    use RefreshDatabase;

    protected Kegiatan $kegiatan;

    protected User $userA;

    protected User $userB;

    protected User $userLuar;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00', 'Asia/Jakarta'));

        $this->kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Grand Seminar Bioskop',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addWeek(),
            'lokasi_type' => 'external',
            'lokasi_nama' => 'Auditorium Tower Unusa',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => true,
            'booking_opens_at' => now()->subDay(),
            'booking_closes_at' => now()->addDays(5),
        ]);

        // Preset denah bioskop: 12 kolom dengan lorong tengah setelah kolom 6
        $layoutConfig = app(DenahKursiService::class)->buat($this->kegiatan, [
            'rows' => ['A', 'B', 'C'],
            'cols_per_row' => 12,
            'aisle_after_col' => 6,
            'vip_rows' => ['A'],
            'panitia_rows' => [],
            'blocked_seats' => [],
        ]);
        $this->kegiatan->update(['seat_layout_config' => $layoutConfig]);

        $this->userA = User::factory()->mahasiswaKipk()->create([
            'name' => 'Ahmad Pemesan',
            'email' => 'ahmad@gmail.com',
            'nim' => '1130023001',
        ]);

        $this->userB = User::factory()->userUmum()->create([
            'name' => 'Budi Teman',
            'email' => 'budi.santoso@gmail.com',
            'nim' => '1130023002',
        ]);

        $this->userLuar = User::factory()->userUmum()->create([
            'name' => 'Orang Luar',
            'email' => 'luar@gmail.com',
            'nim' => '1130023999',
        ]);
    }

    protected function daftarkan(User $user, ?string $kodeKursi = null, bool $needsReselection = false): KegiatanRegistration
    {
        $kursi = $kodeKursi ? $this->kegiatan->seats()->where('seat_code', $kodeKursi)->first() : null;
        if ($kursi) {
            $kursi->update(['status' => SeatStatus::Booked]);
        }

        return KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id,
            'booker_user_id' => $user->id,
            'participant_user_id' => $user->id,
            'ticket_holder_name' => $user->name,
            'ticket_holder_email' => $user->email,
            'ticket_holder_identity' => $user->nim,
            'seat_id' => $kursi?->id,
            'nomor_tiket' => $kursi
                ? KegiatanRegistration::generateNomorTiket($this->kegiatan->id, $kursi->seat_code)
                : 'TIK-'.$this->kegiatan->id.'-'.strtoupper(uniqid()),
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => $needsReselection,
        ]);
    }

    /**
     * Skenario 1: Denah berlorong tengah tidak mengizinkan booking pada koordinat gang.
     */
    public function test_denah_berlorong_tengah_tidak_mengizinkan_booking_pada_koordinat_gang(): void
    {
        // Pastikan denah bioskop memiliki konfigurasi aisle_after_col = 6
        $this->assertSame(6, $this->kegiatan->seat_layout_config['aisle_after_col']);

        // Koordinat gang di antara kolom 6 dan 7 adalah celah visual dan TIDAK tersimpan sebagai kursi di database
        $gangCodes = ['A00', 'A-GANG', 'B-GANG', 'C-AISLE'];
        foreach ($gangCodes as $code) {
            $this->assertFalse(
                $this->kegiatan->seats()->where('seat_code', $code)->exists(),
                "Koordinat gang {$code} tidak boleh ada di tabel kegiatan_seats."
            );
        }

        $regA = $this->daftarkan($this->userA);

        // Jika mencoba mengirim ID kursi fiktif / gang (misal 99999), server menolak dengan 422
        $response = $this->actingAs($this->userA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regA->id, 'seat_id' => 99999],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allocations.0.seat_id']);
    }

    /**
     * Skenario 2: User A berhasil memilih kursi sendiri sekaligus memilihkan kursi untuk teman yang sudah terdaftar tanpa kursi.
     */
    public function test_user_a_berhasil_memilih_kursi_sendiri_sekaligus_memilihkan_kursi_untuk_teman_terdaftar(): void
    {
        $regA = $this->daftarkan($this->userA, null); // Tanpa kursi
        $regB = $this->daftarkan($this->userB, null); // Tanpa kursi

        $seatA = $this->kegiatan->seats()->where('seat_code', 'B05')->firstOrFail();
        $seatB = $this->kegiatan->seats()->where('seat_code', 'B06')->firstOrFail();

        $response = $this->actingAs($this->userA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regA->id, 'seat_id' => $seatA->id],
                    ['registration_id' => $regB->id, 'seat_id' => $seatB->id],
                ],
            ]);

        $response->assertSuccessful();
        $response->assertJson(['success' => true]);

        // Verifikasi kursi User A
        $this->assertEquals($seatA->id, $regA->fresh()->seat_id);
        $this->assertEquals(SeatStatus::Booked, $seatA->fresh()->status);

        // Verifikasi kursi User B (teman)
        $this->assertEquals($seatB->id, $regB->fresh()->seat_id);
        $this->assertEquals(SeatStatus::Booked, $seatB->fresh()->status);
    }

    /**
     * Skenario 3: Memilihkan kursi untuk user yang BELUM terdaftar di kegiatan menghasilkan error 422.
     */
    public function test_memilihkan_kursi_untuk_user_yang_belum_terdaftar_menghasilkan_error_422(): void
    {
        $regA = $this->daftarkan($this->userA, null);
        $seat = $this->kegiatan->seats()->where('seat_code', 'B01')->firstOrFail();

        // 88888 adalah registration_id yang tidak terdaftar di kegiatan ini
        $response = $this->actingAs($this->userA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regA->id, 'seat_id' => $seat->id],
                    ['registration_id' => 88888, 'seat_id' => $this->kegiatan->seats()->where('seat_code', 'B02')->first()->id],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allocations.1.registration_id']);
    }

    /**
     * Skenario 4: Memilihkan kursi untuk user yang SUDAH memiliki kursi menghasilkan error 422.
     */
    public function test_memilihkan_kursi_untuk_user_yang_sudah_memiliki_kursi_menghasilkan_error_422(): void
    {
        $regA = $this->daftarkan($this->userA, null);
        // User B sudah memiliki kursi B01 dan tidak perlu pilih ulang
        $regB = $this->daftarkan($this->userB, 'B01', false);

        $seatBaruA = $this->kegiatan->seats()->where('seat_code', 'B07')->firstOrFail();
        $seatBaruB = $this->kegiatan->seats()->where('seat_code', 'B08')->firstOrFail();

        $response = $this->actingAs($this->userA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regA->id, 'seat_id' => $seatBaruA->id],
                    ['registration_id' => $regB->id, 'seat_id' => $seatBaruB->id],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allocations.1.registration_id']);
    }

    /**
     * Skenario 5: Kursi yang dipilih otomatis terhubung ke baris registrasi teman dan tiket ter-generate.
     */
    public function test_kursi_yang_dipilih_otomatis_terhubung_ke_registrasi_teman_dan_tiket_tergenerate(): void
    {
        $regA = $this->daftarkan($this->userA, 'A01'); // User A sudah punya kursi
        $regB = $this->daftarkan($this->userB, null); // User B belum punya kursi

        $seatTeman = $this->kegiatan->seats()->where('seat_code', 'B10')->firstOrFail();

        // User A memilihkan kursi untuk User B
        $response = $this->actingAs($this->userA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regB->id, 'seat_id' => $seatTeman->id],
                ],
            ]);

        $response->assertSuccessful();

        $regBFresh = $regB->fresh();
        $this->assertEquals($seatTeman->id, $regBFresh->seat_id);
        $this->assertFalse((bool) $regBFresh->needs_seat_reselection);

        // Penomoran tiket definitif mengandung ID kegiatan dan kode kursi B10
        $this->assertStringContainsString('B10', $regBFresh->nomor_tiket);
        $this->assertStringContainsString((string) $this->kegiatan->id, $regBFresh->nomor_tiket);

        // Memicu pengiriman email notifikasi tiket
        Mail::assertSent(TicketIssuedMail::class, function ($mail) use ($regBFresh) {
            return $mail->registration->id === $regBFresh->id;
        });
    }

    /**
     * Skenario 6: Percobaan booking saat saklar is_seat_booking_active dinonaktifkan menghasilkan error 403.
     */
    public function test_percobaan_booking_saat_saklar_seat_booking_nonaktif_menghasilkan_error_403(): void
    {
        $regA = $this->daftarkan($this->userA, null);
        $seat = $this->kegiatan->seats()->where('seat_code', 'B01')->firstOrFail();

        // Super Admin menonaktifkan saklar booking kursi
        $this->kegiatan->update(['is_seat_booking_active' => false]);

        $response = $this->actingAs($this->userA)
            ->postJson(route('kegiatan.pilih-kursi', $this->kegiatan->id), [
                'allocations' => [
                    ['registration_id' => $regA->id, 'seat_id' => $seat->id],
                ],
            ]);

        $response->assertStatus(403);
    }

    /**
     * Skenario Tambahan: Endpoint peserta-tanpa-kursi mengembalikan peserta terdaftar eligible dengan email disamarkan.
     */
    public function test_endpoint_peserta_tanpa_kursi_hanya_menampilkan_peserta_eligible_dengan_email_disamarkan(): void
    {
        $regA = $this->daftarkan($this->userA, null);
        $regB = $this->daftarkan($this->userB, null); // Tanpa kursi
        $regLuar = $this->daftarkan($this->userLuar, 'A02'); // Sudah punya kursi

        // 1. User yang belum login / tidak terdaftar ditolak 403
        $unregUser = User::factory()->userUmum()->create();
        $this->actingAs($unregUser)
            ->getJson(route('kegiatan.peserta-tanpa-kursi', $this->kegiatan->id))
            ->assertStatus(403);

        // 2. User A mencari teman
        $res = $this->actingAs($this->userA)
            ->getJson(route('kegiatan.peserta-tanpa-kursi', [
                'id' => $this->kegiatan->id,
                'search' => 'Budi',
            ]))
            ->assertSuccessful();

        $data = $res->json();
        $this->assertCount(1, $data);
        $this->assertEquals($regB->id, $data[0]['id']);
        $this->assertEquals('Budi Teman', $data[0]['nama_lengkap']);
        $this->assertEquals('1130023002', $data[0]['nim']);

        // Email harus disamarkan (misal: "bu***o@gmail.com")
        $this->assertStringContainsString('***', $data[0]['email']);
        $this->assertNotEquals('budi.santoso@gmail.com', $data[0]['email']);

        // UserLuar yang sudah punya kursi tidak boleh muncul di hasil pencarian
        $resLuar = $this->actingAs($this->userA)
            ->getJson(route('kegiatan.peserta-tanpa-kursi', [
                'id' => $this->kegiatan->id,
                'search' => 'Luar',
            ]))
            ->assertSuccessful();

        $this->assertCount(0, $resLuar->json());
    }
}
