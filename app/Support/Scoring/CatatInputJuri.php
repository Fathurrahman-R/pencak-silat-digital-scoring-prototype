<?php

namespace App\Support\Scoring;

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Events\Scoring\JudgeInputReceived;
use App\Events\Scoring\ScoreAwarded;
use App\Models\JudgeInput;
use App\Models\MatchRound;
use App\Models\SilatMatch;
use App\Models\User;
use Closure;
use Throwable;

/**
 * Menerima satu tekanan tombol juri lewat HTTP dan menjalankannya lewat
 * ConsensusEvaluator.
 *
 * Baris mentahnya selalu tersimpan apa pun hasilnya (FR-F-04) -- yang
 * membedakan diterima atau ditolak hanyalah `rejected_reason`. Input yang
 * tiba saat babak yang dimaksud bukan babak yang sedang berjalan ditolak di
 * sini, sebelum sempat menyentuh ConsensusEvaluator sama sekali (FR-F-07).
 */
class CatatInputJuri
{
    public function __construct(private readonly ConsensusEvaluator $evaluator) {}

    public function __invoke(
        SilatMatch $match,
        User $juri,
        int $babak,
        Sudut $sudut,
        JenisSerangan $jenis,
        ?string $clientTs = null,
    ): JudgeInput {
        $babakDimaksud = $match->rounds()->where('round', $babak)->first();
        $ditolak = ! $this->diterima($match, $babak, $babakDimaksud);

        $input = JudgeInput::create([
            'match_id' => $match->id,
            'round' => $babak,
            'judge_user_id' => $juri->id,
            'corner' => $sudut,
            'point_type' => $jenis,
            'server_ts' => now(),
            'client_ts' => $clientTs,
            'rejected_reason' => $ditolak ? $this->alasanTolak($match, $babak, $babakDimaksud) : null,
        ]);

        /*
         * Partainya dipasang ke input, tidak dibiarkan ditarik ulang.
         *
         * Tanpa ini, tiap persinggahan berikutnya -- siaran yang mencari nomor
         * juri, evaluator yang membaca jendela konsensus -- menanyakan partai
         * yang sama beserta seluruh rantai kelas dan peraturannya sekali lagi.
         * Lima perjalanan ke basis data untuk baris yang sudah ada di tangan
         * pemanggil, di jalur yang dilewati tiap tekanan tombol juri.
         */
        $input->setRelation('match', $match);

        /*
         * Konsensus dihitung LEBIH DULU, dan siarannya tidak boleh
         * menggagalkan apa pun.
         *
         * Sebelum ini urutannya terbalik dan siarannya telanjang: saat server
         * Reverb mati, dorongan `JudgeInputReceived` melempar sebelum
         * evaluator sempat berjalan. Akibatnya tekanan juri dijawab 500,
         * barisnya tersimpan tapi `score_event_id`-nya kosong selamanya, dan
         * angka di papan berhenti bertambah walau ketiga juri terus menekan.
         *
         * Yang benar: nilai terbit dari basis data, siaran cuma jalan cepat
         * untuk mendorongnya ke panel lain. Kalau jalan cepat itu tertutup,
         * gelanggang tetap harus bisa mencatat skor -- panel menyusul lewat
         * resync begitu tersambung kembali. Pola yang sama sudah dipakai
         * seluruh aksi operator lewat siarkan() di PartaiScoringController.
         */
        $scoreEvent = $ditolak ? null : $this->evaluator->evaluasi($input);

        $this->siarkan(fn () => JudgeInputReceived::dispatch($input));

        if ($scoreEvent !== null) {
            $this->siarkan(fn () => ScoreAwarded::dispatch($scoreEvent));
        }

        return $input->refresh();
    }

    private function siarkan(Closure $penyiar): void
    {
        try {
            $penyiar();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Apakah tekanan ini diterima.
     *
     * Selama satu babak dibuka untuk susulan, HANYA babak itu yang menerima
     * input -- termasuk menolak babak berjalan. Babak berjalan memang sudah
     * dijeda saat susulan dibuka, tapi penolakannya dinyatakan di sini, bukan
     * disandarkan pada efek samping timer: pengendali bisa saja melanjutkannya
     * lagi, dan aturan yang bergantung pada urutan tombol adalah aturan yang
     * suatu saat dilanggar tanpa ada yang menyadarinya.
     *
     * Timer babak susulan tidak pernah jalan dan tidak perlu jalan. Syarat
     * `berjalan()` karena itu DIGANTI seluruhnya oleh syarat "susulan terbuka
     * untuk babak ini", bukan ditambahkan padanya.
     *
     * # Waktu habis, tapi babak belum dijeda
     *
     * `berjalan()` cuma membaca kolom `status`, dan kolom itu tidak berubah
     * sendiri saat jam babak menyentuh nol -- ia baru berubah kalau pengendali
     * menekan Jeda atau Selesaikan babak. Di antara dua saat itu ada jeda
     * beberapa detik yang selalu ada (tangan manusia, dan hitungan mundur yang
     * ditonton dulu sampai habis), dan sepanjang detik-detik itu tombol juri
     * masih tembus: nilai yang lahir setelah waktu resmi berakhir tercatat
     * seolah masih di dalam babak.
     *
     * Yang menutup babak karena itu waktunya sendiri, bukan tombol operator.
     * `habis()` dihitung dari `duration_ms` dikurangi waktu terpakai -- angka
     * yang sama yang dipakai jam di semua panel -- jadi penolakannya jatuh
     * pada detik yang sama dengan angka 00:00 yang dilihat juri.
     *
     * Sengaja TIDAK dipakai di jalur susulan: babak susulan memang dibuka
     * dengan timer yang sudah habis, dan justru itulah gunanya.
     */
    private function diterima(SilatMatch $match, int $babak, ?MatchRound $round): bool
    {
        if ($round === null) {
            return false;
        }

        if ($match->susulan_round !== null) {
            return $match->susulan_round === $babak;
        }

        return $match->current_round === $babak && $round->berjalan() && ! $round->habis();
    }

    private function alasanTolak(SilatMatch $match, int $babak, ?MatchRound $round): string
    {
        return match (true) {
            $round === null => 'Babak ini belum pernah dimulai.',
            $match->susulan_round !== null && $match->susulan_round !== $babak
                => "Babak {$match->susulan_round} sedang dibuka untuk input susulan — hanya babak itu yang menerima nilai.",
            $match->current_round !== $babak => 'Babak ini bukan babak yang sedang berjalan.',
            ! $round->berjalan() => 'Timer babak sedang tidak berjalan.',
            $round->habis() => 'Waktu babak sudah habis.',
            default => 'Ditolak.',
        };
    }
}
