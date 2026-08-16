<?php

use App\Enums\StatusTurnamen;

it('hanya mengizinkan perpindahan status searah', function () {
    expect(StatusTurnamen::Draf->bisaPindahKe(StatusTurnamen::Berjalan))->toBeTrue()
        ->and(StatusTurnamen::Berjalan->bisaPindahKe(StatusTurnamen::Selesai))->toBeTrue();
});

/*
 * Menarik kejuaraan yang sudah berjalan kembali ke draf akan membuka kunci
 * setelan peraturan sementara ada partai yang sudah dinilai memakai setelan
 * lama. Karena itu tidak ada jalan mundur sama sekali.
 */
it('menolak perpindahan status mundur', function () {
    expect(StatusTurnamen::Berjalan->bisaPindahKe(StatusTurnamen::Draf))->toBeFalse()
        ->and(StatusTurnamen::Selesai->bisaPindahKe(StatusTurnamen::Berjalan))->toBeFalse()
        ->and(StatusTurnamen::Selesai->transisiSah())->toBe([]);
});

it('mengizinkan setelan peraturan diubah hanya selama draf', function () {
    expect(StatusTurnamen::Draf->bolehUbahAturan())->toBeTrue()
        ->and(StatusTurnamen::Berjalan->bolehUbahAturan())->toBeFalse()
        ->and(StatusTurnamen::Selesai->bolehUbahAturan())->toBeFalse();
});
