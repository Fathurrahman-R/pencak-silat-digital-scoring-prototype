@props([
    'title' => null,
    'heading' => null,
    'description' => null,
    'breadcrumb' => [],
])

{{--
    Kerangka panitia: dua kolom yang menempel penuh ke tepi layar.

    Sebelumnya ia bidang berpadding 16px dengan dua panel kaca mengambang di
    atas latar bersemburat aksen — rupa warisan boilerplate. Brief §3 menuntut
    yang sebaliknya untuk panitia: "kertas kerja, kontras tinggi, tanpa kaca
    dan tanpa noise". Yang dibuang bukan cuma efek kacanya, melainkan seluruh
    gagasan panel mengambang:

      - Nol padding di luar. Di layar 1366px yang dipakai panitia, 32px yang
        hilang di kiri-kanan itu satu kolom tabel penuh.
      - Nol jarak antar panel. Sidebar dan isi dipisahkan SATU garis, bukan
        16px ruang kosong plus dua tepi panel.
      - Nol sudut membulat di tepi layar, nol bayangan mengambang.

    Judul halaman pindah ke dalam bilah kepala, bukan blok terpisah di
    bawahnya. Sebelumnya jejak halaman dan tombol utilitas duduk di satu
    bidang, lalu judul dan tombol aksinya di bidang lain — dua kepala untuk
    satu halaman, dan judulnya tidak pernah sebaris dengan aksinya.
--}}

<x-layouts.base :title="$title ?? $heading">
    <div class="flex min-h-screen">
        @include('layouts.partials.sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            @include('layouts.partials.kepala', [
                'breadcrumb' => $breadcrumb,
                'heading' => $heading,
                'description' => $description,
                'actions' => $actions ?? null,
            ])

            <main class="flex flex-1 flex-col gap-4 px-4 py-5 sm:px-6">
                {{--
                    Ringkasan galat validasi berdiri satu kali di sini, bukan
                    diulang di tiap halaman. Sebagian besar formulir aplikasi
                    ini tinggal di dalam modal; modalnya tertutup lagi setelah
                    halaman digambar ulang, jadi tanpa ringkasan ini pengguna
                    melihat halaman yang tampak tidak berubah dan tidak tahu
                    permintaannya ditolak.
                --}}
                @if ($errors->any())
                    <x-si.callout varian="bahaya" judul="Ada yang perlu diperbaiki">
                        @if ($errors->count() === 1)
                            {{ $errors->first() }}
                        @else
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $pesan)
                                    <li>{{ $pesan }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </x-si.callout>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    <x-si.cari-menu />
</x-layouts.base>
