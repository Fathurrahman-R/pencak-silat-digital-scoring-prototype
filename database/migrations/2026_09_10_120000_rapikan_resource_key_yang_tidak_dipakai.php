<?php

use App\Enums\ResourceAction;
use App\Models\Resource;
use App\Support\Resources\ResourceManager;
use Illuminate\Database\Migrations\Migration;

/*
 * Merapikan resource key yang tidak berakibat apa-apa.
 *
 * Audit `silat:audit-izin` menemukan dua kelompok:
 *
 *   - dua belas key yang tidak dimiliki peran mana pun DAN tidak menjaga satu
 *     rute maupun tampilan pun. Ia cuma baris centang di panel peran yang
 *     membuat panitia mengira sedang memberi kewenangan;
 *   - satu key yang hilang: `mappings.delete`. Memutus pemetaan sebuah key
 *     mematikan otorisasi di baliknya, dan itu bukan penyuntingan -- tapi
 *     rutenya terpaksa dijaga `mappings.update` karena aksinya tidak ada.
 *
 * Seeder resource melewati resource yang sudah ada (`continue`), jadi
 * perubahan definisinya tidak pernah sampai ke pemasangan yang sudah jalan.
 * Migrasi inilah yang membawanya.
 *
 * Turun: aksinya dikembalikan. Permission-nya lahir lagi dengan nama yang
 * sama, tapi pemberiannya ke peran TIDAK kembali -- jalankan seeder peran
 * kalau memang perlu.
 */
return new class extends Migration
{
    /** @var array<string, array<int, ResourceAction>> */
    private const SESUDAH = [
        'turnamen' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete, ResourceAction::Export],
        'nomor-jurus' => [ResourceAction::View, ResourceAction::Update],
        'kontingen' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete],
        'atlet' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete],
        'invoice' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Export],
        'bagan' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete, ResourceAction::Print],
        'jadwal' => [ResourceAction::View, ResourceAction::Assign, ResourceAction::Print],
        'mappings' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Delete],

        // Aksi baca dan tolak yang tidak pernah menjaga apa pun: yang membaca
        // hasil Jurus memakai `penampilan-jurus.view`, dan menolak VAR atau
        // protes manajer dilakukan lewat endpoint putusan yang sama dengan
        // menerimanya (`.approve`).
        'hasil-jurus' => [ResourceAction::Update, ResourceAction::Approve],
        'kendali-gelanggang' => [ResourceAction::View, ResourceAction::Assign, ResourceAction::Manage],
        'penampilan-jurus' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update],
        'pendaftaran' => [
            ResourceAction::View, ResourceAction::Create, ResourceAction::Update,
            ResourceAction::Delete, ResourceAction::Approve, ResourceAction::Reject,
        ],
        'pengurangan-jurus' => [ResourceAction::Create],
        'protes-manajer' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve],
        'timbang-badan' => [ResourceAction::View, ResourceAction::Create],
        'var' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve],
        'verifikasi-juri' => [
            ResourceAction::Create, ResourceAction::Update,
            ResourceAction::Approve, ResourceAction::Reject,
        ],

        // Layar penugasan aparat per partai dibuang; yang tersisa satu layar
        // di halaman Gelanggang, dan ia dijaga Assign.
        'penugasan-aparat' => [ResourceAction::Assign],
    ];

    /** @var array<string, array<int, ResourceAction>> */
    private const SEBELUM = [
        'turnamen' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete, ResourceAction::Manage, ResourceAction::Export],
        'nomor-jurus' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete],
        'kontingen' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete, ResourceAction::Export],
        'atlet' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete, ResourceAction::Export],
        'invoice' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Export],
        'bagan' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete, ResourceAction::Assign, ResourceAction::Print],
        'jadwal' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete, ResourceAction::Assign, ResourceAction::Print],
        'mappings' => [ResourceAction::View, ResourceAction::Update],

        'hasil-jurus' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Print],
        'kendali-gelanggang' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Assign, ResourceAction::Manage],
        'penampilan-jurus' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Manage],
        'pendaftaran' => [
            ResourceAction::View, ResourceAction::Create, ResourceAction::Update,
            ResourceAction::Delete, ResourceAction::Approve, ResourceAction::Reject,
            ResourceAction::Export,
        ],
        'pengurangan-jurus' => [ResourceAction::View, ResourceAction::Create],
        'protes-manajer' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
        'timbang-badan' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Export],
        'var' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
        'verifikasi-juri' => [
            ResourceAction::View, ResourceAction::Create, ResourceAction::Update,
            ResourceAction::Approve, ResourceAction::Reject,
        ],
        'penugasan-aparat' => [ResourceAction::View, ResourceAction::Assign],
    ];

    public function up(): void
    {
        $manager = app(ResourceManager::class);

        foreach (self::SESUDAH as $key => $aksi) {
            $resource = Resource::where('key', $key)->first();

            if ($resource === null) {
                continue;
            }

            $this->bukaKunciYangDibuang($resource, $aksi);
            $manager->syncActions($resource, $aksi);
        }

        /*
         * `kelas-tanding` dibuang seluruhnya. Kelasnya sendiri tetap ada --
         * ia lahir dari naskah 2025 bersama kejuaraannya dan disunting di
         * layar kejuaraan, yang dijaga `turnamen.update`. Yang dibuang cuma
         * empat baris centang yang tidak pernah menjaga apa pun.
         */
        $kelas = Resource::where('key', 'kelas-tanding')->first();

        if ($kelas !== null) {
            $kelas->forceFill(['is_locked' => false])->save();
            $manager->deleteResource($kelas);
        }
    }

    /**
     * Melepas kunci pemetaan yang akan dibuang.
     *
     * Resource yang menyentuh jalannya pertandingan ditandai terkunci supaya
     * tidak terhapus dari panel di tengah acara, dan `syncActions` menolak
     * membuang pemetaan terkunci. Di sini yang dibuang justru aksi yang tidak
     * pernah menjaga apa pun, jadi kuncinya dilepas lebih dulu -- satu per
     * satu, hanya yang memang akan hilang.
     *
     * @param  array<int, ResourceAction>  $aksi
     */
    private function bukaKunciYangDibuang(Resource $resource, array $aksi): void
    {
        $tetap = array_map(fn (ResourceAction $satu) => $satu->value, $aksi);

        foreach ($resource->mappings()->get() as $pemetaan) {
            if (! in_array($pemetaan->action->value, $tetap, true)) {
                $pemetaan->forceFill(['is_locked' => false])->save();
            }
        }
    }

    public function down(): void
    {
        $manager = app(ResourceManager::class);

        foreach (self::SEBELUM as $key => $aksi) {
            $resource = Resource::where('key', $key)->first();

            $resource === null || $manager->syncActions($resource, $aksi);
        }

        if (! Resource::where('key', 'kelas-tanding')->exists()) {
            $manager->createResource([
                'key' => 'kelas-tanding',
                'label' => 'Kelas Tanding',
                'group' => 'Penyelenggaraan',
                'description' => 'Kelas menurut golongan usia, jenis kelamin, dan rentang berat badan.',
            ], [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete]);
        }
    }
};
