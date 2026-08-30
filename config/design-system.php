<?php

return [

    /*
    |---------------------------------------------------------------------
    | Halaman peraga
    |---------------------------------------------------------------------
    |
    | Halaman peraga merender komponen Blade yang sama dengan yang dipakai
    | aplikasi -- bukan salinan statis -- jadi isinya selalu ikut berubah saat
    | komponennya berubah.
    |
    | Kalau dimatikan, route-nya tidak didaftarkan sama sekali — halamannya
    | membalas 404, bukan 403. Bawaannya aktif di mana pun kecuali produksi.
    |
    */

    // Catatan: berkas config dimuat sebelum environment aplikasi ditentukan,
    // jadi APP_ENV dibaca langsung dari env — bukan lewat app()->isProduction().
    'enabled' => env('DESIGN_SYSTEM_ENABLED', env('APP_ENV', 'production') !== 'production'),

];
