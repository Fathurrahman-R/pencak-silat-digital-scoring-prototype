<?php

use App\Broadcasting\ArenaChannelAuthorizer;
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
