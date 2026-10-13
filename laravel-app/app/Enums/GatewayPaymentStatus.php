<?php

namespace App\Enums;

enum GatewayPaymentStatus: string
{
    case Pending = 'pending';
    case Settlement = 'settlement';
    case Expired = 'expired';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
