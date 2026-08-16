<?php

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\TournamentRuleSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TournamentRuleSetting> */
class TournamentRuleSettingFactory extends Factory
{
    protected $model = TournamentRuleSetting::class;

    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            ...TournamentRuleSetting::bawaan(),
        ];
    }
}
