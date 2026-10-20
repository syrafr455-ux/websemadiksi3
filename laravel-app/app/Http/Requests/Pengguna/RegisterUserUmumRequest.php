<?php

namespace App\Http\Requests\Pengguna;

use App\Models\User;

/**
 * Pendaftaran mandiri jalur Peserta Umum di /daftar: aturan field identik dengan form admin
 * (UserUmumRequest::aturan), ditambah konfirmasi kata sandi. Akun jalur ini bukan akun UNUSA
 * (mahasiswa UNUSA mendaftar lewat jalur NIM), jadi NIM tidak wajib unik.
 */
class RegisterUserUmumRequest extends UserUmumRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function targetUser(): ?User
    {
        return null;
    }

    public function akunUnusa(): bool
    {
        return false;
    }

    public function rules(): array
    {
        $aturan = static::aturan(null, true);
        // is_unusa dari form publik diabaikan (akunUnusa() selalu false).
        $aturan['password'][] = 'confirmed';

        return $aturan;
    }

    public function messages(): array
    {
        return parent::messages() + [
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }
}
