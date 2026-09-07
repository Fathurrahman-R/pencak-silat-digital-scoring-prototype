/**
 * Pengukur latensi gelanggang.
 *
 * Penanda sambungan yang sudah ada hanya menjawab dua keadaan: tersambung
 * atau putus. Yang tidak terjawabnya justru keadaan yang paling merugikan --
 * jaringan yang MELAMBAT tapi belum putus. Panel tetap menulis "tersambung",
 * tombol tetap hidup, dan nilai tetap terkirim; hanya saja sampainya
 * terlambat, dan tidak ada yang bisa dilihat petugas untuk menyadarinya.
 * SIMULASI-LAPANGAN.md mencatat jendela delapan detik semacam itu.
 *
 * Diukur lewat ping-pong protokol Pusher di soket yang sudah terbuka, bukan
 * lewat endpoint HTTP baru. Reverb membalas `pusher:ping` dengan
 * `pusher:pong` (Protocols/Pusher/EventHandler.php), jadi tidak ada rute
 * baru, tidak ada kueri, dan tidak ada izin resource yang harus dilewati --
 * penting untuk mesin gelanggang yang CPU-nya sudah direbutkan encoder vMix.
 *
 * Batasnya disebutkan terus terang: yang terukur jalur SIARAN, bukan jalur
 * POST nilai. Kegagalan jalur POST punya penandanya sendiri (pesan galat dan
 * lencana antrean tertahan di panel juri).
 */

const JEDA_DENYUT_MS = 5000;
const TENGGAT_PONG_MS = 3000;

/*
 * Tiga sampel, bukan satu.
 *
 * Angka yang ditampilkan selalu sampel terakhir -- itu yang ditanyakan
 * petugas ("sekarang berapa"). Tapi WARNA-nya diambil dari median tiga sampel
 * terakhir, karena satu lonjakan tunggal di jaringan LAN adalah hal biasa dan
 * titik yang berkedip-kedip ganti warna di depan juri justru mengajari mereka
 * mengabaikannya.
 */
const JUMLAH_SAMPEL = 3;

export function pantauLatensi(Alpine) {
    const pusher = window.Echo?.connector?.pusher;

    if (!pusher) {
        return;
    }

    const store = Alpine.store('koneksi');
    const sampel = [];

    let menungguPong = false;
    let dikirimPada = 0;
    let tenggat = null;

    const catat = (ms) => {
        sampel.push(ms);

        while (sampel.length > JUMLAH_SAMPEL) {
            sampel.shift();
        }

        store.latensiMs = ms;
        store.latensiAcuanMs = median(sampel);
    };

    const kosongkan = () => {
        sampel.length = 0;
        store.latensiMs = null;
        store.latensiAcuanMs = null;
    };

    const selesaikan = () => {
        menungguPong = false;

        if (tenggat !== null) {
            clearTimeout(tenggat);
            tenggat = null;
        }
    };

    const ping = () => {
        if (pusher.connection.state !== 'connected') {
            selesaikan();
            kosongkan();

            return;
        }

        // Ping sebelumnya belum dijawab; jangan menumpuk permintaan di atas
        // jaringan yang justru sedang kepayahan.
        if (menungguPong) {
            return;
        }

        menungguPong = true;
        dikirimPada = performance.now();

        try {
            pusher.connection.send_event('pusher:ping', {});
        } catch {
            selesaikan();

            return;
        }

        /*
         * Pong yang tidak pernah datang dicatat sebagai sampel terburuk, BUKAN
         * sebagai putus.
         *
         * `tombol-nilai` mematikan dirinya dari `$store.koneksi.tersambung`.
         * Kalau tenggat ping ikut menyetel status putus, satu kedipan jaringan
         * akan mematikan tombol penilaian di tengah babak. Deteksi putus tetap
         * sepenuhnya milik `pantauKoneksi()`; berkas ini tidak pernah
         * menyentuh `status`.
         */
        tenggat = setTimeout(() => {
            menungguPong = false;
            tenggat = null;
            catat(TENGGAT_PONG_MS);
        }, TENGGAT_PONG_MS);
    };

    /*
     * `connection.bind('message')` menerima SETIAP pesan masuk, termasuk pong.
     *
     * Penjagaan `menungguPong` bukan hiasan: pusher-js mengirim ping-nya
     * sendiri saat sambungan sepi, dan pong balasannya tidak boleh dihitung
     * sebagai sampel kita -- selisih waktunya akan diukur dari ping yang salah.
     */
    pusher.connection.bind('message', (pesan) => {
        if (pesan?.event !== 'pusher:pong' || !menungguPong) {
            return;
        }

        const ms = Math.round(performance.now() - dikirimPada);

        selesaikan();
        catat(ms);
    });

    /*
     * Ping sekali begitu tersambung, bukan menunggu denyut berikutnya. Panel
     * yang baru dibuka atau baru pulih akan menampilkan angkanya dalam
     * hitungan puluhan milidetik, bukan lima detik kemudian.
     */
    pusher.connection.bind('state_change', ({ current }) => {
        if (current === 'connected') {
            ping();

            return;
        }

        selesaikan();
        kosongkan();
    });

    /*
     * Tab tersembunyi tidak diping: peramban memperlambat timer-nya, dan
     * angka yang lahir dari timer yang dicekik adalah angka bohong. Yang
     * penting justru sesudahnya -- begitu tab kembali dilihat, angkanya harus
     * segar, bukan sisa dari sepuluh menit lalu.
     */
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            selesaikan();

            return;
        }

        kosongkan();
        ping();
    });

    /*
     * Peramban tahu jaringannya lepas jauh sebelum WebSocket menyadarinya --
     * `pantauKoneksi()` memakai kejadian yang sama untuk alasan yang sama.
     *
     * Tanpa ini angka terakhir bertahan di store sepanjang jendela delapan
     * detik itu, lalu muncul lagi sekejap begitu panel tersambung ulang --
     * angka lama yang terbaca seolah baru diukur. Terukur di Playwright:
     * status sudah "putus" sementara store masih memegang sampel terakhir.
     */
    window.addEventListener('offline', () => {
        selesaikan();
        kosongkan();
    });

    /*
     * Denyutnya sengaja tidak pernah dibersihkan. Ia mati bersama halamannya,
     * dan halaman yang tersimpan di bfcache membekukan timer-nya sendiri lalu
     * melanjutkannya saat kembali. Membersihkannya di `pagehide` justru
     * membunuh pengukuran pada panel yang kembali lewat tombol Back --
     * angkanya akan berhenti di sampel terakhir tanpa ada yang menyadarinya.
     */
    setInterval(() => {
        if (document.hidden) {
            return;
        }

        ping();
    }, JEDA_DENYUT_MS);

    ping();
}

function median(nilai) {
    if (nilai.length === 0) {
        return null;
    }

    const urut = [...nilai].sort((a, b) => a - b);

    return urut[Math.floor(urut.length / 2)];
}
