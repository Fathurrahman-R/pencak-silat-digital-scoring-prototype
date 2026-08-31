<?php

use App\Support\Unggah\BatasUnggah;

/**
 * Aplikasi menyatakan batas 4 MB sementara PHP hanya menerima 2 MB, jadi
 * berkas 3 MB ditolak dengan pesan yang tidak menyebut sebab apa pun.
 * Foto struk dan foto akta dari kamera HP rutin berada di rentang itu.
 *
 * Perbaikannya: batas yang berlaku dihitung dari yang paling kecil di antara
 * batas PHP dan batas yang diinginkan, lalu angkanya ikut disebut di pesan.
 */
it('memakai batas terkecil di antara PHP dan yang diinginkan', function () {
    expect(BatasUnggah::kilobyte(4096, batasPhp: 2048))->toBe(2048)
        ->and(BatasUnggah::kilobyte(4096, batasPhp: 8192))->toBe(4096)
        ->and(BatasUnggah::kilobyte(4096, batasPhp: 4096))->toBe(4096);
});

it('menyebut angka batasnya dalam megabyte yang bisa dibaca orang', function () {
    expect(BatasUnggah::label(2048))->toBe('2 MB')
        ->and(BatasUnggah::label(4096))->toBe('4 MB')
        ->and(BatasUnggah::label(1536))->toBe('1,5 MB');
});

it('membaca notasi ukuran php.ini', function () {
    expect(BatasUnggah::keKilobyte('2M'))->toBe(2048)
        ->and(BatasUnggah::keKilobyte('8M'))->toBe(8192)
        ->and(BatasUnggah::keKilobyte('1G'))->toBe(1048576)
        ->and(BatasUnggah::keKilobyte('512K'))->toBe(512)
        ->and(BatasUnggah::keKilobyte('1048576'))->toBe(1024);
});

it('tidak pernah mengembalikan batas nol saat PHP tak membatasi', function () {
    expect(BatasUnggah::kilobyte(4096, batasPhp: 0))->toBe(4096);
});
