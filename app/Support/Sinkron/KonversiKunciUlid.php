<?php

namespace App\Support\Sinkron;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Memindahkan kunci utama sebuah tabel dari auto-increment ke ULID, beserta
 * semua kolom yang menunjuknya.
 *
 * # Kenapa perpindahan ini perlu sama sekali
 *
 * Tiap gelanggang punya basis datanya sendiri, dan penghitung auto-increment
 * tiap basis data mulai dari satu. Gelanggang A menerbitkan nilai bernomor 1,
 * 2, 3; gelanggang B juga. Begitu A menarik data dari B, dua baris berbeda
 * mengklaim nomor yang sama, dan tidak ada cara memilih di antara keduanya
 * yang tidak menghapus salah satu nilai sungguhan.
 *
 * # Kenapa ULID, bukan UUID
 *
 * ULID urut secara leksikografis mengikuti waktu pembuatannya. Di InnoDB,
 * kunci utama menentukan urutan fisik baris di disk, jadi kunci yang urut
 * berarti sisipan selalu jatuh di ujung -- persis seperti auto-increment.
 * UUID versi 4 yang acak menyebarkan sisipan ke seluruh tabel, membelah
 * halaman indeks terus-menerus. Pada tabel sepanas judge_inputs, yang menerima
 * satu baris tiap penekanan tombol juri, bedanya bukan teoretis.
 *
 * # Kenapa satu kelas, bukan empat belas migrasi yang menyalin langkah sama
 *
 * Urutannya panjang dan tiap langkahnya bisa salah diam-diam: kolom penunjuk
 * yang lupa dipetakan menghasilkan foreign key yang menunjuk ke ULID yang
 * tidak ada, dan MySQL baru mengeluhkannya saat baris berikutnya disisipkan --
 * mungkin di tengah pertandingan. Ditulis sekali di sini, ia diuji sekali.
 *
 * # Yang TIDAK dilakukan
 *
 * Tidak menyentuh `sinkron_keluar`. Kolom `baris_id`-nya sudah string sejak
 * lahir justru untuk ini. Catatan lama yang menyebut id integer dibiarkan --
 * ia menunjuk baris yang id-nya sudah berubah, jadi tidak akan ditemukan saat
 * paket disusun, dan itu justru benar: yang lama sudah pernah terkirim.
 */
class KonversiKunciUlid
{
    /**
     * @param  list<array{0: string, 1: string}>  $penunjuk  daftar [tabel, kolom] yang menunjuk kunci tabel ini
     */
    public static function keUlid(string $tabel, array $penunjuk = []): void
    {
        $fk = self::simpanDefinisiForeignKey($penunjuk);
        $index = self::simpanDefinisiIndex($penunjuk);

        self::tambahKolomUlid($tabel);
        self::isiUlid($tabel);

        foreach ($penunjuk as [$tabelPenunjuk, $kolom]) {
            self::tambahKolomPenunjukUlid($tabelPenunjuk, $kolom);
            self::isiPenunjukUlid($tabel, $tabelPenunjuk, $kolom);
        }

        // Foreign key harus lepas SEBELUM kunci yang ditunjuknya dibongkar.
        foreach ($fk as [$tabelPenunjuk, $kolom, $nama]) {
            self::lepasForeignKey($tabelPenunjuk, $nama);
        }

        self::tukarKunciUtama($tabel);

        foreach ($penunjuk as [$tabelPenunjuk, $kolom]) {
            self::tukarKolomPenunjuk($tabelPenunjuk, $kolom);
        }

        foreach ($fk as [$tabelPenunjuk, $kolom, $nama, $onDelete]) {
            self::pasangForeignKey($tabelPenunjuk, $kolom, $tabel, $nama, $onDelete);
        }

        self::pasangUlangIndex($index);
    }

    /**
     * Menyimpan index milik kolom penunjuk sebelum kolomnya dibongkar.
     *
     * Membuang sebuah kolom ikut membuang setiap index yang memuatnya, tanpa
     * peringatan. Index judge_inputs.score_event_id hilang persis begini pada
     * percobaan pertama -- index yang dipasang justru karena tanpa dia panel
     * dewan juri memindai seratus ribu baris untuk menemukan tiga puluh, dan
     * kehilangannya tidak menimbulkan galat apa pun. Yang muncul cuma panel
     * yang melambat lagi, beberapa hari kemudian, tanpa sebab yang kelihatan.
     *
     * Index bikinan foreign key TIDAK ikut disimpan: MySQL membuatnya sendiri
     * saat constraint dipasang kembali, dan menduplikasinya berarti dua index
     * identik yang sama-sama harus diperbarui tiap penulisan.
     *
     * @param  list<array{0: string, 1: string}>  $penunjuk
     * @return list<array{0: string, 1: string, 2: list<string>, 3: bool}>
     */
    private static function simpanDefinisiIndex(array $penunjuk): array
    {
        $hasil = [];

        foreach ($penunjuk as [$tabelPenunjuk, $kolom]) {
            $namaIndex = DB::select(
                "SELECT DISTINCT s.INDEX_NAME nama, s.NON_UNIQUE unik
                 FROM information_schema.STATISTICS s
                 WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME = ?
                   AND s.COLUMN_NAME = ? AND s.INDEX_NAME <> 'PRIMARY'
                   AND s.INDEX_NAME NOT IN (
                       SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = s.TABLE_NAME
                         AND REFERENCED_TABLE_NAME IS NOT NULL
                   )",
                [$tabelPenunjuk, $kolom],
            );

            foreach ($namaIndex as $satu) {
                // Seluruh kolom index ikut dibaca: index gabungan harus
                // dibangun kembali utuh, bukan menyusut jadi satu kolom.
                $kolomIndex = array_map(
                    static fn ($b) => $b->COLUMN_NAME,
                    DB::select(
                        'SELECT COLUMN_NAME FROM information_schema.STATISTICS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
                         ORDER BY SEQ_IN_INDEX',
                        [$tabelPenunjuk, $satu->nama],
                    ),
                );

                $hasil[] = [$tabelPenunjuk, $satu->nama, $kolomIndex, (int) $satu->unik === 0];
            }
        }

        return $hasil;
    }

