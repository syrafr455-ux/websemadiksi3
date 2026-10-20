<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateVenueConfigRequest;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Services\Kegiatan\DenahKursiService;
use App\Support\Wib;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class KegiatanSeatController extends Controller
{
    public function __construct(
        protected DenahKursiService $denah
    ) {}

    /**
     * Perbarui ruangan, denah kursi, dan jadwal booking. Pengaman kapasitas dan reset kursi
     * memakai DenahKursiService yang sama dengan form edit kegiatan.
     * Hanya boleh diakses oleh super_admin (dijaga oleh FormRequest & Policy).
     */
    public function updateVenueConfig(UpdateVenueConfigRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($id, $validated) {
                /** @var Kegiatan $kegiatan */
                $kegiatan = Kegiatan::where('id', $id)->lockForUpdate()->firstOrFail();
                Gate::authorize('updateVenueConfig', $kegiatan);

                $totalSeats = (int) $validated['total_seats'];
                $layoutConfig = $validated['layout_config'] ?? [];

                if ((bool) $validated['layout_changed']) {
                    // Kapasitas dicek, kursi peserta direset + email pilih ulang (afterCommit), venue_change_count +1.
                    $kegiatan->seat_layout_config = $this->denah->ganti($kegiatan, $layoutConfig, $totalSeats, $kegiatan->lokasi_nama);
                } else {
                    $this->denah->pastikanKapasitasCukup($kegiatan, $totalSeats);

                    // Denah tidak berubah: perbarui tier kursi yang belum dipesan tanpa mereset kursi.
                    if (! empty($layoutConfig)) {
                        $kegiatan->seat_layout_config = $this->denah->sinkronkanTier(
                            $kegiatan,
                            array_merge($kegiatan->seat_layout_config ?? [], $layoutConfig)
                        );
                    }
                }

                // Kuota pendaftaran TIDAK disinkronkan dengan total_seats.
                if (! empty($validated['lokasi_nama'])) {
                    $kegiatan->lokasi_nama = trim($validated['lokasi_nama']);
                }
                if (! empty($validated['lokasi_type'])) {
                    $kegiatan->lokasi_type = $validated['lokasi_type'];
                }

                // Jadwal diinput dalam WIB dan disimpan dalam UTC.
                $kegiatan->booking_opens_at = Wib::keUtc($validated['booking_opens_at'] ?? null);
                $kegiatan->booking_closes_at = Wib::keUtc($validated['booking_closes_at'] ?? null);
                $kegiatan->is_seat_booking_active = (bool) $validated['is_seat_booking_active'];
                $kegiatan->save();

                AuditLog::record(
                    auth()->id(),
                    'update_venue_config',
                    "Memperbarui tata letak ruangan & jadwal booking kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
                    $kegiatan
                );
            });

            return back()->with('success', 'Pengaturan denah dan jadwal booking berhasil diperbarui.');
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat memperbarui denah. Silakan coba lagi.')->withInput();
        }
    }
}
