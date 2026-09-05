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
use App\Http\Controllers\Admin\JurusScoringController;
use App\Http\Controllers\Admin\KetuaPertandinganController;
use App\Http\Controllers\Admin\PanelGelanggangController;
use App\Http\Controllers\Admin\PartaiScoringController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\ResourceMappingController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SiaranController;
use App\Http\Controllers\Admin\SinkronController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Admin\TournamentRuleController;
use App\Http\Controllers\Admin\TreasuryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VarController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Admin\VerifikasiJuriController;
use App\Http\Controllers\Admin\WeightInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\BerandaController;
use Illuminate\Support\Facades\Route;

Route::get('/', BerandaController::class)->name('home');

/*
|--------------------------------------------------------------------------
| Peraga komponen
|--------------------------------------------------------------------------
|
| Sengaja tidak didaftarkan sama sekali kalau dimatikan, supaya di produksi
| tidak ada permukaan tambahan yang perlu dijaga. Halamannya tidak menyentuh
| database dan tidak butuh login, jadi tetap bisa dibuka di project baru yang
| seedernya belum dijalankan.
|
| Dua halaman, dan keduanya sengaja terpisah: yang pertama memakai bundel
| admin (terang), yang kedua memakai bundel silat (gelap) dan tidak memuat
| app.css sama sekali. Token yang bocor antar-bundel langsung kelihatan.
|
| Empat halaman peraga RizzxxUI beserta lima layar contohnya sudah dihapus:
| lapisan `ui/` yang mereka peragakan tidak dipanggil satu layar pun lagi.
|
*/
if (config('design-system.enabled')) {
    Route::view('/design-system', 'design-system.si')->name('design-system.si');
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
        /*
         * Sinkron gelanggang TIDAK berada di bawah {tournament}.
         *
         * Yang diaturnya adalah sifat MESIN ini -- gelanggang mana yang
         * dipegangnya, laptop mana tetangganya, sudah sampai mana
         * pertukarannya -- dan itu tidak berubah saat panitia berpindah
         * kejuaraan. Menaruhnya di bawah turnamen akan menyarankan bahwa tiap
         * kejuaraan punya daftar peer sendiri, yang tidak benar dan akan
         * dijalankan orang sebagai kalau benar.
         *
         * Rute yang dipanggil PEER ada di routes/sinkron.php, dijaga token,
         * bukan di sini. Yang ini dipanggil operator yang sudah login.
         */
        Route::controller(SinkronController::class)->prefix('sinkron')->name('sinkron.')->group(function () {
            Route::get('/', 'index')->name('index')
                ->middleware('resource:'.rk('sinkron-gelanggang', ResourceAction::View));
            Route::post('/tarik', 'tarik')->name('tarik')
                ->middleware('resource:'.rk('sinkron-gelanggang', ResourceAction::Update));
        });

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
            Route::post('/{tournament}/buka', 'buka')->name('buka')->middleware('resource:'.rk('turnamen', ResourceAction::View));
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
                    Route::post('/{arena}/operator', 'simpanOperator')->name('operator')->middleware('resource:'.rk('gelanggang', ResourceAction::Update));
                    Route::post('/{arena}/pengendali', 'simpanPengendali')->name('pengendali')->middleware('resource:'.rk('gelanggang', ResourceAction::Update));
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
                    Route::get('/{weightClass}/cetak', 'cetak')->name('cetak')->middleware('resource:'.rk('bagan', ResourceAction::Print));
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
                    Route::get('/cetak', 'cetak')->name('cetak')->middleware('resource:'.rk('jadwal', ResourceAction::Print));
                    Route::post('/{match}/tetapkan', 'tetapkan')->name('tetapkan')->middleware('resource:'.rk('jadwal', ResourceAction::Assign));
                    Route::post('/{match}/lepas', 'lepas')->name('lepas')->middleware('resource:'.rk('jadwal', ResourceAction::Assign));
                    Route::post('/{match}/urutkan', 'urutkan')->name('urutkan')->middleware('resource:'.rk('jadwal', ResourceAction::Assign));
                    /*
                     * Memindahkan langsung ke urutan tujuan. `urutkan` menukar
                     * dengan tetangga sebelah -- memindahkan partai dari urutan
                     * 14 ke 2 lewat jalur itu berarti dua belas permintaan dan
                     * dua belas pemuatan ulang halaman.
                     */
                    Route::post('/{match}/pindahkan', 'pindahkan')->name('pindahkan')->middleware('resource:'.rk('jadwal', ResourceAction::Assign));
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
                    Route::get('/keberatan', 'keberatan')->name('keberatan')->middleware('resource:'.rk('var', ResourceAction::View));
                    Route::get('/juri', 'juri')->name('juri')->middleware('resource:'.rk('penilaian', ResourceAction::Create));
                    Route::get('/juri/manifest.webmanifest', 'manifest')->name('juri.manifest')->middleware('resource:'.rk('penilaian', ResourceAction::Create));
                    Route::get('/berita-acara', 'beritaAcara')->name('berita-acara')->middleware('resource:'.rk('hasil-partai', ResourceAction::Print));

                    Route::post('/timer/mulai', 'mulaiBabak')->name('timer.mulai')->middleware('resource:'.rk('partai', ResourceAction::Update));
                    Route::post('/timer/jeda', 'jeda')->name('timer.jeda')->middleware('resource:'.rk('partai', ResourceAction::Update));
                    Route::post('/timer/lanjut', 'lanjutkan')->name('timer.lanjut')->middleware('resource:'.rk('partai', ResourceAction::Update));
                    Route::post('/timer/reset', 'reset')->name('timer.reset')->middleware('resource:'.rk('partai', ResourceAction::Update));
                    Route::post('/timer/selesai-babak', 'selesaikanBabak')->name('timer.selesai-babak')->middleware('resource:'.rk('partai', ResourceAction::Update));

                    Route::post('/akhiri', 'akhiri')->name('akhiri')->middleware('resource:'.rk('partai', ResourceAction::Manage));
                    Route::post('/sahkan', 'sahkan')->name('sahkan')->middleware('resource:'.rk('hasil-partai', ResourceAction::Approve));

                    Route::post('/nilai', 'nilai')->name('nilai')->middleware('resource:'.rk('penilaian', ResourceAction::Create));
                    /*
                     * Nilai mutlak jatuhan. Dijaga resource `hukuman` Create,
                     * bukan `penilaian` yang dipegang juri: jatuhan sederajat
                     * dengan sanksi -- nilainya mutlak dan diputuskan wasit
                     * yang berdiri di gelanggang, bukan dikonsensuskan juri.
                     */
                    Route::post('/jatuhan', 'jatuhan')->name('jatuhan')->middleware('resource:'.rk('hukuman', ResourceAction::Create));
                    Route::post('/nilai/{scoreEvent}/batal', 'batalkanNilai')->name('nilai.batal')->middleware('resource:'.rk('hasil-partai', ResourceAction::Update));

                    Route::post('/hukuman', 'hukuman')->name('hukuman')->middleware('resource:'.rk('hukuman', ResourceAction::Create));
                    Route::post('/hukuman/{penalty}/batal', 'batalkanHukuman')->name('hukuman.batal')->middleware('resource:'.rk('hasil-partai', ResourceAction::Update));
                    Route::post('/hitungan', 'hitungan')->name('hitungan')->middleware('resource:'.rk('hukuman', ResourceAction::Create));
                });

            /*
             * Panel yang mengikuti GELANGGANG, bukan satu partai.
             *
             * Rute per-partai di atas tidak dihapus: dewan wasit juri tetap
             * harus bisa membuka partai lama yang sudah selesai untuk ditinjau
             * dan disahkan, dan berita acaranya dicetak dari sana.
             *
             * Aksi mutasi sengaja TIDAK diduplikasi di sini. Timer, nilai,
             * hukuman, hitungan, verifikasi, dan VAR tetap memakai rute
             * per-partai; klien memperoleh alamatnya dari payload state dan
             * menghitungnya ulang tiap kali partai aktif berganti. Dua salinan
             * otorisasi untuk aksi yang sama adalah cara paling murah membuat
             * lubang izin.
             */
            Route::controller(PanelGelanggangController::class)
                ->prefix('{tournament}/gelanggang/{arena}/panel')
                ->name('gelanggang.panel.')
                ->group(function () {
                    Route::get('/state', 'state')->name('state')->middleware('resource:'.rk('partai', ResourceAction::View));
                    Route::get('/kendali', 'kendali')->name('kendali')->middleware('resource:'.rk('kendali-gelanggang', ResourceAction::View));
                    Route::get('/papan', 'papan')->name('papan')->middleware('resource:'.rk('partai', ResourceAction::View));
                    Route::get('/wasit', 'wasit')->name('wasit')->middleware('resource:'.rk('hukuman', ResourceAction::View));
                    Route::get('/juri', 'juri')->name('juri')->middleware('resource:'.rk('penilaian', ResourceAction::Create));
                    Route::get('/dewan-juri', 'dewanJuri')->name('dewan-juri')->middleware('resource:'.rk('hasil-partai', ResourceAction::View));

                    /*
                     * Wasit Komisi Protes tidak punya panel lain: panel
                     * keberatan ITULAH panelnya, dan alamatnya per gelanggang
                     * supaya ikut berpindah partai tanpa disentuh.
                     */
                    Route::get('/komisi-protes', 'komisiProtes')->name('komisi-protes')->middleware('resource:'.rk('var', ResourceAction::View));

                    /*
                     * Panel gelanggang Ketua Pertandingan. Dijaga `partai`
                     * Manage seperti ringkasan lintas gelanggangnya: isinya
                     * memuat protes dan VAR, jadi izin melihat partai saja
                     * tidak cukup untuk membenarkan membukanya.
                     */
                    Route::get('/ketua', 'ketua')->name('ketua')->middleware('resource:'.rk('partai', ResourceAction::Manage));

                    /*
                     * Manifest PWA per gelanggang, per peran. Dijaga izin
                     * paling longgar di antara panel-panel di atas: manifest
                     * tidak memuat data pertandingan apa pun, hanya alamat
                     * dan nama ikon.
                     */
                    Route::get('/{peran}/manifest.webmanifest', 'manifest')->name('manifest')->middleware('resource:'.rk('partai', ResourceAction::View));

                    Route::post('/partai-aktif', 'pilihPartai')->name('partai-aktif')->middleware('resource:'.rk('kendali-gelanggang', ResourceAction::Assign));

                    /*
                     * Membuka babak lama melonggarkan penjagaan babak yang
                     * sudah ditutup -- wewenang terberat di gelanggang, jadi
                     * dijaga Manage, bukan Update yang dipegang siapa pun yang
                     * boleh menekan timer.
                     */
                    Route::post('/babak-susulan', 'bukaBabak')->name('babak-susulan.buka')->middleware('resource:'.rk('kendali-gelanggang', ResourceAction::Manage));
                    Route::delete('/babak-susulan', 'tutupBabak')->name('babak-susulan.tutup')->middleware('resource:'.rk('kendali-gelanggang', ResourceAction::Manage));
                });

            /*
             * Panel Ketua Pertandingan -- Pasal 13.4.
             *
             * Dijaga resource `partai` Manage, bukan `verifikasi-juri`: panel
             * ini memantau seluruh gelanggang dan menampilkan protes serta
             * VAR, jadi izin membuka verifikasi saja tidak cukup untuk
             * membenarkan melihat isinya.
             */
            Route::controller(KetuaPertandinganController::class)
                ->prefix('{tournament}/ketua-pertandingan')
                ->name('ketua-pertandingan.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('partai', ResourceAction::Manage));
                    Route::get('/state', 'state')->name('state')->middleware('resource:'.rk('partai', ResourceAction::Manage));
                });

            /*
             * Verifikasi juri -- Pasal 13.
             *
             * `minta` dijaga Create: Wasit dan Ketua Pertandingan, bukan juri.
             * `jawab` dijaga Update, satu-satunya aksi verifikasi yang dipunyai
             * juri -- dan di dalamnya masih diperiksa lagi bahwa penjawabnya
             * memang juri yang ditugaskan di partai itu, karena izin peran
             * hanya menyatakan "boleh menjawab verifikasi", bukan "boleh
             * menjawab verifikasi partai ini".
             */
            Route::controller(VerifikasiJuriController::class)
                ->prefix('{tournament}/partai/{match}/verifikasi')
                ->name('partai.verifikasi.')
                ->group(function () {
                    Route::post('/', 'minta')->name('minta')->middleware('resource:'.rk('verifikasi-juri', ResourceAction::Create));
                    Route::post('/{verifikasi}/jawab', 'jawab')->name('jawab')->middleware('resource:'.rk('verifikasi-juri', ResourceAction::Update));
                    Route::post('/{verifikasi}/terapkan', 'terapkan')->name('terapkan')->middleware('resource:'.rk('verifikasi-juri', ResourceAction::Approve));
                    Route::post('/{verifikasi}/batalkan', 'batalkan')->name('batalkan')->middleware('resource:'.rk('verifikasi-juri', ResourceAction::Reject));
                });

            /*
             * Keberatan: VAR (Pasal 15) dan Protes Manajer (Pasal 15 ayat 4).
             * `var.ajukan` dijaga Create karena siapa pun yang mengoperasikan
             * gelanggang bisa memasukkan protes atas permintaan pelatih;
             * `var.putuskan` dijaga Approve/Reject -- wewenang Wasit Komisi
             * Protes, Pengawas/Dewan Wasit Juri, atau Ketua Pertandingan saja.
             */
            Route::controller(VarController::class)
                ->prefix('{tournament}/partai/{match}/keberatan')
                ->name('partai.keberatan.')
                ->group(function () {
                    Route::post('/var', 'ajukan')->name('var.ajukan')->middleware('resource:'.rk('var', ResourceAction::Create));
                    Route::post('/var/{varReview}/putuskan', 'putuskan')->name('var.putuskan')->middleware('resource:'.rk('var', ResourceAction::Approve));

                    Route::post('/protes-manajer', 'ajukanManajer')->name('protes-manajer.ajukan')->middleware('resource:'.rk('protes-manajer', ResourceAction::Create));
                    Route::post('/protes-manajer/{managerProtest}/banding', 'banding')->name('protes-manajer.banding')->middleware('resource:'.rk('protes-manajer', ResourceAction::Create));
                    Route::post('/protes-manajer/{managerProtest}/putuskan', 'putuskanManajer')->name('protes-manajer.putuskan')->middleware('resource:'.rk('protes-manajer', ResourceAction::Approve));
                });

            /*
             * Mesin scoring Jurus. Jauh lebih ramping dari Tanding: satu
             * penampilan berjalan sekali dari awal sampai selesai, jadi tidak
             * ada kendali babak/jeda seperti timer partai.
             */
            Route::controller(JurusScoringController::class)
                ->prefix('{tournament}/jurus')
                ->name('jurus.')
                ->group(function () {
                    Route::get('/', 'daftarNomor')->name('nomor')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::View));
                    Route::get('/{jurusEvent}', 'index')->name('index')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::View));
                    Route::post('/{jurusEvent}/buat-penampilan', 'generate')->name('generate')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::Create));

                    /*
                     * Format nomor dan bagan gugurnya -- Pasal 12.1.b.1.
                     *
                     * Format dijaga `nomor-jurus.update` (ia mengubah bentuk
                     * pertandingan, bukan menjalankannya), bagan dijaga
                     * `bagan.update` seperti bagan Tanding.
                     */
                    Route::post('/{jurusEvent}/format', 'ubahFormat')->name('format')->middleware('resource:'.rk('nomor-jurus', ResourceAction::Update));
                    Route::post('/{jurusEvent}/susun-bagan', 'susunBagan')->name('susun-bagan')->middleware('resource:'.rk('bagan', ResourceAction::Update));

                    /*
                     * Perbandingan kedua sudut satu battle -- Pasal 12.1.f.
                     *
                     * Berdiri di luar prefix `penampilan/{performance}` dengan
                     * sengaja: yang ditanyakan bukan satu penampilan, melainkan
                     * hubungan antara dua penampilan yang bertemu.
                     */
                    Route::get('/battle/{jurusBattle}', 'battle')->name('battle')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::View));
                    Route::get('/battle/{jurusBattle}/state', 'battleState')->name('battle.state')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::View));
                    Route::post('/battle/{jurusBattle}/penampilan', 'siapkanPenampilanBattle')->name('battle.penampilan')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::Create));
                    Route::post('/battle/{jurusBattle}/putuskan', 'putuskanBattle')->name('battle.putuskan')->middleware('resource:'.rk('hasil-jurus', ResourceAction::Approve));

                    Route::prefix('penampilan/{performance}')->name('penampilan.')->group(function () {
                        Route::get('/', 'state')->name('state')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::View));
                        Route::get('/operator', 'operator')->name('operator')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::View));
                        Route::get('/juri', 'juri')->name('juri')->middleware('resource:'.rk('penilaian', ResourceAction::Create));

                        Route::post('/timer/mulai', 'mulaiTimer')->name('timer.mulai')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::Update));
                        Route::post('/timer/berhenti', 'berhentiTimer')->name('timer.berhenti')->middleware('resource:'.rk('penampilan-jurus', ResourceAction::Update));

                        Route::post('/nilai', 'nilai')->name('nilai')->middleware('resource:'.rk('penilaian', ResourceAction::Create));
                        Route::post('/pengurangan-juri', 'penguranganJuri')->name('pengurangan-juri')->middleware('resource:'.rk('penilaian', ResourceAction::Create));
                        Route::post('/pengurangan-pengawas', 'penguranganPengawas')->name('pengurangan-pengawas')->middleware('resource:'.rk('pengurangan-jurus', ResourceAction::Create));
                        Route::post('/pengurangan/{deduction}/batal', 'batalkanPengurangan')->name('pengurangan.batal')->middleware('resource:'.rk('hasil-jurus', ResourceAction::Update));

                        Route::post('/diskualifikasi', 'diskualifikasi')->name('diskualifikasi')->middleware('resource:'.rk('pengurangan-jurus', ResourceAction::Create));
                        Route::post('/sahkan', 'sahkan')->name('sahkan')->middleware('resource:'.rk('hasil-jurus', ResourceAction::Approve));
                    });
                });

            /*
             * Daftar alamat overlay, bukan overlaynya sendiri. Halaman
             * overlay hidup di routes/overlay.php tanpa auth sama sekali --
             * yang dijaga di sini hanya daftar alamatnya.
             */
            Route::get('{tournament}/siaran', [SiaranController::class, 'index'])
                ->name('siaran.index')
                ->middleware('resource:'.rk('overlay', ResourceAction::View));

            Route::controller(RekapController::class)
                ->prefix('{tournament}/rekap')
                ->name('rekap.')
                ->group(function () {
                    Route::get('/', 'index')->name('index')->middleware('resource:'.rk('rekap', ResourceAction::View));
                    Route::get('/ekspor/medali', 'exportMedali')->name('ekspor.medali')->middleware('resource:'.rk('rekap', ResourceAction::Export));
                    Route::get('/ekspor/medali.pdf', 'exportMedaliPdf')->name('ekspor.medali-pdf')->middleware('resource:'.rk('rekap', ResourceAction::Print));
                    Route::get('/ekspor/peserta', 'exportPeserta')->name('ekspor.peserta')->middleware('resource:'.rk('rekap', ResourceAction::Export));
                    Route::get('/ekspor/jadwal', 'exportJadwal')->name('ekspor.jadwal')->middleware('resource:'.rk('rekap', ResourceAction::Export));
                });
        });

    });
});

// Route auth (login, register, reset password, verifikasi email, 2FA)
// didaftarkan otomatis oleh Fortify — lihat config/fortify.php untuk
// menyalakan atau mematikan fiturnya.
