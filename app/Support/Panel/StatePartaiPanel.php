<?php

namespace App\Support\Panel;

use App\Enums\ResourceAction;
use App\Enums\StatusBabak;
use App\Enums\Sudut;
use App\Models\JudgeInput;
use App\Models\JudgeVerification;
use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use App\Models\User;
use App\Support\Scoring\HitunganTeknik;
use App\Support\Scoring\PollingVerifikasi;
use App\Support\Scoring\SnapshotSkor;
use App\Support\Scoring\TandingScoreCalculator;
use App\Support\Scoring\TanggaHukuman;
use Illuminate\Support\Collection;

/**
 * Seluruh keadaan satu partai, dalam bentuk yang dibaca panel Alpine.
 *
 * Berdiri sendiri, bukan di controller, karena DUA controller memakainya:
 * panel per-partai yang lama dan panel per-gelanggang yang baru. Muatan yang
 * disusun dua kali adalah dua muatan yang suatu saat akan berbeda -- dan
 * bedanya baru ketahuan saat satu panel menampilkan angka yang tidak dimiliki
 * panel sebelahnya di gelanggang yang sama.
 *
 * Bedanya dengan StatePartaiPublik: yang ini memuat `officials`, `riwayat`,
 * dan verifikasi juri. Itu urusan panel aparat, bukan tontonan.
 */
class StatePartaiPanel
{
    /**
     * Sebutan aparat, dihitung sekali per permintaan.
     *
     * @var Collection<int, string>|null
     */
    private ?Collection $sebutan = null;

    public function __construct(
        private readonly TanggaHukuman $tangga,
        private readonly HitunganTeknik $hitungan,
        private readonly TandingScoreCalculator $kalkulator,
        private readonly PollingVerifikasi $polling,
        private readonly SnapshotSkor $snapshot,
    ) {}

