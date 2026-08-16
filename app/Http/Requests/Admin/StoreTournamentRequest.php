<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi ditangani middleware `resource` di route.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'registration_opens_at' => ['nullable', 'date'],

            /*
             * Pendaftaran ditutup sebelum hari pertama bertanding, bukan
             * sesudahnya: bagan disusun dari peserta yang sudah terkunci, dan
             * peserta yang masuk setelah bagan jadi tidak punya tempat.
             */
            'registration_closes_at' => [
                'nullable', 'date',
                'after_or_equal:registration_opens_at',
                'before_or_equal:starts_on',
            ],

            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama kejuaraan',
            'organizer' => 'Penyelenggara',
            'venue' => 'Tempat',
            'starts_on' => 'Tanggal mulai',
            'ends_on' => 'Tanggal selesai',
            'registration_opens_at' => 'Pendaftaran dibuka',
            'registration_closes_at' => 'Pendaftaran ditutup',
            'description' => 'Keterangan',
        ];
    }

    public function messages(): array
    {
        return [
            'registration_closes_at.before_or_equal' =>
                'Pendaftaran harus ditutup paling lambat pada hari pertama bertanding, '
                .'karena bagan disusun dari peserta yang sudah terkunci.',
        ];
    }
}
