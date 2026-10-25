<?php

namespace App\Models;

use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class KegiatanSeat extends Model
{
    protected $table = 'kegiatan_seats';

    protected $fillable = [
        'kegiatan_id',
        'seat_code',
        'row_label',
        'seat_number',
        'col_number',
        'tier',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'seat_number' => 'integer',
            'col_number' => 'integer',
            'tier' => SeatTier::class,
            'status' => SeatStatus::class,
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(KegiatanRegistration::class, 'seat_id');
    }

    /**
     * Apakah kursi ini sedang dipegang registrasi aktif (pending / paid / free)?
     */
    public function hasActiveRegistration(): bool
    {
        return $this->registrations()
            ->whereIn('status_pembayaran', RegistrationPaymentStatus::activeValues())
            ->exists();
    }

    /**
     * Apakah kursi ini termasuk kategori non-peserta (VIP, Panitia, Diblokir)?
     */
    public function isNonPeserta(): bool
    {
        return $this->tier === SeatTier::Vip
            || $this->tier === SeatTier::Panitia
            || $this->status === SeatStatus::ReservedPanitia
            || $this->status === SeatStatus::Blocked;
    }

    /**
     * Apakah kursi ini tersedia dan sah untuk dipilih oleh peserta biasa?
     */
    public function isAvailableForParticipant(): bool
    {
        return $this->status === SeatStatus::Available && $this->tier === SeatTier::Reguler;
    }

    /**
     * Apakah kursi ini tersedia untuk dialokasikan oleh admin?
     */
    public function isAvailableForAdmin(): bool
    {
        return ! in_array($this->status, [SeatStatus::Booked, SeatStatus::Blocked], true);
    }

    /**
     * Memastikan kursi bisa dipesan, melempar ValidationException dengan pesan netral jika tidak tersedia.
     */
    public static function pastikanBisaDipesan(self $seat, bool $isAdmin = false, string $field = 'seat_id'): void
    {
        if ($isAdmin) {
            if (! $seat->isAvailableForAdmin()) {
                throw ValidationException::withMessages([
                    $field => 'Kursi ini tidak tersedia. Silakan pilih kursi lain.',
                ]);
            }

            return;
        }

        if (! $seat->isAvailableForParticipant()) {
            throw ValidationException::withMessages([
                $field => 'Kursi ini tidak tersedia. Silakan pilih kursi lain.',
            ]);
        }
    }
}
