<?php

namespace App\Support\Arsip;

use App\Models\SilatMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim paket arsip ke node global, dan mengingat apa yang belum sampai.
 *
 * # Kenapa antrean, bukan langsung kirim saja
 *
 * Node global bisa mati, kabelnya bisa tersenggol, dan itu tidak boleh
 * menghentikan pertandingan. Pengesahan partai harus tetap berhasil walau
 * arsipnya belum terkirim -- yang tertinggal cukup satu baris antrean yang
 * disapu ulang nanti.
 *
 * Kebalikannya jauh lebih buruk: pengesahan yang gagal karena jaringan berarti
 * dewan juri tidak bisa mengesahkan hasil, dan gelanggang berhenti menunggu
 * laptop yang tidak ada hubungannya dengan pertandingan yang sedang berjalan.
 *
 * # Kenapa percobaan pertamanya di dalam request pengesahan
 *
 * Mesin gelanggang tidak menjalankan pekerja antrean saat hari-H. Pengesahan
 * terjadi sekali per partai -- bukan jalur panas seperti tekanan tombol juri --
 * jadi satu perjalanan HTTP bertimeout pendek di sana masih pantas, dan
 * imbalannya besar: arsip mendarat di node global dalam hitungan detik setelah
 * partai usai, bukan menunggu ada yang ingat menekan tombol.
 *
 * # Kenapa checksum node global yang dipercaya, bukan milik sendiri
 *
 * Tanda terima memuat checksum yang DIHITUNG ULANG node global atas berkas
 * yang benar-benar ia simpan. Mempercayai checksum kiriman sendiri berarti
 * tanda terima cuma menjamin "saya sudah mengirim sesuatu", bukan "yang
 * tersimpan di sana sama dengan yang ada di sini" -- dan yang kedua itulah
 * satu-satunya alasan pemangkasan judge_inputs boleh dijalankan.
 */
class PendorongArsip
{
    public const MENUNGGU = 'menunggu';

    public const DITERIMA = 'diterima';

    public const GAGAL = 'gagal';

    public function __construct(private readonly PaketArsip $paket) {}

