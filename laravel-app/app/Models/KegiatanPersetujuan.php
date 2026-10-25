<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KegiatanPersetujuan extends Model
{
    use HasFactory;

    protected $table = 'kegiatan_persetujuan';

    protected $fillable = [
        'kegiatan_id',
        'user_id',
        'keputusan',
        'catatan',
        'data_version',
        'ip_address',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
