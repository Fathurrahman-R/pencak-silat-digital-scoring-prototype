import Alpine from 'alpinejs';

import './echo';

/**
 * Bundel gelanggang: panel juri, panel wasit, panel operator, live score
 * publik, dan overlay siaran.
 *
 * Sengaja terpisah dari app.js. Halaman admin tidak memuatnya, dan halaman
 * gelanggang tidak memuat ApexCharts maupun komponen admin yang tidak
 * dipakainya — penting untuk overlay vMix, yang berbagi CPU dengan encoder
 * streaming.
 */

/**
 * Status koneksi realtime, dipakai indikator di tiap panel.
 *
 * Ini bukan hiasan. Kalau WebSocket juri terputus tanpa ia sadari, tombolnya
 * tetap bisa ditekan tapi nilainya tidak pernah sampai — dan pertandingan
 * berjalan terus dengan skor yang salah. Karena itu status koneksi disiarkan
 * ke seluruh komponen, dan tombol nilai mematikan dirinya sendiri saat putus.
 */
Alpine.store('koneksi', {
    status: 'menyambung',

    get tersambung() {
        return this.status === 'tersambung';
    },

    tandai(status) {
        this.status = status;
    },
});

function pantauKoneksi() {
    const pusher = window.Echo?.connector?.pusher;

    if (!pusher) {
        return;
    }

    const petakan = {
        connected: 'tersambung',
        connecting: 'menyambung',
        unavailable: 'putus',
        failed: 'putus',
        disconnected: 'putus',
    };

    Alpine.store('koneksi').tandai(petakan[pusher.connection.state] ?? 'menyambung');

    pusher.connection.bind('state_change', ({ current }) => {
        Alpine.store('koneksi').tandai(petakan[current] ?? 'menyambung');
    });
}

/**
 * Timer partai.
 *
 * Waktu resmi selalu milik server. Komponen ini hanya menginterpolasi di
 * antara tick supaya tampilannya halus; ia tidak pernah menghitung sendiri
 * berapa waktu yang tersisa. Jam perangkat juri dan operator tidak dipercaya
 * sama sekali.
 */
Alpine.data('silatTimer', (awalMs = 0) => ({
    sisaMs: awalMs,
    berjalan: false,
    _rafId: null,
    _tickPadaMs: 0,
    _tickDiterima: 0,

    init() {
        this.$watch('berjalan', (berjalan) => (berjalan ? this._mulaiInterpolasi() : this._hentikanInterpolasi()));
    },

    /** Dipanggil setiap tick dari server. */
    terimaTick({ sisa_ms: sisaMs, berjalan }) {
        this.sisaMs = sisaMs;
        this._tickPadaMs = sisaMs;
        this._tickDiterima = performance.now();
        this.berjalan = berjalan;
    },

    _mulaiInterpolasi() {
        const langkah = () => {
            const lewat = performance.now() - this._tickDiterima;
            this.sisaMs = Math.max(0, this._tickPadaMs - lewat);
            this._rafId = requestAnimationFrame(langkah);
        };

        this._rafId = requestAnimationFrame(langkah);
    },

    _hentikanInterpolasi() {
        if (this._rafId !== null) {
            cancelAnimationFrame(this._rafId);
            this._rafId = null;
        }
    },

    destroy() {
        this._hentikanInterpolasi();
    },

    get tampil() {
        const totalDetik = Math.ceil(this.sisaMs / 1000);
        const menit = Math.floor(totalDetik / 60);
        const detik = totalDetik % 60;

        return `${String(menit).padStart(2, '0')}:${String(detik).padStart(2, '0')}`;
    },
}));

window.Alpine = Alpine;
Alpine.start();
pantauKoneksi();
