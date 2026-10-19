<?php

namespace App\Http\Requests\Pengguna;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Mahasiswa KIP-K: SENGAJA identik dengan perilaku form lama (aturan, normalisasi, dan kolom).
 * Perubahan form KIP-K dibahas di sesi tersendiri.
 */
class MahasiswaKipkRequest extends PenggunaRequest
{
    protected function prepareForValidation(): void
    {
        // Tanpa normalisasi tambahan: perilaku lama dipertahankan.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'nim' => ['nullable', 'string', 'max:50', 'unique:users,nim'],
            'phone' => ['nullable', 'string', 'max:20'],
            'angkatan' => ['nullable', 'string', 'max:10'],
            'prodi' => ['nullable', 'string', 'max:100'],
            'university' => ['nullable', 'string', 'max:255'],
            'password' => ['required', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function atributPengguna(): array
    {
        $data = $this->validated();

        return [
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'nim' => ! empty($data['nim']) ? trim($data['nim']) : null,
            'phone' => ! empty($data['phone']) ? trim($data['phone']) : null,
            'angkatan' => $data['angkatan'] ?? null,
            'prodi' => $data['prodi'] ?? null,
            'university' => $data['university'] ?? 'Universitas Nahdlatul Ulama Surabaya',
            'kip_status' => 'KIP UNUSA',
            'password' => Hash::make($data['password']),
        ];
    }
}
