import Alpine from 'alpinejs';

import './echo';
import './overlay/connection';

/**
 * Bundel gelanggang: panel juri, panel wasit, panel operator, live score
 * publik, dan overlay siaran.
 *
 * Sengaja terpisah dari app.js. Halaman admin tidak memuatnya, dan halaman
 * gelanggang tidak memuat satu pun komponen admin yang tidak
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

    /*
     * Hitungan teknik babak berjalan. Akibatnya paling berat di seluruh
     * sistem -- hitungan ke-9 Teguran I, ke-10 menang mutlak, beruntun ketiga
     * menang teknik -- jadi angkanya harus terbaca wasit SEBELUM ia menekan,
     * bukan disimpulkan dari partai yang tiba-tiba berhenti.
     */
    hitunganTeknik: {
        merah: { jumlah: 0, beruntun: 0, terakhir: null },
        biru: { jumlah: 0, beruntun: 0, terakhir: null },
        ambang_beruntun: 3,
        ambang_teguran: 9,
        ambang_mutlak: 10,
    },
    tawaranWmp: null,
    peraturan: { jumlah_juri: 3, ambang_sepakat: 2, window_konsensus_ms: 2000, jumlah_babak: 3 },
    officials: [],
    riwayat: [],
    keberatan: { kartu: { merah: 2, biru: 2 }, var_reviews: [], protes_manajer: [] },
    verifikasi: null,
    pesan: null,
    galat: null,

    /*
     * Indikator per TEKNIK, dipakai panel operator: yang menentukan sebuah
     * nilai sah bukan berapa juri yang menekan, melainkan berapa juri yang
     * menekan teknik yang SAMA dalam satu window konsensus. Operator yang
     * hanya melihat "dua juri menekan" tidak bisa membedakan dua pukulan yang
     * sepakat dari satu pukulan dan satu tendangan yang tidak.
     */
    indikatorTeknik: {
        red: { pukulan: [], tendangan: [] },
        blue: { pukulan: [], tendangan: [] },
    },
    sisaMsTampil: 0,
    // Berkunci `${sisi}-${teknik}`: tiap teknik punya jendela konsensusnya
    // sendiri, jadi tenggat padamnya pun sendiri-sendiri.
    _waktuIndikator: {},
    _sedangMenarik: false,
    _perluTarikLagi: false,
    _tickAnchorMs: 0,
    _tickAt: 0,
    _rafId: null,
    wakeLock: null,

    async init() {
        await this.muatUlang();
        this._pasangEcho();
        this._kunciLayar();

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this._kunciLayar();
            }
        });
    },

    destroy() {
        this._hentikanInterpolasi();
    },

    /**
     * Layar tidak boleh tidur sementara panel gelanggang terbuka -- juri
     * yang layarnya mati di tengah babak berarti tombolnya tidak bisa
     * ditekan sampai dibangunkan lagi. Gagal diam-diam kalau browser
     * menolak (lazim terjadi kalau tab sedang tidak fokus); dicoba lagi
     * begitu tab terlihat lagi lewat listener visibilitychange di init().
     */
    async _kunciLayar() {
        if (!('wakeLock' in navigator)) {
            return;
        }

        try {
            this.wakeLock = await navigator.wakeLock.request('screen');
            this.wakeLock.addEventListener('release', () => {
                this.wakeLock = null;
            });
        } catch (e) {
            // Diam -- dicoba lagi saat tab kembali terlihat.
        }
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

    /**
     * Selisih tiap sudut terhadap lawannya, sudah bertanda -- dipakai panel
     * operator, tempat kedua blok bertumpuk dan angkanya dibandingkan sekolom.
     * Kosong saat imbang: "+ 0" dan "- 0" tidak menyatakan apa pun.
     */
    get selisih() {
        const beda = (this.skorTotal.merah ?? 0) - (this.skorTotal.biru ?? 0);

        if (beda === 0) {
            return { merah: '', biru: '' };
        }

        const besar = Math.abs(beda);

        return beda > 0
            ? { merah: `+ ${besar}`, biru: `- ${besar}` }
            : { merah: `- ${besar}`, biru: `+ ${besar}` };
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
        /*
         * Satu tarikan pada satu waktu, dan letupan siaran digabung jadi satu.
         *
         * Tiap siaran dulu memicu tarikan state penuh sendiri-sendiri. Saat
         * juri menekan tombol beruntun, satu tekanan bisa melahirkan beberapa
         * siaran, dan tiap panel yang terbuka menarik state untuk masing-
         * masing siaran itu. Tarikannya ~200ms dan server melayaninya satu per
         * satu, jadi antreannya tumbuh lebih cepat daripada terurai: indikator
         * dan angka di layar tertinggal makin jauh dari gelanggang.
         *
         * Yang dijamin di sini: selama satu tarikan berjalan, permintaan
         * berikutnya tidak menambah antrean -- ia hanya menandai bahwa masih
         * ada yang perlu ditarik, dan satu tarikan susulan dijalankan sesudah
         * yang sekarang selesai. Berapa pun siaran yang datang di antaranya,
         * hasil akhirnya tetap keadaan TERBARU, karena yang ditarik selalu
         * keadaan saat itu juga, bukan antrean keadaan lama.
         */
        if (this._sedangMenarik) {
            this._perluTarikLagi = true;

            return;
        }

        this._sedangMenarik = true;

        try {
            const res = await fetch(this.cfg.state, { headers: { Accept: 'application/json' } });

            if (!res.ok) {
                this.galat = 'Gagal memuat state partai.';

                return;
            }

            this._terapkan(await res.json());
            this.memuat = false;
        } finally {
            this._sedangMenarik = false;

            if (this._perluTarikLagi) {
                this._perluTarikLagi = false;
                this.muatUlang();
            }
        }
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

    /*
     * ----------------------------------------------------------------
     * Verifikasi juri -- Pasal 13
     * ----------------------------------------------------------------
     */

    /**
     * Verifikasi sedang menahan pertandingan.
     *
     * Panel juri memakai ini untuk MENGAMBIL ALIH layarnya sepenuhnya:
     * tombol nilai tidak boleh tersisa di belakang layar verifikasi, karena
     * juri yang sedang diminta menjawab tidak boleh salah tekan dan memberi
     * nilai untuk kejadian yang justru sedang dipertanyakan.
     */
    get verifikasiBerjalan() {
        return this.verifikasi?.berjalan === true;
    },

    /** Apakah pengguna panel ini sudah menjawab verifikasi yang berjalan. */
    get sudahMenjawabVerifikasi() {
        if (!this.verifikasi) {
            return false;
        }

        return this.verifikasi.jawaban.some((j) => j.judge_user_id === this.cfg.userId);
    },

    /**
     * Verifikasi yang sudah punya hasil tapi belum diterapkan.
     *
     * Panel wasit memakai ini untuk menampilkan akibatnya SEBELUM tombol
     * "Terapkan" ditekan -- yang menekan harus tahu apa yang akan terjadi,
     * bukan membacanya di riwayat setelahnya.
     */
    get verifikasiMenungguPenerapan() {
        return this.verifikasi?.berjalan === true && this.verifikasi?.hasil !== null;
    },

    mintaVerifikasi(jenis, tingkat = null, kejadian = {}) {
        return this.kirim(this.cfg.verifikasiMinta, {
            babak: this.match.current_round,
            jenis,
            tingkat_pelanggaran: tingkat,
            score_event_id: kejadian.score_event_id ?? null,
            penalty_id: kejadian.penalty_id ?? null,
        });
    },

    jawabVerifikasi(jawaban) {
        if (!this.verifikasi) {
            return;
        }

        return this.kirim(this._alamatVerifikasi(this.cfg.verifikasiJawab), { jawaban });
    },

    terapkanVerifikasi() {
        if (!this.verifikasi) {
            return;
        }

        return this.kirim(this._alamatVerifikasi(this.cfg.verifikasiTerapkan));
    },

    batalkanVerifikasi(alasan = null) {
        if (!this.verifikasi) {
            return;
        }

        return this.kirim(this._alamatVerifikasi(this.cfg.verifikasiBatalkan), { alasan });
    },

    _alamatVerifikasi(pola) {
        return pola.replace('__ID__', this.verifikasi.id);
    },

    /**
     * Juri mengirim satu nilai. Babak selalu diambil dari state yang
     * sedang berjalan di server (bukan dari tebakan lokal) -- kalau babak
     * yang sedang aktif berbeda dari yang dikira juri, server yang
     * memutuskan dan menolaknya dengan alasan jelas, bukan JS diam-diam
     * mengirim ke babak yang salah.
     */
    kirimNilai(corner, jenis) {
        return this.kirim(this.cfg.nilai, { babak: this.match.current_round, corner, jenis });
    },

    kirimHukuman(corner, tingkat, catatan) {
        return this.kirim(this.cfg.hukuman, { babak: this.match.current_round, corner, tingkat, catatan: catatan || null });
    },

    kirimHitungan(corner, hitungan) {
        return this.kirim(this.cfg.hitungan, { babak: this.match.current_round, corner, hitungan });
    },

    /**
     * Wasit menerbitkan nilai mutlak jatuhan.
     *
     * Tanpa dialog konfirmasi: jatuhan diputuskan sementara pertandingan
     * berjalan, dan satu dialog di antara keputusan dan angkanya membuat papan
     * skor tertinggal dari apa yang sudah dilihat penonton. Salah tekan
     * diperbaiki lewat pembatalan nilai oleh Dewan Wasit Juri.
     *
     * Kalau wasit sempat ragu dan bertanya ke juri, id verifikasinya ikut
     * dikirim supaya berita acara bisa menunjukkan bahwa nilai ini diputuskan
     * setelah menimbang jawaban itu.
     */
    terbitkanJatuhan(corner) {
        return this.kirim(this.cfg.jatuhan, {
            babak: this.match.current_round,
            corner,
            verifikasi_id: this.saranJatuhan?.id ?? null,
        });
    },

    /**
     * Jawaban juri atas keraguan wasit soal jatuhan, selama belum dipakai.
     *
     * Hilang begitu nilainya terbit: saran yang menggantung setelah jatuhannya
     * dicatat mengundang penekanan kedua untuk jatuhan yang sama.
     */
    get saranJatuhan() {
        const v = this.verifikasi;

        return v?.jenis === 'jatuhan' && v?.hasil && !v?.score_event_id ? v : null;
    },

    batalkanNilai(id, alasan) {
        return this.kirim(this.cfg.nilaiBatal.replace('__ID__', id), { alasan });
    },

    batalkanHukuman(id, alasan) {
        return this.kirim(this.cfg.hukumanBatal.replace('__ID__', id), { alasan });
    },

    /** Pelatih mengangkat kartu meminta tinjauan video -- Pasal 15.2.a. */
    ajukanVar(corner, kejadian, scoreEventId = null, penaltyId = null) {
        return this.kirim(this.cfg.varAjukan, {
            babak: this.match.current_round, corner, kejadian,
            score_event_id: scoreEventId, penalty_id: penaltyId,
        });
    },

    /** Wasit Komisi Protes menetapkan Sah atau Tidak Sah dalam tenggat 5 menit. */
    putuskanVar(id, keputusan, catatan) {
        return this.kirim(this.cfg.varPutuskan.replace('__ID__', id), { keputusan, catatan: catatan || null });
    },

    ajukanProtesManajer(catatan) {
        return this.kirim(this.cfg.protesManajerAjukan, { catatan: catatan || null });
    },

    bandingProtesManajer(id, catatan) {
        return this.kirim(this.cfg.protesManajerBanding.replace('__ID__', id), { catatan: catatan || null });
    },

    putuskanProtesManajer(id, keputusan, catatan) {
        return this.kirim(this.cfg.protesManajerPutuskan.replace('__ID__', id), { keputusan, catatan: catatan || null });
    },

    _terapkan(data) {
        this.match = data.match;
        this.rounds = data.rounds;
        this.skorTotal = data.skor_total;
        this.hukuman = data.hukuman;
        // Namanya dibedakan dari penghitung 1-10 di panel wasit: keduanya
        // bernama "hitungan", dan x-data anak yang menaunginya akan menutupi
        // state ini kalau namanya sama.
        this.hitunganTeknik = data.hitungan ?? this.hitunganTeknik;
        this.tawaranWmp = data.tawaran_wmp;
        this.peraturan = data.peraturan;
        this.officials = data.officials;
        this.riwayat = data.riwayat;
        this.keberatan = data.keberatan;
        this.verifikasi = data.verifikasi;

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
            .listen('.timer.berubah', (e) => this._padaTimer(e))
            .listen('.skor.terbit', (e) => {
                this._tandaiIndikatorSelesai(e.corner, e.point_type);

                /*
                 * Angka dipasang dari muatan siarannya sendiri, tidak menunggu
                 * tarikan state selesai. Siaran ini SUDAH membawa skor kedua
                 * sudut sesudah nilai itu terbit -- menunggu tarikan berarti
                 * papan diam sekitar dua ratus milidetik sesudah nilai
                 * terdengar diumumkan, dan lebih lama lagi saat tekanan
                 * beruntun. Tarikan tetap dijalankan sesudahnya untuk hal-hal
                 * yang tidak dibawa siaran (riwayat, tawaran WMP, verifikasi).
                 */
                if (typeof e.skor_merah === 'number' && typeof e.skor_biru === 'number') {
                    this.skorTotal = { merah: e.skor_merah, biru: e.skor_biru };
                }

                segarkan();
            })
            .listen('.hukuman.terbit', segarkan)
            .listen('.partai.berubah', segarkan)
            .listen('.juri.input', (e) => this._padaInputJuri(e))
            /*
             * Panel juri harus beralih SERENTAK. Juri yang panelnya terlambat
             * beralih akan menekan tombol nilai untuk kejadian yang sedang
             * dipertanyakan, dan nilai itu terbit atas kejadian yang belum
             * diputuskan siapa pemiliknya.
             *
             * Siaran ini tidak membawa isi jawaban siapa pun -- lihat
             * App\Events\Scoring\VerifikasiJuriBerubah. Yang dipakai di sini
             * cuma pemicunya; isinya diambil dari state, yang memang disaring
             * menurut peran yang memintanya.
             */
            .listen('.verifikasi.berubah', segarkan);
    },

    /**
     * Timer berubah: jamnya dipasang dari muatan siaran, tanpa menarik state.
     *
     * Siaran ini membawa seluruh yang dibutuhkan jam -- babak, statusnya, dan
     * sisa waktu yang dihitung SERVER. Tidak ada yang lain di layar yang
     * berubah karena timer, jadi tarikan state penuh di sini cuma menunda
     * hitung mundur mulai bergerak, dan menambah satu permintaan ke server
     * yang sedang melayani tekanan tombol juri.
     *
     * Babak yang belum dikenal panel (mis. babak baru dimulai) tetap ditarik:
     * di situ barisnya memang belum ada untuk diperbarui.
     */
    _padaTimer(e) {
        if (e.match_id !== this.match.id) {
            return;
        }

        const babak = this.rounds.find((r) => r.round === e.round);

        if (!babak) {
            this.muatUlang();

            return;
        }

        Object.assign(babak, {
            status: e.status,
            duration_ms: e.duration_ms,
            sisa_ms: e.sisa_ms,
        });

        this._segarkanTimer();
    },

    /** Titik indikator "juri menekan" -- murni tampilan sementara, dibersihkan sendiri setelah window konsensus lewat atau nilainya terbit. */
    _padaInputJuri(e) {
        if (e.match_id !== this.match.id || e.ditolak) {
            return;
        }

        /*
         * Nomor juri datang dari siaran, tidak lagi dipetakan sendiri dari id
         * pengguna: siarannya juga didengar overlay siaran, yang tidak pernah
         * boleh menerima identitas juri sama sekali. Yang dikirim server kini
         * hanya nomor tugasnya.
         */
        const nomor = e.judge_number;

        if (!nomor) {
            return;
        }

        const sisi = e.corner === 'red' ? 'red' : 'blue';
        const teknik = e.point_type;

        if (!teknik || !this.indikatorTeknik[sisi][teknik] || this.indikatorTeknik[sisi][teknik].includes(nomor)) {
            return;
        }

        this.indikatorTeknik[sisi] = {
            ...this.indikatorTeknik[sisi],
            [teknik]: [...this.indikatorTeknik[sisi][teknik], nomor],
        };

        /*
         * Satu penghitung waktu per SUDUT DAN TEKNIK, bukan satu per sudut.
         *
         * Jendela konsensus berjalan sendiri-sendiri untuk tiap teknik:
         * Juri 1 menekan pukulan lalu tendangan, dan keduanya punya tenggat
         * sendiri. Dengan satu penghitung per sudut, tekanan tendangan
         * mengulang tenggat pukulan, lalu satu penghitung yang jatuh tempo
         * memadamkan KEDUANYA -- termasuk yang jendelanya masih terbuka.
         */
        this._batalkanPadam(sisi, teknik);
        this._waktuIndikator[`${sisi}-${teknik}`] = setTimeout(
            () => this._padamkanIndikator(sisi, teknik),
            this.peraturan.window_konsensus_ms + 500,
        );
    },

    /**
     * Nilai terbit: indikator TEKNIK ITU saja yang padam.
     *
     * Sebelumnya seluruh sudut dibersihkan sekaligus. Akibatnya, saat Juri 2
     * menekan pukulan dan nilai pukulan terbit, tekanan tendangan Juri 1 yang
     * masih menunggu juri kedua ikut lenyap dari layar -- operator melihat
     * papan bersih dan mengira tidak ada yang sedang ditunggu, padahal
     * jendelanya masih terbuka.
     */
    _tandaiIndikatorSelesai(corner, teknik) {
        const sisi = corner === 'red' ? 'red' : 'blue';

        if (teknik) {
            this._padamkanIndikator(sisi, teknik);

            return;
        }

        // Tanpa teknik yang disebut (mis. nilai mutlak dari wasit), tidak ada
        // yang bisa dipastikan masih ditunggu -- seluruh sudut dipadamkan.
        Object.keys(this.indikatorTeknik[sisi]).forEach((t) => this._padamkanIndikator(sisi, t));
    },

    _padamkanIndikator(sisi, teknik) {
        this._batalkanPadam(sisi, teknik);
        this.indikatorTeknik[sisi] = { ...this.indikatorTeknik[sisi], [teknik]: [] };
    },

    _batalkanPadam(sisi, teknik) {
        clearTimeout(this._waktuIndikator[`${sisi}-${teknik}`]);
    },
}));

