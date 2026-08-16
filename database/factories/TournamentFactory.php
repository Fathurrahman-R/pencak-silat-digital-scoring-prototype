<?php

namespace Database\Factories;

use App\Enums\StatusTurnamen;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tournament> */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    public function definition(): array
    {
        $nama = 'Kejuaraan '.fake('id_ID')->city().' Open '.fake()->year();
        $mulai = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'name' => $nama,
            'slug' => Str::slug($nama).'-'.fake()->unique()->numberBetween(1, 99999),
            'organizer' => 'Pengurus Cabang IPSI '.fake('id_ID')->city(),
            'venue' => 'GOR '.fake('id_ID')->lastName(),
            'starts_on' => $mulai,
            'ends_on' => (clone $mulai)->modify('+3 days'),
            'registration_opens_at' => now()->subWeek(),
            'registration_closes_at' => (clone $mulai)->modify('-3 days'),
            'status' => StatusTurnamen::Draf,
        ];
    }

    public function berjalan(): static
    {
        return $this->state(['status' => StatusTurnamen::Berjalan]);
    }

    public function selesai(): static
    {
        return $this->state(['status' => StatusTurnamen::Selesai]);
    }
}
