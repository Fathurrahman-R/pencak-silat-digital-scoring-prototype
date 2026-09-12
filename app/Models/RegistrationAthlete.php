<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

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

    /**
     * Mencari id-nya sendiri sebelum dihapus.
     *
     * `detach()` menyusun model pivot dari dua kolom kuncinya saja -- id-nya
     * tidak ikut. Catatan sinkron menunjuk baris lewat id, jadi penghapusan
     * yang lewat begitu saja tercatat menunjuk ke kosong: node lain menerima
     * perintah hapus tanpa tahu baris mana, dan atlet yang dicabut dari
     * pendaftaran tetap berdiri di gelanggang.
     */
    public function delete(): int
    {
        if (! isset($this->attributes['id'])) {
            $this->attributes['id'] = DB::table($this->table)
                ->where('registration_id', $this->getAttribute('registration_id'))
                ->where('athlete_id', $this->getAttribute('athlete_id'))
                ->value('id');
        }

        return parent::delete();
    }
}
