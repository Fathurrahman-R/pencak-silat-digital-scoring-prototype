<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tournament = $this->route('tournament');
        $arena = $this->route('arena');

        return [
            'name' => ['required', 'string', 'max:255'],

            /*
             * Kode gelanggang muncul di URL siaran langsung dan di alamat
             * overlay vMix, jadi dibatasi ke huruf, angka, dan tanda hubung.
             * Uniknya per kejuaraan, bukan global — "G1" di dua kejuaraan
             * berbeda adalah dua gelanggang yang sah.
             */
            'code' => [
                'required', 'string', 'max:16', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('arenas', 'code')
                    ->where('tournament_id', $tournament->id)
                    ->ignore($arena?->id),
            ],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama gelanggang',
            'code' => 'Kode',
            'sort_order' => 'Urutan',
            'is_active' => 'Aktif',
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh berisi huruf, angka, dan tanda hubung, '
                .'karena dipakai di alamat halaman siaran langsung dan overlay.',
            'code.unique' => 'Kode ini sudah dipakai gelanggang lain di kejuaraan yang sama.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
