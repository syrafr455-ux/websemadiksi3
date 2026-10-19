<?php

namespace App\Http\Requests\Pengguna;

class SuperAdminRequest extends PenggunaRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => static::aturanEmail($this->targetUser()?->id),
            'password' => static::aturanPassword(! $this->isEdit()),
        ];
    }

    public function atributPengguna(): array
    {
        $data = $this->validated();

        return [
            'name' => trim($data['name']),
            'email' => $data['email'],
            'kip_status' => 'Umum',
        ] + $this->atributPassword();
    }
}
