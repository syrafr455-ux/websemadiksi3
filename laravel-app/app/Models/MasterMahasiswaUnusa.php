<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master mahasiswa UNUSA. Sementara diisi lewat impor CSV oleh super_admin;
 * nanti digantikan API database UNUSA (lihat App\Contracts\UnusaAcademicProvider).
 */
class MasterMahasiswaUnusa extends Model
{
    protected $table = 'master_mahasiswa_unusa';

    public const STATUS_AKTIF = ['aktif', 'cuti', 'nonaktif', 'lulus', 'keluar'];

    protected $fillable = [
        'nim',
        'nama_lengkap',
        'email_kampus',
        'no_telepon',
        'prestasi',
        'semester',
        'program_studi_id',
        'angkatan',
        'status_aktif',
        'sumber',
        'diperbarui_pada',
    ];

    protected function casts(): array
    {
        return [
            'diperbarui_pada' => 'datetime',
            // Data pribadi: terenkripsi di database.
            'no_telepon' => 'encrypted',
            'prestasi' => 'encrypted:array',
        ];
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }
}
