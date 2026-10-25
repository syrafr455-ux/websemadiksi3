<?php

namespace App\Services\Academic;

use App\Contracts\UnusaAcademicProvider;
use App\Models\ProgramStudi;
use App\Services\Unusa\UnusaApiClient;
use App\Services\Unusa\UnusaApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Data mahasiswa dari API UNUSA (UNUSA_ACADEMIC_PROVIDER=api). Referensi fakultas/prodi dan staf tetap
 * dari database lokal. Bila API gagal, pencarian kembali ke data impor CSV dan penyebabnya dicatat
 * untuk admin (tanpa NIM atau data pribadi); pengguna tidak melihat galat teknis.
 * Dilengkapi cache singkat untuk pencarian NIM (dari config unusa.api.cache_ttl_seconds).
 *
 * Bentuk respons yang diasumsikan (perlu dikonfirmasi IT UNUSA, lihat docs/api-unusa.md):
 * {nim, nama, email, no_telepon, semester, angkatan, program_studi, status, prestasi: []}
 */
class ApiUnusaAcademicProvider implements UnusaAcademicProvider
{
    public function __construct(
        private readonly UnusaApiClient $api,
        private readonly DatabaseUnusaAcademicProvider $lokal,
    ) {}

    public function fakultas(): array
    {
        return $this->lokal->fakultas();
    }

    public function programStudi(?int $fakultasId = null): array
    {
        return $this->lokal->programStudi($fakultasId);
    }

    public function findStaffByEmail(string $email): ?array
    {
        return $this->lokal->findStaffByEmail($email);
    }

    public function cariMahasiswaByNim(string $nim): ?array
    {
        $nim = trim($nim);
        if ($nim === '') {
            return null;
        }

        $ttl = (int) config('unusa.api.cache_ttl_seconds', 300);
        $cacheKey = 'unusa_nim_search_'.hash('sha256', $nim);

        if ($ttl > 0 && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $data = $this->api->get(str_replace('{nim}', rawurlencode($nim), (string) config('unusa.api.path_mahasiswa')));
        } catch (UnusaApiException $e) {
            Log::warning('API UNUSA gagal, memakai data impor CSV: '.$e->getMessage(), ['jenis' => $e->jenis]);

            return $this->lokal->cariMahasiswaByNim($nim);
        }

        if ($data === null) {
            $lokal = $this->lokal->cariMahasiswaByNim($nim);
            if ($ttl > 0 && $lokal !== null) {
                Cache::put($cacheKey, $lokal, $ttl);
            }

            return $lokal;
        }

        $prodi = isset($data['program_studi'])
            ? ProgramStudi::with('fakultas')->where('nama', $data['program_studi'])->orWhere('kode', $data['program_studi'])->first()
            : null;

        $hasil = [
            'nim' => (string) ($data['nim'] ?? $nim),
            'nama_lengkap' => (string) ($data['nama'] ?? ''),
            'email_kampus' => isset($data['email']) ? strtolower((string) $data['email']) : null,
            'program_studi_id' => $prodi?->id,
            'program_studi' => $prodi?->nama ?? ($data['program_studi'] ?? null),
            'fakultas_id' => $prodi?->fakultas_id,
            'fakultas' => $prodi?->fakultas?->nama,
            'angkatan' => isset($data['angkatan']) ? (string) $data['angkatan'] : null,
            'semester' => isset($data['semester']) ? (int) $data['semester'] : null,
            'no_telepon' => isset($data['no_telepon']) ? (string) $data['no_telepon'] : null,
            'prestasi' => is_array($data['prestasi'] ?? null) ? array_values($data['prestasi']) : [],
            'status_aktif' => (string) ($data['status'] ?? 'aktif'),
        ];

        if ($ttl > 0) {
            Cache::put($cacheKey, $hasil, $ttl);
        }

        return $hasil;
    }
}
