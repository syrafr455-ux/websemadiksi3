<?php

namespace App\Services\Auth;

use App\Models\PenerimaKipk;
use App\Models\User;

final class SsoEvaluasi
{
    public function __construct(
        public bool $dapatLogin,
        public string $kodeAlasan,
        public string $pesan,
        public string $kodeRef,
        public ?User $user = null,
        public ?array $masterMahasiswa = null,
        public ?PenerimaKipk $penerimaKipk = null,
        public bool $perluAktivasi = false,
        public bool $perluDibuat = false,
        public bool $subBentrok = false,
        public bool $akunGanda = false,
    ) {}
}
