<?php

namespace App\Http\Requests\Pengguna;

use App\Models\User;
use App\Support\NormalisasiNim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

/**
 * Dasar form tambah/edit pengguna per role di /admin/pengguna.
 *
 * Route dengan parameter {id} = edit (password boleh kosong = tidak diubah), tanpa {id} = tambah baru.
 * Aturan bersama (email, nomor HP Indonesia, password) dibuat statis agar bisa dipakai ulang
 * persis sama oleh form publik (lihat RegisterUserUmumRequest).
 */
abstract class PenggunaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->targetUser();

        return $target
            ? (bool) $this->user()?->can('update', $target)
            : (bool) $this->user()?->can('create', User::class);
    }

    /**
     * Pengguna yang sedang diedit, atau null saat menambah pengguna baru.
     */
    public function targetUser(): ?User
    {
        $id = $this->route('id');

        return $id ? User::find($id) : null;
    }

    public function isEdit(): bool
    {
        return $this->targetUser() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('email')) {
            $merge['email'] = strtolower(trim((string) $this->input('email')));
        }
        if ($this->filled('phone')) {
            $merge['phone'] = static::normalisasiNomorHp((string) $this->input('phone'));
        }
        if ($this->has('nim')) {
            $merge['nim'] = NormalisasiNim::bersihkan((string) $this->input('nim'));
        }

        $this->merge($merge);
    }

    /**
     * "+62 812-3456-7890" / "6281234567890" / "0812 3456 7890" -> "081234567890".
     */
    public static function normalisasiNomorHp(string $nomor): string
    {
        $nomor = preg_replace('/[\s\-.()]/', '', trim($nomor)) ?? '';

        if (str_starts_with($nomor, '+62')) {
            return '0'.substr($nomor, 3);
        }
        if (str_starts_with($nomor, '62')) {
            return '0'.substr($nomor, 2);
        }

        return $nomor;
    }

    /**
     * Nomor HP Indonesia (setelah normalisasi): 08 + 8-13 digit.
     */
    public static function aturanNomorHp(bool $wajib): array
    {
        return [$wajib ? 'required' : 'nullable', 'string', 'regex:/^08[1-9][0-9]{6,11}$/'];
    }

    public static function aturanEmail(?int $abaikanId): array
    {
        return ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($abaikanId)];
    }

    /**
     * NIM unik hanya di antara akun UNUSA (is_unusa = true); sama dengan indeks users_nim_unusa_unique.
     * Peserta umum dari kampus lain tidak dibatasi.
     */
    public static function aturanNimUnusaUnik(?int $abaikanId): Unique
    {
        return Rule::unique('users', 'nim')->where('is_unusa', true)->ignore($abaikanId);
    }

    /**
     * Aturan format email akun. Pemeriksaan DNS (MX) aktif kecuali dimatikan lewat
     * config('unusa.validasi_email_dns') -- hanya untuk tes, agar tes tidak butuh internet.
     */
    public static function aturanFormatEmailDns(): string
    {
        return config('unusa.validasi_email_dns', true) ? 'email:rfc,dns' : 'email:rfc';
    }

    public static function aturanPassword(bool $wajib): array
    {
        return [$wajib ? 'required' : 'nullable', 'string', Password::min(8)->letters()->numbers()];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar di sistem.',
            'phone.required' => 'Nomor handphone wajib diisi.',
            'phone.regex' => 'Nomor handphone harus nomor Indonesia, contoh 081234567890 atau +6281234567890.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }

    /**
     * Atribut kolom users dari data tervalidasi (tanpa role). Password hanya disertakan bila diisi.
     *
     * @return array<string, mixed>
     */
    abstract public function atributPengguna(): array;

    /**
     * @return array<string, mixed>
     */
    protected function atributPassword(): array
    {
        $password = $this->validated('password');

        return filled($password) ? ['password' => Hash::make($password)] : [];
    }
}
