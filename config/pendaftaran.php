<?php

/*
|--------------------------------------------------------------------------
| Alur pendaftaran peserta
|--------------------------------------------------------------------------
|
| Dua gerbang keras berdiri di antara pendaftaran dan bagan: tagihan harus
| lunas, dan berkas atlet harus lengkap. Keduanya benar untuk kejuaraan yang
| menjalankan administrasinya di dalam sistem.
|
| Di lapangan, sebagian besar panitia masih memungut biaya dan memeriksa
| berkas secara manual — di meja sekretariat, dengan map kertas. Bagi mereka,
| dua gerbang itu bukan pengaman melainkan pekerjaan ganda: hal yang sama
| diperiksa dua kali, sekali di kertas dan sekali lagi di layar.
|
| Dua saklar di bawah mematikan PAKSAANNYA, bukan fiturnya. Tagihan tetap
| terbit dan tetap bisa ditandai lunas; menu verifikasi tetap ada dan tetap
| bisa memutuskan pendaftaran lama. Yang berubah hanya: pendaftaran tidak lagi
| menunggu keduanya.
|
| Keduanya BAWAANNYA MATI. Kejuaraan yang tidak menyalakannya sendiri berjalan
| dengan pengaman penuh seperti sebelumnya.
|
*/

return [

    /*
     * Pendaftaran yang diajukan langsung berstatus Terverifikasi, dan berkas
     * wajib tidak lagi memblokir pengajuan.
     *
     * `verified_by` sengaja dibiarkan kosong pada pendaftaran yang lolos lewat
     * saklar ini. Tidak ada manusia yang memutuskannya, dan mengisinya dengan
     * id sekretaris berarti memalsukan jejak audit.
     */
    'lewati_verifikasi' => filter_var(env('PENDAFTARAN_LEWATI_VERIFIKASI', false), FILTER_VALIDATE_BOOLEAN),

    /*
     * Tagihan berhenti menjadi syarat: verifikasi jalan tanpa memeriksa lunas,
     * dan pendaftaran tidak lagi membeku saat sesi pembayaran dibuka.
     */
    'lewati_pembayaran' => filter_var(env('PENDAFTARAN_LEWATI_PEMBAYARAN', false), FILTER_VALIDATE_BOOLEAN),

];
