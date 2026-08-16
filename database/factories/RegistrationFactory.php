<?php

namespace Database\Factories;

use App\Enums\StatusPendaftaran;
use App\Models\Contingent;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Registration> */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    public function definition(): array
    {
        return [
            'contingent_id' => Contingent::factory(),
            'weight_class_id' => null,
            'jurus_event_id' => null,
            'status' => StatusPendaftaran::Draf,
        ];
    }

    public function diajukan(): static
    {
        return $this->state([
            'status' => StatusPendaftaran::Diajukan,
            'submitted_at' => now(),
        ]);
    }

    public function terverifikasi(): static
    {
        return $this->state([
            'status' => StatusPendaftaran::Terverifikasi,
            'submitted_at' => now(),
            'verified_at' => now(),
        ]);
    }
}
