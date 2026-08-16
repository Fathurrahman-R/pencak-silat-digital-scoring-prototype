<?php

namespace Database\Factories;

use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\JurusEvent;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JurusEvent> */
class JurusEventFactory extends Factory
{
    protected $model = JurusEvent::class;

    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'jenis' => fake()->randomElement(JenisJurus::cases()),
            'golongan_usia' => GolonganUsia::Dewasa,
            'jenis_kelamin' => fake()->randomElement(JenisKelamin::cases()),
            'waktu_acuan_ms' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
