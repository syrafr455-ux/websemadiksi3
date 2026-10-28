<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodeAkademik extends Model
{
    protected $table = 'periode_akademik';

    protected $fillable = ['nama', 'tanggal_mulai', 'tanggal_selesai'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }
}
