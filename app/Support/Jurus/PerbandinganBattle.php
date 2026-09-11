<?php

namespace App\Support\Jurus;

use App\Models\JurusBattle;
use App\Models\JurusDeduction;
use App\Models\JurusPerformance;

/**
 * Perbandingan nilai kedua sudut satu battle Jurus, siap dibaca panel.
 *
 * Berdiri sendiri dari JurusScoreCalculator: yang itu menjawab "berapa skor
 * satu penampilan", yang ini menjawab "kenapa yang satu menang atas yang lain".
 * Pertanyaan kedua butuh keduanya berdampingan, dan butuh selisihnya --
 * angka yang tidak berarti apa-apa untuk satu penampilan sendirian.
 *
 * # Kenapa perbandingannya perlu ditampilkan sama sekali
 *
 * Skor akhir Jurus adalah median enam juri dikurangi pengurangan. "9.72 lawan
 * 9.70" tidak menjelaskan apa pun sampai pembacanya tahu apakah bedanya datang
 * dari penilaian juri atau dari satu pengurangan 0.50 yang dijatuhkan
 * Pengawas. Selisih dua sentimeter di kertas bisa berarti dua hal yang sangat
 * berbeda, dan pelatih yang mengangkat kartu protes menanyakan persis itu.
 */
class PerbandinganBattle
{
    public function __construct(private readonly JurusScoreCalculator $kalkulator) {}

    /**
     * @return array{
     *     battle: array{id: int, status: string, selesai: bool, win_reason: ?string, winner_registration_id: ?int},
     *     merah: array<string, mixed>|null,
     *     biru: array<string, mixed>|null,
     *     selisih: float|null,
     *     siap: bool,
     *     seri: bool,
     * }
     */
    public function __invoke(JurusBattle $battle): array
    {
        $battle->loadMissing([
            'performances.scores.juri',
            'performances.deductions.pencatat',
            'performances.registration.athletes',
            'performances.registration.contingent',
        ]);

        $sisi = fn (string $sudut) => $this->sisi(
            $battle->performances->firstWhere('sudut', $sudut),
        );

        $merah = $sisi('merah');
        $biru = $sisi('biru');

        return [
            'battle' => [
                'id' => $battle->id,
                'status' => $battle->status,
                'selesai' => $battle->selesai(),
                'win_reason' => $battle->win_reason,
                'winner_registration_id' => $battle->winner_registration_id,
            ],
            'merah' => $merah,
            'biru' => $biru,
            /*
             * Selisih hanya berarti kalau kedua sisi sudah punya angka.
             * Menampilkan "9.72 − 0.00 = 9.72" saat sudut kedua belum tampil
             * membuat pembacanya mengira battle-nya sudah timpang sejauh itu.
             */
            'selisih' => $merah !== null && $biru !== null
                ? round(abs($merah['akhir'] - $biru['akhir']), 2)
                : null,

            /*
             * `siap` menjawab pertanyaan TAMPILAN: kapan blok perbandingan
             * pantas digambar sendiri di papan, panel juri, panel ketua, dan
             * panel operator.
             *
             * Syaratnya kedua sudut sudah DISAHKAN, bukan sekadar sudah tampil.
             * Nilai yang belum disahkan masih bisa berubah -- pengurangan
             * Pengawas dijatuhkan sesudah penampilan berhenti, dan pembatalan
             * pengurangan mengubah angkanya lagi. Perbandingan yang muncul
             * lebih awal akan berganti angka di depan penonton, dan yang
             * membacanya tidak punya cara tahu mana yang final.
             *
             * Sengaja TERPISAH dari syarat domain di PutuskanBattle: pemenang
             * battle tidak boleh bergantung pada apa yang kebetulan sedang
             * digambar di layar.
             */
            'siap' => $merah !== null && $biru !== null
                && $merah['disahkan'] && $biru['disahkan'],

            /*
             * Seri dinyatakan, bukan disimpulkan pembacanya dari dua angka
             * yang kebetulan sama. Panel memakainya untuk menampilkan pilihan
             * sudut milik Ketua Pertandingan alih-alih tombol tetapkan biasa.
             */
            'seri' => $merah !== null && $biru !== null
                && round($merah['akhir'], 2) === round($biru['akhir'], 2),
        ];
    }

    /** @return array<string, mixed>|null */
    private function sisi(?JurusPerformance $performance): ?array
    {
        if ($performance === null) {
            return null;
        }

        $berlaku = $performance->deductions->whereNull('voided_at');

        return [
            'performance_id' => $performance->id,
            'registration_id' => $performance->registration_id,
            'nama' => $performance->registration->athletes->pluck('name')->implode(', '),
            'kontingen' => $performance->registration->contingent->name,
            'tahap' => $performance->tahap,
            'durasi_ms' => $performance->duration_ms,
            'didiskualifikasi' => $performance->didiskualifikasi,
            'disahkan' => $performance->disahkan(),

            /*
             * Nilai TIAP juri, bukan cuma mediannya.
             *
             * Median menyembunyikan sebaran: enam juri yang semuanya menilai
             * 9.70 dan enam juri yang menilai antara 9.40 dan 10.00
             * menghasilkan angka akhir yang sama persis. Yang kedua adalah
             * penilaian yang pantas ditanyakan, dan pertanyaannya tidak bisa
             * diajukan kalau angkanya tidak pernah terlihat.
             */
            'nilai_juri' => $performance->scores
                ->sortBy('judge_user_id')
                ->values()
                ->map(fn ($s) => ['nama' => $s->juri?->name, 'value' => (float) $s->value])
                ->all(),

            'median' => $this->kalkulator->median($performance),

            /*
             * Pengurangan dipisah menurut siapa yang menjatuhkannya.
             *
             * 0.01 dijatuhkan juri untuk kesalahan rincian gerak; 0.50
             * dijatuhkan Pengawas untuk pelanggaran waktu, keluar gelanggang,
             * dan pakaian (Pasal 12.1.e). Menjumlahkannya jadi satu angka
             * menghapus perbedaan yang justru paling sering disengketakan.
             */
            'pengurangan_juri' => (float) $berlaku->where('tier', JurusDeduction::TIER_JURI)->sum('jumlah'),
            'pengurangan_pengawas' => (float) $berlaku->where('tier', JurusDeduction::TIER_PENGAWAS)->sum('jumlah'),
            'pengurangan_total' => $this->kalkulator->totalPengurangan($performance),

            'akhir' => $this->kalkulator->skorAkhir($performance),
        ];
    }
}
