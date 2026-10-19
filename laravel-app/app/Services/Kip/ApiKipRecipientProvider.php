<?php

namespace App\Services\Kip;

use App\Contracts\KipRecipientProvider;
use App\Models\PenerimaKipk;
use RuntimeException;

/**
 * KERANGKA KOSONG: integrasi API resmi SIM KIP Kuliah (belum tersedia untuk publik).
 * Aktifkan dengan KIP_RECIPIENT_PROVIDER=api setelah diimplementasikan.
 * DILARANG scraping portal SIM KIP atau meminta password SIM KIP mahasiswa.
 */
class ApiKipRecipientProvider implements KipRecipientProvider
{
    public function cariByNim(string $nim): ?PenerimaKipk
    {
        throw $this->belumTersedia();
    }

    public function cariByNomorPendaftaran(string $nomor): ?PenerimaKipk
    {
        throw $this->belumTersedia();
    }

    private function belumTersedia(): RuntimeException
    {
        return new RuntimeException('Integrasi API SIM KIP Kuliah belum diimplementasikan.');
    }
}
