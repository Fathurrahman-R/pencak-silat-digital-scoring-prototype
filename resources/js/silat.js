import Alpine from 'alpinejs';

import { siapkanEcho } from './echo';
import { pantauLatensi } from './latensi';
import './overlay/connection';

/**
 * Koneksi Reverb dibuka SELALU, kecuali halamannya menyatakan tidak
 * membutuhkannya lewat <meta name="realtime" content="0">.
 *
 * Arah bawaannya sengaja begini, bukan sebaliknya. Halaman statis yang
 * terlewat menyatakan diri hanya menyisakan satu koneksi menganggur --
 * pemborosan kecil. Panel juri yang terlewat menyatakan diri kehilangan
 * seluruh realtime-nya: tombolnya tetap bisa ditekan, nilainya tidak sampai
 * ke mana pun, dan pertandingan berjalan terus dengan skor yang salah.
 * Antara dua arah kesalahan itu, hanya satu yang boleh terjadi diam-diam.
 */
if (document.querySelector('meta[name="realtime"][content="0"]') === null) {
    siapkanEcho();
}

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

    /*
     * Latensi pulang-pergi ke Reverb. `latensiMs` adalah sampel terakhir --
     * angka yang dibaca petugas. `latensiAcuanMs` adalah median beberapa
     * sampel terakhir, dan HANYA itu yang menentukan warna: satu lonjakan
     * tunggal tidak boleh membuat titiknya berkedip ganti warna.
     *
     * Keduanya null selama belum ada ukuran (halaman tanpa Echo, sambungan
     * belum jadi, atau tab yang baru kembali dari sembunyi).
     */
    latensiMs: null,
    latensiAcuanMs: null,

    get tersambung() {
        return this.status === 'tersambung';
    },

    /*
     * Ambangnya mengikuti config/pemantauan.php, tempat lencana kesehatan
     * gelanggang menaruh batas kuningnya di 500 ms. Di bawah 150 ms adalah
     * angka yang terukur sehat di LAN gelanggang.
     *
     * Mengembalikan null, bukan 'buruk', saat belum ada ukuran: belum tahu
     * bukan kabar buruk, dan menampilkannya sebagai kabar buruk akan
     * membuat setiap panel yang baru dibuka berkedip merah sesaat.
     */
    get mutu() {
        if (this.latensiAcuanMs === null) {
            return null;
        }

        if (this.latensiAcuanMs < 150) {
            return 'lancar';
        }

        return this.latensiAcuanMs <= 500 ? 'lambat' : 'buruk';
    },

    tandai(status) {
        this.status = status;
    },
});

/**
 * Menerjemahkan respons gagal jadi kalimat yang bisa ditindaklanjuti.
 *
 * Panel gelanggang dibuka pagi dan dibiarkan menyala sampai malam. Sesinya
 * bisa berakhir di tengah jalan -- dan sebelum ini yang muncul di layar juri
 * adalah "CSRF token mismatch." apa adanya: benar secara teknis, tapi tidak
 * memberi tahu siapa pun apa yang harus dilakukan. Terukur pada panel yang
 * sudah terbuka 9,5 jam.
 */
