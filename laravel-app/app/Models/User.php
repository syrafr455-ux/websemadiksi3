<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Mail\ResetKataSandiMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

/**
 * Verifikasi email hanya diwajibkan untuk pendaftaran mandiri jalur Peserta Umum
 * (lihat wajibVerifikasiEmail() dan middleware EnsureEmailVerifiedForJalurUmum).
 */
class User extends Authenticatable implements MustVerifyEmail
{
    public const JALUR_UNUSA = 'unusa';

    public const JALUR_UMUM = 'umum';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'nim',
        'phone',
        'angkatan',
        'prodi',
        'university',
        'kip_status',
        'verification_status',
        'avatar_url',
        'role',
        'is_blocked',
        'password',
        'fakultas_id',
        'program_studi_id',
        'asal_kampus',
        'program_studi_teks',
        'verifikator_level',
        'jalur_pendaftaran',
        'is_unusa',
        'keycloak_sub',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
    ];

    /**
     * Akun mahasiswa_kipk selalu akun UNUSA (NIM-nya wajib unik, lihat indeks users_nim_unusa_unique).
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->role === UserRole::MahasiswaKipk) {
                $user->is_unusa = true;
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
            'is_unusa' => 'boolean',
            'role' => UserRole::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Cek apakah role pengguna mewajibkan 2FA saat login kata sandi.
     */
    public function requiresTwoFactor(): bool
    {
        $roleStr = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;
        $configuredRoles = (array) config('unusa.two_factor_roles', ['user_umum', 'penilai_karya']);

        return in_array($roleStr, $configuredRoles, true);
    }

    /**
     * Cek apakah 2FA sudah aktif dan dikonfirmasi.
     */
    public function hasEnabledTwoFactor(): bool
    {
        return ! empty($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Super admin (nama lama dipertahankan untuk kompatibilitas kode lama).
     */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    /**
     * Role pengelola: super_admin, verifikator_berkas, penilai_karya.
     */
    public function isStaff(): bool
    {
        return $this->role instanceof UserRole && $this->role->isStaff();
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        if (! $this->role instanceof UserRole) {
            return false;
        }

        foreach ($roles as $role) {
            $value = $role instanceof UserRole ? $role->value : $role;

            if ($this->role->value === $value) {
                return true;
            }
        }

        return false;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'Verified';
    }

    public function hasUnusaEmail(): bool
    {
        return preg_match('/@(student\.)?unusa\.ac\.id$/i', (string) $this->email) === 1;
    }

    public function bookedRegistrations(): HasMany
    {
        return $this->hasMany(KegiatanRegistration::class, 'booker_user_id');
    }

    public function participantRegistrations(): HasMany
    {
        return $this->hasMany(KegiatanRegistration::class, 'participant_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activityRegistrations(): HasMany
    {
        return $this->hasMany(ActivityRegistration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function kipkDocuments(): HasMany
    {
        return $this->hasMany(KipkDocument::class);
    }

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(CustomFormSubmission::class);
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class, 'fakultas_id');
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    /**
     * Pendaftar mandiri jalur Peserta Umum wajib memverifikasi email sebelum memakai dashboard.
     * Akun admin/seeder/jalur UNUSA (sudah terbukti lewat email kampus) tidak terkena aturan ini.
     */
    public function wajibVerifikasiEmail(): bool
    {
        return $this->jalur_pendaftaran === self::JALUR_UMUM && ! $this->hasVerifiedEmail();
    }

    /**
     * Link atur ulang kata sandi dikirim ke email yang tercatat di akun (dipanggil password broker).
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);
        $berlaku = (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);

        Mail::to($this->email)->send(new ResetKataSandiMail($this->name, $url, $berlaku));
    }

    /**
     * Akun user_umum milik mahasiswa UNUSA (penanda is_unusa; boleh mengklaim status KIP-K).
     */
    public function isUmumUnusa(): bool
    {
        return $this->role === UserRole::UserUmum && (bool) $this->is_unusa;
    }

    /**
     * Baris daftar penerima KIP-K yang terhubung ke akun ini.
     */
    public function penerimaKipk(): HasOne
    {
        return $this->hasOne(PenerimaKipk::class, 'user_id');
    }

    /**
     * Baris daftar penerima yang sedang diklaim akun ini (menunggu tinjauan admin).
     */
    public function klaimKipk(): HasOne
    {
        return $this->hasOne(PenerimaKipk::class, 'klaim_user_id');
    }

    public function isVerifikator(): bool
    {
        return $this->role === UserRole::VerifikatorBerkas;
    }

    /**
     * Apakah pengguna ini (super_admin / verifikator) boleh melihat & memproses data milik $mahasiswa.
     *
     * - super_admin: semua mahasiswa.
     * - Admin Prodi: mahasiswa dengan program_studi_id yang sama.
     * - Admin Fakultas: mahasiswa di semua prodi dalam fakultasnya.
     * - Mahasiswa tanpa program_studi_id hanya terlihat oleh super_admin.
     * Aturan yang sama dipakai scopeDalamCakupan() untuk query daftar.
     */
    public function mencakupMahasiswa(?User $mahasiswa): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->isVerifikator() || ! $mahasiswa || ! $mahasiswa->program_studi_id) {
            return false;
        }

        return match ($this->verifikator_level) {
            'prodi' => $this->program_studi_id !== null
                && (int) $mahasiswa->program_studi_id === (int) $this->program_studi_id,
            'fakultas' => $this->fakultas_id !== null
                && (int) $mahasiswa->programStudi?->fakultas_id === (int) $this->fakultas_id,
            default => false,
        };
    }

    /**
     * Batasi query pengguna (mahasiswa) ke cakupan $viewer. Lihat mencakupMahasiswa().
     */
    public function scopeDalamCakupan(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        $tidakAda = fn (Builder $q): Builder => $q->whereRaw('1 = 0');

        if (! $viewer->isVerifikator()) {
            return $tidakAda($query);
        }

        return match ($viewer->verifikator_level) {
            'prodi' => $viewer->program_studi_id
                ? $query->where('program_studi_id', $viewer->program_studi_id)
                : $tidakAda($query),
            'fakultas' => $viewer->fakultas_id
                ? $query->whereHas('programStudi', fn (Builder $p) => $p->where('fakultas_id', $viewer->fakultas_id))
                : $tidakAda($query),
            default => $tidakAda($query),
        };
    }

    /**
     * Lomba yang ditugaskan untuk dijuri (khusus penilai_karya).
     */
    public function lombaDinilai(): BelongsToMany
    {
        return $this->belongsToMany(Kegiatan::class, 'kegiatan_penilai', 'user_id', 'kegiatan_id')->withTimestamps();
    }
}
