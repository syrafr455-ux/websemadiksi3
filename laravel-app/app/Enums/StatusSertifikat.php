<?php

namespace App\Enums;

enum StatusSertifikat: string
{
    case Aktif = 'aktif';
    case Dicabut = 'dicabut';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Sah',
            self::Dicabut => 'Dicabut',
        };
    }
}
