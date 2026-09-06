<?php

/*
 * Galeri /design-system/si merender setiap komponen si/* dalam seluruh
 * keadaannya, jadi satu berkas uji ini menjaga ketiga puluh satunya sekaligus:
 * nama ikon yang salah, prop yang hilang, slot yang berubah nama — semuanya
 * memerahkan uji di sini.
 *
 * Kenapa perlu: DesignSystemTest sudah melakukan hal yang sama untuk lapisan
 * ui/ yang lama, tapi galeri si/* tidak pernah punya penjaganya sendiri.
 * Selama itu, satu-satunya yang menangkap komponen rusak adalah layar yang
 * kebetulan memakainya — dan komponen yang baru dibuat belum dipakai layar
 * mana pun.
 */

use function Pest\Laravel\get;

it('merender seluruh komponen si/* tanpa galat', function () {
    get(route('design-system.si'))->assertOk();
});

it('tidak butuh login — galerinya tidak menyentuh database maupun sesi', function () {
    $this->assertGuest();

    get(route('design-system.si'))->assertOk();
});

/*
 * Komponen yang ada di berkas tapi tidak pernah dipanggil galeri adalah
 * komponen yang tidak punya penjaga sama sekali. Daftar pengecualiannya
 * ditulis eksplisit supaya menambah komponen baru MEMAKSA orang memilih:
 * pasang di galeri, atau nyatakan alasannya di sini.
 */
it('memanggil setiap komponen si/* yang punya wujud sendiri', function () {
    $galeri = file_get_contents(resource_path('views/design-system/si.blade.php'));

    $berkas = collect(glob(resource_path('views/components/si/*.blade.php')))
        ->map(fn (string $p): string => basename($p, '.blade.php'));

    $dikecualikan = [
        // Bagan pohon butuh data babak yang utuh; wujudnya dijaga
        // BracketControllerTest lewat halaman bagan sungguhan.
        'pohon-bagan',

        // Menempel di shell aplikasi, bukan di halaman. Galeri memakai layout
        // docs yang memang tidak punya topbar, jadi keduanya dijaga oleh
        // AdminPagesTest lewat halaman admin sungguhan.
        //
        // 'lonceng' pernah ada di daftar ini dengan alasan yang sama, padahal
        // shell aplikasi tidak pernah memanggilnya -- komponennya sudah
        // dihapus bersama sisa boilerplate lain.
        'jejak',
        'cari-menu',
    ];

    $hilang = $berkas
        ->reject(fn (string $nama): bool => in_array($nama, $dikecualikan, true))
        // Batas kata perlu: tanpa itu `isian` dianggap terpakai hanya karena
        // galeri memanggil `isian-panjang`.
        ->reject(fn (string $nama): bool => preg_match('/<x-si\.'.preg_quote($nama, '/').'[\s>\/]/', $galeri) === 1)
        ->values()
        ->all();

    expect($hilang)->toBe([], 'Komponen si/* berikut tidak dipanggil galeri: '.implode(', ', $hilang));
});
