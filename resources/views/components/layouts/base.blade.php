@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

    @include('layouts.partials.theme-script')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
{{--
    Satu latar untuk semua halaman: permukaan kertas, rata.

    Sebelumnya halaman beraplikasi memakai `bg-shell` — semburat aksen di pojok
    kiri atas. Semburat itu ada untuk satu alasan saja: panel kaca butuh
    sesuatu yang bergradasi di belakangnya, karena di atas warna rata kaca
    hanya jadi kotak abu-abu. Kacanya sudah dibuang, jadi semburatnya ikut.
--}}
<body class="min-h-screen overflow-x-hidden bg-surface font-sans text-body leading-relaxed text-ink antialiased">
    {{--
        x-data kosong di pembungkus ini bukan formalitas: Alpine hanya
        memproses elemen yang punya leluhur ber-x-data. Tanpanya, setiap
        x-on:click="$dispatch(…)" yang berdiri sendiri — pemicu modal di tabel,
        tombol ciut sidebar, tombol ⌘K di topbar — diam saja tanpa error.
    --}}
    <div x-data class="relative z-10">
        {{ $slot }}
    </div>

    <x-si.pesan-kilat />

    @stack('scripts')
</body>
</html>
