<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Events\KegiatanUpdatedEvent;
use App\Jobs\SendSeatReselectionNotificationJob;
use App\Mail\SeatReselectionMail;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\User;
use App\Services\Kegiatan\DenahKursiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tab "Denah & Booking" di Admin/Kegiatan/Show: peta kursi admin, jadwal booking, denah (baris, kolom,
 * VIP/panitia, kursi diblokir), pindah venue, dan pratinjau dampak untuk dialog konfirmasi.
 */
class DenahBookingAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([KegiatanUpdatedEvent::class]);
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00', 'Asia/Jakarta'));
        $this->admin = User::factory()->superAdmin()->create();
    }

    /** Kegiatan dengan denah 2 baris (A VIP, B) x 4 kursi. */
    protected function kegiatan(array $atribut = []): Kegiatan
    {
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Denah', 'kategori' => 'seminar', 'metode_pelaksanaan' => 'offline', 'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addWeek(), 'lokasi_type' => 'external', 'lokasi_nama' => 'Hotel Lama',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf', 'biaya' => 0, 'kuota' => 100, 'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => true, 'booking_opens_at' => now()->subDay(), 'booking_closes_at' => now()->addDays(3),
            ...$atribut,
        ]);
        $kegiatan->update(['seat_layout_config' => app(DenahKursiService::class)->buat($kegiatan, [
            'rows' => ['A', 'B'], 'cols_per_row' => 4, 'vip_rows' => ['A'], 'panitia_rows' => [],
        ])]);

        return $kegiatan->fresh();
    }

    protected function pesan(Kegiatan $kegiatan, string $kode, User $booker, ?User $peserta, string $nama, array $lain = []): KegiatanRegistration
    {
        $kursi = $kegiatan->seats()->where('seat_code', $kode)->firstOrFail();
        $kursi->update(['status' => SeatStatus::Booked]);

        return KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id, 'booker_user_id' => $booker->id, 'participant_user_id' => $peserta?->id,
            'seat_id' => $kursi->id, 'nomor_tiket' => KegiatanRegistration::generateTicketNumber($kegiatan->id, $kode),
            'participant_name' => $nama, 'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered, ...$lain,
        ]);
    }

    /** Dua pemesan; pemesan pertama punya delegasi berakun dan tamu berpemail; satu registrasi kedaluwarsa. */
    protected function isiPeserta(Kegiatan $kegiatan): array
    {
        $budi = User::factory()->mahasiswaKipk()->create(['name' => 'Budi', 'email' => 'budi@gmail.com']);
        $rina = User::factory()->userUmum()->create(['name' => 'Rina', 'email' => 'rina@gmail.com']);
        $sari = User::factory()->userUmum()->create(['name' => 'Sari', 'email' => 'sari@gmail.com']);

        return [
            'budi' => $this->pesan($kegiatan, 'A01', $budi, $budi, 'Budi', ['delegation_data' => ['is_booker' => true]]),
            'rina' => $this->pesan($kegiatan, 'A02', $budi, $rina, 'Rina', ['delegation_data' => ['email' => 'rina@gmail.com']]),
            'tamu' => $this->pesan($kegiatan, 'A03', $budi, null, 'Tamu', ['delegation_data' => ['email' => 'tamu@gmail.com']]),
            'sari' => $this->pesan($kegiatan, 'B01', $sari, $sari, 'Sari'),
            'kedaluwarsa' => $this->pesan($kegiatan, 'B02', $sari, null, 'Lama', ['status_pembayaran' => RegistrationPaymentStatus::Expired]),
        ];
    }

    protected function props($response): array
    {
        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $m);

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR)['props'];
    }

    protected function payload(array $layout, array $lain = []): array
    {
        return [
            'layout_changed' => true, 'is_seat_booking_active' => true,
            'booking_opens_at' => '2026-10-31 10:00:00', 'booking_closes_at' => '2026-11-04 10:00:00',
            'layout_config' => $layout, ...$lain,
        ];
    }

    public function test_admin_show_has_seat_map_with_holders_layout_and_booking_status(): void
    {
        $kegiatan = $this->kegiatan();
        $this->isiPeserta($kegiatan);

        $props = $this->props($this->actingAs($this->admin)->get(route('admin.kegiatan.show', $kegiatan->id))->assertOk());

        $this->assertSame('dibuka', $props['kegiatan']['status_booking']);
        $this->assertSame(['rows' => ['A', 'B'], 'cols_per_row' => 4, 'vip_rows' => ['A'], 'panitia_rows' => [], 'kapasitas' => 8], $props['denah']);
        $kursi = collect($props['kursi'])->keyBy('kode');
        $this->assertSame(['A01', 'A02', 'A03', 'A04', 'B01', 'B02', 'B03', 'B04'], $kursi->keys()->all());
        $this->assertSame(['booked', 'Rina', 'vip'], [$kursi['A02']['status'], $kursi['A02']['pemegang'], $kursi['A02']['tier']]);
        $this->assertSame(['available', null], [$kursi['A04']['status'], $kursi['A04']['pemegang']]);
        $this->assertNull($kursi['B02']['pemegang'], 'Registrasi kedaluwarsa bukan pemegang kursi.');
    }

    public function test_booking_status_has_four_states_with_exclusive_close(): void
    {
        $kegiatan = $this->kegiatan(['booking_opens_at' => now()->addHour(), 'booking_closes_at' => now()->addHours(2)]);
        $this->assertSame(Kegiatan::BOOKING_BELUM_DIBUKA, $kegiatan->statusBookingKursi());

        $this->travel(1)->hours();
        $this->assertSame(Kegiatan::BOOKING_DIBUKA, $kegiatan->statusBookingKursi());

        $this->travel(1)->hours();
        $this->assertSame(Kegiatan::BOOKING_DITUTUP, $kegiatan->statusBookingKursi(), 'Tertutup tepat pada detik batasnya.');

        $kegiatan->update(['is_seat_booking_active' => false, 'booking_closes_at' => null]);
        $this->assertSame(Kegiatan::BOOKING_DIBEKUKAN, $kegiatan->statusBookingKursi());
    }

    public function test_preview_reports_affected_participants_and_emails_exactly_like_the_job(): void
    {
        $kegiatan = $this->kegiatan();
        $this->isiPeserta($kegiatan);

        $pratinjau = $this->actingAs($this->admin)
            ->postJson(route('admin.kegiatan.venue-config.pratinjau', $kegiatan->id), $this->payload(['rows' => ['A', 'B', 'C'], 'cols_per_row' => 3]))
            ->assertOk()->json();

        $this->assertTrue($pratinjau['akan_reset']);
        $this->assertSame(4, $pratinjau['peserta_terdampak'], 'Registrasi kedaluwarsa tidak dihitung.');
        $this->assertSame(['email_dikirim' => 4, 'email_pemesan' => 2, 'email_delegasi' => 2], array_intersect_key($pratinjau, array_flip(['email_dikirim', 'email_pemesan', 'email_delegasi'])));
        $this->assertSame(9, $pratinjau['kapasitas']);
        $this->assertTrue($pratinjau['cukup']);

        // Pratinjau tidak mengubah apa pun.
        $this->assertSame(8, $kegiatan->seats()->count());
        $this->assertSame(0, KegiatanRegistration::where('needs_seat_reselection', true)->count());

        // Menyimpan lalu menjalankan job mengirim email sebanyak yang dilaporkan pratinjau.
        Mail::fake();
        Queue::fake();
        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload(['rows' => ['A', 'B', 'C'], 'cols_per_row' => 3]))
            ->assertSessionHasNoErrors();
        (new SendSeatReselectionNotificationJob($kegiatan->fresh()))->handle();
        Mail::assertSent(SeatReselectionMail::class, $pratinjau['email_dikirim']);
        $this->assertSame(4, KegiatanRegistration::where('needs_seat_reselection', true)->count());
    }

    public function test_preview_rejects_insufficient_capacity_with_the_server_message(): void
    {
        $kegiatan = $this->kegiatan();
        $this->isiPeserta($kegiatan);

        $pratinjau = $this->actingAs($this->admin)
            ->postJson(route('admin.kegiatan.venue-config.pratinjau', $kegiatan->id), $this->payload(['rows' => ['A'], 'cols_per_row' => 3]))
            ->assertOk()->json();

        $this->assertFalse($pratinjau['cukup']);
        $this->assertSame('Pemindahan ruangan dibatalkan: Kapasitas baru (3 kursi) tidak mencukupi untuk menampung 4 peserta yang sudah memiliki kursi.', $pratinjau['pesan_tolak']);

        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload(['rows' => ['A'], 'cols_per_row' => 3]))
            ->assertSessionHasErrors(['capacity' => $pratinjau['pesan_tolak']]);
    }

    public function test_vip_panitia_and_blocked_changes_sync_seats_without_reset(): void
    {
        Queue::fake();
        $kegiatan = $this->kegiatan();
        $peserta = $this->isiPeserta($kegiatan);

        $layout = ['rows' => ['A', 'B'], 'cols_per_row' => 4, 'vip_rows' => [], 'panitia_rows' => [], 'blocked_seats' => ['a04', 'B03']];
        $pratinjau = $this->actingAs($this->admin)->postJson(route('admin.kegiatan.venue-config.pratinjau', $kegiatan->id), $this->payload($layout))->json();
        $this->assertFalse($pratinjau['akan_reset']);
        $this->assertSame(6, $pratinjau['kapasitas']);

        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload($layout))->assertSessionHasNoErrors();

        $kegiatan->refresh();
        $status = $kegiatan->seats()->pluck('status', 'seat_code')->map->value->all();
        $this->assertSame('blocked', $status['A04']);
        $this->assertSame('blocked', $status['B03']);
        $this->assertSame('booked', $status['A01']);
        $this->assertSame(0, $kegiatan->seats()->where('tier', 'vip')->count());
        $this->assertSame(['A04', 'B03'], $kegiatan->seat_layout_config['blocked_seats']);
        $this->assertSame($peserta['budi']->seat_id, $peserta['budi']->fresh()->seat_id, 'Kursi peserta tetap.');
        $this->assertSame(0, $kegiatan->venue_change_count);
        Queue::assertNothingPushed();

        // Kursi yang sedang dipakai tidak bisa diblokir atau dijadikan panitia.
        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload([...$layout, 'blocked_seats' => ['A01']]))
            ->assertSessionHasErrors('layout');
        // Baris B (berisi kursi Sari) dijadikan panitia; kapasitas baris A tetap cukup (4 kursi untuk 4 peserta).
        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload([...$layout, 'blocked_seats' => [], 'panitia_rows' => ['B']]))
            ->assertSessionHasErrors(['layout' => 'Kursi B01, B02 sedang dipakai peserta sehingga tidak bisa dijadikan kursi panitia atau diblokir.']);
        $this->assertSame('blocked', $kegiatan->seats()->where('seat_code', 'A04')->value('status')->value);

        // Membuka blokir mengembalikan kursi menjadi tersedia.
        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload([...$layout, 'blocked_seats' => []]))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $kegiatan->seats()->where('status', SeatStatus::Blocked)->count());
        $this->assertArrayNotHasKey('blocked_seats', $kegiatan->fresh()->seat_layout_config);
    }

    public function test_new_layout_creates_blocked_seats_and_excludes_them_from_capacity(): void
    {
        $kegiatan = $this->kegiatan();

        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload([
            'rows' => ['AA', 'BB'], 'cols_per_row' => 3, 'panitia_rows' => ['BB'], 'blocked_seats' => ['AA02'],
        ]))->assertSessionHasNoErrors();

        $status = $kegiatan->seats()->pluck('status', 'seat_code')->map->value->all();
        $this->assertSame(['AA01' => 'available', 'AA02' => 'blocked', 'AA03' => 'available', 'BB01' => 'reserved_panitia', 'BB02' => 'reserved_panitia', 'BB03' => 'reserved_panitia'], collect($status)->sortKeys()->all());
        $this->assertSame(2, app(DenahKursiService::class)->kapasitas($kegiatan->fresh()->seat_layout_config));
    }

    public function test_schedule_save_keeps_layout_and_blocked_seats(): void
    {
        $kegiatan = $this->kegiatan();
        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload([
            'rows' => ['A', 'B'], 'cols_per_row' => 4, 'vip_rows' => ['A'], 'blocked_seats' => ['B04'],
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), [
            'layout_changed' => false, 'is_seat_booking_active' => false,
            'booking_opens_at' => '2026-11-02 08:00:00', 'booking_closes_at' => '2026-11-05 17:00:00',
        ])->assertSessionHasNoErrors();

        $kegiatan->refresh();
        $this->assertSame(Kegiatan::BOOKING_DIBEKUKAN, $kegiatan->statusBookingKursi());
        $this->assertSame('2026-11-02 01:00:00', $kegiatan->booking_opens_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(['B04'], $kegiatan->seat_layout_config['blocked_seats']);
        $this->assertSame('blocked', $kegiatan->seats()->where('seat_code', 'B04')->value('status')->value);
    }

    public function test_venue_move_saves_validated_facility_choice(): void
    {
        $kegiatan = $this->kegiatan();
        $fasilitas = ['lokasi_type' => 'unusa_campus', 'lokasi_kampus' => 'Kampus A (Wonokromo)', 'lokasi_gedung' => 'Gedung A', 'lokasi_ruangan' => 'Aula Utama Kampus A', 'lokasi_nama' => 'Gedung A - Kampus A (Wonokromo)', 'lokasi_detail' => 'Aula Utama'];

        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload(['rows' => ['A', 'B'], 'cols_per_row' => 4], [...$fasilitas, 'lokasi_ruangan' => 'GSG UNUSA']))
            ->assertSessionHasErrors('lokasi_ruangan');
        $this->assertSame('Hotel Lama', $kegiatan->fresh()->lokasi_nama);

        $this->actingAs($this->admin)->put(route('admin.kegiatan.venue-config', $kegiatan->id), $this->payload(['rows' => ['A', 'B'], 'cols_per_row' => 4], $fasilitas))
            ->assertSessionHasNoErrors();
        $kegiatan->refresh();
        $this->assertSame(['unusa_campus', 'Kampus A (Wonokromo)', 'Gedung A', 'Aula Utama Kampus A', 'Gedung A - Kampus A (Wonokromo)'], [
            $kegiatan->lokasi_type, $kegiatan->lokasi_kampus, $kegiatan->lokasi_gedung, $kegiatan->lokasi_ruangan, $kegiatan->lokasi_nama,
        ]);
    }

    public function test_edit_form_vip_change_no_longer_resets_seats(): void
    {
        Queue::fake();
        $kegiatan = $this->kegiatan();
        $peserta = $this->isiPeserta($kegiatan);

        $this->actingAs($this->admin)->post(route('admin.kegiatan.update', $kegiatan->id), [
            '_method' => 'PUT', 'is_seat_booking_active' => true,
            'seat_layout_config' => ['rows' => ['A', 'B'], 'cols_per_row' => 4, 'vip_rows' => ['B'], 'panitia_rows' => null],
        ])->assertSessionHasNoErrors();

        $this->assertSame($peserta['budi']->seat_id, $peserta['budi']->fresh()->seat_id);
        $this->assertSame(['B01', 'B02', 'B03', 'B04'], $kegiatan->seats()->where('tier', 'vip')->orderBy('seat_code')->pluck('seat_code')->all());
        $this->assertSame(0, $kegiatan->fresh()->venue_change_count);
        Queue::assertNothingPushed();
    }

    public function test_only_super_admin_can_preview(): void
    {
        $kegiatan = $this->kegiatan();

        $this->actingAs(User::factory()->verifikator()->create())
            ->postJson(route('admin.kegiatan.venue-config.pratinjau', $kegiatan->id), $this->payload(['rows' => ['A'], 'cols_per_row' => 3]))
            ->assertForbidden();
    }
}
