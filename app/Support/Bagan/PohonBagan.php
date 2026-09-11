<?php

namespace App\Support\Bagan;

use App\Enums\ModeBagan;
use App\Support\Bagan\Contracts\SumberBagan;
use Illuminate\Support\Collection;

/**
 * Susunan pohon bagan siap gambar: posisi tiap slot dan tiap garis penghubung.
 *
 * Berdiri sendiri, bukan di controller, karena KETIGA permukaan yang menampilkan
 * bagan memakainya: halaman bagan panitia, halaman bagan publik, dan overlay
 * siaran. Bagan yang dihitung ulang di tiap tempat akan menyimpang diam-diam --
 * dan bagan yang berbeda antara layar panitia, layar penonton, dan siaran
 * membuat tiga pihak membaca undian yang sama dengan cara yang berbeda.
 * Dipasangkan dengan satu komponen tampilan tunggal, <x-si.pohon-bagan>.
 *
 * Slot diletakkan dengan koordinat mutlak, bukan flex. `space-around` mendekati
 * posisi yang benar tapi meleset begitu tinggi slot atau jumlah peserta berubah,
 * dan pohon yang garisnya meleset menyesatkan pembacanya tentang siapa bertemu
 * siapa -- kesalahan paling mahal di layar ini.
 */
class PohonBagan
{
    /*
     * Ukuran yang mengikat tata letak. Garis penghubung harus bertemu TEPAT di
     * tengah slot pasangannya: satu-dua piksel meleset terbaca sebagai bagan
     * yang salah sambung.
     *
     * Dua baris: nama pesilat di atas, kontingennya di bawah. Sebaris, nama
     * panjang dan kontingen panjang saling memakan ruang sampai keduanya
     * terpotong -- dan nama kontingen yang terpotong ("Merpati Putih Band…")
     * tidak bisa dibedakan dari kontingen lain berawalan sama.
     */
    public const SLOT_TINGGI = 48;

    public const SLOT_JARAK = 6;    // antar dua slot dalam satu partai

    public const LANGKAH = 132;     // dari tengah partai ke tengah partai berikutnya

    public const KOLOM_AWAL = 260;  // kontingen turun ke baris kedua, kolomnya tidak perlu lebih lebar

    public const KOLOM_LANJUT = 260;

    public const PENGHUBUNG = 40;   // lebar kolom garis antar babak

