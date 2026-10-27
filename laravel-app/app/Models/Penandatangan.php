<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Penandatangan extends Model
{
    protected $table = 'penandatangan';

    protected $fillable = [
        'nama',
        'gelar',
        'jabatan',
        'jenis_identitas',
        'nomor_identitas',
        'institusi',
        'tanda_tangan_path',
        'mode_tampil', // sembunyikan, samarkan, penuh
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'nama_lengkap',
        'identitas_terformat',
    ];

    public function kegiatan(): BelongsToMany
    {
        return $this->belongsToMany(Kegiatan::class, 'kegiatan_penandatangan')
            ->withPivot(['jenis_sertifikat', 'urutan'])
            ->withTimestamps();
    }

    public function getNamaLengkapAttribute(): string
    {
        return $this->gelar ? "{$this->nama}, {$this->gelar}" : $this->nama;
    }

    public function getIdentitasTerformatAttribute(): ?string
    {
        if (empty($this->nomor_identitas) || $this->jenis_identitas === 'tanpa' || $this->mode_tampil === 'sembunyikan') {
            return null;
        }

        $jenis = strtoupper($this->jenis_identitas);

        if ($this->mode_tampil === 'samarkan') {
            $nomor = trim($this->nomor_identitas);
            $panjang = strlen($nomor);
            if ($panjang <= 6) {
                $nomorSamar = substr($nomor, 0, 1).'****'.substr($nomor, -1);
            } else {
                $nomorSamar = substr($nomor, 0, 4).'****'.substr($nomor, -4);
            }

            return "{$jenis}: {$nomorSamar}";
        }

        return "{$jenis}: {$this->nomor_identitas}";
    }
}
