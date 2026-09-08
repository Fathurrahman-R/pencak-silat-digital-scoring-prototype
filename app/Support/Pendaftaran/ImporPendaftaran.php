<?php

namespace App\Support\Pendaftaran;

use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusEvent;
use App\Models\Tournament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Memasukkan daftar peserta satu kontingen sekaligus, dari CSV.
 *
 * # Kenapa CSV, bukan XLSX
 *
 * Berkas kejuaraan hampir selalu lahir di Google Spreadsheet atau Excel, dan
 * keduanya mengekspor CSV tanpa memasang apa pun. XLSX menuntut pustaka pembaca
 * ZIP+XML yang harus ikut dipasang di lima laptop gelanggang -- untuk membaca
 * tabel enam kolom yang formatnya justru sudah kita tentukan sendiri lewat
 * berkas contoh.
 *
 * # Kenapa pratinjaunya menjalankan impor sungguhan
 *
 * Pratinjau yang menebak-nebak hasilnya adalah pratinjau yang suatu saat
 * berbohong: ia memakai jalur kode sendiri, dan jalur itu akan menyimpang dari
 * jalur simpan tanpa ada yang menyadarinya. Di sini keduanya SATU jalur --
 * `jalankan()` menulis betulan lalu memutar balik transaksinya saat
 * `$simpan` bernilai false. Yang dilihat panitia di layar pratinjau adalah
 * yang benar-benar akan terjadi, sampai ke pesan penolakannya.
 *
 * # Yang TIDAK dikerjakan di sini
 *
 * Berkas persyaratan (bukti umur, surat sehat) tidak bisa datang lewat CSV,
 * jadi peserta hasil impor tetap berstatus draf sampai berkasnya diunggah dan
 * pendaftarannya diajukan. Impor memindahkan pengetikan, bukan verifikasi.
 */
class ImporPendaftaran
{
    /** Nama kolom yang dikenali, beserta ejaan lain yang lazim dipakai panitia. */
    private const KOLOM = [
        'nama' => ['nama', 'nama_atlet', 'nama_pesilat', 'nama_lengkap'],
        'jenis_kelamin' => ['jenis_kelamin', 'jk', 'gender', 'l_p'],
        'tanggal_lahir' => ['tanggal_lahir', 'tgl_lahir', 'lahir', 'tanggal'],
        'berat' => ['berat', 'berat_badan', 'bb', 'berat_kg'],
        'tanding' => ['tanding', 'kelas_tanding', 'ikut_tanding'],
        'nomor_jurus' => ['nomor_jurus', 'jurus', 'nomor_seni', 'seni'],
        'regu' => ['regu', 'tim', 'grup', 'kelompok'],
    ];

    public function __construct(
        private readonly PeriksaKelayakan $periksa,
        private readonly DaftarkanPeserta $daftarkan,
    ) {}

    /**
     * Berkas contoh: baris kepala plus dua baris isi sebagai contoh bentuk.
     *
     * Ikut menuliskan contoh, bukan kepala saja. Kolom kosong tidak mengajarkan
     * apa pun tentang bentuk tanggal maupun cara menulis "ikut tanding", dan
     * itu dua hal yang paling sering salah pada impor pertama.
     */
    public function berkasContoh(): string
    {
        $baris = [
            ['nama', 'jenis_kelamin', 'tanggal_lahir', 'berat', 'tanding', 'nomor_jurus', 'regu'],
            ['Budi Santoso', 'putra', '2005-04-17', '58.5', 'ya', '', ''],
            ['Siti Aminah', 'putri', '17/08/2007', '49', 'ya', 'Jurus Tunggal Putri Remaja', ''],
            ['Andi Pratama', 'putra', '2004-01-30', '62', '', 'Jurus Ganda Putra Dewasa', 'Ganda A'],
            ['Rizal Hakim', 'putra', '2004-11-02', '60', '', 'Jurus Ganda Putra Dewasa', 'Ganda A'],
        ];

        $keluaran = fopen('php://temp', 'r+');

        foreach ($baris as $satu) {
            fputcsv($keluaran, $satu);
        }

        rewind($keluaran);
        $isi = stream_get_contents($keluaran);
        fclose($keluaran);

        // BOM supaya Excel membuka huruf beraksen dengan benar; Google
        // Spreadsheet mengabaikannya.
        return "\u{FEFF}".$isi;
    }

