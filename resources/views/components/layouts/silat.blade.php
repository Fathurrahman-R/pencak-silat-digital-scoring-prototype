@props([
    'title' => null,

    /*
     * Dua permukaan memakai layout ini:
     *
     * - `gelanggang` (bawaan) — panel juri, wasit, operator, Dewan Wasit Juri.
     *   Gelap permanen, tidak ikut saklar suasana: dibaca dari tepi matras dan
     *   difoto kamera siaran.
     * - `publik` — beranda, live score, bagan, medali. TERANG bawaan dengan
     *   saklar manual, karena dibuka di HP di bawah matahari (BRIEF §9).
     */
    'permukaan' => 'gelanggang',

    /*
     * Apakah halaman ini membuka koneksi WebSocket ke Reverb.
     *
     * Bawaannya TRUE, dan arah itu disengaja: halaman statis yang lupa
     * menyetelnya hanya menyisakan koneksi menganggur, sementara panel juri
     * yang lupa menyetelnya kehilangan seluruh realtime-nya tanpa satu pun
     * tanda di layar. Halaman yang memang tidak butuh -- turnamen, medali,
     * bagan, beranda -- menyatakannya sendiri dengan :realtime="false".
     */
    'realtime' => true,

    /*
     * Manifest PWA halaman ini, kalau ada.
     *
     * `crossorigin="use-credentials"` bukan hiasan: manifest diambil peramban
     * tanpa kredensial secara bawaan, jadi tanpa atribut ini permintaannya
     * masuk sebagai tamu, kena redirect ke /login, dan yang diterima HTML --
     * peramban menolaknya dengan "Manifest: Line: 1, column: 1, Syntax error"
     * dan panelnya tidak pernah bisa dipasang di layar utama.
     */
    'manifest' => null,
])

@php($publik = $permukaan === 'publik')

{{--
    Layout gelanggang dan wajah publik.

    Berdiri sendiri dari layout admin. Yang dimuat hanya bundel silat, jadi
    token admin tidak pernah ikut masuk dan tidak mungkin bertabrakan.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @if ($manifest)
        <link rel="manifest" href="{{ $manifest }}" crossorigin="use-credentials">
        <link rel="icon" href="/icons/juri.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/juri.svg">
    @endif
    @if ($publik)
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">

        {{-- Dijalankan sebelum CSS dimuat supaya halaman tidak berkedip saat
             pembaca sudah memilih suasana gelap. Bawaannya TERANG: tanpa
             pilihan tersimpan, halaman publik selalu terang, tidak mengikuti
             setelan sistem — papan pengumuman kejuaraan dibaca di luar
             ruangan. --}}
        <script>
            (function () {
                document.documentElement.setAttribute(
                    'data-theme',
                    localStorage.getItem('tema-publik') === 'dark' ? 'dark' : 'light'
                );
            })();
        </script>
    @else
        {{-- Panel juri dipakai di HP dan dipegang satu tangan. Zoom dimatikan
             supaya cubitan tak sengaja tidak menggeser tombol saat ditekan cepat. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="color-scheme" content="dark">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @unless ($realtime)
        <meta name="realtime" content="0">
    @endunless

    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/silat.css', 'resources/js/silat.js'])

    @stack('head')
</head>

<body class="silat antialiased {{ $publik ? 'silat-publik' : 'silat-panggung' }}">
    <div x-data>
        {{ $slot }}
    </div>

    @if ($publik)
        <script>
            document.addEventListener('click', function (e) {
                if (! e.target.closest('[data-saklar-suasana]')) {
                    return;
                }

                var gelap = document.documentElement.getAttribute('data-theme') === 'dark';
                document.documentElement.setAttribute('data-theme', gelap ? 'light' : 'dark');
                localStorage.setItem('tema-publik', gelap ? 'light' : 'dark');
            });
        </script>
    @endif

    @stack('scripts')
</body>
</html>
