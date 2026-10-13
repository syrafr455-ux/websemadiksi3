<?php

namespace App\Contracts;

interface UnusaFacilityProvider
{
    /**
     * Daftar ruangan/fasilitas kampus UNUSA.
     *
     * @return list<array{code: string, name: string, campus: string, capacity: int}>
     */
    public function all(): array;
}
