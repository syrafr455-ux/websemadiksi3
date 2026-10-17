<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KegiatanStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use App\Events\KegiatanUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanSeat;
use App\Services\UnusaFacilityService;
use App\Support\Wib;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanController extends Controller
{
    public function __construct(
        protected UnusaFacilityService $facilityService
    ) {}

    /**
     * Tampilkan daftar kegiatan enterprise di panel admin.
     */
    public function index(Request $request): Response
    {
        \Illuminate\Support\Facades\Gate::authorize('viewAny', Kegiatan::class);

        $query = Kegiatan::query()
            ->withCount(['seats', 'registrations', 'submissions'])
            ->latest('id');

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($w) use ($q) {
                $w->where('nama_kegiatan', 'ilike', "%{$q}%")
                  ->orWhere('kategori', 'ilike', "%{$q}%")
                  ->orWhere('narasumber', 'ilike', "%{$q}%")
                  ->orWhere('lokasi_nama', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->input('kategori'));
        }

        $kegiatans = $query->paginate(12)->withQueryString();
        $unusaFacilities = $this->facilityService->listCampuses();

        return Inertia::render('Admin/Kegiatan/Index', [
            'title' => 'Manajemen Kegiatan Enterprise',
            'kegiatans' => $kegiatans,
            'filters' => $request->only(['q', 'status', 'kategori']),
            'unusaFacilities' => $unusaFacilities,
            'statusOptions' => KegiatanStatus::cases(),
        ]);
    }

    /**
     * Simpan kegiatan baru beserta denah kursi dan dokumen panduan.
     */
    public function store(Request $request): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('create', Kegiatan::class);

        $validated = $request->validate([
            'nama_kegiatan' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => ['required', 'string', 'max:100'],
            'tipe_kegiatan' => ['required', 'string', 'in:seminar,workshop,lomba,pelatihan,sosialisasi,lainnya'],
            'target_peserta' => ['required', 'string', 'in:semua,kipk_only,umum_only'],
            'waktu_mulai' => ['required', 'date'],
            'waktu_selesai' => ['nullable', 'date', 'after_or_equal:waktu_mulai'],
            'lokasi_type' => ['required', 'string', 'in:unusa_campus,external_venue,online'],
            'lokasi_kampus' => ['nullable', 'string'],
            'lokasi_gedung' => ['nullable', 'string'],
            'lokasi_ruangan' => ['nullable', 'string'],
            'lokasi_nama' => ['required', 'string', 'max:255'],
            'lokasi_detail' => ['nullable', 'string', 'max:255'],
            'lokasi_map_url' => ['nullable', 'url', 'max:500'],
            'biaya' => ['required', 'numeric', 'min:0'],
            'kuota_total' => ['required', 'integer', 'min:1'],
            'narasumber' => ['nullable', 'string', 'max:255'],
            'poin_skp' => ['nullable', 'integer', 'min:0'],
            'has_seat_selection' => ['boolean'],
            'has_submission' => ['boolean'],
            'registration_deadline' => ['nullable', 'date'],
            'submission_deadline' => ['nullable', 'date'],
            'submission_rules' => ['nullable', 'string'],
            'panduan_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'banner_image' => ['nullable', 'image', 'max:5120'],
            'certificate_template_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:10240'],
            'certificate_schema_config' => ['nullable', 'array'],
            'seat_layout' => ['nullable', 'array'],
        ]);

        // Validasi fasilitas UNUSA jika lokasi_type adalah unusa_campus
        if ($validated['lokasi_type'] === 'unusa_campus') {
            if (empty($validated['lokasi_gedung']) || empty($validated['lokasi_ruangan'])) {
                return back()->withErrors(['lokasi_gedung' => 'Pilihan gedung dan ruangan UNUSA wajib diisi untuk lokasi kampus.'])->withInput();
            }
        }

        $panduanPath = null;
        if ($request->hasFile('panduan_file')) {
            $panduanPath = $request->file('panduan_file')->store('kegiatan/panduan', 'public');
        }

        $bannerPath = null;
        if ($request->hasFile('banner_image')) {
            $bannerPath = $request->file('banner_image')->store('kegiatan/banner', 'public');
        }

        $certTemplatePath = null;
        if ($request->hasFile('certificate_template_file')) {
            $certTemplatePath = $request->file('certificate_template_file')->store('kegiatan/templates', 'public');
        }

        DB::beginTransaction();
        try {
            $kategoriLower = strtolower(str_replace(' ', '_', $validated['kategori']));
            if (! in_array($kategoriLower, Kegiatan::KATEGORI, true)) {
                $kategoriLower = in_array(strtolower($validated['tipe_kegiatan']), Kegiatan::KATEGORI, true)
                    ? strtolower($validated['tipe_kegiatan'])
                    : 'seminar';
            }

            $sasaran = $validated['target_peserta'];
            if (! in_array($sasaran, Kegiatan::SASARAN, true)) {
                $sasaran = 'umum';
            }

            $lokasiType = $validated['lokasi_type'];
            if (! in_array($lokasiType, Kegiatan::LOKASI_TYPES, true)) {
                $lokasiType = $lokasiType === 'external_venue' ? 'external' : 'unusa_campus';
            }

            $metode = $lokasiType === 'online' ? 'online' : 'offline';

            $kegiatan = Kegiatan::create([
                'nama_kegiatan' => trim($validated['nama_kegiatan']),
                'deskripsi' => $validated['deskripsi'] ?? null,
                'kategori' => $kategoriLower,
                'metode_pelaksanaan' => $metode,
                'sasaran_peserta' => $sasaran,
                // Input jadwal dianggap WIB, disimpan dalam UTC.
                'tanggal_mulai' => Wib::keUtc($validated['waktu_mulai']),
                'tanggal_selesai' => Wib::keUtc($validated['waktu_selesai'] ?? null),
                'registration_deadline' => Wib::keUtc($validated['registration_deadline'] ?? null),
                'submission_deadline' => Wib::keUtc($validated['submission_deadline'] ?? null),
                'lokasi_type' => $lokasiType,
                'lokasi_nama' => trim($validated['lokasi_nama']),
                'lokasi_detail' => $validated['lokasi_detail'] ?? null,
                'biaya' => (float) $validated['biaya'],
                'kuota' => (int) $validated['kuota_total'],
                'narasumber' => ! empty($validated['narasumber']) ? [['nama' => $validated['narasumber']]] : null,
                'poin_xp' => (int) ($validated['poin_skp'] ?? 0),
                'file_panduan_pdf' => $panduanPath ?? 'kegiatan/panduan/default_panduan.pdf',
                'certificate_template_path' => $certTemplatePath,
                'certificate_schema_config' => $request->input('certificate_schema_config'),
                'is_seat_booking_active' => $request->boolean('has_seat_selection'),
                'is_submission_active' => $request->boolean('has_submission'),
                'submission_instructions' => $validated['submission_rules'] ?? null,
                'status' => KegiatanStatus::Published,
                'seat_layout_config' => $request->input('seat_layout', []),
            ]);

            // Buat generator denah kursi interaktif jika has_seat_selection aktif
            if ($request->boolean('has_seat_selection')) {
                $layout = $request->input('seat_layout', []);
                $this->generateSeatsForKegiatan($kegiatan, $layout);
            }

            AuditLog::record(
                auth()->id(),
                'create_kegiatan',
                "Membuat kegiatan baru '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
                $kegiatan
            );

            DB::commit();

            return redirect()->route('admin.kegiatan')->with('success', 'Kegiatan enterprise berhasil diterbitkan.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membuat kegiatan: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Tampilkan detail atau data edit kegiatan.
     */
    public function show(int $id): Response
    {
        $kegiatan = Kegiatan::with(['seats' => function ($q) {
            $q->orderBy('row_label')->orderBy('col_number');
        }, 'registrations.participantUser'])->findOrFail($id);

        return Inertia::render('Admin/Kegiatan/Show', [
            'kegiatan' => $kegiatan,
            'unusaFacilities' => $this->facilityService->listCampuses(),
        ]);
    }

    /**
     * Perbarui data kegiatan, periksa perubahan jadwal/lokasi & lindungi kursi booked.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::with('seats')->findOrFail($id);
        \Illuminate\Support\Facades\Gate::authorize('update', $kegiatan);

        $validated = $request->validate([
            'nama_kegiatan' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => ['required', 'string', 'max:100'],
            'tipe_kegiatan' => ['nullable', 'string'],
            'target_peserta' => ['nullable', 'string'],
            'waktu_mulai' => ['required', 'date'],
            'waktu_selesai' => ['nullable', 'date', 'after_or_equal:waktu_mulai'],
            'lokasi_type' => ['required', 'string'],
            'lokasi_kampus' => ['nullable', 'string'],
            'lokasi_gedung' => ['nullable', 'string'],
            'lokasi_ruangan' => ['nullable', 'string'],
            'lokasi_nama' => ['required', 'string', 'max:255'],
            'lokasi_detail' => ['nullable', 'string', 'max:255'],
            'lokasi_map_url' => ['nullable', 'url', 'max:500'],
            'biaya' => ['required', 'numeric', 'min:0'],
            'kuota_total' => ['required', 'integer', 'min:1'],
            'narasumber' => ['nullable', 'string', 'max:255'],
            'poin_skp' => ['nullable', 'integer', 'min:0'],
            'has_seat_selection' => ['boolean'],
            'has_submission' => ['boolean'],
            'registration_deadline' => ['nullable', 'date'],
            'submission_deadline' => ['nullable', 'date'],
            'submission_rules' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'panduan_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'banner_image' => ['nullable', 'image', 'max:5120'],
            'certificate_template_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:10240'],
            'certificate_schema_config' => ['nullable', 'array'],
            'seat_layout' => ['nullable', 'array'],
        ]);

        DB::beginTransaction();
        try {
            $changedAttributes = [];
            // Bandingkan jadwal lama dan baru sama-sama dalam WIB.
            $oldWaktuMulai = Wib::dari($kegiatan->tanggal_mulai)?->format('Y-m-d H:i');
            $newWaktuMulai = Wib::dari(Wib::keUtc($validated['waktu_mulai']))->format('Y-m-d H:i');
            $oldLokasi = $kegiatan->lokasi_nama.' '.$kegiatan->lokasi_detail;
            $newLokasi = trim($validated['lokasi_nama']).' '.($validated['lokasi_detail'] ?? '');

            if ($oldWaktuMulai !== $newWaktuMulai) {
                $changedAttributes['waktu_mulai'] = ['old' => $oldWaktuMulai, 'new' => $newWaktuMulai];
            }
            if ($oldLokasi !== $newLokasi) {
                $changedAttributes['lokasi'] = ['old' => $oldLokasi, 'new' => $newLokasi];
            }

            $panduanFile = $kegiatan->file_panduan_pdf;
            if ($request->hasFile('panduan_file')) {
                $panduanFile = $request->file('panduan_file')->store('kegiatan/panduan', 'public');
            }

            $certTemplatePath = $kegiatan->certificate_template_path;
            if ($request->hasFile('certificate_template_file')) {
                $certTemplatePath = $request->file('certificate_template_file')->store('kegiatan/templates', 'public');
            }

            $kategoriLower = strtolower(str_replace(' ', '_', $validated['kategori']));
            if (! in_array($kategoriLower, Kegiatan::KATEGORI, true)) {
                $kategoriLower = $kegiatan->kategori;
            }

            $status = KegiatanStatus::tryFrom($validated['status']) ?? KegiatanStatus::Published;

            $updatePayload = [
                'nama_kegiatan' => trim($validated['nama_kegiatan']),
                'deskripsi' => $validated['deskripsi'] ?? null,
                'kategori' => $kategoriLower,
                // Input jadwal dianggap WIB, disimpan dalam UTC. Deadline yang tidak dikirim tetap memakai nilai lama (sudah UTC).
                'tanggal_mulai' => Wib::keUtc($validated['waktu_mulai']),
                'tanggal_selesai' => Wib::keUtc($validated['waktu_selesai'] ?? null),
                'registration_deadline' => Wib::keUtc($validated['registration_deadline'] ?? null) ?? $kegiatan->registration_deadline,
                'submission_deadline' => Wib::keUtc($validated['submission_deadline'] ?? null) ?? $kegiatan->submission_deadline,
                'lokasi_nama' => trim($validated['lokasi_nama']),
                'lokasi_detail' => $validated['lokasi_detail'] ?? null,
                'biaya' => (float) $validated['biaya'],
                'kuota' => (int) $validated['kuota_total'],
                'poin_xp' => (int) ($validated['poin_skp'] ?? 0),
                'file_panduan_pdf' => $panduanFile,
                'certificate_template_path' => $certTemplatePath,
                'is_seat_booking_active' => $request->boolean('has_seat_selection'),
                'is_submission_active' => $request->boolean('has_submission'),
                'submission_instructions' => $validated['submission_rules'] ?? null,
                'status' => $status,
            ];

            if ($request->has('certificate_schema_config')) {
                $updatePayload['certificate_schema_config'] = $request->input('certificate_schema_config');
            }

            $kegiatan->update($updatePayload);

            // Jika seat layout diubah, update hanya kursi yang belum booked
            if ($request->boolean('has_seat_selection') && $request->has('seat_layout')) {
                $layout = $request->input('seat_layout', []);
                $this->updateSeatsForKegiatan($kegiatan, $layout);
            }

            // Jika ada perubahan waktu/lokasi, trigger notifikasi email kepada peserta
            if (! empty($changedAttributes)) {
                event(new KegiatanUpdatedEvent($kegiatan, $changedAttributes));
            }

            AuditLog::record(
                auth()->id(),
                'update_kegiatan',
                "Memperbarui data kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
                $kegiatan
            );

            DB::commit();

            return back()->with('success', 'Kegiatan berhasil diperbarui.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memperbarui kegiatan: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Hapus kegiatan (hanya jika belum ada peserta yang memesan tiket/kursi).
     */
    public function destroy(int $id): RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        \Illuminate\Support\Facades\Gate::authorize('delete', $kegiatan);

        $hasBooked = $kegiatan->registrations()->exists();
        if ($hasBooked) {
            return back()->with('error', 'Kegiatan tidak dapat dihapus karena sudah memiliki peserta terdaftar.');
        }

        DB::beginTransaction();
        try {
            $kegiatan->seats()->delete();
            $kegiatan->delete();

            AuditLog::record(
                auth()->id(),
                'delete_kegiatan',
                "Menghapus kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$id})",
                null
            );

            DB::commit();

            return redirect()->route('admin.kegiatan')->with('success', 'Kegiatan berhasil dihapus.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus kegiatan: '.$e->getMessage());
        }
    }

    /**
     * Helper: Generate denah kursi otomatis baris A-I.
     */
    protected function generateSeatsForKegiatan(Kegiatan $kegiatan, array $layout): void
    {
        $rows = $layout['rows'] ?? ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        $cols = (int) ($layout['cols_per_row'] ?? 10);
        $vipRows = $layout['vip_rows'] ?? ['A', 'B'];
        $panitiaRows = $layout['panitia_rows'] ?? [];

        $seatsToInsert = [];
        $now = now();

        foreach ($rows as $r) {
            $r = strtoupper(trim($r));
            for ($c = 1; $c <= $cols; $c++) {
                $tier = SeatTier::Reguler;
                if (in_array($r, $vipRows, true)) {
                    $tier = SeatTier::Vip;
                } elseif (in_array($r, $panitiaRows, true)) {
                    $tier = SeatTier::Panitia;
                }

                $status = ($tier === SeatTier::Panitia)
                    ? SeatStatus::ReservedPanitia
                    : SeatStatus::Available;

                $seatCode = $r.str_pad((string) $c, 2, '0', STR_PAD_LEFT);

                $seatsToInsert[] = [
                    'kegiatan_id' => $kegiatan->id,
                    'seat_code' => $seatCode,
                    'row_label' => $r,
                    'seat_number' => $c,
                    'col_number' => $c,
                    'tier' => $tier->value,
                    'status' => $status->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($seatsToInsert)) {
            KegiatanSeat::insert($seatsToInsert);
        }
    }

    /**
     * Helper: Update denah kursi dengan proteksi kursi yang berstatus 'booked'.
     */
    protected function updateSeatsForKegiatan(Kegiatan $kegiatan, array $layout): void
    {
        $existingBookedSeatCodes = $kegiatan->seats()
            ->where('status', SeatStatus::Booked->value)
            ->pluck('seat_code')
            ->all();

        // Jangan pernah hapus kursi booked
        $kegiatan->seats()
            ->whereNotIn('seat_code', $existingBookedSeatCodes)
            ->delete();

        $rows = $layout['rows'] ?? ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        $cols = (int) ($layout['cols_per_row'] ?? 10);
        $vipRows = $layout['vip_rows'] ?? ['A', 'B'];
        $panitiaRows = $layout['panitia_rows'] ?? [];

        $seatsToInsert = [];
        $now = now();

        foreach ($rows as $r) {
            $r = strtoupper(trim($r));
            for ($c = 1; $c <= $cols; $c++) {
                $seatCode = $r.str_pad((string) $c, 2, '0', STR_PAD_LEFT);

                // Jika sudah ada kursi booked, biarkan tetap ada
                if (in_array($seatCode, $existingBookedSeatCodes, true)) {
                    continue;
                }

                $tier = SeatTier::Reguler;
                if (in_array($r, $vipRows, true)) {
                    $tier = SeatTier::Vip;
                } elseif (in_array($r, $panitiaRows, true)) {
                    $tier = SeatTier::Panitia;
                }

                $status = ($tier === SeatTier::Panitia)
                    ? SeatStatus::ReservedPanitia
                    : SeatStatus::Available;

                $seatsToInsert[] = [
                    'kegiatan_id' => $kegiatan->id,
                    'seat_code' => $seatCode,
                    'row_label' => $r,
                    'seat_number' => $c,
                    'col_number' => $c,
                    'tier' => $tier->value,
                    'status' => $status->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($seatsToInsert)) {
            KegiatanSeat::insert($seatsToInsert);
        }
    }
}
