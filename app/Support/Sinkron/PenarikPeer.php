<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Menarik satu potongan perubahan dari satu peer, lalu menerapkannya.
 *
 * # Kenapa satu potongan, bukan sampai habis
 *
 * Mesin gelanggang tidak menjalankan pekerja antrean saat hari-H, jadi
 * penarikan berjalan di dalam permintaan HTTP biasa. Satu permintaan yang
 * menahan proses php-cgi selama semenit adalah satu proses yang tidak
 * melayani tekanan tombol juri selama semenit -- dan hanya ada delapan.
 *
 * Perulangannya digerakkan browser: ia memanggil ini berkali-kali sampai
 * peer bilang selesai, dengan bilah kemajuan yang bergerak di antaranya.
 * Kalau operator menutup halaman di tengah, yang tertinggal cuma kursor yang
 * belum sampai ujung, dan penarikan berikutnya melanjutkannya.
 *
 * # Kenapa kursor disimpan SETELAH penerapan berhasil
 *
 * Kursor yang maju lebih dulu berarti perubahan yang gagal diterapkan
 * dianggap sudah masuk, dan tidak akan pernah ditarik lagi. Yang hilang
 * tidak menimbulkan galat apa pun -- ia baru ketahuan saat rekap medali
 * disusun dan angkanya tidak cocok antar laptop.
 */
class PenarikPeer
{
    public function __construct(private readonly PenerapPaket $penerap) {}

    /**
     * @return array{
     *     peer: string, kursor: int, selesai: bool,
     *     diterapkan: int, dihapus: int, ditolak: int, dilewati: int,
     * }
     */
    public function tarikSatuPotongan(string $namaPeer): array
    {
        $peer = $this->cariPeer($namaPeer);
        $kursor = $this->kursorSekarang($namaPeer);

        $balasan = Http::timeout(20)
            ->withHeaders(['X-Sinkron-Token' => $peer['token']])
            ->acceptJson()
            ->get($peer['url'].'/sinkron/paket', ['sejak' => $kursor]);

        if ($balasan->failed()) {
            $this->catatGalat($namaPeer, "HTTP {$balasan->status()}");

            throw new RuntimeException("Peer {$namaPeer} menjawab HTTP {$balasan->status()}.");
        }

        $paket = $balasan->json();

        if (! is_array($paket) || ! array_key_exists('kursor', $paket)) {
            $this->catatGalat($namaPeer, 'Balasan bukan paket sinkron.');

            throw new RuntimeException("Balasan peer {$namaPeer} bukan paket sinkron.");
        }

        $ringkasan = $this->penerap->terapkan($paket);

        $this->majukanKursor($namaPeer, (int) $paket['kursor'], $ringkasan['diterapkan'] + $ringkasan['dihapus']);

        return [
            'peer' => $namaPeer,
            'kursor' => (int) $paket['kursor'],
            'selesai' => (bool) ($paket['selesai'] ?? true),
            ...$ringkasan,
        ];
    }

    /** @return array{nama: string, url: string, token: string} */
    private function cariPeer(string $nama): array
    {
        foreach ((array) config('sinkron.peer', []) as $peer) {
            if (($peer['nama'] ?? null) === $nama) {
                return $peer;
            }
        }

        throw new RuntimeException("Peer {$nama} tidak ada di daftar konfigurasi.");
    }

    private function kursorSekarang(string $peer): int
    {
        return (int) (DB::table('sinkron_kursor')->where('peer', $peer)->value('kursor_terakhir') ?? 0);
    }

    private function majukanKursor(string $peer, int $kursor, int $baris): void
    {
        DB::table('sinkron_kursor')->upsert([[
            'peer' => $peer,
            'kursor_terakhir' => $kursor,
            'ditarik_pada' => now(),
            'baris_diterapkan' => $baris,
            'galat_terakhir' => null,
        ]], ['peer'], ['kursor_terakhir', 'ditarik_pada', 'baris_diterapkan', 'galat_terakhir']);
    }

    private function catatGalat(string $peer, string $pesan): void
    {
        DB::table('sinkron_kursor')->upsert([[
            'peer' => $peer,
            'kursor_terakhir' => $this->kursorSekarang($peer),
            'ditarik_pada' => now(),
            'baris_diterapkan' => 0,
            'galat_terakhir' => mb_substr($pesan, 0, 255),
        ]], ['peer'], ['ditarik_pada', 'galat_terakhir']);
    }
}
