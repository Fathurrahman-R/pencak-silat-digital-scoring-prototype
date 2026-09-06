<?php

namespace App\Http\Controllers;

use App\Support\Arsip\PenerimaArsip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Penerima arsip bukti di node global.
 *
 * Hidup di kelompok rute sinkron, dijaga token bersama dan pembatasan jaringan
 * lokal -- yang mengetuk laptop gelanggang, bukan orang.
 *
 * Seluruh endpoint di sini menolak bekerja kalau node ini bukan node global.
 * Laptop gelanggang yang salah dikonfigurasi lalu menerima arsip gelanggang
 * lain akan menyimpan bukti di tempat yang tidak pernah dicari siapa pun saat
 * dibutuhkan.
 */
class ArsipController extends Controller
{
    public function __construct(private readonly PenerimaArsip $penerima) {}

    public function terima(Request $request): JsonResponse
    {
        $this->pastikanNodeGlobal();

        $partai = (string) $request->query('partai', '');

        if ($partai === '') {
            return response()->json(['pesan' => 'Partai tidak disebutkan.'], 422);
        }

        $padat = $request->getContent();

        if ($padat === '') {
            return response()->json(['pesan' => 'Paket kosong.'], 422);
        }

        try {
            $hasil = $this->penerima->terima($partai, $padat, $request->header('X-Arsip-Checksum'));
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json($hasil, 201);
    }

    /**
     * Tanda terima versi terakhir sebuah partai.
     *
     * Ditanyakan gelanggang tepat sebelum memangkas riwayat juri. Balasan 404
     * di sini berarti pemangkasan TIDAK boleh berjalan -- dan itu jawaban yang
     * benar, bukan kegagalan.
     */
    public function tandaTerima(string $match): JsonResponse
    {
        $this->pastikanNodeGlobal();

        $tanda = $this->penerima->tandaTerima($match);

        if ($tanda === null) {
            return response()->json(['pesan' => 'Belum ada arsip untuk partai ini.'], 404);
        }

        return response()->json($tanda);
    }

    private function pastikanNodeGlobal(): void
    {
        abort_unless(config('sinkron.peran') === 'global', 404);
    }
}
