<?php

namespace App\Http\Requests\Pengguna;

use App\Support\NormalisasiNim;

/**
 * Mahasiswa Umum (user_umum). Aturan yang SAMA dipakai form admin dan pendaftaran publik /daftar.
 *
 * NIM wajib unik hanya bila akun ditandai sebagai akun mahasiswa UNUSA (is_unusa);
 * peserta dari kampus lain cukup email yang unik.
 */
class UserUmumRequest extends PenggunaRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function aturan(?int $abaikanId, bool $wajibPassword, bool $akunUnusa = false): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'asal_kampus' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:50', ...($akunUnusa ? [static::aturanNimUnusaUnik($abaikanId)] : [])],
            'program_studi_teks' => ['required', 'string', 'max:150'],
            'email' => static::aturanEmail($abaikanId),
            'phone' => static::aturanNomorHp(true),
            'password' => static::aturanPassword($wajibPassword),
            'is_unusa' => ['sometimes', 'boolean'],
        ];
    }

    public function rules(): array
    {
        return static::aturan($this->targetUser()?->id, ! $this->isEdit(), $this->akunUnusa());
    }

    /**
     * Penanda akun UNUSA dari form admin; saat edit tanpa field ini, nilai lama dipertahankan.
     */
    public function akunUnusa(): bool
    {
        return $this->has('is_unusa') ? $this->boolean('is_unusa') : (bool) $this->targetUser()?->is_unusa;
    }

    public function messages(): array
    {
        return parent::messages() + [
            'asal_kampus.required' => 'Asal kampus wajib diisi.',
            'nim.required' => 'NIM wajib diisi.',
            'nim.unique' => 'NIM ini sudah dipakai akun mahasiswa UNUSA lain.',
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
            'nim' => NormalisasiNim::bersihkan($data['nim']),
            'program_studi_teks' => $prodi,
            'phone' => $data['phone'],
            'is_unusa' => $this->akunUnusa(),
            // Kolom lama ikut diisi agar tampilan lama tidak menampilkan default "UNUSA / Teknik Informatika".
            'university' => $asalKampus,
            'prodi' => $prodi,
            'kip_status' => 'Umum',
        ] + $this->atributPassword();
    }
}
