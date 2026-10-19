<?php

namespace App\Http\Requests\Pengguna;

use Illuminate\Validation\Rule;

/**
 * Juri (penilai_karya) beserta lomba yang dijuri (pivot kegiatan_penilai).
 */
class PenilaiKaryaRequest extends PenggunaRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => static::aturanEmail($this->targetUser()?->id),
            'phone' => static::aturanNomorHp(false),
            'password' => static::aturanPassword(! $this->isEdit()),
            'lomba_ids' => ['nullable', 'array'],
            'lomba_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('kegiatan', 'id')->where('kategori', 'lomba')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'lomba_ids.*.exists' => 'Kegiatan yang dipilih untuk dijuri harus berkategori lomba.',
        ];
    }

    public function atributPengguna(): array
    {
        $data = $this->validated();

        return [
            'name' => trim($data['name']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'kip_status' => 'Umum',
        ] + $this->atributPassword();
    }

    /**
     * @return list<int>
     */
    public function lombaIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('lomba_ids') ?? [])));
    }

    /**
     * Penugasan lomba hanya diubah bila field dikirim (edit tanpa field ini tidak menghapus penugasan).
     */
    public function mengirimLomba(): bool
    {
        return $this->has('lomba_ids');
    }
}
