<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_form_id',
        'user_id',
        'applicant_name',
        'applicant_nim',
        'applicant_email',
        'answers',
        'status',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Formulir yang boleh dilihat $viewer di panel admin: super_admin semua formulir;
     * verifikator hanya formulir monev dari mahasiswa dalam cakupan fakultas/prodinya.
     */
    public function scopeTerlihatOleh(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        if ($viewer->isVerifikator()) {
            return $query
                ->whereHas('form', fn (Builder $f) => $f->where('form_key', self::FORM_VERIFIKATOR))
                ->whereHas('user', fn (Builder $u) => $u->dalamCakupan($viewer));
        }

        return $query->where('user_id', $viewer->id);
    }

    /**
     * Form key yang diverifikasi oleh verifikator berkas (selain berkas KIP-K).
     */
    public const FORM_VERIFIKATOR = 'monev';
}
