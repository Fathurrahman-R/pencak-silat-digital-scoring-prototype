<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Facades\DB;

/**
 * Baris ini milik node yang mana.
 *
 * # Kenapa satu pertanyaan ini menggantikan resolusi konflik
 *
 * Sinkron peer-to-peer tanpa aturan kepemilikan menuntut jawaban atas
 * pertanyaan yang tidak punya jawaban benar: kalau dua node mengubah baris
 * yang sama, mana yang menang? Semua jawabannya membuang pekerjaan seseorang,
 * dan yang paling sering dipakai -- stempel waktu terbaru -- membuangnya
 * berdasarkan jam laptop yang tidak pernah benar-benar sama.
 *
 * Sistem ini tidak menjawabnya. Ia membuat keadaannya tidak bisa terjadi:
 * tiap baris punya tepat satu node yang boleh menulisnya, dan node lain
 * menerimanya read-only. Kelas ini yang tahu node mana itu.
 *
 * Dipakai di dua arah, dan keduanya perlu:
 *
 *   mengekspor -- node hanya mengirim baris miliknya sendiri. Tanpa ini,
 *   gelanggang A meneruskan salinan basi milik gelanggang B kepada C, dan C
 *   tidak punya cara tahu mana yang lebih baru.
 *
 *   mengimpor -- node menolak baris yang ia sendiri miliki. Inilah yang
 *   memutus lingkaran: perubahan tidak pernah kembali ke pembuatnya lewat
 *   jalan memutar, sudah basi, dan menimpa yang asli.
 */
class Kepemilikan
{
    /**
     * Kode arena yang dipegang node ini.
     *
     * @var list<string>
     */
    private readonly array $arena;

    /** @var array<string, ?string> */
    private array $ingatan = [];

