<?php

namespace App\Http\Requests\Admin;

use App\Models\Kegiatan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateVenueConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $kegiatanId = $this->route('id') ?? $this->route('kegiatan');
        $kegiatan = is_numeric($kegiatanId) ? Kegiatan::find((int) $kegiatanId) : $kegiatanId;

        if ($kegiatan instanceof Kegiatan) {
            return Gate::allows('updateVenueConfig', $kegiatan) || Gate::allows('update', $kegiatan);
        }

        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Kapasitas eksplisit; bila kosong dihitung dari denah (kursi non-panitia, tidak diblokir).
            'total_seats' => ['nullable', 'integer', 'min:1'],
            'booking_opens_at' => ['nullable', 'date'],
            'booking_closes_at' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if ($value && $this->filled('booking_opens_at')) {
                        $opensAt = strtotime($this->input('booking_opens_at'));
                        $closesAt = strtotime($value);
                        if ($closesAt <= $opensAt) {
                            $fail('Waktu batas akhir booking kursi (booking_closes_at) harus setelah waktu buka (booking_opens_at).');
                        }
                    }
                },
            ],
            'layout_changed' => ['required', 'boolean'],
            'is_seat_booking_active' => ['required', 'boolean'],
            'layout_config' => ['required_if:layout_changed,true', 'nullable', 'array'],
            'layout_config.rows' => ['sometimes', 'array', 'min:1', 'max:30'],
            'layout_config.rows.*' => ['string', 'max:3', 'regex:/^[A-Za-z0-9]+$/'],
            'layout_config.cols_per_row' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'layout_config.vip_rows' => ['sometimes', 'nullable', 'array'],
            'layout_config.vip_rows.*' => ['string', 'max:3'],
            'layout_config.panitia_rows' => ['sometimes', 'nullable', 'array'],
            'layout_config.panitia_rows.*' => ['string', 'max:3'],
            'layout_config.blocked_seats' => ['sometimes', 'nullable', 'array'],
            'layout_config.blocked_seats.*' => ['string', 'max:8'],
            'lokasi_nama' => ['nullable', 'string', 'max:255'],
            'lokasi_detail' => ['nullable', 'string', 'max:255'],
            'lokasi_type' => ['nullable', 'string', 'in:unusa_campus,external,online'],
            'lokasi_kampus' => ['nullable', 'string', 'max:255'],
            'lokasi_gedung' => ['nullable', 'string', 'max:255'],
            'lokasi_ruangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'total_seats.integer' => 'Jumlah kapasitas kursi harus berupa angka bulat.',
            'total_seats.min' => 'Kapasitas kursi minimal adalah 1.',
            'layout_changed.required' => 'Flag perubahan denah (layout_changed) wajib ditentukan.',
            'is_seat_booking_active.required' => 'Status aktif booking kursi wajib ditentukan.',
            'layout_config.required_if' => 'Konfigurasi denah kursi (layout_config) wajib diisi ketika denah diubah.',
            'layout_config.rows.min' => 'Denah minimal punya satu baris.',
            'layout_config.rows.*.regex' => 'Label baris hanya boleh huruf atau angka (maks. 3 karakter).',
            'layout_config.cols_per_row.max' => 'Jumlah kursi per baris maksimal 50.',
        ];
    }
}
