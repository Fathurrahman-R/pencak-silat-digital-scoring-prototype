<?php

/*
|--------------------------------------------------------------------------
| Jaringan yang boleh membuka /overlay/*
|--------------------------------------------------------------------------
|
| Rute overlay tidak pernah melewati middleware 'auth' -- Web Browser Input
| vMix tidak bisa login. Pembatasan jaringan ini satu-satunya pengamannya,
| jadi jangan diperlonggar hanya karena test IP tidak sesuai; sesuaikan
| OVERLAY_ALLOWED_CIDRS di .env untuk jaringan gelanggang yang sebenarnya.
|
| Bawaannya mencakup localhost dan tiga rentang IP privat RFC 1918 -- cukup
| untuk topologi "vMix dan server Laravel di mesin yang sama" atau di LAN
| gelanggang mana pun tanpa perlu diatur ulang.
|
*/

/*
|--------------------------------------------------------------------------
| Saklar overlay siaran
|--------------------------------------------------------------------------
|
| Overlay vMix bukan kebutuhan tiap kejuaraan. Selama saklar ini menyala,
| kelima event siaran mendorong muatannya ke DUA channel Reverb sekaligus --
| presence untuk panel juri/wasit/operator, dan `public-live.*` untuk overlay
| dan live score. Kejuaraan tanpa vMix membayar channel kedua itu penuh pada
| tiap penekanan tombol juri tanpa satu pun pendengar.
|
| Bawaannya MATI: fitur yang membebani siaran harus dinyalakan dengan sadar,
| bukan diwarisi diam-diam oleh instalasi baru. Halamannya tidak hilang saat
| mati -- ia merender halaman yang menjelaskan dirinya sendiri, dan endpoint
| state-nya membalas 503 tanpa menyentuh database.
|
| Channel `public-live.*` baru benar-benar berhenti saat saklar ini DAN
| LIVE_SCORE_ENABLED sama-sama mati; lihat App\Support\Live\SaluranArena.
|
*/

return [
    // Dipaksa jadi boolean dengan alasan yang sama seperti
    // config/design-system.php: nilai env tidak selalu sampai sebagai boolean.
    // Dari .env Laravel menerjemahkan "false" sendiri, tapi dari <env> di
    // phpunit.xml yang tiba berupa string "1" -- dan nilai seperti itu lolos
    // sebagai "aktif" di satu tempat sekaligus gagal pada pemeriksaan yang
    // menuntut boolean di tempat lain.
    'enabled' => filter_var(env('OVERLAY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'allowed_cidrs' => array_values(array_filter(array_map(
        'trim',
        explode(',', env(
            'OVERLAY_ALLOWED_CIDRS',
            '127.0.0.1/32,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
        )),
    ))),
];