    public function __invoke(SilatMatch $match, ?User $untuk = null): array
    {
        $match->load([
            'red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent',
            'bracket.weightClass.tournament.ruleSetting', 'rounds', 'officials.user',
        ]);

        $peraturan = $match->bracket->weightClass->tournament->peraturan();
        /*
         * Babak TAMPIL, bukan babak berjalan.
         *
         * Selama susulan terbuka, ringkasan hukuman dan hitungan teknik harus
         * menunjuk babak yang sedang dicatat. Tanpa ini wasit mencatat teguran
         * susulan ke babak 2 sambil membaca hitungan milik babak 3.
         */
        $babakSekarang = $match->babakTampil();

        /*
         * Angka-angka partai dikumpulkan dalam lima query, bukan lebih dari
         * tiga puluh.
         *
         * Endpoint ini ditarik tiap kali ada nilai terbit, oleh setiap panel
         * yang sedang terbuka. Ditanyakan per angka -- skor tiap babak, tiap
         * tahap hukuman, tiap hitungan teknik, dua sudut masing-masing -- satu
         * tarikan layar jadi puluhan perjalanan ke basis data, dan di server
         * yang melayani satu permintaan pada satu waktu, semuanya mengantre
         * tepat di depan tekanan tombol juri berikutnya.
         *
         * Aturannya tetap tinggal di TanggaHukuman dan HitunganTeknik; yang
         * pindah ke sini cuma keputusan MEMUAT barisnya sekali.
         */
        /*
         * Skor dan rincian teknik datang dari snapshot, bukan dari tiga query
         * agregasi. Di antara dua tekanan tombol jawabannya tidak berubah, dan
         * endpoint ini ditarik jauh lebih sering daripada nilai terbit: tiap
         * panel yang terbuka, tiap kali ada siaran, ditambah sekali tiap dua
         * puluh detik selama babak berjalan.
         *
         * Angkanya tetap angka TandingScoreCalculator -- SnapshotSkor tidak
         * menghitung apa pun sendiri, dan membuang simpanannya begitu ada nilai
         * atau hukuman yang berubah.
         */
        $angka = $this->snapshot->baca($match);
        $rekap = ['total' => $angka['total'], 'babak' => $angka['babak']];
        $teknik = $angka['teknik'];

        $hukumanBerlaku = $match->penalties()->berlaku()->get(['id', 'round', 'corner', 'tier']);
        $hitunganBabakIni = $this->hitungan->hitunganBabak($match, $babakSekarang);

        $rounds = $match->rounds->sortBy('round')->values()->map(fn ($r) => [
            'round' => $r->round,
            'status' => $r->status->value,
            'duration_ms' => $r->duration_ms,
            'sisa_ms' => $r->sisaMs(),
            'skor_merah' => $rekap['babak'][$r->round]['merah'] ?? 0,
            'skor_biru' => $rekap['babak'][$r->round]['biru'] ?? 0,
            'susulan' => $r->round === $match->susulan_round,
            /*
             * Aturannya ditegakkan di server dan dikirim jadi, bukan disusun
             * ulang klien. Panel yang menyusun aturannya sendiri adalah panel
             * yang suatu saat menawarkan tombol untuk hal yang server tolak.
             */
            'dapat_dibuka' => $r->status === StatusBabak::Selesai
                && $r->round < ($match->current_round ?? 0)
                && ! $match->disahkan(),
        ]);

        $penalti = fn (Sudut $sudut) => $this->tangga->ringkasan($hukumanBerlaku, $sudut, $babakSekarang);

        /*
         * Hitungan teknik babak ini, per sudut.
         *
         * Sebelumnya tidak ada satu pun panel yang menampilkannya, sementara
         * akibatnya paling berat di seluruh sistem: hitungan ke-9 menjatuhkan
         * Teguran I, ke-10 mengakhiri partai, dan hitungan beruntun ketiga
         * dalam satu babak membuat lawannya menang teknik. Wasit yang tidak
         * melihat angka ini menekan hitungan ketiga tanpa tahu bahwa
         * tekanannya menghabisi partai -- dan setelah partai berhenti, tidak
         * ada tempat untuk memeriksa hitungan yang sebenarnya sudah berapa.
         */
        $hitunganTeknik = fn (Sudut $sudut) => $this->hitungan->ringkasan($hitunganBabakIni, $sudut);

        return [
            'match' => [
                'id' => $match->id,
                'status' => $match->status,
                'current_round' => $match->current_round,
                'red' => $match->red ? [
                    'registration_id' => $match->red->id,
                    'athletes' => $match->red->athletes->pluck('name'),
                    'contingent' => $match->red->contingent->name,
                ] : null,
                'blue' => $match->blue ? [
                    'registration_id' => $match->blue->id,
                    'athletes' => $match->blue->athletes->pluck('name'),
                    'contingent' => $match->blue->contingent->name,
                ] : null,
                'winner_registration_id' => $match->winner_registration_id,
                'win_reason' => $match->win_reason,
                'ratified' => $match->disahkan(),
            ],
            /*
             * Identitas partai ikut dikirim, tidak lagi dirender server saja.
             *
             * Panel gelanggang berpindah partai TANPA memuat ulang halaman.
             * Apa pun yang dicetak Blade dari $match akan membeku di partai
             * yang sudah ditinggalkan -- dan yang paling berbahaya di antaranya
             * nomor partai, karena itulah yang diumumkan ke gelanggang.
             */
            'identitas' => [
                'partai' => $match->id,
                'gelanggang' => $match->arena?->name,
                'kelas' => $match->bracket->weightClass->name,
                'golongan' => $match->bracket->weightClass->golongan_usia->label(),
                'jenis_kelamin' => $match->bracket->weightClass->jenis_kelamin->label(),
                'babak_bagan' => $match->bracket->namaBabak($match->round),
            ],
            'rounds' => $rounds,
            'susulan' => $match->susulan_round === null ? null : [
                'round' => $match->susulan_round,
                'dibuka_at' => optional($match->susulan_dibuka_at)->toIso8601String(),
            ],
            'skor_total' => $rekap['total'],
            'hukuman' => [
                'merah' => $penalti(Sudut::Merah),
                'biru' => $penalti(Sudut::Biru),
            ],
            /*
             * Berapa kali tiap teknik terbit, per sudut -- bahan papan hasil
             * yang muncul sesudah partai selesai. Sampai sekarang rincian ini
             * hanya bisa dibaca lewat overlay siaran atau berita acara PDF;
             * petugas gelanggang yang ingin tahu dari mana angka akhirnya
             * datang harus membuka vMix atau mencetak berkas.
             */
            'teknik' => $teknik,
            'hitungan' => [
                'merah' => $hitunganTeknik(Sudut::Merah),
                'biru' => $hitunganTeknik(Sudut::Biru),
                // Ambangnya ikut dikirim supaya panel menyatakan sisa tekanan
                // yang tersedia, bukan memajang angka telanjang yang artinya
                // hanya diketahui orang yang hafal Pasal 11.6.g.3.
                'ambang_beruntun' => (int) config('scoring.tanding.hitungan_teknik.menang_teknik_setelah_hitungan_beruntun'),
                'ambang_teguran' => (int) config('scoring.tanding.hitungan_teknik.teguran_pada_hitungan'),
                'ambang_mutlak' => (int) config('scoring.tanding.hitungan_teknik.mutlak_pada_hitungan'),
            ],
            'tawaran_wmp' => $this->kalkulator->cekTawaranWmp($match, $rekap['total'])?->value,
            /*
             * Tawaran saat kedua pesilat sama-sama tidak bisa bangkit. Sama
             * seperti WMP: yang menekan tombol akhiri tetap manusia.
             */
            'tawaran_serentak' => $this->kalkulator->penyelesaianHitunganSerentak(
                $match,
                (int) config('scoring.tanding.hitungan_teknik.mutlak_pada_hitungan'),
                // Barisnya sudah dimuat di atas; tanpa ini ia jadi query keenam.
                $hitunganBabakIni,
            ),
            'peraturan' => [
                'jumlah_juri' => $peraturan->jumlah_juri_tanding,
                'ambang_sepakat' => $peraturan->ambang_sepakat,
                'window_konsensus_ms' => $peraturan->window_konsensus_ms,
                'jumlah_babak' => $peraturan->babakUntuk($match->bracket->weightClass->golongan_usia)['jumlah'],
                /*
                 * Dipakai jam panel saat belum ada satu pun baris babak.
                 * Tanpa ini operator melihat 00:00 sebelum menekan "Mulai",
                 * dan tidak punya cara memastikan durasi babak yang akan
                 * dijalankannya.
                 */
                'durasi_babak_ms' => $peraturan->babakUntuk($match->bracket->weightClass->golongan_usia)['durasi_ms'],
            ],
            'officials' => $match->officials->map(fn ($o) => [
                'role' => $o->role, 'number' => $o->number, 'name' => $o->user->name, 'user_id' => $o->user_id,
            ]),
            'riwayat' => $this->riwayat($match),
            /*
             * Rincian penekan tombol tiap nilai sudah pindah ke node arsip.
             *
             * Dinyatakan, bukan dibiarkan kosong. Riwayat tanpa penekan pada
             * partai yang dipangkas terbaca sama persis dengan partai yang
             * nilainya memang terbit tanpa satu pun juri menekan -- dan yang
             * kedua itu keadaan yang serius. Panel harus bisa membedakannya.
             */
            'riwayat_dipangkas_pada' => $match->judge_inputs_dipangkas_pada?->toIso8601String(),
            /*
             * Lajur tekanan juri mentah -- termasuk yang TIDAK jadi nilai.
             *
             * Dikirim hanya kepada yang boleh membuka panel peninjauan hasil.
             * Panel juri menarik endpoint yang sama, dan tekanan mentah tidak
             * ada gunanya di sana sementara ongkosnya satu kueri pada jalur
             * yang ditarik tiap kali sebuah nilai terbit.
             */
            'tekanan' => $untuk !== null && $untuk->can(rk('hasil-partai', ResourceAction::View))
                ? $this->tekanan($match)
                : null,
            'keberatan' => $this->keberatan($match),
            'verifikasi' => $this->verifikasi($match, $untuk),
        ];
    }

