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
    jumlahBabak: null,
    red: null,
    blue: null,
    skorTotal: { merah: 0, biru: 0 },
    hukuman: {
        merah: { pembinaan: 0, teguran: 0, peringatan: 0 },
        biru: { pembinaan: 0, teguran: 0, peringatan: 0 },
    },
    // Berapa kali tiap teknik terbit -- dipakai rincian papan hasil.
    teknik: {
        merah: { pukulan: 0, tendangan: 0, jatuhan: 0 },
        biru: { pukulan: 0, tendangan: 0, jatuhan: 0 },
    },
    /*
     * Juri mana yang sedang menekan teknik apa, per sudut.
     *
     * Inilah satu-satunya bagian siaran yang menjelaskan MENGAPA sebuah nilai
     * terbit atau tidak terbit: penonton yang melihat serangan bersih tapi
     * papan diam berhak tahu bahwa yang sepakat memang belum cukup. Isinya
     * nomor tugas juri (1..n) yang dikirim server, bukan identitas siapa pun.
     *
     * Murni tampilan sementara: dibersihkan sendiri begitu jendela konsensus
     * lewat, atau begitu nilainya benar-benar terbit.
     */
    indikatorTeknik: {
        merah: { pukulan: [], tendangan: [], jatuhan: [] },
        biru: { pukulan: [], tendangan: [], jatuhan: [] },
    },
    peraturan: { jumlah_juri: 3, window_konsensus_ms: 2000 },
    sisaMsTampil: 0,
    kilat: null,
    _tickAnchorMs: 0,
    _tickAt: 0,
    _rafId: null,
    _kilatTimeout: null,
    // Berkunci `${sisi}-${teknik}-${nomor}` -- lihat _padaInputJuri.
    _waktuIndikator: {},
    // Berkunci `${sisi}-${teknik}`, naik tiap kali tekniknya dipadamkan --
    // membatalkan nyala susulan yang sudah tidak berlaku. Lihat _padaInputJuri.
    _generasiIndikator: {},
    // Partai + babak yang sedang diwakili isi indikatorTeknik.
    _indikatorUntuk: null,
    _sedangMenarik: false,
    _perluTarikLagi: false,
    _tenggatSegarkan: null,
    _denyutId: null,

    async init() {
        await this.muatUlang();
        this._pasangEcho();

        /*
         * Denyut sinkron 20 detik.
         *
         * Jam overlay diinterpolasi lokal dari acuan siaran terakhir, dan
         * grafis vMix menyala berjam-jam tanpa pernah dimuat ulang. Satu
         * siaran yang hilang di jaringan gelanggang cukup untuk membuat
         * acuannya basi, dan sejak itu jam di layar penonton terus berjalan
         * dari angka yang keliru tanpa pernah mengoreksi diri. Tarikan
         * berkala ini yang mengembalikannya -- beban satu permintaan per 20
         * detik per grafis, dan endpoint state-nya sendiri sudah di-cache
         * satu detik di server.
         */
        this._denyutId = setInterval(() => this.muatUlang(), 20000);
    },

    destroy() {
        this._hentikanInterpolasi();
        clearInterval(this._denyutId);
        clearTimeout(this._tenggatSegarkan);
        this._bersihkanIndikator();
    },

    get tampilWaktu() {
        const totalDetik = Math.ceil(this.sisaMsTampil / 1000);
        const menit = Math.floor(totalDetik / 60);
        const detik = totalDetik % 60;

        return `${String(menit).padStart(2, '0')}:${String(detik).padStart(2, '0')}`;
    },

    /**
     * Tarik keadaan terbaru, satu tarikan pada satu waktu.
     *
     * Letupan siaran digabung: selama satu tarikan berjalan, siaran berikutnya
     * tidak menambah antrean, hanya menandai bahwa masih ada yang perlu
     * ditarik. Tanpa ini, tekanan tombol juri yang beruntun melahirkan
     * antrean tarikan yang tumbuh lebih cepat daripada terurai, dan overlay
     * menayangkan keadaan yang makin tertinggal dari gelanggang.
     */
    async muatUlang() {
        if (this._sedangMenarik) {
            this._perluTarikLagi = true;

            return;
        }

        this._sedangMenarik = true;

        try {
            const res = await fetch(this.cfg.state, { headers: { Accept: 'application/json' } });
            const data = await res.json();

            this._terapkan(data);
        } catch (e) {
            // Overlay tidak punya siapa pun untuk melapor -- dibiarkan, akan
            // dicoba lagi begitu event berikutnya tiba atau koneksi pulih.
        } finally {
            this._sedangMenarik = false;
            this.memuat = false;

            if (this._perluTarikLagi) {
                this._perluTarikLagi = false;
                this.muatUlang();
            }
        }
    },

    /**
     * Tarikan yang dipicu SIARAN, ditunda sesaat supaya letupan jadi satu.
     *
     * Penggabungan di muatUlang() hanya menangkap siaran yang datang selagi
     * tarikan berjalan; yang datang di sela-selanya masih melahirkan tarikan
     * sendiri-sendiri. Tenggang pendek ini menyatukan mereka tanpa terlihat di
     * siaran: skor dan indikator sudah dipasang dari muatan siarannya sendiri.
     */
    _jadwalkanMuatUlang() {
        clearTimeout(this._tenggatSegarkan);
        this._tenggatSegarkan = setTimeout(() => this.muatUlang(), 150);
    },

    _terapkan(data) {
        const penanda = `${data.match?.id}-${data.babak_label}`;

        if (this._indikatorUntuk !== null && this._indikatorUntuk !== penanda) {
            this._bersihkanIndikator();
        }

        this._indikatorUntuk = penanda;

        this.adaPartai = data.ada_partai;

        if (!data.ada_partai) {
            this._hentikanInterpolasi();
            this.sisaMsTampil = 0;

            return;
        }

        this.match = data.match;
        this.kelas = data.kelas;
        this.babakLabel = data.babak_label;
        this.jumlahBabak = data.jumlah_babak ?? null;
        this.red = data.red;
        this.blue = data.blue;
        this.skorTotal = data.skor_total;
        this.hukuman = data.hukuman;
        this.teknik = data.teknik ?? this.teknik;
        this.peraturan = data.peraturan ?? this.peraturan;

        this._segarkanTimer(data.timer);
    },

    _segarkanTimer(timer) {
        if (!timer) {
            this._hentikanInterpolasi();
            this.sisaMsTampil = 0;

            return;
        }

        /*
         * Sisa waktu dipakai apa adanya dari server. Hitungan lokal di
         * bawahnya cuma jaring pengaman untuk muatan lama yang belum
         * membawanya -- ia mengurangi `started_at` dari jam MESIN INI, dan
         * mesin vMix yang jamnya meleset lima detik menayangkan hitung mundur
         * yang meleset lima detik ke seluruh penonton.
         */
        let sisaMs = timer.sisa_ms;

        if (typeof sisaMs !== 'number') {
            sisaMs = timer.duration_ms - timer.accumulated_ms;

            if (timer.status === 'berjalan' && timer.started_at) {
                const lewatMs = Date.now() - new Date(timer.started_at).getTime();
                sisaMs = Math.max(0, sisaMs - lewatMs);
            }
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

    /**
     * Satu juri menekan satu teknik.
     *
     * Yang tiba dari siaran hanya nomor tugas juri, sudut, dan tekniknya --
     * lihat App\Events\Scoring\JudgeInputReceived. Tekanan yang DITOLAK server
     * (babak belum berjalan, misalnya) tidak pernah menyalakan indikator:
     * kalau ia menyala, penonton mengira nilainya sedang dihitung padahal
     * tekanan itu tidak pernah ikut dihitung sama sekali.
     */
    _padaInputJuri(e) {
        if (e.ditolak || !e.judge_number) {
            return;
        }

        const sisi = e.corner === 'red' ? 'merah' : 'biru';
        const teknik = e.point_type;

        if (!this.indikatorTeknik[sisi]?.[teknik]) {
            return;
        }

        /*
         * Tekanan ulang oleh juri yang SAMA tidak diabaikan: titiknya padam
         * sekejap lalu menyala lagi. Penonton yang melihat serangan kedua
         * mendarat berhak melihat bahwa juri itu menekan lagi, bukan layar
         * yang diam karena nomornya sudah tercatat.
         */
        const sudahAda = this.indikatorTeknik[sisi][teknik].includes(e.judge_number);

        if (sudahAda) {
            this._setIndikator(sisi, teknik, this.indikatorTeknik[sisi][teknik].filter((n) => n !== e.judge_number));
        }

        /*
         * Nyala susulan dibatalkan kalau tekniknya keburu dipadamkan: selama
         * jeda 80 milidetik itu nilainya bisa terbit, dan titik yang sudah
         * dinyatakan selesai tidak boleh menyala lagi di siaran.
         */
        const kunciPadam = `${sisi}-${teknik}`;
        const generasi = this._generasiIndikator[kunciPadam] ?? 0;

        const nyalakan = () => {
            if ((this._generasiIndikator[kunciPadam] ?? 0) !== generasi) {
                return;
            }

            this._setIndikator(sisi, teknik, [...this.indikatorTeknik[sisi][teknik], e.judge_number]);
        };

        if (sudahAda) {
            setTimeout(nyalakan, 80);
        } else {
            nyalakan();
        }

        /*
         * Dibersihkan sendiri sesudah jendela konsensus lewat. Tanpa ini,
         * indikator yang tidak pernah mencapai ambang akan menetap di siaran
         * sepanjang partai dan terbaca sebagai nilai yang tertunda.
         *
         * Tenggatnya per JURI, bukan per sudut+teknik. Jendela konsensus
         * berjalan sejak tekanan masing-masing juri: dengan satu tenggat per
         * teknik, tekanan juri berikutnya memperpanjang titik juri sebelumnya
         * melewati jendelanya sendiri, dan siaran menayangkan tekanan yang
         * sudah dibuang server sebagai tekanan yang masih ditunggu.
         *
         * Panjangnya dibawa siaran (`kedaluwarsa_ms`) karena jendela konsensus
         * bisa ditimpa per turnamen.
         */
        const tenggat = (e.kedaluwarsa_ms ?? this.peraturan.window_konsensus_ms ?? 2000) + 250;

        this._batalkanPadam(sisi, teknik, e.judge_number);
        this._waktuIndikator[`${sisi}-${teknik}-${e.judge_number}`] = setTimeout(
            () => this._padamkanSatu(sisi, teknik, e.judge_number),
            tenggat,
        );
    },

    /**
     * Nilai terbit: indikator TEKNIK ITU saja yang padam.
     *
     * Memadamkan seluruh sudut akan menghapus tekanan teknik lain yang masih
     * menunggu juri kedua -- penonton melihat papan bersih dan mengira tidak
     * ada yang sedang ditunggu, padahal jendelanya masih terbuka.
     */
    _tandaiIndikatorSelesai(corner, teknik) {
        const sisi = corner === 'red' ? 'merah' : 'biru';

        if (teknik) {
            this._padamkanIndikator(sisi, teknik);

            return;
        }

        Object.keys(this.indikatorTeknik[sisi]).forEach((t) => this._padamkanIndikator(sisi, t));
    },

    /** Seluruh juri untuk satu teknik padam sekaligus -- dipakai saat nilainya terbit. */
    _padamkanIndikator(sisi, teknik) {
        (this.indikatorTeknik[sisi][teknik] ?? []).forEach((n) => this._batalkanPadam(sisi, teknik, n));

        const kunci = `${sisi}-${teknik}`;
        this._generasiIndikator[kunci] = (this._generasiIndikator[kunci] ?? 0) + 1;

        this._setIndikator(sisi, teknik, []);
    },

    /** Satu juri saja yang padam -- jendela konsensus JURI ITU yang tutup. */
    _padamkanSatu(sisi, teknik, nomor) {
        this._batalkanPadam(sisi, teknik, nomor);
        this._setIndikator(sisi, teknik, (this.indikatorTeknik[sisi][teknik] ?? []).filter((n) => n !== nomor));
    },

    // Objek sudutnya diganti utuh, bukan disunting di tempat: Alpine hanya
    // melacak perubahan yang lewat penetapan properti.
    _setIndikator(sisi, teknik, daftar) {
        this.indikatorTeknik[sisi] = { ...this.indikatorTeknik[sisi], [teknik]: daftar };
    },

    _batalkanPadam(sisi, teknik, nomor) {
        clearTimeout(this._waktuIndikator[`${sisi}-${teknik}-${nomor}`]);
        delete this._waktuIndikator[`${sisi}-${teknik}-${nomor}`];
    },

    /**
     * Seluruh titik dikosongkan saat partai atau babaknya berganti -- tekanan
     * dari babak sebelumnya tidak menunggu apa pun lagi, dan tidak ada siaran
     * yang akan datang untuk memadamkannya.
     */
    _bersihkanIndikator() {
        Object.keys(this._waktuIndikator).forEach((k) => clearTimeout(this._waktuIndikator[k]));
        this._waktuIndikator = {};

        Object.keys(this.indikatorTeknik).forEach((sisi) => {
            Object.keys(this.indikatorTeknik[sisi]).forEach((t) => this._padamkanIndikator(sisi, t));
        });
    },

    _pasangEcho() {
        if (!this.cfg.arenaId) {
            return;
        }

        const segarkan = () => this._jadwalkanMuatUlang();
        const channel = window.Echo.channel(`public-live.${this.cfg.arenaId}`);

        channel
            /*
             * Jam dipasang dari muatan siarannya sendiri -- ia sudah membawa
             * babak, statusnya, dan sisa waktu versi server. Menariknya lewat
             * state hanya menunda hitung mundur mulai bergerak, dan menambah
             * permintaan ke server yang sedang melayani gelanggang.
             */
            .listen('.timer.berubah', (e) => this._segarkanTimer(e))
            .listen('.hukuman.terbit', segarkan)
            .listen('.partai.berubah', segarkan)
            .listen('.skor.terbit', (e) => {
                this._kilatkan(e.corner);
                this._tandaiIndikatorSelesai(e.corner, e.point_type);

                // Angka dari muatan siarannya sendiri: di siaran, papan yang
                // diam dua ratus milidetik sesudah nilai terbit terlihat
                // sebagai papan yang salah, bukan papan yang sedang menunggu.
                if (typeof e.skor_merah === 'number' && typeof e.skor_biru === 'number') {
                    this.skorTotal = { merah: e.skor_merah, biru: e.skor_biru };
                }

                segarkan();
            })
            .listen('.juri.input', (e) => this._padaInputJuri(e));

        // Resync penuh tiap kali koneksi WebSocket kembali tersambung --
        // event yang terlewat selama putus tidak pernah terulang sendiri.
        const pusher = window.Echo.connector?.pusher;
        pusher?.connection?.bind('connected', segarkan);
    },
}));
