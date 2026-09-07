<?php

namespace App\Broadcasting;

use App\Enums\ResourceAction;
use App\Models\User;
use App\Support\Resources\ResourceGate;

/**
 * Otorisasi channel privat satu penampilan Jurus -- dan channel battle-nya.
 *
 * Izinnya disamakan dengan panel yang mendengarkannya: siapa pun yang boleh
 * MELIHAT penilaian boleh mendengar perubahannya. Yang tidak boleh masuk
 * adalah official kontingen dan penonton -- mereka membaca hasil dari
 * halaman publik, bukan dari aliran perubahan gelanggang.
 *
 * Kelas, bukan closure di routes/channels.php, dengan alasan yang sama
 * seperti ArenaChannelAuthorizer: supaya bisa diuji tanpa merangkai
 * permintaan HTTP penuh. Methodnya WAJIB bernama `join`.
 */
class JurusPenampilanChannelAuthorizer
{
    public function __construct(private readonly ResourceGate $gate) {}

    /**
     * Penampilan/battle-nya sengaja tidak dicari ulang di sini.
     *
     * Yang dijaga channel ini adalah "boleh melihat penilaian di kejuaraan
     * ini", bukan "ditugaskan pada penampilan ini" -- persis seperti channel
     * gelanggang. Penugasan per-penampilan memang belum ada di Jurus, dan
     * memasangnya di sini akan menutup channel untuk juri yang sah tanpa satu
     * pun tempat di layar yang menjelaskan sebabnya.
     */
    public function join(User $user, int $id): bool
    {
        return $this->gate->any([
            rk('penilaian', ResourceAction::View),
            rk('partai', ResourceAction::View),
        ], $user);
    }
}
