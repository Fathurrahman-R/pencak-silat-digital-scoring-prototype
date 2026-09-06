<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Menolak berjalan kalau yang tersambung bukan database uji.
     *
     * RefreshDatabase menjalankan `migrate:fresh` -- ia MENGHAPUS seluruh isi
     * database yang sedang tersambung. Nama database uji ditetapkan lewat
     * `<env>` di phpunit.xml, dan itu hanya berlaku selama konfigurasi belum
     * di-cache: sesudah `php artisan optimize` (atau `config:cache`), Laravel
     * membaca bootstrap/cache/config.php dan tidak pernah lagi melihat env
     * apa pun. Seluruh uji lalu menunjuk ke database SUNGGUHAN, dan uji
     * pertama yang berjalan menghapus kejuaraan yang sedang berlangsung.
     *
     * Tidak ada isyarat apa pun saat itu terjadi: perintahnya sama, keluarannya
     * hijau, dan datanya sudah hilang. Karena itu penjagaan ini berhenti
     * dengan galat, bukan peringatan.
     *
     * Dua lapis, dan urutannya disengaja. Yang pertama menolak konfigurasi
     * yang di-cache -- sebabnya. Yang kedua menolak nama database yang bukan
     * database uji -- gejalanya, dan jaring untuk keadaan lain yang belum
     * terpikirkan. Lapis pertama ditambahkan karena lapis kedua sendirian
     * punya lubang: nama sungguhan yang kebetulan berakhiran `_test` lolos
     * tanpa suara.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        // Diperiksa DI SINI, bukan di setUp(): aplikasinya baru ada sesudah
        // baris di atas, dan RefreshDatabase baru berjalan sesudah metode ini
        // selesai. Inilah satu-satunya titik yang punya konfigurasi sekaligus
        // masih sempat mencegah datanya dihapus.
        /*
         * Diperiksa LEBIH DULU daripada nama databasenya, karena inilah
         * sebabnya, bukan gejalanya.
         *
         * Konfigurasi yang di-cache membuat phpunit.xml mati total: Laravel
         * membaca bootstrap/cache/config.php dan tidak pernah lagi melihat
         * <env> mana pun. Nama database yang dipakai jadi apa pun yang
         * kebetulan terpanggang ke dalam cache itu -- dan kalau nama itu
         * kebetulan berakhiran `_test`, penjagaan di bawah meloloskannya
         * tanpa suara. Kejuaraan yang databasenya bernama `porprov_2026_test`
         * akan dihapus uji pertama yang berjalan.
         *
         * Karena itu keadaan ini ditolak apa pun nama databasenya. Uji yang
         * berjalan di atas konfigurasi ter-cache tidak menguji apa yang
         * dikiranya diuji: seluruh saklar di phpunit.xml -- siaran, bcrypt,
         * saklar pendaftaran -- ikut diabaikan.
         */
        if ($this->app->configurationIsCached()) {
            throw new RuntimeException(
                "Uji menolak berjalan: konfigurasi sedang di-cache.
"
                ."Dalam keadaan itu phpunit.xml tidak berlaku sama sekali -- termasuk nama database
"
                ."uji, jadi uji berikutnya bisa menghapus database sungguhan. Jalankan
"
                .'`php artisan optimize:clear` lebih dulu, lalu ulangi -- BUKAN `config:clear`, yang '
                ."meninggalkan cache rute, dan
berkas rute ter-cache menghabiskan memori PHP di tengah rangkaian uji."
            );
        }

        $koneksi = config('database.default');
        $database = (string) config("database.connections.{$koneksi}.database");

        // `:memory:` ikut diterima supaya penjagaan ini tidak menghalangi kalau
        // suatu saat uji dipindah ke SQLite dalam memori -- di sana tidak ada
        // data siapa pun yang bisa hilang.
        $aman = $database === ':memory:' || str_ends_with($database, '_test');

        if (! $aman) {
            throw new RuntimeException(
                "Uji menolak berjalan: database yang tersambung [{$database}] bukan database uji.\n"
                ."Penyebab yang paling lazim adalah konfigurasi yang sedang di-cache -- phpunit.xml\n"
                ."tidak berlaku sama sekali dalam keadaan itu. Jalankan `php artisan optimize:clear`\n"
                ."lebih dulu, lalu ulangi -- BUKAN `config:clear`, yang meninggalkan cache rute, dan\n"
                .'berkas rute ter-cache menghabiskan memori PHP di tengah rangkaian uji.'
            );
        }
    }
}