/**
 * Panel Ketua Pertandingan -- Pasal 13.4.
 *
 * Berdiri sendiri, tidak memakai partaiPanel: tidak ada satu partai yang jadi
 * pusatnya, dan tidak ada timer yang dikendalikannya sendiri.
 *
 * Disegarkan lewat polling berkala, bukan Reverb. Panel ini memantau banyak
 * gelanggang sekaligus; berlangganan semua presence channel gelanggang akan
 * membuat Ketua Pertandingan terhitung sebagai "petugas tersambung" di setiap
 * gelanggang yang tidak ditungguinya, dan panel lain memakai hitungan itu.
 */
Alpine.data('panelKetua', (cfg) => ({
    cfg,
    memuat: true,
    gelanggang: [],
    antrean: [],
    pesan: null,
    galat: null,
    _timer: null,

    async init() {
        await this.muatUlang();

        /*
         * Enam detik. Cukup jarang supaya tidak membebani basis data yang
         * sedang melayani panel gelanggang sungguhan, dan cukup sering supaya
         * perkara yang tenggatnya lewat tidak luput lebih dari satu tarikan
         * napas. Ketua Pertandingan tidak menekan tombol per detik seperti
         * juri -- yang dibacanya adalah keadaan, bukan kejadian.
         */
        this._timer = setInterval(() => this.muatUlang(), 6000);
    },

    destroy() {
        clearInterval(this._timer);
    },

    async muatUlang() {
        try {
            const res = await fetch(this.cfg.state, { headers: { Accept: 'application/json' } });

            if (!res.ok) {
                this.galat = 'Gagal memuat keadaan gelanggang.';
                return;
            }

            const data = await res.json();
            this.gelanggang = data.gelanggang;
            this.antrean = data.antrean;
            this.galat = null;
            this.memuat = false;
        } catch (e) {
            this.galat = 'Tidak bisa menghubungi server.';
        }
    },

    /** Alamat aksi partai dibuat di sini: partai yang berjalan berganti tanpa halaman dimuat ulang. */
    alamat(pola, matchId) {
        return pola.replace('__MATCH__', matchId);
    },

    waktu(sisaMs) {
        if (sisaMs === null || sisaMs === undefined) {
            return '--:--';
        }

        const total = Math.max(0, Math.ceil(sisaMs / 1000));

        return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
    },

    /**
     * Sisa tenggat sebagai kalimat, bukan jam mundur.
     *
     * "96 menit lagi" terbaca sekali lihat; "01:36:12" menuntut pembacanya
     * menghitung sendiri. Yang sudah lewat dinyatakan lewat, bukan sebagai
     * angka negatif.
     */
    sisaTenggat(iso) {
        if (!iso) {
            return null;
        }

        const selisihMenit = Math.round((new Date(iso) - Date.now()) / 60000);

        if (selisihMenit < 0) {
            return `${Math.abs(selisihMenit)} mnt lewat`;
        }

        return `${selisihMenit} mnt lagi`;
    },

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

    mintaVerifikasi(papan) {
        return this.kirim(this.alamat(this.cfg.verifikasiMinta, papan.tanding.id), {
            babak: papan.tanding.babak,
            jenis: 'jatuhan',
        });
    },

    hentikan(papan) {
        return this.kirim(this.alamat(this.cfg.timerJeda, papan.tanding.id));
    },
}));

