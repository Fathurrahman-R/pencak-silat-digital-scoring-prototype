import Alpine from 'alpinejs';

/**
 * Koneksi overlay siaran vMix.
 *
 * Beda dari partaiPanel (dipakai panel gelanggang) dalam dua hal yang
 * sengaja dijaga tegas:
 *
 *   1. Read-only. Tidak ada satu pun metode aksi di sini -- overlay tidak
 *      pernah mengirim apa pun ke server, hanya menerima.
 *   2. Channel publik (`window.Echo.channel(...)`), bukan presence
 *      (`.join(...)`). Presence butuh /broadcasting/auth yang mengandalkan
 *      sesi login, dan Browser Input vMix tidak pernah login.
 *
 * "Reconnect dengan backoff" dari rencana awal sepenuhnya ditangani
 * pustaka Pusher-js yang dipakai Echo -- tidak ditulis ulang di sini.
 * Yang ditambahkan cuma resync state PENUH setiap kali koneksi kembali
 * tersambung, supaya overlay yang sempat putus tidak terus menampilkan
 * angka basi begitu jaringan pulih.
 */

Alpine.data('overlayLive', (cfg) => ({
    cfg,
    memuat: true,
    adaPartai: false,
    match: null,
    kelas: null,
    babakLabel: null,
    red: null,
    blue: null,
    skorTotal: { merah: 0, biru: 0 },
    hukuman: {
        merah: { pembinaan: 0, teguran: 0, peringatan: 0 },
        biru: { pembinaan: 0, teguran: 0, peringatan: 0 },
    },
    sisaMsTampil: 0,
    kilat: null,
    _tickAnchorMs: 0,
    _tickAt: 0,
    _rafId: null,
    _kilatTimeout: null,

    async init() {
        await this.muatUlang();
        this._pasangEcho();
    },

    destroy() {
        this._hentikanInterpolasi();
    },

    get tampilWaktu() {
        const totalDetik = Math.ceil(this.sisaMsTampil / 1000);
        const menit = Math.floor(totalDetik / 60);
        const detik = totalDetik % 60;

        return `${String(menit).padStart(2, '0')}:${String(detik).padStart(2, '0')}`;
    },

    async muatUlang() {
        try {
            const res = await fetch(this.cfg.state, { headers: { Accept: 'application/json' } });
            const data = await res.json();

            this._terapkan(data);
        } catch (e) {
            // Overlay tidak punya siapa pun untuk melapor -- dibiarkan, akan
            // dicoba lagi begitu event berikutnya tiba atau koneksi pulih.
        }

        this.memuat = false;
    },

    _terapkan(data) {
        this.adaPartai = data.ada_partai;

        if (!data.ada_partai) {
            this._hentikanInterpolasi();
            this.sisaMsTampil = 0;

            return;
        }

        this.match = data.match;
        this.kelas = data.kelas;
        this.babakLabel = data.babak_label;
        this.red = data.red;
        this.blue = data.blue;
        this.skorTotal = data.skor_total;
        this.hukuman = data.hukuman;

        this._segarkanTimer(data.timer);
    },

    _segarkanTimer(timer) {
        if (!timer) {
            this._hentikanInterpolasi();
            this.sisaMsTampil = 0;

            return;
        }

        let sisaMs = timer.duration_ms - timer.accumulated_ms;

        if (timer.status === 'berjalan' && timer.started_at) {
            const lewatMs = Date.now() - new Date(timer.started_at).getTime();
            sisaMs = Math.max(0, sisaMs - lewatMs);
        }

        this._tickAnchorMs = sisaMs;
        this._tickAt = performance.now();
        this.sisaMsTampil = sisaMs;

        if (timer.status === 'berjalan') {
            this._mulaiInterpolasi();
        } else {
            this._hentikanInterpolasi();
        }
    },

    _mulaiInterpolasi() {
        this._hentikanInterpolasi();

        const langkah = () => {
            const lewatMs = performance.now() - this._tickAt;
            this.sisaMsTampil = Math.max(0, this._tickAnchorMs - lewatMs);
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

    /** Kilatan singkat (lihat .silat-kilat di silat.css) saat nilai baru terbit untuk sudut tertentu. */
    _kilatkan(corner) {
        this.kilat = corner;
        clearTimeout(this._kilatTimeout);
        this._kilatTimeout = setTimeout(() => {
            this.kilat = null;
        }, 500);
    },

    _pasangEcho() {
        if (!this.cfg.arenaId) {
            return;
        }

        const segarkan = () => this.muatUlang();
        const channel = window.Echo.channel(`public-live.${this.cfg.arenaId}`);

        channel
            .listen('.timer.berubah', segarkan)
            .listen('.hukuman.terbit', segarkan)
            .listen('.partai.berubah', segarkan)
            .listen('.skor.terbit', (e) => {
                this._kilatkan(e.corner);
                segarkan();
            });

        // Resync penuh tiap kali koneksi WebSocket kembali tersambung --
        // event yang terlewat selama putus tidak pernah terulang sendiri.
        const pusher = window.Echo.connector?.pusher;
        pusher?.connection?.bind('connected', segarkan);
    },
}));
