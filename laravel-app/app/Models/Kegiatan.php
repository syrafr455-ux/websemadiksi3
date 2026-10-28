<?php

namespace App\Models;

use App\Enums\KegiatanStatus;
use App\Enums\UserRole;
use App\Support\Wib;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Kegiatan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kegiatan';

    public const KATEGORI = [
        'webinar',
        'workshop',
        'seminar',
        'pelatihan',
        'pengabdian_masyarakat',
        'lomba',
        'lainnya',
    ];

    public const METODE = ['online', 'offline', 'hybrid'];

    public const SASARAN = ['kipk_only', 'internal_unusa', 'umum'];

    public const LOKASI_TYPES = ['unusa_campus', 'external', 'online'];

    protected $fillable = [
        'nama_kegiatan',
        'deskripsi',
        'kategori',
        'metode_pelaksanaan',
        'sasaran_peserta',
        'tanggal_mulai',
        'tanggal_selesai',
        'registration_deadline',
        'submission_deadline',
        'lokasi_type',
        'lokasi_kampus',
        'lokasi_gedung',
        'lokasi_ruangan',
        'lokasi_nama',
        'lokasi_detail',
        'narasumber',
        'file_panduan_pdf',
        'certificate_template_path',
        'certificate_schema_config',
        'kuota',
        'kuota_terisi',
        'kuota_onsite',
        'poin_xp',
        'biaya',
        'is_seat_booking_active',
        'booking_opens_at',
        'booking_closes_at',
        'venue_change_count',
        'seat_layout_config',
        'is_submission_active',
        'submission_instructions',
        'status',
        'catatan_penolakan',
        'platform_daring',
        'tautan_rapat',
        'meeting_id',
        'passcode_rapat',
        'join_button_mode',
        'menit_buka_gabung',
        'batas_tutup_gabung',
        'is_gabung_manual_open',
        'izin_tautan_presensi_akhir',
        'wajib_tangkapan_layar',
        'presensi_akhir_dibuka',
        'presensi_akhir_mulai',
        'presensi_akhir_selesai',
        'legacy_activity_id',
        'created_by',
    ];

    protected $hidden = [
        'tautan_rapat',
        'meeting_id',
        'passcode_rapat',
    ];

    protected $appends = ['file_panduan_url', 'certificate_template_url'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'datetime',
            'tanggal_selesai' => 'datetime',
            'registration_deadline' => 'datetime',
            'submission_deadline' => 'datetime',
            'booking_opens_at' => 'datetime',
            'booking_closes_at' => 'datetime',
            'batas_tutup_gabung' => 'datetime',
            'tautan_rapat' => 'encrypted',
            'passcode_rapat' => 'encrypted',
            'menit_buka_gabung' => 'integer',
            'is_gabung_manual_open' => 'boolean',
            'izin_tautan_presensi_akhir' => 'boolean',
            'wajib_tangkapan_layar' => 'boolean',
            'presensi_akhir_dibuka' => 'boolean',
            'presensi_akhir_mulai' => 'datetime',
            'presensi_akhir_selesai' => 'datetime',
            'narasumber' => 'array',
            'seat_layout_config' => 'array',
            'certificate_schema_config' => 'array',
            'kuota' => 'integer',
            'kuota_terisi' => 'integer',
            'kuota_onsite' => 'integer',
            'poin_xp' => 'integer',
            'venue_change_count' => 'integer',
            'biaya' => 'decimal:2',
            'is_seat_booking_active' => 'boolean',
            'is_submission_active' => 'boolean',
            'status' => KegiatanStatus::class,
        ];
    }

    /**
     * Cek apakah sesi presensi akhir sedang dibuka dan dalam jendela waktu.
     */
    public function isPresensiAkhirOpen(): bool
    {
        if ($this->status !== KegiatanStatus::Published && $this->status !== KegiatanStatus::Ongoing) {
            return false;
        }

        if (! $this->presensi_akhir_dibuka) {
            return false;
        }

        $now = now();

        if ($this->presensi_akhir_mulai && $now->lt($this->presensi_akhir_mulai)) {
            return false;
        }

        if ($this->presensi_akhir_selesai && $now->gt($this->presensi_akhir_selesai)) {
            return false;
        }

        return true;
    }

    /**
     * Status pembacaan sesi presensi akhir: 'belum_dibuka', 'dibuka', 'ditutup'.
     */
    public function statusPresensiAkhir(): string
    {
        if ($this->isPresensiAkhirOpen()) {
            return 'dibuka';
        }

        if ($this->presensi_akhir_selesai && now()->gt($this->presensi_akhir_selesai)) {
            return 'ditutup';
        }

        if (! $this->presensi_akhir_dibuka && ($this->presensi_akhir_mulai || $this->presensi_akhir_selesai)) {
            return 'ditutup';
        }

        return 'belum_dibuka';
    }

    /**
     * Juri (penilai_karya) yang ditugaskan menilai karya lomba ini.
     */
    public function penilai(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kegiatan_penilai', 'kegiatan_id', 'user_id')->withTimestamps();
    }

    public function seats(): HasMany
    {
        return $this->hasMany(KegiatanSeat::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(KegiatanRegistration::class);
    }

    public function activeRegistrations(): HasMany
    {
        return $this->registrations()->active();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(KegiatanSubmission::class);
    }

    public function presensis(): HasMany
    {
        return $this->hasMany(KegiatanPresensi::class);
    }

    public function sertifikats(): HasMany
    {
        return $this->hasMany(Sertifikat::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function persetujuan(): HasMany
    {
        return $this->hasMany(KegiatanPersetujuan::class, 'kegiatan_id');
    }

    public function gabungs(): HasMany
    {
        return $this->hasMany(KegiatanGabung::class, 'kegiatan_id');
    }

    public function isOnline(): bool
    {
        return $this->metode_pelaksanaan === 'online' || $this->lokasi_type === 'online';
    }

    public function isHybrid(): bool
    {
        return $this->metode_pelaksanaan === 'hybrid';
    }

    public function isDaringOrHybrid(): bool
    {
        return $this->isOnline() || $this->isHybrid();
    }

    public function waktuBukaGabung(): ?CarbonInterface
    {
        if (! $this->tanggal_mulai) {
            return null;
        }
        $menit = (int) ($this->menit_buka_gabung ?? 15);

        return $this->tanggal_mulai->copy()->subMinutes($menit);
    }

    public function waktuTutupGabung(): ?CarbonInterface
    {
        if ($this->batas_tutup_gabung) {
            return $this->batas_tutup_gabung;
        }

        return $this->tanggal_selesai ?? ($this->tanggal_mulai ? $this->tanggal_mulai->copy()->addHours(2) : null);
    }

    public function isJoinWindowOpen(): bool
    {
        if ($this->status !== KegiatanStatus::Published && $this->status !== KegiatanStatus::Ongoing) {
            return false;
        }

        // Override manual jika disetel
        if ($this->is_gabung_manual_open === true) {
            return true;
        }
        if ($this->is_gabung_manual_open === false && $this->join_button_mode === 'manual') {
            return false;
        }
        if ($this->is_gabung_manual_open === false && $this->join_button_mode === 'otomatis') {
            return false;
        }

        // Mode manual tanpa override true -> tertutup
        if ($this->join_button_mode === 'manual') {
            return false;
        }

        // Mode otomatis (is_gabung_manual_open null) -> cek jadwal
        $now = now();
        $buka = $this->waktuBukaGabung();
        $tutup = $this->waktuTutupGabung();

        if ($buka && $now->lt($buka)) {
            return false;
        }
        if ($tutup && $now->gt($tutup)) {
            return false;
        }

        return true;
    }

    public function statusAksesGabung(): string
    {
        if ($this->isJoinWindowOpen()) {
            return 'dibuka';
        }

        $now = now();
        $buka = $this->waktuBukaGabung();
        if ($buka && $now->lt($buka) && ($this->join_button_mode === 'otomatis' || $this->is_gabung_manual_open === null)) {
            return 'belum_dibuka';
        }

        return 'ditutup';
    }

    /**
     * Penyetuju wajib: SEMUA super admin aktif (tidak diblokir) selain pembuat,
     * dihitung ulang saat pengecekan. Pembuat otomatis dianggap setuju.
     *
     * @return Collection<int, User>
     */
    public function penyetujuWajib()
    {
        $query = User::where('role', UserRole::SuperAdmin)->where('is_blocked', false);
        if ($this->created_by) {
            $query->where('id', '!=', $this->created_by);
        }

        return $query->get();
    }

    /**
     * Sidik jari data versi saat ini yang disetujui.
     */
    public function sidikJariData(): string
    {
        return hash('sha256', json_encode([
            'nama_kegiatan' => $this->nama_kegiatan,
            'tanggal_mulai' => $this->tanggal_mulai?->toISOString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toISOString(),
            'registration_deadline' => $this->registration_deadline?->toISOString(),
            'biaya' => (float) $this->biaya,
            'kuota' => (int) $this->kuota,
            'metode_pelaksanaan' => $this->metode_pelaksanaan,
            'sasaran_peserta' => $this->sasaran_peserta,
            'lokasi_nama' => $this->lokasi_nama,
        ]));
    }

    /**
     * Periksa apakah seluruh penyetuju wajib telah menyetujui kegiatan ini.
     * Kegiatan otomatis menjadi terbit ketika semua penyetuju wajib sudah setuju.
     * Bila tidak ada super admin lain yang aktif, kegiatan tidak bisa terbit.
     */
    public function cekDanPerbaruiStatusPersetujuan(): bool
    {
        $penyetujuWajib = $this->penyetujuWajib();
        if ($penyetujuWajib->isEmpty()) {
            return false;
        }

        $setujuCount = $this->persetujuan()
            ->whereIn('user_id', $penyetujuWajib->pluck('id'))
            ->where('keputusan', 'setuju')
            ->count();

        if ($setujuCount === $penyetujuWajib->count()) {
            $this->status = KegiatanStatus::Published;
            $this->save();

            AuditLog::record(
                null,
                'publish_kegiatan_otomatis',
                "Kegiatan '{$this->nama_kegiatan}' (ID: {$this->id}) otomatis terbit setelah disetujui oleh seluruh super admin aktif",
                $this
            );

            return true;
        }

        return false;
    }

    /**
     * URL publik file panduan PDF (null untuk data lama tanpa panduan).
     */
    public function getFilePanduanUrlAttribute(): ?string
    {
        return $this->file_panduan_pdf
            ? Storage::disk('public')->url($this->file_panduan_pdf)
            : null;
    }

    /**
     * URL publik file template sertifikat (jika diunggah).
     */
    public function getCertificateTemplateUrlAttribute(): ?string
    {
        return $this->certificate_template_path
            ? Storage::disk('public')->url($this->certificate_template_path)
            : null;
    }

    public function getTitleAttribute(): string
    {
        return $this->nama_kegiatan;
    }

    public function getDateTextAttribute(): string
    {
        return $this->tanggal_mulai ? Wib::dari($this->tanggal_mulai)->translatedFormat('d F Y') : '-';
    }

    public function isFree(): bool
    {
        return (float) $this->biaya <= 0.0;
    }

    public function isLomba(): bool
    {
        return strtolower((string) $this->kategori) === 'lomba';
    }

    public function acceptsSubmissions(): bool
    {
        return $this->is_submission_active && $this->status !== KegiatanStatus::Cancelled;
    }

    /**
     * Cek apakah pendaftaran/tiket masih dibuka berdasarkan status dan batas waktu registrasi.
     */
    public function isRegistrationOpen(): bool
    {
        if ($this->status !== KegiatanStatus::Published && $this->status !== KegiatanStatus::Ongoing) {
            return false;
        }

        // Tertutup tepat saat deadline (deadline eksklusif).
        if ($this->registration_deadline && now()->greaterThanOrEqualTo($this->registration_deadline)) {
            return false;
        }

        return true;
    }

    /**
     * Cek apakah pengumpulan karya masih dibuka berdasarkan batas waktu submission.
     */
    public function isSubmissionOpen(): bool
    {
        if (! $this->acceptsSubmissions()) {
            return false;
        }

        // Tertutup tepat saat deadline (deadline eksklusif).
        if ($this->submission_deadline && now()->greaterThanOrEqualTo($this->submission_deadline)) {
            return false;
        }

        return true;
    }

    /**
     * Apakah kegiatan ini menggunakan denah kursi interaktif?
     */
    public function hasSeatLayout(): bool
    {
        return (bool) $this->is_seat_booking_active || $this->seats()->exists();
    }

    /**
     * Single source of truth untuk jadwal dan status aktif booking kursi.
     * HANYA gate untuk kegiatan yang memakai denah kursi.
     * True hanya bila is_seat_booking_active = true DAN waktu sekarang berada di dalam jendela
     * [booking_opens_at, booking_closes_at): dibuka tepat saat booking_opens_at, tertutup tepat saat booking_closes_at.
     * Batas yang null dianggap tidak membatasi.
     */
    public function isSeatBookingOpen(): bool
    {
        if ($this->status === KegiatanStatus::Draft || $this->status === KegiatanStatus::Cancelled) {
            return false;
        }

        return $this->statusBookingKursi() === self::BOOKING_DIBUKA;
    }

    public const BOOKING_BELUM_DIBUKA = 'belum_dibuka';

    public const BOOKING_DIBUKA = 'dibuka';

    public const BOOKING_DITUTUP = 'ditutup';

    public const BOOKING_DIBEKUKAN = 'dibekukan';

    /**
     * Status jendela booking kursi: dibekukan (booking dinonaktifkan admin; kursi yang ada tetap tersimpan),
     * belum_dibuka (sebelum booking_opens_at), ditutup (tepat/sesudah booking_closes_at), atau dibuka.
     */
    public function statusBookingKursi(): string
    {
        if (! (bool) $this->is_seat_booking_active) {
            return self::BOOKING_DIBEKUKAN;
        }

        $now = now();

        if ($this->booking_opens_at && $now->lt($this->booking_opens_at)) {
            return self::BOOKING_BELUM_DIBUKA;
        }

        if ($this->booking_closes_at && $now->gte($this->booking_closes_at)) {
            return self::BOOKING_DITUTUP;
        }

        return self::BOOKING_DIBUKA;
    }

    public function scopeBookable($query)
    {
        return $query->whereIn('status', [KegiatanStatus::Published->value, KegiatanStatus::Ongoing->value]);
    }

    /**
     * Apakah $user (sebagai peserta) memenuhi sasaran kegiatan ini?
     */
    public function allowsParticipant(User $user): bool
    {
        $role = $user->role;

        if (! $role instanceof UserRole || ! $role->isParticipant() || $user->is_blocked) {
            return false;
        }

        return match ($this->sasaran_peserta) {
            'kipk_only' => $role === UserRole::MahasiswaKipk && $user->isVerified(),
            'internal_unusa' => $role === UserRole::MahasiswaKipk || $user->hasUnusaEmail(),
            default => true,
        };
    }

    /**
     * Daftar field yang, jika berubah, wajib dibroadcast ke peserta.
     *
     * @return list<string>
     */
    public static function scheduleSensitiveFields(): array
    {
        return ['tanggal_mulai', 'tanggal_selesai', 'lokasi_type', 'lokasi_nama', 'lokasi_detail', 'metode_pelaksanaan'];
    }

    public function getWaktuMulaiAttribute()
    {
        return $this->tanggal_mulai;
    }

    public function setWaktuMulaiAttribute($value): void
    {
        $this->attributes['tanggal_mulai'] = $value;
    }

    public function getWaktuSelesaiAttribute()
    {
        return $this->tanggal_selesai;
    }

    public function setWaktuSelesaiAttribute($value): void
    {
        $this->attributes['tanggal_selesai'] = $value;
    }

    public function getTargetPesertaAttribute()
    {
        return $this->sasaran_peserta;
    }

    public function setTargetPesertaAttribute($value): void
    {
        $this->attributes['sasaran_peserta'] = $value;
    }

    public function getKuotaTotalAttribute()
    {
        return $this->kuota;
    }

    public function setKuotaTotalAttribute($value): void
    {
        $this->attributes['kuota'] = $value;
    }

    public function getPoinSkpAttribute()
    {
        return $this->poin_xp;
    }

    public function setPoinSkpAttribute($value): void
    {
        $this->attributes['poin_xp'] = $value;
    }

    public function getHasSeatSelectionAttribute(): bool
    {
        return (bool) $this->is_seat_booking_active;
    }

    public function setHasSeatSelectionAttribute($value): void
    {
        $this->attributes['is_seat_booking_active'] = (bool) $value;
    }

    public function getHasSubmissionAttribute(): bool
    {
        return (bool) $this->is_submission_active;
    }

    public function setHasSubmissionAttribute($value): void
    {
        $this->attributes['is_submission_active'] = (bool) $value;
    }

    public function getKuotaTerisiAttribute(): int
    {
        return $this->registrations()->count();
    }
}
