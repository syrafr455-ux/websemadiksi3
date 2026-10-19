<?php

namespace App\Contracts;

/**
 * Sumber data akademik UNUSA: fakultas, program studi, mahasiswa, dan staf (dosen/admin).
 *
 * Implementasi saat ini membaca tabel lokal (DatabaseUnusaAcademicProvider). Saat API database
 * UNUSA tersedia, cukup buat provider baru yang mengimplementasikan kontrak ini lalu ganti
 * binding di AppServiceProvider -- controller, form, dan policy tidak perlu diubah.
 */
interface UnusaAcademicProvider
{
    /**
     * @return list<array{id: int, kode: string, nama: string}>
     */
    public function fakultas(): array;

    /**
     * @return list<array{id: int, kode: string, nama: string, jenjang: string, fakultas_id: int}>
     */
    public function programStudi(?int $fakultasId = null): array;

    /**
     * Data mahasiswa berdasarkan NIM, atau null bila tidak ditemukan.
     *
     * @return array{nim: string, nama: string, email: ?string, program_studi_id: ?int}|null
     */
    public function findMahasiswaByNim(string $nim): ?array;

    /**
     * Data staf (dosen / admin fakultas / admin prodi) berdasarkan email, atau null bila tidak ditemukan.
     *
     * @return array{email: string, nama: string, fakultas_id: ?int, program_studi_id: ?int}|null
     */
    public function findStaffByEmail(string $email): ?array;
}
