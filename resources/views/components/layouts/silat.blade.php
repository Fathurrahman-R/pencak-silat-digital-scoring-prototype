@props([
    'title' => null,
])

{{--
    Layout gelanggang: panel juri, wasit, operator, dewan juri, dan halaman
    live score publik.

    Berdiri sendiri dari layout admin. Yang dimuat hanya bundel silat, jadi
    token RizzxxUI tidak pernah ikut masuk dan tidak mungkin bertabrakan.

    Tidak ada `data-theme` di sini, dan itu disengaja: papan skor tidak punya
    mode terang. Ia dibaca dari tribun dan difoto kamera siaran, jadi warnanya
    tetap gelap dalam kondisi apa pun.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    {{-- Panel juri dipakai di HP dan dipegang satu tangan. Zoom dimatikan
         supaya cubitan tak sengaja tidak menggeser tombol saat ditekan cepat. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">

    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/silat.css', 'resources/js/silat.js'])

    @stack('head')
</head>

<body class="silat silat-panggung antialiased">
    <div x-data>
        {{ $slot }}
    </div>

    @stack('scripts')
</body>
</html>
