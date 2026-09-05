<?php

namespace App\Support\Scoring;

use App\Enums\JenisSerangan;
use App\Models\SilatMatch;

/**
 * Angka partai yang sudah dihitung, dibaca tanpa menghitung ulang.
 *
 * # Kenapa ada
 *
 * Endpoint state ditarik ulang tiap nilai terbit, oleh setiap panel yang
 * sedang terbuka, ditambah sekali tiap dua puluh detik selama babak berjalan.
 * Tiga dari query-nya -- dua untuk rekap skor, satu untuk rincian teknik --
 * menghitung ulang hal yang persis sama, dari baris yang persis sama, sampai
 * ada yang menekan tombol berikutnya. Di antara dua tekanan itu jawabannya
 * tidak mungkin berubah.
 *
 * # Kenapa ini tidak melanggar TandingScoreCalculator:12-15
 *
 * Naskah di sana menolak skor tersimpan dengan alasan yang benar: pembatalan
 * satu baris oleh dewan juri harus langsung berlaku, tanpa ada angka lain di
 * tempat lain yang perlu ikut disunting. Kelas ini tidak menyunting angka
 * apa pun. Ia menyimpan hasil hitungan kalkulator apa adanya, lalu
 * MEMBUANGNYA begitu ada nilai atau hukuman yang berubah -- lihat
 * App\Observers\SnapshotSkorObserver. Pembacaan sesudah itu menghitung ulang
 * dari nol.
 *
 * Kalkulator tetap satu-satunya yang tahu cara menyusun skor. Kalau isi
 * snapshot pernah berbeda dari hitungan segar, yang salah snapshot-nya, dan
 * `silat:snapshot-skor --bangun-ulang` membuangnya. Test menjaga keduanya
 * tetap sama.
 *
 * # Kenapa malas, bukan ditulis saat nilai terbit
 *
 * Menulis snapshot di jalur penerbitan nilai berarti menambah pekerjaan tepat
 * di tempat yang paling sempit: transaksi yang sedang memegang row lock partai
 * sementara tekanan tombol juri berikutnya mengantre di belakangnya. Yang
 * ditambahkan di sana cuma mengosongkan satu penanda. Hitungannya dikerjakan
 * pembaca pertama sesudahnya, di luar kunci.
 */
class SnapshotSkor
{
    public function __construct(private readonly TandingScoreCalculator $kalkulator) {}

    /**
     * Angka partai, dari snapshot kalau masih sahih, dihitung ulang kalau tidak.
     *
     * @return array{
     *     total: array{merah: int, biru: int},
     *     babak: array<int, array{merah: int, biru: int}>,
     *     teknik: array{merah: array<string, int>, biru: array<string, int>},
     * }
     */
    public function baca(SilatMatch $match): array
    {
        if ($match->snapshot_pada !== null && is_array($match->snapshot_skor)) {
            return $this->bentukkan($match->snapshot_skor);
        }

        return $this->hitungDanSimpan($match);
    }

    /**
     * Menandai snapshot tidak lagi bisa dipercaya.
     *
     * Tidak menghitung apa pun. Dipanggil dari observer, yang berjalan di
     * dalam transaksi penerbitan nilai -- menghitung ulang di sana berarti
     * mengerjakan pekerjaan pembaca sambil memegang kunci partai.
     */
    public function batalkan(SilatMatch $match): void
    {
        if ($match->snapshot_pada === null) {
            return;
        }

        $match->forceFill(['snapshot_pada' => null])->saveQuietly();
    }

    /** Menghitung ulang dan menyimpannya, apa pun keadaan snapshot sekarang. */
    public function bangunUlang(SilatMatch $match): array
    {
        return $this->hitungDanSimpan($match);
    }

    private function hitungDanSimpan(SilatMatch $match): array
    {
        $rekap = $this->kalkulator->rekapSkor($match);

        $isi = [
            'total' => $rekap['total'],
            'babak' => $rekap['babak'],
            'teknik' => $this->kalkulator->rekapTeknik($match),
        ];

        /*
         * saveQuietly, bukan save: menulis snapshot bukan perubahan pada
         * partainya. Membiarkannya menembakkan event `saved` berarti tiap
         * PEMBACAAN skor terlihat seperti partai yang baru saja disunting --
         * dan observer mana pun yang mendengarkan akan ikut berjalan, termasuk
         * yang membatalkan snapshot ini sendiri.
         */
        $match->forceFill([
            'snapshot_skor' => $isi,
            'snapshot_pada' => now(),
        ])->saveQuietly();

        return $isi;
    }

    /**
     * Mengembalikan bentuk yang persis sama dengan hasil hitungan segar.
     *
     * json_decode mengembalikan kunci babak sebagai string dan angkanya bisa
     * saja terbaca sebagai string juga. Pemanggil membandingkan angka dan
     * mengindeks babak dengan integer; menyerahkan bentuk yang berbeda-beda
     * tergantung dari mana datanya berarti bug yang cuma muncul saat cache
     * kebetulan panas.
     *
     * Rincian teknik disusun ulang mengikuti urutan JenisSerangan, bukan
     * urutan yang datang dari JSON. MySQL menyimpan kunci objek JSON secara
     * terurut sendiri -- jatuhan, pukulan, tendangan -- sementara kalkulator
     * menghasilkannya dalam urutan naskah: pukulan, tendangan, jatuhan. Tanpa
     * penyusunan ulang di sini, rincian serangan di papan hasil berpindah
     * urutan begitu snapshot-nya panas, dan kembali lagi tiap kali ada nilai
     * baru. Sumber urutannya harus enum, bukan kebetulan penyimpanan.
     */
    private function bentukkan(array $tersimpan): array
    {
        $babak = [];

        foreach ($tersimpan['babak'] ?? [] as $nomor => $angka) {
            $babak[(int) $nomor] = [
                'merah' => (int) ($angka['merah'] ?? 0),
                'biru' => (int) ($angka['biru'] ?? 0),
            ];
        }

        $teknik = [];

        foreach (['merah', 'biru'] as $sisi) {
            $teknik[$sisi] = [];

            foreach (JenisSerangan::cases() as $jenis) {
                $teknik[$sisi][$jenis->value] = (int) ($tersimpan['teknik'][$sisi][$jenis->value] ?? 0);
            }
        }

        return [
            'total' => [
                'merah' => (int) ($tersimpan['total']['merah'] ?? 0),
                'biru' => (int) ($tersimpan['total']['biru'] ?? 0),
            ],
            'babak' => $babak,
            'teknik' => $teknik,
        ];
    }
}
