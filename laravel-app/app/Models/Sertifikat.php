<?php

namespace App\Models;

use App\Enums\JenisSertifikat;
use App\Enums\PeranSertifikat;
use App\Enums\StatusSertifikat;
use App\Support\Wib;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Sertifikat extends Model
{
    protected $table = 'sertifikat';

    protected $fillable = [
        'nomor_sertifikat',
        'nomor_urut',
        'kegiatan_id',
        'registration_id',
        'user_id',
        'penerima_key',
        'nama_penerima',
        'email_penerima',
        'asal_institusi',
        'nim_penerima',
        'presensi_id',
        'peran_sertifikat',
        'jenis',
        'predikat',
        'keterangan_publik',
        'status',
        'alasan_pencabutan',
        'dicabut_pada',
        'dicabut_oleh',
        'penandatangan_snapshot',
        'file_pdf_path',
        'document_hash',
        'is_valid',
        'issued_at',
    ];

    protected $appends = [
        'file_url',
        'verification_url',
        'peran_label',
        'jenis_label',
        'status_label',
        'recipient_name',
        'recipient_email',
        'institution_name',
    ];

    protected function casts(): array
    {
        return [
            'peran_sertifikat' => PeranSertifikat::class,
            'jenis' => JenisSertifikat::class,
            'status' => StatusSertifikat::class,
            'penandatangan_snapshot' => 'array',
            'is_valid' => 'boolean',
            'issued_at' => 'datetime',
            'dicabut_pada' => 'datetime',
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

    public function presensi(): BelongsTo
    {
        return $this->belongsTo(KegiatanPresensi::class, 'presensi_id');
    }

    public function dicabutOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicabut_oleh');
    }

    public function riwayatPerubahan(): HasMany
    {
        return $this->hasMany(SertifikatRiwayatPerubahan::class, 'sertifikat_id')->latest();
    }

    public function isAktif(): bool
    {
        return $this->status === StatusSertifikat::Aktif || $this->status === 'aktif';
    }

    public function isDicabut(): bool
    {
        return $this->status === StatusSertifikat::Dicabut || $this->status === 'dicabut';
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
        if ($this->jenis instanceof JenisSertifikat) {
            return $this->jenis->label();
        }

        return $this->peran_sertifikat?->label() ?? 'Peserta';
    }

    public function getJenisLabelAttribute(): string
    {
        if ($this->jenis instanceof JenisSertifikat) {
            return $this->jenis->label();
        }

        return ucfirst($this->jenis ?? 'Peserta');
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->status instanceof StatusSertifikat) {
            return $this->status->label();
        }

        return $this->status === 'dicabut' ? 'Dicabut' : 'Sah';
    }

    public function getRecipientNameAttribute(): string
    {
        return $this->nama_penerima ?: ($this->user?->name ?? 'Peserta Terdaftar');
    }

    public function getRecipientEmailAttribute(): ?string
    {
        return $this->email_penerima ?: ($this->user?->email ?? null);
    }

    public function getInstitutionNameAttribute(): string
    {
        return $this->asal_institusi ?: 'Universitas Nahdlatul Ulama Surabaya';
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
     * Hitung kunci penerima unik untuk mencegah duplikasi:
     * - Jika ada akun (user_id): "user:{id}"
     * - Jika tanpa akun (luar): "ext:" . sha256(nama_dinormalisasi . "|" . institusi_dinormalisasi)
     */
    public static function hitungPenerimaKey(?int $userId, ?string $nama, ?string $institusi): string
    {
        if ($userId) {
            return 'user:'.$userId;
        }

        $namaNorm = Str::lower(trim($nama ?? ''));
        $instNorm = Str::lower(trim($institusi ?? ''));

        return 'ext:'.hash('sha256', "{$namaNorm}|{$instNorm}");
    }

    /**
     * Format: CERT-{KODE_KEGIATAN}-{TAHUN}-{NOMOR}
     * Contoh: CERT-WKS05-2026-9A3K1Z
     */
    public static function generateNomorSertifikat(int|string $kegiatanCode, ?int $year = null): string
    {
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

    /**
     * Penomoran seri atomik per kegiatan dengan format CERT-{KODE}-{TAHUN}-{NOMOR}
     * Menghasilkan nomor urut sekuensial yang aman saat penerbitan massal serentak.
     */
    public static function generateNomorSertifikatAtomik(Kegiatan $kegiatan, ?int $year = null): array
    {
        $year = $year ?: Wib::sekarang()->year;
        $kategori = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $kegiatan->kategori ?: 'SMD'), 0, 4)) ?: 'SMD';
        $code = $kategori.$kegiatan->id;

        // Ambil nomor urut tertinggi saat ini untuk kegiatan ini
        $maxUrut = static::query()->where('kegiatan_id', $kegiatan->id)->max('nomor_urut') ?? 0;
        $nomorUrut = $maxUrut + 1;

        do {
            $nomorStr = sprintf('%04d', $nomorUrut);
            $nomorSertifikat = sprintf('CERT-%s-%d-%s', $code, $year, $nomorStr);
            $ada = static::query()->where('nomor_sertifikat', $nomorSertifikat)->exists();
            if ($ada) {
                $nomorUrut++;
            }
        } while ($ada);

        return [
            'nomor_sertifikat' => $nomorSertifikat,
            'nomor_urut' => $nomorUrut,
        ];
    }
}
