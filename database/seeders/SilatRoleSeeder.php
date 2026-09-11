<?php

namespace Database\Seeders;

use App\Enums\ResourceAction;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Peran mengikuti susunan aparat pertandingan pada Pasal 13 Peraturan
 * Pertandingan Pencak Silat Nasional Tahun 2025, bukan istilah karangan
 * sendiri. Ini penting supaya panitia mengenali namanya tanpa perlu
 * menerjemahkan, dan supaya pembagian wewenangnya bisa diadu langsung dengan
 * naskah.
 *
 * Aparat yang tidak menyentuh aplikasi — Announcer, Petugas Medis, Petugas
 * Lapangan — sengaja tidak dibuatkan peran.
 *
 * Beberapa jabatan naskah digabung karena di lapangan dipegang orang yang
 * sama, dan peran yang tidak pernah dipakai justru memperbesar permukaan
 * salah-tugas:
 *
 *   Sekretariat Pertandingan  = Sekretaris + Bendahara + Petugas Timbang Badan.
 *                               Satu meja pra-acara: berkas, tagihan, timbangan.
 *   Ketua Pertandingan        menyerap Delegasi Teknik. Wewenang Delegasi
 *                               (pengesahan hasil, putusan protes) seluruhnya
 *                               sudah dipegang Ketua, jadi perannya sendiri
 *                               tidak pernah menambah apa pun.
 */
class SilatRoleSeeder extends Seeder
{
    /**
     * Peran yang sudah digabung ke peran lain.
     *
     * Dihapus, bukan dibiarkan menganggur: peran kosong yang masih terdaftar
     * tetap bisa dipilih di panel Pengguna, dan akun yang menerimanya akan
     * diam-diam kehilangan seluruh kewenangannya.
     */
    private const DIBUBARKAN = [
        'delegasi-teknik' => 'ketua-pertandingan',
        'sekretaris-pertandingan' => 'operator-it',
        'bendahara' => 'operator-it',
        'petugas-timbang' => 'operator-it',

        /*
         * Sekretariat ikut lebur ke Operator IT, September 2026.
         *
         * Bukan karena pekerjaannya hilang -- pendaftaran, tagihan, timbangan,
         * dan cetak tetap ada -- melainkan karena di kejuaraan yang dilayani
         * aplikasi ini keduanya orang yang sama: satu meja dengan satu laptop
         * yang juga memasang papan skor. Dua peran untuk satu orang cuma
         * membuat separuh kewenangannya tertinggal di akun yang salah.
         */
        'sekretariat' => 'operator-it',

        /*
         * Wasit lebur ke Ketua Pertandingan, September 2026.
         */
        'wasit' => 'ketua-pertandingan',

        /*
         * Dewan Wasit Juri dan Komisi Protes menyusul, September 2026.
         *
         * Ketiganya sudah memegang hampir seluruh kewenangan yang sama dengan
         * Ketua Pertandingan -- meninjau nilai, membatalkan, mengesahkan,
         * memutus VAR -- dan di kejuaraan yang dilayani aplikasi ini mereka
         * duduk di meja yang sama. Yang tersisa cuma satu kewenangan yang
         * belum dipegang Ketua: menjatuhkan pengurangan Pengawas pada
         * penampilan Jurus, dan itu ikut pindah bersama peleburan ini.
         */
        'pengawas-wasit-juri' => 'ketua-pertandingan',
        'wasit-komisi-protes' => 'ketua-pertandingan',

        // Peran bawaan boilerplate: `admin` cuma memegang users.*, dan
        // pekerjaan itu sekarang ada di Operator IT.
        'admin' => 'operator-it',
    ];

