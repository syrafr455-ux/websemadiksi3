<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SertifikatRiwayatPerubahan extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sertifikat_riwayat_perubahan';

    protected $fillable = [
        'sertifikat_id',
        'user_id',
        'ip_address',
        'kolom_diubah',
        'nilai_lama',
        'nilai_baru',
        'alasan',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function sertifikat(): BelongsTo
    {
        return $this->belongsTo(Sertifikat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
