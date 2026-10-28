<?php

namespace App\Enums;

enum CaraHadir: string
{
    case Onsite = 'onsite';
    case Daring = 'daring';

    public function label(): string
    {
        return match ($this) {
            self::Onsite => 'Onsite',
            self::Daring => 'Via Zoom',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