    /**
     * Alamat ekspor CSV sebuah Google Spreadsheet.
     *
     * Sengaja memakai jalur ekspor bawaan Spreadsheet, bukan API-nya. API
     * menuntut kunci layanan yang harus dibuat, disimpan, dan diperbarui di
     * tiap laptop; jalur ini hanya menuntut satu hal yang sudah dipahami
     * panitia -- berkasnya dibagikan sebagai "siapa saja yang memiliki link".
     *
     * @throws RuntimeException bila alamatnya bukan Google Spreadsheet
     */
    public function alamatEksporSpreadsheet(string $url): string
    {
        if (! preg_match('#docs\.google\.com/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $cocok)) {
            throw new RuntimeException(
                'Alamat itu bukan Google Spreadsheet. Salin alamat dari bilah alamat peramban '
                .'saat spreadsheet-nya terbuka.',
            );
        }

        $id = $cocok[1];

        // gid menunjuk LEMBAR mana yang diambil. Tanpa itu Google memberi
        // lembar pertama, dan daftar peserta jarang berada di lembar pertama.
        $gid = preg_match('#[#?&]gid=([0-9]+)#', $url, $g) ? $g[1] : '0';

        return "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}";
    }

    /**
     * Mengunduh isi CSV dari sebuah alamat.
     *
     * @throws RuntimeException bila tidak terjangkau atau balasannya bukan CSV
     */
    public function unduh(string $url): string
    {
        $konteks = stream_context_create(['http' => [
            'timeout' => 20,
            'follow_location' => 1,
            'max_redirects' => 5,
            'ignore_errors' => true,
        ]]);

        $isi = @file_get_contents($url, false, $konteks);

        if ($isi === false) {
            throw new RuntimeException(
                'Spreadsheet tidak terjangkau dari laptop ini. Gelanggang biasanya tanpa internet — '
                .'unduh CSV-nya lebih dulu lewat Berkas → Unduh → CSV, lalu unggah berkasnya di sini.',
            );
        }

        // Google membalas halaman login berbentuk HTML kalau berkasnya belum
        // dibagikan. Dibiarkan lewat, ia terbaca sebagai satu baris tanpa satu
        // pun kolom yang dikenali -- pesan yang tidak menolong siapa pun.
        if (Str::startsWith(ltrim($isi), ['<!DOCTYPE', '<html', '<HTML'])) {
            throw new RuntimeException(
                'Spreadsheet-nya belum boleh dibaca tanpa login. Buka menu Bagikan, '
                .'lalu setel "Siapa saja yang memiliki link" sebagai Pelihat.',
            );
        }

        return $isi;
    }

    /**
     * Membaca CSV jadi baris berkunci nama kolom.
     *
     * @return array<int, array<string, string>>
     *
     * @throws RuntimeException bila kepala kolomnya tidak dikenali
     */
    public function baca(string $isi): array
    {
        $isi = preg_replace('/^\x{FEFF}/u', '', $isi) ?? $isi;

        $penunjuk = fopen('php://temp', 'r+');
        fwrite($penunjuk, $isi);
        rewind($penunjuk);

        $pemisah = $this->tebakPemisah($isi);

        $kepala = fgetcsv($penunjuk, 0, $pemisah);

        if ($kepala === false || $kepala === [null]) {
            fclose($penunjuk);

            throw new RuntimeException('Berkasnya kosong.');
        }

        $peta = $this->petakanKolom($kepala);

        if (! isset($peta['nama'])) {
            fclose($penunjuk);

            throw new RuntimeException(
                'Kolom "nama" tidak ditemukan di baris pertama. Baris pertama harus berisi nama kolom — '
                .'unduh berkas contoh di atas untuk melihat bentuknya.',
            );
        }

        $baris = [];
        $nomor = 1;

        while (($isiBaris = fgetcsv($penunjuk, 0, $pemisah)) !== false) {
            $nomor++;

            // Baris kosong di ujung berkas adalah hal biasa pada ekspor
            // spreadsheet, dan bukan kekeliruan yang perlu dilaporkan.
            if ($isiBaris === [null] || count(array_filter($isiBaris, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $satu = ['_baris' => $nomor];

            foreach ($peta as $kunci => $indeks) {
                $satu[$kunci] = trim((string) ($isiBaris[$indeks] ?? ''));
            }

            $baris[] = $satu;
        }

        fclose($penunjuk);

        return $baris;
    }

    /**
     * Menjalankan impor.
     *
     * `$simpan` false berarti pratinjau: seluruhnya dikerjakan sungguhan lalu
     * diputar balik. Lihat catatan kelas soal kenapa keduanya satu jalur.
     *
     * @param  array<int, array<string, string>>  $baris
     * @return array{baris: array<int, array<string, mixed>>, ringkas: array<string, int>}
     */
    public function jalankan(Tournament $tournament, Contingent $contingent, array $baris, bool $simpan): array
    {
        DB::beginTransaction();

        try {
            $hasil = $this->prosesSemua($tournament, $contingent, $baris);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($simpan) {
            DB::commit();
        } else {
            DB::rollBack();
        }

        return $hasil;
    }

    /**
     * @param  array<int, array<string, string>>  $baris
     * @return array{baris: array<int, array<string, mixed>>, ringkas: array<string, int>}
     */
    private function prosesSemua(Tournament $tournament, Contingent $contingent, array $baris): array
    {
        $hasil = [];
        $atletBaris = [];

        /*
         * Dihitung sekali per PENDAFTARAN, bukan per baris. Satu nomor Ganda
         * lahir dari dua baris CSV; menjumlahkan nomor tiap baris membuat
         * ringkasan menjanjikan lima pendaftaran pada berkas yang menghasilkan
         * empat, dan angka yang tidak cocok dengan isi tabel di bawahnya
         * adalah angka yang membuat panitia menghitung ulang dengan tangan.
         */
        $jumlahPendaftaran = 0;

        // ------------------------------------------------ tahap 1: atlet
        foreach ($baris as $satu) {
            $catatan = [];
            $atlet = null;

            try {
                $atlet = $this->atletDariBaris($contingent, $satu, $catatan);
            } catch (RuntimeException $e) {
                $hasil[] = $this->baris($satu, 'tolak', [$e->getMessage()], null, $tournament);

                continue;
            }

            $atletBaris[$satu['_baris']] = $atlet;
            $hasil[$satu['_baris']] = $this->baris($satu, 'siap', $catatan, $atlet, $tournament);
        }

        // ------------------------------------------------ tahap 2: tanding
        foreach ($baris as $satu) {
            $atlet = $atletBaris[$satu['_baris']] ?? null;

            if ($atlet === null || ! $this->benar($satu['tanding'] ?? '')) {
                continue;
            }

            $cocok = $this->periksa->kelasYangCocok($atlet, $contingent);

            if ($cocok->isEmpty()) {
                $hasil[$satu['_baris']]['catatan'][] = 'Tidak ada kelas tanding yang cocok dengan '
                    .'jenis kelamin, golongan usia, dan berat badannya.';

                continue;
            }

            if ($cocok->count() > 1) {
                $hasil[$satu['_baris']]['catatan'][] = 'Ada '.$cocok->count().' kelas tanding yang cocok, '
                    .'jadi kelasnya harus dipilih sendiri di halaman Pendaftaran nomor.';

                continue;
            }

            try {
                $this->daftarkan->tanding($contingent, $cocok->first(), $atlet);
                $hasil[$satu['_baris']]['nomor'][] = 'Tanding '.$cocok->first()->name;
                $jumlahPendaftaran++;
            } catch (PendaftaranDitolak $ditolak) {
                $hasil[$satu['_baris']]['catatan'] = [...$hasil[$satu['_baris']]['catatan'], ...$ditolak->alasan];
            }
        }

        // ------------------------------------------------ tahap 3: jurus
        //
        // Dikelompokkan lebih dulu: satu nomor Ganda diisi dua baris CSV yang
        // harus jadi SATU pendaftaran, dan yang menyatukannya adalah kolom
        // `regu`. Baris tanpa `regu` berdiri sendiri -- itulah nomor Tunggal.
        foreach ($this->kelompokJurus($baris) as $anggota) {
            $nomorTeks = $anggota[0]['nomor_jurus'];
            $nomor = $this->nomorJurus($tournament, $nomorTeks);
            $barisAnggota = array_column($anggota, '_baris');

            if ($nomor === null) {
                foreach ($barisAnggota as $n) {
                    if (isset($hasil[$n])) {
                        $hasil[$n]['catatan'][] = "Nomor jurus “{$nomorTeks}” tidak ada di kejuaraan ini.";
                    }
                }

                continue;
            }

            $atlet = collect($barisAnggota)
                ->map(fn ($n) => $atletBaris[$n] ?? null)
                ->filter()
                ->values();

            if ($atlet->count() !== count($barisAnggota)) {
                continue;
            }

            try {
                $this->daftarkan->jurus($contingent, $nomor, $atlet);
                $jumlahPendaftaran++;

                foreach ($barisAnggota as $n) {
                    $hasil[$n]['nomor'][] = $nomor->nama();
                }
            } catch (PendaftaranDitolak $ditolak) {
                foreach ($barisAnggota as $n) {
                    $hasil[$n]['catatan'] = [...$hasil[$n]['catatan'], ...$ditolak->alasan];
                }
            }
        }

        $hasil = array_values($hasil);

        return [
            'baris' => $hasil,
            'ringkas' => [
                'baris' => count($hasil),
                'atlet_baru' => count(array_filter($hasil, fn ($b) => $b['status'] === 'siap' && $b['atlet_baru'])),
                'atlet_lama' => count(array_filter($hasil, fn ($b) => $b['status'] === 'siap' && ! $b['atlet_baru'])),
                'nomor' => $jumlahPendaftaran,
                'ditolak' => count(array_filter($hasil, fn ($b) => $b['status'] === 'tolak')),
                'catatan' => count(array_filter($hasil, fn ($b) => $b['catatan'] !== [])),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $satu
     * @param  array<int, string>  $catatan
     * @return array<string, mixed>
     */
    private function baris(array $satu, string $status, array $catatan, ?Athlete $atlet = null, ?Tournament $tournament = null): array
    {
        return [
            'baris' => $satu['_baris'],
            'nama' => $satu['nama'] ?? '',
            'status' => $status,
            'atlet_baru' => $atlet?->wasRecentlyCreated ?? false,
            /*
             * Golongan dihitung terhadap TANGGAL MULAI kejuaraan, bukan hari
             * ini. Selisihnya cuma penting sekali setahun -- pesilat yang
             * berulang tahun di antara hari impor dan hari-H -- dan justru itu
             * baris yang paling mahal kalau golongannya keliru di layar.
             */
            'golongan' => $atlet?->golonganUsia($tournament)?->label(),
            'nomor' => [],
            'catatan' => $catatan,
        ];
    }

    /**
     * Atlet untuk satu baris: yang sudah ada dipakai ulang, yang belum dibuat.
     *
     * Dicocokkan dengan nama DAN tanggal lahir. Nama saja terlalu longgar --
     * dua "Ahmad" dalam satu kontingen bukan hal aneh, dan menggabungkan
     * keduanya jadi satu orang menghapus salah satunya dari kejuaraan tanpa
     * pesan apa pun. Impor ulang berkas yang sama karena itu tidak menggandakan
     * siapa pun, dan itu memang bentuk pemakaian yang paling sering terjadi:
     * panitia memperbaiki satu baris lalu mengunggah ulang seluruh berkas.
     *
     * @param  array<string, mixed>  $satu
     * @param  array<int, string>  $catatan
     *
     * @throws RuntimeException bila barisnya tidak layak jadi atlet
     */
    private function atletDariBaris(Contingent $contingent, array $satu, array &$catatan): Athlete
    {
        $nama = trim((string) ($satu['nama'] ?? ''));

        if ($nama === '') {
            throw new RuntimeException('Kolom nama kosong.');
        }

        $kelamin = $this->jenisKelamin($satu['jenis_kelamin'] ?? '');

        if ($kelamin === null) {
            throw new RuntimeException(
                'Jenis kelamin tidak terbaca: "'.($satu['jenis_kelamin'] ?? '').'". '
                .'Tulis putra atau putri (boleh juga L atau P).',
            );
        }

        $lahir = $this->tanggal($satu['tanggal_lahir'] ?? '');

        if ($lahir === null) {
            throw new RuntimeException(
                'Tanggal lahir tidak terbaca: "'.($satu['tanggal_lahir'] ?? '').'". '
                .'Tulis 2005-04-17 atau 17/04/2005.',
            );
        }

        if ($lahir->isFuture()) {
            throw new RuntimeException('Tanggal lahir ada di masa depan.');
        }

        $berat = $this->angka($satu['berat'] ?? '');

        if ($berat !== null && ($berat < 10 || $berat > 200)) {
            $catatan[] = 'Berat '.$berat.' kg di luar jangkauan wajar, jadi dikosongkan.';
            $berat = null;
        }

        $adaSudah = $contingent->athletes()
            ->where('name', $nama)
            ->whereDate('birth_date', $lahir->toDateString())
            ->first();

        if ($adaSudah !== null) {
            // Berat badan yang berubah antara dua unggahan memang wajar --
            // panitia memperbaiki angkanya setelah timbang percobaan.
            if ($berat !== null && (float) $adaSudah->weight_claim !== $berat) {
                $adaSudah->update(['weight_claim' => $berat]);
                $catatan[] = 'Atlet sudah ada; berat klaimnya diperbarui jadi '.$berat.' kg.';
            } else {
                $catatan[] = 'Atlet sudah ada di kontingen ini, jadi tidak dibuat ulang.';
            }

            return $adaSudah;
        }

        return $contingent->athletes()->create([
            'name' => $nama,
            'jenis_kelamin' => $kelamin,
            'birth_date' => $lahir->toDateString(),
            'weight_claim' => $berat,
        ]);
    }

    /**
     * Baris yang menyebut nomor jurus, dikelompokkan menurut `regu`.
     *
     * @param  array<int, array<string, string>>  $baris
     * @return array<int, array<int, array<string, string>>>
     */
    private function kelompokJurus(array $baris): array
    {
        $kelompok = [];

        foreach ($baris as $satu) {
            $nomor = trim((string) ($satu['nomor_jurus'] ?? ''));

            if ($nomor === '') {
                continue;
            }

            $regu = trim((string) ($satu['regu'] ?? ''));

            // Tanpa `regu`, tiap baris berdiri sendiri. Kuncinya diberi nomor
            // barisnya supaya dua nomor Tunggal yang sama tidak ikut menyatu.
            $kunci = $regu === ''
                ? 'baris:'.$satu['_baris']
                : Str::lower($nomor).'|'.Str::lower($regu);

            $kelompok[$kunci][] = $satu;
        }

        return array_values($kelompok);
    }

    /** Nomor jurus yang namanya cocok, dibandingkan longgar terhadap spasi dan huruf besar. */
    private function nomorJurus(Tournament $tournament, string $teks): ?JurusEvent
    {
        $cari = Str::lower(preg_replace('/\s+/', ' ', trim($teks)) ?? $teks);

        return $tournament->jurusEvents->first(
            fn (JurusEvent $n) => Str::lower(preg_replace('/\s+/', ' ', $n->nama()) ?? $n->nama()) === $cari
        );
    }

    /** @param  array<int, string>  $kepala */
    private function petakanKolom(array $kepala): array
    {
        $peta = [];

        foreach ($kepala as $i => $judul) {
            $bersih = Str::slug(trim((string) $judul), '_');

            foreach (self::KOLOM as $kunci => $ejaan) {
                if (in_array($bersih, $ejaan, true) && ! isset($peta[$kunci])) {
                    $peta[$kunci] = $i;
                }
            }
        }

        return $peta;
    }

    /**
     * Pemisah kolom ditebak dari baris pertama.
     *
     * Excel berbahasa Indonesia menulis CSV dengan titik koma, bukan koma, dan
     * berkas seperti itu terbaca sebagai satu kolom raksasa -- gejalanya
     * "kolom nama tidak ditemukan" pada berkas yang jelas-jelas punya kolom
     * nama.
     */
    private function tebakPemisah(string $isi): string
    {
        $barisPertama = strtok($isi, "\n") ?: '';

        return substr_count($barisPertama, ';') > substr_count($barisPertama, ',') ? ';' : ',';
    }

    private function jenisKelamin(string $teks): ?JenisKelamin
    {
        return match (Str::lower(trim($teks))) {
            'putra', 'l', 'laki-laki', 'laki laki', 'pria', 'm', 'male' => JenisKelamin::Putra,
            'putri', 'p', 'perempuan', 'wanita', 'f', 'female' => JenisKelamin::Putri,
            default => null,
        };
    }

    private function tanggal(string $teks): ?Carbon
    {
        $teks = trim($teks);

        if ($teks === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y', 'm/d/Y'] as $bentuk) {
            try {
                $tanggal = Carbon::createFromFormat($bentuk, $teks);

                if ($tanggal !== false && $tanggal->format($bentuk) === $teks) {
                    return $tanggal->startOfDay();
                }
            } catch (Throwable) {
                // Bentuk berikutnya.
            }
        }

        return null;
    }

    private function angka(string $teks): ?float
    {
        $teks = trim(str_replace(',', '.', $teks));

        return $teks === '' || ! is_numeric($teks) ? null : (float) $teks;
    }

    private function benar(string $teks): bool
    {
        return in_array(Str::lower(trim($teks)), ['1', 'ya', 'y', 'true', 'x', 'v', 'iya', 'yes'], true);
    }
}
