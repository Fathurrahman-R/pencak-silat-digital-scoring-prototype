<?php

namespace Database\Factories;

use App\Models\Contingent;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contingent> */
class ContingentFactory extends Factory
{
    protected $model = Contingent::class;

    public function definition(): array
    {
        $kota = fake('id_ID')->city();

        return [
            'tournament_id' => Tournament::factory(),
            'user_id' => null,
            'name' => 'Kontingen '.$kota.' '.fake()->unique()->numberBetween(1, 9999),
            'region' => $kota,
            'contact_name' => fake('id_ID')->name(),
            'contact_phone' => '08'.fake()->numerify('##########'),
        ];
    }
}
