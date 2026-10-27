<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SertifikatTemplat extends Model
{
    protected $table = 'sertifikat_templat';

    /** Kolom yang dipakai kode templat; dicocokkan dengan skema oleh tes (Schema::hasColumn). */
    public const KOLOM = ['id', 'kegiatan_id', 'jenis', 'orientasi', 'latar_path', 'layout', 'teks', 'versi', 'diubah_oleh'];

    protected $fillable = ['kegiatan_id', 'jenis', 'orientasi', 'latar_path', 'layout', 'teks', 'versi', 'diubah_oleh'];

    protected function casts(): array
    {
        return ['layout' => 'array', 'teks' => 'array', 'versi' => 'integer'];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }
}
