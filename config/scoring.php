<?php

use App\Enums\GolonganUsia;

/*
|--------------------------------------------------------------------------
| Parameter peraturan pertandingan
|--------------------------------------------------------------------------
|
| Sumber: Peraturan Pertandingan Pencak Silat Nasional Tahun 2025, SK Ketua
| Umum PB IPSI Nomor Skep-70/III/2025. Naskahnya ada di `document/`.
|
| Berkas ini adalah nilai bawaan yang dipakai saat turnamen baru dibuat.
| Setelah tersimpan, tiap turnamen memegang salinannya sendiri, sehingga
| kejuaraan yang sedang berjalan tidak ikut berubah kalau berkas ini diedit.
|
| Tiap nilai diberi rujukan pasalnya. Yang tidak punya rujukan berarti memang
| tidak diatur naskah, dan itu ditandai terang-terangan.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Komposisi wasit juri — Pasal 16 ayat 1
    |--------------------------------------------------------------------------
    */

    'juri' => [

        'tanding' => [
            // Pasal 16.1.a: 1 Wasit dan 3 Juri per gelanggang.
            'jumlah' => 3,

            /*
             * TIDAK DIATUR NASKAH.
             *
             * Naskah 2025 menetapkan jumlah juri, tetapi tidak menyebut berapa
             * juri harus sepakat maupun selebar apa jendela waktunya. Keduanya
             * murni keputusan implementasi digital scoring.
             *
             * Dengan tiga juri, mayoritas berarti dua. Jendela 2 detik mengikuti
             * praktik yang lazim dipakai sistem digital scoring.
             */
            'ambang_sepakat' => 2,
            'window_ms' => 2000,
        ],

        'jurus' => [
            // Pasal 16.1.b: minimal 4 orang dan harus genap. Naskah Pasal 12
            // mencontohkan median dari 6 juri.
            'jumlah_minimal' => 4,
            'harus_genap' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Kategori Tanding — Pasal 11
    |--------------------------------------------------------------------------
    */

    'tanding' => [

        /*
         * Nilai prestasi teknik — Pasal 11.6.e.
         *
         * Hanya tiga. Naskah 2025 tidak mengenal nilai 4 untuk kuncian, dan
         * tidak mengenal nilai gabungan 1+1, 1+2, maupun 1+3. Keduanya berasal
         * dari edisi peraturan lama.
         */
        'nilai' => [
            'pukulan' => 1,   // serangan dengan tangan
            'tendangan' => 2, // serangan dengan kaki
            'jatuhan' => 3,   // tangkapan, sapuan, ungkitan, kaitan, guntingan, serangan balik jatuhan
        ],

        /*
         * Tahapan hukuman — Pasal 11.6.d.4.
         *
         * Urutannya: Pembinaan, Teguran, Peringatan, Diskualifikasi.
         */
        'hukuman' => [

            'pembinaan' => [
                // Tidak mengurangi nilai, dan berlaku akumulatif tanpa
                // membedakan jenis pelanggaran.
                'pengurangan' => 0,
                'cakupan' => 'partai',
                'jumlah_kolom' => 2,

                // Setelah dua pembinaan, pelanggaran ringan berikutnya naik
                // menjadi Teguran.
                'ambang_naik_ke_teguran' => 2,
            ],

            'teguran' => [
                'pengurangan' => [
                    1 => -1,
                    2 => -2,
                ],

                /*
                 * Naskah menyebut "setelah Teguran kedua dalam babak
                 * pertandingan yang sama", sehingga hitungan teguran
                 * diperlakukan per babak.
                 */
                'cakupan' => 'babak',
                'jumlah_kolom' => 2,

                // Teguran ketiga tidak pernah terjadi sebagai teguran — ia
                // langsung menjadi Peringatan I.
                'naik_ke_peringatan_pada' => 3,
            ],

            'peringatan' => [
                'pengurangan' => [
                    1 => -5,
                    2 => -10,
                    3 => null, // Peringatan III berarti diskualifikasi, bukan pengurangan
                ],

                // Pasal 11.6.d.4.c: "Berlaku untuk seluruh babak."
                'cakupan' => 'partai',
                'jumlah_kolom' => 3,
                'tingkat_diskualifikasi' => 3,
            ],

        ],

        /*
         * Tingkat pelanggaran — Pasal 11.6.d.
         *
         * Pelanggaran adalah sebab, hukuman adalah akibat. Tiap sanksi
         * menyimpan tingkat pelanggaran yang menyebabkannya.
         */
        'tingkat_pelanggaran' => [
            'ringan' => 'pembinaan',   // naik ke teguran setelah dua pembinaan
            'sedang' => 'teguran',     // langsung teguran
            'berat' => 'peringatan',   // langsung Peringatan I
        ],

        /*
         * Babak dan waktu — Pasal 9 ayat 3 dan Pasal 11 ayat 3.
         *
         * Durasi dalam milidetik, dihitung sebagai waktu bersih: berhenti saat
         * wasit menghentikan pertandingan dan saat hitungan terhadap pesilat
         * yang jatuh.
         */
        'babak' => [
            GolonganUsia::UsiaDini1->value => ['jumlah' => 2, 'durasi_ms' => 90_000],
            GolonganUsia::UsiaDini2->value => ['jumlah' => 2, 'durasi_ms' => 90_000],
            GolonganUsia::PraRemaja->value => ['jumlah' => 3, 'durasi_ms' => 120_000],
            GolonganUsia::Remaja->value => ['jumlah' => 3, 'durasi_ms' => 120_000],
            GolonganUsia::Dewasa->value => ['jumlah' => 3, 'durasi_ms' => 120_000],
            GolonganUsia::Master1->value => ['jumlah' => 2, 'durasi_ms' => 90_000],
            GolonganUsia::Master2->value => ['jumlah' => 2, 'durasi_ms' => 60_000],
        ],

        'istirahat_ms' => 60_000,

        /*
         * Menang WMP karena pertandingan tidak seimbang — Pasal 11.6.g.4.b
         * dan Pasal 9.4.3.
         *
         * Selisih 30 berlaku pada babak II atau III. Golongan usia dini memakai
         * selisih 20 dan berlaku sejak babak mana pun.
         */
        'wmp_selisih' => [
            'bawaan' => ['selisih' => 30, 'mulai_babak' => 2],
            GolonganUsia::UsiaDini1->value => ['selisih' => 20, 'mulai_babak' => 1],
            GolonganUsia::UsiaDini2->value => ['selisih' => 20, 'mulai_babak' => 1],
        ],

        /*
         * Hitungan teknik — Pasal 11.6.g.2 dan 11.6.g.3.
         */
        'hitungan_teknik' => [
            // Bisa sikap pasang saat dihitung: hitungan tetap lanjut sampai 9,
            // lalu pesilat yang dihitung menerima Teguran I.
            'teguran_pada_hitungan' => 9,

            // Tidak bisa bangkit: hitungan lanjut sampai 10, lawan menang mutlak.
            'mutlak_pada_hitungan' => 10,

            // Tiga hitungan berturut-turut dalam satu babak: lawan menang teknik.
            'menang_teknik_setelah_hitungan_beruntun' => 3,
        ],

        // Pasal 11.6.g.2.b.1: dokter punya 120 detik memutuskan fit atau tidak.
        'pemeriksaan_dokter_detik' => 120,

        // Pasal 11.6.g.5: tiga panggilan berinterval 30 detik sebelum dinyatakan
        // menang undur diri.
        'undur_diri' => ['jumlah_panggilan' => 3, 'interval_detik' => 30],

        /*
         * Pemecah seri pada menang angka — Pasal 11.6.g.1.b.
         * Dijalankan berurutan, berhenti pada yang pertama memisahkan.
         */
        'pemecah_seri' => [
            'hukuman_terendah',
            'nilai_prestasi_tertinggi', // urutan nilai 3, lalu 2, lalu 1
            'babak_tambahan',
            'berat_badan_teringan',
            'undian',
        ],

        /*
         * Sasaran — Pasal 11.6.c.
         * Tungkai dan lengan boleh jadi sasaran antara tetapi tidak bernilai.
         */
        'sasaran_bernilai' => ['dada', 'perut', 'rusuk_kiri', 'rusuk_kanan', 'punggung'],
        'sasaran_terlarang' => ['leher', 'kepala', 'kemaluan'],

    ],

    /*
    |--------------------------------------------------------------------------
    | Kategori Jurus — Pasal 12
    |--------------------------------------------------------------------------
    |
    | Naskah 2025 memakai istilah "Jurus", bukan "Seni".
    |
    */

    'jurus' => [

        // Pasal 12.1.f: skala 9.00 sampai 10.00.
        'skala' => ['min' => 9.00, 'max' => 10.00, 'langkah' => 0.01],

        /*
         * Nilai akhir adalah MEDIAN dari nilai seluruh juri — bukan
         * penjumlahan setelah membuang nilai tertinggi dan terendah. Karena
         * jumlah juri selalu genap, median diambil dari rata-rata dua nilai
         * tengah.
         */
        'agregasi' => 'median',

        'pengurangan' => [
            // Oleh juri: kesalahan rincian gerak, kesalahan urutan, gerakan
            // tertinggal, senjata terlepas tanpa menyentuh matras.
            'juri' => 0.01,

            // Oleh Pengawas/Dewan Wasit Juri: pelanggaran waktu 5-10 detik,
            // keluar gelanggang, senjata jatuh menyentuh lantai, pakaian tidak
            // sesuai, menahan gerakan lebih dari 5 detik.
            'pengawas' => 0.50,
        ],

        // Pasal 12.1.e.4.h: diskualifikasi ditunjukkan dengan skor 0,00.
        'skor_diskualifikasi' => 0.00,

        /*
         * Toleransi waktu penampilan — Pasal 12.1.e.1.a.
         * Lewat dari toleransi berarti pengurangan; jauh melewatinya berarti
         * diskualifikasi.
         */
        'toleransi_detik' => [
            'bawaan' => 5, // Remaja dan Dewasa
            GolonganUsia::UsiaDini1->value => 10,
            GolonganUsia::UsiaDini2->value => 10,
            GolonganUsia::PraRemaja->value => 10,
        ],

        'diskualifikasi_lewat_detik' => [
            'bawaan' => 10, // Remaja dan Dewasa
            GolonganUsia::UsiaDini1->value => 15,
            GolonganUsia::UsiaDini2->value => 15,
            GolonganUsia::PraRemaja->value => 15,
        ],

        /*
         * Waktu acuan penampilan per tahap — Pasal 12.1.c, dalam milidetik.
         */
        'waktu_acuan_ms' => [
            'tunggal' => [
                'penyisihan' => 80_000,  // 1 menit 20 detik, tangan kosong
                'semifinal' => 100_000,  // 1 menit 40 detik, senjata
                'final' => 180_000,      // 3 menit, lengkap
            ],
            'tunggal_bebas' => [
                'penyisihan' => 90_000,  // maksimal 1 menit 30 detik
                'semifinal' => 90_000,
                'final' => 180_000,      // maksimal 3 menit
            ],
        ],

        /*
         * Pemecah seri — Pasal 12.1.f.2.
         * Standar deviasi lebih rendah menang; artinya penilaian juri lebih rapat.
         */
        'pemecah_seri' => [
            'hukuman_terendah',
            'waktu_terdekat_ke_acuan',
            'standar_deviasi_terendah',
            'undian',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | VAR dan pengajuan keberatan — Pasal 15
    |--------------------------------------------------------------------------
    */

    'var' => [

        // Pasal 15.2.a dan 15.3.a: jatah kartu protes pelatih.
        'kartu_protes' => [
            'tanding' => 2, // per pertandingan, berlaku sepanjang tiga babak
            'jurus' => 1,   // per penampilan
        ],

        /*
         * Pasal 15 (Wasit Komisi Protes): keputusan tidak boleh lebih dari 5
         * menit. Lewat dari itu, prosesnya dilanjutkan dengan verifikasi juri
         * yang dipimpin Ketua Pertandingan.
         */
        'tenggat_keputusan_detik' => 300,

        // Protes kategori Jurus hanya untuk dua alasan.
        'alasan_jurus' => ['penampilan_tidak_sesuai_deskripsi', 'keluar_gelanggang'],

    ],

    /*
    |--------------------------------------------------------------------------
    | Protes Manajer — Pasal 15 ayat 4
    |--------------------------------------------------------------------------
    |
    | Berjenjang dengan tenggat masing-masing, dalam menit.
    |
    */

    'protes_manajer' => [
        'tingkat_pertama' => [
            'ambil_formulir_menit' => 10,   // sejak keputusan pemenang
            'kembalikan_formulir_menit' => 20,
            'keputusan_menit' => 120,       // oleh Ketua Pertandingan
        ],
        'banding' => [
            'ajukan_menit' => 10,           // sejak hasil tingkat pertama
            'kembalikan_formulir_menit' => 20,
            'keputusan_menit' => 120,       // oleh Delegasi Teknik, bersifat final
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gelanggang — Pasal 8
    |--------------------------------------------------------------------------
    */

    'gelanggang' => [
        'ukuran_m' => 10,             // matras bujur sangkar 10 m x 10 m
        'diameter_bidang_tanding_m' => 8,
        'tebal_matras_cm' => 5,
        'lebar_garis_cm' => 5,
    ],

];
