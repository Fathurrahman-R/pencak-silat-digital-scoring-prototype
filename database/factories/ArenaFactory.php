<?php

namespace Database\Factories;

use App\Models\Arena;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Arena> */
class ArenaFactory extends Factory
{
    protected $model = Arena::class;

    public function definition(): array
    {
        $nomor = fake()->unique()->numberBetween(1, 20);

        return [
            'tournament_id' => Tournament::factory(),
            'name' => "Gelanggang {$nomor}",
            'code' => "G{$nomor}",
            'sort_order' => $nomor,
            'is_active' => true,
        ];
    }
}
