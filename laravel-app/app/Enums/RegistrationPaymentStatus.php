<?php

namespace App\Enums;

enum RegistrationPaymentStatus: string
{
    case Free = 'free';
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratis',
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Lunas',
            self::Expired => 'Kedaluwarsa',
            self::Failed => 'Gagal',
        };
    }

    /**
     * Status registrasi yang masih "memegang" kursi dan kuota.
     *
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Free->value, self::Pending->value, self::Paid->value];
    }

    /**
     * Registrasi sudah sah (boleh check-in, unduh tiket, unggah karya).
     */
    public function isConfirmed(): bool
    {
        return in_array($this, [self::Free, self::Paid], true);
    }
}
