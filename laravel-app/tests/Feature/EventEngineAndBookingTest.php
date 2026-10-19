<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use App\Enums\UserRole;
use App\Events\KegiatanUpdatedEvent;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventEngineAndBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function getSuperAdmin(): User
    {
        return User::where('role', UserRole::SuperAdmin->value)->first()
            ?? User::factory()->create(['role' => UserRole::SuperAdmin]);
    }

    protected function getMahasiswaKipk(): User
    {
        return User::where('role', UserRole::MahasiswaKipk->value)->where('is_blocked', false)->first()
            ?? User::factory()->create(['role' => UserRole::MahasiswaKipk, 'is_blocked' => false]);
    }

    protected function getOrCreateWorkshopKegiatan(): Kegiatan
    {
        $kegiatan = Kegiatan::where('nama_kegiatan', 'Workshop AI & Next-Gen Cloud')->latest()->first();
        if ($kegiatan) {
            return $kegiatan;
        }

        $admin = $this->getSuperAdmin();
        $this->actingAs($admin)->post(route('admin.kegiatan.store'), [
            'nama_kegiatan' => 'Workshop AI & Next-Gen Cloud',
            'kategori' => 'Workshop',
            'tanggal_mulai' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_kampus' => 'Kampus B (Jemursari)',
            'lokasi_gedung' => 'Tower UNUSA',
            'lokasi_ruangan' => 'Auditorium Lt. 9',
            'lokasi_nama' => 'Tower UNUSA - Kampus B',
            'lokasi_detail' => 'Auditorium Lt. 9',
            'biaya' => 0,
            'kuota' => 50,
            'is_seat_booking_active' => true,
            'seat_layout_config' => [
                'rows' => ['A', 'B', 'C'],
                'cols_per_row' => 5,
                'vip_rows' => ['A'],
            ],
        ]);

        return Kegiatan::where('nama_kegiatan', 'Workshop AI & Next-Gen Cloud')->latest()->firstOrFail();
    }

    public function test_super_admin_can_access_kegiatan_index(): void
    {
        $admin = $this->getSuperAdmin();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get(route('admin.kegiatan'));
        $response->assertStatus(200);
        $response->assertSee('<script data-page="app" type="application/json">{"component":"Admin/Kegiatan/Index"', false);
    }

    public function test_store_kegiatan_creates_event_and_generates_seat_layout(): void
    {
        $admin = $this->getSuperAdmin();

        $response = $this->actingAs($admin)->post(route('admin.kegiatan.store'), [
            'nama_kegiatan' => 'Workshop AI & Next-Gen Cloud',
            'kategori' => 'Workshop',
            'tanggal_mulai' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_kampus' => 'Kampus B (Jemursari)',
            'lokasi_gedung' => 'Tower UNUSA',
            'lokasi_ruangan' => 'Auditorium Lt. 9',
            'lokasi_nama' => 'Tower UNUSA - Kampus B',
            'lokasi_detail' => 'Auditorium Lt. 9',
            'biaya' => 0,
            'kuota' => 50,
            'is_seat_booking_active' => true,
            'seat_layout_config' => [
                'rows' => ['A', 'B', 'C'],
                'cols_per_row' => 5,
                'vip_rows' => ['A'],
            ],
        ]);

        if (session('errors') || session('error')) {
            dump(session('errors')?->all(), session('error'));
        }
        $response->assertRedirect(route('admin.kegiatan'));

        $kegiatan = Kegiatan::where('nama_kegiatan', 'Workshop AI & Next-Gen Cloud')->first();
        $this->assertNotNull($kegiatan);
        $this->assertSame(15, $kegiatan->seats()->count());

        // Check VIP seat A01
        $seatA1 = $kegiatan->seats()->where('seat_code', 'A01')->first();
        $this->assertNotNull($seatA1);
        $this->assertSame(SeatTier::Vip, $seatA1->tier);
    }

    public function test_updating_kegiatan_triggers_event_on_schedule_change(): void
    {
        Event::fake([KegiatanUpdatedEvent::class]);

        $admin = $this->getSuperAdmin();
        $kegiatan = $this->getOrCreateWorkshopKegiatan();
        $this->assertNotNull($kegiatan);

        $newTime = now()->addDays(7)->format('Y-m-d H:i:s');

        $response = $this->actingAs($admin)->put(route('admin.kegiatan.update', $kegiatan->id), [
            'nama_kegiatan' => $kegiatan->nama_kegiatan,
            'kategori' => $kegiatan->kategori,
            'tanggal_mulai' => $newTime,
            'lokasi_type' => $kegiatan->lokasi_type,
            'lokasi_nama' => 'Tower UNUSA - Kampus B Baru',
            'biaya' => $kegiatan->biaya,
            'kuota' => $kegiatan->kuota,
            'status' => KegiatanStatus::Published->value,
            'is_seat_booking_active' => false,
        ]);

        $response->assertRedirect();
        Event::assertDispatched(KegiatanUpdatedEvent::class);
    }

    public function test_participant_can_checkout_multi_delegation_seats(): void
    {
        $student = $this->getMahasiswaKipk();
        $kegiatan = $this->getOrCreateWorkshopKegiatan();
        $this->assertNotNull($kegiatan);

        KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->where('participant_user_id', $student->id)
            ->delete();

        $seatB1 = $kegiatan->seats()->where('seat_code', 'B01')->first();
        $seatB2 = $kegiatan->seats()->where('seat_code', 'B02')->first();
        $this->assertNotNull($seatB1);
        $this->assertNotNull($seatB2);

        $seatB1->update(['status' => SeatStatus::Available]);
        $seatB2->update(['status' => SeatStatus::Available]);

        $response = $this->actingAs($student)->post(route('kegiatan.checkout', $kegiatan->id), [
            'delegates' => [
                [
                    'name' => $student->name,
                    'email' => $student->email,
                    'identity' => $student->nim,
                    'seat_id' => $seatB1->id,
                ],
                [
                    'name' => 'Rekan Delegasi Dua',
                    'email' => 'rekan@example.com',
                    'identity' => '2240099999',
                    'seat_id' => $seatB2->id,
                ],
            ],
            'payment_method' => 'QRIS',
        ]);

        $response->assertRedirect(route('dashboard.kegiatan.saya'));

        $seatB1->refresh();
        $seatB2->refresh();
        $this->assertSame(SeatStatus::Booked, $seatB1->status);
        $this->assertSame(SeatStatus::Booked, $seatB2->status);

        $reg1 = KegiatanRegistration::where('seat_id', $seatB1->id)->first();
        $this->assertNotNull($reg1);
        $this->assertStringStartsWith('TKT-', $reg1->nomor_tiket);
    }

    public function test_cannot_double_book_occupied_seat(): void
    {
        $student = $this->getMahasiswaKipk();
        $kegiatan = $this->getOrCreateWorkshopKegiatan();
        $this->assertNotNull($kegiatan);

        $seatB1 = $kegiatan->seats()->where('seat_code', 'B01')->first();
        $this->assertNotNull($seatB1);
        $seatB1->update(['status' => SeatStatus::Booked]);
        $this->assertSame(SeatStatus::Booked, $seatB1->status);

        // Try booking already booked seat
        $response = $this->actingAs($student)->post(route('kegiatan.checkout', $kegiatan->id), [
            'delegates' => [
                [
                    'name' => 'Peserta Lain',
                    'email' => 'other@example.com',
                    'seat_id' => $seatB1->id,
                ],
            ],
        ]);

        $response->assertSessionHas('error');
    }
}
