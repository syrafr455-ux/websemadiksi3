<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
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
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'role' => UserRole::class,
        ];
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

    /**
     * @param  UserRole|string  ...$roles
     */
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
}
