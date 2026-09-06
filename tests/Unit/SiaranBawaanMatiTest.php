<?php

/**
 * phpunit.xml menyalakan kedua saklar siaran untuk seluruh rangkaian uji, dan
 * itu memang perlu -- tanpa itu setiap uji overlay dan live score gagal. Efek
 * sampingnya: tidak ada satu pun uji lain yang bisa membuktikan bahwa
 * bawaannya justru MATI.
 *
 * Berkas ini menutup celah itu lewat bacaConfigTanpaEnv() di tests/Pest.php,
 * yang membaca ulang berkas config dengan variabel env dihapus -- persis
 * seperti instalasi baru yang .env-nya belum menyebut saklar apa pun.
 */

it('mematikan overlay kalau .env belum menyebutnya', function () {
    expect(bacaConfigTanpaEnv('overlay', 'OVERLAY_ENABLED')['enabled'])->toBeFalse();
});

it('mematikan live score kalau .env belum menyebutnya', function () {
    expect(bacaConfigTanpaEnv('live', 'LIVE_SCORE_ENABLED')['enabled'])->toBeFalse();
});

/**
 * Nilai dari <env> phpunit.xml tiba sebagai string "1", bukan boolean true.
 * Tanpa filter_var, nilai seperti itu lolos sebagai "aktif" di satu tempat
 * sekaligus gagal pada pemeriksaan yang menuntut boolean di tempat lain --
 * kegagalan yang hanya muncul di rangkaian uji, tidak pernah di produksi.
 */
it('selalu menghasilkan boolean, bukan string', function () {
    expect(config('overlay.enabled'))->toBeBool()
        ->and(config('live.enabled'))->toBeBool();
});
