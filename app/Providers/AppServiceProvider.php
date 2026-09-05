<?php

namespace App\Providers;

use App\Models\Penalty;
use App\Models\ScoreEvent;
use App\Observers\SinkronObserver;
use App\Observers\SnapshotSkorObserver;
use App\Support\Sinkron\PetaSinkron;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        foreach (PetaSinkron::MODEL as $model) {
            $model::observe(SinkronObserver::class);
        }
    }
}
