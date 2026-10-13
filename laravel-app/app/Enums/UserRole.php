<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case MahasiswaKipk = 'mahasiswa_kipk';
    case UserUmum = 'user_umum';
    case VerifikatorBerkas = 'verifikator_berkas';
    case PenilaiKarya = 'penilai_karya';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::MahasiswaKipk => 'Mahasiswa KIP-K',
            self::UserUmum => 'User Umum',
            self::VerifikatorBerkas => 'Verifikator Berkas',
            self::PenilaiKarya => 'Penilai Karya (Juri)',
        };
    }

    /**
     * Role yang masuk ke panel pengelola (bukan peserta kegiatan).
     */
    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::VerifikatorBerkas, self::PenilaiKarya], true);
    }

    /**
     * Role yang dapat menjadi peserta / pemesan kegiatan.
     */
    public function isParticipant(): bool
    {
        return in_array($this, [self::MahasiswaKipk, self::UserUmum], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function participantValues(): array
    {
        return [self::MahasiswaKipk->value, self::UserUmum->value];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $role): array => ['value' => $role->value, 'label' => $role->label()],
            self::cases()
        );
    }
}
