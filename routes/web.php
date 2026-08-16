<?php

use App\Enums\ResourceAction;
use App\Http\Controllers\Admin\AparatController;
use App\Http\Controllers\Admin\ArenaController;
use App\Http\Controllers\Admin\AthleteController;
use App\Http\Controllers\Admin\BracketController;
use App\Http\Controllers\Admin\ContingentController;
use App\Http\Controllers\Admin\FeeScheduleController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\JadwalController;
use App\Http\Controllers\Admin\PartaiScoringController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\ResourceMappingController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Admin\TournamentRuleController;
use App\Http\Controllers\Admin\TreasuryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Admin\WeightInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Dokumentasi design system
|--------------------------------------------------------------------------
|
| Sengaja tidak didaftarkan sama sekali kalau dimatikan, supaya di produksi
| tidak ada permukaan tambahan yang perlu dijaga. Halamannya tidak menyentuh
| database dan tidak butuh login, jadi tetap bisa dibuka di project baru yang
| seedernya belum dijalankan.
|
*/
if (config('design-system.enabled')) {
    Route::controller(DesignSystemController::class)
        ->prefix('design-system')
        ->name('design-system.')
        ->group(function () {
            Route::get('/', 'foundation')->name('foundation');
            Route::get('/komponen', 'components')->name('components');
            Route::get('/pola', 'patterns')->name('patterns');
            Route::get('/layar/{screen}', 'screen')->name('screen');
        });

    /*
     * Peraga design system gelanggang. Sejajar dengan /design-system milik
     * admin, tapi memakai bundel dan token yang benar-benar terpisah — halaman
     * ini tidak memuat app.css sama sekali, sehingga kalau ada token silat yang
     * bocor ke RizzxxUI (atau sebaliknya), akan langsung kelihatan di sini.
     */
    Route::view('/design-system/gelanggang', 'silat.peraga')->name('design-system.gelanggang');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('profil')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('avatar');
        Route::delete('/avatar', [ProfileController::class, 'destroyAvatar'])->name('avatar.destroy');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Panel admin
    |--------------------------------------------------------------------------
    |
    | Setiap route dijaga resource key lewat middleware `resource`. Koma berarti
    | DAN, garis tegak berarti ATAU. Key-nya sama persis dengan yang dipakai di
    | Blade dan menu, jadi satu perubahan pemetaan berlaku di semua tempat.
    |
    */
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::controller(UserController::class)->prefix('users')->name('users.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('resource:'.rk('users', ResourceAction::View));
            Route::get('/create', 'create')->name('create')->middleware('resource:'.rk('users', ResourceAction::Create));
            Route::post('/', 'store')->name('store')->middleware('resource:'.rk('users', ResourceAction::Create));
            Route::get('/export', 'export')->name('export')->middleware('resource:'.rk('users', ResourceAction::Export));
            Route::get('/{user}/panel', 'panel')->name('panel')->middleware('resource:'.rk('users', ResourceAction::View));
            Route::get('/{user}/edit', 'edit')->name('edit')->middleware('resource:'.rk('users', ResourceAction::Update));
            Route::put('/{user}', 'update')->name('update')->middleware('resource:'.rk('users', ResourceAction::Update));
            Route::post('/bulk-destroy', 'bulkDestroy')->name('bulk-destroy')->middleware('resource:'.rk('users', ResourceAction::Delete));
            Route::delete('/{user}', 'destroy')->name('destroy')->middleware('resource:'.rk('users', ResourceAction::Delete));
        });

        Route::controller(RoleController::class)->prefix('roles')->name('roles.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('resource:'.rk('roles', ResourceAction::View));
            Route::get('/create', 'create')->name('create')->middleware('resource:'.rk('roles', ResourceAction::Create));
            Route::post('/', 'store')->name('store')->middleware('resource:'.rk('roles', ResourceAction::Create));
            Route::get('/{role}/panel', 'panel')->name('panel')->middleware('resource:'.rk('roles', ResourceAction::View));
            Route::get('/{role}/edit', 'edit')->name('edit')->middleware('resource:'.rk('roles', ResourceAction::Update));
            Route::put('/{role}', 'update')->name('update')->middleware('resource:'.rk('roles', ResourceAction::Update));
            Route::post('/bulk-destroy', 'bulkDestroy')->name('bulk-destroy')->middleware('resource:'.rk('roles', ResourceAction::Delete));
            Route::delete('/{role}', 'destroy')->name('destroy')->middleware('resource:'.rk('roles', ResourceAction::Delete));
        });

        Route::controller(PermissionController::class)->prefix('permissions')->name('permissions.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('resource:'.rk('permissions', ResourceAction::View));
            Route::get('/create', 'create')->name('create')->middleware('resource:'.rk('permissions', ResourceAction::Create));
            Route::post('/', 'store')->name('store')->middleware('resource:'.rk('permissions', ResourceAction::Create));
            Route::get('/{permission}/edit', 'edit')->name('edit')->middleware('resource:'.rk('permissions', ResourceAction::Update));
            Route::put('/{permission}', 'update')->name('update')->middleware('resource:'.rk('permissions', ResourceAction::Update));
            Route::post('/bulk-destroy', 'bulkDestroy')->name('bulk-destroy')->middleware('resource:'.rk('permissions', ResourceAction::Delete));
            Route::delete('/{permission}', 'destroy')->name('destroy')->middleware('resource:'.rk('permissions', ResourceAction::Delete));
        });

        Route::controller(ResourceController::class)->prefix('resources')->name('resources.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('resource:'.rk('resources', ResourceAction::View));
            Route::get('/create', 'create')->name('create')->middleware('resource:'.rk('resources', ResourceAction::Create));
            Route::post('/', 'store')->name('store')->middleware('resource:'.rk('resources', ResourceAction::Create));
            Route::get('/{resource}', 'show')->name('show')->middleware('resource:'.rk('resources', ResourceAction::View));
            Route::get('/{resource}/edit', 'edit')->name('edit')->middleware('resource:'.rk('resources', ResourceAction::Update));
            Route::put('/{resource}', 'update')->name('update')->middleware('resource:'.rk('resources', ResourceAction::Update));
            Route::post('/bulk-destroy', 'bulkDestroy')->name('bulk-destroy')->middleware('resource:'.rk('resources', ResourceAction::Delete));
            Route::delete('/{resource}', 'destroy')->name('destroy')->middleware('resource:'.rk('resources', ResourceAction::Delete));
        });

        Route::controller(ResourceMappingController::class)->prefix('mappings')->name('mappings.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('resource:'.rk('mappings', ResourceAction::View));
            Route::put('/{mapping}', 'update')->name('update')->middleware('resource:'.rk('mappings', ResourceAction::Update));
            Route::delete('/{mapping}', 'destroy')->name('destroy')->middleware('resource:'.rk('mappings', ResourceAction::Update));
            Route::post('/auto', 'autoMap')->name('auto')->middleware('resource:'.rk('mappings', ResourceAction::Update));
        });

        /*
        |----------------------------------------------------------------------
        | Kejuaraan dan gelanggang
        |----------------------------------------------------------------------
        |
        | Gelanggang bersarang di bawah kejuaraan karena memang tidak pernah
        | berdiri sendiri, dan dijaga resource key-nya sendiri: panitia yang
        | boleh menyusun jadwal gelanggang belum tentu boleh membuat kejuaraan.
        |
        */
        Route::controller(TournamentController::class)->prefix('turnamen')->name('turnamen.')->group(function () {
            Route::get('/', 'index')->name('index')->middleware('resource:'.rk('turnamen', ResourceAction::View));
            Route::get('/create', 'create')->name('create')->middleware('resource:'.rk('turnamen', ResourceAction::Create));
            Route::post('/', 'store')->name('store')->middleware('resource:'.rk('turnamen', ResourceAction::Create));
            Route::get('/export', 'export')->name('export')->middleware('resource:'.rk('turnamen', ResourceAction::Export));
            Route::get('/{tournament}/panel', 'panel')->name('panel')->middleware('resource:'.rk('turnamen', ResourceAction::View));
            Route::get('/{tournament}/edit', 'edit')->name('edit')->middleware('resource:'.rk('turnamen', ResourceAction::Update));
            Route::put('/{tournament}', 'update')->name('update')->middleware('resource:'.rk('turnamen', ResourceAction::Update));
            Route::patch('/{tournament}/status', 'updateStatus')->name('status')->middleware('resource:'.rk('turnamen', ResourceAction::Update));
            Route::post('/bulk-destroy', 'bulkDestroy')->name('bulk-destroy')->middleware('resource:'.rk('turnamen', ResourceAction::Delete));
            Route::delete('/{tournament}', 'destroy')->name('destroy')->middleware('resource:'.rk('turnamen', ResourceAction::Delete));

            /*
             * Setelan peraturan punya resource key sendiri: yang boleh
             * menyunting jadwal kejuaraan belum tentu boleh mengubah nilai
             * teknik dan tangga hukuman.
             */
            Route::controller(TournamentRuleController::class)
                ->prefix('{tournament}/peraturan')
                ->name('peraturan.')
                ->group(function () {
                    Route::get('/', 'edit')->name('edit')->middleware('resource:'.rk('peraturan-turnamen', ResourceAction::View));
                    Route::put('/', 'update')->name('update')->middleware('resource:'.rk('peraturan-turnamen', ResourceAction::Update));
                    Route::post('/reset', 'reset')->name('reset')->middleware('resource:'.rk('peraturan-turnamen', ResourceAction::Update));
                });

            /*
             * Kontingen dan atlet.
             *
             * Satu set halaman melayani dua peran sekaligus: panitia yang
             * melihat semua kontingen, dan official yang hanya melihat
             * miliknya. Pembatasannya di ScopesContingents, bukan di route,
             * supaya tidak ada dua tampilan yang harus dijaga sinkron.
             */
            Route::controller(ContingentController::class)
                ->prefix('{tournament}/kontingen')
                ->name('kontingen.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('kontingen', ResourceAction::View));
                    Route::get('/create', 'create')->name('create')->middleware('resource:'.rk('kontingen', ResourceAction::Create));
                    Route::post('/', 'store')->name('store')->middleware('resource:'.rk('kontingen', ResourceAction::Create));
                    Route::get('/{contingent}/panel', 'panel')->name('panel')->middleware('resource:'.rk('kontingen', ResourceAction::View));
                    Route::get('/{contingent}/edit', 'edit')->name('edit')->middleware('resource:'.rk('kontingen', ResourceAction::Update));
                    Route::put('/{contingent}', 'update')->name('update')->middleware('resource:'.rk('kontingen', ResourceAction::Update));
                    Route::delete('/{contingent}', 'destroy')->name('destroy')->middleware('resource:'.rk('kontingen', ResourceAction::Delete));
                });

            Route::controller(AthleteController::class)
                ->prefix('{tournament}/kontingen/{contingent}/atlet')
                ->name('kontingen.atlet.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('atlet', ResourceAction::View));
                    Route::post('/', 'store')->name('store')->middleware('resource:'.rk('atlet', ResourceAction::Create));
                    Route::put('/{athlete}', 'update')->name('update')->middleware('resource:'.rk('atlet', ResourceAction::Update));
                    Route::delete('/{athlete}', 'destroy')->name('destroy')->middleware('resource:'.rk('atlet', ResourceAction::Delete));

                    Route::post('/{athlete}/berkas', 'storeDocument')->name('berkas.store')->middleware('resource:'.rk('atlet', ResourceAction::Update));
                    Route::get('/{athlete}/berkas/{document}', 'showDocument')->name('berkas.show')->middleware('resource:'.rk('atlet', ResourceAction::View));
                    Route::delete('/{athlete}/berkas/{document}', 'destroyDocument')->name('berkas.destroy')->middleware('resource:'.rk('atlet', ResourceAction::Update));
                });

            Route::controller(RegistrationController::class)
                ->prefix('{tournament}/kontingen/{contingent}/pendaftaran')
                ->name('kontingen.pendaftaran.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('pendaftaran', ResourceAction::View));
                    Route::post('/tanding', 'storeTanding')->name('tanding')->middleware('resource:'.rk('pendaftaran', ResourceAction::Create));
                    Route::post('/jurus', 'storeJurus')->name('jurus')->middleware('resource:'.rk('pendaftaran', ResourceAction::Create));
                    Route::post('/{registration}/ajukan', 'submit')->name('ajukan')->middleware('resource:'.rk('pendaftaran', ResourceAction::Update));
                    Route::delete('/{registration}', 'destroy')->name('destroy')->middleware('resource:'.rk('pendaftaran', ResourceAction::Delete));
                });

            Route::controller(FeeScheduleController::class)
                ->prefix('{tournament}/tarif')
                ->name('tarif.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('tarif', ResourceAction::View));
                    Route::post('/', 'store')->name('store')->middleware('resource:'.rk('tarif', ResourceAction::Update));
                    Route::post('/kontingen', 'storeKontingen')->name('kontingen')->middleware('resource:'.rk('tarif', ResourceAction::Update));
                    Route::delete('/{feeSchedule}', 'destroy')->name('destroy')->middleware('resource:'.rk('tarif', ResourceAction::Update));
                });

            Route::controller(VerificationController::class)
                ->prefix('{tournament}/verifikasi')
                ->name('verifikasi.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('pendaftaran', ResourceAction::View));
                    Route::post('/{registration}/setujui', 'setujui')->name('setujui')->middleware('resource:'.rk('pendaftaran', ResourceAction::Approve));
                    Route::post('/{registration}/tolak', 'tolak')->name('tolak')->middleware('resource:'.rk('pendaftaran', ResourceAction::Reject));
                    Route::post('/{registration}/tinjau-ulang', 'tinjauUlang')->name('tinjau-ulang')->middleware('resource:'.rk('pendaftaran', ResourceAction::Approve));
                });

            Route::controller(TreasuryController::class)
                ->prefix('{tournament}/bendahara')
                ->name('bendahara.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('invoice', ResourceAction::View));
                    Route::get('/export', 'export')->name('export')->middleware('resource:'.rk('invoice', ResourceAction::Export));
                    Route::post('/{invoice}/lunas', 'tandaiLunas')->name('lunas')->middleware('resource:'.rk('invoice', ResourceAction::Approve));
                    Route::get('/{invoice}/bukti/{pembayaran}', 'bukti')->name('bukti')->middleware('resource:'.rk('invoice', ResourceAction::View));
                });

            Route::controller(InvoiceController::class)
                ->prefix('{tournament}/kontingen/{contingent}/tagihan')
                ->name('kontingen.tagihan.')
                ->group(function () {
                    Route::get('/', 'show')->name('show')->middleware('resource:'.rk('invoice', ResourceAction::View));
                    Route::post('/kunci', 'kunci')->name('kunci')->middleware('resource:'.rk('invoice', ResourceAction::Update));
                    Route::post('/batal', 'batal')->name('batal')->middleware('resource:'.rk('invoice', ResourceAction::Update));
                });

            Route::controller(WeightInController::class)
                ->prefix('{tournament}/timbang')
                ->name('timbang.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('timbang-badan', ResourceAction::View));
                    Route::post('/{registration}', 'store')->name('store')->middleware('resource:'.rk('timbang-badan', ResourceAction::Create));
                });

            Route::controller(ArenaController::class)
                ->prefix('{tournament}/gelanggang')
                ->name('gelanggang.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('gelanggang', ResourceAction::View));
                    Route::post('/', 'store')->name('store')->middleware('resource:'.rk('gelanggang', ResourceAction::Create));
                    Route::put('/{arena}', 'update')->name('update')->middleware('resource:'.rk('gelanggang', ResourceAction::Update));
                    Route::delete('/{arena}', 'destroy')->name('destroy')->middleware('resource:'.rk('gelanggang', ResourceAction::Delete));
                });

            /*
             * Bagan dikunci secara sengaja tegas: menyusun dan menukar tempat
             * hanya bisa selagi belum dikunci, dan membukanya kembali
             * dianggap seberat menghapus — makanya dijaga aksi Delete, bukan
             * Update seperti aksi biasa.
             */
            Route::controller(BracketController::class)
                ->prefix('{tournament}/bagan')
                ->name('bagan.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('bagan', ResourceAction::View));
                    Route::post('/{weightClass}/susun', 'susun')->name('susun')->middleware('resource:'.rk('bagan', ResourceAction::Create));
                    Route::get('/{weightClass}', 'show')->name('show')->middleware('resource:'.rk('bagan', ResourceAction::View));
                    Route::post('/{weightClass}/tukar', 'tukar')->name('tukar')->middleware('resource:'.rk('bagan', ResourceAction::Update));
                    Route::post('/{weightClass}/kunci', 'kunci')->name('kunci')->middleware('resource:'.rk('bagan', ResourceAction::Update));
                    Route::post('/{weightClass}/buka-kunci', 'bukaKunci')->name('buka-kunci')->middleware('resource:'.rk('bagan', ResourceAction::Delete));
                });

            Route::controller(JadwalController::class)
                ->prefix('{tournament}/jadwal')
                ->name('jadwal.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('jadwal', ResourceAction::View));
                    Route::post('/{match}/tetapkan', 'tetapkan')->name('tetapkan')->middleware('resource:'.rk('jadwal', ResourceAction::Assign));
                    Route::post('/{match}/lepas', 'lepas')->name('lepas')->middleware('resource:'.rk('jadwal', ResourceAction::Assign));
                    Route::post('/{match}/urutkan', 'urutkan')->name('urutkan')->middleware('resource:'.rk('jadwal', ResourceAction::Assign));
                });

            Route::controller(AparatController::class)
                ->prefix('{tournament}/partai/{match}/aparat')
                ->name('partai.aparat.')
                ->group(function () {
                    Route::get('/', 'show')->name('show')->middleware('resource:'.rk('penugasan-aparat', ResourceAction::View));
                    Route::post('/', 'store')->name('store')->middleware('resource:'.rk('penugasan-aparat', ResourceAction::Assign));
                });

            /*
             * Mesin scoring Tanding. `akhiri` dijaga resource Manage
             * (bukan Update seperti kendali timer biasa) karena mengakhiri
             * partai itu tindakan sekali jalan yang tidak bisa dibatalkan
             * lewat tombol yang sama. `sahkan` dan pembatalan nilai/hukuman
             * dijaga resource hasil-partai -- wewenang dewan juri, bukan
             * operator gelanggang.
             */
            Route::controller(PartaiScoringController::class)
                ->prefix('{tournament}/partai/{match}')
                ->name('partai.')
                ->group(function () {
                    Route::get('/', 'state')->name('state')->middleware('resource:'.rk('partai', ResourceAction::View));
                    Route::get('/operator', 'operator')->name('operator')->middleware('resource:'.rk('partai', ResourceAction::View));
                    Route::get('/wasit', 'wasit')->name('wasit')->middleware('resource:'.rk('hukuman', ResourceAction::View));
                    Route::get('/dewan-juri', 'dewanJuri')->name('dewan-juri')->middleware('resource:'.rk('hasil-partai', ResourceAction::View));
                    Route::get('/juri', 'juri')->name('juri')->middleware('resource:'.rk('penilaian', ResourceAction::Create));
                    Route::get('/juri/manifest.webmanifest', 'manifest')->name('juri.manifest')->middleware('resource:'.rk('penilaian', ResourceAction::Create));

                    Route::post('/timer/mulai', 'mulaiBabak')->name('timer.mulai')->middleware('resource:'.rk('partai', ResourceAction::Update));
                    Route::post('/timer/jeda', 'jeda')->name('timer.jeda')->middleware('resource:'.rk('partai', ResourceAction::Update));
                    Route::post('/timer/lanjut', 'lanjutkan')->name('timer.lanjut')->middleware('resource:'.rk('partai', ResourceAction::Update));
                    Route::post('/timer/reset', 'reset')->name('timer.reset')->middleware('resource:'.rk('partai', ResourceAction::Update));
                    Route::post('/timer/selesai-babak', 'selesaikanBabak')->name('timer.selesai-babak')->middleware('resource:'.rk('partai', ResourceAction::Update));

                    Route::post('/akhiri', 'akhiri')->name('akhiri')->middleware('resource:'.rk('partai', ResourceAction::Manage));
                    Route::post('/sahkan', 'sahkan')->name('sahkan')->middleware('resource:'.rk('hasil-partai', ResourceAction::Approve));

                    Route::post('/nilai', 'nilai')->name('nilai')->middleware('resource:'.rk('penilaian', ResourceAction::Create));
                    Route::post('/nilai/{scoreEvent}/batal', 'batalkanNilai')->name('nilai.batal')->middleware('resource:'.rk('hasil-partai', ResourceAction::Update));

                    Route::post('/hukuman', 'hukuman')->name('hukuman')->middleware('resource:'.rk('hukuman', ResourceAction::Create));
                    Route::post('/hukuman/{penalty}/batal', 'batalkanHukuman')->name('hukuman.batal')->middleware('resource:'.rk('hasil-partai', ResourceAction::Update));
                    Route::post('/hitungan', 'hitungan')->name('hitungan')->middleware('resource:'.rk('hukuman', ResourceAction::Create));
                });
        });

    });
});

// Route auth (login, register, reset password, verifikasi email, 2FA)
// didaftarkan otomatis oleh Fortify — lihat config/fortify.php untuk
// menyalakan atau mematikan fiturnya.