function pesanGagal(status, body) {
    if (status === 419) {
        return 'Sesi Anda sudah berakhir. Muat ulang halaman ini lalu masuk lagi sebelum melanjutkan.';
    }

    if (status === 403) {
        return body.message ?? 'Anda tidak berwenang melakukan aksi ini pada partai ini.';
    }

    if (status === 404) {
        return 'Partai ini sudah tidak ada. Muat ulang halaman dan buka partai dari menu Jadwal.';
    }

    if (status >= 500) {
        return 'Server gelanggang sedang bermasalah. Coba lagi; kalau berulang, panggil operator IT.';
    }

    if (body.errors) {
        // `dapat_dipaksa` adalah penanda untuk panel, bukan kalimat untuk
        // dibaca petugas -- menggabungkannya ke pesan hanya mengulang hal yang
        // sama dua kali di satu baris galat.
        const { dapat_dipaksa: _penanda, ...sisa } = body.errors;

        return Object.values(sisa).flat().join(' ');
    }

    return body.message ?? 'Gagal.';
}

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

    /*
     * Kegagalan menyambung MENYEBUT alamat yang dicobanya.
     *
     * Penanda di layar cuma bisa berbunyi "Terputus" -- itu memang yang perlu
     * diketahui juri, dan menambahkan alamat WebSocket di sana hanya membuat
     * layar gelanggang penuh oleh hal yang tidak bisa ditindaklanjuti siapa pun
     * di pinggir matras.
     *
     * Tapi yang MEMPERBAIKINYA butuh justru alamat itu, dan sebelum baris ini
     * ia tidak pernah tertulis di mana pun: sambungan yang diblokir peramban
     * (mis. `ws://` dari halaman `https://`) gagal tanpa satu pun pesan yang
     * terlihat, dan yang terbaca cuma "Reverb mati" -- padahal Reverb tidak
     * pernah dihubungi. Ditemukan begitu di Safari iOS.
     */
    const { options } = pusher.connection;
    const alamatWs = `${options.forceTLS ? 'wss' : 'ws'}://${options.wsHost}:${options.forceTLS ? options.wssPort : options.wsPort}/app/${options.key}`;

    pusher.connection.bind('error', (galat) => {
        console.error('[silat] WebSocket gagal:', alamatWs, galat);
    });

    pusher.connection.bind('state_change', ({ current }) => {
        if (current === 'unavailable' || current === 'failed') {
            console.error(
                `[silat] WebSocket ${current} — tidak berhasil menyambung ke ${alamatWs}. `
                + 'Periksa: Reverb berjalan, portnya terbuka dari perangkat ini, '
                + 'dan skema halaman (http/https) sama dengan skema WebSocket-nya.',
            );
        }
    });

    /*
     * WebSocket bukan pendeteksi putus yang cepat.
     *
     * Diukur di lapangan: WiFi HP juri dicabut, dan delapan detik kemudian
     * panel masih bertuliskan "tersambung" -- Pusher baru menyadarinya setelah
     * ping-nya sendiri kedaluwarsa. Selama jendela itu tombol nilai tetap
     * tampak hidup, ditekan, dan tekanannya hilang.
     *
     * Peramban sudah tahu jauh lebih cepat. Kejadian `offline` dipakai apa
     * adanya: tombol nilai mematikan dirinya sendiri begitu status jadi
     * "putus", jadi juri melihatnya sebelum ia menekan, bukan sesudah.
     */
    const kabar = () => {
        if (! navigator.onLine) {
            Alpine.store('koneksi').tandai('putus');

            return;
        }

        Alpine.store('koneksi').tandai(petakan[pusher.connection.state] ?? 'menyambung');
    };

    window.addEventListener('offline', kabar);
    window.addEventListener('online', kabar);
    kabar();
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

    /*
     * Tekanan juri yang belum sampai ke server karena jaringan perangkat ini
     * sedang putus. Lihat alirkanAntrean().
     */
    antrean: [],
    _mengalirkan: false,
    _denyutAntrean: null,
    _gagalJaringan: false,

    /*
     * Kunci galat validasi dari permintaan terakhir, dan sasaran perpindahan
     * yang menunggu ditegaskan ulang oleh pengendali. Lihat pilihPartai().
     */
    _galatKunci: [],
    _mendarat: false,
    paksaTertunda: null,

    match: { id: cfg.matchId, status: 'terjadwal', current_round: null, red: null, blue: null, winner_registration_id: null, win_reason: null, ratified: false },
    rounds: [],
    skorTotal: { merah: 0, biru: 0 },
    hukuman: {
        merah: { pembinaan: 0, teguran: 0, peringatan: 0, diskualifikasi: false },
        biru: { pembinaan: 0, teguran: 0, peringatan: 0, diskualifikasi: false },
    },

    /*
     * Berapa KALI tiap teknik terbit, per sudut -- bukan jumlah nilainya.
     * Dibaca papan hasil yang muncul sesudah partai selesai.
     */
    teknik: {
        merah: { pukulan: 0, tendangan: 0, jatuhan: 0 },
        biru: { pukulan: 0, tendangan: 0, jatuhan: 0 },
    },

    /*
     * Blok panel gelanggang: mode, gelanggang, antrean jadwal, dan alamat aksi
     * partai yang sedang tayang.
     *
     * Ada juga di panel per-partai, isinya cuma lebih sedikit -- dengan begitu
     * tidak ada satu pun tempat di view yang perlu bertanya "panel ini yang
     * mana" sebelum membaca sesuatu.
     */
    panel: cfg.mode ? { mode: cfg.mode, arena: null, antrean: [], aksi: {} } : null,

    /*
     * Babak lama yang sedang dibuka untuk pencatatan susulan, kalau ada.
     *
     * Selama ia terisi, SELURUH input panel ini masuk ke babak itu -- bukan ke
     * babak berjalan. Yang membaca nilainya `babakInput`.
     */
    susulan: null,

    /*
     * Identitas partai yang sedang ditayangkan -- nomor, kelas, tahap bagan.
     *
     * Dibaca kepala panel. Sebelum panel mengikuti gelanggang, Blade mencetak
     * ini langsung dari $match dan itu memang cukup; begitu partai bisa
     * berganti tanpa halaman dimuat ulang, yang dicetak Blade membeku di
     * partai yang sudah ditinggalkan.
     */
    identitas: { partai: cfg.matchId, gelanggang: null, kelas: null, golongan: null, jenis_kelamin: null, babak_bagan: null },

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

    /*
     * Penyelesaian yang ditawarkan saat KEDUA pesilat sama-sama tidak bangkit
     * -- Pasal 11.6.c huruf b dan c. Bentuknya { sebab, pemenang }: sistem
     * menghitung, aparat yang menekan.
     */
    tawaranSerentak: null,
    peraturan: { jumlah_juri: 3, ambang_sepakat: 2, window_konsensus_ms: 2000, jumlah_babak: 3, durasi_babak_ms: 0 },
    officials: [],
    riwayat: [],

    /*
     * Lajur tekanan juri mentah -- null berarti panel ini tidak berhak
     * melihatnya, bukan berarti tidak ada tekanan. Bedanya dipakai layar:
     * daftar kosong menyatakan "belum ada yang menekan", null tidak
     * menggambar bloknya sama sekali.
     */
    tekanan: null,
    riwayatDipangkasPada: null,

    keberatan: { kartu: { merah: 2, biru: 2 }, var_reviews: [], protes_manajer: [] },
    verifikasi: null,

    /*
     * Id verifikasi yang modal hasilnya sudah ditutup.
     *
     * Disimpan sebagai ID, bukan sebagai boolean: modal harus muncul SEKALI per
     * verifikasi, lalu tidak muncul lagi walaupun state terus ditarik tiap dua
     * detik sepanjang sisa partai -- dan harus muncul LAGI untuk verifikasi
     * berikutnya, yang di satu babak bisa terjadi beberapa kali.
     */
    _hasilVerifikasiDitutup: null,

    /*
     * Sudah pernah menyerap satu muatan state.
     *
     * Dipakai menyemai penanda di atas: verifikasi yang SUDAH diterapkan
     * sebelum panel ini dibuka tidak boleh memunculkan modalnya. Endpoint
     * state selalu mengirim verifikasi TERAKHIR partai ini, apa pun umurnya --
     * jadi tanpa semaian ini, operator yang membuka papan di tengah babak
     * ketiga disambut hasil verifikasi dari babak pertama, sebesar layar.
     */
    _hasilVerifikasiDisemai: false,

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
    /*
     * Berkunci `${sisi}-${teknik}-${nomor}`: satu penghitung padam untuk TIAP
     * JURI, bukan satu untuk tiap teknik.
     *
     * Jendela konsensus berjalan sejak tekanan MASING-MASING juri. Dengan satu
     * penghitung per teknik, tekanan juri kedua mengulang tenggat juri pertama:
     * Juri 1 menekan di detik 0, Juri 3 menekan di detik 1,8, dan titik Juri 1
     * baru padam di detik 4,3 -- padahal jendelanya sudah tutup di detik 2 dan
     * server sudah membuangnya. Operator membaca layar yang bilang masih ada
     * yang ditunggu, padahal tidak ada.
     */
    _waktuIndikator: {},
    // Berkunci `${sisi}-${teknik}`, naik tiap kali tekniknya dipadamkan --
    // membatalkan nyala susulan yang sudah tidak berlaku. Lihat _padaInputJuri.
    _generasiIndikator: {},
    // Partai + babak yang sedang diwakili isi indikatorTeknik. Begitu salah
    // satunya berganti, titik-titik lama tidak menyatakan apa pun lagi.
    _indikatorUntuk: null,
    _sedangMenarik: false,
    _perluTarikLagi: false,
    _tenggatSegarkan: null,
    _tenggatCadangan: null,
    _siaranTerakhirAt: 0,
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

                /*
                 * Jamnya ikut ditarik ulang, bukan cuma kunci layarnya.
                 *
                 * Hitung mundur di layar diinterpolasi lokal dari acuan
                 * terakhir. Selama tab tersembunyi, acuan itu jadi basi:
                 * begitu tab dibuka lagi, jam melanjutkan hitungan dari
                 * angka yang sudah salah dan tidak pernah mengoreksi diri
                 * sampai kebetulan ada siaran masuk. Operator yang kembali
                 * ke panel melihat sisa waktu yang meyakinkan tapi keliru --
                 * pada satu pengukuran, 53 detik lebih banyak daripada
                 * sisa yang sebenarnya.
                 */
                this.muatUlang();
            }
        });

        /*
         * Halaman yang dipulihkan dari bfcache (tombol Kembali) tidak
         * menjalankan init() lagi, jadi ia kembali dengan seluruh state
         * lamanya. `persisted` menandai kejadian itu.
         */
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) {
                this.muatUlang();
            }
        });

        this._muatAntrean();
        window.addEventListener('online', () => this.alirkanAntrean());
    },

    destroy() {
        this._hentikanInterpolasi();
        this._hentikanDenyutSinkron();
        clearTimeout(this._tenggatSegarkan);
        clearTimeout(this._tenggatCadangan);
        clearInterval(this._denyutAntrean);
        this._bersihkanIndikator();
    },

    /*
     * ANTREAN TEKANAN SAAT JARINGAN PUTUS.
     *
     * WiFi venue berkedip sepersekian detik justru saat serangan datang.
     * Sebelum ini tekanan yang jatuh di celah itu hilang: pesannya muncul,
     * lalu juri kembali menonton matras dan tidak pernah tahu nilainya tidak
     * pernah sampai.
     *
     * Yang diantre cuma tekanan juri. Aksi operator dan wasit sengaja TIDAK
     * ikut: mengulang "Selesaikan babak" atau "Akhiri partai" beberapa detik
     * kemudian, saat keadaan gelanggang sudah berubah, jauh lebih berbahaya
     * daripada kehilangannya.
     *
     * Antreannya bertahan di localStorage supaya tab yang dimuat ulang -- atau
     * HP yang layarnya mati lalu dinyalakan lagi -- tidak ikut membuangnya.
     */
    get antreanTertahan() {
        return this.antrean.length;
    },

    _kunciAntrean() {
        return 'silat-antrean-nilai-' + this.cfg.matchId;
    },

    _muatAntrean() {
        try {
            const isi = JSON.parse(localStorage.getItem(this._kunciAntrean()) ?? '[]');

            /*
             * Yang sudah terlalu tua dibuang, tidak dikirim diam-diam.
             *
             * Tekanan berumur belasan menit hampir pasti milik babak yang
             * sudah selesai. Server memang akan menolaknya dengan alasan yang
             * benar, tapi barisnya tetap masuk jejak audit sebagai tekanan
             * yang seolah baru terjadi -- dan yang membacanya nanti tidak
             * punya cara tahu itu gema dari tab kemarin.
             */
            const batas = Date.now() - 5 * 60 * 1000;
            const segar = isi.filter((t) => Date.parse(t.client_ts) >= batas);

            this.antrean = segar;
            this._simpanAntrean();

            if (isi.length > segar.length) {
                this.galat = (isi.length - segar.length) + ' tekanan yang tertahan terlalu lama dibuang — '
                    + 'catat lewat Dewan Wasit Juri kalau memang ada nilai yang terlewat.';
            }

            if (segar.length > 0) {
                /*
                 * Denyutnya dinyalakan LEBIH DULU, bukan disandarkan pada
                 * kejadian `online`. Panel yang dimuat ulang selagi jaringannya
                 * masih putus tidak akan pernah menerima kejadian itu -- ia
                 * memang tidak pernah "kembali" online dari sudut pandang tab
                 * baru ini -- dan antreannya akan menganggur selamanya.
                 */
                this._denyutkanAntrean();
                this.alirkanAntrean();
            }
        } catch (e) {
            this.antrean = [];
        }
    },

    _simpanAntrean() {
        try {
            localStorage.setItem(this._kunciAntrean(), JSON.stringify(this.antrean));
        } catch (e) {
            // Penyimpanan penuh atau ditolak. Antreannya tetap hidup di memori
            // selama tab ini terbuka; tidak ada alasan menggagalkan tekanan
            // yang sedang ditangani hanya karena cadangannya tidak bisa
            // ditulis.
        }
    },

    _antrekan(tekan) {
        this.antrean.push(tekan);
        this._simpanAntrean();
        this._denyutkanAntrean();
    },

    /*
     * Dicoba lagi tiap lima detik selama antreannya belum kosong. Kejadian
     * `online` saja tidak cukup: jaringan bisa kembali tanpa peramban
     * mengabarkannya (WiFi tetap tersambung, gerbangnya yang sempat hilang).
     */
    _denyutkanAntrean() {
        if (this._denyutAntrean) {
            return;
        }

        this._denyutAntrean = setInterval(() => {
            if (this.antrean.length === 0) {
                clearInterval(this._denyutAntrean);
                this._denyutAntrean = null;

                return;
            }

            this.alirkanAntrean();
        }, 5000);
    },

    /**
     * Mengalirkan antrean, satu per satu dan berurutan.
     *
     * Berurutan karena urutan tekanan adalah urutan kejadian di matras, dan
     * satu per satu karena tekanan berikutnya tidak berguna dikirim kalau
     * yang pertama saja belum sampai.
     *
     * Yang DITOLAK server ikut dibuang dari antrean, bukan dicoba lagi
     * selamanya: babak yang sudah lewat tidak akan pernah menerimanya, dan
     * penolakannya sendiri sudah tercatat di jejak audit lengkap dengan
     * waktu tekan aslinya.
     */
    async alirkanAntrean() {
        if (this._mengalirkan || this.antrean.length === 0) {
            return;
        }

        this._mengalirkan = true;

        try {
            while (this.antrean.length > 0) {
                const berhasil = await this.kirim(this.cfg.nilai, this.antrean[0], { segarkan: false });

                if (! berhasil && this._gagalJaringan) {
                    break;
                }

                this.antrean.shift();
                this._simpanAntrean();
            }
        } finally {
            this._mengalirkan = false;
        }

        if (this.antrean.length === 0) {
            await this.muatUlang();

            return;
        }

        // Masih ada sisa: pastikan denyut coba-lagi memang menyala, apa pun
        // jalan yang membawa kita ke sini.
        this._denyutkanAntrean();
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

    get susulanTerbuka() {
        return this.susulan !== null;
    },

    /**
     * Babak yang akan tercatat kalau tombol ditekan SEKARANG.
     *
     * Seluruh pengirim -- nilai juri, hukuman wasit, hitungan teknik --
     * membacanya dari sini, bukan dari `match.current_round`. Satu tempat,
     * supaya tidak ada pengirim yang tertinggal saat aturannya berubah.
     */
    get babakInput() {
        return this.susulan?.round ?? this.match.current_round;
    },

    /**
     * Apakah tekanan tombol nilai akan DITERIMA kalau dikirim sekarang.
     *
     * Cerminan CatatInputJuri::diterima() di sisi layar, dan hanya itu
     * gunanya: server tetap yang memutuskan. Tanpa ini tombol nilai tampak
     * hidup sepanjang babak yang sudah diselesaikan atau masih dijeda, dan
     * juri baru tahu tekanannya ditolak sesudah menekan -- di gelanggang yang
     * berisik, umpan balik yang datang terlambat itu terbaca sebagai jaringan
     * yang lambat, bukan sebagai tekanan yang memang tidak dihitung.
     *
     * Babak susulan sengaja melewati syarat timer, sama seperti di server:
     * timernya memang tidak pernah jalan.
     */
    get babakMenerimaNilai() {
        if (this.susulanTerbuka) {
            return true;
        }

        /*
         * Jam yang sudah menyentuh nol menutup babak, walau `status` masih
         * 'berjalan'.
         *
         * Kolom status baru berubah kalau pengendali menekan Jeda atau
         * Selesaikan babak, dan di antara 00:00 dan tekanan itu selalu ada
         * beberapa detik. Tanpa syarat ini tombol juri masih tembus di
         * detik-detik tersebut, dan nilainya tercatat seolah masih di dalam
         * babak. CatatInputJuri menolaknya di server dengan syarat yang sama;
         * yang di sini cuma supaya tombolnya tidak terlihat bisa ditekan.
         */
        return this.babakAktif?.status === 'berjalan' && this.sisaMsTampil > 0;
    },

    /**
     * Jam sudah menyentuh nol, tapi babaknya belum ditutup pengendali.
     *
     * Kolom `status` di server tetap 'berjalan' sampai ada yang menekan Jeda
     * atau Selesaikan babak, dan di antara 00:00 dan tekanan itu selalu ada
     * beberapa detik. Panel yang membaca kolom itu apa adanya menulis
     * "BERJALAN" di bawah jam yang menunjukkan 00:00 -- dua pernyataan yang
     * saling membantah, dan yang dipercaya operator adalah yang salah.
     *
     * Keadaannya nyata dan punya arti sendiri: pertandingan sudah habis
     * waktunya, tombol juri sudah tidak menerima apa pun (CatatInputJuri
     * menolaknya dengan syarat yang sama), tapi babaknya belum resmi ditutup.
     */
    get waktuHabis() {
        return this.babakAktif?.status === 'berjalan' && this.sisaMsTampil <= 0;
    },

    /** Dipakai tampilan jam -- MM:SS dari sisaMsTampil, yang diinterpolasi lokal antara dua siaran timer. */
    get tampilWaktu() {
        const totalDetik = Math.ceil(this.sisaMsTampil / 1000);
        const menit = Math.floor(totalDetik / 60);
        const detik = totalDetik % 60;

        return `${String(menit).padStart(2, '0')}:${String(detik).padStart(2, '0')}`;
    },

    get babakAktif() {
        /*
         * Bertahan terhadap keadaan tanpa partai.
         *
         * Panel kendali dirender juga di gelanggang yang belum dipilihkan
         * partai -- di situlah pengendali memilihnya. Muatan state untuk
         * keadaan itu tidak punya `rounds` maupun `match`, dan Alpine tetap
         * mengevaluasi setiap ekspresi di dalam `x-show` yang bernilai salah,
         * jadi getter ini tetap dipanggil. Tanpa penjagaan di sini, membuka
         * gelanggang kosong membanjiri konsol dengan TypeError.
         */
        return (this.rounds ?? []).find((r) => r.round === this.match?.current_round) ?? null;
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
        return this.match?.status === 'selesai';
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
            return this.match?.current_round ?? 1;
        }

        if (this.babakAktif.status === 'selesai') {
            const berikutnya = this.match.current_round + 1;

            /*
             * Babak terakhir sudah selesai -- tidak ada babak berikutnya
             * untuk ditawarkan. Sebelum ini tombolnya tetap digambar
             * ("Mulai babak 4" pada golongan berbabak tiga), dan operator
             * baru tahu setelah menekannya dan dijawab penolakan.
             */
            return berikutnya > this.peraturan.jumlah_babak ? null : berikutnya;
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
     * Tarikan yang dipicu SIARAN, ditunda sesaat supaya letupan jadi satu.
     *
     * Penggabungan di muatUlang() hanya menangkap siaran yang datang selagi
     * tarikan berjalan. Yang datang di sela-selanya -- dua nilai terbit dalam
     * satu pertukaran serangan, hukuman menyusul sepersekian detik kemudian --
     * masih melahirkan tarikan sendiri-sendiri. Tenggang pendek ini menyatukan
     * mereka tanpa terasa: angka skor sudah dipasang seketika dari muatan
     * siarannya sendiri, jadi yang tertunda cuma hal-hal yang tidak dibawa
     * siaran (riwayat, tawaran WMP, verifikasi).
     */
    _jadwalkanMuatUlang() {
        clearTimeout(this._tenggatSegarkan);
        this._tenggatSegarkan = setTimeout(() => this.muatUlang(), 150);
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
     *
     * `segarkan: false` dipakai tombol yang ditekan BERUNTUN -- lihat
     * kirimNilai(), yang memakai jaring pengaman lain untuk janji yang sama.
     */
    async kirim(url, data = {}, { segarkan = true, metode = 'POST' } = {}) {
        this.galat = null;
        this._gagalJaringan = false;

        /*
         * Tawaran paksa milik SATU penolakan, bukan milik panel.
         *
         * Dibersihkan di sini, di jalan yang dilalui semua aksi: tanpa itu
         * tombol paksa dari penolakan perpindahan tadi masih berdiri di
         * samping pesan galat aksi lain yang sama sekali tidak berhubungan --
         * dan menekannya memindahkan gelanggang yang tidak diminta siapa pun.
         */
        this.paksaTertunda = null;

        try {
            const res = await fetch(url, {
                method: metode,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(data),
            });

            const body = await res.json().catch(() => ({}));

            if (!res.ok) {
                this.galat = pesanGagal(res.status, body);

                /*
                 * Kunci galatnya ikut disimpan, bukan cuma kalimatnya:
                 * pemanggil yang perlu tahu apakah penolakan ini masih bisa
                 * ditembus paksa membaca penandanya, bukan mencocokkan teks.
                 */
                this._galatKunci = Object.keys(body.errors ?? {});

                return false;
            }

            this._galatKunci = [];
            this.pesan = body.pesan ?? null;

            if (segarkan) {
                await this.muatUlang();
            }

            return true;
        } catch (e) {
            // Permintaan yang tidak sampai adalah bukti paling awal bahwa
            // jaringan perangkat ini sedang mati -- lebih awal daripada
            // WebSocket menyadarinya. Statusnya ikut ditandai supaya tombol
            // nilai berhenti menerima tekanan yang tidak akan pernah sampai.
            Alpine.store('koneksi').tandai('putus');
            this._gagalJaringan = true;

            this.galat = 'Tidak bisa menghubungi server. Periksa jaringan perangkat ini.';

            return false;
        }
    },

    /**
     * Memindahkan partai yang ditayangkan gelanggang.
     *
     * `null` berarti mengosongkan gelanggang. Penolakan "partai masih
     * berjalan" datang dari server, bukan disimpulkan di sini -- klien tidak
     * pernah memutuskan sendiri apa yang boleh ditinggalkan.
     */
    async pilihPartai(matchId, paksa = false) {
        if (! this.cfg.pilihPartai) {
            return;
        }

        const berhasil = await this.kirim(this.cfg.pilihPartai, { match_id: matchId, paksa }, { segarkan: true });

        /*
         * Penolakan yang bisa ditembus paksa menawarkan jalannya di layar.
         *
         * Sebelum ini pesannya menyuruh pengendali "pindah paksa" sementara
         * tidak satu pun tombol di panel mengirimkannya -- jalan buntu, persis
         * di keadaan yang paling butuh jalan keluar: partai ditinggal berjalan
         * karena perangkat pengendalinya mati.
         *
         * Sasarannya disimpan, bukan dihitung ulang saat tombol ditekan:
         * `null` berarti mengosongkan gelanggang, dan itu sasaran yang sah.
         */
        if (! berhasil && (this._galatKunci ?? []).includes('dapat_dipaksa')) {
            this.paksaTertunda = { matchId };
        }

        return berhasil;
    },

    /** Mengulang aksi yang ditolak, kali ini dengan pernyataan paksa. */
    ulangiPaksa() {
        const tertunda = this.paksaTertunda;

        if (! tertunda) {
            return;
        }

        return 'penampilanId' in tertunda
            ? this.pilihPenampilan(tertunda.penampilanId, true)
            : this.pilihPartai(tertunda.matchId, true);
    },

    /**
     * Melepas satu partai atau penampilan ke gelanggang lain.
     *
     * Yang dikirim BARIS-nya, bukan pointer: pemindahan jadwal berbeda dari
     * pemindahan tayangan. Partai yang dilepas berhenti ditayangkan di sini
     * sebagai akibat, bukan sebagai perintah terpisah -- server yang
     * memutuskannya, supaya klien tidak pernah memegang setengah keadaan.
     */
    lepasKeGelanggang(barisId, keArenaId, jenis = 'tanding', alasan = null) {
        if (! this.panel?.serah?.lepas) {
            return;
        }

        return this.kirim(this.panel.serah.lepas, {
            ke_arena_id: keArenaId,
            jenis,
            baris_id: barisId,
            alasan,
        }, { segarkan: true });
    },

    /** Menarik kembali penawaran yang belum diambil gelanggang tujuan. */
    batalkanLepas(serahId) {
        if (! this.panel?.serah?.batal) {
            return;
        }

        return this.kirim(this.panel.serah.batal.replace('__ID__', serahId), {}, { segarkan: true });
    },

    /**
     * Mengambil baris yang ditawarkan gelanggang lain.
     *
     * Sesudah ini barulah ia masuk antrean gelanggang ini dan boleh
     * ditayangkan. Urutan itu yang membuat penulisannya sah di sinkron: adopsi
     * tercatat lebih dulu, kepemilikan berpindah sesudahnya.
     */
    ambilLepasan(serahId) {
        if (! this.panel?.serah?.ambil) {
            return;
        }

        return this.kirim(this.panel.serah.ambil.replace('__ID__', serahId), {}, { segarkan: true });
    },

    /**
     * Menayangkan penampilan Jurus dari panel kendali.
     *
     * Kembaran pilihPartai() untuk kategori Jurus, termasuk tawaran paksanya:
     * satu gelanggang menayangkan satu hal, jadi memilih penampilan berarti
     * melepas partai Tanding yang sedang tayang -- dan penolakan "belum
     * diakhiri" datang lewat kunci galat yang sama.
     */
    async pilihPenampilan(performanceId, paksa = false) {
        if (! this.panel?.jurus?.pilih) {
            return;
        }

        const berhasil = await this.kirim(this.panel.jurus.pilih, { performance_id: performanceId, paksa }, { segarkan: true });

        if (! berhasil && (this._galatKunci ?? []).includes('dapat_dipaksa')) {
            this.paksaTertunda = { penampilanId: performanceId };
        }

        return berhasil;
    },

    bukaSusulan(babak) {
        return this.kirim(this.cfg.bukaSusulan, { babak }, { segarkan: true });
    },

    tutupSusulan() {
        return this.kirim(this.cfg.tutupSusulan, {}, { segarkan: true, metode: 'DELETE' });
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

    /**
     * Modal hasil verifikasi sedang pantas ditampilkan.
     *
     * Syaratnya `sudah_diterapkan`, bukan `hasil` terisi. Hasil terbit begitu
     * ambang suara tercapai, tapi ia belum keputusan sampai Wasit menekan
     * Terapkan -- dan hasil yang dipajang sebesar layar sebelum itu akan
     * diumumkan orang di sekitar meja mendahului Wasitnya sendiri.
     */
    get hasilVerifikasiTampil() {
        return this.verifikasi?.sudah_diterapkan === true
            && this.verifikasi?.id !== this._hasilVerifikasiDitutup;
    },

    tutupHasilVerifikasi() {
        this._hasilVerifikasiDitutup = this.verifikasi?.id ?? null;
    },

    /*
     * Ada protes VAR yang belum diputus.
     *
     * Pasal 15 ayat 3 huruf d menyuruh Wasit Komisi Protes memutuskannya
     * BERSAMA Pengawas/Dewan Wasit Juri dan Wasit, jadi panel wasit harus
     * menampilkannya tanpa berpindah halaman. Panel itu dirancang untuk
     * 844x390 dan sudah penuh, jadi kartunya MENGGANTIKAN tangga hukuman
     * selama tenggatnya berjalan -- pola yang sama dengan verifikasi juri.
     */
    get protesBerjalan() {
        return this.protesTerdekat !== null;
    },

    /** Protes VAR belum diputus yang tenggatnya paling dekat habis. */
    get protesTerdekat() {
        const terbuka = (this.keberatan?.var_reviews ?? []).filter((r) => ! r.keputusan);

        if (terbuka.length === 0) {
            return null;
        }

        return terbuka.reduce((a, b) => ((a.sisa_detik ?? 0) <= (b.sisa_detik ?? 0) ? a : b));
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
            babak: this.babakInput,
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
     *
     * SATU-SATUNYA tombol yang tidak menarik state penuh sesudah POST-nya.
     *
     * Inilah tombol yang ditekan paling sering dan paling beruntun: saat atlet
     * bergerak cepat, tiga juri menekan berkali-kali dalam hitungan detik. Tiap
     * tarikan susulan itu payload panel penuh, dan yang menariknya bukan cuma
     * penekannya -- tiap panel lain ikut menarik begitu nilainya terbit. Satu
     * tekanan melahirkan belasan permintaan, dan antreannya tumbuh lebih cepat
     * daripada terurai.
     *
     * Janji "penekan tombol tidak boleh bergantung pada Reverb" tetap dipegang,
     * caranya saja yang berubah: kalau tidak ada siaran APA PUN yang tiba dalam
     * satu setengah detik sesudah tekanan, state ditarik seperti dulu. Reverb
     * sehat -- dan indikator tekanannya memang datang dari sana -- tidak ada
     * tarikan sama sekali.
     */
    async kirimNilai(corner, jenis) {
        const dikirimAt = Date.now();

        /*
         * `client_ts` ikut dibawa pada SETIAP tekanan, bukan hanya yang
         * diantre. Nilainya sama-sama berguna saat jaringan sehat: jejak audit
         * jadi menyimpan kapan tombolnya ditekan, bukan cuma kapan
         * permintaannya kebetulan sampai. Server tetap menghitung konsensus
         * dari waktunya sendiri.
         */
        const tekan = { babak: this.babakInput, corner, jenis, client_ts: new Date().toISOString() };

        const hasil = await this.kirim(this.cfg.nilai, tekan, { segarkan: false });

        /*
         * Ditahan, bukan dibuang. Tombolnya sendiri sudah mati begitu status
         * koneksi berubah jadi putus, jadi yang sampai ke sini adalah tekanan
         * yang jatuh tepat di celah sebelum peramban menyadarinya.
         */
        if (! hasil && this._gagalJaringan) {
            this._antrekan(tekan);
            this.galat = 'Jaringan putus — tekanan ini ditahan dan dikirim sendiri begitu tersambung lagi.';

            return false;
        }

        if (hasil) {
            clearTimeout(this._tenggatCadangan);
            this._tenggatCadangan = setTimeout(() => {
                if (this._siaranTerakhirAt < dikirimAt) {
                    this.muatUlang();
                }
            }, 1500);
        }

        return hasil;
    },

    kirimHukuman(corner, tingkat, catatan) {
        return this.kirim(this.cfg.hukuman, { babak: this.babakInput, corner, tingkat, catatan: catatan || null });
    },

    kirimHitungan(corner, hitungan) {
        return this.kirim(this.cfg.hitungan, { babak: this.babakInput, corner, hitungan });
    },

    /*
     * Kedua pesilat jatuh dan tidak bangkit -- Pasal 11.6.c huruf b.
     *
     * Bukan dua tekanan hitungan biasa: jalur satu sudut menjatuhkan Teguran di
     * hitungan ke-9 dan mengakhiri partai dengan pemenang di ke-10, dan
     * keduanya keliru di sini. Servernya mencatat dua baris tanpa akibat itu,
     * lalu panel menawarkan penyelesaian yang benar.
     */
    kirimHitunganSerentak(hitungan) {
        return this.kirim(this.cfg.hitungan, { babak: this.babakInput, hitungan, serentak: true });
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
            babak: this.babakInput,
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
            babak: this.babakInput, corner, kejadian,
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

    /*
     * Protes yang DITERIMA wajib menyebut akibatnya -- Pasal 15 ayat 4 huruf
     * c.e menyediakan tiga bentuk jawaban, dan tidak satu pun di antaranya
     * berbunyi "diterima tanpa akibat". Servernya menolak kalau kosong, jadi
     * mengirimnya tanpa akibat berarti tombol Terima tidak pernah bisa
     * berhasil.
     */
    putuskanProtesManajer(id, keputusan, catatan, akibat = null) {
        return this.kirim(this.cfg.protesManajerPutuskan.replace('__ID__', id), {
            keputusan,
            catatan: catatan || null,
            akibat: keputusan === 'diterima' ? akibat || null : null,
        });
    },

    /*
     * Judul tab mengikuti partai, tidak berhenti di partai yang membukanya.
     *
     * Judulnya dicetak Blade saat halaman dimuat, dan panel gelanggang
     * berpindah partai TANPA memuat ulang -- jadi label tabnya membeku
     * menyebut kelas yang sudah ditinggalkan. Di laptop gelanggang, tempat
     * papan, kendali, dan dewan juri dibuka berdampingan, label tab itulah
     * satu-satunya cara membedakannya tanpa mengklik.
     *
     * Awalan peran dan nama aplikasi diambil dari judul awal, bukan ditulis
     * ulang di sini: keduanya sudah ditentukan layout, dan menyalinnya berarti
     * dua tempat yang harus diubah bersamaan.
     */
    _segarkanJudul() {
        if (this._polaJudul === undefined) {
            const bagian = document.title.split(' — ');

            this._polaJudul = bagian.length >= 3
                ? { awalan: bagian[0], akhiran: bagian.slice(2).join(' — ') }
                : null;
        }

        if (this._polaJudul === null || ! this.identitas?.kelas) {
            return;
        }

        document.title = `${this._polaJudul.awalan} — ${this.identitas.kelas} — ${this._polaJudul.akhiran}`;
    },

    _terapkan(data) {
        const idBaru = data.match?.id ?? null;

        /*
         * Layar tunggu tidak bisa menggambar partai; ia harus memuat ulang.
         *
         * Halaman ini dikirim server untuk gelanggang yang belum dipilihkan
         * partai, dan isinya cuma satu kalimat -- tidak ada satu pun elemen
         * panel di dalamnya. Menyerap state baru di sini hanya mengubah angka
         * yang tidak digambar siapa pun: layarnya tetap menyuruh menunggu
         * meski partainya sudah ditayangkan, dan juri baru tahu setelah ada
         * yang menyuruhnya memuat ulang di tengah partai.
         *
         * Muat ulang hanya sekali per pendaratan: `_mendarat` menjaga siaran
         * kedua yang tiba sebelum halaman berganti tidak menyalakan pemuatan
         * kedua.
         */
        if (this.cfg.menunggu && idBaru !== null) {
            if (! this._mendarat) {
                this._mendarat = true;
                window.location.reload();
            }

            return;
        }

        /*
         * Gelanggang beralih ke Jurus: halaman ini bukan lagi halaman yang
         * pantas.
         *
         * Panel Tanding tidak punya satu pun elemen untuk menggambar
         * penampilan Jurus, dan `match` yang jadi null membuatnya memajang
         * partai kosong dengan tombol yang masih bisa ditekan. Servernya yang
         * merender panel Jurus di alamat yang sama; yang perlu dilakukan di
         * sini cuma bertanya lagi.
         *
         * Panel kendali dikecualikan lewat `ikutiTayang`: ia yang menekan
         * peralihannya, dan bentuknya memang sama di kedua mode.
         */
        if (this.cfg.ikutiTayang && data.panel?.tayang === 'jurus') {
            if (! this._mendarat) {
                this._mendarat = true;
                window.location.reload();
            }

            return;
        }

        const gantiPartai = this.match?.id != null && idBaru !== this.match.id;

        if (gantiPartai) {
            this._resetPartai();
        }

        const penanda = `${data.match?.id}-${data.match?.current_round}`;

        if (this._indikatorUntuk !== null && this._indikatorUntuk !== penanda) {
            this._bersihkanIndikator();
        }

        this._indikatorUntuk = penanda;

        this.match = data.match;

        /*
         * Muatan state gelanggang KOSONG cuma berisi `match: null` dan blok
         * panel -- tidak ada rounds, skor_total, maupun hukuman di dalamnya.
         *
         * Menyerapnya apa adanya membuat ketiganya jadi `undefined`, dan
         * panel kendali (satu-satunya panel yang memang dirender di gelanggang
         * kosong) menghitung `skorTotal.merah` di dialog "Akhiri partai" yang
         * selalu ada di DOM. Alpine mengevaluasi ekspresi di dalam x-show yang
         * bernilai salah, jadi tiap pembukaan gelanggang kosong melempar
         * TypeError -- termasuk lewat getter `selisih` yang dibaca panel
         * operator. Nilai awalnya dipasang balik, bukan dibiarkan hilang.
         */
        this.rounds = data.rounds ?? [];
        this.skorTotal = data.skor_total ?? { merah: 0, biru: 0 };
        this.hukuman = data.hukuman ?? {
            merah: { pembinaan: 0, teguran: 0, peringatan: 0, diskualifikasi: false },
            biru: { pembinaan: 0, teguran: 0, peringatan: 0, diskualifikasi: false },
        };
        this.teknik = data.teknik ?? this.teknik;
        this.susulan = data.susulan ?? null;
        this.identitas = data.identitas ?? this.identitas;
        this._segarkanJudul();

        /*
         * Alamat aksi diserap ke cfg, bukan disimpan terpisah: seluruh
         * pemanggil sudah membaca `this.cfg.timerMulai` dan kawan-kawannya.
         * Panel per-gelanggang menghitungnya ulang tiap partai berganti, dan
         * itu satu-satunya hal yang membuatnya berpindah tanpa memuat ulang
         * halaman.
         */
        if (data.panel) {
            this.panel = data.panel;

            if (data.panel.aksi) {
                this.cfg = { ...this.cfg, ...data.panel.aksi, matchId: data.match?.id ?? null };
            }
        }
        // Namanya dibedakan dari penghitung 1-10 di panel wasit: keduanya
        // bernama "hitungan", dan x-data anak yang menaunginya akan menutupi
        // state ini kalau namanya sama.
        this.hitunganTeknik = data.hitungan ?? this.hitunganTeknik;
        this.tawaranWmp = data.tawaran_wmp;
        this.tawaranSerentak = data.tawaran_serentak ?? null;
        this.peraturan = data.peraturan;
        this.officials = data.officials;
        this.riwayat = data.riwayat;
        this.tekanan = data.tekanan ?? null;
        this.riwayatDipangkasPada = data.riwayat_dipangkas_pada ?? null;
        this.keberatan = data.keberatan;
        /*
         * Muatan PERTAMA cuma menyemai, tidak memicu modal hasil. Yang sudah
         * diterapkan sebelum panel ini dibuka bukan kabar baru bagi siapa pun
         * di meja -- mereka sudah melihatnya waktu itu terjadi.
         */
        if (! this._hasilVerifikasiDisemai) {
            this._hasilVerifikasiDisemai = true;

            if (data.verifikasi?.sudah_diterapkan) {
                this._hasilVerifikasiDitutup = data.verifikasi.id;
            }
        }

        this.verifikasi = data.verifikasi;

        this._segarkanTimer();
    },

    /**
     * Membuang keadaan yang terikat partai sebelumnya.
     *
     * Dipanggil hanya di panel gelanggang, saat pengendali memindahkan jadwal.
     * Yang dibuang bukan sembarang: tiap barisnya menutup satu kekeliruan yang
     * benar-benar bisa terjadi di matras.
     */
    _resetPartai() {
        // Pesan "Babak 3 diselesaikan" dari partai sebelumnya menempel di
        // partai baru dan terbaca sebagai kabar tentang yang sekarang.
        this.pesan = null;
        this.galat = null;

        // Polling verifikasi partai lama muncul di panel juri partai baru --
        // juri menjawab pertanyaan tentang kejadian yang bukan di depannya.
        this.verifikasi = null;

        // Semaian modal hasil ikut disetel ulang: verifikasi partai BARU yang
        // kebetulan sudah diterapkan sebelum pengendali memindahkan pointer
        // bukan kabar untuk meja ini, sama seperti saat panel baru dibuka.
        this._hasilVerifikasiDitutup = null;
        this._hasilVerifikasiDisemai = false;

        // Tawaran WMP yang menempel memicu tombol akhiri untuk partai yang
        // salah. Sama untuk tawaran hitungan serentak.
        this.tawaranWmp = null;
        this.tawaranSerentak = null;

        // Riwayat dan keberatan menunjuk id milik partai lama; tombol
        // batalkan di panel dewan juri akan mengirim id yang keliru.
        this.riwayat = [];
        this.tekanan = null;
        this.riwayatDipangkasPada = null;
        this.keberatan = { kartu: { merah: 2, biru: 2 }, var_reviews: [], protes_manajer: [] };

        // Tarikan susulan partai lama yang masih dijadwalkan akan menimpa
        // state yang baru saja diisi.
        clearTimeout(this._tenggatSegarkan);
        clearTimeout(this._tenggatCadangan);

        // Jam yang terus berjalan di partai yang belum dimulai.
        this._hentikanInterpolasi();
        this.sisaMsTampil = 0;

        this._bersihkanIndikator();

        /*
         * Komponen anak punya state sendiri yang tidak terjangkau dari sini --
         * dialog hukuman yang terbuka, catatan yang setengah diketik. Mereka
         * mendengarkan peristiwa ini dan membersihkan miliknya masing-masing.
         */
        window.dispatchEvent(new CustomEvent('partai-berganti'));
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

            /*
             * Belum ada satu pun baris babak: jam menampilkan durasi penuh
             * babak, bukan 00:00. Nol di layar sebelum pertandingan mulai
             * membuat operator tidak punya cara memastikan berapa lama babak
             * yang akan ia jalankan -- dan terbaca seperti timer yang rusak.
             */
            this.sisaMsTampil = this.peraturan?.durasi_babak_ms ?? 0;

            return;
        }

        this._tickAnchorMs = aktif.sisa_ms;
        this._tickAt = performance.now();
        this.sisaMsTampil = aktif.sisa_ms;

        if (aktif.status === 'berjalan') {
            this._mulaiInterpolasi();
            this._mulaiDenyutSinkron();
        } else {
            this._hentikanInterpolasi();
            this._hentikanDenyutSinkron();
        }
    },

    /**
     * Denyut sinkron: selama babak berjalan, state ditarik ulang tiap 20
     * detik supaya jam mengoreksi diri.
     *
     * Hitung mundur di layar diinterpolasi lokal dari satu titik acuan, dan
     * acuan itu hanya diperbarui saat ada siaran masuk. Kalau tabnya sempat
     * tersembunyi, halaman dipulihkan dari cache tombol Kembali, atau satu
     * siaran hilang di jaringan gelanggang, acuannya jadi basi dan jam terus
     * berjalan dari angka yang salah tanpa pernah mengoreksi diri. Terukur
     * satu kali meleset 58 detik -- dan yang membaca layar tidak punya cara
     * tahu bahwa angka itu keliru.
     *
     * Dua puluh detik cukup jarang untuk tidak menambah beban (satu tarikan
     * per panel per 20 detik, jauh di bawah denyut 6 detik yang sudah dipakai
     * panel pemantau) dan cukup sering untuk membuat penyimpangan tidak
     * pernah tumbuh melewati satu petak jam.
     */
    _mulaiDenyutSinkron() {
        if (this._denyutId) {
            return;
        }

        this._denyutId = setInterval(() => this.muatUlang(), 20000);
    },

    _hentikanDenyutSinkron() {
        clearInterval(this._denyutId);
        this._denyutId = null;
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

        /*
         * Tiap siaran yang tiba menandai bahwa Reverb hidup. Yang membacanya
         * kirimNilai(): selama tanda itu terus diperbarui, ia tidak perlu
         * menarik state sendiri sesudah tekanan.
         */
        const segarkan = () => {
            this._siaranTerakhirAt = Date.now();
            this._jadwalkanMuatUlang();
        };

        window.Echo.join(`arena.${this.cfg.arenaId}`)
            .listen('.timer.berubah', (e) => {
                this._siaranTerakhirAt = Date.now();
                this._padaTimer(e);
            })
            .listen('.skor.terbit', (e) => {
                /*
                 * Siaran gelanggang ini juga membawa nilai partai LAIN begitu
                 * panel mengikuti gelanggang, bukan satu partai.
                 *
                 * Selama panel terikat satu partai, penjagaan ini tidak pernah
                 * menggigit. Begitu ia mengikuti gelanggang, siaran yang
                 * menyusul dari partai yang baru saja ditinggalkan akan
                 * menimpa angka partai baru -- papan menampilkan skor milik
                 * pertandingan yang sudah usai.
                 */
                if (e.match_id !== this.match.id) {
                    return;
                }

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
            .listen('.juri.input', (e) => {
                this._siaranTerakhirAt = Date.now();
                this._padaInputJuri(e);
            })
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
            .listen('.verifikasi.berubah', segarkan)
            /*
             * Gelanggang berpindah partai.
             *
             * Tarikan PENUH, bukan tambal parsial. Ini perubahan modus, bukan
             * perubahan angka: alamat aksi, riwayat, keberatan, dan verifikasi
             * semuanya berganti pemilik sekaligus. Di situ kebenaran
             * mengalahkan latensi.
             */
            /*
             * Babak susulan dibuka atau ditutup. Tarikan penuh: ini perubahan
             * modus, dan panel juri harus beralih serentak.
             */
            .listen('.babak-susulan.berubah', () => {
                this._siaranTerakhirAt = Date.now();
                this.muatUlang();
            })
            .listen('.gelanggang.partai', () => {
                this._siaranTerakhirAt = Date.now();

                /*
                 * Layar tunggu memuat ulang begitu POINTER bergerak, apa pun
                 * isinya -- tidak menunggu muatan state menyebut satu partai.
                 *
                 * Gelanggang yang beralih ke penampilan JURUS tidak pernah
                 * mengisi `match`, jadi layar tunggu yang menanti partai akan
                 * diam selamanya sementara nomor Jurus sudah berjalan di
                 * matras. Servernya yang tahu halaman mana yang pantas
                 * dirender; yang perlu dilakukan layar ini cuma bertanya lagi.
                 */
                if (this.cfg.menunggu) {
                    if (! this._mendarat) {
                        this._mendarat = true;
                        window.location.reload();
                    }

                    return;
                }

                this.muatUlang();
            });
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

    /**
     * Titik indikator "juri menekan" -- murni tampilan sementara, dibersihkan
     * sendiri setelah jendela konsensus juri ITU lewat, atau nilainya terbit.
     */
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

        if (!teknik || !this.indikatorTeknik[sisi][teknik]) {
            return;
        }

        /*
         * Tekanan ulang oleh juri yang SAMA tidak lagi diabaikan.
         *
         * Dulu nomor yang sudah ada di daftar membuat fungsi ini pulang lebih
         * awal. Akibatnya juri yang menekan dua kali beruntun untuk teknik yang
         * sama -- lazim saat serangan datang bertubi -- tidak melihat perubahan
         * apa pun di layar, dan membacanya sebagai tekanan yang tidak masuk.
         * Titiknya sekarang dipadamkan sekejap lalu dinyalakan lagi, jadi
         * tekanan keduanya terlihat, dan tenggatnya dihitung ulang dari tekanan
         * yang baru itu.
         */
        const sudahAda = this.indikatorTeknik[sisi][teknik].includes(nomor);

        if (sudahAda) {
            this._setIndikator(sisi, teknik, this.indikatorTeknik[sisi][teknik].filter((n) => n !== nomor));
        }

        /*
         * Nyala susulan dibatalkan kalau tekniknya keburu dipadamkan.
         *
         * Selama jeda 80 milidetik itu, nilai bisa terbit dan memadamkan
         * seluruh titik teknik ini. Tanpa penanda generasi, nyala susulan
         * menghidupkan lagi titik yang baru saja dinyatakan selesai, dan ia
         * menetap sampai tenggatnya sendiri -- layar menyatakan ada yang
         * ditunggu untuk nilai yang sudah terbit.
         */
        const kunciPadam = `${sisi}-${teknik}`;
        const generasi = this._generasiIndikator[kunciPadam] ?? 0;

        const nyalakan = () => {
            if ((this._generasiIndikator[kunciPadam] ?? 0) !== generasi) {
                return;
            }

            this._setIndikator(sisi, teknik, [...this.indikatorTeknik[sisi][teknik], nomor]);
        };

        if (sudahAda) {
            setTimeout(nyalakan, 80);
        } else {
            nyalakan();
        }

        /*
         * Satu penghitung waktu per JURI, bukan per sudut+teknik.
         *
         * Tenggatnya dibawa siaran (`kedaluwarsa_ms`) karena yang berlaku
         * adalah jendela konsensus partai ini, yang bisa ditimpa per turnamen.
         * Margin kecil ditambahkan supaya titik tidak padam sesaat SEBELUM
         * server benar-benar menutup jendelanya.
         */
        const tenggat = (e.kedaluwarsa_ms ?? this.peraturan.window_konsensus_ms) + 250;

        this._batalkanPadam(sisi, teknik, nomor);
        this._waktuIndikator[`${sisi}-${teknik}-${nomor}`] = setTimeout(
            () => this._padamkanSatu(sisi, teknik, nomor),
            tenggat,
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
     * Seluruh titik dikosongkan saat partai atau babaknya berganti.
     *
     * Indikator menyatakan "ada yang sedang ditunggu SEKARANG". Tekanan dari
     * babak atau partai sebelumnya tidak menunggu apa pun lagi, dan tidak ada
     * siaran yang akan datang untuk memadamkannya.
     */
    _bersihkanIndikator() {
        Object.keys(this._waktuIndikator).forEach((k) => clearTimeout(this._waktuIndikator[k]));
        this._waktuIndikator = {};

        Object.keys(this.indikatorTeknik).forEach((sisi) => {
            Object.keys(this.indikatorTeknik[sisi]).forEach((t) => this._padamkanIndikator(sisi, t));
        });
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
            // Permintaan yang tidak sampai adalah bukti paling awal bahwa
            // jaringan perangkat ini sedang mati -- lebih awal daripada
            // WebSocket menyadarinya. Statusnya ikut ditandai supaya tombol
            // nilai berhenti menerima tekanan yang tidak akan pernah sampai.
            Alpine.store('koneksi').tandai('putus');
            this._gagalJaringan = true;

            this.galat = 'Tidak bisa menghubungi server. Periksa jaringan perangkat ini.';

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
/**
 * Perbandingan nilai kedua sudut satu battle Jurus.
 *
 * Read-only dan tanpa Echo: halaman ini dibaca SESUDAH battle selesai, saat
 * tidak ada lagi yang berubah. Menyambungkan WebSocket untuk data yang sudah
 * diam hanya menambah satu koneksi yang menganggur di gelanggang yang
 * jaringannya sudah sibuk.
 */
Alpine.data('perbandinganBattle', (cfg) => ({
    cfg,
    memuat: true,
    battle: null,
    merah: null,
    biru: null,
    selisih: null,
    siap: false,
    seri: false,
    galat: null,

    _saluran: null,

    /*
     * Muatan utuh, supaya <x-silat.komparasi-battle> yang sama bisa dipakai
     * halaman ini DAN keempat panel gelanggang. Tanpa getter ini, halaman
     * battle memegang muatan yang sama dalam bentuk yang berbeda, dan
     * komponennya harus digandakan untuk membacanya.
     */
    get perbandingan() {
        return {
            battle: this.battle,
            merah: this.merah,
            biru: this.biru,
            selisih: this.selisih,
            siap: this.siap,
            seri: this.seri,
        };
    },

    init() {
        this.muat();

        /*
         * Ikut mendengarkan siaran, dengan alasan yang sama seperti
         * jurusPanel. Halaman ini semula dianggap dibaca SESUDAH battle
         * selesai, tapi di sinilah tombol "Tetapkan pemenang" berdiri -- dan
         * yang menekannya membandingkan dua angka yang, tanpa ini, berhenti
         * bergerak sejak halaman dibuka. Satu channel cukup: tiap penampilan
         * battle menyiarkan ke channel battle-nya juga.
         */
        if (window.Echo && this.cfg.battleId) {
            this._saluran = `jurus.battle.${this.cfg.battleId}`;
            window.Echo.private(this._saluran).listen('.jurus.penampilan', () => this.muat());
        }
    },

    destroy() {
        if (this._saluran) {
            window.Echo?.leave(this._saluran);
            this._saluran = null;
        }
    },

    async muat() {
        try {
            const res = await fetch(this.cfg.state, { headers: { Accept: 'application/json' } });

            if (!res.ok) {
                return;
            }

            const data = await res.json();

            this.battle = data.battle;
            this.merah = data.merah;
            this.biru = data.biru;
            this.selisih = data.selisih;
            this.siap = data.siap ?? false;
            this.seri = data.seri ?? false;
        } finally {
            this.memuat = false;
        }
    },

    /** Menetapkan pemenang; sudut dan alasan hanya diisi saat skornya seri. */
    async putuskanBattle(pemenang = null, alasan = null) {
        this.galat = null;

        try {
            const res = await fetch(this.cfg.putuskan, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ pemenang_registration_id: pemenang, alasan }),
            });

            const body = await res.json().catch(() => ({}));

            if (! res.ok) {
                this.galat = pesanGagal(res.status, body);

                return false;
            }

            await this.muat();

            return true;
        } catch (e) {
            this.galat = 'Tidak bisa menghubungi server. Periksa jaringan perangkat ini.';

            return false;
        }
    },

    /** Sudut ini yang memenangkan battle. */
    menang(sudut) {
        const sisi = sudut === 'merah' ? this.merah : this.biru;

        return Boolean(
            this.battle?.winner_registration_id
            && sisi
            && this.battle.winner_registration_id === sisi.registration_id,
        );
    },
}));

Alpine.data('jurusPanel', (cfg) => ({
    cfg,
    memuat: true,
    performance: { id: cfg.performanceId, status: 'terjadwal', started_at: null, duration_ms: null, didiskualifikasi: false, ratified: false },
    peserta: { nama: '', kontingen: '' },
    skor: { median: 0, total_pengurangan: 0, akhir: 0 },
    nilaiJuri: [],
    pengurangan: [],

    /*
     * Perbandingan kedua sudut battle, atau null.
     *
     * Null untuk nomor berformat peringkat -- yang tidak punya battle sama
     * sekali -- dan untuk battle yang salah satu sudutnya belum disahkan.
     * Komponen <x-silat.komparasi-battle> yang memutuskan kapan ia digambar,
     * dari `siap` di dalam muatan ini; panel tidak menghitungnya sendiri.
     */
    komparasi: null,

    berjalanMs: 0,
    pesan: null,
    galat: null,
    _rafId: null,
    _saluran: [],
    _saluranGelanggang: null,
    _pusher: null,
    _padaSambung: null,
    _sedangMenarik: false,
    _mendarat: false,

    // Khusus panel juri (silat.jurus-juri) -- kosong dan tidak dipakai di
    // panel operator, tapi hidup di sini (bukan disebar dari luar) supaya
    // getter reaktifnya (nilaiSaya) tidak ikut bergantung pada pola object
    // spread yang membekukan getter (lihat commit fa068e0).
    nilaiInput: '',

    async init() {
        await this.muatUlang();
        this._pasangEcho();
    },

    destroy() {
        this._hentikanStopwatch();
        this._lepasEcho();
    },

    /**
     * Panel Jurus mengikuti siaran, sama seperti panel Tanding.
     *
     * Sebelum ini Jurus adalah satu-satunya modul tanpa siaran: juri mengirim
     * nilai dan panel operator tetap menulis "Belum ada juri yang mengirim
     * nilai" sampai ada yang menekan muat ulang; pengurangan pengawas tidak
     * pernah sampai ke layar operator sama sekali. Operator yang menunggu
     * angka yang tidak akan datang adalah cara paling mudah menghentikan satu
     * nomor Jurus.
     *
     * Tarikan penuh tiap siaran, bukan tambal parsial dari muatannya. Satu
     * penampilan dinilai sekali oleh tiap juri -- tidak ada tekanan beruntun
     * seperti tombol nilai Tanding, jadi tidak ada yang dibayar dengan
     * menarik state utuh, dan endpoint state tetap satu-satunya tempat aturan
     * median dan pengurangan dihitung.
     *
     * Sambungan yang PULIH ikut menarik ulang: siaran yang lewat selama
     * WebSocket putus tidak pernah terulang sendiri, dan panel yang kembali
     * tersambung dengan angka lama terlihat persis seperti panel yang sehat.
     */
    _pasangEcho() {
        if (! window.Echo || ! this.cfg.performanceId) {
            return;
        }

        const segarkan = () => this.muatUlang({ diam: true });

        this._saluran = [`jurus.penampilan.${this.cfg.performanceId}`];

        if (this.cfg.battleId) {
            this._saluran.push(`jurus.battle.${this.cfg.battleId}`);
        }

        for (const nama of this._saluran) {
            window.Echo.private(nama).listen('.jurus.penampilan', segarkan);
        }

        /*
         * Panel yang beralamat GELANGGANG ikut mendengar channel gelanggang.
         *
         * Channel penampilan cuma membawa perubahan penampilan ITU. Yang tidak
         * dibawanya justru perubahan yang paling menentukan: pengendali
         * berpindah ke penampilan lain, ke partai Tanding, atau mengosongkan
         * gelanggang. Tanpa langganan ini, panel juri Jurus tetap memajang
         * penampilan yang sudah tidak ada di matras -- lengkap dengan tombol
         * nilai yang masih bisa ditekan.
         *
         * Dimuat ulang, bukan ditarik ulang: pergantian tayangan mengubah
         * BENTUK halaman, bukan cuma angkanya. Penampilan yang hilang berarti
         * layar tunggu, dan layar tunggu tidak punya satu pun elemen panel
         * untuk diisi state baru. Servernya yang memutuskan halaman mana yang
         * pantas; tugas panel ini cuma bertanya lagi.
         */
        if (this.cfg.arenaId) {
            this._saluranGelanggang = `arena.${this.cfg.arenaId}`;

            window.Echo.join(this._saluranGelanggang).listen('.gelanggang.partai', () => {
                if (! this._mendarat) {
                    this._mendarat = true;
                    window.location.reload();
                }
            });
        }

        this._pusher = window.Echo.connector?.pusher;
        this._padaSambung = segarkan;
        this._pusher?.connection?.bind('connected', this._padaSambung);
    },

    _lepasEcho() {
        for (const nama of this._saluran ?? []) {
            window.Echo?.leave(nama);
        }

        this._saluran = [];

        if (this._saluranGelanggang) {
            window.Echo?.leave(this._saluranGelanggang);
            this._saluranGelanggang = null;
        }

        if (this._padaSambung) {
            this._pusher?.connection?.unbind('connected', this._padaSambung);
            this._padaSambung = null;
        }
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

    /**
     * @param  {{diam?: boolean}} opsi  `diam` untuk tarikan denyut: kegagalan
     *   tidak menulis pesan galat, dan tidak menghapus pesan yang sedang
     *   dibaca petugas. Penolakan "Nilai juri belum lengkap" yang hilang
     *   sendiri dua detik kemudian membuat ketua menekan Sahkan berulang kali
     *   tanpa pernah membaca sebabnya.
     */
    async muatUlang({ diam = false } = {}) {
        // Satu tarikan pada satu waktu: denyut dua detik tidak boleh menumpuk
        // di atas tarikan yang masih berjalan saat jaringan gelanggang lambat.
        if (this._sedangMenarik) {
            return;
        }

        this._sedangMenarik = true;

        let res;

        try {
            res = await fetch(this.cfg.state, { headers: { Accept: 'application/json' } });
        } catch (e) {
            if (! diam) {
                this.galat = 'Gagal memuat state penampilan.';
            }

            return;
        } finally {
            this._sedangMenarik = false;
        }

        if (!res.ok) {
            if (! diam) {
                this.galat = 'Gagal memuat state penampilan.';
            }

            return;
        }

        const data = await res.json();
        this.performance = data.performance;
        this.peserta = data.peserta;
        this.skor = data.skor;
        this.nilaiJuri = data.nilai_juri;
        this.pengurangan = data.pengurangan;
        this.komparasi = data.komparasi ?? null;
        this.memuat = false;

        /*
         * Alamat aksi ikut diserap tiap tarikan, bukan cuma saat halaman
         * dimuat. Panel yang beralamat GELANGGANG berpindah penampilan tanpa
         * memuat ulang, dan alamat yang dibekukan saat render akan mengirim
         * nilai ke penampilan yang sudah turun dari matras.
         */
        if (data.aksi) {
            Object.assign(this.cfg, data.aksi);
        }

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
                this.galat = pesanGagal(res.status, body);

                return false;
            }

            this.pesan = body.pesan ?? null;
            await this.muatUlang();

            return true;
        } catch (e) {
            // Permintaan yang tidak sampai adalah bukti paling awal bahwa
            // jaringan perangkat ini sedang mati -- lebih awal daripada
            // WebSocket menyadarinya. Statusnya ikut ditandai supaya tombol
            // nilai berhenti menerima tekanan yang tidak akan pernah sampai.
            Alpine.store('koneksi').tandai('putus');
            this._gagalJaringan = true;

            this.galat = 'Tidak bisa menghubungi server. Periksa jaringan perangkat ini.';

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

    /**
     * Menetapkan pemenang battle dari panel yang menampilkan dasar
     * keputusannya.
     *
     * `pemenang` dan `alasan` hanya diisi saat skor akhir kedua sudut sama --
     * dan hanya di situ server menerimanya. Skor yang berbeda diputus angka;
     * mengirim pilihan sudut untuk battle yang tidak seri ditolak server,
     * bukan diam-diam diterima.
     */
    putuskanBattle(pemenang = null, alasan = null) {
        if (! this.cfg.putuskanBattle) {
            return false;
        }

        return this.kirim(this.cfg.putuskanBattle, {
            pemenang_registration_id: pemenang,
            alasan,
        });
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
pantauLatensi(Alpine);
