<?php

namespace App\Support\Bagan\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Partai yang menempati satu tempat di bagan gugur.
 *
 * Dipenuhi dua hal yang bentuk tabelnya berbeda: partai Tanding (`matches`)
 * dan battle Jurus (`jurus_battles`). Keduanya menempati koordinat yang sama --
 * ronde dan posisi -- dan pemenangnya naik dengan aritmetika yang sama persis.
 *
 * Antarmuka ini ada supaya PromosiPemenang tidak perlu digandakan. Menggandakan
 * aritmetika bagan berarti dua tempat yang harus diperbaiki saat undian bergeser,
 * dan yang kedua akan tertinggal -- kekeliruan yang baru ketahuan setelah
 * pemenang naik ke slot yang salah di hari-H.
 */
interface Terbagankan
{
    /** Bagan tempat partai ini berdiri. */
    public function baganPartai(): BelongsTo;

    /**
     * Partai-partai lain di bagan yang sama.
     *
     * @return HasMany<static, Model>
     */
    public function sesamaBagan(): HasMany;

    /** Posisi partai penerima pemenang, di ronde berikutnya. */
    public function posisiBerikutnya(): int;

    /** 'red' atau 'blue' -- sudut yang ditempati pemenang di partai berikutnya. */
    public function sudutBerikutnya(): string;
}
