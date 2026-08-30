<?php

/*
 * Label penomoran halaman.
 *
 * Sebelumnya berbunyi '&laquo; Sebelumnya' -- entitas HTML mentah, warisan
 * berkas bahasa bawaan Laravel. Di view kami keduanya dipakai di dalam
 * <span class="sr-only">, dan `{{ }}` meloloskan entitasnya apa adanya, jadi
 * pembaca layar melafalkan "ampersand l a q u o titik koma" sebelum kata yang
 * sesungguhnya.
 *
 * Panah kirinya sudah digambar ikon di sebelahnya; teks ini hanya untuk yang
 * tidak melihat ikon itu, jadi ia cukup berisi kata.
 */

return [
    'previous' => 'Halaman sebelumnya',
    'next' => 'Halaman berikutnya',
];
