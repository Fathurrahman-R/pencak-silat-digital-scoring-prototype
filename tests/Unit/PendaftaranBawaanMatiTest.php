<?php

/**
 * phpunit.xml mematok kedua saklar pendaftaran MATI supaya seluruh rangkaian
 * uji menjalani alur penuh dengan gerbangnya utuh. Patokan itu menyembunyikan
 * pertanyaan yang justru penting: kalau .env sebuah instalasi tidak menyebut
 * saklar ini sama sekali, apakah gerbangnya menyala atau mati?
 *
 * Jawabannya harus MATI-nya saklar, artinya gerbangnya MENYALA. Panitia yang
 * tidak pernah membaca berkas config tidak boleh kehilangan pengaman
 * pembayaran dan verifikasi berkas tanpa pernah memintanya.
 */
it('mematikan lewati verifikasi kalau .env belum menyebutnya', function () {
    expect(bacaConfigTanpaEnv('pendaftaran', 'PENDAFTARAN_LEWATI_VERIFIKASI')['lewati_verifikasi'])->toBeFalse();
});

it('mematikan lewati pembayaran kalau .env belum menyebutnya', function () {
    expect(bacaConfigTanpaEnv('pendaftaran', 'PENDAFTARAN_LEWATI_PEMBAYARAN')['lewati_pembayaran'])->toBeFalse();
});

/**
 * Nilai dari <env> phpunit.xml tiba sebagai string "false", yang di PHP justru
 * truthy. Tanpa filter_var, mematok saklar ini "false" di phpunit.xml malah
 * MENYALAKANNYA untuk seluruh rangkaian uji -- kebalikan persis dari yang
 * dimaksud, dan tidak akan terlihat sampai sebuah uji gagal dengan alasan yang
 * tidak masuk akal.
 */
it('selalu menghasilkan boolean, bukan string', function () {
    expect(config('pendaftaran.lewati_verifikasi'))->toBeBool()
        ->and(config('pendaftaran.lewati_pembayaran'))->toBeBool();
});

it('mematok kedua saklar mati sepanjang rangkaian uji', function () {
    expect(config('pendaftaran.lewati_verifikasi'))->toBeFalse()
        ->and(config('pendaftaran.lewati_pembayaran'))->toBeFalse();
});
