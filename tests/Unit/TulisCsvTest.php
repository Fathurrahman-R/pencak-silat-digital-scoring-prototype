<?php

use App\Support\Ekspor\TulisCsv;

/**
 * Sel yang diawali tanda rumus dieksekusi Excel dan LibreOffice begitu
 * berkasnya dibuka. Tanda kutip ganda CSV tidak menolongnya: kutip hanya
 * melindungi pemisah kolom, bukan mencegah selnya dibaca sebagai rumus.
 *
 * Datanya datang dari peserta -- nama kontingen dan nama atlet diisi
 * official kontingen sendiri -- jadi setiap ekspor adalah jalur dari input
 * peserta ke komputer panitia.
 */
it('menetralkan sel yang diawali tanda rumus', function (string $bahaya) {
    expect(TulisCsv::aman([$bahaya]))->toBe(["'".$bahaya]);
})->with([
    '=HYPERLINK("http://jahat.example","Klik")',
    '+1+1',
    '-1+1',
    '@SUM(A1:A9)',
    "\tterdepan tab",
    "\rterdepan carriage return",
]);

it('membiarkan teks biasa apa adanya', function (string $aman) {
    expect(TulisCsv::aman([$aman]))->toBe([$aman]);
})->with([
    'Perisai Diri Jakarta',
    'Dimas Prakoso',
    'INV-202608-00001',
    'Tanding Putra Dewasa Kelas A',
    'Sudah lunas - dibayar tunai',
    'nama@contoh.test',
]);

it('membiarkan angka dan null apa adanya', function () {
    expect(TulisCsv::aman([1, 950000, 9.85, null, true]))->toBe([1, 950000, 9.85, null, true]);
});

it('menetralkan setiap sel dalam satu baris, bukan hanya yang pertama', function () {
    expect(TulisCsv::aman(['Aman', '=1+1', 'Aman juga', '@cmd']))
        ->toBe(['Aman', "'=1+1", 'Aman juga', "'@cmd"]);
});

it('menulis baris yang sudah ditetralkan ke handle', function () {
    $handle = fopen('php://memory', 'r+');

    TulisCsv::tulis($handle, ['Kontingen', 'Total']);
    TulisCsv::tulis($handle, ['=HYPERLINK("http://jahat.example")', 950000]);

    rewind($handle);
    $isi = stream_get_contents($handle);
    fclose($handle);

    expect($isi)->toContain("'=HYPERLINK")
        ->and($isi)->toStartWith('Kontingen,Total');
});