    /**
     * Verifikasi juri yang sedang berjalan -- Pasal 13.
     *
     * # Kenapa disaring menurut siapa yang meminta
     *
     * Satu endpoint state melayani semua panel di gelanggang, panel juri
     * termasuk. Kalau jawaban tiap juri ikut dikirim apa adanya, juri yang
     * membuka panelnya akan melihat rekannya sudah menjawab "sudut merah",
     * lalu tidak lagi menjawab apa yang dilihatnya sendiri.
     *
     * Maka: juri partai ini hanya menerima SIAPA yang sudah menjawab, tanpa
     * jawabannya, selama pollingnya berjalan. Wasit, Ketua Pertandingan, dan
     * Dewan Wasit Juri menerima jawabannya -- mereka memang harus melihat
     * jawaban masuk satu per satu untuk tahu siapa yang masih ditunggu.
     *
     * Begitu polling ditutup, jawabannya terbuka untuk semua: tidak ada lagi
     * juri yang bisa terpengaruh, dan berita acara memang memuatnya.
     *
     * @return array<string, mixed>|null
     */
    private function verifikasi(SilatMatch $match, ?User $untuk): ?array
    {
        $verifikasi = JudgeVerification::query()
            ->where('match_id', $match->id)
            ->with(['answers.judge:id,name', 'peminta:id,name'])
            ->latest('id')
            ->first();

        if ($verifikasi === null) {
            return null;
        }

        $bolehLihatJawaban = ! $verifikasi->berjalan() || ! $this->juriPartaiIni($match, $untuk);

        return [
            'id' => $verifikasi->id,
            'round' => $verifikasi->round,
            'jenis' => $verifikasi->jenis->value,
            'pertanyaan' => $verifikasi->jenis->pertanyaan(),
            'pilihan_tidak_ada' => $verifikasi->jenis->pilihanTidakAda(),
            'tingkat_pelanggaran' => $verifikasi->tingkat_pelanggaran?->value,
            'tingkat_pelanggaran_label' => $verifikasi->tingkat_pelanggaran?->label(),
            'status' => $verifikasi->status,
            'berjalan' => $verifikasi->berjalan(),
            'diminta_at' => $verifikasi->diminta_at?->toIso8601String(),
            /*
             * Pasal 13 menyebut verifikasi datang dari Ketua Pertandingan
             * maupun Wasit. Panel juri menyebutkan yang mana -- juri yang
             * ditanya berhak tahu siapa yang menghentikan pertandingan, dan
             * dua jabatan itu punya bobot berbeda di gelanggang.
             */
            'diminta_oleh' => $verifikasi->peminta?->name,
            'hasil' => $verifikasi->hasil?->value,
            'hasil_label' => $verifikasi->hasil?->label(),
            'sudah_diterapkan' => $verifikasi->sudahDiterapkan(),
            /*
             * Terisi berarti jawaban ini sudah dipakai wasit untuk menerbitkan
             * jatuhan. Tanpa penanda itu, saran di panel wasit menggantung
             * setelah nilainya terbit dan mengundang penekanan kedua untuk
             * jatuhan yang sama.
             */
            'score_event_id' => $verifikasi->score_event_id,
            'akibat' => $verifikasi->hasil ? $this->polling->akibat($verifikasi) : null,
            'ambang' => $match->bracket->weightClass->tournament->peraturan()->ambang_sepakat,
            'jumlah_juri' => $this->polling->jumlahJuri($verifikasi),
            'hitungan' => $bolehLihatJawaban ? $this->polling->hitungan($verifikasi) : null,
            'jawaban' => $verifikasi->answers->sortBy('judge_number')->values()->map(fn ($j) => [
                'judge_user_id' => $j->judge_user_id,
                'judge_number' => $j->judge_number,
                'judge_name' => $j->judge?->name,
                'sebutan' => $j->sebutan(),
                // Yang disembunyikan cuma ini. Siapa yang sudah menjawab tetap
                // terlihat -- itu tidak menggiring siapa pun.
                'jawaban' => $bolehLihatJawaban ? $j->jawaban->value : null,
                'jawaban_label' => $bolehLihatJawaban ? $j->jawaban->label() : null,
                'server_ts' => $j->server_ts?->toIso8601String(),
            ]),
            'menunggu' => $this->polling->belumMenjawab($verifikasi)->map(fn ($o) => [
                'judge_user_id' => $o->user_id,
                'judge_number' => $o->number,
                'sebutan' => $o->sebutan(),
            ])->values(),
        ];
    }

