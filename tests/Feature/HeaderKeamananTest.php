<?php

use App\Models\Tournament;

/**
 * Panel gelanggang tidak boleh bisa dibingkai halaman lain.
 *
 * Tanpa `X-Frame-Options` atau `frame-ancestors`, panel juri dan operator
 * bisa dimuat di iframe tak terlihat: juri yang sedang login dikelabui
 * menekan tombol nilai atau "Akhiri partai" tanpa sadar. Ini penting justru
 * karena sebagian rute sistem ini memang dirancang dijangkau dari luar
 * jaringan gelanggang.
 */
it('menolak halaman dibingkai situs lain', function () {
    $this->get('/login')->assertHeader('X-Frame-Options', 'DENY');
});

it('mematikan penebakan tipe berkas oleh peramban', function () {
    $this->get('/login')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('tidak membocorkan alamat halaman ke situs lain', function () {
    $this->get('/login')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('tidak mengumumkan versi PHP', function () {
    $this->get('/login')->assertHeaderMissing('X-Powered-By');
});

it('memasang header yang sama di halaman publik live', function () {
    $tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);

    $this->get("/live/turnamen/{$tournament->id}")
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
