<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KegiatanGabung extends Model
{
    protected $table = 'kegiatan_gabung';

    protected $fillable = [
        'kegiatan_id',
        'registration_id',
        'user_id',
        'pertama_pada',
        'terakhir_pada',
        'jumlah',
    ];

    protected function casts(): array
    {
        return [
            'pertama_pada' => 'datetime',
            'terakhir_pada' => 'datetime',
            'jumlah' => 'integer',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KegiatanRegistration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