    /** Apakah pengguna ini juri yang ditugaskan di partai ini. */
    private function juriPartaiIni(SilatMatch $match, ?User $untuk): bool
    {
        if ($untuk === null) {
            return false;
        }

        return MatchOfficial::query()
            ->where('match_id', $match->id)
            ->where('user_id', $untuk->id)
            ->where('role', MatchOfficial::ROLE_JURI)
            ->exists();
    }

    /** @return array<string, mixed> */
    private function keberatan(SilatMatch $match): array
    {
        $kartu = $match->protestCards()->get()->keyBy(fn ($k) => $k->corner->value);
        $sisaKartu = fn (string $corner) => $kartu->has($corner)
            ? $kartu[$corner]->sisaKartu()
            : config('scoring.var.kartu_protes.tanding');

        $varReviews = $match->varReviews()->with(['pemutus'])->latest('id')->limit(20)->get()->map(fn ($v) => [
            'id' => $v->id,
            'round' => $v->round,
            'corner' => $v->corner->value,
            'kejadian' => $v->kejadian,
            'diajukan_at' => $v->diajukan_at->toIso8601String(),
            'tenggat_at' => $v->tenggat_at->toIso8601String(),
            'sisa_detik' => $v->sisaDetik(),
            'lewat_tenggat' => $v->lewatTenggat(),
            'keputusan' => $v->keputusan,
            'catatan' => $v->catatan,
        ]);

        $protesManajer = $match->managerProtests()->latest('id')->get()->map(fn ($p) => [
            'id' => $p->id,
            'level' => $p->level,
            'parent_id' => $p->parent_id,
            'diajukan_at' => $p->diajukan_at->toIso8601String(),
            'tenggat_keputusan_at' => $p->tenggat_keputusan_at->toIso8601String(),
            'keputusan' => $p->keputusan,
            /*
             * Akibat ikut dikirim, bukan hanya disimpan. Protes yang diterima
             * WAJIB menyebut akibatnya (Pasal 15 ayat 4 huruf c.e), dan
             * "Diterima" tanpa menyebut apa yang harus terjadi berikutnya
             * adalah separuh keputusan bagi yang membacanya di gelanggang.
             */
            'akibat' => $p->akibat?->value,
            'akibat_label' => $p->akibat?->label(),
            'akibat_diterapkan' => $p->akibat_diterapkan_at !== null,
            'catatan' => $p->catatan,
            'final' => $p->final(),
        ]);

        return [
            'kartu' => ['merah' => $sisaKartu('red'), 'biru' => $sisaKartu('blue')],
            'var_reviews' => $varReviews,
            'protes_manajer' => $protesManajer,
        ];
    }

