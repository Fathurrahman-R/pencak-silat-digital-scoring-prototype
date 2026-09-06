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

/*
 * Halaman milik pengguna yang login tidak boleh tertinggal di back/forward
 * cache peramban. Terukur di lapangan: logout dari panel juri, tekan back, dan
 * panelnya muncul kembali lengkap dengan nama kedua atlet. `no-cache` saja
 * tidak mengatur bfcache -- hanya `no-store` yang melarang peramban
 * menyimpannya.
 */
it('melarang halaman pengguna yang login disimpan peramban', function () {
    $pengguna = App\Models\User::factory()->create();

    $balasan = $this->actingAs($pengguna)->get('/dashboard');

    expect($balasan->headers->get('Cache-Control'))->toContain('no-store');
});

/*
 * Yang tanpa sesi tidak ikut: overlay vMix dan live score penonton ditarik
 * berulang-ulang dan justru ingin boleh di-cache.
 */
it('tidak memasang no-store pada halaman publik', function () {
    $tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);

    expect($this->get("/live/turnamen/{$tournament->id}")->headers->get('Cache-Control'))
        ->not->toContain('no-store');
});
