<?php

namespace App\Services;

use App\Contracts\UnusaFacilityProvider;
use Illuminate\Support\Facades\Cache;

/**
 * Fasad domain untuk master ruangan UNUSA.
 *
 * Seluruh kode aplikasi (validasi, dropdown form) hanya bergantung pada kelas ini,
 * sehingga integrasi API kampus di masa depan cukup mengganti UnusaFacilityProvider.
 */
class UnusaFacilityService
{
    public function __construct(private readonly UnusaFacilityProvider $provider)
    {
    }

    /**
     * @return list<array{code: string, name: string, campus: string, capacity: int}>
     */
    public function rooms(): array
    {
        $ttl = (int) config('unusa.facilities_cache_ttl', 0);

        if ($ttl <= 0) {
            return $this->provider->all();
        }

        return Cache::remember('unusa.facilities', $ttl, fn () => $this->provider->all());
    }

    /**
     * @return list<string>
     */
    public function roomNames(): array
    {
        return array_map(static fn (array $room): string => $room['name'], $this->rooms());
    }

    public function isValidRoom(string $name): bool
    {
        return in_array($name, $this->roomNames(), true);
    }

    public function find(string $name): ?array
    {
        foreach ($this->rooms() as $room) {
            if ($room['name'] === $name) {
                return $room;
            }
        }

        return null;
    }

    public function listCampuses(): array
    {
        return [
            [
                'name' => 'Kampus B (Jemursari)',
                'address' => 'Jl. Raya Jemursari No. 57, Surabaya',
                'buildings' => [
                    [
                        'name' => 'Tower UNUSA',
                        'rooms' => [
                            ['name' => 'Auditorium Lt. 9', 'capacity' => 500],
                            ['name' => 'Kafe Fastron Lt. 3', 'capacity' => 150],
                            ['name' => 'Smart Classroom Lt. 4', 'capacity' => 80],
                        ],
                    ],
                    [
                        'name' => 'Gedung Rektorat & Serba Guna',
                        'rooms' => [
                            ['name' => 'GSG UNUSA', 'capacity' => 800],
                            ['name' => 'Ruang Seminar Rektorat', 'capacity' => 150],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Kampus A (Wonokromo)',
                'address' => 'Jl. SMEA No. 57, Wonokromo, Surabaya',
                'buildings' => [
                    [
                        'name' => 'Gedung A',
                        'rooms' => [
                            ['name' => 'Aula Utama Kampus A', 'capacity' => 400],
                            ['name' => 'Ruang Rapat Utama', 'capacity' => 60],
                        ],
                    ],
                ],
            ],
        ];
    }
}
