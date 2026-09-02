<?php

namespace App\Support\Live;

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Models\Arena;
use App\Models\SilatMatch;
use App\Support\Scoring\TandingScoreCalculator;
use App\Support\Scoring\TanggaHukuman;

/**
 * Payload skor publik satu gelanggang -- dipakai overlay siaran (Fase 6,
 * dikunci jaringan lokal) dan live score publik (Fase 5, dibuka lewat
 * tunnel). Keduanya butuh bentuk data yang identik: tanpa `officials`,
 * tanpa `judge_user_id`, tanpa input mentah juri -- itu urusan panel admin,
 * bukan tontonan.
 *
 * Awalnya hidup di App\Http\Controllers\OverlayController; dipindah ke sini
 * begitu LiveScoreController butuh bentuk yang sama persis, supaya kedua
 * pemakainya tidak diam-diam bergeser satu sama lain.
 */
class StatePartaiPublik
{
    public function __construct(
        private readonly TandingScoreCalculator $kalkulator,
        private readonly TanggaHukuman $tangga,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(Arena $arena): array
    {
        $match = $this->partaiRelevan($arena);

        if ($match === null) {
            return ['ada_partai' => false];
        }

        $babakSekarang = $match->current_round ?? $match->rounds->max('round') ?? 1;

        /*
         * Hukuman dimuat sekali lalu dihitung di memori, bukan ditanyakan
         * enam kali. Layar publik dan overlay siaran memang boleh tertinggal
         * dari matras, tapi permintaannya tetap mengantre di server yang sama
         * dengan panel gelanggang -- dan yang menunggu di belakangnya adalah
         * tekanan tombol juri.
         */
        $hukumanBerlaku = $match->penalties()->berlaku()->get(['id', 'round', 'corner', 'tier']);
        $rekap = $this->kalkulator->rekapSkor($match);

        $penalti = fn (Sudut $sudut) => collect($this->tangga->ringkasan($hukumanBerlaku, $sudut, $babakSekarang))
            ->only(['pembinaan', 'teguran', 'peringatan'])
            ->all();

        /*
         * Berapa KALI tiap teknik terbit sepanjang partai, per sudut.
         *
         * Dipakai papan hasil siaran, yang merinci dari mana angka akhirnya
         * datang: "menang angka 21-14" tidak menjelaskan apa pun sampai
         * penonton tahu 21 itu tersusun dari berapa pukulan, tendangan, dan
         * jatuhan. Yang dihitung hanya nilai yang BERLAKU -- yang dibatalkan
         * tidak ikut menyusun skornya, jadi ia juga tidak boleh muncul di
         * rinciannya.
         */
        $terbit = $match->scoreEvents()->berlaku()
            ->selectRaw('corner, point_type, count(*) as jumlah')
            ->groupBy('corner', 'point_type')
            ->get();

        $teknik = fn (Sudut $sudut) => collect(JenisSerangan::cases())
            ->mapWithKeys(fn (JenisSerangan $jenis) => [
                $jenis->value => (int) $terbit
                    ->firstWhere(fn ($baris) => $baris->corner === $sudut && $baris->point_type === $jenis)
                    ?->jumlah,
            ])
            ->all();

        $round = $match->rounds->firstWhere('round', $babakSekarang);
        $peraturan = $match->bracket->weightClass->tournament->peraturan();

        return [
            'ada_partai' => true,
            'match' => [
                'id' => $match->id,
                'status' => $match->status,
                'current_round' => $match->current_round,
                'win_reason' => $match->win_reason,
                'winner_corner' => $match->winner_registration_id === null ? null
                    : ($match->winner_registration_id === $match->red_registration_id ? 'red' : 'blue'),
                'ratified' => $match->disahkan(),
            ],
            'kelas' => [
                'nama' => $match->bracket->weightClass->name,
                'golongan' => $match->bracket->weightClass->golongan_usia->label(),
                'jenis_kelamin' => $match->bracket->weightClass->jenis_kelamin->label(),
            ],
            'babak_label' => $match->bracket->namaBabak($match->round),
            /*
             * Jumlah babak ikut dikirim supaya overlay bisa menulis "Babak 2/3".
             * `babak_label` di atas adalah tahap bagan ("Semifinal"), bukan babak
             * pertandingan — dua hal berbeda yang sebelumnya membuat scorebug
             * siaran tidak pernah menyebut babak keberapa yang sedang berjalan.
             */
            'jumlah_babak' => $peraturan->babakUntuk($match->bracket->weightClass->golongan_usia)['jumlah'],
            'red' => $match->red ? [
                'nama' => $match->red->athletes->pluck('name')->implode(', '),
                'kontingen' => $match->red->contingent->name,
            ] : null,
            'blue' => $match->blue ? [
                'nama' => $match->blue->athletes->pluck('name')->implode(', '),
                'kontingen' => $match->blue->contingent->name,
            ] : null,
            'timer' => $round ? [
                'round' => $round->round,
                'status' => $round->status->value,
                'duration_ms' => $round->duration_ms,
                'accumulated_ms' => $round->accumulated_ms,
                'started_at' => optional($round->started_at)->toIso8601String(),
                // Dihitung server, sama seperti di siaran timer.berubah: mesin
                // vMix dan HP penonton tidak pernah sejam dengan server, dan
                // hitung mundur yang meleset beberapa detik terlihat semua
                // orang di layar besar.
                'sisa_ms' => $round->sisaMs(),
            ] : null,
            'skor_total' => $rekap['total'],
            'hukuman' => [
                'merah' => $penalti(Sudut::Merah),
                'biru' => $penalti(Sudut::Biru),
            ],
            'teknik' => [
                'merah' => $teknik(Sudut::Merah),
                'biru' => $teknik(Sudut::Biru),
            ],
            /*
             * Formasi juri, bukan identitasnya: berapa juri yang bertugas,
             * berapa yang harus sepakat, dan berapa lama jendela konsensusnya.
             * Overlay memakainya untuk menggambar indikator J1..Jn dan untuk
             * tahu kapan indikator yang tidak mencapai ambang harus padam.
             */
            'peraturan' => [
                'jumlah_juri' => $peraturan->jumlah_juri_tanding,
                'ambang_sepakat' => $peraturan->ambang_sepakat,
                'window_konsensus_ms' => $peraturan->window_konsensus_ms,
            ],
        ];
    }

    /** Partai yang sedang berlangsung di gelanggang ini, atau partai terakhir yang selesai kalau belum ada yang berlangsung. */
    private function partaiRelevan(Arena $arena): ?SilatMatch
    {
        $muatan = [
            'red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent',
            // Setelan peraturan ikut dimuat, bukan ditanyakan belakangan: ia
            // dibaca beberapa kali per tarikan (jumlah babak, formasi juri),
            // dan tiap pembacaan tanpa ini jadi perjalanan sendiri.
            'bracket.weightClass.tournament.ruleSetting', 'rounds',
        ];

        return SilatMatch::where('arena_id', $arena->id)
            ->where('status', SilatMatch::STATUS_BERLANGSUNG)
            ->with($muatan)
            ->first()
            ?? SilatMatch::where('arena_id', $arena->id)
                ->where('status', SilatMatch::STATUS_SELESAI)
                ->with($muatan)
                ->latest('updated_at')
                ->first();
    }
}
