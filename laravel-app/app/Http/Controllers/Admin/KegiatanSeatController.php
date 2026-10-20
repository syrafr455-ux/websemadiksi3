<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateVenueConfigRequest;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Services\Kegiatan\DenahKursiService;
use App\Services\UnusaFacilityService;
use App\Support\Wib;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class KegiatanSeatController extends Controller
{
    public function __construct(
        protected DenahKursiService $denah,
        protected UnusaFacilityService $fasilitas
    ) {}

    /**
     * Perbarui ruangan (pindah venue), denah kursi, dan jadwal booking. Pengaman kapasitas dan reset kursi
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

                $kapasitas = isset($validated['total_seats']) ? (int) $validated['total_seats'] : null;

                if ((bool) $validated['layout_changed']) {
                    // Susunan berubah: kapasitas dicek, kursi peserta direset + email pilih ulang (afterCommit),
                    // venue_change_count +1. Hanya VIP/panitia/blokir yang berubah: kursi disinkronkan tanpa reset.
                    $kegiatan->seat_layout_config = $this->denah->terapkan(
                        $kegiatan,
                        $this->denahDikirim($kegiatan, $validated),
                        $kapasitas,
                        $kegiatan->lokasi_nama
                    );
                } else {
                    $this->denah->pastikanKapasitasCukup($kegiatan, $kapasitas ?? $this->denah->kapasitas($kegiatan->seat_layout_config ?? []));

                    if (! empty($validated['layout_config'])) {
                        $kegiatan->seat_layout_config = $this->denah->sinkronkan($kegiatan, $this->denahDikirim($kegiatan, $validated));
                    }
                }

                $this->terapkanVenue($kegiatan, $validated);

                // Kuota pendaftaran TIDAK disinkronkan dengan kapasitas kursi.
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

    /**
     * Dampak perubahan denah sebelum disimpan (untuk dialog konfirmasi): apakah kursi akan direset,
     * jumlah peserta yang diminta memilih ulang, jumlah email, dan pesan penolakan bila kapasitas kurang.
     * Tidak mengubah data apa pun.
     */
    public function pratinjau(UpdateVenueConfigRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $kegiatan = Kegiatan::findOrFail($id);
        Gate::authorize('updateVenueConfig', $kegiatan);

        $layout = (bool) $validated['layout_changed']
            ? $this->denahDikirim($kegiatan, $validated)
            : ($kegiatan->seat_layout_config ?? []);

        return response()->json($this->denah->pratinjau(
            $kegiatan,
            $layout,
            isset($validated['total_seats']) ? (int) $validated['total_seats'] : null
        ));
    }

    /**
     * Denah yang dikirim, dilengkapi nilai tersimpan untuk key yang tidak dikirim (mis. kursi diblokir).
     */
    protected function denahDikirim(Kegiatan $kegiatan, array $validated): array
    {
        return [...$this->denah->normalisasi($kegiatan->seat_layout_config ?? []), ...($validated['layout_config'] ?? [])];
    }

    /**
     * Pindah venue: tipe lokasi, nama & detail lokasi, serta pilihan fasilitas UNUSA (divalidasi).
     */
    protected function terapkanVenue(Kegiatan $kegiatan, array $validated): void
    {
        if (! empty($validated['lokasi_nama'])) {
            $kegiatan->lokasi_nama = trim($validated['lokasi_nama']);
        }
        if (array_key_exists('lokasi_detail', $validated)) {
            $kegiatan->lokasi_detail = $validated['lokasi_detail'];
        }

        $kirimFasilitas = array_intersect(['lokasi_kampus', 'lokasi_gedung', 'lokasi_ruangan'], array_keys($validated)) !== [];
        $tipe = $validated['lokasi_type'] ?? $kegiatan->lokasi_type;

        if (! empty($validated['lokasi_type']) || $kirimFasilitas) {
            $kegiatan->lokasi_type = $tipe;
            if ($kirimFasilitas || $tipe !== 'unusa_campus') {
                $kegiatan->fill($this->fasilitas->pilihanKampus($tipe, [
                    'lokasi_kampus' => $validated['lokasi_kampus'] ?? null,
                    'lokasi_gedung' => $validated['lokasi_gedung'] ?? null,
                    'lokasi_ruangan' => $validated['lokasi_ruangan'] ?? null,
                ]));
            }
        }
    }
}
