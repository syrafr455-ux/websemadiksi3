<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Events\KegiatanUpdatedEvent;
use App\Jobs\SendSeatReselectionNotificationJob;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\User;
use App\Services\Kegiatan\DenahKursiService;
use App\Support\Wib;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Modul kegiatan admin: halaman detail (Admin/Kegiatan/Show), satu jalur denah kursi untuk form edit
 * dan venue-config, pilihan fasilitas UNUSA yang tersimpan, serta narasumber yang bisa dikosongkan.
 */
class KegiatanModulAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Event::fake([KegiatanUpdatedEvent::class]);
        $this->admin = User::factory()->superAdmin()->create();
    }

    /** Kegiatan dengan denah 2 baris x 5 kursi (A VIP, B reguler). */
    protected function kegiatanBerdenah(array $atribut = []): Kegiatan
    {
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Denah',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addWeek(),
            'lokasi_type' => 'unusa_campus',
            'lokasi_kampus' => 'Kampus B (Jemursari)',
            'lokasi_gedung' => 'Tower UNUSA',
            'lokasi_ruangan' => 'Auditorium Lt. 9',
            'lokasi_nama' => 'Tower UNUSA - Kampus B (Jemursari)',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => true,
            ...$atribut,
        ]);
        $kegiatan->update(['seat_layout_config' => app(DenahKursiService::class)->buat($kegiatan, [
            'rows' => ['A', 'B'], 'cols_per_row' => 5, 'vip_rows' => ['A'], 'panitia_rows' => [],
        ])]);

        return $kegiatan->fresh();
    }

    /** Pesan kursi untuk peserta (booker sendiri atau delegasi). */
    protected function pesanKursi(Kegiatan $kegiatan, string $kode, User $booker, ?User $peserta = null, array $atribut = []): KegiatanRegistration
    {
        $kursi = $kegiatan->seats()->where('seat_code', $kode)->firstOrFail();
        $kursi->update(['status' => SeatStatus::Booked]);
        $peserta ??= $booker;

        return KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $booker->id,
            'participant_user_id' => $peserta->id,
            'seat_id' => $kursi->id,
            'nomor_tiket' => KegiatanRegistration::generateTicketNumber($kegiatan->id, $kode),
            'participant_name' => $peserta->name,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
            ...$atribut,
        ]);
    }

    /** Pesan error pertama di session (errors tersimpan sebagai array karena serialisasi session json). */
    protected function pesanError(string $key): ?string
    {
        $errors = session('errors');

        return is_array($errors)
            ? ($errors['default'][$key][0] ?? $errors[$key][0] ?? null)
            : $errors?->first($key);
    }

    protected function props($response): array
    {
        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $m);

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** Form edit seperti yang dikirim halaman React (lihat kegiatanKeForm + kegiatanKePayload). */
    protected function formEdit(Kegiatan $kegiatan, array $ubah = []): array
    {
        return array_replace([
            '_method' => 'PUT',
            'nama_kegiatan' => $kegiatan->nama_kegiatan,
            'kategori' => $kegiatan->kategori,
            'lokasi_type' => $kegiatan->lokasi_type,
            'lokasi_kampus' => $kegiatan->lokasi_kampus ?: 'Kampus B (Jemursari)',
            'lokasi_gedung' => $kegiatan->lokasi_gedung ?: 'Tower UNUSA',
            'lokasi_ruangan' => $kegiatan->lokasi_ruangan ?: 'Auditorium Lt. 9',
            'lokasi_nama' => $kegiatan->lokasi_nama,
            'tanggal_mulai' => Wib::dari($kegiatan->tanggal_mulai)->format('Y-m-d\TH:i:s'),
            'biaya' => $kegiatan->biaya,
            'kuota' => $kegiatan->kuota,
            'is_seat_booking_active' => true,
            'seat_layout_config' => $kegiatan->seat_layout_config,
        ], $ubah);
    }

    public function test_admin_show_lists_registrants_with_booker_and_reselection_flag(): void
    {
        $kegiatan = $this->kegiatanBerdenah();
        $booker = User::factory()->mahasiswaKipk()->create(['name' => 'Budi Pemesan']);
        $rekan = User::factory()->userUmum()->create(['name' => 'Rina Rekan']);
        $sendiri = $this->pesanKursi($kegiatan, 'A01', $booker);
        $delegasi = $this->pesanKursi($kegiatan, 'A02', $booker, $rekan, ['status_kehadiran' => AttendanceStatus::CheckedIn]);
        $pilihUlang = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id, 'booker_user_id' => $booker->id,
            'nomor_tiket' => 'TKT-PILIH-ULANG', 'participant_name' => 'Tamu Tanpa Akun',
            'status_pembayaran' => RegistrationPaymentStatus::Pending, 'needs_seat_reselection' => true,
        ]);

        $page = $this->props($this->actingAs($this->admin)->get(route('admin.kegiatan.show', $kegiatan->id))->assertOk());

        $this->assertSame('Admin/Kegiatan/Show', $page['component']);
        $this->assertSame('Tower UNUSA', $page['props']['kegiatan']['lokasi_gedung']);
        $this->assertSame(3, $page['props']['ringkasan']['pendaftar_aktif']);
        $this->assertSame(1, $page['props']['ringkasan']['perlu_pilih_ulang']);
        $this->assertSame(10, $page['props']['ringkasan']['kursi_total']);
        $this->assertSame(2, $page['props']['ringkasan']['kursi_terisi']);

        $baris = collect($page['props']['pendaftar'])->keyBy('id');
        // Yang perlu pilih ulang kursi tampil paling atas.
        $this->assertSame($pilihUlang->id, $page['props']['pendaftar'][0]['id']);
        $this->assertTrue($baris[$pilihUlang->id]['needs_seat_reselection']);
        $this->assertNull($baris[$pilihUlang->id]['kursi']);
        $this->assertSame('Menunggu Pembayaran', $baris[$pilihUlang->id]['status_pembayaran_label']);

        $this->assertNull($baris[$sendiri->id]['booker'], 'Pesan sendiri tidak menampilkan booker.');
        $this->assertSame('A01', $baris[$sendiri->id]['kursi']);
        $this->assertSame('Rina Rekan', $baris[$delegasi->id]['nama_peserta']);
        $this->assertSame('Budi Pemesan', $baris[$delegasi->id]['booker']['name']);
        $this->assertSame('checked_in', $baris[$delegasi->id]['status_kehadiran']);
        $this->assertSame($delegasi->nomor_tiket, $baris[$delegasi->id]['nomor_tiket']);
    }

    public function test_admin_show_is_only_for_super_admin(): void
    {
        $kegiatan = $this->kegiatanBerdenah();

        $this->actingAs(User::factory()->verifikator()->create())->get(route('admin.kegiatan.show', $kegiatan->id))->assertRedirect();
        $this->actingAs($this->admin)->get(route('admin.kegiatan.show', 99999))->assertNotFound();
    }

    public function test_edit_form_rejects_layout_smaller_than_booked_seats_with_venue_config_message(): void
    {
        Queue::fake();
        $kegiatan = $this->kegiatanBerdenah();
        $peserta = User::factory()->count(3)->mahasiswaKipk()->create();
        foreach (['A01', 'A02', 'B01'] as $i => $kode) {
            $this->pesanKursi($kegiatan, $kode, $peserta[$i]);
        }

        // Denah baru 1 baris x 2 kursi = 2 kursi < 3 kursi terisi.
        $this->actingAs($this->admin)
            ->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
                'seat_layout_config' => ['rows' => ['A'], 'cols_per_row' => 2],
            ]))
            ->assertSessionHasErrors('capacity');
        $pesanForm = $this->pesanError('capacity');

        // Endpoint venue-config memberi pesan yang sama persis.
        $this->actingAs($this->admin)
            ->put(route('admin.kegiatan.venue-config', $kegiatan->id), [
                'total_seats' => 2, 'layout_changed' => true, 'is_seat_booking_active' => true,
                'layout_config' => ['rows' => ['A'], 'cols_per_row' => 2],
            ])
            ->assertSessionHasErrors('capacity');
        $this->assertSame($this->pesanError('capacity'), $pesanForm);
        $this->assertStringContainsString('Kapasitas baru (2 kursi) tidak mencukupi untuk menampung 3 peserta', $pesanForm);

        $kegiatan->refresh();
        $this->assertSame(10, $kegiatan->seats()->count());
        $this->assertSame(0, $kegiatan->venue_change_count);
        $this->assertSame(5, $kegiatan->seat_layout_config['cols_per_row']);
        $this->assertSame(0, KegiatanRegistration::where('needs_seat_reselection', true)->count());
        Queue::assertNothingPushed();
    }

    public function test_rejected_layout_change_does_not_store_the_uploaded_pdf(): void
    {
        $kegiatan = $this->kegiatanBerdenah();
        $peserta = User::factory()->count(3)->mahasiswaKipk()->create();
        foreach (['A01', 'A02', 'B01'] as $i => $kode) {
            $this->pesanKursi($kegiatan, $kode, $peserta[$i]);
        }

        $this->actingAs($this->admin)
            ->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
                'seat_layout_config' => ['rows' => ['A'], 'cols_per_row' => 2],
                'panduan_file' => UploadedFile::fake()->create('panduan-baru.pdf', 80, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('capacity');

        $this->assertSame([], Storage::disk('public')->allFiles('kegiatan/panduan'));
        $this->assertSame('kegiatan/panduan.pdf', $kegiatan->fresh()->file_panduan_pdf);
    }

    public function test_panitia_rows_do_not_count_as_capacity_in_edit_form(): void
    {
        $kegiatan = $this->kegiatanBerdenah();
        $peserta = User::factory()->count(3)->mahasiswaKipk()->create();
        foreach (['A01', 'A02', 'B01'] as $i => $kode) {
            $this->pesanKursi($kegiatan, $kode, $peserta[$i]);
        }

        // 2 baris x 2 kursi, tetapi baris B khusus panitia: hanya 2 kursi untuk peserta.
        $this->actingAs($this->admin)
            ->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
                'seat_layout_config' => ['rows' => ['A', 'B'], 'cols_per_row' => 2, 'panitia_rows' => ['B']],
            ]))
            ->assertSessionHasErrors('capacity');
    }

    public function test_valid_new_layout_from_edit_form_runs_the_same_reset_flow_as_venue_config(): void
    {
        Queue::fake();
        $kegiatan = $this->kegiatanBerdenah();
        $booker = User::factory()->mahasiswaKipk()->create();
        $reg = $this->pesanKursi($kegiatan, 'B02', $booker);
        $kadaluarsa = $this->pesanKursi($kegiatan, 'B03', User::factory()->userUmum()->create(), null, [
            'status_pembayaran' => RegistrationPaymentStatus::Expired,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
                'lokasi_nama' => 'Gedung Serba Guna - Kampus B (Jemursari)',
                'seat_layout_config' => ['rows' => ['A', 'B', 'C'], 'cols_per_row' => 4, 'vip_rows' => null, 'panitia_rows' => null],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $kegiatan->refresh();
        $reg->refresh();
        $this->assertSame(12, $kegiatan->seats()->count());
        $this->assertSame(0, $kegiatan->seats()->where('tier', 'vip')->count(), 'vip_rows null = tanpa baris VIP.');
        $this->assertSame(1, $kegiatan->venue_change_count);
        $this->assertSamaTanpaUrutanKunci(['rows' => ['A', 'B', 'C'], 'cols_per_row' => 4, 'vip_rows' => [], 'panitia_rows' => []], $kegiatan->seat_layout_config);

        $this->assertNull($reg->seat_id);
        $this->assertTrue($reg->needs_seat_reselection);
        $this->assertSame('Tower UNUSA - Kampus B (Jemursari)', $reg->seat_history[0]['venue'], 'Riwayat memakai ruangan lama.');
        $this->assertSame('B02', $reg->seat_history[0]['seat']);
        $this->assertFalse($kadaluarsa->fresh()->needs_seat_reselection, 'Registrasi kedaluwarsa tidak ikut direset.');

        Queue::assertPushed(SendSeatReselectionNotificationJob::class, fn ($job) => $job->kegiatan->is($kegiatan));
    }

    public function test_reselection_job_from_edit_form_waits_for_commit(): void
    {
        $kegiatan = $this->kegiatanBerdenah();
        $this->pesanKursi($kegiatan, 'A01', User::factory()->mahasiswaKipk()->create());
        Queue::fake();

        // Email pilih ulang baru dikirim setelah transaksi tersimpan (sama dengan venue-config).
        $this->actingAs($this->admin)->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
            'seat_layout_config' => ['rows' => ['A', 'B', 'C'], 'cols_per_row' => 5],
        ]))->assertSessionHas('success');

        Queue::assertPushed(SendSeatReselectionNotificationJob::class, fn ($job) => $job->afterCommit === true);
    }

    public function test_facility_choice_is_stored_and_returned_intact(): void
    {
        $this->actingAs($this->admin)->post(route('admin.kegiatan.store'), [
            'nama_kegiatan' => 'Workshop Fasilitas',
            'kategori' => 'workshop',
            'tanggal_mulai' => '2026-12-01T09:00',
            'lokasi_type' => 'unusa_campus',
            'lokasi_kampus' => 'Kampus A (Wonokromo)',
            'lokasi_gedung' => 'Gedung A',
            'lokasi_ruangan' => 'Ruang Rapat Utama',
            'lokasi_nama' => 'Gedung A - Kampus A (Wonokromo)',
            'biaya' => 0,
            'kuota' => 40,
            'is_seat_booking_active' => false,
            'panduan_file' => UploadedFile::fake()->create('panduan.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $kegiatan = Kegiatan::where('nama_kegiatan', 'Workshop Fasilitas')->firstOrFail();

        // Props daftar kegiatan (sumber form edit) membawa pilihan yang tersimpan.
        $item = collect($this->props($this->actingAs($this->admin)->get(route('admin.kegiatan')))['props']['kegiatans']['data'])
            ->firstWhere('id', $kegiatan->id);
        $this->assertSame(['Kampus A (Wonokromo)', 'Gedung A', 'Ruang Rapat Utama'], [$item['lokasi_kampus'], $item['lokasi_gedung'], $item['lokasi_ruangan']]);

        // Simpan ulang tanpa perubahan: pilihan tetap; ganti ruangan: tersimpan.
        $this->actingAs($this->admin)->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, ['is_seat_booking_active' => false]))
            ->assertSessionHasNoErrors();
        $this->assertSame('Ruang Rapat Utama', $kegiatan->fresh()->lokasi_ruangan);

        $this->actingAs($this->admin)->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
            'is_seat_booking_active' => false, 'lokasi_ruangan' => 'Aula Utama Kampus A',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Aula Utama Kampus A', $kegiatan->fresh()->lokasi_ruangan);

        // Pilihan yang dikirim kosong ditolak (tidak diam-diam memakai nilai lama).
        $this->actingAs($this->admin)->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
            'is_seat_booking_active' => false, 'lokasi_kampus' => '', 'lokasi_nama' => '-',
        ]))->assertSessionHasErrors('lokasi_gedung');
        $this->assertSame('Gedung A - Kampus A (Wonokromo)', $kegiatan->fresh()->lokasi_nama);

        // Kombinasi yang tidak ada di daftar fasilitas ditolak.
        $this->actingAs($this->admin)->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
            'is_seat_booking_active' => false, 'lokasi_gedung' => 'Tower UNUSA',
        ]))->assertSessionHasErrors('lokasi_ruangan');
        $this->assertSame('Gedung A', $kegiatan->fresh()->lokasi_gedung);

        // Pindah ke venue eksternal: pilihan kampus dikosongkan.
        $this->actingAs($this->admin)->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
            'is_seat_booking_active' => false, 'lokasi_type' => 'external', 'lokasi_nama' => 'Hotel Majapahit',
        ]))->assertSessionHasNoErrors();
        $kegiatan->refresh();
        $this->assertSame('external', $kegiatan->lokasi_type);
        $this->assertNull($kegiatan->lokasi_kampus);
        $this->assertNull($kegiatan->lokasi_gedung);
        $this->assertNull($kegiatan->lokasi_ruangan);
    }

    public function test_store_requires_complete_facility_choice_for_campus_location(): void
    {
        $this->actingAs($this->admin)->post(route('admin.kegiatan.store'), [
            'nama_kegiatan' => 'Tanpa Ruangan',
            'kategori' => 'seminar',
            'tanggal_mulai' => '2026-12-01T09:00',
            'lokasi_type' => 'unusa_campus',
            'lokasi_kampus' => 'Kampus B (Jemursari)',
            'lokasi_nama' => 'Kampus B',
            'biaya' => 0,
            'kuota' => 10,
        ])->assertSessionHasErrors('lokasi_gedung');

        $this->assertSame(0, Kegiatan::count());
    }

    public function test_all_narasumber_can_be_removed_while_uploading_a_pdf(): void
    {
        $kegiatan = $this->kegiatanBerdenah(['narasumber' => [['nama' => 'Siti Aminah', 'gelar' => 'Dr.']]]);

        // Seperti FormData dari browser: array kosong dikirim sebagai string kosong bersama berkas PDF.
        $this->actingAs($this->admin)->post(route('admin.kegiatan.update', $kegiatan->id), $this->formEdit($kegiatan, [
            'narasumber' => '',
            'panduan_file' => UploadedFile::fake()->create('panduan-baru.pdf', 80, 'application/pdf'),
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $kegiatan->refresh();
        $this->assertNull($kegiatan->narasumber);
        $this->assertStringStartsWith('kegiatan/panduan/', $kegiatan->file_panduan_pdf);
        Storage::disk('public')->assertExists($kegiatan->file_panduan_pdf);
    }

    public function test_omitting_narasumber_keeps_it_unchanged(): void
    {
        $kegiatan = $this->kegiatanBerdenah(['narasumber' => [['nama' => 'Siti Aminah']]]);
        $form = $this->formEdit($kegiatan);
        unset($form['narasumber']);

        $this->actingAs($this->admin)->post(route('admin.kegiatan.update', $kegiatan->id), $form)->assertSessionHasNoErrors();

        $this->assertSame([['nama' => 'Siti Aminah']], $kegiatan->fresh()->narasumber);
    }
}
