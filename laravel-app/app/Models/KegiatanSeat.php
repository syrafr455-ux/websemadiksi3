<?php

namespace App\Models;

use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
