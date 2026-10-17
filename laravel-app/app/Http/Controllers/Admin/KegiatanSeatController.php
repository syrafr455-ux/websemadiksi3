<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateVenueConfigRequest;
use App\Jobs\SendSeatReselectionNotificationJob;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Support\Wib;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class KegiatanSeatController extends Controller
{
    /**
     * Update venue configuration, seat layout, capacity safeguarding, and booking schedule.
     * Hanya boleh diakses oleh super_admin (dijaga oleh FormRequest & Policy).
     */
    public function updateVenueConfig(UpdateVenueConfigRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();
        $totalSeats = (int) $validated['total_seats'];
        $layoutChanged = (bool) $validated['layout_changed'];
        $isSeatBookingActive = (bool) $validated['is_seat_booking_active'];

        // Revisi 6: Jadwal diinput dalam zona waktu Asia/Jakarta dan disimpan dalam UTC
        $bookingOpensAt = Wib::keUtc($validated['booking_opens_at'] ?? null);
        $bookingClosesAt = Wib::keUtc($validated['booking_closes_at'] ?? null);
        $layoutConfig = $validated['layout_config'] ?? [];

        try {
            DB::transaction(function () use (
                $id,
                $validated,
                $totalSeats,
                $layoutChanged,
                $isSeatBookingActive,
                $bookingOpensAt,
                $bookingClosesAt,
                $layoutConfig
            ) {
                /** @var Kegiatan $kegiatan */
                $kegiatan = Kegiatan::where('id', $id)->lockForUpdate()->firstOrFail();

                // Otorisasi eksplisit via Policy
                Gate::authorize('updateVenueConfig', $kegiatan);

                // a. Revisi 3: Hitung currentBookedCount = peserta aktif yang memegang kursi
                // Registrasi berstatus batal/expired dikecualikan dari perhitungan kapasitas.
                $expiredSeatIds = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
                    ->whereIn('status_pembayaran', [
                        RegistrationPaymentStatus::Expired->value,
                        RegistrationPaymentStatus::Failed->value,
                    ])
                    ->whereNotNull('seat_id')
                    ->pluck('seat_id')
                    ->all();

                $activeRegistrationsCount = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
                    ->active()
                    ->whereNotNull('seat_id')
                    ->count();

                $bookedSeatsWithoutExpired = $kegiatan->seats()
                    ->where('status', SeatStatus::Booked->value)
                    ->whereNotIn('id', $expiredSeatIds)
                    ->count();

                $currentBookedCount = max($activeRegistrationsCount, $bookedSeatsWithoutExpired);

                // b. Safeguard Kapasitas: Bila total_seats < currentBookedCount, batalkan dan lempar error
                if ($totalSeats < $currentBookedCount) {
                    $errorMessage = "Pemindahan ruangan dibatalkan: Kapasitas baru ({$totalSeats} kursi) tidak mencukupi untuk menampung {$currentBookedCount} peserta yang sudah memiliki kursi.";
                    throw ValidationException::withMessages([
                        'capacity' => $errorMessage,
                    ]);
                }

                $oldVenue = $kegiatan->lokasi_nama ?: 'Ruangan Sebelumnya';

                // c. Bila layout_changed = true
                if ($layoutChanged) {
                    if ($currentBookedCount > 0) {
                        // 1) Revisi 3: Catat riwayat kursi hanya untuk registrasi aktif yang memiliki kursi, lalu reset kursi
                        $affectedRegistrations = KegiatanRegistration::with('seat')
                            ->where('kegiatan_id', $kegiatan->id)
                            ->active()
                            ->whereNotNull('seat_id')
                            ->get();

                        $nowUtc = now()->setTimezone('UTC')->toIso8601String();

                        foreach ($affectedRegistrations as $reg) {
                            $oldSeatLabel = $reg->seat ? $reg->seat->seat_code : 'N/A';
                            $history = is_array($reg->seat_history) ? $reg->seat_history : [];

                            $history[] = [
                                'venue' => $oldVenue,
                                'seat' => $oldSeatLabel,
                                'changed_at' => $nowUtc,
                            ];

                            $reg->update([
                                'seat_id' => null,
                                'needs_seat_reselection' => true,
                                'seat_history' => $history,
                            ]);
                        }

                        // 2) Hapus kursi lama, lalu generate kursi baru dari layout_config
                        $kegiatan->seats()->delete();
                        $this->generateSeatsForVenue($kegiatan, $layoutConfig);

                        // 3) Naikkan venue_change_count sebesar 1
                        $kegiatan->increment('venue_change_count');

                        // 4) Dispatch SendSeatReselectionNotificationJob dengan afterCommit()
                        SendSeatReselectionNotificationJob::dispatch($kegiatan)->afterCommit();
                    } else {
                        // layout_changed = true dan currentBookedCount = 0: generate ulang tanpa reset peserta & tanpa email
                        $kegiatan->seats()->delete();
                        $this->generateSeatsForVenue($kegiatan, $layoutConfig);
                        $kegiatan->increment('venue_change_count');
                    }

                    $kegiatan->seat_layout_config = $layoutConfig;
                } else {
                    // d. Denah tidak berubah: perbarui metadata kursi tanpa mereset kursi
                    if (! empty($layoutConfig)) {
                        $this->syncSeatMetadata($kegiatan, $layoutConfig);
                        $kegiatan->seat_layout_config = $layoutConfig;
                    }
                }

                // e. Perbarui kegiatan: lokasi_nama, lokasi_type, booking_opens_at, booking_closes_at, is_seat_booking_active
                // CATATAN: kuota pendaftaran TIDAK diubah/disinkronkan dengan total_seats
                if (! empty($validated['lokasi_nama'])) {
                    $kegiatan->lokasi_nama = trim($validated['lokasi_nama']);
                }
                if (! empty($validated['lokasi_type'])) {
                    $kegiatan->lokasi_type = $validated['lokasi_type'];
                }

                $kegiatan->booking_opens_at = $bookingOpensAt;
                $kegiatan->booking_closes_at = $bookingClosesAt;
                $kegiatan->is_seat_booking_active = $isSeatBookingActive;
                $kegiatan->save();

                // Audit Log
                AuditLog::record(
                    auth()->id(),
                    'update_venue_config',
                    "Memperbarui tata letak ruangan & jadwal booking kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
                    $kegiatan
                );
            });

            // f. Sukses
            return back()->with('success', 'Pengaturan denah dan jadwal booking berhasil diperbarui.');
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            return back()->with('error', 'Terjadi kesalahan saat memperbarui denah: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Helper: Men-generate kursi baru untuk venue kegiatan sesuai layout_config.
     */
    protected function generateSeatsForVenue(Kegiatan $kegiatan, array $layout): void
    {
        $rows = $layout['rows'] ?? ['A', 'B', 'C', 'D', 'E', 'F'];
        $cols = (int) ($layout['cols_per_row'] ?? 10);
        $vipRows = $layout['vip_rows'] ?? ['A'];
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
     * Helper: Sinkronisasi metadata kursi (tier, reserved) tanpa mengubah kursi yang sudah booked.
     */
    protected function syncSeatMetadata(Kegiatan $kegiatan, array $layout): void
    {
        $vipRows = $layout['vip_rows'] ?? [];
        $panitiaRows = $layout['panitia_rows'] ?? [];

        // Hanya perbarui kursi yang belum booked
        $availableSeats = $kegiatan->seats()
            ->where('status', '!=', SeatStatus::Booked->value)
            ->get();

        foreach ($availableSeats as $seat) {
            $r = strtoupper($seat->row_label);
            $tier = SeatTier::Reguler;
            if (in_array($r, $vipRows, true)) {
                $tier = SeatTier::Vip;
            } elseif (in_array($r, $panitiaRows, true)) {
                $tier = SeatTier::Panitia;
            }

            $status = ($tier === SeatTier::Panitia)
                ? SeatStatus::ReservedPanitia
                : SeatStatus::Available;

            $seat->update([
                'tier' => $tier,
                'status' => $status,
            ]);
        }
    }
}
