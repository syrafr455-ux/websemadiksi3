<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\RegistrationPaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class KegiatanRegistration extends Model
{
    protected $table = 'kegiatan_registrations';

    protected $fillable = [
        'kegiatan_id',
        'booker_user_id',
        'participant_user_id',
        'seat_id',
        'nomor_tiket',
        'ticket_code',
        'participant_name',
        'participant_identity',
        'participant_phone',
        'amount_paid',
        'delegation_data',
        'status_kehadiran',
        'attendance_status',
        'status_pembayaran',
        'payment_status',
        'payment_id',
        'checked_in_at',
        'needs_seat_reselection',
        'seat_history',
    ];

    protected function casts(): array
    {
        return [
            'status_kehadiran' => AttendanceStatus::class,
            'status_pembayaran' => RegistrationPaymentStatus::class,
            'checked_in_at' => 'datetime',
            'amount_paid' => 'decimal:2',
            'delegation_data' => 'array',
            'needs_seat_reselection' => 'boolean',
            'seat_history' => 'array',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class)->withTrashed();
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booker_user_id');
    }

    public function bookerUser(): BelongsTo
    {
        return $this->booker();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_user_id');
    }

    public function participantUser(): BelongsTo
    {
        return $this->participant();
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(KegiatanSeat::class, 'seat_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getPaymentStatusAttribute()
    {
        return $this->status_pembayaran;
    }

    public function setPaymentStatusAttribute($value): void
    {
        $this->attributes['status_pembayaran'] = $value instanceof RegistrationPaymentStatus ? $value->value : $value;
    }

    public function getAttendanceStatusAttribute()
    {
        return $this->status_kehadiran;
    }

    public function setAttendanceStatusAttribute($value): void
    {
        $this->attributes['status_kehadiran'] = $value instanceof AttendanceStatus ? $value->value : $value;
    }

    public function submission(): HasOne
    {
        return $this->hasOne(KegiatanSubmission::class, 'registration_id');
    }

    /**
     * Registrasi yang masih memegang kursi/kuota (free, pending, paid).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status_pembayaran', RegistrationPaymentStatus::activeValues());
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereIn('status_pembayaran', [
            RegistrationPaymentStatus::Free->value,
            RegistrationPaymentStatus::Paid->value,
        ]);
    }

    public function isConfirmed(): bool
    {
        return $this->status_pembayaran?->isConfirmed() ?? false;
    }

    /**
     * Format: TKT-{KEGIATAN_ID}-{RANDOM}-{SEAT}, contoh TKT-12-X7Q2M9-B4.
     * Tanpa kursi (event tanpa denah) memakai penanda GEN.
     */
    public static function generateTicketNumber(int $kegiatanId, ?string $seatCode): string
    {
        $seat = $seatCode !== null && $seatCode !== ''
            ? strtoupper(str_replace('-', '', $seatCode))
            : 'GEN';

        do {
            $number = sprintf('TKT-%d-%s-%s', $kegiatanId, strtoupper(Str::random(6)), $seat);
        } while (static::query()->where('nomor_tiket', $number)->exists());

        return $number;
    }

    public function getTicketCodeAttribute(): ?string
    {
        return $this->nomor_tiket;
    }

    public function setTicketCodeAttribute($value): void
    {
        $this->attributes['nomor_tiket'] = $value;
    }

    public function getTicketHolderNameAttribute(): ?string
    {
        return $this->participant_name ?: ($this->participantUser?->name ?? $this->bookerUser?->name);
    }

    public function setTicketHolderNameAttribute($value): void
    {
        $this->attributes['participant_name'] = $value;
    }

    public function getTicketHolderIdentityAttribute(): ?string
    {
        return $this->participant_identity ?: ($this->participantUser?->nim ?? null);
    }

    public function setTicketHolderIdentityAttribute($value): void
    {
        $this->attributes['participant_identity'] = $value;
    }

    public function getTicketHolderPhoneAttribute(): ?string
    {
        return $this->participant_phone ?: ($this->participantUser?->phone ?? null);
    }

    public function setTicketHolderPhoneAttribute($value): void
    {
        $this->attributes['participant_phone'] = $value;
    }

    public static function generateNomorTiket(int $kegiatanId, ?string $seatCode): string
    {
        return static::generateTicketNumber($kegiatanId, $seatCode);
    }
}
