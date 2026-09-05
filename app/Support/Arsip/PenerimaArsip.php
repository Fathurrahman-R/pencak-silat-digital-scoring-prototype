<?php

namespace App\Support\Arsip;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Menyimpan paket arsip yang datang dari laptop gelanggang.
 *
 * # Kenapa disimpan sebagai berkas, bukan disisipkan ke tabel
 *
 * Node global juga menerima data yang sama lewat sinkron biasa, ke tabelnya
 * sendiri. Kalau arsip ikut ditulis ke tabel itu, keduanya akan saling
 * menimpa dan tidak ada lagi salinan yang bisa disebut "keadaan saat partai
 * disahkan".
 *
 * Berkas beku menjawab pertanyaan yang berbeda dari tabel. Tabel menjawab
 * "bagaimana keadaannya sekarang"; berkas menjawab "apa yang tercatat saat
 * gong terakhir dibunyikan". Protes menanyakan yang kedua.
 *
 * # Kenapa tidak pernah ditimpa
 *
 * Partai yang sama bisa dikirim ulang -- babak susulan membuka kembali babak
 * yang sudah ditutup, dan hasilnya berubah. Menimpa berkas lama berarti
 * menghapus bukti keadaan sebelum perubahan itu, padahal justru perubahan
 * itulah yang paling mungkin dipersoalkan. Kiriman berikutnya jadi versi
 * baru bernomor, dan keduanya disimpan.
 */
class PenerimaArsip
{
    public const DISK = 'local';

    public function __construct(private readonly PaketArsip $paket) {}

    /**
     * @return array{checksum: string, versi: int, jalur: string, jumlah_baris: int}
     */
    public function terima(string $matchId, string $padat, ?string $checksumKiriman = null): array
    {
        /*
         * Checksum dihitung ULANG di sini atas bita yang benar-benar diterima,
         * lalu dibandingkan dengan yang dibawa pengirim. Kalau kiriman rusak di
         * tengah jalan -- LAN gelanggang bukan jaringan yang tenang -- itu
         * ketahuan sekarang, bukan bertahun kemudian saat berkasnya dibuka
         * untuk menjawab gugatan.
         */
        $checksum = $this->paket->checksum($padat);

        if ($checksumKiriman !== null && ! hash_equals($checksum, $checksumKiriman)) {
            throw new RuntimeException('Paket rusak di perjalanan: checksum tidak cocok.');
        }

        $isi = $this->paket->bacaKembali($padat);

        if (! is_array($isi) || ($isi['partai'] ?? null) === null) {
            throw new RuntimeException('Paket bukan arsip partai yang sah.');
        }

        $turnamen = $isi['turnamen'] ?? 0;
        $versi = $this->versiBerikutnya($matchId);
        $jalur = "arsip/{$turnamen}/{$matchId}-v{$versi}.json.gz";

        Storage::disk(self::DISK)->put($jalur, $padat);

        $jumlahBaris = $this->paket->jumlahBaris($isi);

        DB::table('arsip_partai')->insert([
            'match_id' => $matchId,
            'versi' => $versi,
            'node_asal' => (string) ($isi['node'] ?? 'tidak disebut'),
            'checksum' => $checksum,
            'jumlah_baris' => $jumlahBaris,
            'ukuran_bita' => strlen($padat),
            'jalur_berkas' => $jalur,
            'dibekukan_pada' => $isi['dibekukan_pada'] ?? null,
            'diterima_pada' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'checksum' => $checksum,
            'versi' => $versi,
            'jalur' => $jalur,
            'jumlah_baris' => $jumlahBaris,
        ];
    }

    /**
     * Tanda terima versi terakhir sebuah partai, atau null kalau belum pernah
     * sampai.
     *
     * Dipakai gelanggang untuk memastikan sekali lagi sebelum memangkas
     * judge_inputs. Status tersimpan di sisi gelanggang tidak cukup: ia bisa
     * saja mencatat "diterima" untuk berkas yang sesudahnya terhapus di sini.
     *
     * @return array{versi: int, checksum: string, jumlah_baris: int, diterima_pada: string}|null
     */
    public function tandaTerima(string $matchId): ?array
    {
        $baris = DB::table('arsip_partai')
            ->where('match_id', $matchId)
            ->orderByDesc('versi')
            ->first();

        if ($baris === null) {
            return null;
        }

        // Baris tanpa berkasnya bukan tanda terima: yang menjamin bukti masih
        // ada adalah berkasnya, bukan catatan tentang berkasnya.
        if (! Storage::disk(self::DISK)->exists($baris->jalur_berkas)) {
            return null;
        }

        return [
            'versi' => (int) $baris->versi,
            'checksum' => (string) $baris->checksum,
            'jumlah_baris' => (int) $baris->jumlah_baris,
            'diterima_pada' => (string) $baris->diterima_pada,
        ];
    }

    private function versiBerikutnya(string $matchId): int
    {
        return ((int) DB::table('arsip_partai')->where('match_id', $matchId)->max('versi')) + 1;
    }
}