    /**
     * Nilai dan hukuman terbaru yang masih berlaku, dipakai panel dewan juri
     * untuk membatalkan salah satunya. Digabung satu daftar terurut waktu
     * supaya panel tidak perlu menyandingkan dua daftar terpisah sendiri.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Tiap tekanan tombol juri pada partai ini, apa pun hasilnya.
     *
     * # Kenapa ada
     *
     * Riwayat panel hanya memperlihatkan nilai yang TERBIT. Tekanan yang tidak
     * cukup disepakati tidak meninggalkan jejak di layar mana pun -- padahal
     * itulah yang ditanyakan pelatih saat memprotes: "juri saya menekan, kenapa
     * tidak jadi nilai?". Dewan Wasit Juri sebelumnya cuma bisa menjawabnya
     * dengan membuka basis data, di tengah tenggat protes lima menit.
     *
     * Yang membuatnya bisa dijawab adalah tiga keadaan yang dibedakan di sini:
     * tekanan yang ikut menerbitkan nilai, tekanan yang ditolak sistem beserta
     * alasannya, dan tekanan yang berdiri SENDIRIAN -- sah, tercatat, tapi
     * tidak menemukan juri lain di dalam jendela kesepakatan. Yang ketiga
     * itulah jawaban yang selama ini tidak terlihat.
     *
     * # Kenapa dibatasi
     *
     * Satu partai tiga babak meninggalkan puluhan sampai ratusan tekanan.
     * Enam puluh terbaru cukup untuk menjawab protes atas kejadian yang baru
     * saja terjadi -- dan protes selalu tentang kejadian yang baru saja
     * terjadi. Yang lebih lama ada di berita acara dan paket arsip.
     *
     * @return list<array<string, mixed>>
     */
    private function tekanan(SilatMatch $match): array
    {
        /*
         * Partai yang riwayatnya sudah dipangkas menjawab dengan daftar
         * kosong, bukan daftar yang seolah tidak pernah ada tekanan.
         * Pembedanya `riwayat_dipangkas_pada` yang sudah ikut di payload.
         */
        if ($match->judge_inputs_dipangkas_pada !== null) {
            return [];
        }

        $sebutan = $this->sebutanAparat($match);

        return JudgeInput::query()
            ->where('match_id', $match->id)
            ->latest('server_ts')
            ->latest('id')
            ->limit(60)
            ->get(['id', 'round', 'judge_user_id', 'corner', 'point_type', 'server_ts', 'score_event_id', 'rejected_reason'])
            ->map(fn (JudgeInput $i) => [
                'id' => $i->id,
                'round' => $i->round,
                'juri' => $sebutan[$i->judge_user_id] ?? null,
                'corner' => $i->corner->value,
                'teknik' => $i->point_type->label(),
                'nilai' => $i->point_type->nilai(),
                'waktu' => $i->server_ts->toIso8601String(),
                /*
                 * Tiga keadaan, tiga kata yang berbeda. "Tidak jadi nilai"
                 * saja menyamakan tekanan yang ditolak sistem dengan tekanan
                 * sah yang kebetulan sendirian -- dan yang kedua adalah
                 * tekanan yang juri-nya benar.
                 */
                'status' => match (true) {
                    $i->score_event_id !== null => 'terbit',
                    $i->rejected_reason !== null => 'ditolak',
                    default => 'sendirian',
                },
                'score_event_id' => $i->score_event_id,
                'alasan_tolak' => $i->rejected_reason,
            ])
            ->all();
    }

