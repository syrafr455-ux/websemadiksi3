<?php

namespace App\Contracts;

use App\Models\PenerimaKipk;

/**
 * Sumber status penerima KIP Kuliah.
 *
 * SIM KIP Kuliah (Kemdiktisaintek) tidak menyediakan API publik, jadi implementasi saat ini membaca
 * daftar penerima hasil impor super_admin (DatabaseKipRecipientProvider). Implementasi API resmi
 * nanti cukup mengembalikan baris PenerimaKipk lokal (mis. hasil sinkronisasi) agar penautan akun
 * dan audit tetap memakai tabel daftar_penerima_kipk. DILARANG scraping portal atau meminta password SIM KIP.
 */
interface KipRecipientProvider
{
    public function cariByNim(string $nim): ?PenerimaKipk;

    /**
     * Nomor pendaftaran SIM KIP Kuliah; dinormalisasi (hanya digit) sebelum dicari.
     */
    public function cariByNomorPendaftaran(string $nomor): ?PenerimaKipk;
}
