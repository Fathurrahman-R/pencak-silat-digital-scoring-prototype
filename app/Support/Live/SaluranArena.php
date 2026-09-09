<?php

namespace App\Support\Live;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;

/**
 * Daftar channel siaran untuk satu gelanggang.
 *
 * Kelima event scoring memakai daftar yang sama, dan daftar itu punya satu
 * cabang yang mahal: channel publik `public-live.{id}`. Selama ia disertakan,
 * setiap tekanan tombol juri mendorong muatan ke DUA channel Reverb --
 * padahal kejuaraan tanpa vMix dan tanpa live score tidak punya satu pun
 * pendengar di channel kedua itu.
 *
 * Channel presence TIDAK PERNAH dilepas, apa pun saklarnya: di situlah panel
 * juri, wasit, operator, dan ketua pertandingan mendengarkan. Mematikan
 * siaran publik tidak boleh berarti mematikan pertandingannya.
 *
 * Helper statis, bukan trait: tiap event mengambil arena_id lewat jalurnya
 * sendiri (round->match, scoreEvent->match, penalty->match, match,
 * input->match), jadi trait tidak menghemat apa pun sambil menyembunyikan
 * daftar channelnya di balik pewarisan.
 */
class SaluranArena
{
    /** @return array<int, Channel> */
    public static function untuk(?int $arenaId): array
    {
        $saluran = static::presensi($arenaId);

        if ($saluran !== [] && static::siaranPublikMenyala()) {
            $saluran[] = new Channel('public-live.'.$arenaId);
        }

        return $saluran;
    }

    /**
     * Hanya channel presence gelanggang, tanpa cabang siaran publik.
     *
     * Dipakai Jurus. Papan penonton dan overlay vMix belum menampilkan Jurus,
     * dan memanggil untuk() supaya "seragam" akan membuka `public-live.{id}`
     * untuknya begitu salah satu saklar menyala -- channel yang belum ada
     * pendengarnya hari ini, tapi yang perubahan berikutnya akan isi dengan
     * muatan bernama juri tanpa ada yang ingat bahwa ia terbuka.
     *
     * Kalau nanti Jurus memang ditayangkan ke penonton, yang berubah di sini
     * satu baris -- dan perubahan itu akan terbaca sebagai keputusan.
     *
     * @return array<int, Channel>
     */
    public static function presensi(?int $arenaId): array
    {
        // Baris yang belum dijadwalkan ke gelanggang tidak punya channel untuk
        // disiarkan -- datanya tetap tersimpan di database, hanya siarannya
        // yang tidak ada tujuan.
        if ($arenaId === null) {
            return [];
        }

        return [new PresenceChannel('arena.'.$arenaId)];
    }

    /**
     * Cukup salah satu saklar menyala. Overlay vMix dan live score publik
     * mendengarkan channel yang sama, jadi channel itu baru boleh dilepas
     * ketika tidak ada satu pun dari keduanya yang dipakai.
     *
     * Sengaja dibaca lewat config() tiap kali dipanggil, bukan disimpan ke
     * properti statis: nilai yang dibekukan saat boot membuat saklar ini
     * mustahil dibalik di dalam satu uji.
     */
    private static function siaranPublikMenyala(): bool
    {
        return config('overlay.enabled') || config('live.enabled');
    }
}
