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

return [
    'allowed_cidrs' => array_values(array_filter(array_map(
        'trim',
        explode(',', env(
            'OVERLAY_ALLOWED_CIDRS',
            '127.0.0.1/32,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
        )),
    ))),
];
