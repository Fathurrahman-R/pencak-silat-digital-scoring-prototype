<?php

namespace App\Broadcasting;

use App\Enums\ResourceAction;
use App\Models\User;
use App\Support\Resources\ResourceGate;

/**
 * Otorisasi channel privat satu gelanggang.
 *
 * Ditulis sebagai kelas, bukan closure langsung di routes/channels.php,
 * supaya logikanya bisa diuji tanpa merangkai permintaan HTTP penuh.
 *
 * Methodnya WAJIB bernama `join`. Itu kontrak Laravel untuk channel berbasis
 * kelas, dan bukan sekadar selera penamaan: `__invoke` membuat setiap
 * langganan presence gagal dengan 500 sementara seluruh unit test yang
 * memanggil kelas ini sebagai fungsi tetap hijau. Lihat
 * tests/Feature/Scoring/ChannelAuthEndpointTest.php, yang sengaja menembus
 * broadcaster sungguhan supaya kontrak itu ikut teruji.
 *
 * Operator, wasit, juri, dan dewan juri masing-masing menyandang resource
 * key yang berbeda, jadi izinnya dicek dengan ATAU, bukan satu key tunggal.
 */
class ArenaChannelAuthorizer
{
    public function __construct(private readonly ResourceGate $gate) {}

    /** @return array{id: int, name: string}|false */
    public function join(User $user, int $arenaId): array|false
    {
        $boleh = $this->gate->any([
            rk('partai', ResourceAction::View),
            rk('penilaian', ResourceAction::View),
            rk('hukuman', ResourceAction::View),
        ], $user);

        return $boleh ? ['id' => $user->id, 'name' => $user->name] : false;
    }
}
