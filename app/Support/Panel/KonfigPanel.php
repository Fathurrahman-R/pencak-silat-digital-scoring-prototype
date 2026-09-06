<?php

namespace App\Support\Panel;

use App\Models\Arena;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;

/**
 * Alamat yang dipegang panel gelanggang.
 *
 * Dipecah jadi dua bagian dengan alasan yang menentukan bentuk seluruh panel
 * per-gelanggang:
 *
 *   tetap()  hal yang tidak berubah selama panel terbuka -- gelanggang, mode,
 *            siapa yang memegangnya, dan alamat resync.
 *   aksi()   seluruh alamat aksi SATU partai.
 *
 * Selama panel terikat satu partai, keduanya boleh menyatu -- dan memang
 * begitu bentuknya sejak awal. Begitu panel mengikuti gelanggang, `aksi()`
 * harus bisa dihitung ulang tiap kali pengendali memindahkan jadwal, tanpa
 * memuat ulang halaman dan tanpa memutus koneksi Echo yang sudah tersambung.
 *
 * Nama kuncinya dipertahankan persis seperti sebelumnya. Tidak satu pun
 * pemanggil di Blade maupun JS perlu diubah, dan itu disengaja: refactor yang
 * sekaligus mengganti nama adalah refactor yang tidak bisa dibuktikan setara.
 */
class KonfigPanel
{
    /**
     * @param  string  $mode  'partai' untuk alamat lama yang terikat satu
     *                        partai, 'gelanggang' untuk panel yang mengikuti
     *                        partai aktif gelanggang
     * @return array<string, mixed>
     */
    public function __invoke(
        Tournament $tournament,
        ?SilatMatch $match,
        ?Arena $arena,
        ?User $untuk,
        string $mode = 'partai',
    ): array {
        return $this->tetap($tournament, $arena, $untuk, $mode, $match)
            + ($match !== null ? $this->aksi($tournament, $match) : []);
    }

    /**
     * Bagian yang tidak berubah selama panel terbuka.
     *
     * @return array<string, mixed>
     */
    public function tetap(
        Tournament $tournament,
        ?Arena $arena,
        ?User $untuk,
        string $mode = 'partai',
        ?SilatMatch $match = null,
    ): array {
        return [
            'mode' => $mode,
            'matchId' => $match?->id,
            'arenaId' => $arena?->id ?? $match?->arena_id,
            'arenaNama' => $arena?->name,

            /*
             * Panel perlu tahu ia sedang dipegang siapa, bukan cuma partai
             * apa. Layar verifikasi juri memakainya untuk membedakan "kamu
             * belum menjawab" dari "kamu sudah, tinggal menunggu yang lain" --
             * dua keadaan yang tampilannya harus jauh berbeda supaya juri
             * tidak menekan dua kali.
             */
            'userId' => $untuk?->id,

            'state' => $mode === 'gelanggang' && $arena !== null
                ? route('admin.turnamen.gelanggang.panel.state', [$tournament, $arena])
                : ($match !== null ? route('admin.turnamen.partai.state', [$tournament, $match]) : null),

            /*
             * Hanya panel gelanggang yang punya alamat ini. Klien memeriksa
             * keberadaannya sebelum memanggil, jadi panel per-partai tidak
             * perlu tahu tombol pindah jadwal itu ada.
             */
            'pilihPartai' => $mode === 'gelanggang' && $arena !== null
                ? route('admin.turnamen.gelanggang.panel.partai-aktif', [$tournament, $arena])
                : null,
        ];
    }

    /**
     * Seluruh alamat aksi satu partai.
     *
     * `__ID__` adalah penanda yang diganti klien dengan id baris yang sedang
     * disentuh -- JS tidak pernah menyusun route Laravel sendiri.
     *
     * @return array<string, string>
     */
    public function aksi(Tournament $tournament, SilatMatch $match): array
    {
        $partai = fn (string $nama, mixed ...$ekstra) => route(
            "admin.turnamen.partai.{$nama}",
            [$tournament, $match, ...$ekstra],
        );

        return [
            'timerMulai' => $partai('timer.mulai'),
            'timerJeda' => $partai('timer.jeda'),
            'timerLanjut' => $partai('timer.lanjut'),
            'timerReset' => $partai('timer.reset'),
            'timerSelesai' => $partai('timer.selesai-babak'),
            'akhiri' => $partai('akhiri'),
            'sahkan' => $partai('sahkan'),
            'nilai' => $partai('nilai'),
            'jatuhan' => $partai('jatuhan'),
            'hukuman' => $partai('hukuman'),
            'hitungan' => $partai('hitungan'),
            'nilaiBatal' => $partai('nilai.batal', '__ID__'),
            'hukumanBatal' => $partai('hukuman.batal', '__ID__'),
            'verifikasiMinta' => $partai('verifikasi.minta'),
            'verifikasiJawab' => $partai('verifikasi.jawab', '__ID__'),
            'verifikasiTerapkan' => $partai('verifikasi.terapkan', '__ID__'),
            'verifikasiBatalkan' => $partai('verifikasi.batalkan', '__ID__'),
            'varAjukan' => $partai('keberatan.var.ajukan'),
            'varPutuskan' => $partai('keberatan.var.putuskan', '__ID__'),
            'protesManajerAjukan' => $partai('keberatan.protes-manajer.ajukan'),
            'protesManajerBanding' => $partai('keberatan.protes-manajer.banding', '__ID__'),
            'protesManajerPutuskan' => $partai('keberatan.protes-manajer.putuskan', '__ID__'),
        ];
    }
}
