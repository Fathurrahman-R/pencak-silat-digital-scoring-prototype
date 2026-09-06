<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Membaca ulang satu berkas config dengan variabel env-nya dihapus.
 *
 * phpunit.xml mematok beberapa saklar untuk seluruh rangkaian uji, dan itu
 * memang perlu. Efek sampingnya: tidak ada satu pun uji yang bisa membuktikan
 * apa nilai BAWAAN saklar itu di instalasi yang .env-nya belum menyebutnya.
 *
 * Helper ini menutup celah itu. Ia membaca ulang berkas config dengan
 * variabel env dihapus, persis seperti instalasi baru yang .env-nya belum
 * menyebut saklar apa pun.
 */
function bacaConfigTanpaEnv(string $berkas, string $kunciEnv): array
{
    /*
     * Nilai dari <env> phpunit.xml mendarat di tiga tempat sekaligus:
     * getenv(), $_ENV, dan $_SERVER. Repository Env dibangun immutable, jadi
     * clear() padanya tidak berpengaruh -- ketiganya harus dikosongkan
     * langsung, lalu repository-nya dipaksa dibangun ulang lewat
     * Env::enablePutenv() (satu-satunya jalan umum yang mengosongkan cache
     * statisnya).
     */
    $sebelumnya = [
        'putenv' => getenv($kunciEnv),
        'env' => $_ENV[$kunciEnv] ?? null,
        'server' => $_SERVER[$kunciEnv] ?? null,
    ];

    $bangunUlang = function () {
        Env::enablePutenv();
    };

    putenv($kunciEnv);
    unset($_ENV[$kunciEnv], $_SERVER[$kunciEnv]);
    $bangunUlang();

    try {
        return require base_path("config/{$berkas}.php");
    } finally {
        if ($sebelumnya['putenv'] !== false) {
            putenv("{$kunciEnv}={$sebelumnya['putenv']}");
        }

        foreach (['env' => '_ENV', 'server' => '_SERVER'] as $kunci => $global) {
            if ($sebelumnya[$kunci] !== null) {
                $GLOBALS[$global][$kunciEnv] = $sebelumnya[$kunci];
            }
        }

        $bangunUlang();
    }
}
