<?php

namespace App\Console\Commands;

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Models\SilatMatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menumpuk riwayat penilaian sebanyak satu hari pertandingan sungguhan.
 *
 * Basis data pengembangan berisi delapan baris judge_inputs. Di atas angka
 * itu, EXPLAIN selalu menjawab hal yang sama dan selalu terlihat baik: MySQL
 * memindai seluruh tabel karena memang lebih murah daripada membuka index.
 * Artinya keputusan apa pun soal index yang diambil dari basis data
 * pengembangan diambil dari data yang tidak pernah membantah apa pun.
 *
 * Perintah ini menyusun beban yang membantah. Ia tidak melewati jalur
 * konsensus -- ConsensusEvaluator mengunci baris partai tiap tekanan, dan
 * seratus ribu tekanan lewat jalur itu akan makan berjam-jam untuk menghasilkan
 * bentuk data yang sama saja. Yang ditiru bentuknya: berapa baris, tersebar ke
 * berapa partai, berapa juri, dan berapa persen yang akhirnya jadi nilai.
 *
 * Bukan untuk dijalankan di mesin gelanggang.
 */
class BebanUjiCommand extends Command
{
    protected $signature = 'silat:beban
                            {--partai=200 : Berapa partai yang diberi riwayat}
                            {--input=500 : Rata-rata tekanan tombol juri per partai}
                            {--bersihkan : Buang riwayat buatan lebih dulu}';

    protected $description = 'Menumpuk riwayat penilaian sebesar satu hari pertandingan, untuk mengukur perilaku query';

    /**
     * Penanda baris buatan. judge_inputs tidak punya kolom bebas selain ini,
     * dan memakainya berarti pembersihan bisa menyasar tepat baris yang
     * perintah ini buat -- bukan menghapus seluruh tabel dan ikut membawa
     * riwayat sungguhan yang kebetulan ada di mesin yang sama.
     */
    public const PENANDA = 'beban-uji';

    private const POTONGAN = 2000;

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini menulis ratusan ribu baris riwayat palsu. Ia tidak berjalan di production.');

            return self::FAILURE;
        }

        if ($this->option('bersihkan')) {
            $dibuang = DB::table('judge_inputs')->where('rejected_reason', self::PENANDA)->delete();
            $this->info(number_format($dibuang).' baris judge_inputs buatan dibuang.');
        }

        $partai = SilatMatch::query()->limit((int) $this->option('partai'))->pluck('id');

        if ($partai->isEmpty()) {
            $this->error('Belum ada partai di basis data ini. Jalankan `php artisan silat:simulasi` lebih dulu.');

            return self::FAILURE;
        }

        $juri = User::query()->limit(3)->pluck('id');

        if ($juri->count() < 3) {
            $this->error('Butuh minimal tiga user untuk berperan sebagai juri.');

            return self::FAILURE;
        }

        $perPartai = (int) $this->option('input');
        $bar = $this->output->createProgressBar($partai->count());
        $bar->start();

        foreach ($partai as $matchId) {
            $this->isiSatuPartai($matchId, $juri->all(), $perPartai);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Tabel', 'Baris'], collect(['judge_inputs', 'score_events', 'penalties'])
            ->map(fn ($t) => [$t, number_format(DB::table($t)->count())])
            ->all());

        return self::SUCCESS;
    }

    /**
     * Satu partai diisi tiga babak, tiap tekanan diberi stempel waktu yang
     * merambat maju. Stempel yang merambat itu bukan hiasan: judge_inputs
     * di-index lewat server_ts, dan seratus ribu baris berstempel sama akan
     * memberi selektivitas palsu yang membuat pengukurannya tidak berarti.
     */
    private function isiSatuPartai(int|string $matchId, array $juri, int $perPartai): void
    {
        $jenis = JenisSerangan::cases();
        $sudut = [Sudut::Merah->value, Sudut::Biru->value];
        $mulai = Carbon::now()->subDays(random_int(1, 3))->startOfHour();

        $input = [];
        $nilai = [];

        for ($i = 0; $i < $perPartai; $i++) {
            $babak = intdiv($i, max(1, intdiv($perPartai, 3))) + 1;
            $babak = min($babak, 3);
            $ts = $mulai->clone()->addMilliseconds($i * 400)->format('Y-m-d H:i:s.v');
            $serangan = $jenis[array_rand($jenis)];
            $sisi = $sudut[array_rand($sudut)];

            $input[] = [
                'match_id' => $matchId,
                'round' => $babak,
                'judge_user_id' => $juri[array_rand($juri)],
                'corner' => $sisi,
                'point_type' => $serangan->value,
                'server_ts' => $ts,
                'client_ts' => $ts,
                'score_event_id' => null,
                'rejected_reason' => self::PENANDA,
                'created_at' => $ts,
                'updated_at' => $ts,
            ];

            // Kira-kira satu dari dua belas tekanan berbuah nilai -- angka itu
            // sejalan dengan 40-an nilai per partai yang terlihat di data uji.
            if ($i % 12 === 0) {
                $nilai[] = [
                    // Kunci dibangkitkan di sini: penyisipan massal lewat query
                    // builder melewati model, jadi HasUlids tidak berjalan.
                    'id' => (string) Str::ulid(),
                    'match_id' => $matchId,
                    'round' => $babak,
                    'corner' => $sisi,
                    'point_type' => $serangan->value,
                    'value' => $serangan->nilai(),
                    'server_ts' => $ts,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
        }

        foreach (array_chunk($input, self::POTONGAN) as $potongan) {
            DB::table('judge_inputs')->insert($potongan);
        }

        foreach (array_chunk($nilai, self::POTONGAN) as $potongan) {
            DB::table('score_events')->insert($potongan);
        }
    }
}
