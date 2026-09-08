<?php

namespace App\Support\Gelanggang;

use App\Events\Gelanggang\PartaiAktifBerubah;
use App\Models\Arena;
use App\Models\ArenaOfficial;
use App\Models\ArenaTayang;
use App\Models\JurusPerformance;
use App\Models\MatchOfficial;
use App\Models\SerahJadwal;
use App\Models\SilatMatch;
use App\Models\User;
use App\Support\Bagan\KesiapanHulu;
use App\Support\Scoring\MatchTimer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu-satunya penulis `arena_tayang`.
 *
 * Namanya `PointerTayang`, bukan lagi `PointerPartaiAktif`: satu gelanggang
 * menayangkan partai Tanding ATAU penampilan Jurus, dan keduanya melewati
 * kelas ini. Nama lamanya menyebut separuh pekerjaannya.
 *
 * Pola kepemilikannya sama dengan MatchTimer atas `current_round`: satu kelas
 * memegang satu invariant, sehingga pertanyaan "apa yang bisa mengubah ini"
 * punya satu jawaban yang bisa dibaca sekali.
 *
 * Pointer ini ORTOGONAL dengan `matches.status`, dan itu disengaja:
 *
 *   `matches.status`          daur hidup satu partai (MatchTimer yang menulis)
 *   `arena_tayang`             apa yang sedang ditayangkan gelanggang (kelas ini)
 *
 * Karena itu mengakhiri partai TIDAK memajukan pointer. Partai yang sudah
 * selesai tetap ditunjuk sampai pengendali memindahkannya -- itulah yang
 * membuat papan hasil siaran bertahan di layar alih-alih berkedip hilang
 * beberapa detik setelah gong terakhir.
 */
class PointerTayang
{
    public function __construct(
        private readonly MatchTimer $timer,
        private readonly KesiapanHulu $kesiapan,
    ) {}

    /**
     * Menunjuk partai yang ditayangkan gelanggang.
     *
     * @param  bool  $paksa  memindahkan pointer meski partai yang sedang
     *                       ditunjuk belum diakhiri -- untuk pengendali yang
     *                       terlanjur menekan "Mulai" pada partai yang keliru
     *
     * @throws RuntimeException
     */
    public function tunjuk(Arena $arena, SilatMatch $match, User $oleh, bool $paksa = false): Arena
    {
        if ($match->arena_id !== $arena->id) {
            /*
             * Dua sebab penolakan yang berbeda, dua kalimat yang berbeda.
             *
             * Partai yang sedang DITAWARKAN ke gelanggang ini bukan kekeliruan
             * alamat: pengendali melihatnya di daftar "ditawarkan dari
             * gelanggang lain", lalu menekan Tayangkan tanpa menekan Ambil
             * lebih dulu. Menjawabnya "tidak dijadwalkan di sini" menyuruhnya
             * mencari kesalahan yang tidak ada, sementara yang kurang cuma
             * satu tombol yang ada di layar yang sama.
             */
            $penawaran = app(SerahTerimaJadwal::class)
                ->penawaranMenggantung(SerahJadwal::TANDING, $match->id);

            if ($penawaran !== null && $penawaran->ke_arena_id === $arena->id) {
                throw new RuntimeException(
                    "Partai ini masih ditawarkan dari {$penawaran->arena->name}. Tekan Ambil lebih dulu, baru bisa ditayangkan di sini.",
                );
            }

            throw new RuntimeException('Partai ini tidak dijadwalkan di gelanggang ini.');
        }

        $this->pastikanBelumDilepas(SerahJadwal::TANDING, $match->id, 'Partai');

        $this->pastikanHuluSudahSampai($match, $paksa);

        $sebelumnya = $this->partaiAktif($arena);

        if ($sebelumnya !== null && $sebelumnya->id === $match->id) {
            return $arena;
        }

        if ($sebelumnya !== null) {
            $this->pastikanBolehDitinggalkan($sebelumnya, $paksa);
        }

        /*
         * Satu gelanggang menayangkan satu hal, jadi menayangkan partai
         * Tanding melepas penampilan Jurus yang sedang tayang. Pelepasan itu
         * dijaga persis seperti melepas partai yang babaknya masih berjalan --
         * sebelumnya tidak, dan akibatnya terlihat di peramban: penampilan
         * yang sedang dinilai tergusur tanpa satu pun penolakan, sementara
         * juri Jurus tetap memegang panel berisi penampilan yang sudah tidak
         * ada di matras.
         */
        $this->pastikanPenampilanBolehDitinggalkan($this->penampilanAktif($arena), $paksa);

        DB::transaction(function () use ($arena, $match, $oleh) {
            $this->tulisTayang($arena, ArenaTayang::TANDING, $match->id, $oleh);

            $this->salinAparatGelanggang($arena, $match);
        });

        $this->umumkan($arena->refresh(), $match, $sebelumnya);

        return $arena;
    }

