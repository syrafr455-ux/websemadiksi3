<?php

namespace App\Http\Requests\Pengguna;

use App\Models\ProgramStudi;
use Closure;

/**
 * Verifikator Berkas: Admin Fakultas (satu fakultas) atau Admin Prodi (satu prodi; fakultas ikut dari prodi).
 * Email wajib berdomain resmi (config('unusa.verifikator_email_domains')).
 */
class VerifikatorBerkasRequest extends PenggunaRequest
{
    public const LEVEL_FAKULTAS = 'fakultas';

    public const LEVEL_PRODI = 'prodi';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [...static::aturanEmail($this->targetUser()?->id), $this->aturanDomainEmail()],
            'phone' => static::aturanNomorHp(false),
            'password' => static::aturanPassword(! $this->isEdit()),
            'verifikator_level' => ['required', 'in:'.self::LEVEL_FAKULTAS.','.self::LEVEL_PRODI],
            'fakultas_id' => ['nullable', 'required_if:verifikator_level,'.self::LEVEL_FAKULTAS, 'integer', 'exists:fakultas,id'],
            'program_studi_id' => ['nullable', 'required_if:verifikator_level,'.self::LEVEL_PRODI, 'integer', 'exists:program_studi,id'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'verifikator_level.required' => 'Pilih jenis verifikator: Admin Fakultas atau Admin Prodi.',
            'verifikator_level.in' => 'Jenis verifikator harus Admin Fakultas atau Admin Prodi.',
            'fakultas_id.required_if' => 'Admin Fakultas wajib memilih fakultas.',
            'program_studi_id.required_if' => 'Admin Prodi wajib memilih program studi.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function domainEmail(): array
    {
        return array_values(array_map('strtolower', (array) config('unusa.verifikator_email_domains', ['unusa.ac.id'])));
    }

    protected function aturanDomainEmail(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $domain = strtolower(substr((string) strrchr((string) $value, '@'), 1));

            if (! in_array($domain, static::domainEmail(), true)) {
                $daftar = implode(', ', array_map(fn (string $d): string => '@'.$d, static::domainEmail()));
                $fail("Email verifikator harus memakai domain resmi: {$daftar}.");
            }
        };
    }

    public function atributPengguna(): array
    {
        $data = $this->validated();
        $level = $data['verifikator_level'];

        if ($level === self::LEVEL_PRODI) {
            $prodiId = (int) $data['program_studi_id'];
            $fakultasId = ProgramStudi::whereKey($prodiId)->value('fakultas_id');
        } else {
            $prodiId = null;
            $fakultasId = (int) $data['fakultas_id'];
        }

        return [
            'name' => trim($data['name']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'verifikator_level' => $level,
            'fakultas_id' => $fakultasId,
            'program_studi_id' => $prodiId,
            'kip_status' => 'Umum',
        ] + $this->atributPassword();
    }
}
