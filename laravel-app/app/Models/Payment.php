<?php

namespace App\Models;

use App\Enums\GatewayPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'external_reference',
        'user_id',
        'gross_amount',
        'amount',
        'payment_method',
        'payment_status',
        'status',
        'payload_response',
        'payload',
        'expired_at',
        'expires_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'payment_status' => GatewayPaymentStatus::class,
            'payload_response' => 'array',
            'expired_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function getExternalReferenceAttribute()
    {
        return $this->order_id;
    }

    public function setExternalReferenceAttribute($val): void
    {
        $this->attributes['order_id'] = $val;
    }

    public function getAmountAttribute()
    {
        return $this->gross_amount;
    }

    public function setAmountAttribute($val): void
    {
        $this->attributes['gross_amount'] = $val;
    }

    public function getStatusAttribute()
    {
        return $this->payment_status;
    }

    public function setStatusAttribute($val): void
    {
        $this->attributes['payment_status'] = $val instanceof GatewayPaymentStatus ? $val->value : $val;
    }

    public function getPayloadAttribute()
    {
        return $this->payload_response;
    }

    public function setPayloadAttribute($val): void
    {
        $this->attributes['payload_response'] = is_array($val) ? json_encode($val) : $val;
    }

    public function getExpiresAtAttribute()
    {
        return $this->expired_at;
    }

    public function setExpiresAtAttribute($val): void
    {
        $this->attributes['expired_at'] = $val;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(KegiatanRegistration::class);
    }

    public function isPending(): bool
    {
        return $this->payment_status === GatewayPaymentStatus::Pending;
    }

    public function getRouteKeyName(): string
    {
        return 'order_id';
    }
}