    /**
     * Mengosongkan gelanggang -- tidak ada partai yang sedang ditayangkan.
     *
     * @param  bool  $paksa  mengosongkan meski partai yang ditunjuk belum
     *                       diakhiri. Jalan yang sama dengan tunjuk(): partai
     *                       yang ditinggalkan hanya dijeda, tidak diselesaikan.
     *                       Tanpa ini, gelanggang yang partainya ditinggal
     *                       berjalan -- perangkat pengendali mati, partai batal
     *                       di tengah -- tidak punya satu jalan pun untuk
     *                       dikosongkan.
     */
    public function kosongkan(Arena $arena, User $oleh, bool $paksa = false): Arena
    {
        $sebelumnya = $this->partaiAktif($arena);

        /*
         * Gelanggang yang sedang menayangkan penampilan Jurus juga harus bisa
         * dikosongkan. Sebelum pointer menampung dua jenis, cabang ini keluar
         * lebih awal begitu `partaiAktif` kosong -- dan gelanggang Jurus tidak
         * punya satu jalan pun untuk dikosongkan, termasuk saat penampilannya
         * batal dimainkan.
         *
         * Penampilan tidak punya babak berjalan yang harus dijeda seperti
         * partai Tanding, jadi tidak ada yang perlu dijaga sebelum dilepas.
         */
        if ($sebelumnya === null) {
            if ($arena->tayang?->menayangkanJurus()) {
                $this->pastikanPenampilanBolehDitinggalkan($this->penampilanAktif($arena), $paksa);

                $this->tulisTayang($arena, null, null, $oleh);
                $this->umumkan($arena->refresh(), null, null);
            }

            return $arena;
        }

        $this->pastikanBolehDitinggalkan($sebelumnya, $paksa);

        $this->tulisTayang($arena, null, null, $oleh);

        $this->umumkan($arena->refresh(), null, $sebelumnya);

        return $arena;
    }

    /**
     * Menunjuk penampilan Jurus yang ditayangkan gelanggang.
     *
     * Jalur yang sama persis dengan tunjuk() untuk Tanding, dan itu memang
     * intinya: sebelum ini panel Jurus beralamat per PENAMPILAN, sehingga tiap
     * pergantian nomor menuntut setiap juri membuka alamat baru sendiri-
     * sendiri di HP-nya. Alasan yang sama yang dulu memindahkan panel Tanding
     * ke alamat gelanggang berlaku utuh di sini.
     *
     * Satu gelanggang menayangkan SATU hal: menunjuk penampilan otomatis
     * melepas partai Tanding yang sedang ditunjuk -- lewat penjagaan yang
     * sama, jadi partai yang babaknya masih berjalan tetap tidak bisa
     * ditinggalkan tanpa `$paksa`.
     *
     * @throws RuntimeException
     */
    public function tunjukPenampilan(Arena $arena, JurusPerformance $performance, User $oleh, bool $paksa = false): Arena
    {
        if ($performance->arena_id !== $arena->id) {
            throw new RuntimeException('Penampilan ini tidak dijadwalkan di gelanggang ini.');
        }

        $this->pastikanBelumDilepas(SerahJadwal::JURUS, $performance->id, 'Penampilan');

        $partaiSebelumnya = $this->partaiAktif($arena);

        if ($partaiSebelumnya !== null) {
            $this->pastikanBolehDitinggalkan($partaiSebelumnya, $paksa);
        }

        $penampilanSebelumnya = $this->penampilanAktif($arena);

        if ($penampilanSebelumnya?->id !== $performance->id) {
            $this->pastikanPenampilanBolehDitinggalkan($penampilanSebelumnya, $paksa);
        }

        DB::transaction(function () use ($arena, $performance, $oleh) {
            $this->tulisTayang($arena, ArenaTayang::JURUS, $performance->id, $oleh);
        });

        $this->umumkan($arena->refresh(), null, $partaiSebelumnya);

        return $arena;
    }

