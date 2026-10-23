<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Token aktivasi akun mahasiswa. Hanya hash SHA-256 token yang disimpan (token asli hanya ada di link email).
 */
class AktivasiMahasiswa extends Model
{
    protected $table = 'aktivasi_mahasiswa';

    protected $fillable = ['nim', 'token_hash', 'kedaluwarsa_pada', 'dipakai_pada'];

    protected function casts(): array
    {
        return [
            'kedaluwarsa_pada' => 'datetime',
            'dipakai_pada' => 'datetime',
        ];
    }

    public function masihBerlaku(): bool
    {
        return $this->dipakai_pada === null && $this->kedaluwarsa_pada->isFuture();
    }
}
