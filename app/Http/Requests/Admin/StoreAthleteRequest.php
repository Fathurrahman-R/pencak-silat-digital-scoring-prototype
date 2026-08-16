<?php

namespace App\Http\Requests\Admin;

use App\Enums\JenisKelamin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAthleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::enum(JenisKelamin::class)],

            /*
             * Batas bawah 100 tahun ke belakang menutup salah ketik tahun yang
             * paling sering terjadi, dan batas atasnya hari ini — tanggal lahir
             * di masa depan menghasilkan umur negatif yang jatuh ke luar semua
             * golongan tanpa pesan yang jelas.
             */
            'birth_date' => ['required', 'date', 'after:'.now()->subYears(100)->toDateString(), 'before_or_equal:today'],

            'weight_claim' => ['nullable', 'numeric', 'min:10', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama atlet',
            'jenis_kelamin' => 'Jenis kelamin',
            'birth_date' => 'Tanggal lahir',
            'weight_claim' => 'Berat badan',
        ];
    }

    public function messages(): array
    {
        return [
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.',
        ];
    }
}
