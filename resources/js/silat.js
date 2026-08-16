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

/**
 * Panel gelanggang: operator, wasit, dewan juri.
 *
 * Satu factory dipakai ketiganya -- tombol yang tampil berbeda per panel
 * (diatur @resource di Bladenya masing-masing berdasarkan resource key
 * pengguna yang login), tapi state dan cara menyambung ke Reverb sama
 * persis. `cfg` datang dari server lewat @js(...): alamat resync dan
 * seluruh alamat aksi, supaya JS tidak pernah menyusun sendiri route Laravel.
 *
 * Sumber kebenaran skor dan hukuman selalu resync penuh (fetch ulang state),
 * bukan menambal angka lokal dari payload event satu-satu. Event Reverb di
 * sini cuma pemicu "sesuatu berubah, ambil lagi" -- korektif lebih murah
 * daripada berusaha menjaga dua salinan angka tetap sinkron selamanya.
 */
Alpine.data('partaiPanel', (cfg) => ({
    cfg,
    memuat: true,
    match: { id: cfg.matchId, status: 'terjadwal', current_round: null, red: null, blue: null, winner_registration_id: null, win_reason: null, ratified: false },
    rounds: [],
    skorTotal: { merah: 0, biru: 0 },
    hukuman: {
        merah: { pembinaan: 0, teguran: 0, peringatan: 0, diskualifikasi: false },
        biru: { pembinaan: 0, teguran: 0, peringatan: 0, diskualifikasi: false },
    },
    tawaranWmp: null,
    peraturan: { jumlah_juri: 3, ambang_sepakat: 2, window_konsensus_ms: 2000, jumlah_babak: 3 },
    officials: [],
    riwayat: [],
    pesan: null,
    galat: null,
    indikator: { red: [], blue: [] },
    sisaMsTampil: 0,
    _petaJuri: {},
    _waktuIndikator: { red: null, blue: null },
    _tickAnchorMs: 0,
    _tickAt: 0,
    _rafId: null,

    async init() {
        await this.muatUlang();
        this._pasangEcho();
    },

    destroy() {
        this._hentikanInterpolasi();
    },

    /** Dipakai tampilan jam -- MM:SS dari sisaMsTampil, yang diinterpolasi lokal antara dua siaran timer. */
    get tampilWaktu() {
        const totalDetik = Math.ceil(this.sisaMsTampil / 1000);
        const menit = Math.floor(totalDetik / 60);
        const detik = totalDetik % 60;

        return `${String(menit).padStart(2, '0')}:${String(detik).padStart(2, '0')}`;
    },

    get babakAktif() {
        return this.rounds.find((r) => r.round === this.match.current_round) ?? null;
    },

    get sudahSelesai() {
        return this.match.status === 'selesai';
    },

    /**
     * Nomor babak yang tombol "Mulai" harus targetkan, atau `null` kalau
     * tombol itu tidak relevan sekarang.
     *
     * Belum ada babak sama sekali, atau babak sekarang baru direset ke
     * "belum_mulai" -- keduanya berarti (ULANGI) babak yang sama, bukan maju
     * ke babak berikutnya. Hanya saat babak sekarang benar-benar "selesai"
     * barulah nomornya maju.
     */
    get babakUntukDimulai() {
        if (this.sudahSelesai) {
            return null;
        }

        if (!this.babakAktif || this.babakAktif.status === 'belum_mulai') {
            return this.match.current_round ?? 1;
        }

        if (this.babakAktif.status === 'selesai') {
            return this.match.current_round + 1;
        }

        return null;
    },

    async muatUlang() {
        const res = await fetch(this.cfg.state, { headers: { Accept: 'application/json' } });

        if (!res.ok) {
            this.galat = 'Gagal memuat state partai.';
            return;
        }

        this._terapkan(await res.json());
        this.memuat = false;
    },

    /**
     * Semua tombol aksi memanggil ini -- POST ke alamat yang dikirim server,
     * umpan balik lewat `pesan`/`galat`.
     *
     * State disegarkan langsung di sini setelah sukses, tidak menunggu
     * siaran Echo memantul balik. Siaran itu tetap ada untuk memperbarui
     * panel LAIN yang sedang menonton partai yang sama, tapi orang yang baru
     * saja menekan tombol tidak boleh bergantung padanya -- kalau Reverb
     * sedang tidak terjangkau, ia tetap harus langsung melihat akibat
     * tekanannya sendiri.
     */
    async kirim(url, data = {}) {
        this.galat = null;

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(data),
            });

            const body = await res.json().catch(() => ({}));

            if (!res.ok) {
                this.galat = body.errors ? Object.values(body.errors).flat().join(' ') : (body.message ?? 'Gagal.');

                return false;
            }

            this.pesan = body.pesan ?? null;
            await this.muatUlang();

            return true;
        } catch (e) {
            this.galat = 'Tidak bisa menghubungi server.';

            return false;
        }
    },

    mulaiBabak() {
        if (this.babakUntukDimulai === null) {
            return;
        }

        return this.kirim(this.cfg.timerMulai, { babak: this.babakUntukDimulai });
    },

    jeda() {
        return this.kirim(this.cfg.timerJeda);
    },

    lanjutkan() {
        return this.kirim(this.cfg.timerLanjut);
    },

    resetBabak() {
        return this.kirim(this.cfg.timerReset);
    },

    selesaikanBabak() {
        return this.kirim(this.cfg.timerSelesai);
    },

    akhiri(corner, sebab) {
        return this.kirim(this.cfg.akhiri, { corner, sebab });
    },

    sahkan() {
        return this.kirim(this.cfg.sahkan);
    },

    kirimHukuman(corner, tingkat, catatan) {
        return this.kirim(this.cfg.hukuman, { babak: this.match.current_round, corner, tingkat, catatan: catatan || null });
    },

    kirimHitungan(corner, hitungan) {
        return this.kirim(this.cfg.hitungan, { babak: this.match.current_round, corner, hitungan });
    },

    batalkanNilai(id, alasan) {
        return this.kirim(this.cfg.nilaiBatal.replace('__ID__', id), { alasan });
    },

    batalkanHukuman(id, alasan) {
        return this.kirim(this.cfg.hukumanBatal.replace('__ID__', id), { alasan });
    },

    _terapkan(data) {
        this.match = data.match;
        this.rounds = data.rounds;
        this.skorTotal = data.skor_total;
        this.hukuman = data.hukuman;
        this.tawaranWmp = data.tawaran_wmp;
        this.peraturan = data.peraturan;
        this.officials = data.officials;
        this.riwayat = data.riwayat;
        this._petaJuri = Object.fromEntries(
            data.officials.filter((o) => o.role === 'juri').map((o) => [o.user_id, o.number]),
        );

        this._segarkanTimer();
    },

    /**
     * Menyegarkan titik acuan hitung mundur dari data resync/siaran terbaru,
     * lalu menyalakan atau mematikan interpolasi lokal sesuai statusnya.
     *
     * Sengaja tidak lewat komponen `x-data` bersarang: `x-ref` yang
     * ditempatkan di elemen yang sama dengan `x-data`-nya sendiri tidak
     * tercatat di `$refs` milik komponen INDUK -- ditemukan langsung lewat
     * pengujian manual, bukan dugaan. Jadi hitung mundurnya dijalankan di
     * sini, di komponen induk, bukan lewat `terimaTick()` pada child scope
     * yang ternyata tidak terjangkau.
     */
    _segarkanTimer() {
        const aktif = this.babakAktif;

        if (!aktif) {
            this._hentikanInterpolasi();
            this.sisaMsTampil = 0;

            return;
        }

        this._tickAnchorMs = aktif.sisa_ms;
        this._tickAt = performance.now();
        this.sisaMsTampil = aktif.sisa_ms;

        if (aktif.status === 'berjalan') {
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

    _pasangEcho() {
        if (!this.cfg.arenaId) {
            return;
        }

        const segarkan = () => this.muatUlang();

        window.Echo.join(`arena.${this.cfg.arenaId}`)
            .listen('.timer.berubah', segarkan)
            .listen('.skor.terbit', (e) => {
                this._tandaiIndikatorSelesai(e.corner);
                segarkan();
            })
            .listen('.hukuman.terbit', segarkan)
            .listen('.partai.berubah', segarkan)
            .listen('.juri.input', (e) => this._padaInputJuri(e));
    },

    /** Titik indikator "juri menekan" -- murni tampilan sementara, dibersihkan sendiri setelah window konsensus lewat atau nilainya terbit. */
    _padaInputJuri(e) {
        if (e.match_id !== this.match.id || e.ditolak) {
            return;
        }

        const nomor = this._petaJuri[e.judge_id];

        if (!nomor) {
            return;
        }

        const sisi = e.corner === 'red' ? 'red' : 'blue';

        if (!this.indikator[sisi].includes(nomor)) {
            this.indikator[sisi] = [...this.indikator[sisi], nomor];
        }

        clearTimeout(this._waktuIndikator[sisi]);
        this._waktuIndikator[sisi] = setTimeout(() => {
            this.indikator[sisi] = [];
        }, this.peraturan.window_konsensus_ms + 500);
    },

    _tandaiIndikatorSelesai(corner) {
        const sisi = corner === 'red' ? 'red' : 'blue';
        this.indikator[sisi] = [];
        clearTimeout(this._waktuIndikator[sisi]);
    },
}));

window.Alpine = Alpine;
Alpine.start();
pantauKoneksi();
