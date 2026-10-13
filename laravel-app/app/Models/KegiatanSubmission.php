<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class KegiatanSubmission extends Model
{
    protected $table = 'kegiatan_submissions';

    protected $fillable = [
        'kegiatan_id',
        'registration_id',
        'nama_kelompok_peserta',
        'title',
        'file_karya_url',
        'file_url',
        'nilai',
        'final_score',
        'catatan_juri',
        'judge_notes',
        'judged_by',
        'graded_by',
        'judged_at',
        'graded_at',
        'status',
    ];

    protected $appends = ['title', 'file_url', 'final_score', 'judge_notes', 'status', 'graded_by'];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'judged_at' => 'datetime',
        ];
    }

    public function getTitleAttribute()
    {
        return $this->nama_kelompok_peserta;
    }

    public function setTitleAttribute($val): void
    {
        $this->attributes['nama_kelompok_peserta'] = $val;
    }

    public function getFileUrlAttribute()
    {
        return $this->file_karya_url;
    }

    public function setFileUrlAttribute($val): void
    {
        $this->attributes['file_karya_url'] = $val;
    }

    public function getFinalScoreAttribute()
    {
        return $this->nilai;
    }

    public function setFinalScoreAttribute($val): void
    {
        $this->attributes['nilai'] = $val;
    }

    public function getJudgeNotesAttribute()
    {
        return $this->catatan_juri;
    }

    public function setJudgeNotesAttribute($val): void
    {
        $this->attributes['catatan_juri'] = $val;
    }

    public function getGradedByAttribute()
    {
        return $this->judge?->name;
    }

    public function setGradedByAttribute($val): void
    {
        if (is_numeric($val)) {
            $this->attributes['judged_by'] = (int) $val;
        } elseif (is_string($val) && auth()->check()) {
            $this->attributes['judged_by'] = auth()->id();
        }
    }

    public function getGradedAtAttribute()
    {
        return $this->judged_at;
    }

    public function setGradedAtAttribute($val): void
    {
        $this->attributes['judged_at'] = $val;
    }

    public function getStatusAttribute()
    {
        return $this->nilai !== null ? 'graded' : 'submitted';
    }

    public function setStatusAttribute($val): void
    {
        // virtual status handled by nilai
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class)->withTrashed();
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KegiatanRegistration::class, 'registration_id');
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judged_by');
    }

    public function isJudged(): bool
    {
        return $this->nilai !== null;
    }

    /**
     * URL yang dapat dibuka juri: link eksternal apa adanya, file lokal lewat disk publik.
     */
    public function previewUrl(): string
    {
        if (preg_match('#^https?://#i', $this->file_karya_url) === 1) {
            return $this->file_karya_url;
        }

        return Storage::disk('public')->url($this->file_karya_url);
    }
}