    /**
     * Baris yang sedang ditawarkan ke gelanggang lain tidak boleh ditayangkan.
     *
     * Melepas sudah mengosongkan pointer kalau baris itu sedang tayang, tapi
     * tanpa penjagaan ini pengendali bisa menunjuknya LAGI semenit kemudian --
     * dan ditemukan begitu di peramban: Gelanggang A menayangkan partai yang
     * sudah ia tawarkan ke B, tanpa satu pun pesan. Dua gelanggang lalu
     * sama-sama menganggap partai itu miliknya, persis keadaan yang seluruh
     * rancangan serah-terima ini dibuat untuk mencegah.
     *
     * Jalan keluarnya disebutkan di pesannya: tarik kembali penawarannya.
     *
     * @throws RuntimeException
     */
    private function pastikanBelumDilepas(string $jenis, int $barisId, string $sebutan): void
    {
        $penawaran = app(SerahTerimaJadwal::class)->penawaranMenggantung($jenis, $barisId);

        if ($penawaran === null) {
            return;
        }

        throw new RuntimeException(
            "{$sebutan} ini sedang ditawarkan ke {$penawaran->tujuan->name} dan belum diambil. Batalkan penawarannya dulu kalau mau ditayangkan di sini.",
        );
    }

    /**
     * Penampilan Jurus yang ditunjuk gelanggang ini, kalau masih sah.
     *
     * Saringan `arena_id` dengan alasan yang sama seperti partaiAktif():
     * penampilan bisa dilepas dari jadwal setelah pointer menunjuknya, dan
     * pointer basi akan menayangkan penampilan milik gelanggang lain.
     */
    public function penampilanAktif(Arena $arena): ?JurusPerformance
    {
        $tayang = $arena->tayang;

        if ($tayang === null || ! $tayang->menayangkanJurus()) {
            return null;
        }

        return JurusPerformance::whereKey($tayang->tayang_id)
            ->where('arena_id', $arena->id)
            ->first();
    }

    /**
     * Penampilan berikutnya di antrean gelanggang, menurut urutan tayangnya.
     *
     * Yang sudah selesai dilewati, sama seperti antrean Tanding: pengendali
     * mencari yang berikutnya dimainkan, bukan membaca ulang yang sudah lewat.
     */
    public function penampilanBerikutnya(Arena $arena): ?JurusPerformance
    {
        $sekarang = $arena->tayang?->menayangkanJurus() ? (int) $arena->tayang->tayang_id : null;

        return $this->antreanJurus($arena)
            ->first(fn (JurusPerformance $satu) => $satu->id !== $sekarang && ! $satu->selesai());
    }

    /**
     * Antrean penampilan Jurus gelanggang ini, urut tayang.
     *
     * @return Collection<int, JurusPerformance>
     */
    public function antreanJurus(Arena $arena): Collection
    {
        return JurusPerformance::query()
            ->diGelanggang($arena)
            ->with(['jurusEvent', 'registration.athletes', 'registration.contingent'])
            ->get();
    }

    /**
     * Partai yang ditunjuk gelanggang ini, kalau masih sah.
     *
     * Saringan `arena_id` bukan kueri berlebihan: partai bisa dilepas dari
     * jadwal setelah pointer menunjuknya, dan pointer basi yang menunjuk
     * partai tanpa gelanggang akan menayangkan partai milik gelanggang lain.
     */
    public function partaiAktif(Arena $arena): ?SilatMatch
    {
        if ($arena->active_match_id === null) {
            return null;
        }

        return SilatMatch::whereKey($arena->active_match_id)
            ->where('arena_id', $arena->id)
            ->first();
    }

    /** Partai berikutnya di antrean gelanggang, menurut urutan tayangnya. */
    public function berikutnya(Arena $arena): ?SilatMatch
    {
        return $this->antrean($arena)
            ->first(fn (SilatMatch $partai) => $partai->id !== $arena->active_match_id
                && $partai->status !== SilatMatch::STATUS_SELESAI);
    }

