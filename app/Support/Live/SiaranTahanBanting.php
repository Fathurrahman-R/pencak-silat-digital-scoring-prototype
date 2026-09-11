<?php

namespace App\Support\Live;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Siaran yang gagal tidak boleh menggagalkan aksi yang sudah tercatat.
 *
 * # Apa yang terjadi tanpa ini
 *
 * Seluruh event gelanggang berupa `ShouldBroadcastNow` -- sengaja, karena di
 * venue tidak ada worker antrean yang menjaga latensi tetap di bawah setengah
 * detik. Akibatnya siaran berjalan DI DALAM permintaan yang menulis
 * perubahannya. Kalau Reverb mati, cURL menunggu satu detik lalu melempar,
 * dan pengendali membaca:
 *
 *     422 "Pusher error: cURL error 28: Connection timed out ..."
 *
 * padahal pointer gelanggang SUDAH berpindah -- penulisannya sudah commit
 * sebelum siaran dicoba. Jadi layar bilang gagal, basis data bilang berhasil,
 * dan yang menekan tombolnya menekannya lagi. Ditemukan di peramban saat blok
 * J uji kotak hitam dijalankan tanpa `reverb:start`, 10 September 2026.
 *
 * # Kenapa boleh ditelan
 *
 * Siaran di sini PEMERCEPAT, bukan satu-satunya jalan: tiap panel tetap
 * menarik `state` secara berkala, jadi layar yang kehilangan satu siaran
 * menyusul sendiri beberapa detik kemudian. Yang tidak bisa disusul adalah
 * aksi yang ditolak padahal sudah terjadi.
 *
 * Ditelan, bukan dibisukan: tiap kegagalan tercatat di log dengan nama
 * event-nya, supaya "papan tidak pernah berkedip" bisa dilacak ke sebabnya.
 */
class SiaranTahanBanting extends BroadcastManager
{
    /** @param  mixed  $event */
    public function queue($event): void
    {
        try {
            parent::queue($event);
        } catch (Throwable $e) {
            Log::warning('Siaran gagal dikirim; aksinya tetap tercatat.', [
                'event' => is_object($event) ? $event::class : gettype($event),
                'galat' => $e->getMessage(),
            ]);
        }
    }
}
