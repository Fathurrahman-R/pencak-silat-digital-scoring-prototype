<?php

/*
|--------------------------------------------------------------------------
| Saklar live score publik
|--------------------------------------------------------------------------
|
| Live score publik hanya berguna kalau ada yang menontonnya dari luar
| gelanggang -- lewat tunnel, atau lewat layar yang dipasang di lobi. Banyak
| kejuaraan berjalan tanpa keduanya, dan dalam keadaan itu setiap penekanan
| tombol juri tetap mendorong muatan ke channel `public-live.*` yang tidak
| didengarkan siapa pun.
|
| Bawaannya MATI, sama seperti OVERLAY_ENABLED, dan alasannya sama: beban
| siaran dinyalakan dengan sadar, tidak diwarisi diam-diam.
|
| Yang dimatikan HANYA halaman gelanggang realtime (/live/gelanggang/*) dan
| endpoint state-nya. Halaman turnamen, medali, dan bagan tetap hidup apa pun
| saklarnya -- ketiganya tidak memakai Echo sama sekali, jadi tidak membebani
| Reverb, dan penonton tetap butuh melihat hasil.
|
*/

return [
    // Dipaksa jadi boolean; alasan lengkapnya ada di config/overlay.php.
    'enabled' => filter_var(env('LIVE_SCORE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
