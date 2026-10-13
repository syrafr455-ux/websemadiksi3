<?php

namespace App\Enums;

enum PeranSertifikat: string
{
    case Peserta = 'peserta';
    case Juara1 = 'juara_1';
    case Juara2 = 'juara_2';
    case Juara3 = 'juara_3';
    case Narasumber = 'narasumber';
    case Panitia = 'panitia';

    public function label(): string
    {
        return match ($this) {
            self::Peserta => 'Peserta',
            self::Juara1 => 'Juara 1',
            self::Juara2 => 'Juara 2',
            self::Juara3 => 'Juara 3',
            self::Narasumber => 'Narasumber',
            self::Panitia => 'Panitia Pelaksana',
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