    /** @return array<string, mixed> */
    public function __invoke(SumberBagan $bracket): array
    {
        $slots = $bracket->tempatBagan()->sortBy('position')->values();

        /*
         * Ukuran bagan selalu pangkat dua, apa pun jumlah baris slot yang
         * kebetulan ada.
         *
         * Sebelumnya jumlah slot dipakai apa adanya. Satu baris slot yang
         * hilang -- pendaftaran terhapus, cascade ikut membawa slotnya --
         * membuat ukuran jadi ganjil, dan perataan berpasangan ke babak
         * berikutnya menemukan potongan berisi satu elemen lalu berhenti
         * dengan galat. Yang jatuh bukan halaman panitia saja: halaman bagan
         * penonton ikut mati. Geometri pohon tidak boleh bergantung pada
         * kelengkapan data; kalau ada slot yang hilang, yang pantas terjadi
         * adalah kotak kosong, bukan layar galat.
         */
        /*
         * Bagan pemasalan TIDAK dibulatkan ke pangkat dua.
         *
         * Membulatkannya akan menggambar enam kotak kosong di sebelah sepuluh
         * peserta -- kotak yang tidak mewakili siapa pun dan tidak pernah
         * dipertandingkan, padahal seluruh alasan mode pemasalan ada justru
         * untuk menghapus bye itu.
         */
        $ukuran = $bracket->modeBagan() === ModeBagan::Pemasalan
            ? max(2, $slots->count() ?: $bracket->ukuranBagan())
            : UrutanUnggulan::ukuranBagan(max(2, $slots->count() ?: $bracket->ukuranBagan()));

        $jumlahBabak = (int) ceil(log($ukuran, 2));

        // Tengah tiap slot babak pertama, lalu rata-rata berpasangan ke atas.
        $tengah = [];
        for ($i = 0; $i < $ukuran; $i++) {
            $tengah[0][$i] = intdiv($i, 2) * self::LANGKAH
                + ($i % 2) * (self::SLOT_TINGGI + self::SLOT_JARAK)
                + intdiv(self::SLOT_TINGGI, 2);
        }

        /*
         * jumlahBabak = log2(ukuran): bagan 4 peserta punya DUA babak, yaitu
         * penyisihan dan final. Kolom terakhir bernomor jumlahBabak-1, dan
         * tengah dihitung sampai situ saja -- satu babak kelebihan akan
         * menggambar kolom kosong berlabel "Final" di sebelah final yang
         * sebenarnya.
         */
        /*
         * Potongan berisi SATU tinggi -- babak ganjil pada bagan pemasalan --
         * mempertahankan ketinggiannya apa adanya: yang melenggang berdiri
         * tepat di ketinggian tempatnya sendiri, bukan di tengah pasangan
         * yang tidak ada. Untuk bagan pangkat dua tiap potongan selalu berisi
         * dua, jadi cabang ini tidak pernah diambil di sana.
         */
        for ($r = 1; $r < $jumlahBabak; $r++) {
            foreach (array_chunk($tengah[$r - 1], 2) as $j => $pasangan) {
                $tengah[$r][$j] = count($pasangan) === 2
                    ? intdiv($pasangan[0] + $pasangan[1], 2)
                    : $pasangan[0];
            }
        }

        $partaiPerBabak = $bracket->partaiBagan()->groupBy('round');
        $pasanganBye = $this->pasanganBye($slots, $ukuran);

        $kolom = [];
        $x = 0;

        for ($r = 0; $r < $jumlahBabak; $r++) {
            $lebar = $r === 0 ? self::KOLOM_AWAL : self::KOLOM_LANJUT;

            $kolom[] = [
                'judul' => $bracket->namaBabak($r + 1),
                'x' => $x,
                'lebar' => $lebar,
                'slot' => $r === 0
                    ? $this->slotBabakPertama($slots, $tengah[0], $ukuran, $pasanganBye)
                    : $this->slotBabakLanjut($partaiPerBabak->get($r + 1), $tengah[$r]),
            ];

            $x += $lebar + self::PENGHUBUNG;
        }

        return [
            'tinggi' => (int) ceil($ukuran / 2) * self::LANGKAH - (self::LANGKAH - self::SLOT_TINGGI * 2 - self::SLOT_JARAK),
            'lebar' => $x - self::PENGHUBUNG,
            'slot_tinggi' => self::SLOT_TINGGI,
            'kolom' => $kolom,
            'garis' => $this->garis($tengah, $jumlahBabak, $pasanganBye),
        ];
    }

    /**
     * Slot babak pertama, tanpa pasangan yang isinya bye.
     *
     * Pasangan bye dilewati seluruhnya — baik pesilat yang beruntung maupun
     * tempat kosong lawannya. Partainya tidak pernah dipertandingkan, jadi
     * memajangnya di kolom pertama membuat pembaca bagan mengira ada
     * pertandingan yang ia lewatkan. Pesilatnya tetap terlihat, satu kolom di
     * sebelah kanan, di babak tempat ia benar-benar naik gelanggang.
     *
     * Tempatnya tidak ikut dirapatkan: sisa pasangan tetap di ketinggian
     * aslinya supaya garis penghubungnya bertemu tepat di tengah slot babak
     * berikutnya.
     *
     * @param  array<int, true>  $pasanganBye  nomor pasangan yang dilewati
     * @return array<int, array<string, mixed>>
     */
    private function slotBabakPertama($slots, array $tengah, int $ukuran, array $pasanganBye = []): array
    {
        $hasil = [];

        for ($i = 0; $i < $ukuran; $i++) {
            if (isset($pasanganBye[intdiv($i, 2)])) {
                continue;
            }

            $slot = $slots->get($i);
            $peserta = $slot?->registration;

            $hasil[] = [
                'y' => $tengah[$i] - intdiv(self::SLOT_TINGGI, 2),
                'nomor' => $slot?->position ?? $i + 1,
                // Slot ganjil adalah sudut merah -- berlaku sebelum partainya
                // dijadwalkan, dan kontingen memakainya untuk menyiapkan sudut.
                'sudut' => $i % 2 === 0 ? 'merah' : 'biru',
                'nama' => $peserta?->athletes->pluck('name')->implode(', '),
                'kontingen' => $peserta?->contingent->name,
                'kosong' => $peserta === null,
            ];
        }

        return $hasil;
    }