    public function __construct()
    {
        $this->arena = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('sinkron.arena')),
        )));
    }

    public function nodeGlobal(): bool
    {
        return config('sinkron.peran') === 'global';
    }

    public function namaNode(): string
    {
        return (string) config('sinkron.node');
    }

    /**
     * Apakah baris ini ditulis oleh node ini.
     *
     * @param  array<string, mixed>  $baris
     */
    public function milikNodeIni(string $tabel, array $baris): bool
    {
        if (in_array($tabel, PetaSinkron::GLOBAL, true)) {
            return $this->nodeGlobal();
        }

        if (in_array($tabel, PetaSinkron::PENGHUBUNG, true)) {
            /*
             * Baris penghubung disisipkan node global, tapi diperbarui node
             * gelanggang selama partai berjalan. Yang berhak mengirimkan
             * keadaan terkininya adalah yang memegang gelanggangnya -- kecuali
             * baris itu belum dijadwalkan ke gelanggang mana pun, dan waktu itu
             * hanya node global yang tahu apa-apa tentangnya.
             */
            $kode = $this->kodeArenaDariId($baris['arena_id'] ?? null);

            return $kode === null
                ? $this->nodeGlobal()
                : $this->memegangArena($kode);
        }

        if (! isset(PetaSinkron::LOKAL[$tabel])) {
            return false;
        }

        $kode = $this->telusuriArena($tabel, $baris);

        /*
         * Baris lokal yang partainya belum ditempatkan di gelanggang mana pun
         * tidak bisa lahir: nilai, hukuman, dan timer semuanya butuh partai
         * yang sedang ditayangkan. Kalau toh ada, ia tidak diklaim siapa pun --
         * lebih baik tertinggal daripada diklaim node yang salah lalu menimpa
         * catatan gelanggang yang sesungguhnya menjalankannya.
         */
        return $kode !== null && $this->memegangArena($kode);
    }

    /**
     * Apakah node ini berhak MENGIRIMKAN baris ini ke peer.
     *
     * Berbeda dari `milikNodeIni()`, yang menjawab siapa berhak
     * MEMPERBARUINYA. Node global menyisipkan seluruh data kejuaraan --
     * termasuk partai yang sudah dijadwalkan ke gelanggang, yang sejak saat
     * itu diperbarui gelanggangnya. Kalau hak kirim disamakan dengan hak
     * perbarui, node global berhenti menyiarkan partai begitu ia dijadwalkan,
     * dan laptop gelanggang yang baru dipasang tidak pernah menerima daftar
     * partainya sendiri: ia menunggu kiriman dari satu-satunya mesin yang
     * menurut aturan memilikinya, yaitu dirinya sendiri, yang belum punya
     * apa-apa.
     *
     * Terlihat begitu di peramban 11 September 2026: seluruh tabel tersalin
     * lengkap ke node baru, `matches` nol dari 174.
     *
     * Yang menjaga supaya siaran ini tidak menimpa hasil pertandingan yang
     * lebih baru ada di sisi penerima: baris yang sudah ada dan miliknya
     * sendiri tidak pernah ditimpa.
     *
     * @param  array<string, mixed>  $baris
     */
    public function bolehMengirim(string $tabel, array $baris): bool
    {
        if ($this->milikNodeIni($tabel, $baris)) {
            return true;
        }

        $dataKejuaraan = in_array($tabel, PetaSinkron::GLOBAL, true)
            || in_array($tabel, PetaSinkron::PENGHUBUNG, true);

        return $dataKejuaraan && $this->nodeGlobal();
    }

    public function memegangArena(string $kode): bool
    {
        return in_array($kode, $this->arena, true);
    }

    /**
     * Menelusuri rantai relasi sebuah baris lokal sampai bertemu kode arena.
     *
     * @param  array<string, mixed>  $baris
     */
    private function telusuriArena(string $tabel, array $baris): ?string
    {
        $rantai = PetaSinkron::LOKAL[$tabel] ?? [];

        /*
         * Rantai kosong berarti barisnya menyebut gelanggangnya sendiri --
         * tidak ada yang perlu dilompati. Sebelum ini, tabel semacam itu harus
         * menuliskan rantai palsu ke `arenas` yang tidak menuju ke mana-mana,
         * dan pembacanya harus menebak apa maksudnya.
         */
        if ($rantai === []) {
            return $this->kodeArenaDariId($baris['arena_id'] ?? null);
        }

        // Langkah pertama berangkat dari baris yang sedang ditanyakan;
        // langkah berikutnya berangkat dari baris yang baru saja ditemukan.
        $nilai = $baris[$rantai[0][1]] ?? null;

        foreach ($rantai as $i => [$tabelTujuan, $kolom]) {
            if ($nilai === null) {
                return null;
            }

            $tujuan = DB::table($tabelTujuan)->where('id', $nilai)->first();

            if ($tujuan === null) {
                return null;
            }

            // Sampai di tabel yang menyebut gelanggang: rantai selesai di sini,
            // walaupun masih ada langkah tersisa di petanya.
            if (isset($tujuan->arena_id)) {
                return $this->kodeArenaDariId($tujuan->arena_id);
            }

            $lanjutan = $rantai[$i + 1][1] ?? null;
            $nilai = $lanjutan === null ? null : ($tujuan->{$lanjutan} ?? null);
        }

        return null;
    }

    /**
     * Kode gelanggang dari id-nya.
     *
     * Kode, bukan id, yang dipakai membandingkan kepemilikan. Id
     * auto-increment berbeda antar basis data; kode ("A", "B") sama di semua
     * node karena ikut disinkronkan dari node global. Membandingkan id berarti
     * node gelanggang A mengklaim baris milik B begitu urutan penyisipan di
     * kedua basis data kebetulan berbeda.
     */
    private function kodeArenaDariId(mixed $arenaId): ?string
    {
        if ($arenaId === null) {
            return null;
        }

        $kunci = (string) $arenaId;

        return $this->ingatan[$kunci] ??= DB::table('arenas')
            ->where('id', $arenaId)
            ->value('code');
    }
}
