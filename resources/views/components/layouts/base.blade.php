@props([
    'title' => null,

    /*
     * `texture` dan `backdrop` dipertahankan sebagai prop supaya 20+ pemanggil
     * tidak putus, tapi keduanya tidak lagi menggambar apa pun. Grid dan
     * butiran noise adalah warisan RizzxxUI: keduanya ada untuk membuat panel
     * kaca terbaca sebagai kaca, dan kacanya sendiri sudah dibuang.
     *
     * Prop-nya ikut hilang di Tahap 4, saat tidak ada lagi yang memanggilnya.
     */
    'texture' => true,
    'backdrop' => 'page',
])

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
@php($shell = $backdrop === 'shell')

<body @class([
    'min-h-screen font-sans text-body leading-relaxed text-ink antialiased',
    'bg-shell' => $shell,
    'bg-surface' => ! $shell,
])>
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
