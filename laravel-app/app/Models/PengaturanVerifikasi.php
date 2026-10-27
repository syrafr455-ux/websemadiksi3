<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanVerifikasi extends Model
{
    protected $table = 'pengaturan_verifikasi';

    protected $fillable = [
        'tampilkan_asal_institusi',
        'tampilkan_jenis_predikat',
        'tampilkan_nim_samar',
        'tampilkan_penyelenggara',
        'tampilkan_keterangan',
        'tampilkan_tabel_penandatangan',
        'teks_pengantar',
        'updated_by',
    ];

    protected $casts = [
        'tampilkan_asal_institusi' => 'boolean',
        'tampilkan_jenis_predikat' => 'boolean',
        'tampilkan_nim_samar' => 'boolean',
        'tampilkan_penyelenggara' => 'boolean',
        'tampilkan_keterangan' => 'boolean',
        'tampilkan_tabel_penandatangan' => 'boolean',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function ambil(): self
    {
        return static::firstOrCreate([], [
            'tampilkan_asal_institusi' => true,
            'tampilkan_jenis_predikat' => true,
            'tampilkan_nim_samar' => true,
            'tampilkan_penyelenggara' => true,
            'tampilkan_keterangan' => true,
            'tampilkan_tabel_penandatangan' => true,
            'teks_pengantar' => 'Halaman ini memvalidasi keaslian sertifikat resmi yang diterbitkan oleh sistem SEMADIKSI Universitas Nahdlatul Ulama Surabaya.',
        ]);
    }
}