    /**
     * Antrean partai gelanggang ini, urut tayang.
     *
     * Partai yang belum punya `order_in_arena` ditaruh di belakang, bukan
     * dibuang: ia tetap dijadwalkan di sini dan pengendali harus bisa
     * memilihnya.
     *
     * @return Collection<int, SilatMatch>
     */
    public function antrean(Arena $arena, int $limit = 20): Collection
    {
        return SilatMatch::where('arena_id', $arena->id)
            ->with(['red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent', 'bracket.weightClass'])
            ->orderByRaw('order_in_arena is null')
            ->orderBy('order_in_arena')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Menahan partai yang salah satu sudutnya belum bisa diketahui.
     *
     * Pemenang partai hulu di gelanggang lain baru sampai ke laptop ini
     * setelah ada yang menekan tombol sinkron. Menayangkan partainya sebelum
     * itu berarti memanggil pesilat yang belum ditentukan ke matras -- dan
     * yang lebih buruk, pengendali mengisi sudutnya sendiri dengan tebakan.
     *
     * Pesannya menyebut gelanggang mana yang ditunggu. "Data belum lengkap"
     * saja akan dijawab dengan menekan semua tombol sinkron satu per satu,
     * di tengah hari pertandingan.
     *
     * Bisa ditembus dengan $paksa, jalan yang sama dengan meninggalkan partai
     * yang belum diakhiri: kadang panitia memang sudah tahu hasilnya dari
     * gelanggang sebelah dan tidak bisa menunggu jaringan.
     *
     * @throws PenolakanDapatDipaksa
     */
    /**
     * Satu-satunya tempat `arena_tayang` ditulis.
     *
     * updateOrCreate, bukan update: barisnya baru lahir saat pengendali
     * pertama kali memilih sesuatu di gelanggang itu. Mengosongkan pointer
     * TIDAK menghapus barisnya -- `disetel_oleh` harus tetap menyimpan siapa
     * yang mengosongkannya, karena "kosongkan gelanggang" juga sebuah
     * keputusan yang bisa ditanyakan kemudian.
     */
    private function tulisTayang(Arena $arena, ?string $jenis, ?int $id, User $oleh): void
    {
        ArenaTayang::updateOrCreate(
            ['arena_id' => $arena->id],
            [
                'tayang_type' => $jenis,
                'tayang_id' => $id,
                'disetel_pada' => now(),
                'disetel_oleh' => $oleh->id,
            ],
        );

        // Relasi yang sudah termuat di objek ini basi begitu barisnya ditulis;
        // pemanggil berikutnya membaca `active_match_id` lewat relasi itu.
        $arena->unsetRelation('tayang');
    }

    private function pastikanHuluSudahSampai(SilatMatch $match, bool $paksa): void
    {
        if ($paksa) {
            return;
        }

        $menunggu = $this->kesiapan->gelanggangDitunggu($match);

        if ($menunggu === []) {
            return;
        }

        throw new PenolakanDapatDipaksa(
            'Hasil dari '.implode(' dan ', $menunggu).' belum sampai ke gelanggang ini. '
            .'Tarik sinkron dulu lewat menu Sinkron Gelanggang, atau pindah paksa kalau hasilnya sudah pasti.',
        );
    }

    /**
     * @throws PenolakanDapatDipaksa
     */
    /**
     * Penampilan Jurus yang sedang berjalan tidak boleh tergusur diam-diam.
     *
     * Kembaran pastikanBolehDitinggalkan() untuk sisi Jurus, dan alasannya
     * sama persis: satu gelanggang menayangkan satu hal, jadi menayangkan
     * apa pun yang lain berarti melepas yang sedang tayang. Yang membedakan
     * cuma apa yang dilakukan sesudah pengendali memaksa.
     *
     * Partai Tanding yang ditinggalkan paksa DIJEDA babaknya -- jamnya berhenti
     * dan bisa dilanjutkan. Penampilan Jurus tidak punya jeda: timernya sekali
     * jalan, dan menghentikannya berarti mencatat durasi sebagai durasi
     * penampilan yang sebenarnya tidak pernah selesai dimainkan. Durasi itulah
     * yang menentukan pengurangan waktu (Pasal 12.1.e), jadi angka yang
     * dikarang di sini akan muncul sebagai potongan nilai yang tidak pernah
     * terjadi di matras.
     *
     * Karena itu statusnya dibiarkan apa adanya: penampilan tetap tercatat
     * berjalan, dan pengendali bisa kembali menayangkannya lalu
     * menyelesaikannya seperti biasa.
     *
     * @throws PenolakanDapatDipaksa
     */
    private function pastikanPenampilanBolehDitinggalkan(?JurusPerformance $penampilan, bool $paksa): void
    {
        if ($penampilan === null || $penampilan->status !== JurusPerformance::STATUS_BERLANGSUNG) {
            return;
        }

        if (! $paksa) {
            throw new PenolakanDapatDipaksa(
                'Penampilan Jurus yang sedang berjalan belum diselesaikan. Selesaikan dulu, atau pindah paksa.',
            );
        }
    }

    private function pastikanBolehDitinggalkan(SilatMatch $partai, bool $paksa): void
    {
        if ($partai->status !== SilatMatch::STATUS_BERLANGSUNG) {
            return;
        }

        if (! $paksa) {
            throw new PenolakanDapatDipaksa(
                'Partai yang sedang berjalan belum diakhiri. Akhiri dulu, atau pindah paksa.',
            );
        }

        /*
         * Statusnya sengaja TIDAK diubah jadi selesai.
         *
         * Pengendali yang salah memilih partai lalu terlanjur menekan "Mulai"
         * harus bisa kembali ke partai itu dan melanjutkannya. Menandainya
         * selesai berarti memaksa pemenang ditetapkan untuk partai yang belum
         * dimainkan -- kekeliruan yang jauh lebih mahal daripada yang sedang
         * diperbaiki.
         */
        $babak = $partai->babakAktif();

        if ($babak?->berjalan()) {
            $this->timer->jeda($babak);
        }
    }

    /**
     * Menyalin aparat gelanggang ke partai yang baru ditunjuk.
     *
     * Baris `match_officials` yang SUDAH ada tidak ditimpa. Sekretariat yang
     * sengaja menugaskan aparat khusus untuk partai final tidak boleh
     * kehilangan penugasannya hanya karena pengendali menekan tombol pindah.
     *
     * Kenapa disalin, bukan dibaca langsung dari gelanggang saat dibutuhkan:
     * penugasan gelanggang bisa berubah di tengah hari, sementara pertanyaan
     * "siapa yang bertugas di partai nomor 14" harus punya jawaban yang tetap
     * setahun kemudian. Berita acara mencetaknya.
     */
    private function salinAparatGelanggang(Arena $arena, SilatMatch $match): void
    {
        $sudahAda = MatchOfficial::where('match_id', $match->id)->exists();

        if ($sudahAda) {
            return;
        }

        $baris = ArenaOfficial::where('arena_id', $arena->id)
            ->get()
            ->map(fn (ArenaOfficial $aparat) => [
                /*
                 * Kunci dibangkitkan di sini, bukan diserahkan ke basis data.
                 * Penyisipan massal lewat query builder melewati model, jadi
                 * HasUlids tidak pernah dijalankan -- dan kolomnya bukan lagi
                 * auto-increment yang bisa mengisi dirinya sendiri.
                 */
                'id' => (string) Str::ulid(),
                'match_id' => $match->id,
                'user_id' => $aparat->user_id,
                'role' => $aparat->role,
                'number' => $aparat->number,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($baris === []) {
            return;
        }

        MatchOfficial::insert($baris);
    }

    private function umumkan(Arena $arena, ?SilatMatch $match, ?SilatMatch $sebelumnya): void
    {
        /*
         * Cache state dilupakan lebih dulu, bukan dibiarkan kedaluwarsa
         * sendiri. Umurnya cuma satu detik, tapi satu detik itu jatuh persis
         * di antara pengendali menekan dan layar besar berganti -- dan yang
         * ditayangkannya selama itu adalah partai yang sudah bukan miliknya.
         */
        Cache::forget("overlay-state-arena-{$arena->id}");
        Cache::forget("live-state-arena-{$arena->id}");

        PartaiAktifBerubah::dispatch($arena, $match, $sebelumnya);
    }
}
