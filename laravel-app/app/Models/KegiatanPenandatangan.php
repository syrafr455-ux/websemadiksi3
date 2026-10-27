<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KegiatanPenandatangan extends Model
{
    protected $table = 'kegiatan_penandatangan';

    protected $fillable = [
        'kegiatan_id',
        'penandatangan_id',
        'jenis_sertifikat',
        'urutan',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function penandatangan(): BelongsTo
    {
        return $this->belongsTo(Penandatangan::class);
    }
}
