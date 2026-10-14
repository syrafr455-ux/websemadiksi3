<?php

namespace App\Http\Requests\Admin;

use App\Models\Kegiatan;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'total_seats' => ['required', 'integer', 'min:1'],
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
            'lokasi_nama' => ['nullable', 'string', 'max:255'],
            'lokasi_type' => ['nullable', 'string', 'in:unusa_campus,external,online'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'total_seats.required' => 'Jumlah kapasitas kursi (total_seats) wajib diisi.',
            'total_seats.integer' => 'Jumlah kapasitas kursi harus berupa angka bulat.',
            'total_seats.min' => 'Kapasitas kursi minimal adalah 1.',
            'layout_changed.required' => 'Flag perubahan denah (layout_changed) wajib ditentukan.',
            'is_seat_booking_active.required' => 'Status aktif booking kursi wajib ditentukan.',
            'layout_config.required_if' => 'Konfigurasi denah kursi (layout_config) wajib diisi ketika denah diubah.',
        ];
    }
}
