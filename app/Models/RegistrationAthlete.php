<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Baris pivot pendaftaran-atlet, sebagai model.
 *
 * Ada supaya `attach()` menembakkan observer. Pivot tanpa model menulis lewat
 * query builder, jadi SinkronObserver tidak pernah mendengarnya: pendaftaran
 * yang dibuat di node global SESUDAH laptop gelanggang terpasang tiba di sana
 * tanpa satu atlet pun -- partai yang memanggil sudut tanpa nama.
 *
 * Bertambah-otomatis, tidak seperti pivot bawaan: tabelnya punya kolom `id`,
 * dan id itulah penanda barisnya di `sinkron_keluar`. Tanpa ini model yang
 * baru disimpan tidak tahu id-nya sendiri, dan catatannya menunjuk ke kosong.
 */
class RegistrationAthlete extends Pivot
{
    protected $table = 'registration_athlete';

    public $incrementing = true;
}
