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
     * verifikator hanya formulir di config('unusa.verifikator_form_keys') dari mahasiswa dalam cakupan fakultas/prodinya.
     */
    public function scopeTerlihatOleh(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        if ($viewer->isVerifikator()) {
            return $query
                ->whereHas('form', fn (Builder $f) => $f->whereIn('form_key', self::formVerifikator()))
                ->whereHas('user', fn (Builder $u) => $u->dalamCakupan($viewer));
        }

        return $query->where('user_id', $viewer->id);
    }

    /**
     * Form key yang hanya boleh diproses super_admin, apa pun isi config.
     */
    public const FORM_KHUSUS_SUPER_ADMIN = ['pencairan'];

    /**
     * Form key yang diproses verifikator berkas (selain berkas KIP-K).
     *
     * @return list<string>
     */
    public static function formVerifikator(): array
    {
        return array_values(array_diff(
            (array) config('unusa.verifikator_form_keys', ['monev', 'pelaporan']),
            self::FORM_KHUSUS_SUPER_ADMIN
        ));
    }
}
