<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class KegiatanPresensi extends Model
{
    protected $table = 'kegiatan_presensi';

    protected $fillable = [
        'kegiatan_id',
        'user_id',
        'registration_id',
        'nama_lengkap',
        'nim',
        'prodi',
        'email',
        'nomor_telepon',
        'signature_path',
        'check_in_at',
    ];

    protected $appends = ['signature_url'];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KegiatanRegistration::class, 'registration_id');
    }

    public function sertifikat(): HasOne
    {
        return $this->hasOne(Sertifikat::class, 'presensi_id');
    }

    public function getSignatureUrlAttribute(): ?string
    {
        if (empty($this->signature_path)) {
            return null;
        }

        if (preg_match('#^https?://#i', $this->signature_path) === 1) {
            return $this->signature_path;
        }

        return Storage::disk('public')->url($this->signature_path);
    }
}
