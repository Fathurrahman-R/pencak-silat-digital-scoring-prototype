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
    const alamat = import.meta.env.VITE_REVERB_HOST || window.location.hostname;

    // Reverb berjalan di portnya sendiri, terpisah dari port HTTP. Tidak ada
    // nilai bawaan yang masuk akal untuk ditebak dari `location`, jadi ini tetap
    // dari env; 8080 adalah port yang dipakai `reverb:start` di dokumentasi.
    const port = import.meta.env.VITE_REVERB_PORT || 8080;

    const skema = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: alamat,
        wsPort: port,
        wssPort: port,
        forceTLS: skema === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
}