    /*
     * Peran yang dibuang TANPA pengganti.
     *
     * `user` tidak memegang kewenangan apa pun, jadi memindahkan pemegangnya
     * ke peran mana pun justru MENAIKKAN haknya -- dan peran inilah yang
     * paling mungkin menempel di akun yang baru mendaftar. Yang benar
     * membuang perannya dan membiarkan akunnya tanpa peran, persis keadaan
     * yang sudah ia miliki.
     */
    private const DIBUANG = ['user'];

    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $role = Role::firstOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                [
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                ],
            );

            $role->syncPermissions($this->permissionsFor($definition['grants']));
        }

        $this->bubarkanPeranLama();
    }

    /**
     * Memindahkan pemegang peran lama ke peran penggantinya, lalu membuang
     * peran lamanya.
     *
     * Dijalankan setelah definisi, bukan sebelumnya: peran pengganti harus
     * sudah ada sebelum ada akun yang dipindahkan ke sana. Pada pemasangan
     * yang masih bersih tidak ada yang dikerjakan sama sekali.
     */
    private function bubarkanPeranLama(): void
    {
        foreach (self::DIBUANG as $namaPeran) {
            $peran = Role::where('name', $namaPeran)->where('guard_name', 'web')->first();

            $peran?->users()->get()->each(fn ($pengguna) => $pengguna->removeRole($peran));
            $peran?->delete();
        }

        foreach (self::DIBUBARKAN as $lama => $pengganti) {
            $peran = Role::where('name', $lama)->where('guard_name', 'web')->first();

            if ($peran === null) {
                continue;
            }

            foreach ($peran->users()->get() as $pengguna) {
                $pengguna->assignRole($pengganti);
                $pengguna->removeRole($peran);
            }

            $peran->delete();
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        $lihat = [ResourceAction::View];
        $ubah = [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete];

        return [
            [
                'name' => 'ketua-pertandingan',
                'label' => 'Ketua Pertandingan',
                'description' => 'Mengatur kelancaran pertandingan, memimpin verifikasi juri, mengesahkan hasil, dan memutus protes sampai tingkat akhir.',
                'grants' => [
                    'turnamen' => $lihat,
                    'gelanggang' => $lihat,
                    /*
                     * Print ikut, dan itu bukan kelengkapan.
                     *
                     * Sebelum ini `bagan.print` dan `jadwal.print` tidak
                     * dimiliki SATU peran pun. Tombol "Cetak PDF" ada di kedua
                     * halaman, tapi ia dibungkus @resource dan karena itu tidak
                     * pernah tergambar untuk siapa pun -- hanya super-admin
                     * yang lolos, lewat Gate::before. Bagan yang dipaku di
                     * papan pengumuman dan jadwal yang dibawa ke meja
                     * gelanggang jadi mustahil dicetak oleh yang bertugas
                     * mencetaknya.
                     */
                    'jadwal' => [
                        ResourceAction::View, ResourceAction::Update,
                        ResourceAction::Assign, ResourceAction::Print,
                    ],
                    /*
                     * Delete di sini BUKAN menghapus bagan, melainkan membuka
                     * kuncinya -- undian yang sudah final dinyatakan bisa
                     * disusun ulang. Yang menyusunnya Operator IT; yang
                     * membatalkan finalitasnya Ketua Pertandingan, karena ia
                     * yang menanggung akibatnya di gelanggang.
                     */
                    'bagan' => [ResourceAction::View, ResourceAction::Print, ResourceAction::Delete],
                    'penugasan-aparat' => [ResourceAction::Assign],
                    'partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Manage],

                    /*
                     * Bekas kewenangan Wasit, yang lebur ke sini September
                     * 2026: menjatuhkan pembinaan, teguran, dan peringatan,
                     * serta membaca nilai juri yang sedang masuk.
                     *
                     * `hukuman.view` sekalian yang membuka panel wasit
                     * (`panel/wasit`) -- tanpa ia, yang memimpin partai tidak
                     * punya layar untuk memimpinnya.
                     */
                    'hukuman' => [ResourceAction::View, ResourceAction::Create],
                    'penilaian' => $lihat,

                    /*
                     * Jalan keluar saat perangkat pengendali mati di tengah
                     * pertandingan. Ketua Pertandingan wewenangnya lintas
                     * gelanggang, jadi ia satu-satunya yang bisa mengambil
                     * alih tanpa menunggu penugasan ulang.
                     */
                    'kendali-gelanggang' => [ResourceAction::View, ResourceAction::Assign, ResourceAction::Manage],
                    // Wewenangnya lintas gelanggang, jadi ia juga yang paling
                    // butuh menarik data dari laptop lain.
                    'sinkron-gelanggang' => [ResourceAction::View, ResourceAction::Update],
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Print],
                    // Ketua Pertandingan menampung protes VAR maupun Protes
                    // Manajer atas nama pelatih (keduanya diajukan pelatih di
                    // gelanggang, bukan lewat akun sistem sendiri), lalu
                    // memutus tingkat pertama Protes Manajer.
                    'var' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve],
                    // Pasal 13 menyebut verifikasi juri datang dari Ketua
                    // Pertandingan maupun Wasit. Deskripsi peran ini sudah
                    // berbunyi "memimpin verifikasi juri" sejak awal.
                    'verifikasi-juri' => [ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                    'protes-manajer' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve],
                    'penampilan-jurus' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update],
                    'hasil-jurus' => [ResourceAction::Update, ResourceAction::Approve],

                    /*
                     * Bekas kewenangan Dewan Wasit Juri: pengurangan 0.50 dan
                     * diskualifikasi pada penampilan Jurus (Pasal 12.1.e).
                     * Satu-satunya milik Dewan yang belum dipegang Ketua saat
                     * keduanya lebur.
                     */
                    'pengurangan-jurus' => [ResourceAction::Create],

                    'overlay' => $lihat,
                    'rekap' => [ResourceAction::View, ResourceAction::Export, ResourceAction::Print],
                ],
            ],
            [
                'name' => 'juri',
                'label' => 'Juri',
                'description' => 'Menilai serangan yang masuk. Hanya bisa menambah nilai, tidak pernah mengubah atau menghapusnya.',
                'grants' => [
                    'partai' => $lihat,
                    'penilaian' => [ResourceAction::View, ResourceAction::Create],
                    // Menjawab verifikasi, tidak pernah membukanya. Juri yang
                    // bisa membuka pertanyaan sendiri bisa memaksa polling
                    // atas kejadian yang menguntungkan sudut yang dinilainya.
                    'verifikasi-juri' => [ResourceAction::Update],
                    // Sama seperti Tanding: juri Jurus hanya melihat penampilan
                    // dan mengirim nilai, tidak pernah mengendalikan timer.
                    'penampilan-jurus' => $lihat,
                ],
            ],
            [
                'name' => 'pengendali-gelanggang',
                'label' => 'Pengendali Gelanggang',
                /*
                 * Satu perangkat memegang satu gelanggang.
                 *
                 * Sebelum peran ini ada, memindahkan jadwal berarti setiap
                 * petugas membuka alamat partai berikutnya sendiri-sendiri --
                 * juri di HP masing-masing, wasit, dewan juri, dan operator.
                 * Yang paling dirugikan juri: mereka hanya ingin menekan
                 * nilai, bukan mengurus navigasi di antara partai.
                 */
                'description' => 'Memegang satu gelanggang: menentukan partai yang sedang dimainkan, mengendalikan timer dan perpindahan babak, serta membuka babak lama untuk input susulan.',
                'grants' => [
                    'gelanggang' => $lihat,
                    'jadwal' => $lihat,
                    'kendali-gelanggang' => [ResourceAction::View, ResourceAction::Assign, ResourceAction::Manage],
                    /*
                     * Menarik data dari gelanggang lain. Diberikan ke
                     * pengendali, bukan ditahan di sekretariat, karena yang
                     * pertama tahu bahwa hasil hulu belum sampai adalah orang
                     * yang partainya ditolak sistem -- dan ia sedang berdiri
                     * di gelanggang, bukan di meja panitia.
                     */
                    'sinkron-gelanggang' => [ResourceAction::View, ResourceAction::Update],
                    'partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Manage],
                    /*
                     * Kategori Jurus juga dikendalikan dari gelanggang: sejak
                     * panel Jurus punya alamat per gelanggang, pengendali yang
                     * menentukan penampilan mana yang sedang ditayangkan.
                     *
                     * Ditemukan lewat blackbox testing bahwa ia bisa MEMINDAHKAN
                     * penampilan (dijaga `kendali-gelanggang.assign`) tapi tidak
                     * bisa MEMBUKA panelnya (dijaga `penampilan-jurus.view`) --
                     * peran yang memutuskan tanpa boleh melihat apa yang sedang
                     * diputuskannya.
                     *
                     * Hanya melihat: menilai tetap urusan juri, pengurangan 0.50
                     * urusan Dewan Wasit Juri, pengesahan urusan Ketua.
                     */
                    'penampilan-jurus' => $lihat,
                    'penilaian' => $lihat,
                    'hukuman' => $lihat,
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Print],
                    // Paling dekat dengan meja pelatih: bisa memasukkan protes
                    // VAR ke sistem, tapi tidak memutusnya.
                    'var' => [ResourceAction::View, ResourceAction::Create],
                    'overlay' => $lihat,
                ],
            ],
            [
                'name' => 'operator-it',
                'label' => 'Operator IT',
                /*
                 * Timer dan pengakhiran partai pindah ke Pengendali Gelanggang.
                 *
                 * Yang tersisa di sini memang pekerjaan Operator IT menurut
                 * naskah Pasal 13 ayat 2: menjalankan perangkat digital score
                 * dan siarannya, bukan memimpin jalannya pertandingan.
                 */
                'description' => 'Satu meja untuk seluruh administrasi kejuaraan — pendaftaran, tagihan, timbangan, jadwal, bagan, dan setelan — sekaligus papan tampilan gelanggang beserta siarannya. Tidak mengendalikan timer maupun jalannya partai.',
                'grants' => [
                    /*
                     * ── Administrasi kejuaraan ──────────────────────────────
                     *
                     * Sekretariat lebur ke sini, September 2026: di kejuaraan
                     * yang dilayani aplikasi ini, yang memasang papan skor dan
                     * yang menerima pendaftaran orang yang sama.
                     *
                     * `gelanggang` ikut, termasuk menetapkan siapa operator
                     * dan pengendali tiap matras -- sebelumnya tidak dimiliki
                     * peran mana pun, jadi menyiapkan gelanggang mustahil
                     * dijalankan siapa pun kecuali super-admin.
                     *
                     * `peraturan-turnamen` juga: angka yang menentukan
                     * skoring, durasi babak, dan hukuman disetel di meja ini
                     * sebelum acara, bukan di pinggir matras saat partai
                     * berjalan.
                     *
                     * Yang TIDAK ikut: struktur hak akses itu sendiri --
                     * `roles`, `permissions`, `resources`, dan `mappings`
                     * selain melihat. Peran yang boleh menyunting perannya
                     * sendiri bisa menaikkan haknya sendiri sampai setara
                     * super-admin, dan itu menghapus arti seluruh pembagian di
                     * berkas ini. Kalau panitia memang menghendakinya, yang
                     * dipakai akun super-admin.
                     */
                    'turnamen' => [...$ubah, ResourceAction::Export],
                    'gelanggang' => $ubah,
                    'peraturan-turnamen' => [ResourceAction::View, ResourceAction::Update],
                    'users' => [
                        ResourceAction::View, ResourceAction::Create,
                        ResourceAction::Update, ResourceAction::Delete,
                        ResourceAction::Export,
                    ],
                    'roles' => $lihat,
                    'permissions' => $lihat,
                    'resources' => $lihat,
                    'mappings' => $lihat,

                    /*
                     * Menarik data antar laptop.
                     *
                     * Sebelum ini hanya Ketua Pertandingan dan Pengendali
                     * Gelanggang yang memegangnya -- dan NODE GLOBAL tidak
                     * punya pengendali gelanggang sama sekali. Di mesin itu
                     * satu-satunya yang bisa menekan "Tarik" jadi Ketua, yang
                     * sedang berdiri di matras, sementara yang memasang dan
                     * menjaga laptopnya justru meja ini.
                     */
                    'sinkron-gelanggang' => [ResourceAction::View, ResourceAction::Update],

                    // ── Peserta dan keuangan, bekas meja Sekretariat ────────
                    'kontingen' => $ubah,
                    'atlet' => $ubah,

                    /*
                     * Approve dan Reject dipisahkan dari Update. Menyunting
                     * data pendaftaran dan mengesahkan keikutsertaan adalah dua
                     * kewenangan yang berbeda, dan yang kedua menentukan siapa
                     * berhak naik gelanggang.
                     */
                    'pendaftaran' => [
                        ResourceAction::View, ResourceAction::Create, ResourceAction::Update,
                        ResourceAction::Approve, ResourceAction::Reject,
                    ],

                    /*
                     * Tarif boleh disusun ulang, tagihan boleh dikunci dan
                     * ditandai lunas — tapi tidak ada Delete pada invoice:
                     * tagihan yang sudah terbit bagian dari catatan keuangan
                     * kejuaraan.
                     */
                    'tarif' => $ubah,
                    'invoice' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Export],
                    'timbang-badan' => [ResourceAction::View, ResourceAction::Create],

                    // Format nomor Jurus -- battle atau peringkat -- ditetapkan
                    // di sini, bersama pendaftaran dan keabsahan peserta.
                    'nomor-jurus' => [ResourceAction::View, ResourceAction::Update],

                    'rekap' => [ResourceAction::View, ResourceAction::Export, ResourceAction::Print],

                    // ── Gelanggang ─────────────────────────────────────────
                    'jadwal' => [ResourceAction::View, ResourceAction::Assign, ResourceAction::Print],

                    /*
                     * Menyusun bagan, menukar undian, dan menguncinya.
                     *
                     * Sebelum ini `bagan.create`, `bagan.update`, dan
                     * `bagan.delete` tidak dimiliki SATU peran pun -- seluruh
                     * tahap pra-acara mustahil dijalankan siapa pun kecuali
                     * super-admin, yang lolos lewat Gate::before. Ditemukan
                     * uji kotak hitam: Ketua membuka halaman bagan dan tidak
                     * menemukan form menyusunnya.
                     *
                     * TANPA Delete. Membuka kunci bagan yang sudah final
                     * dinilai seberat menghapusnya, dan itu wewenang Ketua
                     * Pertandingan -- lihat rasional di grup rute `bagan`.
                     */
                    'bagan' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Print],

                    'partai' => $lihat,
                    'penilaian' => $lihat,
                    'hukuman' => $lihat,
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Print],
                    // Operator paling dekat dengan meja pelatih -- bisa
                    // memasukkan protes VAR ke sistem, tapi tidak memutusnya.
                    'var' => [ResourceAction::View, ResourceAction::Create],
                    'penampilan-jurus' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update],

                    // Operator IT yang memasang Web Browser Input di vMix,
                    // jadi dialah yang paling butuh daftar alamat overlay.
                    'overlay' => $lihat,
                ],
            ],
            [
                'name' => 'official-kontingen',
                'label' => 'Official Kontingen',
                'description' => 'Mendaftarkan atlet kontingennya sendiri, mengunggah berkas, dan membayar tagihan.',
                'grants' => [
                    /*
                     * Hanya melihat kontingen, tidak mengubahnya. Nama dan
                     * kontak kontingen ditetapkan panitia saat pendaftaran
                     * kontingen diterima, dan ketiadaan hak ubah di sini pula
                     * yang membatasi official hanya pada kontingennya sendiri —
                     * lihat App\Http\Controllers\Concerns\ScopesContingents.
                     */
                    'kontingen' => $lihat,
                    'atlet' => $ubah,
                    'pendaftaran' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete],
                    /*
                     * Update, bukan cuma View: MENGUNCI tagihan sendiri adalah
                     * langkah official, dan panduan alur menaruhnya di
                     * kursinya ("Tagihan → Kunci tagihan dan lanjut bayar").
                     * Tanpa itu rantai pra-acara berhenti di draf tagihan.
                     *
                     * Approve sengaja TIDAK diberikan. Menandai lunas dijaga
                     * `invoice.approve` dan tetap milik Sekretariat: kontingen
                     * yang bisa menyatakan tagihannya sendiri lunas membuat
                     * seluruh verifikasi kehilangan artinya.
                     */
                    'invoice' => [ResourceAction::View, ResourceAction::Update],
                    'jadwal' => $lihat,
                    'bagan' => $lihat,
                    'rekap' => $lihat,
                ],
            ],
        ];
    }

    /**
     * Mengambil permission lewat resource key-nya, bukan lewat nama permission.
     * Kalau pemetaannya diubah lewat panel nanti, seeder ini tetap menunjuk hal
     * yang benar.
     *
     * @param  array<string, array<int, ResourceAction>>  $map
     * @return array<int, Permission>
     */
    private function permissionsFor(array $map): array
    {
        $permissions = [];

        foreach ($map as $resourceKey => $actions) {
            $resource = Resource::where('key', $resourceKey)->with('mappings.permission')->first();

            if ($resource === null) {
                continue;
            }

            foreach ($actions as $action) {
                $mapping = $resource->mappings->firstWhere('action', $action);

                if ($mapping?->permission) {
                    $permissions[] = $mapping->permission;
                }
            }
        }

        return $permissions;
    }
}
