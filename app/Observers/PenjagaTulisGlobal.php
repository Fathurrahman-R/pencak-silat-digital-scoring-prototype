<?php

namespace App\Observers;

use App\Exceptions\PenulisanDataKejuaraanDitolak;
use App\Support\Sinkron\Kepemilikan;
use Illuminate\Database\Eloquent\Model;

/**
 * Node gelanggang tidak boleh menulis data kejuaraan.
 *
 * # Apa yang terjadi tanpa ini
 *
 * Uji kotak hitam 11 September 2026: node berperan `gelanggang` membuat
 * kontingen lewat UI-nya sendiri. Layar menjawab berhasil, barisnya lahir
 * dengan id 7 -- dan `sinkron_keluar` tidak mencatat apa pun, karena
 * kepemilikan menolak mencatat baris GLOBAL di node gelanggang. Hasilnya data
 * hantu: hidup di satu laptop, tidak pernah sampai ke node global maupun
 * gelanggang lain, dan id 7 itu akan bertabrakan dengan kontingen id 7 yang
 * kelak lahir di node global. Penerapan paket meng-upsert menurut id, jadi
 * yang terjadi bukan kejuaraan ganda melainkan satu baris menimpa baris lain
 * yang artinya berbeda.
 *
 * Aturan satu penulis sudah ditulis di dokumen dan dipakai kepemilikan saat
 * MENCATAT. Yang belum ada penjagaannya adalah penulisannya sendiri.
 *
 * # Kenapa di observer, bukan di controller
 *
 * Penerapan paket menulis lewat query builder, jadi ia melewati observer ini
 * dengan sendirinya -- node gelanggang tetap boleh MENERIMA data global dari
 * node global. Yang dihadang cuma penulisan lewat model, yaitu yang datang
 * dari layar dan perintah di mesin itu.
 *
 * # Kolom teknis yang tetap boleh ditulis
 *
 * Masuk ke aplikasi memperbarui baris `users` (remember_token, dan rehash kata
 * sandi kalau biayanya berubah). Menolaknya berarti tidak ada yang bisa masuk
 * ke node gelanggang sama sekali -- penjagaan yang mengunci pintunya sendiri.
 */
class PenjagaTulisGlobal
{
    /**
     * Kolom yang boleh berubah di node gelanggang meski tabelnya GLOBAL.
     *
     * @var list<string>
     */
    private const KOLOM_TEKNIS = [
        'remember_token',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'updated_at',
    ];

    public function __construct(private readonly Kepemilikan $kepemilikan) {}

    public function creating(Model $model): void
    {
        $this->tolak($model, 'membuat');
    }

    public function updating(Model $model): void
    {
        $berubah = array_keys($model->getDirty());

        if (array_diff($berubah, self::KOLOM_TEKNIS) === []) {
            return;
        }

        $this->tolak($model, 'mengubah');
    }

    public function deleting(Model $model): void
    {
        $this->tolak($model, 'menghapus');
    }

    private function tolak(Model $model, string $kata): void
    {
        if (! config('sinkron.jaga_penulis_global', true)) {
            return;
        }

        /*
         * Mesin yang berdiri sendiri tidak menulis dua kali.
         *
         * Pemasangan satu laptop -- yang dipakai hampir semua kejuaraan --
         * berperan `gelanggang` tanpa satu peer pun, dan ia satu-satunya mesin
         * yang ada. Menjaganya berarti melumpuhkan seluruh administrasi
         * kejuaraan demi konflik yang tidak mungkin terjadi.
         *
         * Penjagaan ini hidup begitu mesin ini punya tetangga.
         */
        if ((array) config('sinkron.peer', []) === []) {
            return;
        }

        if ($this->kepemilikan->milikNodeIni($model->getTable(), $model->getAttributes())) {
            return;
        }

        $node = (string) config('sinkron.node');

        throw new PenulisanDataKejuaraanDitolak(
            "Mesin ini ({$node}) tidak boleh {$kata} data kejuaraan. "
            .'Data seperti ini ditulis di node global, lalu datang ke sini lewat sinkron — '
            .'kalau ditulis di dua tempat, keduanya akan mengaku benar dan salah satunya hilang tanpa pesan.',
        );
    }
}
