<?php

use App\Support\Bagan\UrutanUnggulan;

it('memilih ukuran bagan pangkat dua terkecil yang memuat pesertanya', function (int $peserta, int $ukuran) {
    expect(UrutanUnggulan::ukuranBagan($peserta))->toBe($ukuran);
})->with([
    [2, 2], [3, 4], [4, 4], [5, 8], [8, 8], [9, 16], [16, 16], [17, 32], [32, 32], [33, 64],
]);

it('menolak bagan yang pesertanya kurang dari dua', function () {
    UrutanUnggulan::ukuranBagan(1);
})->throws(InvalidArgumentException::class);

/*
 * Susunan baku: pada bagan 8 tempat, partainya 1-8, 4-5, 2-7, dan 3-6.
 * Unggulan 1 dan 2 karena itu baru mungkin bertemu di final, sedangkan 1 dan 4
 * paling cepat bertemu di semifinal.
 */
it('menghasilkan susunan baku bagan gugur', function () {
    expect(UrutanUnggulan::untuk(2))->toBe([1, 2])
        ->and(UrutanUnggulan::untuk(4))->toBe([1, 4, 2, 3])
        ->and(UrutanUnggulan::untuk(8))->toBe([1, 8, 4, 5, 2, 7, 3, 6]);
});

it('memakai tiap nomor tepat sekali', function (int $ukuran) {
    $urutan = UrutanUnggulan::untuk($ukuran);

    expect($urutan)->toHaveCount($ukuran)
        ->and(array_unique($urutan))->toHaveCount($ukuran)
        ->and(min($urutan))->toBe(1)
        ->and(max($urutan))->toBe($ukuran);
})->with([2, 4, 8, 16, 32, 64]);

/*
 * Sifat yang membuat susunan ini dipakai: dua nomor yang bertemu di babak
 * pertama selalu berjumlah ukuran bagan ditambah satu. Dari sinilah bye
 * tersebar merata, karena nomor besar — yang nanti diisi bye — selalu
 * berpasangan dengan nomor kecil.
 */
it('memasangkan nomor yang berjumlah ukuran bagan ditambah satu', function (int $ukuran) {
    $urutan = UrutanUnggulan::untuk($ukuran);

    foreach (array_chunk($urutan, 2) as $pasangan) {
        expect($pasangan[0] + $pasangan[1])->toBe($ukuran + 1);
    }
})->with([2, 4, 8, 16, 32]);

it('menolak ukuran bagan yang bukan pangkat dua', function () {
    UrutanUnggulan::untuk(6);
})->throws(InvalidArgumentException::class);

/*
 * Inti dari penyebaran bye: dengan 5 peserta pada bagan 8, tiga bye harus
 * jatuh ke tiga partai yang berbeda — bukan menumpuk sehingga satu sisi bagan
 * melenggang ke semifinal tanpa bertanding.
 */
it('menyebar bye ke partai yang berbeda-beda', function (int $peserta, int $ukuran) {
    $urutan = UrutanUnggulan::untuk($ukuran);

    $partaiKosong = 0;

    foreach (array_chunk($urutan, 2) as $pasangan) {
        $terisi = count(array_filter($pasangan, fn (int $n): bool => $n <= $peserta));

        // Tidak boleh ada partai yang kedua sisinya bye — itu berarti satu
        // tempat di babak kedua terisi tanpa ada yang bertanding.
        expect($terisi)->toBeGreaterThan(0);

        if ($terisi === 1) {
            $partaiKosong++;
        }
    }

    expect($partaiKosong)->toBe($ukuran - $peserta);
})->with([
    'lima peserta di bagan delapan' => [5, 8],
    'tiga peserta di bagan empat' => [3, 4],
    'sembilan peserta di bagan enam belas' => [9, 16],
    'tujuh belas peserta di bagan tiga dua' => [17, 32],
]);
