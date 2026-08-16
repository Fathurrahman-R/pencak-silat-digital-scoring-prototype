<?php

namespace Database\Factories;

use App\Models\FeeSchedule;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FeeSchedule> */
class FeeScheduleFactory extends Factory
{
    protected $model = FeeSchedule::class;

    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'kind' => FeeSchedule::KIND_NOMOR,
            'kategori' => null,
            'golongan_usia' => null,
            'amount' => 150_000,
            'label' => null,
        ];
    }

    public function kontingen(int $amount = 250_000): static
    {
        return $this->state([
            'kind' => FeeSchedule::KIND_KONTINGEN,
            'kategori' => null,
            'golongan_usia' => null,
            'amount' => $amount,
            'label' => 'Biaya tetap kontingen',
        ]);
    }
}
