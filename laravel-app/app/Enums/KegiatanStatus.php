<?php

namespace App\Enums;

enum KegiatanStatus: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Published = 'published';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Diajukan',
            self::Published => 'Dipublikasikan',
            self::Ongoing => 'Berlangsung',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Status di mana peserta masih boleh melakukan registrasi / booking.
     */
    public function isBookable(): bool
    {
        return in_array($this, [self::Published, self::Ongoing], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