    /**
     * Mencatat bahwa partai ini perlu diarsipkan, lalu mencoba sekali.
     *
     * Kegagalan tidak pernah dilempar ke pemanggil: yang memanggilnya adalah
     * jalur pengesahan hasil, dan hasil yang sudah diputuskan dewan juri tidak
     * boleh batal karena satu laptop tidak menjawab.
     */
    public function antrekan(SilatMatch $match): void
    {
        DB::table('arsip_keluar')->upsert([[
            'match_id' => (string) $match->id,
            'status' => self::MENUNGGU,
            'percobaan' => 0,
            'checksum' => null,
            'jumlah_baris' => 0,
            'ukuran_bita' => 0,
            'dikirim_pada' => null,
            'diterima_pada' => null,
            'galat_terakhir' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['match_id'], ['status', 'updated_at']);

        try {
            $this->dorong($match);
        } catch (Throwable $e) {
            // Sudah tercatat di arsip_keluar oleh dorong(); di sini cukup
            // dipastikan tidak merambat ke jalur pengesahan.
            Log::warning('Arsip partai belum terkirim', [
                'partai' => $match->id,
                'sebab' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{terkirim: bool, checksum: ?string, pesan: ?string}
     */
    public function dorong(SilatMatch $match): array
    {
        $global = $this->nodeGlobal();

        if ($global === null) {
            $this->catat($match, self::GAGAL, galat: 'Node global belum terdaftar di SINKRON_PEER.');

            return ['terkirim' => false, 'checksum' => null, 'pesan' => 'Node global belum terdaftar.'];
        }

        $isi = $this->paket->bangun($match);
        $padat = $this->paket->padatkan($isi);
        $checksum = $this->paket->checksum($padat);

        try {
            $balasan = Http::timeout(20)
                ->withHeaders([
                    'X-Sinkron-Token' => $global['token'],
                    'X-Arsip-Checksum' => $checksum,
                    'Content-Type' => 'application/gzip',
                ])
                ->withBody($padat, 'application/gzip')
                ->post($global['url'].'/sinkron/arsip?partai='.urlencode((string) $match->id));
        } catch (Throwable $e) {
            $this->catat($match, self::GAGAL, galat: $e->getMessage(), checksum: $checksum,
                baris: $this->paket->jumlahBaris($isi), ukuran: strlen($padat));

            throw $e;
        }

        if ($balasan->failed()) {
            $this->catat($match, self::GAGAL, galat: "HTTP {$balasan->status()}", checksum: $checksum,
                baris: $this->paket->jumlahBaris($isi), ukuran: strlen($padat));

            return ['terkirim' => false, 'checksum' => $checksum, 'pesan' => "Node global menjawab HTTP {$balasan->status()}."];
        }

        $diterima = (string) ($balasan->json('checksum') ?? '');

        /*
         * Checksum yang tidak cocok berarti yang tersimpan di sana BUKAN yang
         * dikirim dari sini. Ditandai gagal, bukan diterima -- kalau tidak,
         * pemangkasan judge_inputs akan membuang bukti dengan bersandar pada
         * salinan yang berbeda isinya.
         */
        if (! hash_equals($checksum, $diterima)) {
            $this->catat($match, self::GAGAL, galat: 'Checksum node global tidak cocok.', checksum: $checksum,
                baris: $this->paket->jumlahBaris($isi), ukuran: strlen($padat));

            return ['terkirim' => false, 'checksum' => $checksum, 'pesan' => 'Checksum tidak cocok.'];
        }

        $this->catat($match, self::DITERIMA, checksum: $checksum,
            baris: $this->paket->jumlahBaris($isi), ukuran: strlen($padat), diterima: true);

        return ['terkirim' => true, 'checksum' => $checksum, 'pesan' => null];
    }

    /**
     * Menyapu partai yang arsipnya belum sampai.
     *
     * @return array{dicoba: int, berhasil: int}
     */
    public function sapu(int $batas = 5): array
    {
        $tertunda = DB::table('arsip_keluar')
            ->whereIn('status', [self::MENUNGGU, self::GAGAL])
            ->orderBy('updated_at')
            ->limit($batas)
            ->pluck('match_id');

        $berhasil = 0;

        foreach ($tertunda as $matchId) {
            $match = SilatMatch::find($matchId);

            if ($match === null) {
                continue;
            }

            try {
                $berhasil += $this->dorong($match)['terkirim'] ? 1 : 0;
            } catch (Throwable) {
                // Sudah tercatat; sapuan berikutnya mencobanya lagi.
            }
        }

        return ['dicoba' => $tertunda->count(), 'berhasil' => $berhasil];
    }

    /** @return array{nama: string, url: string, token: string}|null */
    private function nodeGlobal(): ?array
    {
        foreach ((array) config('sinkron.peer', []) as $peer) {
            if (($peer['peran'] ?? null) === 'global' || ($peer['nama'] ?? null) === 'global') {
                return $peer;
            }
        }

        return null;
    }

    private function catat(
        SilatMatch $match,
        string $status,
        ?string $galat = null,
        ?string $checksum = null,
        int $baris = 0,
        int $ukuran = 0,
        bool $diterima = false,
    ): void {
        DB::table('arsip_keluar')->upsert([[
            'match_id' => (string) $match->id,
            'status' => $status,
            'percobaan' => 1,
            'checksum' => $checksum,
            'jumlah_baris' => $baris,
            'ukuran_bita' => $ukuran,
            'dikirim_pada' => now(),
            'diterima_pada' => $diterima ? now() : null,
            'galat_terakhir' => $galat === null ? null : mb_substr($galat, 0, 255),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['match_id'], [
            'status', 'checksum', 'jumlah_baris', 'ukuran_bita',
            'dikirim_pada', 'diterima_pada', 'galat_terakhir', 'updated_at',
        ]);

        /*
         * Penghitung percobaan dinaikkan terpisah, bukan lewat upsert: nilai
         * yang bergantung pada isi lamanya tidak bisa dititipkan ke sana --
         * baris yang baru disisipkan tidak punya isi lama untuk ditambahi.
         * Angka ini yang membedakan "gagal sekali karena kabel tersenggol"
         * dari "gagal dua puluh kali karena alamatnya memang salah".
         */
        DB::table('arsip_keluar')->where('match_id', $match->id)->increment('percobaan');
    }
}
