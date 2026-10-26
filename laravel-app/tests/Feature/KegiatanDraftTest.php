<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KegiatanDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function user(UserRole $role = UserRole::MahasiswaKipk): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_blocked' => false,
            'email_verified_at' => now(),
        ]);
    }

    protected function createKegiatan(KegiatanStatus $status, bool $withSeat = true): Kegiatan
    {
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Kegiatan Status '.$status->value,
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addWeek(),
            'tanggal_selesai' => now()->addWeek()->addHours(3),
            'registration_deadline' => now()->addDays(3),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium',
            'file_panduan_pdf' => 'panduan/uji.pdf',
            'biaya' => 0,
            'kuota' => 50,
            'is_seat_booking_active' => $withSeat,
            'booking_opens_at' => now()->subDay(),
            'booking_closes_at' => now()->addDays(3),
            'status' => $status,
        ]);

        if ($withSeat) {
            KegiatanSeat::create([
                'kegiatan_id' => $kegiatan->id,
                'seat_code' => 'A-1',
                'row_label' => 'A',
                'seat_number' => 1,
                'tier' => 'reguler',
                'status' => SeatStatus::Available,
            ]);
        }

        return $kegiatan;
    }

    public function test_draft_kegiatan_returns_404_for_guest_and_participants(): void
    {
        $draft = $this->createKegiatan(KegiatanStatus::Draft);
        $peserta = $this->user(UserRole::MahasiswaKipk);

        // Guest: 404
        $this->get(route('kegiatan.detail', $draft->id))->assertNotFound();
        $this->get(route('kegiatan.status-kursi', $draft->id))->assertNotFound();

        // Peserta: 404
        $this->actingAs($peserta)->get(route('kegiatan.detail', $draft->id))->assertNotFound();
        $this->actingAs($peserta)->get(route('kegiatan.status-kursi', $draft->id))->assertNotFound();
    }

    public function test_cancelled_kegiatan_returns_404_for_guest_and_participants(): void
    {
        $cancelled = $this->createKegiatan(KegiatanStatus::Cancelled);
        $peserta = $this->user(UserRole::MahasiswaKipk);

        // Guest: 404
        $this->get(route('kegiatan.detail', $cancelled->id))->assertNotFound();
        $this->get(route('kegiatan.status-kursi', $cancelled->id))->assertNotFound();

        // Peserta: 404
        $this->actingAs($peserta)->get(route('kegiatan.detail', $cancelled->id))->assertNotFound();
        $this->actingAs($peserta)->get(route('kegiatan.status-kursi', $cancelled->id))->assertNotFound();
    }

    public function test_super_admin_can_preview_draft_kegiatan_with_banner(): void
    {
        $draft = $this->createKegiatan(KegiatanStatus::Draft);
        $admin = $this->user(UserRole::SuperAdmin);

        $response = $this->actingAs($admin)->get(route('kegiatan.detail', $draft->id))->assertOk();

        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $m);
        $page = json_decode($m[1], true);
        $this->assertSame('Kegiatan/Show', $page['component']);
        $this->assertTrue($page['props']['isDraftPreview']);
        $this->assertFalse($page['props']['pendaftaran']['dibuka']);
        $this->assertFalse($page['props']['booking']['bisa_pilih']);

        // status-kursi juga bisa dibuka oleh super admin
        $this->actingAs($admin)->get(route('kegiatan.status-kursi', $draft->id))->assertOk();
    }

    public function test_checkout_and_reselect_seat_on_draft_kegiatan_are_rejected(): void
    {
        $draft = $this->createKegiatan(KegiatanStatus::Draft);
        $seat = $draft->seats()->first();
        $peserta = $this->user(UserRole::MahasiswaKipk);

        // Checkout ditolak
        $response = $this->actingAs($peserta)->post(route('kegiatan.checkout', $draft->id), [
            'delegates' => [
                [
                    'name' => $peserta->name,
                    'email' => $peserta->email,
                    'seat_id' => $seat->id,
                ],
            ],
        ]);
        $response->assertSessionHas('error');
        $this->assertSame(0, KegiatanRegistration::where('kegiatan_id', $draft->id)->count());

        // Reselect seat ditolak
        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $draft->id,
            'booker_user_id' => $peserta->id,
            'participant_user_id' => $peserta->id,
            'nomor_tiket' => 'TIK-DRAFT-001',
            'participant_name' => $peserta->name,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'needs_seat_reselection' => true,
        ]);

        $response = $this->actingAs($peserta)->post(route('kegiatan.reselect-seat', $draft->id), [
            'registration_id' => $reg->id,
            'seat_id' => $seat->id,
        ]);
        $response->assertSessionHas('error');
    }

    public function test_landing_page_and_public_lists_do_not_show_draft_kegiatan(): void
    {
        $published = $this->createKegiatan(KegiatanStatus::Published);
        $draft = $this->createKegiatan(KegiatanStatus::Draft);

        $response = $this->get(route('home'))->assertOk();
        $response->assertSee($published->nama_kegiatan);
        $response->assertDontSee($draft->nama_kegiatan);

        // Uji legacy activity draft di home dan dashboard
        $actDraft = Activity::create([
            'title' => 'Legacy Draft Activity',
            'category' => 'Seminar',
            'type' => 'Online',
            'date_text' => '10 Nov 2026',
            'location' => 'Zoom',
            'price' => 'Gratis',
            'quota' => 50,
            'status' => 'draft',
            'is_closed' => false,
        ]);

        // Karena Kegiatan::exists(), home menampilkan published Kegiatan.
        $this->get(route('home'))->assertDontSee('Legacy Draft Activity');

        // Di dashboard kegiatan
        $user = $this->user();
        $dashResponse = $this->actingAs($user)->get(route('dashboard.kegiatan'))->assertOk();
        $dashResponse->assertDontSee('Legacy Draft Activity');
    }
}