    /** @param  list<array{0: string, 1: string, 2: list<string>, 3: bool}>  $index */
    private static function pasangUlangIndex(array $index): void
    {
        foreach ($index as [$tabel, $nama, $kolom, $unik]) {
            $sudahAda = DB::selectOne(
                'SELECT 1 x FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
                [$tabel, $nama],
            );

            if ($sudahAda !== null) {
                continue;
            }

            $daftar = '`'.implode('`, `', $kolom).'`';
            $jenis = $unik ? 'UNIQUE INDEX' : 'INDEX';

            DB::statement("ALTER TABLE `{$tabel}` ADD {$jenis} `{$nama}` ({$daftar})");
        }
    }

    /**
     * Mengembalikan kunci tabel ke auto-increment.
     *
     * Nomor yang dihasilkannya BARU, bukan nomor yang dulu dipakai sebelum
     * perpindahan -- nomor lama sudah hilang saat kolomnya dibuang, dan tidak
     * ada tempat menyimpannya yang tidak ikut membebani tabel selamanya.
     * Relasi antar baris tetap utuh karena penunjuknya dipetakan ulang
     * bersamaan; yang berubah cuma angkanya.
     *
     * Gunanya membatalkan pemasangan yang belum jadi, bukan memulihkan
     * kejuaraan yang sedang berjalan. Untuk yang kedua, cadangan basis data.
     *
     * @param  list<array{0: string, 1: string}>  $penunjuk
     */
    public static function keInteger(string $tabel, array $penunjuk = []): void
    {
        $fk = self::simpanDefinisiForeignKey($penunjuk);
        $index = self::simpanDefinisiIndex($penunjuk);

        DB::statement("ALTER TABLE `{$tabel}` ADD COLUMN `id_lama` BIGINT UNSIGNED NULL AFTER `id`");

        $nomor = 0;
        DB::table($tabel)->orderBy('id')->select('id')->chunk(500, function ($baris) use ($tabel, &$nomor) {
            foreach ($baris as $satu) {
                DB::table($tabel)->where('id', $satu->id)->update(['id_lama' => ++$nomor]);
            }
        });

        foreach ($penunjuk as [$tabelPenunjuk, $kolom]) {
            DB::statement("ALTER TABLE `{$tabelPenunjuk}` ADD COLUMN `{$kolom}_lama` BIGINT UNSIGNED NULL AFTER `{$kolom}`");
            DB::statement(
                "UPDATE `{$tabelPenunjuk}` p JOIN `{$tabel}` t ON p.`{$kolom}` = t.`id` SET p.`{$kolom}_lama` = t.`id_lama`"
            );
        }

        foreach ($fk as [$tabelPenunjuk, $kolom, $nama]) {
            self::lepasForeignKey($tabelPenunjuk, $nama);
        }

        DB::statement("ALTER TABLE `{$tabel}` DROP PRIMARY KEY");
        DB::statement("ALTER TABLE `{$tabel}` DROP COLUMN `id`");
        DB::statement("ALTER TABLE `{$tabel}` CHANGE `id_lama` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");

        foreach ($penunjuk as [$tabelPenunjuk, $kolom]) {
            DB::statement("ALTER TABLE `{$tabelPenunjuk}` DROP COLUMN `{$kolom}`");
            DB::statement("ALTER TABLE `{$tabelPenunjuk}` CHANGE `{$kolom}_lama` `{$kolom}` BIGINT UNSIGNED NULL");
        }

        foreach ($fk as [$tabelPenunjuk, $kolom, $nama, $onDelete]) {
            self::pasangForeignKey($tabelPenunjuk, $kolom, $tabel, $nama, $onDelete);
        }

        self::pasangUlangIndex($index);
    }

    private static function tambahKolomUlid(string $tabel): void
    {
        Schema::table($tabel, function (Blueprint $table) {
            $table->char('id_ulid', 26)->nullable()->after('id');
        });
    }

