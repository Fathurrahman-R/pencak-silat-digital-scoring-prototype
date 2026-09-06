<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambang peringatan gelanggang
    |--------------------------------------------------------------------------
    |
    | Angka-angka ini DIUKUR, bukan ditebak. Seluruhnya berasal dari basis data
    | berisi 100.500 baris judge_inputs -- setara satu hari pertandingan di
    | empat gelanggang -- yang dilayani nginx dengan delapan proses php-cgi.
    |
    | Rancangan awal menetapkan kuning di 300 ms untuk waktu tarikan state.
    | Pengukuran membantahnya: baseline yang SEHAT sudah 182-317 ms. Ambang itu
    | akan menyala kuning sejak jam pertama hari pertama, dan peringatan yang
    | selalu menyala tidak dibaca siapa pun -- ia justru melatih operator
    | mengabaikan lencana ini persis saat lencananya mulai benar.
    |
    | Angka di bawah memberi jarak dari baseline: kuning saat tarikan sudah
    | separuh lebih lambat dari yang pernah terlihat sehat, merah saat sudah
    | dua kali lipatnya.
    |
    */

    'ambang' => [

        /*
        | Waktu tarikan state panel, persentil 95, dalam milidetik.
        |
        | Metrik yang paling jujur dari ketiganya: ia mengukur GEJALA yang
        | dirasakan operator, bukan tebakan tentang penyebabnya. Dua metrik
        | lain di bawah menjelaskan kenapa.
        */
        'state_p95_ms' => [
            'kuning' => (int) env('PANTAU_STATE_KUNING', 500),
            'merah' => (int) env('PANTAU_STATE_MERAH', 900),
        ],

        /*
        | Jumlah baris judge_inputs.
        |
        | Satu hari pertandingan empat gelanggang meninggalkan sekitar seratus
        | ribu baris. Kuning di seratus lima puluh ribu berarti peringatan
        | muncul di hari kedua, saat masih ada jeda antar sesi untuk
        | menanganinya -- bukan di hari terakhir saat semua orang sibuk.
        */
        'baris_judge_inputs' => [
            'kuning' => (int) env('PANTAU_BARIS_KUNING', 150_000),
            'merah' => (int) env('PANTAU_BARIS_MERAH', 300_000),
        ],

        /*
        | Partai yang sudah disahkan tapi arsipnya belum sampai ke node global.
        |
        | Ambangnya kecil dan memang harus kecil: tiap partai di sini adalah
        | satu partai yang buktinya cuma ada di satu laptop. Lima sudah cukup
        | untuk menyatakan bahwa dorongan otomatisnya sedang tidak bekerja.
        */
        'partai_belum_terarsip' => [
            'kuning' => (int) env('PANTAU_ARSIP_KUNING', 5),
            'merah' => (int) env('PANTAU_ARSIP_MERAH', 15),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Pengambilan sampel waktu tarikan
    |--------------------------------------------------------------------------
    |
    | Pengukurannya sendiri harus murah. Endpoint state ditarik tiap panel yang
    | terbuka, tiap ada siaran, ditambah sekali tiap dua puluh detik selama
    | babak berjalan -- alat ukur yang membebani jalur itu akan mengubah angka
    | yang sedang diukurnya.
    |
    | Satu dari lima permintaan dicatat, ditulis sebagai satu baris ke berkas
    | biasa. Bukan cache (yang butuh kunci) dan bukan tabel (yang menambah
    | penulisan ke basis data yang sedang diselidiki).
    |
    */

    'sampel' => [
        'satu_dari' => (int) env('PANTAU_SAMPEL', 5),
        'simpan_baris' => (int) env('PANTAU_SAMPEL_BARIS', 500),
        'berkas' => 'pemantauan/state.log',
    ],

    /*
    | Berapa lama hasil hitungan lencana dipakai ulang. Operator tidak butuh
    | angka per detik; ia butuh tahu apakah ada yang perlu dikerjakan di jeda
    | berikutnya.
    */
    'cache_detik' => (int) env('PANTAU_CACHE', 60),

];
