<?php

namespace App\Models;

use App\Enums\PeranSertifikat;
use App\Support\Wib;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Sertifikat extends Model
{
    protected $table = 'sertifikat';

    protected $fillable = [
        'nomor_sertifikat',
        'kegiatan_id',
        'user_id',
        'nama_penerima',
        'email_penerima',
        'presensi_id',
        'peran_sertifikat',
        'file_pdf_path',
        'document_hash',
        'is_valid',
        'issued_at',
    ];

    protected $appends = ['file_url', 'verification_url', 'peran_label', 'recipient_name', 'recipient_email'];

    protected function casts(): array
    {
        return [
            'peran_sertifikat' => PeranSertifikat::class,
            'is_valid' => 'boolean',
            'issued_at' => 'datetime',
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

    public function presensi(): BelongsTo
    {
        return $this->belongsTo(KegiatanPresensi::class, 'presensi_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (empty($this->file_pdf_path)) {
            return null;
        }

        if (preg_match('#^https?://#i', $this->file_pdf_path) === 1) {
            return $this->file_pdf_path;
        }

        return Storage::disk('public')->url($this->file_pdf_path);
    }

    public function getVerificationUrlAttribute(): string
    {
        return url('/verifikasi?code='.$this->nomor_sertifikat);
    }

    public function getPeranLabelAttribute(): string
    {
        return $this->peran_sertifikat?->label() ?? 'Peserta';
    }

    public function getRecipientNameAttribute(): string
    {
        return $this->nama_penerima ?: ($this->user?->name ?? 'Peserta Terdaftar');
    }

    public function getRecipientEmailAttribute(): string
    {
        return $this->email_penerima ?: ($this->user?->email ?? '-');
    }

    public function verifyDocumentHash(): bool
    {
        if (empty($this->file_pdf_path) || ! Storage::disk('public')->exists($this->file_pdf_path)) {
            return false;
        }

        $realPath = Storage::disk('public')->path($this->file_pdf_path);

        return hash_file('sha256', $realPath) === $this->document_hash;
    }

    /**
     * Format: CERT-{KODE_KEGIATAN}-{TAHUN}-{RANDOM}
     * Contoh: CERT-WKS05-2026-9A3K1Z
     */
    public static function generateNomorSertifikat(int|string $kegiatanCode, ?int $year = null): string
    {
        // Tahun terbit menurut WIB: sertifikat 1 Januari 00:30 WIB sudah memakai tahun baru.
        $year = $year ?: Wib::sekarang()->year;
        $cleanCode = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', (string) $kegiatanCode), 0, 8)) ?: 'EVT';

        do {
            $random = strtoupper(Str::random(6));
            $number = sprintf('CERT-%s-%d-%s', $cleanCode, $year, $random);
        } while (static::query()->where('nomor_sertifikat', $number)->exists());

        return $number;
    }

    public static function generateCertificateNumber(Kegiatan|int|string $kegiatan, ?int $year = null): string
    {
        $code = $kegiatan instanceof Kegiatan
            ? ($kegiatan->kategori ?: 'SMD').$kegiatan->id
            : (string) $kegiatan;

        return static::generateNomorSertifikat($code, $year);
    }
}
