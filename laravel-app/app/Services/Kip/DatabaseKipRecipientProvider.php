<?php

namespace App\Services\Kip;

use App\Contracts\KipRecipientProvider;
use App\Models\PenerimaKipk;
use App\Support\NomorKip;

/**
 * IMPLEMENTASI SEMENTARA: daftar penerima KIP-K hasil impor super_admin (tabel daftar_penerima_kipk).
 */
class DatabaseKipRecipientProvider implements KipRecipientProvider
{
    public function cariByNim(string $nim): ?PenerimaKipk
    {
        $nim = trim($nim);

        return $nim === '' ? null : PenerimaKipk::where('nim', $nim)->first();
    }

    public function cariByNomorPendaftaran(string $nomor): ?PenerimaKipk
    {
        return NomorKip::normalisasi($nomor) === '' ? null : PenerimaKipk::denganNomor($nomor)->first();
    }
}
