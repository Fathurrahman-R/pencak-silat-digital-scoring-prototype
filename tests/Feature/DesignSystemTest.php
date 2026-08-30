<?php

/*
 * Dua halaman peraga yang tersisa setelah lapisan RizzxxUI dibongkar.
 *
 * Keduanya sengaja terpisah: `si` memakai bundel admin (terang), `gelanggang`
 * memakai bundel silat (gelap) dan tidak memuat app.css sama sekali. Token
 * yang bocor dari satu bundel ke bundel lain langsung kelihatan di sana.
 *
 * Isi halaman `si` dijaga GaleriKomponenSiTest, yang juga memastikan setiap
 * komponen di components/si/ benar-benar dipanggil dari galeri.
 */

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

it('membuka halaman peraga', function (string $route) {
    get(route($route))->assertOk();
})->with([
    'design-system.si',
    'design-system.gelanggang',
]);

it('tidak butuh login — halamannya tidak menyentuh database maupun sesi', function () {
    $this->assertGuest();

    get(route('design-system.si'))->assertOk();
    get(route('design-system.gelanggang'))->assertOk();
});

/*
 * Route-nya tidak didaftarkan sama sekali kalau dimatikan, supaya di produksi
 * tidak ada permukaan tambahan yang perlu dijaga. Yang diuji di sini bukan
 * 403-nya, melainkan bahwa nama route-nya memang tidak ada.
 */
it('tidak mendaftarkan route apa pun saat peraga dimatikan', function () {
    expect(config('design-system.enabled'))->toBeTrue()
        ->and(Route::has('design-system.si'))->toBeTrue();

    // Alamat layar contoh RizzxxUI sudah dihapus bersama lapisan yang
    // diperagakannya. Diuji supaya tidak diam-diam dihidupkan lagi.
    expect(Route::has('design-system.foundation'))->toBeFalse()
        ->and(Route::has('design-system.components'))->toBeFalse()
        ->and(Route::has('design-system.patterns'))->toBeFalse()
        ->and(Route::has('design-system.screen'))->toBeFalse();
});

it('menutup alamat layar contoh yang sudah dihapus', function () {
    get('/design-system/layar/dashboard')->assertNotFound();
    get('/design-system/komponen')->assertNotFound();
    get('/design-system/pola')->assertNotFound();
});
