<?php

use App\Broadcasting\ArenaChannelAuthorizer;
use App\Broadcasting\JurusPenampilanChannelAuthorizer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Satu gelanggang, satu channel privat untuk seluruh petugas yang berhak
 * melihat jalannya partai di sana. Logikanya di ArenaChannelAuthorizer,
 * bukan di sini, supaya bisa diuji langsung.
 *
 * `public-live.{arena}` sengaja TIDAK didaftarkan di sini: namanya tidak
 * diawali `private-`/`presence-`, jadi ia otomatis jadi channel publik
 * tanpa autentikasi -- persis yang dibutuhkan halaman live score nanti.
 */
Broadcast::channel('arena.{arenaId}', ArenaChannelAuthorizer::class);

/*
 * Jurus disiarkan lewat channel per-penampilan, bukan per-gelanggang.
 *
 * Alamat panelnya memang per penampilan, dan `jurus_performances.arena_id`
 * boleh kosong -- nomor yang dinilai di luar jadwal gelanggang tetap harus
 * bisa disiarkan. Battle punya channel sendiri karena halaman perbandingan
 * membaca dua penampilan sekaligus.
 */
Broadcast::channel('jurus.penampilan.{penampilanId}', JurusPenampilanChannelAuthorizer::class);
Broadcast::channel('jurus.battle.{battleId}', JurusPenampilanChannelAuthorizer::class);
