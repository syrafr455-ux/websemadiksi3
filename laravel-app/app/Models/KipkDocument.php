<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KipkDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'user_nim',
        'category',
        'tahap',
        'title',
        'file_name',
        'file_size',
        'file_type',
        'file_url',
        'uploaded_by',
        'status',
        'score',
        'notes',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'tahap' => 'integer',
            'score' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Berkas yang boleh dilihat $viewer: super_admin semua, verifikator sesuai cakupan
     * fakultas/prodi mahasiswa pemilik berkas, selain itu hanya berkas miliknya sendiri.
     */
    public function scopeTerlihatOleh(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        if ($viewer->isVerifikator()) {
            return $query->whereHas('user', fn (Builder $u) => $u->dalamCakupan($viewer));
        }

        return $query->where('user_id', $viewer->id);
    }
}
