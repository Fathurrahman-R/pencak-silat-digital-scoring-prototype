@props([
    'judul',
    'pesan',
    'petunjuk' => null,
])

{{--
    Halaman yang muncul menggantikan overlay siaran atau live score gelanggang
    saat saklarnya dimatikan.

    SENGAJA TANPA LAYOUT, dan itu bukan soal selera. Kedua layout yang wajar
    dipakai di sini -- x-layouts.overlay dan x-layouts.silat -- sama-sama
    memuat resources/js/silat.js, yang mengimpor echo.js dan membuka koneksi
    WebSocket ke Reverb begitu halaman dimuat. Halaman yang justru dibuat
    untuk memangkas beban siaran tidak boleh menahan satu koneksi Reverb
    selama vMix membiarkannya terbuka berjam-jam.

    Karena itu: nol @vite JavaScript, nol x-data, nol <script>. Hanya CSS.

    Latarnya TIDAK transparan seperti layout overlay. Overlay transparan
    supaya alpha channel vMix bekerja; halaman ini justru harus terbaca
    sebagai halaman, termasuk saat seseorang membukanya langsung di browser
    untuk mencari tahu kenapa grafisnya kosong.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $judul }}</title>

    @vite(['resources/css/silat.css'])
</head>

<body class="silat" style="margin:0;">
    <main class="flex min-h-screen flex-col items-center justify-center gap-4 px-6 text-center">
        <p class="text-[11px] tracking-[.28em] text-silat-teks-redup uppercase">Siaran</p>

        <h1 class="text-2xl font-semibold text-silat-teks">{{ $judul }}</h1>

        <p class="max-w-md text-silat-teks-redup">{{ $pesan }}</p>

        @if ($petunjuk)
            <p class="max-w-md text-[13px] text-silat-teks-samar">{{ $petunjuk }}</p>
        @endif
    </main>
</body>
</html>
