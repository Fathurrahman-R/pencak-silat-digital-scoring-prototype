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
        'sekretaris-pertandingan' => 'sekretariat',
        'bendahara' => 'sekretariat',
        'petugas-timbang' => 'sekretariat',
    ];

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
                    'penugasan-aparat' => [ResourceAction::View, ResourceAction::Assign],
                    'partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Manage],
                    /*
                     * Jalan keluar saat perangkat pengendali mati di tengah
                     * pertandingan. Ketua Pertandingan wewenangnya lintas
                     * gelanggang, jadi ia satu-satunya yang bisa mengambil
                     * alih tanpa menunggu penugasan ulang.
                     */
                    'kendali-gelanggang' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Assign, ResourceAction::Manage],
                    // Wewenangnya lintas gelanggang, jadi ia juga yang paling
                    // butuh menarik data dari laptop lain.
                    'sinkron-gelanggang' => [ResourceAction::View, ResourceAction::Update],
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Print],
                    // Ketua Pertandingan menampung protes VAR maupun Protes
                    // Manajer atas nama pelatih (keduanya diajukan pelatih di
                    // gelanggang, bukan lewat akun sistem sendiri), lalu
                    // memutus tingkat pertama Protes Manajer.
                    'var' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                    // Pasal 13 menyebut verifikasi juri datang dari Ketua
                    // Pertandingan maupun Wasit. Deskripsi peran ini sudah
                    // berbunyi "memimpin verifikasi juri" sejak awal.
                    'verifikasi-juri' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                    'protes-manajer' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                    'penampilan-jurus' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Manage],
                    'hasil-jurus' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Print],
                    'overlay' => $lihat,
                    'rekap' => [ResourceAction::View, ResourceAction::Export, ResourceAction::Print],
                ],
            ],
            [
                'name' => 'pengawas-wasit-juri',
                'label' => 'Pengawas / Dewan Wasit Juri',
                'description' => 'Mengevaluasi penilaian juri, menyusun penugasan, dan mencatat pengurangan 0.50 pada kategori Jurus.',
                'grants' => [
                    'penugasan-aparat' => [ResourceAction::View, ResourceAction::Assign],
                    'partai' => $lihat,
                    'penilaian' => $lihat,
                    'hukuman' => [ResourceAction::View, ResourceAction::Create],
                    /*
                     * Termasuk MENGESAHKAN dan MENCETAK berita acara.
                     *
                     * Sebelumnya hanya View dan Update, jadi Dewan Wasit Juri
                     * bisa membatalkan nilai keliru tapi tidak bisa
                     * mengesahkan hasil yang sudah dibereskannya sendiri, dan
                     * berita acaranya membalas 403 -- padahal panduan
                     * operasional menaruh kedua pekerjaan itu di kursinya.
                     * Pengesahan tertahan di Ketua Pertandingan, yang di
                     * lapangan sedang mengurus gelanggang lain.
                     */
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Print],
                    // Melihat saja: hasil verifikasi masuk bahan evaluasi
                    // penilaian juri, tapi memintanya adalah wewenang Wasit
                    // dan Ketua Pertandingan.
                    'verifikasi-juri' => $lihat,
                    'var' => [ResourceAction::View, ResourceAction::Approve, ResourceAction::Reject],
                    'penampilan-jurus' => $lihat,
                    'pengurangan-jurus' => [ResourceAction::View, ResourceAction::Create],
                    'hasil-jurus' => [ResourceAction::View, ResourceAction::Update],
                ],
            ],
            [
                'name' => 'wasit-komisi-protes',
                'label' => 'Wasit Komisi Protes',
                'description' => 'Menganalisis tayangan ulang dan menetapkan hasil protes VAR dalam tenggat 5 menit.',
                'grants' => [
                    'partai' => $lihat,
                    'penilaian' => $lihat,
                    'hukuman' => $lihat,
                    'var' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                ],
            ],
            [
                'name' => 'wasit',
                'label' => 'Wasit',
                'description' => 'Memimpin pertandingan, menjatuhkan pembinaan, teguran, dan peringatan, serta menghentikan pertandingan.',
                'grants' => [
                    'partai' => [ResourceAction::View, ResourceAction::Update],
                    'hukuman' => [ResourceAction::View, ResourceAction::Create],
                    'penilaian' => $lihat,
                    'verifikasi-juri' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
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
                    'verifikasi-juri' => [ResourceAction::View, ResourceAction::Update],
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
                    'kendali-gelanggang' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Assign, ResourceAction::Manage],
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
                'description' => 'Menjalankan papan tampilan gelanggang dan perangkat siarannya. Tidak mengendalikan timer maupun jalannya partai.',
                'grants' => [
                    'jadwal' => $lihat,

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
                    'bagan' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update],

                    'partai' => $lihat,
                    'penilaian' => $lihat,
                    'hukuman' => $lihat,
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Print],
                    // Operator paling dekat dengan meja pelatih -- bisa
                    // memasukkan protes VAR ke sistem, tapi tidak memutusnya.
                    'var' => [ResourceAction::View, ResourceAction::Create],
                    'penampilan-jurus' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Manage],
                    'hasil-jurus' => [ResourceAction::View, ResourceAction::Print],

                    // Operator IT yang memasang Web Browser Input di vMix,
                    // jadi dialah yang paling butuh daftar alamat overlay.
                    'overlay' => $lihat,
                ],
            ],
            [
                'name' => 'sekretariat',
                'label' => 'Sekretariat Pertandingan',
                'description' => 'Meja pra-acara: menerima pendaftaran, memeriksa berkas, menagih dan mencatat pembayaran, lalu menimbang peserta Tanding.',
                'grants' => [
                    'turnamen' => $lihat,
                    'kontingen' => $ubah,
                    'atlet' => $ubah,

                    /*
                     * Approve dan Reject dipisahkan dari Update. Menyunting
                     * data pendaftaran dan mengesahkan keikutsertaan adalah dua
                     * kewenangan yang berbeda, dan yang kedua menentukan siapa
                     * berhak naik gelanggang.
                     */
                    'pendaftaran' => [
                        ResourceAction::View, ResourceAction::Update,
                        ResourceAction::Approve, ResourceAction::Reject,
                        ResourceAction::Export,
                    ],

                    /*
                     * Bekas kewenangan Bendahara. Tarif boleh disusun ulang,
                     * tagihan boleh dikunci dan ditandai lunas — tapi tidak
                     * ada Delete pada invoice: tagihan yang sudah terbit
                     * bagian dari catatan keuangan kejuaraan.
                     */
                    'tarif' => $ubah,
                    'invoice' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Export],

                    // Bekas kewenangan Petugas Timbang Badan.
                    'timbang-badan' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Export],

                    'kelas-tanding' => $lihat,

                    /*
                     * Format nomor Jurus -- battle atau peringkat -- ditetapkan
                     * di meja ini, bersama pendaftaran dan keabsahan peserta.
                     * Ia mengubah BENTUK pertandingan, bukan menjalankannya,
                     * jadi ia bukan pekerjaan gelanggang.
                     *
                     * Sebelum ini `nomor-jurus.update` juga tidak dimiliki
                     * peran mana pun.
                     */
                    'nomor-jurus' => [ResourceAction::View, ResourceAction::Update],

                    /*
                     * Mencetak, bukan cuma melihat. Yang memaku bagan di papan
                     * pengumuman dan membagikan jadwal ke meja gelanggang
                     * adalah meja ini -- bukan Ketua Pertandingan, yang sedang
                     * berdiri di matras, dan bukan super-admin.
                     */
                    'bagan' => [ResourceAction::View, ResourceAction::Print],
                    'jadwal' => [ResourceAction::View, ResourceAction::Print],
                    'rekap' => [ResourceAction::View, ResourceAction::Export, ResourceAction::Print],
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
