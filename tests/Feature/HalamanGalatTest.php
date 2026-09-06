<?php

/**
 * Setiap kode galat yang bisa dilihat pengguna punya halamannya sendiri
 * dalam bahasa Indonesia. 429 sempat tertinggal: pengguna yang salah kata
 * sandi beberapa kali mendapat halaman bawaan Laravel berbahasa Inggris,
 * tanpa keterangan berapa lama harus menunggu.
 */
it('punya halaman berbahasa Indonesia untuk tiap kode galat', function (string $kode, string $judul) {
    $isi = view("errors.{$kode}", ['exception' => new Exception])->render();

    expect($isi)->toContain($judul)
        ->and($isi)->toContain($kode);
})->with([
    ['403', 'Akses ditolak'],
    ['404', 'Halaman tidak ditemukan'],
    ['419', 'Sesi kedaluwarsa'],
    ['405', 'Alamat ini tidak dibuka lewat peramban'],
    ['429', 'Terlalu banyak percobaan'],
    ['500', 'Terjadi kesalahan'],
]);

it('menyebut berapa lama harus menunggu di halaman 429', function () {
    expect(view('errors.429', ['exception' => new Exception])->render())
        ->toContain('satu menit');
});
