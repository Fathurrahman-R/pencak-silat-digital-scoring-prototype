@props([
    'title' => null,

    /*
     * Latar shell dipakai halaman yang punya sidebar dan topbar mengambang;
     * sisanya duduk langsung di atas permukaan kertas.
     *
     * Menggantikan prop `backdrop` bernilai 'page'|'shell' dan prop `texture`
     * yang sudah lama tidak menggambar apa pun -- grid dan butiran noise ada
     * untuk membuat panel kaca terbaca sebagai kaca, dan kacanya sendiri sudah
     * dibuang.
     */
    'shell' => false,
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
