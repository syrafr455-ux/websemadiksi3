<?php

namespace Tests\Feature;

use App\Events\KegiatanUpdatedEvent;
use App\Models\Kegiatan;
use App\Models\User;
use App\Support\Wib;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Form edit kegiatan (Admin/Kegiatan/Index) tidak boleh merusak data:
 * membuka form lalu menyimpan tanpa perubahan = tidak ada kolom yang berubah.
 */
class KegiatanEditFormRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Event::fake([KegiatanUpdatedEvent::class]);
        $this->travelTo(Carbon::parse('2026-10-19 09:00:00', 'Asia/Jakarta'));
        $this->admin = User::factory()->superAdmin()->create();
    }

    /**
     * Kegiatan dengan semua field terisi, dibuat lewat form "Tambah Kegiatan".
     */
    protected function buatKegiatanLengkap(): Kegiatan
    {
        $this->actingAs($this->admin)->post(route('admin.kegiatan.store'), [
            'nama_kegiatan' => 'Lomba Inovasi Digital KIP-K',
            'deskripsi' => 'Kompetisi karya inovasi digital mahasiswa.',
            'kategori' => 'lomba',
            'sasaran_peserta' => 'internal_unusa',
            'metode_pelaksanaan' => 'hybrid',
            'tanggal_mulai' => '2026-11-10T08:00',
            'tanggal_selesai' => '2026-11-10T16:30',
            'registration_deadline' => '2026-11-05T23:59',
            'submission_deadline' => '2026-11-08T17:00:30',
            'lokasi_type' => 'unusa_campus',
            'lokasi_kampus' => 'Kampus B (Jemursari)',
            'lokasi_gedung' => 'Tower UNUSA',
            'lokasi_ruangan' => 'Auditorium Lt. 9',
            'lokasi_nama' => 'Tower UNUSA - Kampus B',
            'lokasi_detail' => 'Auditorium Lantai 9',
            'biaya' => 25000,
            'kuota' => 37,
            'poin_xp' => 15,
            'narasumber' => [
                ['nama' => 'Siti Aminah', 'gelar' => 'Dr.', 'instansi' => 'UNUSA'],
                ['nama' => 'Budi Hartono', 'gelar' => null, 'instansi' => 'Dinas Kominfo'],
            ],
            'is_seat_booking_active' => true,
            'seat_layout_config' => ['rows' => ['A', 'B', 'C', 'D'], 'cols_per_row' => 6, 'vip_rows' => ['A'], 'panitia_rows' => ['D']],
            'is_submission_active' => true,
            'submission_instructions' => 'Unggah PDF maksimal 20MB.',
            'status' => 'published',
            'panduan_file' => UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('admin.kegiatan'))->assertSessionHasNoErrors();

        return Kegiatan::where('nama_kegiatan', 'Lomba Inovasi Digital KIP-K')->firstOrFail();
    }

    /**
     * Data kegiatan persis seperti yang diterima halaman React (props Inertia di HTML awal).
     */
    protected function propsKegiatan(Kegiatan $kegiatan): array
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kegiatan'))->assertOk()->getContent();
        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $match);
        $page = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

        return collect($page['props']['kegiatans']['data'])->firstWhere('id', $kegiatan->id);
    }

    /** Port PHP dari keInputWib() di resources/js/lib/waktuWib.js. */
    protected function keInputWib(?string $iso): string
    {
        if (! $iso) {
            return '';
        }
        $wib = Wib::dari(Carbon::parse($iso));

        return $wib->format('Y-m-d\TH:i').($wib->second !== 0 ? $wib->format(':s') : '');
    }

    /** Port PHP dari kegiatanKeForm() di resources/js/lib/kegiatanForm.js (state form edit). */
    protected function formEdit(array $item): array
    {
        $layout = $item['seat_layout_config'] ?? [];

        return [
            'nama_kegiatan' => $item['nama_kegiatan'] ?? '',
            'deskripsi' => $item['deskripsi'] ?? '',
            'kategori' => $item['kategori'] ?? 'seminar',
            'sasaran_peserta' => $item['sasaran_peserta'] ?? 'umum',
            'metode_pelaksanaan' => $item['metode_pelaksanaan'] ?? 'offline',
            'tanggal_mulai' => $this->keInputWib($item['tanggal_mulai'] ?? null),
            'tanggal_selesai' => $this->keInputWib($item['tanggal_selesai'] ?? null),
            'registration_deadline' => $this->keInputWib($item['registration_deadline'] ?? null),
            'submission_deadline' => $this->keInputWib($item['submission_deadline'] ?? null),
            'lokasi_type' => $item['lokasi_type'] ?? 'unusa_campus',
            'lokasi_kampus' => 'Kampus B (Jemursari)',
            'lokasi_gedung' => 'Tower UNUSA',
            'lokasi_ruangan' => 'Auditorium Lt. 9',
            'lokasi_nama' => $item['lokasi_nama'] ?? '',
            'lokasi_detail' => $item['lokasi_detail'] ?? '',
            'biaya' => $item['biaya'] ?? 0,
            'kuota' => $item['kuota'] ?? 1,
            'poin_xp' => $item['poin_xp'] ?? 0,
            'narasumber' => is_array($item['narasumber'] ?? null) ? $item['narasumber'] : [],
            'is_seat_booking_active' => (bool) ($item['is_seat_booking_active'] ?? false),
            'is_submission_active' => (bool) ($item['is_submission_active'] ?? false),
            'submission_instructions' => $item['submission_instructions'] ?? '',
            'status' => $item['status'] ?? 'published',
            'seat_layout_config' => [
                'rows' => $layout['rows'] ?? ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
                'cols_per_row' => $layout['cols_per_row'] ?? 10,
                'vip_rows' => $layout['vip_rows'] ?? ['A', 'B'],
                'panitia_rows' => $layout['panitia_rows'] ?? [],
            ],
            'panduan_file' => null,
        ];
    }

    /** Kirim form edit seperti React: POST dengan _method=PUT. */
    protected function simpanForm(Kegiatan $kegiatan, array $data)
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.kegiatan.update', $kegiatan->id), ['_method' => 'PUT', ...$data])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    protected function barisKegiatan(Kegiatan $kegiatan): array
    {
        return (array) DB::table('kegiatan')->where('id', $kegiatan->id)->first();
    }

    protected function snapshotKursi(Kegiatan $kegiatan): array
    {
        return DB::table('kegiatan_seats')->where('kegiatan_id', $kegiatan->id)->orderBy('id')
            ->get(['id', 'seat_code', 'tier', 'status', 'updated_at'])
            ->map(fn ($seat) => (array) $seat)->all();
    }

    public function test_form_edit_props_contain_every_stored_value(): void
    {
        $kegiatan = $this->buatKegiatanLengkap();
        $form = $this->formEdit($this->propsKegiatan($kegiatan));

        $this->assertSame('lomba', $form['kategori']);
        $this->assertSame('internal_unusa', $form['sasaran_peserta']);
        $this->assertSame('hybrid', $form['metode_pelaksanaan']);
        $this->assertSame('2026-11-10T08:00', $form['tanggal_mulai']);
        $this->assertSame('2026-11-10T16:30', $form['tanggal_selesai']);
        $this->assertSame('2026-11-05T23:59', $form['registration_deadline']);
        $this->assertSame('2026-11-08T17:00:30', $form['submission_deadline']);
        $this->assertSame(37, $form['kuota']);
        $this->assertSame(15, $form['poin_xp']);
        $this->assertTrue($form['is_seat_booking_active']);
        $this->assertTrue($form['is_submission_active']);
        $this->assertSame('Unggah PDF maksimal 20MB.', $form['submission_instructions']);
        $this->assertSame(['Siti Aminah', 'Budi Hartono'], array_column($form['narasumber'], 'nama'));
        $this->assertSame(['rows' => ['A', 'B', 'C', 'D'], 'cols_per_row' => 6, 'vip_rows' => ['A'], 'panitia_rows' => ['D']], $form['seat_layout_config']);
    }

    public function test_saving_edit_form_without_changes_changes_nothing(): void
    {
        $kegiatan = $this->buatKegiatanLengkap();
        $sebelum = $this->barisKegiatan($kegiatan);
        $kursiSebelum = $this->snapshotKursi($kegiatan);
        $this->assertCount(24, $kursiSebelum);

        // Satu jam kemudian: bila ada yang ditulis, updated_at pasti ikut berubah.
        $this->travel(1)->hours();
        $this->simpanForm($kegiatan, $this->formEdit($this->propsKegiatan($kegiatan)));

        $this->assertSame($sebelum, $this->barisKegiatan($kegiatan), 'Ada kolom kegiatan yang berubah padahal form disimpan tanpa perubahan.');
        $this->assertSame($kursiSebelum, $this->snapshotKursi($kegiatan), 'Denah kursi dibuat ulang padahal tidak berubah.');
        Event::assertNotDispatched(KegiatanUpdatedEvent::class);
    }

    public function test_changing_one_field_in_edit_form_changes_only_that_field(): void
    {
        $kegiatan = $this->buatKegiatanLengkap();
        $sebelum = $this->barisKegiatan($kegiatan);
        $kursiSebelum = $this->snapshotKursi($kegiatan);

        $this->travel(1)->hours();
        $form = $this->formEdit($this->propsKegiatan($kegiatan));
        $form['kuota'] = 40;
        $this->simpanForm($kegiatan, $form);

        $sesudah = $this->barisKegiatan($kegiatan);
        $berubah = array_keys(array_diff_assoc(array_map('strval', array_map(fn ($v) => $v ?? '', $sesudah)), array_map('strval', array_map(fn ($v) => $v ?? '', $sebelum))));
        sort($berubah);

        $this->assertSame(['kuota', 'updated_at'], $berubah);
        $this->assertSame(40, (int) $sesudah['kuota']);
        $this->assertSame($kursiSebelum, $this->snapshotKursi($kegiatan));
        Event::assertNotDispatched(KegiatanUpdatedEvent::class);
    }

    public function test_partial_update_does_not_reset_omitted_fields_to_defaults(): void
    {
        $kegiatan = $this->buatKegiatanLengkap();
        $sebelum = $this->barisKegiatan($kegiatan);

        $this->travel(1)->hours();
        $this->simpanForm($kegiatan, ['nama_kegiatan' => 'Lomba Inovasi Digital KIP-K 2026']);

        $sesudah = $this->barisKegiatan($kegiatan);
        $this->assertSame('Lomba Inovasi Digital KIP-K 2026', $sesudah['nama_kegiatan']);
        unset($sebelum['nama_kegiatan'], $sebelum['updated_at'], $sesudah['nama_kegiatan'], $sesudah['updated_at']);
        $this->assertSame($sebelum, $sesudah, 'Field yang tidak dikirim tidak boleh berubah (kuota, booking kursi, karya, dsb.).');
    }

    public function test_narasumber_can_be_added_and_removed_and_is_stored_intact(): void
    {
        $kegiatan = $this->buatKegiatanLengkap();
        $kegiatan->update(['narasumber' => [
            ['nama' => 'Siti Aminah', 'gelar' => 'Dr.', 'instansi' => 'UNUSA', 'foto' => 'narasumber/siti.jpg'],
            ['nama' => 'Budi Hartono', 'gelar' => null, 'instansi' => 'Dinas Kominfo'],
        ]]);

        $form = $this->formEdit($this->propsKegiatan($kegiatan));
        // Hapus narasumber kedua, tambah narasumber baru; foto narasumber pertama harus tetap ada.
        $form['narasumber'] = [$form['narasumber'][0], ['nama' => 'Rina Kusuma', 'gelar' => 'M.Kom.', 'instansi' => '']];
        $this->simpanForm($kegiatan, $form);

        $this->assertSame([
            ['nama' => 'Siti Aminah', 'gelar' => 'Dr.', 'instansi' => 'UNUSA', 'foto' => 'narasumber/siti.jpg'],
            ['nama' => 'Rina Kusuma', 'gelar' => 'M.Kom.', 'instansi' => null],
        ], $kegiatan->fresh()->narasumber);

        // Simpan ulang tanpa perubahan: narasumber tersimpan persis sama.
        $sebelum = $this->barisKegiatan($kegiatan);
        $this->simpanForm($kegiatan, $this->formEdit($this->propsKegiatan($kegiatan)));
        $this->assertSame($sebelum, $this->barisKegiatan($kegiatan));
    }

    public function test_registration_deadline_from_form_is_converted_and_can_be_cleared(): void
    {
        $kegiatan = $this->buatKegiatanLengkap();
        $this->assertSame('2026-11-05 16:59:00', Carbon::parse($this->barisKegiatan($kegiatan)['registration_deadline'])->format('Y-m-d H:i:s'));

        $form = $this->formEdit($this->propsKegiatan($kegiatan));
        $form['registration_deadline'] = '2026-11-06T12:00';
        $this->simpanForm($kegiatan, $form);
        $this->assertSame('2026-11-06 05:00:00', $kegiatan->fresh()->registration_deadline->utc()->format('Y-m-d H:i:s'));

        $form['registration_deadline'] = '';
        $this->simpanForm($kegiatan, $form);
        $this->assertNull($kegiatan->fresh()->registration_deadline);
    }

    public function test_changing_seat_layout_regenerates_seats(): void
    {
        $kegiatan = $this->buatKegiatanLengkap();

        $form = $this->formEdit($this->propsKegiatan($kegiatan));
        $form['seat_layout_config']['cols_per_row'] = 8;
        $this->simpanForm($kegiatan, $form);

        $this->assertSame(32, $kegiatan->seats()->count());
        $this->assertSame(8, $kegiatan->fresh()->seat_layout_config['cols_per_row']);
    }

    public function test_end_before_start_is_rejected_on_partial_update(): void
    {
        $kegiatan = $this->buatKegiatanLengkap();

        $this->actingAs($this->admin)
            ->post(route('admin.kegiatan.update', $kegiatan->id), ['_method' => 'PUT', 'tanggal_selesai' => '2026-11-09T10:00'])
            ->assertSessionHasErrors('tanggal_selesai');

        $this->assertSame('2026-11-10 09:30:00', $kegiatan->fresh()->tanggal_selesai->utc()->format('Y-m-d H:i:s'));
    }
}
