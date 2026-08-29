@props([
    'title' => 'Detail',
    'breadcrumb' => [],
])

{{--
    Pembungkus untuk panel detail yang biasanya dimuat ke dalam drawer.

    Isi panel adalah fragmen HTML tanpa layout -- itu memang yang dibutuhkan
    <x-si.panel-rincian>, yang menyuntikkannya lewat x-html. Tapi rutenya
    adalah GET biasa: bisa di-bookmark, dibuka di tab baru, atau sekadar
    ter-refresh. Tanpa pembungkus ini, ketiga hal itu menghasilkan halaman
    telanjang tanpa CSS -- teks polos dengan ikon SVG raksasa -- yang terbaca
    seperti aplikasi rusak, bukan seperti alamat yang memang bukan untuk
    dikunjungi langsung.

    Pembedanya header `X-Requested-With` yang dikirim drawer. Kalau ada,
    fragmennya keluar apa adanya seperti sebelumnya; kalau tidak, isinya
    berdiri sebagai halaman utuh.
--}}

@if (request()->ajax())
    {{ $slot }}
@else
    <x-layouts.admin :title="$title" :breadcrumb="$breadcrumb">
        <x-si.kartu>
            {{ $slot }}
        </x-si.kartu>
    </x-layouts.admin>
@endif