    /**
     * Sebutan aparat partai ini, dipetakan sekali per permintaan.
     *
     * Dipakai riwayat DAN lajur tekanan. Dihitung dua kali, panel Dewan Wasit
     * Juri membayar dua kueri untuk jawaban yang sama persis.
     *
     * @return Collection<int, string>
     */
    private function sebutanAparat(SilatMatch $match): Collection
    {
        return $this->sebutan ??= ($match->relationLoaded('officials')
            ? $match->officials
            : $match->officials()->with('user:id,name')->get())
            ->mapWithKeys(fn (MatchOfficial $o) => [$o->user_id => $o->sebutan()]);
    }

    private function riwayat(SilatMatch $match): array
    {
        /*
         * Sebutan aparat dipetakan sekali di sini, bukan di-query per baris.
         * Panel dewan juri sanggup menampilkan 60 baris sekaligus; menanyakan
         * nama tiap penekan satu per satu akan jadi puluhan query untuk satu
         * halaman yang dibuka justru saat pertandingan sedang disengketakan.
         */
        $sebutan = $this->sebutanAparat($match);

        /*
         * Nilai dan hukuman yang lahir dari verifikasi juri tidak punya
         * judge_inputs -- tidak ada juri yang menekan tombolnya. Tanpa
         * penandaan ini, riwayat menampilkan jatuhan +3 yang seolah muncul
         * sendiri tanpa satu pun penekan, dan itu justru baris yang paling
         * dipersoalkan saat hasilnya digugat.
         */
        $dariVerifikasi = JudgeVerification::query()
            ->where('match_id', $match->id)
            ->where('status', JudgeVerification::SELESAI)
            ->get(['id', 'score_event_id', 'penalty_id']);

        $verifikasiNilai = $dariVerifikasi->whereNotNull('score_event_id')->pluck('id', 'score_event_id');
        $verifikasiHukuman = $dariVerifikasi->whereNotNull('penalty_id')->pluck('id', 'penalty_id');

        $nilai = $match->scoreEvents()->berlaku()->with('judgeInputs:id,score_event_id,judge_user_id')
            ->latest('id')->limit(30)->get()->map(fn ($s) => [
                'tipe' => 'nilai',
                'id' => $s->id,
                'round' => $s->round,
                'corner' => $s->corner->value,
                'label' => "{$s->point_type->label()} ({$s->value})",
                // Angkanya berdiri sendiri di kolomnya, bukan hanya menempel di
                // label: panel Dewan Wasit Juri membandingkan belasan baris ke
                // bawah, dan angka yang rata kanan jauh lebih cepat dibaca.
                'nilai' => '+'.$s->value,
                'waktu' => $s->server_ts->toIso8601String(),
                'verifikasi_id' => $verifikasiNilai[$s->id] ?? null,
                // Urut supaya "Juri 1, Juri 3" tidak berganti-ganti urutan tiap resync.
                /*
                 * Nilai mutlak jatuhan tidak punya judge_inputs sama sekali --
                 * tidak ada juri yang menekan tombolnya. Tanpa penandaan ini,
                 * riwayat menampilkan +3 yang seolah muncul sendiri, dan itu
                 * justru baris yang paling dipersoalkan saat hasilnya digugat.
                 */
                'oleh' => match (true) {
                    $s->mutlak() => $sebutan[$s->issued_by] ?? 'Wasit',
                    isset($verifikasiNilai[$s->id]) => 'Verifikasi juri',
                    default => $s->judgeInputs
                        ->map(fn ($i) => $sebutan[$i->judge_user_id] ?? null)
                        ->filter()->unique()->sort()->values()->implode(', ') ?: null,
                },
            ]);

        $hukuman = $match->penalties()->berlaku()->with('pencatat:id,name')
            ->latest('id')->limit(30)->get()->map(fn ($p) => [
                'tipe' => 'hukuman',
                'id' => $p->id,
                'round' => $p->round,
                'corner' => $p->corner->value,
                'label' => "{$p->tier->label()} ".($p->points !== null ? $p->points : '(DQ)'),
                'nilai' => $p->points !== null ? (string) $p->points : 'DQ',
                'waktu' => $p->created_at->toIso8601String(),
                'verifikasi_id' => $verifikasiHukuman[$p->id] ?? null,
                'oleh' => isset($verifikasiHukuman[$p->id])
                    ? 'Verifikasi juri'
                    : ($sebutan[$p->created_by] ?? $p->pencatat?->name),
            ]);

        return $nilai->concat($hukuman)->sortByDesc('waktu')->values()->all();
    }
}
