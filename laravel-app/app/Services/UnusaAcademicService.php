<?php

namespace App\Services;

use App\Contracts\UnusaAcademicProvider;

class UnusaAcademicService
{
    public function __construct(private readonly UnusaAcademicProvider $provider) {}

    /**
     * Fakultas beserta program studinya, untuk pilihan di form.
     *
     * @return list<array{id: int, kode: string, nama: string, program_studi: list<array<string, mixed>>}>
     */
    public function fakultasDenganProdi(): array
    {
        $prodi = collect($this->provider->programStudi())->groupBy('fakultas_id');

        return array_map(
            fn (array $fakultas): array => $fakultas + ['program_studi' => $prodi->get($fakultas['id'], collect())->values()->all()],
            $this->provider->fakultas()
        );
    }

    public function cariMahasiswaByNim(string $nim): ?array
    {
        return $this->provider->cariMahasiswaByNim($nim);
    }

    public function findStaffByEmail(string $email): ?array
    {
        return $this->provider->findStaffByEmail($email);
    }
}
