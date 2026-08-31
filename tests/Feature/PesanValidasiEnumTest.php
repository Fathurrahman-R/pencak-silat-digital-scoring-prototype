<?php

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Berkas terjemahan `lang/id/validation.php` sudah ada dan dipakai, tapi
 * kunci `enum` tidak pernah diisi -- sehingga setiap aturan Rule::enum di
 * seluruh aplikasi jatuh ke bahasa Inggris: "The selected corner is
 * invalid." muncul berdampingan dengan pesan Indonesia dalam satu respons.
 *
 * Diterjemahkan sekali di sini, bukan ditambal per controller, supaya
 * aturan enum yang ditulis kemudian ikut terjemahannya tanpa diingat lagi.
 */
it('menerjemahkan pesan enum ke bahasa Indonesia', function () {
    $pesan = Validator::make(
        ['sudut' => 'hijau'],
        ['sudut' => ['required', Rule::enum(Sudut::class)]],
    )->errors()->first('sudut');

    expect($pesan)->not->toContain('The ')
        ->and($pesan)->not->toContain('is invalid')
        ->and($pesan)->toContain('tidak dikenal');
});

it('memakai nama atribut yang disebut pemanggilnya', function () {
    $pesan = Validator::make(
        ['jenis' => 'kuncian'],
        ['jenis' => [Rule::enum(JenisSerangan::class)]],
        attributes: ['jenis' => 'Jenis serangan'],
    )->errors()->first('jenis');

    expect($pesan)->toContain('Jenis serangan');
});
