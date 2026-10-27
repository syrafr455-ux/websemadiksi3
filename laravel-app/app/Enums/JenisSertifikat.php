<?php

namespace App\Enums;

enum JenisSertifikat: string
{
    case Peserta = 'peserta';
    case Juri = 'juri';
    case Pembicara = 'pembicara';
    case Juara = 'juara';

    public function label(): string
    {
        return match ($this) {
            self::Peserta => 'Peserta',
            self::Juri => 'Juri',
            self::Pembicara => 'Pembicara',
            self::Juara => 'Juara',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