    /**
     * Nomor pasangan babak pertama yang isinya bye.
     *
     * Dikenali dari tempat undian, bukan dari partainya: tepat satu dari dua
     * tempat berpenghuni berarti lawannya tidak akan pernah ada.
     *
     * @return array<int, true> nomor pasangan (mulai 0) sebagai kunci
     */
    private function pasanganBye($slots, int $ukuran): array
    {
        $bye = [];

        for ($j = 0; $j < (int) ceil($ukuran / 2); $j++) {
            $terisi = (int) ($slots->get($j * 2)?->registration_id !== null)
                + (int) ($slots->get($j * 2 + 1)?->registration_id !== null);

            if ($terisi === 1) {
                $bye[$j] = true;
            }
        }

        return $bye;
    }

    /** @return array<int, array<string, mixed>> */
    private function slotBabakLanjut(?Collection $partai, array $tengah): array
    {
        $urut = ($partai ?? collect())->sortBy('position')->values();
        $hasil = [];

        foreach ($tengah as $j => $y) {
            /*
             * Satu slot babak lanjut = satu SISI dari satu partai. Partai ke-n
             * di babak itu memuat slot 2n dan 2n+1.
             */
            $p = $urut->get(intdiv($j, 2));
            $peserta = $j % 2 === 0 ? $p?->red : $p?->blue;

            $hasil[] = [
                'y' => $y - intdiv(self::SLOT_TINGGI, 2),
                'nama' => $peserta?->athletes->pluck('name')->implode(', '),
                'kontingen' => $peserta?->contingent->name,
                'sudut' => $j % 2 === 0 ? 'merah' : 'biru',
                // Sudut baru diwarnai setelah penghuninya pasti. Mewarnai slot
                // yang masih menunggu berarti menjanjikan sesuatu yang belum
                // diputuskan.
                'menunggu' => $peserta === null,
                'bye' => $peserta !== null && $p?->bye(),
            ];
        }

        return $hasil;
    }

    /**
     * Garis penghubung antar babak: keluar dari tiap slot, menyatu, lalu masuk.
     *
     * @return array<int, array<string, mixed>>
     */
    private function garis(array $tengah, int $jumlahBabak, array $pasanganBye = []): array
    {
        $garis = [];
        $x = self::KOLOM_AWAL;

        // Penghubung ada di ANTARA kolom, jadi jumlahnya satu kurang dari
        // jumlah babak.
        for ($r = 0; $r < $jumlahBabak - 1; $r++) {
            $separuh = intdiv(self::PENGHUBUNG, 2);

            foreach (array_chunk($tengah[$r], 2) as $j => $pasangan) {
                // Pasangan bye tidak digambar di kolom pertama, jadi tidak ada
                // slot yang bisa dijadikan pangkal garisnya.
                if ($r === 0 && isset($pasanganBye[$j])) {
                    continue;
                }

                /*
                 * Potongan berisi satu -- yang melenggang pada bagan pemasalan
                 * berjumlah ganjil. Satu garis mendatar lurus, tanpa siku:
                 * tidak ada dua slot yang dipertemukan di sini, dan siku yang
                 * digambar tanpa lawan terbaca sebagai partai yang tidak ada.
                 */
                if (count($pasangan) === 1) {
                    $garis[] = ['jenis' => 'h', 'x' => $x, 'y' => $pasangan[0], 'panjang' => self::PENGHUBUNG];

                    continue;
                }

                [$atas, $bawah] = $pasangan;

                $garis[] = ['jenis' => 'h', 'x' => $x, 'y' => $atas, 'panjang' => $separuh];
                $garis[] = ['jenis' => 'h', 'x' => $x, 'y' => $bawah, 'panjang' => $separuh];
                $garis[] = ['jenis' => 'v', 'x' => $x + $separuh, 'y' => $atas, 'panjang' => $bawah - $atas];
                $garis[] = ['jenis' => 'h', 'x' => $x + $separuh, 'y' => $tengah[$r + 1][$j], 'panjang' => $separuh];
            }

            $x += self::PENGHUBUNG + self::KOLOM_LANJUT;
        }

        return $garis;
    }
}
