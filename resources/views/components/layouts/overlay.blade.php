@props([
    'title' => null,
])

{{--
    Layout overlay siaran, dipasang di vMix sebagai Web Browser Input.

    Aturan yang mengikat halaman ini, semuanya berasal dari kenyataan bahwa ia
    bukan halaman web biasa:

      - Latar wajib benar-benar transparan supaya alpha channel vMix bekerja.
        Satu deklarasi background yang terlewat akan muncul sebagai kotak hitam
        menutupi gambar kamera.
      - Kanvas dikunci 1920x1080, tidak responsif. Overlay dirender pada
        resolusi tetap; media query di sini hanya akan membuatnya meleset.
      - Nol interaksi. Tidak ada tombol, tidak ada dialog, tidak ada yang bisa
        diklik. vMix menyalakan halaman ini dan meninggalkannya berjam-jam.
      - Tidak ada scrollbar. Konten yang meluber akan terlihat di siaran.

    Rute yang memuat layout ini dibatasi jaringan lokal dan tidak pernah
    diteruskan lewat tunnel publik — Web Browser Input tidak bisa melakukan
    login, jadi pembatasan jaringan adalah satu-satunya pengamannya.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' — Overlay' : 'Overlay' }}</title>

    @vite(['resources/css/silat.css', 'resources/js/silat.js'])

    @stack('head')
</head>

<body class="silat" style="margin:0; background:transparent; overflow:hidden;">
    <div x-data class="silat-overlay">
        {{ $slot }}
    </div>

    @stack('scripts')
</body>
</html>
