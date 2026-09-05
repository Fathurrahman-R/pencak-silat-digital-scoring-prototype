<?php

namespace App\Support\Arsip;

use App\Models\SilatMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Membuang riwayat penekanan tombol juri milik partai yang sudah diarsipkan.
 *
 * # Kenapa hanya judge_inputs
 *
 * Ia sendirian menyumbang sekitar sembilan puluh persen volume basis data,
 * dan ia satu-satunya yang tidak dibutuhkan lagi oleh layar mana pun setelah
 * partai disahkan. score_events, hukuman, verifikasi, dan VAR tetap tinggal:
 * papan hasil dan rekap membacanya, dan membuangnya berarti menukar ruang
 * disk dengan permintaan jaringan ke node global tiap kali panitia membuka
 * hasil partai lama.
 *
 * # Tiga syarat, dan yang ketiga yang paling penting
 *
 * Pemangkasan ini MENGHAPUS BUKTI. Satu-satunya yang membenarkannya adalah
 * keyakinan bahwa salinannya benar-benar ada di tempat lain -- bukan catatan
 * bahwa salinannya pernah dikirim.
 *
 *   1. partai sudah disahkan          hasil yang belum final masih bisa
 *                                     berubah, dan perubahannya butuh
 *                                     riwayatnya.
 *
 *   2. arsipnya tercatat diterima     syarat murah yang menyaring sebagian
 *                                     besar.
 *
 *   3. node global mengonfirmasi      DITANYAKAN LANGSUNG, saat itu juga.
 *      checksum yang sama             Catatan lokal bisa menyebut "diterima"
 *                                     untuk berkas yang sesudahnya terhapus,
 *                                     tertimpa, atau tidak pernah benar-benar
 *                                     tersimpan. Yang menjamin bukti masih ada
 *                                     hanyalah node global yang masih
 *                                     memegangnya sekarang.
 *
 * Ditambah satu penjagaan yang tidak ada hubungannya dengan arsip: partai yang
 * sedang ditayangkan gelanggang mana pun tidak disentuh, walau semua syarat di
 * atas terpenuhi.
 */
class PemangkasRiwayatJuri
{
    /**
     * @return array{diperiksa: int, dipangkas: int, baris_dibuang: int, dilewati: list<string>}
     */
    public function pangkas(int $batas = 50): array
    {
        $kandidat = SilatMatch::query()
            ->whereNotNull('ratified_at')
            ->whereNull('judge_inputs_dipangkas_pada')
            ->whereIn('id', DB::table('arsip_keluar')
                ->where('status', PendorongArsip::DITERIMA)
                ->pluck('match_id'))
            ->whereNotIn('id', DB::table('arenas')
                ->whereNotNull('active_match_id')
                ->pluck('active_match_id'))
            ->orderBy('ratified_at')
            ->limit($batas)
            ->get();

        $dipangkas = 0;
        $baris = 0;
        $dilewati = [];

        foreach ($kandidat as $match) {
            $konfirmasi = $this->konfirmasiNodeGlobal($match);

            if ($konfirmasi !== true) {
                $dilewati[] = "Partai {$match->id}: {$konfirmasi}";

                continue;
            }

            $terhapus = DB::table('judge_inputs')->where('match_id', $match->id)->delete();

            /*
             * Penanda ditulis walau tidak ada baris yang terhapus. Yang
             * dicatatnya bukan "berapa yang dibuang" melainkan "rincian juri
             * partai ini sekarang ada di node arsip" -- dan panel perlu tahu
             * itu untuk mengatakannya kepada yang membuka riwayatnya, alih-alih
             * menampilkan daftar kosong seolah tidak ada yang menekan tombol.
             */
            $match->forceFill(['judge_inputs_dipangkas_pada' => now()])->saveQuietly();

            $dipangkas++;
            $baris += $terhapus;
        }

        return [
            'diperiksa' => $kandidat->count(),
            'dipangkas' => $dipangkas,
            'baris_dibuang' => $baris,
            'dilewati' => $dilewati,
        ];
    }

    /** @return true|string true kalau boleh dipangkas, alasan kalau tidak */
    private function konfirmasiNodeGlobal(SilatMatch $match): true|string
    {
        $global = $this->nodeGlobal();

        if ($global === null) {
            return 'node global belum terdaftar';
        }

        $tercatat = DB::table('arsip_keluar')->where('match_id', $match->id)->first();

        try {
            $balasan = Http::timeout(10)
                ->withHeaders(['X-Sinkron-Token' => $global['token']])
                ->acceptJson()
                ->get($global['url'].'/sinkron/arsip/'.urlencode((string) $match->id).'/tanda-terima');
        } catch (Throwable $e) {
            return 'node global tidak terjangkau ('.$e->getMessage().')';
        }

        if ($balasan->failed()) {
            return "node global menjawab HTTP {$balasan->status()}";
        }

        $checksum = (string) ($balasan->json('checksum') ?? '');

        if ($checksum === '') {
            return 'node global tidak memegang arsip partai ini';
        }

        if (! hash_equals((string) ($tercatat->checksum ?? ''), $checksum)) {
            return 'checksum di node global berbeda dengan yang dikirim dari sini';
        }

        return true;
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
}
