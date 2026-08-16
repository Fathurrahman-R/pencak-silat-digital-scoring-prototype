<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContingentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tournament = $this->route('tournament');
        $contingent = $this->route('contingent');

        return [
            // Nama kontingen dipanggil announcer dan tercetak di bagan, jadi
            // tidak boleh kembar di satu kejuaraan.
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('contingents', 'name')
                    ->where('tournament_id', $tournament->id)
                    ->whereNull('deleted_at')
                    ->ignore($contingent?->id),
            ],
            'region' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama kontingen',
            'region' => 'Daerah',
            'contact_name' => 'Nama kontak',
            'contact_phone' => 'Telepon kontak',
            'user_id' => 'Official',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Kontingen dengan nama ini sudah terdaftar di kejuaraan yang sama.',
        ];
    }
}
