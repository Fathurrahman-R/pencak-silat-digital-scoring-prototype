<?php

namespace Database\Factories;

use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Athlete> */
class AthleteFactory extends Factory
{
    protected $model = Athlete::class;

    public function definition(): array
    {
        return [
            'contingent_id' => Contingent::factory(),
            'name' => fake('id_ID')->name(),
            'jenis_kelamin' => fake()->randomElement(JenisKelamin::cases()),
            'birth_date' => fake()->dateTimeBetween('-30 years', '-18 years'),
            'weight_claim' => fake()->randomFloat(1, 45, 90),
        ];
    }

    /**
     * Atlet yang umurnya jatuh di tengah golongan tertentu pada tanggal acuan.
     *
     * Dipakai pengujian yang bergantung pada golongan usia. Diambil dari titik
     * tengah rentang, bukan dari tepinya, supaya pergeseran satu hari tidak
     * diam-diam memindahkan atletnya ke golongan sebelah.
     */
    public function golongan(GolonganUsia $golongan, ?\DateTimeInterface $acuan = null): static
    {
        [$bawah, $atas] = $golongan->batasUmur();
        $umur = match (true) {
            $bawah === null => max(1, $atas - 1),
            $atas === null => $bawah + 5,
            default => (int) floor(($bawah + $atas) / 2),
        };

        $acuan ??= now();

        return $this->state([
            // Enam bulan setelah ulang tahun ke-$umur: aman dari pembulatan di
            // kedua arah.
            'birth_date' => (clone $acuan)->modify("-{$umur} years")->modify('-6 months'),
        ]);
    }

    public function putra(): static
    {
        return $this->state(['jenis_kelamin' => JenisKelamin::Putra]);
    }

    public function putri(): static
    {
        return $this->state(['jenis_kelamin' => JenisKelamin::Putri]);
    }
}
