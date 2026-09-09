import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

/**
 * Alamat WebSocket mengikuti alamat yang sedang dibuka, bukan yang ditanam
 * saat aset dibangun.
 *
 * HP juri membuka `http://<ip-server>:8000`, jadi `location.hostname` MEMANG
 * alamat server di jaringan itu. Sebelumnya alamatnya diambil dari
 * VITE_REVERB_HOST, yang dibaca Vite saat kompilasi dan ikut tertanam di dalam
 * berkas aset -- artinya IP yang berubah (router venue membagikan alamat lain,
 * mesin pindah jaringan) memutus WebSocket seluruh HP juri sekaligus, dan
 * memperbaikinya menuntut `npm run build` ulang di tengah kejuaraan. Panel
 * memang menampilkan penanda "Terputus", tapi baru sesudah percobaan sambungnya
 * kedaluwarsa, dan sepanjang itu tekanan tombol juri tidak sampai ke layar
 * siapa pun.
 *
 * VITE_REVERB_HOST tetap dihormati kalau memang diisi -- satu-satunya alasan
 * mengisinya adalah Reverb yang sengaja dijalankan di mesin LAIN dari yang
 * melayani HTTP.
 *
 * Hal yang sama berlaku untuk SKEMA-nya, dan itu sempat tidak berlaku: lihat
 * komentar `aman` di dalam siapkanEcho().
 */
/**
 * Koneksi dibuka lewat panggilan, bukan sebagai efek samping impor.
 *
 * Sebelumnya berkas ini menyambung ke Reverb begitu ia diimpor, dan
 * silat.js mengimpornya tanpa syarat -- artinya halaman yang tidak
 * mendengarkan channel apa pun (bagan overlay, halaman turnamen, medali)
 * tetap memegang satu koneksi WebSocket. Overlay bagan di vMix justru yang
 * paling lama dibiarkan terbuka: berjam-jam, sepanjang acara.
 */
export function siapkanEcho() {
    /*
     * Reverb di mesin LAIN dari yang melayani HTTP: satu-satunya keadaan yang
     * membenarkan alamat ditanam saat build. Kalau ia diisi, seluruh keputusan
     * di bawah mengikutinya -- termasuk portnya.
     */
    const alamatKhusus = (import.meta.env.VITE_REVERB_HOST || '').trim();

    /*
     * Aman atau tidak DITENTUKAN HALAMANNYA, bukan berkas .env saat build.
     *
     * Sebelumnya baris ini berbunyi `VITE_REVERB_SCHEME || location.protocol`,
     * dan cabang keduanya tidak pernah dijalankan: .env selalu mengisi
     * VITE_REVERB_SCHEME. Aset yang dibangun di LAN (`http`) karena itu
     * membawa `forceTLS: false` ke mana pun ia disajikan -- termasuk ke
     * halaman `https` yang lewat tunnel.
     *
     * Peramban menolak `ws://` dari halaman `https://` sebagai konten
     * campuran, dan Safari iOS menolaknya TANPA pesan yang terlihat: panel
     * cuma diam, lalu menyalakan penanda "Terputus" setelah percobaan
     * sambungnya kedaluwarsa. Terbaca sebagai "Reverb mati", padahal Reverb
     * tidak pernah dihubungi.
     *
     * Alasannya sama persis dengan alasan alamatnya mengikuti `location`:
     * yang benar saat aset dibangun belum tentu benar saat aset dibuka.
     */
    const aman = window.location.protocol === 'https:';

    const alamat = alamatKhusus || window.location.hostname;

    /*
     * Halaman `https` selalu lewat proxy yang menerbitkan `/app/*` di origin
     * yang SAMA dengan halamannya (lihat docs/TUNNELING.md) -- jadi portnya
     * port halaman itu, bukan REVERB_PORT. Menyambung ke 8080 dari balik
     * tunnel berarti menyambung ke port yang memang sengaja tidak dibuka
     * keluar.
     *
     * Di LAN tanpa proxy, Reverb berdiri di portnya sendiri dan tidak ada yang
     * bisa ditebak dari `location`, jadi di situ env yang dipakai.
     */
    const port = (alamatKhusus || ! aman)
        ? (import.meta.env.VITE_REVERB_PORT || 8080)
        : (window.location.port || 443);

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: alamat,
        wsPort: port,
        wssPort: port,
        forceTLS: aman,
        /*
         * Satu transport saja, yang memang cocok dengan halamannya. Membiarkan
         * keduanya menyala membuat pusher-js mencoba `ws://` dari halaman
         * `https://` sebagai cadangan -- percobaan yang pasti diblokir, dan
         * yang menunda penanda "Terputus" muncul selama beberapa detik tanpa
         * satu pun keterangan.
         */
        enabledTransports: aman ? ['wss'] : ['ws'],
    });

    return window.Echo;
}