    /**
     * Mengisi ULID baris yang sudah ada, dalam urutan id lama.
     *
     * Urutan itu bukan kerapian: ULID membawa waktu pembuatannya di depan, dan
     * membangkitkannya berurutan membuat baris lama tetap terurut sama seperti
     * sebelumnya. Riwayat nilai yang diurutkan berdasarkan kunci -- dan panel
     * dewan juri melakukannya -- tidak berubah urutannya setelah migrasi.
     */
    private static function isiUlid(string $tabel): void
    {
        DB::table($tabel)->orderBy('id')->select('id')->chunk(500, function ($baris) use ($tabel) {
            foreach ($baris as $satu) {
                DB::table($tabel)->where('id', $satu->id)->update(['id_ulid' => (string) Str::ulid()]);
            }
        });
    }

    private static function tambahKolomPenunjukUlid(string $tabelPenunjuk, string $kolom): void
    {
        Schema::table($tabelPenunjuk, function (Blueprint $table) use ($kolom) {
            $table->char($kolom.'_ulid', 26)->nullable()->after($kolom);
        });
    }

    private static function isiPenunjukUlid(string $tabel, string $tabelPenunjuk, string $kolom): void
    {
        DB::statement(
            "UPDATE `{$tabelPenunjuk}` p JOIN `{$tabel}` t ON p.`{$kolom}` = t.`id` SET p.`{$kolom}_ulid` = t.`id_ulid`"
        );
    }

    /**
     * Membongkar kunci lama dan memasang ULID sebagai gantinya.
     *
     * `MODIFY` lebih dulu melepas sifat auto-increment: MySQL menolak
     * membuang kunci utama selama kolomnya masih auto-increment, dengan pesan
     * yang tidak menyebut sebab itu sama sekali.
     */
    private static function tukarKunciUtama(string $tabel): void
    {
        DB::statement("ALTER TABLE `{$tabel}` MODIFY `id` BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE `{$tabel}` DROP PRIMARY KEY");
        DB::statement("ALTER TABLE `{$tabel}` DROP COLUMN `id`");
        DB::statement("ALTER TABLE `{$tabel}` CHANGE `id_ulid` `id` CHAR(26) NOT NULL");
        DB::statement("ALTER TABLE `{$tabel}` ADD PRIMARY KEY (`id`)");
    }

    private static function tukarKolomPenunjuk(string $tabelPenunjuk, string $kolom): void
    {
        DB::statement("ALTER TABLE `{$tabelPenunjuk}` DROP COLUMN `{$kolom}`");
        DB::statement("ALTER TABLE `{$tabelPenunjuk}` CHANGE `{$kolom}_ulid` `{$kolom}` CHAR(26) NULL");
    }

    /**
     * Membaca definisi foreign key yang ada supaya bisa dipasang kembali persis
     * seperti semula.
     *
     * Aturan ON DELETE-nya ikut dibaca. Menebaknya berarti mengganti
     * `cascade` jadi `set null` tanpa ada yang menyadarinya, dan yang
     * tertinggal adalah baris nilai tanpa partai setelah satu penghapusan.
     *
     * @param  list<array{0: string, 1: string}>  $penunjuk
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private static function simpanDefinisiForeignKey(array $penunjuk): array
    {
        $hasil = [];

        foreach ($penunjuk as [$tabelPenunjuk, $kolom]) {
            $baris = DB::selectOne(
                'SELECT k.CONSTRAINT_NAME nama, r.DELETE_RULE aturan
                 FROM information_schema.KEY_COLUMN_USAGE k
                 JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                   ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA
                 WHERE k.TABLE_SCHEMA = DATABASE()
                   AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ?
                   AND k.REFERENCED_TABLE_NAME IS NOT NULL',
                [$tabelPenunjuk, $kolom],
            );

            // Kolom penunjuk tanpa foreign key tetap sah -- judge_inputs.score_event_id
            // memang sengaja dibiarkan tanpa constraint sejak migrasi aslinya.
            if ($baris !== null) {
                $hasil[] = [$tabelPenunjuk, $kolom, $baris->nama, $baris->aturan];
            }
        }

        return $hasil;
    }

    private static function lepasForeignKey(string $tabel, string $nama): void
    {
        DB::statement("ALTER TABLE `{$tabel}` DROP FOREIGN KEY `{$nama}`");
    }

    private static function pasangForeignKey(
        string $tabelPenunjuk,
        string $kolom,
        string $tabelTujuan,
        string $nama,
        string $onDelete,
    ): void {
        $aturan = match (strtoupper($onDelete)) {
            'CASCADE' => 'ON DELETE CASCADE',
            'SET NULL' => 'ON DELETE SET NULL',
            default => '',
        };

        DB::statement(
            "ALTER TABLE `{$tabelPenunjuk}` ADD CONSTRAINT `{$nama}` "
            ."FOREIGN KEY (`{$kolom}`) REFERENCES `{$tabelTujuan}` (`id`) {$aturan}"
        );
    }
}
