<?php

namespace App\Http\Requests\Pengguna;

use App\Models\User;

/**
 * Pendaftaran mandiri Mahasiswa Umum di /daftar: aturan field identik dengan form admin
 * (UserUmumRequest::aturan), ditambah konfirmasi kata sandi.
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

    public function rules(): array
    {
        $aturan = static::aturan(null, true);
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
