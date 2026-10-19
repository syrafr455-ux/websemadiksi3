<?php

namespace App\Services\Academic;

use App\Contracts\UnusaAcademicProvider;
use RuntimeException;

/**
 * KERANGKA KOSONG: integrasi API database UNUSA (belum tersedia).
 * Aktifkan dengan UNUSA_ACADEMIC_PROVIDER=api setelah method di bawah diimplementasikan.
 */
class ApiUnusaAcademicProvider implements UnusaAcademicProvider
{
    public function fakultas(): array
    {
        throw $this->belumTersedia();
    }

    public function programStudi(?int $fakultasId = null): array
    {
        throw $this->belumTersedia();
    }

    public function cariMahasiswaByNim(string $nim): ?array
    {
        throw $this->belumTersedia();
    }

    public function findStaffByEmail(string $email): ?array
    {
        throw $this->belumTersedia();
    }

    private function belumTersedia(): RuntimeException
    {
        return new RuntimeException('Integrasi API database UNUSA belum diimplementasikan.');
    }
}