/**
 * Panel Jurus: operator/pengawas dan juri berbagi factory ini, sama seperti
 * `partaiPanel` dipakai bersama operator/wasit/dewan juri Tanding.
 *
 * Jauh lebih sederhana dari partaiPanel -- satu penampilan berjalan sekali
 * dari awal sampai selesai tanpa babak maupun jeda, jadi ini stopwatch
 * (hitung naik dari `started_at`), bukan hitung mundur server-authoritative
 * seperti timer partai.
 */
Alpine.data('jurusPanel', (cfg) => ({
    cfg,
    memuat: true,
    performance: { id: cfg.performanceId, status: 'terjadwal', started_at: null, duration_ms: null, didiskualifikasi: false, ratified: false },
    peserta: { nama: '', kontingen: '' },
    skor: { median: 0, total_pengurangan: 0, akhir: 0 },
    nilaiJuri: [],
    pengurangan: [],
    berjalanMs: 0,
    pesan: null,
    galat: null,
    _rafId: null,

    // Khusus panel juri (silat.jurus-juri) -- kosong dan tidak dipakai di
    // panel operator, tapi hidup di sini (bukan disebar dari luar) supaya
    // getter reaktifnya (nilaiSaya) tidak ikut bergantung pada pola object
    // spread yang membekukan getter (lihat commit fa068e0).
    nilaiInput: '',

    async init() {
        await this.muatUlang();
    },

    destroy() {
        this._hentikanStopwatch();
    },

    /** Nilai yang sudah dikirim juri yang sedang login, atau null bila belum. */
    get nilaiSaya() {
        return this.nilaiJuri.find((n) => n.judge_user_id === this.cfg.judgeUserId)?.value ?? null;
    },

    get tampilWaktu() {
        const totalDetik = Math.floor(this.berjalanMs / 1000);
        const menit = Math.floor(totalDetik / 60);
        const detik = totalDetik % 60;

        return `${String(menit).padStart(2, '0')}:${String(detik).padStart(2, '0')}`;
    },

    async muatUlang() {
        const res = await fetch(this.cfg.state, { headers: { Accept: 'application/json' } });

        if (!res.ok) {
            this.galat = 'Gagal memuat state penampilan.';

            return;
        }

        const data = await res.json();
        this.performance = data.performance;
        this.peserta = data.peserta;
        this.skor = data.skor;
        this.nilaiJuri = data.nilai_juri;
        this.pengurangan = data.pengurangan;
        this.memuat = false;

        if (this.nilaiSaya !== null) {
            this.nilaiInput = this.nilaiSaya.toFixed(2);
        }

        if (this.performance.status === 'berlangsung' && this.performance.started_at) {
            this._mulaiStopwatch(new Date(this.performance.started_at).getTime());
        } else {
            this._hentikanStopwatch();
            this.berjalanMs = this.performance.duration_ms ?? 0;
        }
    },

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

    mulai() {
        return this.kirim(this.cfg.mulai);
    },

    berhenti() {
        return this.kirim(this.cfg.berhenti);
    },

    kirimNilai(value) {
        return this.kirim(this.cfg.nilai, { value });
    },

    kirimNilaiInput() {
        return this.kirimNilai(parseFloat(this.nilaiInput));
    },

    penguranganJuri(alasan) {
        return this.kirim(this.cfg.penguranganJuri, { alasan });
    },

    penguranganPengawas(alasan) {
        return this.kirim(this.cfg.penguranganPengawas, { alasan });
    },

    batalkanPengurangan(id, alasan) {
        return this.kirim(this.cfg.penguranganBatal.replace('__ID__', id), { alasan });
    },

    diskualifikasi() {
        return this.kirim(this.cfg.diskualifikasi);
    },

    sahkan() {
        return this.kirim(this.cfg.sahkan);
    },

    _mulaiStopwatch(mulaiEpochMs) {
        this._hentikanStopwatch();

        const langkah = () => {
            this.berjalanMs = Date.now() - mulaiEpochMs;
            this._rafId = requestAnimationFrame(langkah);
        };

        this._rafId = requestAnimationFrame(langkah);
    },

    _hentikanStopwatch() {
        if (this._rafId !== null) {
            cancelAnimationFrame(this._rafId);
            this._rafId = null;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
pantauKoneksi();
