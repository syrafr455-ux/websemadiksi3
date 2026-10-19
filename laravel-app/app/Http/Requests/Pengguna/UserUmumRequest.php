<?php

namespace App\Http\Requests\Pengguna;

use Illuminate\Validation\Rule;

/**
 * Mahasiswa Umum (user_umum). Aturan yang SAMA dipakai form admin dan pendaftaran publik /daftar.
 */
class UserUmumRequest extends PenggunaRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function aturan(?int $abaikanId, bool $wajibPassword): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'asal_kampus' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:50', Rule::unique('users', 'nim')->ignore($abaikanId)],
            'program_studi_teks' => ['required', 'string', 'max:150'],
            'email' => static::aturanEmail($abaikanId),
            'phone' => static::aturanNomorHp(true),
            'password' => static::aturanPassword($wajibPassword),
        ];
    }

    public function rules(): array
    {
        return static::aturan($this->targetUser()?->id, ! $this->isEdit());
    }

    public function messages(): array
    {
        return parent::messages() + [
            'asal_kampus.required' => 'Asal kampus wajib diisi.',
            'nim.required' => 'NIM wajib diisi.',
            'nim.unique' => 'NIM ini sudah terdaftar di sistem.',
            'program_studi_teks.required' => 'Program studi wajib diisi.',
        ];
    }

    public function atributPengguna(): array
    {
        $data = $this->validated();
        $asalKampus = trim($data['asal_kampus']);
        $prodi = trim($data['program_studi_teks']);

        return [
            'name' => trim($data['name']),
            'email' => $data['email'],
            'asal_kampus' => $asalKampus,
            'nim' => trim($data['nim']),
            'program_studi_teks' => $prodi,
            'phone' => $data['phone'],
            // Kolom lama ikut diisi agar tampilan lama tidak menampilkan default "UNUSA / Teknik Informatika".
            'university' => $asalKampus,
            'prodi' => $prodi,
            'kip_status' => 'Umum',
        ] + $this->atributPassword();
    }
}
