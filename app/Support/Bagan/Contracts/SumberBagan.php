<?php

namespace App\Support\Bagan\Contracts;

use App\Enums\ModeBagan;
use Illuminate\Support\Collection;

/**
 * Bagan gugur yang bisa digambar PohonBagan.
 *
 * Dipenuhi dua tabel yang bentuknya berbeda: `brackets` untuk Tanding dan
 * `jurus_brackets` untuk nomor Jurus berformat battle. Keduanya menyimpan hal
 * yang sama -- tempat undian, partai per ronde, dan ukuran -- tapi rantai
 * relasinya berbeda sampai ke akar: bagan Tanding menggantung pada kelas
 * tanding (`weight_class_id`, NOT NULL dan unik), bagan Jurus pada nomor.
 * Alasan lengkap tabel terpisah ada di migrasi `buat_bagan_jurus`.
 *
 * Antarmuka ini menyebut LIMA hal yang benar-benar disentuh PohonBagan, dan
 * tidak satu pun di antaranya menyangkut kelas tanding. Karena itu
 * generalisasinya tidak memaksa `jurus_brackets` punya `weight_class_id`
 * maupun `mode` -- dua kolom yang untuknya hanya akan pernah berisi satu
 * nilai.
 *
 * Kenapa bukan memperluas Terbagankan: yang itu dipenuhi PARTAI (SilatMatch,
 * JurusBattle), bukan bagan. Menaruh pertanyaan bagan di sana memaksa tiap
 * partai menjawab tentang bracket-nya, dan PromosiPemenang yang bertipe
 * Terbagankan mendadak menerima objek yang setengah kontraknya tidak ia pakai.
 */
interface SumberBagan
{
    /** Ukuran bagan: jumlah tempat undian babak pertama. */
    public function ukuranBagan(): int;

    /** Gugur atau pemasalan. Bagan Jurus selalu gugur -- Pasal 12.1.b.1. */
    public function modeBagan(): ModeBagan;

    /**
     * Tempat undian babak pertama, urut posisi.
     *
     * Tiap baris punya `position`, `registration_id`, dan relasi `registration`.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function tempatBagan(): Collection;

    /**
     * Partai di bagan ini, urut ronde lalu posisi.
     *
     * Tiap baris punya `round`, `position`, relasi `red`/`blue`, dan `bye()`.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function partaiBagan(): Collection;

    /** Nama babak sebagaimana disebut panitia dan announcer. */
    public function namaBabak(int $round): string;
}
