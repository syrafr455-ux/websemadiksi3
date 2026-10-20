<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Jobs\SendSeatReselectionNotificationJob;
use App\Mail\SeatReselectionMail;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\User;
use App\Services\Kegiatan\DenahKursiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Peta kursi peserta di Kegiatan/Show: pilih kursi saat checkout (diri sendiri & delegasi),
 * pilih ulang kursi dari tautan email, status berkala, dan penanda di Kegiatan Saya.
 */
class KursiPesertaTest extends TestCase
{
    use RefreshDatabase;

    protected Kegiatan $kegiatan;

    protected User $budi;

    protected User $rina;

    protected User $lain;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00', 'Asia/Jakarta'));

        $this->kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Kursi', 'kategori' => 'seminar', 'metode_pelaksanaan' => 'offline', 'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addWeek(), 'lokasi_type' => 'external', 'lokasi_nama' => 'Hotel',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf', 'biaya' => 0, 'kuota' => 100, 'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => true, 'booking_opens_at' => now()->subDay(), 'booking_closes_at' => now()->addDays(3),
        ]);
        $this->kegiatan->update(['seat_layout_config' => app(DenahKursiService::class)->buat($this->kegiatan, [
            'rows' => ['A', 'B'], 'cols_per_row' => 4, 'vip_rows' => ['A'], 'panitia_rows' => ['B'], 'blocked_seats' => ['A04'],
        ])]);
        // B: baris panitia; buka B01 & B02 sebagai kursi biasa untuk peserta.
        $this->kegiatan->seats()->whereIn('seat_code', ['B01', 'B02'])->update(['status' => SeatStatus::Available->value]);

        $this->budi = User::factory()->mahasiswaKipk()->create(['name' => 'Budi Pemesan', 'email' => 'budi@gmail.com']);
        $this->rina = User::factory()->userUmum()->create(['name' => 'Rina Delegasi', 'email' => 'rina@gmail.com']);
        $this->lain = User::factory()->userUmum()->create(['name' => 'Orang Lain Rahasia', 'email' => 'lain@gmail.com']);
    }

    protected function kursi(string $kode)
    {
        return $this->kegiatan->seats()->where('seat_code', $kode)->firstOrFail();
    }

    protected function pesan(string $kode, User $booker, ?User $peserta, string $nama, array $lain = []): KegiatanRegistration
    {
        $kursi = $kode ? $this->kursi($kode) : null;
        $kursi?->update(['status' => SeatStatus::Booked]);

        return KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id, 'booker_user_id' => $booker->id, 'participant_user_id' => $peserta?->id,
            'seat_id' => $kursi?->id, 'nomor_tiket' => KegiatanRegistration::generateTicketNumber($this->kegiatan->id, $kode ?: 'GEN'),
            'participant_name' => $nama, 'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered, ...$lain,
        ]);
    }

    protected function halaman(?User $user, array $query = [])
    {
        if (! $user) {
            // actingAs() bertahan antar-request; tamu = tanpa pengguna.
            $this->app['auth']->forgetGuards();
        }
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('kegiatan.detail', ['slugOrId' => $this->kegiatan->id, ...$query]));
    }

    protected function props($response): array
    {
        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $m);

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR)['props'];
    }

    public function test_seat_map_shows_status_per_seat_and_own_seats_without_leaking_other_holders(): void
    {
        $this->pesan('A01', $this->budi, $this->budi, 'Budi Pemesan');
        $this->pesan('A02', $this->budi, $this->rina, 'Rina Delegasi');
        $this->pesan('A03', $this->lain, $this->lain, 'Orang Lain Rahasia');

        $respons = $this->halaman($this->budi)->assertOk();
        $kursi = collect($this->props($respons)['kursi'])->keyBy('kode');

        $this->assertSame(['milik', 'Budi Pemesan'], [$kursi['A01']['status'], $kursi['A01']['pemegang']]);
        $this->assertSame(['milik', 'Rina Delegasi'], [$kursi['A02']['status'], $kursi['A02']['pemegang']], 'Kursi delegasi ditandai milik pemesan.');
        $this->assertSame(['terisi', null], [$kursi['A03']['status'], $kursi['A03']['pemegang']]);
        $this->assertSame('diblokir', $kursi['A04']['status']);
        $this->assertSame('tersedia', $kursi['B01']['status']);
        $this->assertSame('panitia', $kursi['B03']['status']);
        $this->assertSame('vip', $kursi['A01']['tier']);
        $this->assertStringNotContainsString('Orang Lain Rahasia', $respons->getContent());

        // Delegasi melihat kursinya sendiri sebagai milik; kursi pemesannya tidak.
        $kursiRina = collect($this->props($this->halaman($this->rina))['kursi'])->keyBy('kode');
        $this->assertSame('milik', $kursiRina['A02']['status']);
        $this->assertSame('terisi', $kursiRina['A01']['status']);

        // Tamu: tidak ada kursi milik, tidak ada nama.
        $tamu = $this->halaman(null)->assertOk();
        $this->assertSame([], collect($this->props($tamu)['kursi'])->where('status', 'milik')->all());
        $this->assertStringNotContainsString('Budi Pemesan', $tamu->getContent());
    }

    public function test_status_endpoint_returns_only_ids_statuses_and_booking_window(): void
    {
        $this->pesan('A01', $this->budi, $this->budi, 'Budi Pemesan');
        $this->pesan('A03', $this->lain, $this->lain, 'Orang Lain Rahasia');

        $json = $this->actingAs($this->budi)->getJson(route('kegiatan.status-kursi', $this->kegiatan->id))->assertOk()->json();

        $this->assertSame(['memakai_denah' => true, 'status' => 'dibuka', 'bisa_pilih' => true], array_intersect_key($json['booking'], array_flip(['memakai_denah', 'status', 'bisa_pilih'])));
        $perKursi = collect($json['kursi'])->keyBy('id');
        $this->assertSame(['id' => $this->kursi('A01')->id, 'status' => 'milik', 'pemegang' => 'Budi Pemesan'], $perKursi[$this->kursi('A01')->id]);
        $this->assertSame(['id' => $this->kursi('A03')->id, 'status' => 'terisi'], $perKursi[$this->kursi('A03')->id]);
        foreach ($json['kursi'] as $k) {
            $this->assertEmpty(array_diff(array_keys($k), ['id', 'status', 'pemegang']));
        }
        $this->assertStringNotContainsString('Orang Lain Rahasia', json_encode($json));

        // Jendela booking dihormati: sebelum dibuka, peta tetap ada tapi tidak bisa dipilih.
        $this->kegiatan->update(['booking_opens_at' => now()->addHour()]);
        $json = $this->getJson(route('kegiatan.status-kursi', $this->kegiatan->id))->assertOk()->json();
        $this->assertSame(['belum_dibuka', false], [$json['booking']['status'], $json['booking']['bisa_pilih']]);
        $this->assertNotEmpty($json['kursi']);
    }

    public function test_checkout_with_seats_for_self_and_delegate(): void
    {
        $this->actingAs($this->budi)->post(route('kegiatan.checkout', $this->kegiatan->id), ['delegates' => [
            ['name' => 'Budi Pemesan', 'email' => 'budi@gmail.com', 'seat_id' => $this->kursi('A01')->id],
            ['name' => 'Rina Delegasi', 'email' => 'rina@gmail.com', 'seat_id' => $this->kursi('B01')->id],
        ]])->assertRedirect(route('dashboard.kegiatan.saya'))->assertSessionHasNoErrors();

        $this->assertSame(SeatStatus::Booked, $this->kursi('A01')->status);
        $this->assertSame(SeatStatus::Booked, $this->kursi('B01')->status);
        $rina = KegiatanRegistration::where('seat_id', $this->kursi('B01')->id)->firstOrFail();
        $this->assertSame([$this->budi->id, $this->rina->id], [$rina->booker_user_id, $rina->participant_user_id]);
    }

    public function test_taken_seat_is_rejected_per_participant_and_other_choices_stay_untouched(): void
    {
        $this->pesan('B01', $this->lain, $this->lain, 'Orang Lain Rahasia');

        $respons = $this->actingAs($this->budi)->post(route('kegiatan.checkout', $this->kegiatan->id), ['delegates' => [
            ['name' => 'Budi Pemesan', 'email' => 'budi@gmail.com', 'seat_id' => $this->kursi('A01')->id],
            ['name' => 'Rina Delegasi', 'email' => 'rina@gmail.com', 'seat_id' => $this->kursi('B01')->id],
        ]]);

        $respons->assertSessionHasErrors(['delegates.1.seat_id' => 'Kursi B01 baru saja diambil atau tidak tersedia. Silakan pilih kursi lain.']);
        $respons->assertSessionDoesntHaveErrors('delegates.0.seat_id');
        $this->assertSame(SeatStatus::Available, $this->kursi('A01')->status, 'Seluruh pemesanan dibatalkan; kursi A01 tetap tersedia untuk dipilih lagi.');
        $this->assertSame(0, KegiatanRegistration::where('booker_user_id', $this->budi->id)->count());
    }

    public function test_seat_event_requires_a_distinct_seat_per_participant(): void
    {
        $this->actingAs($this->budi)->post(route('kegiatan.checkout', $this->kegiatan->id), ['delegates' => [
            ['name' => 'Budi Pemesan', 'email' => 'budi@gmail.com', 'seat_id' => $this->kursi('A01')->id],
            ['name' => 'Rina Delegasi', 'email' => 'rina@gmail.com'],
        ]])->assertSessionHasErrors(['delegates.1.seat_id' => 'Pilih kursi untuk peserta ke-2.']);

        $this->actingAs($this->budi)->post(route('kegiatan.checkout', $this->kegiatan->id), ['delegates' => [
            ['name' => 'Budi Pemesan', 'email' => 'budi@gmail.com', 'seat_id' => $this->kursi('A01')->id],
            ['name' => 'Rina Delegasi', 'email' => 'rina@gmail.com', 'seat_id' => $this->kursi('A01')->id],
        ]])->assertSessionHasErrors(['delegates.1.seat_id' => 'Satu kursi hanya untuk satu peserta.']);

        $this->assertSame(SeatStatus::Available, $this->kursi('A01')->status);
    }

    public function test_reselection_link_from_email_opens_the_flow_and_booker_reselects_for_delegate(): void
    {
        $milikBudi = $this->pesan('', $this->budi, $this->budi, 'Budi Pemesan', ['needs_seat_reselection' => true, 'delegation_data' => ['is_booker' => true]]);
        $milikRina = $this->pesan('', $this->budi, $this->rina, 'Rina Delegasi', ['needs_seat_reselection' => true, 'delegation_data' => ['email' => 'rina@gmail.com']]);

        // Email ke pemesan memuat tautan dengan penanda pilih_ulang; email ke delegasi tanpa tautan.
        (new SendSeatReselectionNotificationJob($this->kegiatan))->handle();
        $url = null;
        Mail::assertSent(SeatReselectionMail::class, function (SeatReselectionMail $mail) use (&$url) {
            if ($mail->hasTo('budi@gmail.com')) {
                $url = $mail->reselectionUrl;
            }

            return true;
        });
        $this->assertSame(route('kegiatan.detail', ['slugOrId' => $this->kegiatan->id, 'pilih_ulang' => 1]), $url);
        $this->assertStringContainsString(e($url), (new SeatReselectionMail($this->kegiatan, 'Budi', null, $url, true))->render());

        // Tamu diarahkan masuk lalu kembali ke tautan yang sama.
        $this->get($url)->assertRedirect(route('login'));
        $this->assertSame($url, session('url.intended'));

        // Pemesan membuka tautan: alur pilih ulang terbuka dengan daftar registrasinya.
        $props = $this->props($this->actingAs($this->budi)->get($url)->assertOk());
        $this->assertTrue($props['bukaPilihUlang']);
        $daftar = collect($props['registrasiSaya'])->keyBy('id');
        $this->assertTrue($daftar[$milikRina->id]['perlu_pilih_ulang']);
        $this->assertTrue($daftar[$milikRina->id]['saya_pemesan']);

        // Delegasi melihat tiketnya menunggu pemesan, tanpa hak memilih.
        $propsRina = collect($this->props($this->halaman($this->rina))['registrasiSaya'])->keyBy('id');
        $this->assertFalse($propsRina[$milikRina->id]['saya_pemesan']);
        $this->actingAs($this->rina)->post(route('kegiatan.reselect-seat', $this->kegiatan->id), ['registration_id' => $milikRina->id, 'seat_id' => $this->kursi('B02')->id])
            ->assertSessionHas('error');

        // Pemesan memilih ulang untuk delegasi -> masih ada 1 lagi -> kembali ke alur pilih ulang.
        $this->actingAs($this->budi)->post(route('kegiatan.reselect-seat', $this->kegiatan->id), ['registration_id' => $milikRina->id, 'seat_id' => $this->kursi('B02')->id])
            ->assertRedirect($url)->assertSessionHas('success');
        $this->assertFalse($milikRina->fresh()->needs_seat_reselection);
        $this->assertSame($this->kursi('B02')->id, $milikRina->fresh()->seat_id);

        // Kursi yang sudah diambil ditolak dengan pesan jelas; registrasi tetap menunggu.
        $this->actingAs($this->budi)->post(route('kegiatan.reselect-seat', $this->kegiatan->id), ['registration_id' => $milikBudi->id, 'seat_id' => $this->kursi('B02')->id])
            ->assertSessionHasErrors(['seat_id' => 'Kursi B02 tidak tersedia atau baru saja diambil. Silakan pilih kursi lain.']);
        $this->assertTrue($milikBudi->fresh()->needs_seat_reselection);

        // Registrasi terakhir -> selesai, ke Kegiatan Saya.
        $this->actingAs($this->budi)->post(route('kegiatan.reselect-seat', $this->kegiatan->id), ['registration_id' => $milikBudi->id, 'seat_id' => $this->kursi('A02')->id])
            ->assertRedirect(route('dashboard.kegiatan.saya'));
        $this->assertFalse($milikBudi->fresh()->needs_seat_reselection);

        // Registrasi yang tidak sedang menunggu kursi baru tidak bisa pindah kursi lewat jalur ini.
        $this->actingAs($this->budi)->post(route('kegiatan.reselect-seat', $this->kegiatan->id), ['registration_id' => $milikBudi->id, 'seat_id' => $this->kursi('A03')->id])
            ->assertSessionHas('error', 'Registrasi ini tidak sedang menunggu pemilihan ulang kursi.');
        $this->assertSame($this->kursi('A02')->id, $milikBudi->fresh()->seat_id);
    }

    public function test_reselection_is_rejected_outside_the_booking_window(): void
    {
        $reg = $this->pesan('', $this->budi, $this->budi, 'Budi Pemesan', ['needs_seat_reselection' => true]);
        $this->kegiatan->update(['is_seat_booking_active' => false]);

        $this->actingAs($this->budi)->post(route('kegiatan.reselect-seat', $this->kegiatan->id), ['registration_id' => $reg->id, 'seat_id' => $this->kursi('B01')->id])
            ->assertSessionHas('error');
        $this->assertTrue($reg->fresh()->needs_seat_reselection);
        $this->assertSame('dibekukan', $this->props($this->halaman($this->budi))['booking']['status']);
    }

    public function test_kegiatan_saya_shows_seat_and_reselection_marker_with_button_for_booker_only(): void
    {
        $this->pesan('A01', $this->budi, $this->budi, 'Budi Pemesan');
        $menunggu = $this->pesan('', $this->budi, $this->rina, 'Rina Delegasi', ['needs_seat_reselection' => true]);

        $props = fn (User $u) => collect($this->props($this->actingAs($u)->get(route('dashboard.kegiatan.saya'))->assertOk())['enterpriseRegistrations'])->keyBy('id');

        $budi = $props($this->budi);
        $this->assertSame(['free', false], [$budi->firstWhere('participant_name', 'Budi Pemesan')['payment_status'], $budi->firstWhere('participant_name', 'Budi Pemesan')['perlu_pilih_ulang']]);
        $this->assertSame('A01', $budi->firstWhere('participant_name', 'Budi Pemesan')['seat']['seat_code']);
        $this->assertSame([true, true], [$budi[$menunggu->id]['perlu_pilih_ulang'], $budi[$menunggu->id]['bisa_pilih_ulang']]);

        $rina = $props($this->rina);
        $this->assertSame([true, false], [$rina[$menunggu->id]['perlu_pilih_ulang'], $rina[$menunggu->id]['bisa_pilih_ulang']]);
    }
}
