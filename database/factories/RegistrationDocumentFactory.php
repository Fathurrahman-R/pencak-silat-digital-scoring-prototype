<?php

namespace Database\Factories;

use App\Enums\JenisBerkas;
use App\Models\Athlete;
use App\Models\RegistrationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegistrationDocument> */
class RegistrationDocumentFactory extends Factory
{
    protected $model = RegistrationDocument::class;

    public function definition(): array
    {
        $jenis = fake()->randomElement(JenisBerkas::cases());

        return [
            'athlete_id' => Athlete::factory(),
            'jenis' => $jenis,
            'path' => 'peserta/uji/'.fake()->uuid().'.pdf',
            'original_name' => $jenis->value.'.pdf',
            'size_bytes' => fake()->numberBetween(50_000, 2_000_000),
            'mime' => 'application/pdf',
        ];
    }
}
