<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use App\Enums\UserRole;
use App\Jobs\SendSeatReselectionNotificationJob;
use App\Mail\SeatReselectionMail;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingLifecycleAndVenueTest extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_blocked' => false,
        ]);
    }

    protected function createMahasiswaKipk(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::MahasiswaKipk,
            'is_blocked' => false,
        ], $attributes));
    }

    protected function createPenilaiKarya(): User
    {
        return User::factory()->create([
            'role' => UserRole::PenilaiKarya,
            'is_blocked' => false,
        ]);
    }

    protected function createKegiatanWithSeats(int $rowsCount = 3, int $colsCount = 5): Kegiatan
    {
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Teknologi Nasional ' . Str::random(4),
            'deskripsi' => 'Seminar teknologi cloud dan AI.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(5),
            'tanggal_selesai' => now()->addDays(5)->addHours(4),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower UNUSA Lt. 9',
            'file_panduan_pdf' => 'kegiatan/panduan/default.pdf',
            'kuota' => 100,
            'kuota_terisi' => 0,
            'biaya' => 0,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => true,
            'booking_opens_at' => now()->subDay(),
            'booking_closes_at' => now()->addDays(3),
            'venue_change_count' => 0,
            'seat_layout_config' => [
                'rows' => array_slice(['A', 'B', 'C', 'D'], 0, $rowsCount),
                'cols_per_row' => $colsCount,
                'vip_rows' => ['A'],
            ],
        ]);

        $rows = array_slice(['A', 'B', 'C', 'D'], 0, $rowsCount);
        $seats = [];
        $now = now();

        foreach ($rows as $r) {
            for ($c = 1; $c <= $colsCount; $c++) {
                $seatCode = $r . str_pad((string) $c, 2, '0', STR_PAD_LEFT);
                $seats[] = [
                    'kegiatan_id' => $kegiatan->id,
                    'seat_code' => $seatCode,
                    'row_label' => $r,
                    'seat_number' => $c,
                    'col_number' => $c,
                    'tier' => ($r === 'A') ? SeatTier::Vip->value : SeatTier::Reguler->value,
                    'status' => SeatStatus::Available->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        KegiatanSeat::insert($seats);

        return $kegiatan;
    }

    /**
     * 1. Safeguard Kapasitas: Kapasitas baru < kursi terisi ditolak dan data tidak berubah sama sekali
     */
    public function test_capacity_safeguard_rejects_new_capacity_less_than_booked_seats(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createMahasiswaKipk();
        $kegiatan = $this->createKegiatanWithSeats(2, 5); // 10 kursi total

        // Pesan 3 kursi
        $seatA1 = $kegiatan->seats()->where('seat_code', 'A01')->first();
        $seatA2 = $kegiatan->seats()->where('seat_code', 'A02')->first();
        $seatA3 = $kegiatan->seats()->where('seat_code', 'A03')->first();

        $seatA1->update(['status' => SeatStatus::Booked]);
        $seatA2->update(['status' => SeatStatus::Booked]);
        $seatA3->update(['status' => SeatStatus::Booked]);

        $initialSeatCount = $kegiatan->seats()->count();
        $initialVenueCount = $kegiatan->venue_change_count;

        // Admin mencoba memindahkan ke ruangan baru dengan kapasitas hanya 2 kursi (< 3 kursi terisi)
        $response = $this->actingAs($admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), [
            'total_seats' => 2,
            'layout_changed' => true,
            'is_seat_booking_active' => true,
            'layout_config' => [
                'rows' => ['A'],
                'cols_per_row' => 2,
            ],
            'lokasi_nama' => 'Ruang Rapat Kecil 101',
        ]);

        $response->assertSessionHasErrors('capacity');

        // Pastikan database TIDAK berubah sama sekali
        $kegiatan->refresh();
        $this->assertSame($initialSeatCount, $kegiatan->seats()->count());
        $this->assertSame($initialVenueCount, $kegiatan->venue_change_count);
        $this->assertSame('Auditorium Tower UNUSA Lt. 9', $kegiatan->lokasi_nama);
        $this->assertSame(3, $kegiatan->seats()->where('status', SeatStatus::Booked)->count());
    }

    /**
     * 2. Denah berubah dengan peserta: kursi lama terhapus, seat_id null, needs_seat_reselection true, seat_history terisi, venue_change_count +1, job ter-dispatch
     */
    public function test_layout_change_with_participants_resets_seats_records_history_and_dispatches_job(): void
    {
        Queue::fake();

        $admin = $this->createSuperAdmin();
        $student = $this->createMahasiswaKipk();
        $kegiatan = $this->createKegiatanWithSeats(2, 5); // 10 kursi

        $seatB1 = $kegiatan->seats()->where('seat_code', 'B01')->first();
        $seatB1->update(['status' => SeatStatus::Booked]);

        $registration = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $student->id,
            'participant_user_id' => $student->id,
            'seat_id' => $seatB1->id,
            'nomor_tiket' => 'TKT-TEST-B01',
            'participant_name' => $student->name,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => false,
        ]);

        // Pindahkan ke ruangan baru dengan 15 kursi
        $response = $this->actingAs($admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), [
            'total_seats' => 15,
            'layout_changed' => true,
            'is_seat_booking_active' => true,
            'layout_config' => [
                'rows' => ['A', 'B', 'C'],
                'cols_per_row' => 5,
                'vip_rows' => ['A'],
            ],
            'lokasi_nama' => 'Hall Utama Kampus A',
        ]);

        $response->assertSessionHas('success');

        $kegiatan->refresh();
        $registration->refresh();

        // Kursi lama terhapus, jumlah kursi baru adalah 15
        $this->assertSame(15, $kegiatan->seats()->count());
        $this->assertSame(1, $kegiatan->venue_change_count);
        $this->assertSame('Hall Utama Kampus A', $kegiatan->lokasi_nama);

        // Registrasi peserta di-reset
        $this->assertNull($registration->seat_id);
        $this->assertTrue($registration->needs_seat_reselection);

        // Riwayat kursi tercatat dengan format UTC
        $this->assertIsArray($registration->seat_history);
        $this->assertCount(1, $registration->seat_history);
        $this->assertSame('Auditorium Tower UNUSA Lt. 9', $registration->seat_history[0]['venue']);
        $this->assertSame('B01', $registration->seat_history[0]['seat']);
        $this->assertArrayHasKey('changed_at', $registration->seat_history[0]);

        // Job terkirim
        Queue::assertPushed(SendSeatReselectionNotificationJob::class, function ($job) use ($kegiatan) {
            return $job->kegiatan->id === $kegiatan->id;
        });
    }

    /**
     * 3. Denah tidak berubah: kursi dan peserta tetap, kuota pendaftaran tidak berubah, hanya metadata yang berubah
     */
    public function test_layout_not_changed_preserves_seats_and_participants(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createMahasiswaKipk();
        $kegiatan = $this->createKegiatanWithSeats(2, 5); // 10 kursi, kuota = 100

        $seatA1 = $kegiatan->seats()->where('seat_code', 'A01')->first();
        $seatA1->update(['status' => SeatStatus::Booked]);

        $registration = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $student->id,
            'participant_user_id' => $student->id,
            'seat_id' => $seatA1->id,
            'nomor_tiket' => 'TKT-TEST-A01',
            'participant_name' => $student->name,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
        ]);

        $originalKuota = $kegiatan->kuota; // 100

        // Admin hanya memperbarui jadwal booking & nama ruangan tanpa mengubah denah
        $newClosesAt = now()->addDays(4)->format('Y-m-d H:i:s');

        $response = $this->actingAs($admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), [
            'total_seats' => 10,
            'layout_changed' => false,
            'is_seat_booking_active' => true,
            'booking_closes_at' => $newClosesAt,
            'lokasi_nama' => 'Auditorium Tower UNUSA Lt. 9 (Renovated)',
        ]);

        $response->assertSessionHas('success');

        $kegiatan->refresh();
        $registration->refresh();

        // Kuota umum TIDAK disinkronkan ke total_seats (tetap 100)
        $this->assertSame($originalKuota, $kegiatan->kuota);

        // Kursi dan registrasi tetap utuh
        $this->assertSame($seatA1->id, $registration->seat_id);
        $this->assertFalse($registration->needs_seat_reselection);
        $this->assertSame(0, $kegiatan->venue_change_count);
        $this->assertSame('Auditorium Tower UNUSA Lt. 9 (Renovated)', $kegiatan->lokasi_nama);
    }

    /**
     * 4. Jadwal & Freeze: Booking ditutup (toggle off atau di luar jadwal) menolak booking kursi baru, tapi kursi yang sudah ada tetap
     */
    public function test_booking_freeze_rejects_new_seat_booking_while_preserving_existing_seats(): void
    {
        $student = $this->createMahasiswaKipk();
        $kegiatan = $this->createKegiatanWithSeats(2, 5);

        // 1 Kursi sudah ter-booking
        $seatA1 = $kegiatan->seats()->where('seat_code', 'A01')->first();
        $seatA1->update(['status' => SeatStatus::Booked]);

        $seatA2 = $kegiatan->seats()->where('seat_code', 'A02')->first();

        // Set booking kursi ditutup (is_seat_booking_active = false)
        $kegiatan->update(['is_seat_booking_active' => false]);
        $this->assertFalse($kegiatan->isSeatBookingOpen());

        // Peserta mencoba checkout dengan memilih kursi A02
        $response = $this->actingAs($student)->post(route('kegiatan.checkout', $kegiatan->id), [
            'delegates' => [
                [
                    'name' => $student->name,
                    'email' => $student->email,
                    'seat_id' => $seatA2->id,
                ],
            ],
        ]);

        $response->assertSessionHas('error');

        // Kursi A01 yang sudah ada TETAP berstatus Booked
        $seatA1->refresh();
        $seatA2->refresh();
        $this->assertSame(SeatStatus::Booked, $seatA1->status);
        $this->assertSame(SeatStatus::Available, $seatA2->status);

        // Uji jika booking di luar rentang jadwal (booking_opens_at masih di masa depan)
        $kegiatan->update([
            'is_seat_booking_active' => true,
            'booking_opens_at' => now()->addDay(),
            'booking_closes_at' => now()->addDays(3),
        ]);
        $this->assertFalse($kegiatan->isSeatBookingOpen());

        $response2 = $this->actingAs($student)->post(route('kegiatan.checkout', $kegiatan->id), [
            'delegates' => [
                [
                    'name' => $student->name,
                    'email' => $student->email,
                    'seat_id' => $seatA2->id,
                ],
            ],
        ]);
        $response2->assertSessionHas('error');
    }

    /**
     * 5. Gate isSeatBookingOpen() HANYA untuk kegiatan dengan denah kursi. Kegiatan tanpa denah tetap bisa checkout
     */
    public function test_events_without_seat_selection_can_checkout_even_if_seat_booking_is_inactive(): void
    {
        $student = $this->createMahasiswaKipk();

        // Kegiatan online tanpa denah kursi
        $webinar = Kegiatan::create([
            'nama_kegiatan' => 'Webinar Karir Global',
            'kategori' => 'webinar',
            'metode_pelaksanaan' => 'online',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(3),
            'lokasi_type' => 'online',
            'lokasi_nama' => 'Zoom Meeting',
            'file_panduan_pdf' => 'default.pdf',
            'kuota' => 100,
            'kuota_terisi' => 0,
            'biaya' => 0,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false, // Nonaktif karena tidak pakai kursi
        ]);

        $this->assertFalse($webinar->hasSeatLayout());

        $response = $this->actingAs($student)->post(route('kegiatan.checkout', $webinar->id), [
            'delegates' => [
                [
                    'name' => $student->name,
                    'email' => $student->email,
                    'seat_id' => null,
                ],
            ],
        ]);

        $response->assertRedirect(route('dashboard.kegiatan.saya'));
        $this->assertDatabaseHas('kegiatan_registrations', [
            'kegiatan_id' => $webinar->id,
            'participant_user_id' => $student->id,
        ]);
    }

    /**
     * 6. Peserta dengan needs_seat_reselection = true dapat memilih ulang kursi saat booking buka, lalu flag kembali false
     */
    public function test_affected_participant_can_reselect_seat_and_flag_returns_false(): void
    {
        $student = $this->createMahasiswaKipk();
        $kegiatan = $this->createKegiatanWithSeats(2, 5);

        // Registrasi yang kehilangan kursi
        $registration = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $student->id,
            'participant_user_id' => $student->id,
            'seat_id' => null,
            'nomor_tiket' => 'TKT-RESELECT-01',
            'participant_name' => $student->name,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => true,
        ]);

        $newSeat = $kegiatan->seats()->where('seat_code', 'B03')->first();
        $this->assertSame(SeatStatus::Available, $newSeat->status);

        // Peserta memilih kursi B03
        $response = $this->actingAs($student)->post(route('kegiatan.reselect-seat', $kegiatan->id), [
            'registration_id' => $registration->id,
            'seat_id' => $newSeat->id,
        ]);

        $response->assertRedirect(route('dashboard.kegiatan.saya'));
        $response->assertSessionHas('success');

        $registration->refresh();
        $newSeat->refresh();

        $this->assertSame($newSeat->id, $registration->seat_id);
        $this->assertFalse($registration->needs_seat_reselection);
        $this->assertSame(SeatStatus::Booked, $newSeat->status);
    }

    /**
     * 7. Role selain super_admin ditolak memanggil updateVenueConfig (403)
     */
    public function test_non_super_admin_cannot_update_venue_config(): void
    {
        $penilai = $this->createPenilaiKarya();
        $kegiatan = $this->createKegiatanWithSeats(2, 5);

        $response = $this->actingAs($penilai)->put(route('admin.kegiatan.venue-config', $kegiatan->id), [
            'total_seats' => 20,
            'layout_changed' => false,
            'is_seat_booking_active' => true,
        ]);

        // EnsureRole mengarahkan penilai_karya ke admin.penilaian
        $response->assertRedirect(route('admin.penilaian'));
    }

    /**
     * 8. E-tiket dan pembayaran peserta tetap valid setelah denah di-reset
     */
    public function test_eticket_and_payment_remain_valid_after_venue_reset(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createMahasiswaKipk();
        $kegiatan = $this->createKegiatanWithSeats(2, 5);

        $payment = Payment::create([
            'order_id' => 'ORDER-' . Str::random(10),
            'user_id' => $student->id,
            'gross_amount' => 50000,
            'payment_method' => 'QRIS',
            'payment_status' => 'settlement',
            'paid_at' => now(),
        ]);

        $seat = $kegiatan->seats()->first();
        $seat->update(['status' => SeatStatus::Booked]);

        $registration = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $student->id,
            'participant_user_id' => $student->id,
            'seat_id' => $seat->id,
            'payment_id' => $payment->id,
            'nomor_tiket' => 'TKT-PAID-IMMUTABLE-01',
            'participant_name' => $student->name,
            'amount_paid' => 50000,
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'status_kehadiran' => AttendanceStatus::Registered,
        ]);

        // Denah di-reset oleh admin
        $this->actingAs($admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), [
            'total_seats' => 20,
            'layout_changed' => true,
            'is_seat_booking_active' => true,
            'layout_config' => [
                'rows' => ['A', 'B'],
                'cols_per_row' => 10,
            ],
            'lokasi_nama' => 'Grand Ballroom Tower UNUSA',
        ]);

        $registration->refresh();
        $payment->refresh();

        // E-tiket nomor_tiket, payment_id, dan status_pembayaran tetap utuh dan valid
        $this->assertSame('TKT-PAID-IMMUTABLE-01', $registration->nomor_tiket);
        $this->assertSame($payment->id, $registration->payment_id);
        $this->assertSame(RegistrationPaymentStatus::Paid, $registration->status_pembayaran);
        $this->assertSame('settlement', $payment->payment_status instanceof \App\Enums\GatewayPaymentStatus ? $payment->payment_status->value : $payment->payment_status);
    }

    /**
     * 9. Revisi 3: Registrasi batal/expired dikecualikan dari hitungan kapasitas, reset kursi, dan pengiriman email
     */
    public function test_expired_and_cancelled_registrations_are_excluded_from_capacity_reset_and_email(): void
    {
        Mail::fake();

        $admin = $this->createSuperAdmin();
        $studentActive = $this->createMahasiswaKipk(['email' => 'active@unusa.ac.id']);
        $studentExpired = $this->createMahasiswaKipk(['email' => 'expired@unusa.ac.id']);
        $studentFailed = $this->createMahasiswaKipk(['email' => 'failed@unusa.ac.id']);

        $kegiatan = $this->createKegiatanWithSeats(2, 5); // 10 kursi total

        $seat1 = $kegiatan->seats()->where('seat_code', 'A01')->first();
        $seat2 = $kegiatan->seats()->where('seat_code', 'A02')->first();
        $seat3 = $kegiatan->seats()->where('seat_code', 'A03')->first();

        $seat1->update(['status' => SeatStatus::Booked]);
        $seat2->update(['status' => SeatStatus::Booked]);
        $seat3->update(['status' => SeatStatus::Booked]);

        // Registrasi Aktif (Paid)
        $regActive = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $studentActive->id,
            'participant_user_id' => $studentActive->id,
            'seat_id' => $seat1->id,
            'nomor_tiket' => 'TKT-ACTIVE-01',
            'participant_name' => $studentActive->name,
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => false,
        ]);

        // Registrasi Expired (Batal/Kedaluwarsa)
        $regExpired = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $studentExpired->id,
            'participant_user_id' => $studentExpired->id,
            'seat_id' => $seat2->id,
            'nomor_tiket' => 'TKT-EXPIRED-01',
            'participant_name' => $studentExpired->name,
            'status_pembayaran' => RegistrationPaymentStatus::Expired,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => false,
        ]);

        // Registrasi Failed (Gagal)
        $regFailed = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $studentFailed->id,
            'participant_user_id' => $studentFailed->id,
            'seat_id' => $seat3->id,
            'nomor_tiket' => 'TKT-FAILED-01',
            'participant_name' => $studentFailed->name,
            'status_pembayaran' => RegistrationPaymentStatus::Failed,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => false,
        ]);

        // Total kursi yang bertuliskan Booked di database = 3, namun peserta aktif hanya 1.
        // Admin memindahkan ke ruangan dengan total_seats = 2.
        // Karena registrasi expired & failed dikecualikan, total_seats = 2 cukup untuk 1 peserta aktif.
        $response = $this->actingAs($admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), [
            'total_seats' => 2,
            'layout_changed' => true,
            'is_seat_booking_active' => true,
            'layout_config' => [
                'rows' => ['A'],
                'cols_per_row' => 2,
            ],
            'lokasi_nama' => 'Ruang Seminar 2 Kursi',
        ]);

        $response->assertSessionHas('success');

        $regActive->refresh();
        $regExpired->refresh();
        $regFailed->refresh();

        // 1. Peserta aktif di-reset dan flag needs_seat_reselection menjadi true
        $this->assertNull($regActive->seat_id);
        $this->assertTrue($regActive->needs_seat_reselection);
        $this->assertNotEmpty($regActive->seat_history);

        // 2. Peserta expired & failed TIDAK di-reset menjadi needs_seat_reselection (tetap false)
        $this->assertFalse($regExpired->needs_seat_reselection);
        $this->assertEmpty($regExpired->seat_history);

        $this->assertFalse($regFailed->needs_seat_reselection);
        $this->assertEmpty($regFailed->seat_history);

        // 3. Jalankan job notifikasi email pemilihan ulang
        (new SendSeatReselectionNotificationJob($kegiatan))->handle();

        // Email hanya dikirim ke peserta aktif
        Mail::assertSent(SeatReselectionMail::class, function ($mail) {
            return $mail->hasTo('active@unusa.ac.id');
        });

        // Email TIDAK dikirim ke peserta expired maupun failed
        Mail::assertNotSent(SeatReselectionMail::class, function ($mail) {
            return $mail->hasTo('expired@unusa.ac.id') || $mail->hasTo('failed@unusa.ac.id');
        });
    }

    /**
     * 10. Revisi 4: Booker memilih ulang kursi untuk delegasinya, email pemilihan ulang dengan link ke booker, email delegasi tanpa link
     */
    public function test_booker_reselects_seat_for_delegation_and_email_rules_for_booker_and_delegates(): void
    {
        Mail::fake();

        $booker = $this->createMahasiswaKipk(['email' => 'booker@unusa.ac.id', 'name' => 'Booker Utama']);
        $delegate = $this->createMahasiswaKipk(['email' => 'delegasi@unusa.ac.id', 'name' => 'Peserta Delegasi']);

        $kegiatan = $this->createKegiatanWithSeats(2, 5);

        // Registrasi Delegasi di mana booker adalah $booker dan participant adalah $delegate
        $regDelegate = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $booker->id,
            'participant_user_id' => $delegate->id,
            'seat_id' => null,
            'nomor_tiket' => 'TKT-DELEGATE-01',
            'participant_name' => $delegate->name,
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => true,
            'delegation_data' => [
                'email' => $delegate->email,
                'is_booker' => false,
            ],
        ]);

        // Jalankan Job Notifikasi
        (new SendSeatReselectionNotificationJob($kegiatan))->handle();

        // 1. Email ke booker: memiliki isBooker = true dan reselectionUrl terisi
        Mail::assertSent(SeatReselectionMail::class, function ($mail) {
            return $mail->hasTo('booker@unusa.ac.id')
                && $mail->isBooker === true
                && ! empty($mail->reselectionUrl);
        });

        // 2. Email ke delegasi: memiliki isBooker = false dan reselectionUrl = null (tanpa tautan)
        Mail::assertSent(SeatReselectionMail::class, function ($mail) {
            return $mail->hasTo('delegasi@unusa.ac.id')
                && $mail->isBooker === false
                && empty($mail->reselectionUrl);
        });

        // Render mail untuk delegasi untuk memastikan tidak ada URL di konten HTML
        $delegateMail = new SeatReselectionMail($kegiatan, 'Peserta Delegasi', '15 Oktober 2026 12:00 WIB', null, false);
        $renderedHtml = $delegateMail->render();
        $this->assertStringContainsString('dilakukan oleh pemesan tiket (booker)', $renderedHtml);
        $this->assertStringNotContainsString('Pilih Ulang Kursi Sekarang', $renderedHtml);

        // 3. Delegasi mencoba memilih kursi langsung untuk tiketnya (bukan booker) -> DITOLAK
        $newSeat = $kegiatan->seats()->where('seat_code', 'A02')->first();

        $responseDelegate = $this->actingAs($delegate)->post(route('kegiatan.reselect-seat', $kegiatan->id), [
            'registration_id' => $regDelegate->id,
            'seat_id' => $newSeat->id,
        ]);

        $responseDelegate->assertSessionHas('error');
        $regDelegate->refresh();
        $this->assertNull($regDelegate->seat_id);
        $this->assertTrue($regDelegate->needs_seat_reselection);

        // 4. Booker memilihkan kursi untuk delegasinya -> SUKSES
        $responseBooker = $this->actingAs($booker)->post(route('kegiatan.reselect-seat', $kegiatan->id), [
            'registration_id' => $regDelegate->id,
            'seat_id' => $newSeat->id,
        ]);

        $responseBooker->assertRedirect(route('dashboard.kegiatan.saya'));
        $responseBooker->assertSessionHas('success');

        $regDelegate->refresh();
        $this->assertSame($newSeat->id, $regDelegate->seat_id);
        $this->assertFalse($regDelegate->needs_seat_reselection);
    }

    /**
     * 11. Revisi 5 (Penolakan 1): reselectSeat menolak kursi dari kegiatan lain
     */
    public function test_reselect_seat_rejects_seat_from_another_kegiatan(): void
    {
        $student = $this->createMahasiswaKipk();
        $kegiatan1 = $this->createKegiatanWithSeats(2, 5);
        $kegiatan2 = $this->createKegiatanWithSeats(2, 5);

        // Registrasi pada Kegiatan 1
        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan1->id,
            'booker_user_id' => $student->id,
            'participant_user_id' => $student->id,
            'seat_id' => null,
            'nomor_tiket' => 'TKT-CROSS-EVENT-01',
            'participant_name' => $student->name,
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => true,
        ]);

        // Kursi dari Kegiatan 2
        $seatFromKegiatan2 = $kegiatan2->seats()->first();

        $response = $this->actingAs($student)->post(route('kegiatan.reselect-seat', $kegiatan1->id), [
            'registration_id' => $reg->id,
            'seat_id' => $seatFromKegiatan2->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('kegiatan lain', session('error'));

        $reg->refresh();
        $this->assertNull($reg->seat_id);
    }

    /**
     * 12. Revisi 5 (Penolakan 2): reselectSeat menolak kursi yang tidak berstatus available
     */
    public function test_reselect_seat_rejects_seat_that_is_not_available(): void
    {
        $student = $this->createMahasiswaKipk();
        $kegiatan = $this->createKegiatanWithSeats(2, 5);

        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $student->id,
            'participant_user_id' => $student->id,
            'seat_id' => null,
            'nomor_tiket' => 'TKT-UNAVAIL-01',
            'participant_name' => $student->name,
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => true,
        ]);

        // Kursi yang sudah berstatus Booked
        $occupiedSeat = $kegiatan->seats()->first();
        $occupiedSeat->update(['status' => SeatStatus::Booked]);

        $response = $this->actingAs($student)->post(route('kegiatan.reselect-seat', $kegiatan->id), [
            'registration_id' => $reg->id,
            'seat_id' => $occupiedSeat->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('tidak tersedia', session('error'));

        $reg->refresh();
        $this->assertNull($reg->seat_id);
    }

    /**
     * 13. Revisi 5 (Penolakan 3): reselectSeat menolak registrasi yang bukan milik user atau delegasinya
     */
    public function test_reselect_seat_rejects_registration_not_belonging_to_user_or_delegation(): void
    {
        $studentA = $this->createMahasiswaKipk();
        $studentB = $this->createMahasiswaKipk();
        $kegiatan = $this->createKegiatanWithSeats(2, 5);

        // Registrasi milik Student B
        $regStudentB = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $studentB->id,
            'participant_user_id' => $studentB->id,
            'seat_id' => null,
            'nomor_tiket' => 'TKT-OTHER-USER-01',
            'participant_name' => $studentB->name,
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'status_kehadiran' => AttendanceStatus::Registered,
            'needs_seat_reselection' => true,
        ]);

        $availableSeat = $kegiatan->seats()->first();

        // Student A mencoba memilih kursi untuk registrasi Student B
        $response = $this->actingAs($studentA)->post(route('kegiatan.reselect-seat', $kegiatan->id), [
            'registration_id' => $regStudentB->id,
            'seat_id' => $availableSeat->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('bukan pemesan', session('error'));

        $regStudentB->refresh();
        $this->assertNull($regStudentB->seat_id);
    }

    /**
     * 14. Revisi 6: Jadwal diinput dalam Asia/Jakarta disimpan dalam UTC, dan batas 1 menit sebelum/sesudah jam buka dan tutup
     */
    public function test_schedule_input_in_asia_jakarta_stored_in_utc_and_one_minute_boundary_checks(): void
    {
        $admin = $this->createSuperAdmin();
        $kegiatan = $this->createKegiatanWithSeats(2, 5);

        // Admin memasukkan jadwal dalam zona waktu Asia/Jakarta (WIB)
        // Buka: 2026-10-20 10:00:00 WIB (= 2026-10-20 03:00:00 UTC)
        // Tutup: 2026-10-20 18:00:00 WIB (= 2026-10-20 11:00:00 UTC)
        $opensAtWib = '2026-10-20 10:00:00';
        $closesAtWib = '2026-10-20 18:00:00';

        $response = $this->actingAs($admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), [
            'total_seats' => 10,
            'layout_changed' => false,
            'is_seat_booking_active' => true,
            'booking_opens_at' => $opensAtWib,
            'booking_closes_at' => $closesAtWib,
        ]);

        $response->assertSessionHas('success');

        $kegiatan->refresh();

        // 1. Verifikasi tersimpan dalam UTC pada database
        $this->assertSame('2026-10-20 03:00:00', $kegiatan->booking_opens_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 11:00:00', $kegiatan->booking_closes_at->setTimezone('UTC')->format('Y-m-d H:i:s'));

        try {
            // 2. Tepat 1 menit sebelum jam buka (09:59 WIB / 02:59 UTC) -> Booking DITUTUP
            Carbon::setTestNow(Carbon::parse('2026-10-20 09:59:00', 'Asia/Jakarta'));
            $this->assertFalse($kegiatan->isSeatBookingOpen());

            // 3. Tepat 1 menit sesudah jam buka (10:01 WIB / 03:01 UTC) -> Booking DIBUKA
            Carbon::setTestNow(Carbon::parse('2026-10-20 10:01:00', 'Asia/Jakarta'));
            $this->assertTrue($kegiatan->isSeatBookingOpen());

            // 4. Tepat 1 menit sebelum jam tutup (17:59 WIB / 10:59 UTC) -> Booking DIBUKA
            Carbon::setTestNow(Carbon::parse('2026-10-20 17:59:00', 'Asia/Jakarta'));
            $this->assertTrue($kegiatan->isSeatBookingOpen());

            // 5. Tepat 1 menit sesudah jam tutup (18:01 WIB / 11:01 UTC) -> Booking DITUTUP
            Carbon::setTestNow(Carbon::parse('2026-10-20 18:01:00', 'Asia/Jakarta'));
            $this->assertFalse($kegiatan->isSeatBookingOpen());

            // 6. Batas detik: jendela booking adalah [buka, tutup).
            Carbon::setTestNow(Carbon::parse('2026-10-20 09:59:59', 'Asia/Jakarta'));
            $this->assertFalse($kegiatan->isSeatBookingOpen(), 'Satu detik sebelum jam buka harus tertutup.');

            Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', 'Asia/Jakarta'));
            $this->assertTrue($kegiatan->isSeatBookingOpen(), 'Tepat jam buka harus terbuka.');

            Carbon::setTestNow(Carbon::parse('2026-10-20 17:59:59', 'Asia/Jakarta'));
            $this->assertTrue($kegiatan->isSeatBookingOpen(), 'Satu detik sebelum jam tutup harus terbuka.');

            Carbon::setTestNow(Carbon::parse('2026-10-20 18:00:00', 'Asia/Jakarta'));
            $this->assertFalse($kegiatan->isSeatBookingOpen(), 'Tepat jam tutup harus tertutup.');
        } finally {
            Carbon::setTestNow(); // Reset clock
        }
    }
}
