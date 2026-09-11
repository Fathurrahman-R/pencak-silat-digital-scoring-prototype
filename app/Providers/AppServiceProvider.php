<?php

namespace App\Providers;

use App\Models\Penalty;
use App\Models\ScoreEvent;
use App\Observers\PenjagaTulisGlobal;
use App\Observers\SinkronObserver;
use App\Observers\SnapshotSkorObserver;
use App\Listeners\CatatPivotPeran;
use App\Support\Live\SiaranTahanBanting;
use App\Support\Sinkron\PetaSinkron;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Siaran yang gagal tidak menggagalkan aksinya -- alasan lengkapnya
         * di SiaranTahanBanting.
         *
         * Dipasang di boot(), bukan register(): BroadcastServiceProvider milik
         * framework mengikat manajernya SESUDAH provider aplikasi register,
         * jadi ikatan yang dipasang lebih awal ditimpa tanpa suara. Diperiksa
         * begitu -- closure yang tersimpan di container ternyata masih milik
         * framework.
         *
         * `forgetInstance` untuk yang telanjur diresolusi selama boot; entri
         * deferred dibuang supaya tidak ada yang memuat ulang providernya dan
         * menimpa ikatan ini lagi.
         */
        $this->app->singleton(BroadcastManager::class, fn ($app) => new SiaranTahanBanting($app));
        $this->app->alias(BroadcastManager::class, BroadcastingFactory::class);
        $this->app->singleton(
            BroadcasterContract::class,
            fn ($app) => $app->make(BroadcastManager::class)->connection(),
        );

        $this->app->forgetInstance(BroadcastManager::class);
        $this->app->forgetInstance(BroadcasterContract::class);
        $this->app->removeDeferredServices([
            BroadcastManager::class,
            BroadcastingFactory::class,
            BroadcasterContract::class,
        ]);

        /*
         * /live/* dibuka lewat tunnel ke internet publik -- lonjakan
         * penonton (atau siapa pun yang menemukan URL-nya) tidak boleh bisa
         * membebani mesin scoring yang sama juga dipakai gelanggang.
         * Per-IP, bukan per-user, karena rute ini tidak butuh login sama
         * sekali.
         */
        RateLimiter::for('live', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        /*
         * Snapshot skor dibuang dari sini, bukan dari tiap penerbit nilai.
         * Alasannya ada di App\Observers\SnapshotSkorObserver: jalur yang
         * mengubah nilai dan hukuman ada banyak, dan yang lupa membuang
         * snapshot tidak akan terlihat sebagai galat -- ia terlihat sebagai
         * skor yang berhenti bergerak setelah dewan juri membatalkan sesuatu.
         */
        ScoreEvent::observe(SnapshotSkorObserver::class);
        Penalty::observe(SnapshotSkorObserver::class);

        /*
         * Catatan perubahan untuk sinkron antar gelanggang, dipasang ke semua
         * model yang ikut disinkronkan sekaligus.
         *
         * Didaftarkan dari peta, bukan diketik satu per satu: daftarnya empat
         * puluh model, dan satu yang terlewat berarti satu tabel yang diam-diam
         * tidak pernah sampai ke node lain. Kelalaian seperti itu tidak muncul
         * sebagai galat -- ia muncul sebagai rekap medali yang angkanya tidak
         * cocok antar laptop, di hari terakhir kejuaraan.
         *
         * Observer-nya sendiri murah saat sinkron tidak dipakai: ia berhenti
         * di baris pertama begitu tahu barisnya bukan milik node ini, dan
         * pemasangan satu gelanggang tanpa peer tidak memiliki apa-apa.
         */
        foreach (PetaSinkron::MODEL as $tabel => $model) {
            $model::observe(SinkronObserver::class);

            /*
             * Data kejuaraan ditulis di node global saja. Penjagaannya dipasang
             * di model, bukan di controller: penerapan paket menulis lewat
             * query builder dan karena itu melewatinya dengan sendirinya --
             * node gelanggang tetap boleh MENERIMA data global, yang dihadang
             * cuma yang lahir dari layarnya sendiri.
             */
            if (in_array($tabel, PetaSinkron::GLOBAL, true)) {
                $model::observe(PenjagaTulisGlobal::class);
            }
        }

        /*
         * Pivot peran tidak punya model, jadi tidak pernah lewat observer.
         * Tanpa listener ini, akun berpindah antar node tanpa perannya.
         */
        Event::listen(RoleAttachedEvent::class, [CatatPivotPeran::class, 'handleRoleAttached']);
        Event::listen(RoleDetachedEvent::class, [CatatPivotPeran::class, 'handleRoleDetached']);
        Event::listen(PermissionAttachedEvent::class, [CatatPivotPeran::class, 'handlePermissionAttached']);
        Event::listen(PermissionDetachedEvent::class, [CatatPivotPeran::class, 'handlePermissionDetached']);
    }
}
