<?php

namespace App\Broadcasting;

use App\Enums\ResourceAction;
use App\Models\User;
use App\Support\Resources\ResourceGate;

/**
 * Otorisasi channel privat satu gelanggang.
 *
 * Ditulis sebagai kelas, bukan closure langsung di routes/channels.php,
 * supaya bisa diuji langsung tanpa harus menembus lapisan broadcaster HTTP
 * -- broadcaster default di lingkungan testing ('null') meregistrasi channel
 * saat boot dan tidak bisa ditukar belakangan lewat config(), jadi jalur
 * /broadcasting/auth tidak bisa dipakai menguji otorisasi ini secara andal.
 *
 * Operator, wasit, juri, dan dewan juri masing-masing menyandang resource
 * key yang berbeda, jadi izinnya dicek dengan ATAU, bukan satu key tunggal.
 */
class ArenaChannelAuthorizer
{
    public function __construct(private readonly ResourceGate $gate) {}

    /** @return array{id: int, name: string}|false */
    public function __invoke(User $user, int $arenaId): array|false
    {
        $boleh = $this->gate->any([
            rk('partai', ResourceAction::View),
            rk('penilaian', ResourceAction::View),
            rk('hukuman', ResourceAction::View),
        ], $user);

        return $boleh ? ['id' => $user->id, 'name' => $user->name] : false;
    }
}
