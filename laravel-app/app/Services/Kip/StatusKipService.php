<?php

namespace App\Services\Kip;

use App\Models\User;

/**
 * Perubahan status KIP-K akun (penautan ke daftar penerima, naik/turun role) beserta audit & notifikasi.
 */
class StatusKipService
{
    /**
     * Dipanggil setelah impor daftar penerima. (Penautan akun lewat NIM & penandaan penerima
     * yang hilang dari daftar ditambahkan bersama alur klaim.)
     *
     * @param  list<string>  $hashDiImpor
     * @return array<string, int>
     */
    public function sinkronkanSetelahImpor(array $hashDiImpor, User $admin): array
    {
        return [];
    }
}
