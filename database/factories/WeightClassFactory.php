<?php

namespace Database\Factories;

use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Tournament;
use App\Models\WeightClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeightClass> */
class WeightClassFactory extends Factory
{
    protected $model = WeightClass::class;

    public function definition(): array
    {
        $huruf = fake()->unique()->randomElement(range('A', 'J'));
        $bawah = fake()->numberBetween(45, 80);

        return [
            'tournament_id' => Tournament::factory(),
            'golongan_usia' => GolonganUsia::Dewasa,
            'jenis_kelamin' => JenisKelamin::Putra,
            'code' => $huruf,
            'name' => "Kelas {$huruf}",
            'weight_min' => $bawah,
            'weight_max' => $bawah + 5,

            // Rumusan yang paling sering dipakai naskah: "Diatas X kg sampai
            // Y kg". Ditulis di sini, bukan diserahkan ke bawaan kolom, supaya
            // objek hasil create() sudah memegang nilainya tanpa perlu refresh.
            'weight_min_exclusive' => true,
            'weight_max_inclusive' => true,
            'sort_order' => ord($huruf) - ord('A'),
            'is_active' => true,
        ];
    }
}
