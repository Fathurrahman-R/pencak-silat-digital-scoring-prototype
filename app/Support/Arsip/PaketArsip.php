<?php

namespace App\Support\Arsip;

use App\Models\SilatMatch;
use Illuminate\Support\Facades\DB;

/**
 * Rantai bukti lengkap satu partai, dibekukan jadi satu paket.
 *
 * # Kenapa paket beku, bukan mengandalkan sinkron biasa
 *
 * Sinkron peer-to-peer membawa keadaan TERKINI sebuah baris. Protes
 * menanyakan hal lain: keadaan saat partai disahkan. Keduanya kebetulan sama
 * selama tidak ada yang berubah sesudahnya, dan justru perkara yang
 * disengketakan adalah perkara yang berubah sesudahnya.
 *
 * Dan ada satu tabel yang tidak ikut sinkron sama sekali: judge_inputs.
 * Gelanggang tetangga tidak berkepentingan atas penekanan tombol mentah
 * gelanggang lain, dan tabel itu sendirian menyumbang sekitar sembilan puluh
 * persen volume basis data. Padahal justru barisan penekanan tombol itulah
 * yang ditanyakan saat hasil digugat: siapa menekan apa, pada milidetik
 * keberapa, dan berapa juri yang sepakat.
 *
 * Paket ini satu-satunya salinan bukti itu di luar laptop tempat ia lahir.
 * Karena itu pemangkasan judge_inputs tidak boleh berjalan sebelum tanda
 * terimanya kembali.
 *
 * # Kenapa gzip
 *
 * Riwayat satu partai berisi ratusan sampai ribuan baris judge_inputs yang
 * sebagian besar kolomnya berulang -- match_id yang sama, jenis serangan dari
 * daftar tiga, sudut dari daftar dua. JSON mentahnya besar dan hampir
 * seluruhnya pengulangan. Yang dikirim lewat LAN gelanggang sebaiknya sekecil
 * mungkin: jaringan itu juga yang membawa tekanan tombol juri.
 */
class PaketArsip
{
    /**
     * Tabel yang menyusun rantai bukti, beserta kolom yang menautkannya ke
     * partai.
     *
     * Ditulis mendatar, bukan diturunkan dari PetaSinkron: yang ini soal
     * "apa yang dibutuhkan untuk menjawab protes", yang itu soal "apa yang
     * perlu diketahui gelanggang lain". Keduanya kebetulan mirip sekarang,
     * dan menyatukannya berarti perubahan pada salah satunya diam-diam
     * mengubah yang lain.
     *
     * @var array<string, string>
     */
    private const LANGSUNG = [
        'judge_inputs' => 'match_id',
        'score_events' => 'match_id',
        'penalties' => 'match_id',
        'technical_counts' => 'match_id',
        'match_rounds' => 'match_id',
        'match_round_reopens' => 'match_id',
        'match_officials' => 'match_id',
        'judge_verifications' => 'match_id',
        'protest_cards' => 'match_id',
        'var_reviews' => 'match_id',
        'manager_protests' => 'match_id',
    ];

    /**
     * @return array{
     *     versi: int, node: string, partai: string, turnamen: ?int,
     *     dibekukan_pada: string, tabel: array<string, list<array<string, mixed>>>,
     * }
     */
    public function bangun(SilatMatch $match): array
    {
        $tabel = [];

        foreach (self::LANGSUNG as $nama => $kolom) {
            $tabel[$nama] = array_map(
                static fn ($baris) => (array) $baris,
                DB::table($nama)->where($kolom, $match->id)->orderBy('id')->get()->all(),
            );
        }

        /*
         * Jawaban verifikasi tidak menyebut partai sama sekali -- ia menempel
         * ke verifikasinya. Diambil lewat verifikasi yang sudah terkumpul di
         * atas, bukan lewat kueri kedua ke tabel partai.
         */
        $idVerifikasi = array_column($tabel['judge_verifications'], 'id');

        $tabel['judge_verification_answers'] = $idVerifikasi === [] ? [] : array_map(
            static fn ($baris) => (array) $baris,
            DB::table('judge_verification_answers')
                ->whereIn('judge_verification_id', $idVerifikasi)
                ->orderBy('id')->get()->all(),
        );

        return [
            'versi' => 1,
            'node' => (string) config('sinkron.node'),
            'partai' => (string) $match->id,
            'turnamen' => $match->bracket?->weightClass?->tournament_id,
            'dibekukan_pada' => now()->toIso8601String(),
            'partai_baris' => (array) DB::table('matches')->where('id', $match->id)->first(),
            'tabel' => $tabel,
        ];
    }

    /** Paket yang sudah dipadatkan, siap dikirim atau disimpan. */
    public function padatkan(array $paket): string
    {
        return gzencode(json_encode($paket, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 6);
    }

    public function bacaKembali(string $padat): array
    {
        return json_decode(gzdecode($padat), true);
    }

    /**
     * Sidik jari paket.
     *
     * Dihitung atas isi yang SUDAH dipadatkan, bukan atas JSON mentahnya:
     * itulah bita yang benar-benar berpindah dan yang benar-benar disimpan.
     * Menghitungnya atas bentuk lain berarti tanda terima menjamin sesuatu
     * yang tidak persis sama dengan yang tersimpan di node arsip.
     */
    public function checksum(string $padat): string
    {
        return hash('sha256', $padat);
    }

    /** @param  array<string, list<array<string, mixed>>>  $tabel */
    public function jumlahBaris(array $paket): int
    {
        return array_sum(array_map('count', $paket['tabel'] ?? []));
    }
}
