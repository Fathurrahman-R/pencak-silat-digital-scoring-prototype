<?php

use App\Models\Arena;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Live\SiaranTahanBanting;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
 * Reverb mati di tengah acara.
 *
 * Seluruh event gelanggang `ShouldBroadcastNow` -- tanpa worker antrean, demi
 * latensi. Artinya siaran berjalan di dalam permintaan yang menulis
 * perubahannya, dan kalau Reverb tidak menjawab, cURL melempar SESUDAH
 * penulisan commit. Yang menekan tombolnya membaca 422 atas aksi yang
 * sebenarnya berhasil, lalu menekannya lagi.
 *
 * Ditemukan di peramban saat blok J uji kotak hitam dijalankan tanpa
 * `reverb:start`: pointer gelanggang berpindah, panel membalas
 * "Pusher error: cURL error 28: Connection timed out".
 *
 * Uji ini tidak memeriksa Reverb. Ia memeriksa satu sifat: pengirim siaran
 * yang melempar tidak boleh menjalar keluar.
 */

it('menelan kegagalan siaran, dan mencatatnya', function () {
    Broadcast::extend('meledak', fn () => new class implements Broadcaster
    {
        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = [])
        {
            throw new BroadcastException('cURL error 28: Connection timed out after 1002 milliseconds');
        }
    });

    config(['broadcasting.default' => 'meledak', 'broadcasting.connections.meledak' => ['driver' => 'meledak']]);

    Log::spy();

    $manajer = new SiaranTahanBanting(app());

    $tournament = Tournament::factory()->create();
    $arena = Arena::factory()->for($tournament)->create();
    $user = User::factory()->create();

    // Tidak melempar keluar -- itulah seluruh isi janjinya.
    $manajer->queue(new App\Events\Gelanggang\PartaiAktifBerubah($arena, null, null));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $pesan, array $konteks = []) => str_contains($pesan, 'Siaran gagal')
            && str_contains($konteks['galat'] ?? '', 'cURL error 28'));

    expect($user->exists)->toBeTrue();
});

/*
 * Dan yang sebaliknya: manajer ini tidak boleh MEMBISUKAN siaran yang sehat.
 * Kalau ia menelan segalanya, seluruh papan berhenti berkedip tanpa satu pun
 * uji yang merah.
 */
it('meneruskan siaran yang sehat apa adanya', function () {
    // ArrayObject, bukan array: closure menangkap array BY VALUE, dan
    // penampungnya lalu tetap kosong walau siarannya jalan -- uji yang
    // kelihatan merah karena alasan yang salah.
    $terkirim = new ArrayObject;

    Broadcast::extend('catat', fn () => new class($terkirim) implements Broadcaster
    {
        public function __construct(private ArrayObject $catatan) {}

        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = [])
        {
            $this->catatan[] = $event;
        }
    });

    config(['broadcasting.default' => 'catat', 'broadcasting.connections.catat' => ['driver' => 'catat']]);

    $tournament = Tournament::factory()->create();
    $arena = Arena::factory()->for($tournament)->create();

    (new SiaranTahanBanting(app()))->queue(new App\Events\Gelanggang\PartaiAktifBerubah($arena, null, null));

    expect($terkirim)->toHaveCount(1);
});

/*
 * Dan yang paling mudah hilang tanpa suara: pemasangannya. Kelas ini bisa
 * berdiri sempurna sambil tidak dipakai siapa pun -- itu yang terjadi pada
 * percobaan pertama, karena BroadcastServiceProvider milik framework mengikat
 * manajernya SESUDAH provider aplikasi register.
 */
it('memasang manajer itu sebagai manajer siaran aplikasi', function () {
    expect(app(Illuminate\Contracts\Broadcasting\Factory::class))
        ->toBeInstanceOf(SiaranTahanBanting::class)
        ->and(app(Illuminate\Broadcasting\BroadcastManager::class))
        ->toBeInstanceOf(SiaranTahanBanting::class);
});
