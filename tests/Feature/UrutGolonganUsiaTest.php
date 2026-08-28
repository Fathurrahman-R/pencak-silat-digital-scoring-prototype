<?php

use App\Enums\GolonganUsia;
use App\Models\JurusEvent;
use App\Models\Tournament;
use App\Models\WeightClass;

/*
 * `orderBy('golongan_usia')` mengurutkan nilai backing enum sebagai teks,
 * jadi hasilnya alfabet: dewasa lebih dulu dari usia_dini_1. Di daftar kelas
 * publik itu menaruh golongan Dewasa paling atas dan anak-anak paling bawah,
 * kebalikan dari buku acara.
 *
 * Uji ini mengunci urutan menurut umur. Ia memeriksa hasil query, bukan array
 * enum-nya — kalau seseorang kembali menulis orderBy('golongan_usia'), uji ini
 * yang gagal, bukan uji enum.
 */

it('mengurutkan kelas menurut umur, bukan alfabet nilai enum', function () {
    $tournament = Tournament::factory()->create();

    /*
     * Sengaja dibuat dengan urutan terbalik dari yang benar, dan dipilih
     * golongan yang alfabetnya berlawanan dengan umurnya: "dewasa" < "usia_dini_1"
     * sebagai teks, padahal Usia Dini 1 harus lebih dulu.
     */
    foreach ([GolonganUsia::Master2, GolonganUsia::Dewasa, GolonganUsia::UsiaDini1, GolonganUsia::PraUsiaDini] as $golongan) {
        WeightClass::factory()->for($tournament)->create(['golongan_usia' => $golongan]);
    }

    $urut = WeightClass::query()->where('tournament_id', $tournament->id)
        ->urutGolonganUsia()->pluck('golongan_usia');

    expect($urut->all())->toBe([
        GolonganUsia::PraUsiaDini,
        GolonganUsia::UsiaDini1,
        GolonganUsia::Dewasa,
        GolonganUsia::Master2,
    ]);
});

it('mengurutkan nomor jurus menurut umur juga', function () {
    $tournament = Tournament::factory()->create();

    foreach ([GolonganUsia::Dewasa, GolonganUsia::UsiaDini2] as $golongan) {
        JurusEvent::factory()->for($tournament)->create(['golongan_usia' => $golongan]);
    }

    $urut = JurusEvent::query()->where('tournament_id', $tournament->id)
        ->urutGolonganUsia()->pluck('golongan_usia');

    expect($urut->all())->toBe([GolonganUsia::UsiaDini2, GolonganUsia::Dewasa]);
});
