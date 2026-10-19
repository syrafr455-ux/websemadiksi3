<?php

namespace App\Models;

use App\Support\NomorKip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris daftar penerima KIP Kuliah (impor dari UNUSA, berbasis nomor pendaftaran SIM KIP Kuliah).
 *
 * Nomor pendaftaran terenkripsi dan TIDAK ikut diserialisasi ($hidden). Untuk tampilan pakai
 * atribut "nomor_tersamar"; nomor lengkap hanya dibuka super_admin lewat aksi yang diaudit.
 */
class PenerimaKipk extends Model
{
    protected $table = 'daftar_penerima_kipk';

    public const TINJAU_NORMAL = 'normal';

    public const TINJAU_PERLU = 'perlu_ditinjau';

    public const ALASAN_KLAIM_NAMA = 'klaim_nama_tidak_mirip';

    public const ALASAN_HILANG = 'hilang_dari_impor';

    protected $fillable = [
        'nomor_pendaftaran_kip',
        'nomor_kip_hash',
        'nim',
        'nama_lengkap',
        'program_studi_teks',
        'angkatan',
        'status_penetapan',
        'sumber',
        'diimpor_pada',
        'diimpor_oleh',
        'user_id',
        'cara_terhubung',
        'dicocokkan_pada',
        'status_tinjau',
        'alasan_tinjau',
        'klaim_user_id',
        'klaim_pada',
        'catatan_tinjau',
    ];

    protected $hidden = ['nomor_pendaftaran_kip', 'nomor_kip_hash'];

    protected $appends = ['nomor_tersamar'];

    protected function casts(): array
    {
        return [
            'nomor_pendaftaran_kip' => 'encrypted',
            'diimpor_pada' => 'datetime',
            'dicocokkan_pada' => 'datetime',
            'klaim_pada' => 'datetime',
        ];
    }

    /**
     * Set nomor (ternormalisasi) sekaligus hash pencariannya.
     */
    public function aturNomor(string $nomor): static
    {
        $this->nomor_pendaftaran_kip = NomorKip::normalisasi($nomor);
        $this->nomor_kip_hash = NomorKip::hash($nomor);

        return $this;
    }

    public function getNomorTersamarAttribute(): string
    {
        return NomorKip::samarkan($this->nomor_pendaftaran_kip);
    }

    public function scopeDenganNomor(Builder $query, string $nomor): Builder
    {
        return $query->where('nomor_kip_hash', NomorKip::hash($nomor));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pengklaim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'klaim_user_id');
    }

    public function pengimpor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diimpor_oleh');
    }
}
