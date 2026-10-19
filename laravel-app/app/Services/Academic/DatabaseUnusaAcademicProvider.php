<?php

namespace App\Services\Academic;

use App\Contracts\UnusaAcademicProvider;
use App\Enums\UserRole;
use App\Models\Fakultas;
use App\Models\MasterMahasiswaUnusa;
use App\Models\ProgramStudi;
use App\Models\User;

/**
 * IMPLEMENTASI SEMENTARA: membaca master fakultas/prodi, master_mahasiswa_unusa (impor CSV),
 * dan akun staf dari database lokal.
 * Diganti provider API UNUSA saat tersedia (lihat App\Contracts\UnusaAcademicProvider).
 */
class DatabaseUnusaAcademicProvider implements UnusaAcademicProvider
{
    public function fakultas(): array
    {
        return Fakultas::orderBy('nama')
            ->get(['id', 'kode', 'nama'])
            ->map(fn (Fakultas $f): array => ['id' => $f->id, 'kode' => $f->kode, 'nama' => $f->nama])
            ->all();
    }

    public function programStudi(?int $fakultasId = null): array
    {
        return ProgramStudi::query()
            ->when($fakultasId, fn ($q) => $q->where('fakultas_id', $fakultasId))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'jenjang', 'fakultas_id'])
            ->map(fn (ProgramStudi $p): array => [
                'id' => $p->id,
                'kode' => $p->kode,
                'nama' => $p->nama,
                'jenjang' => $p->jenjang,
                'fakultas_id' => $p->fakultas_id,
            ])
            ->all();
    }

    public function cariMahasiswaByNim(string $nim): ?array
    {
        $mahasiswa = MasterMahasiswaUnusa::with('programStudi.fakultas')->where('nim', trim($nim))->first();

        return $mahasiswa ? [
            'nim' => $mahasiswa->nim,
            'nama_lengkap' => $mahasiswa->nama_lengkap,
            'email_kampus' => $mahasiswa->email_kampus,
            'program_studi_id' => $mahasiswa->program_studi_id,
            'program_studi' => $mahasiswa->programStudi?->nama,
            'fakultas_id' => $mahasiswa->programStudi?->fakultas_id,
            'fakultas' => $mahasiswa->programStudi?->fakultas?->nama,
            'angkatan' => $mahasiswa->angkatan,
            'status_aktif' => $mahasiswa->status_aktif,
        ] : null;
    }

    public function findStaffByEmail(string $email): ?array
    {
        $user = User::where('email', strtolower(trim($email)))
            ->whereIn('role', [UserRole::SuperAdmin->value, UserRole::VerifikatorBerkas->value, UserRole::PenilaiKarya->value])
            ->first();

        return $user ? [
            'email' => $user->email,
            'nama' => $user->name,
            'fakultas_id' => $user->fakultas_id,
            'program_studi_id' => $user->program_studi_id,
        ] : null;
    }
}
