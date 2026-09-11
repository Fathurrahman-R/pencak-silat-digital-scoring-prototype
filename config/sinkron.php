<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Peran node
    |--------------------------------------------------------------------------
    |
    | Satu gelanggang satu laptop. Satu laptop lagi -- node global -- tidak
    | melayani gelanggang sama sekali: ia satu-satunya yang boleh menulis data
    | kejuaraan (atlet, kontingen, bagan, jadwal, pengguna), dan ia yang
    | menampung arsip bukti tiap partai yang sudah disahkan.
    |
    | Aturan satu penulis itu yang menggantikan resolusi konflik. Tanpa dia,
    | dua panitia yang menyunting bagan di dua laptop berbeda menghasilkan dua
    | bagan yang sama-sama merasa benar, dan tidak ada cara memilih di antara
    | keduanya yang tidak membuang pekerjaan seseorang.
    |
    */

    'peran' => env('SINKRON_PERAN', 'gelanggang'),

    /*
    | Menolak node gelanggang menulis data kejuaraan (PenjagaTulisGlobal).
    |
    | Menyala di mesin sungguhan. Dimatikan di rangkaian uji, yang menyiapkan
    | data prasyaratnya sendiri sambil menyamar jadi node gelanggang untuk
    | menguji kepemilikan -- penjagaannya sendiri diuji terpisah, dengan
    | saklar ini dinyalakan di dalam ujinya.
    */

    'jaga_penulis_global' => env('SINKRON_JAGA_PENULIS_GLOBAL', true),

    /*
    | Nama node ini, dipakai sebagai penanda asal di paket sinkron dan arsip.
    | Harus unik antar laptop dan sebaiknya menyebut gelanggangnya -- yang
    | membaca log saat ada yang salah adalah panitia, bukan mesin.
    */

    'node' => env('SINKRON_NODE', 'gelanggang-tanpa-nama'),

    /*
    | Kode gelanggang yang dipegang node ini, dipisah koma. Kode, bukan id:
    | id auto-increment berbeda antar basis data, sementara kode gelanggang
    | ("A", "B") sama di semua node karena ikut disinkronkan dari node global.
    |
    | Node global mengisinya kosong. Ia tidak memiliki gelanggang mana pun,
    | dan itu yang membuatnya tidak pernah mengklaim baris hasil pertandingan
    | milik node lain.
    */

    'arena' => env('SINKRON_ARENA', ''),

    /*
    | Token yang harus dibawa peer untuk menarik dari node INI. Kosong berarti
    | endpoint sinkron mati -- pilihan yang aman untuk pemasangan yang belum
    | dikonfigurasi, karena node yang belum disetel tidak diam-diam membuka
    | seluruh isi basis datanya ke LAN.
    */

    'token' => env('SINKRON_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Daftar peer
    |--------------------------------------------------------------------------
    |
    | Diisi dari .env sebagai satu baris, karena panitia menyalinnya antar
    | laptop lewat teks, bukan lewat berkas config:
    |
    |   SINKRON_PEER="global|http://192.168.1.10:8000|rahasia1,gelanggang-b|http://192.168.1.12:8000|rahasia2"
    |
    | Tiap peer: nama|url|token, dipisah koma antar peer.
    |
    */

    'peer' => array_values(array_filter(array_map(
        static function (string $baris): ?array {
            $bagian = array_map('trim', explode('|', $baris));

            if (count($bagian) < 3 || $bagian[0] === '' || $bagian[1] === '') {
                return null;
            }

            return ['nama' => $bagian[0], 'url' => rtrim($bagian[1], '/'), 'token' => $bagian[2]];
        },
        array_filter(array_map('trim', explode(',', (string) env('SINKRON_PEER', '')))),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Ukuran potongan
    |--------------------------------------------------------------------------
    |
    | Berapa baris per permintaan tarik. Penarikan digerakkan browser dalam
    | perulangan sampai kursor habis, bukan satu permintaan raksasa: mesin
    | gelanggang tidak menjalankan pekerja antrean saat hari-H, dan satu
    | permintaan yang menahan php-cgi selama semenit adalah satu proses yang
    | tidak melayani tekanan tombol juri selama semenit.
    |
    */

    'potongan' => (int) env('SINKRON_POTONGAN', 500),

];
