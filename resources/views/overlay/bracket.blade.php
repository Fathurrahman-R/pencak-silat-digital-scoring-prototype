{{--
    Bagan antar partai. Statis, tanpa Alpine/Echo -- susunan bagan tidak
    berubah selama satu partai berlangsung, jadi tidak ada yang perlu
    disegarkan realtime di sini. Kelasnya dipilih lewat ?kelas=ID (lihat
    OverlayController::bracket), karena satu turnamen bisa punya ratusan
    kelas dan overlay tidak bisa menebak mana yang mau ditayangkan.

    Bagannya adalah komponen yang SAMA PERSIS dengan halaman bagan panitia
    (<x-si.pohon-bagan>, koordinatnya dari App\Support\Bagan\PohonBagan), bukan
    versi siaran tersendiri. Panitia, penonton, dan pemirsa siaran membaca satu
    undian yang sama; dua gambar berbeda untuk satu undian membuat ketiganya
    berdebat tentang bagan yang sebenarnya identik.

    Karena itu ia juga digambar di atas bidang kertas terang, sama seperti
    papan hasil -- bagan panitia hidup di atas kartu putih, dan menyalinnya
    apa adanya berarti membawa bidang itu serta.
--}}

<x-layouts.overlay title="Bagan" :realtime="false">
    <div class="relative h-full w-full p-[64px]">
        @if (! $bracket)
            <p class="text-[18px] text-silat-teks-redup">
                Tambahkan <span class="silat-angka text-silat-teks">?kelas=ID</span> ke alamat sumber ini untuk memilih kelas tanding yang ditayangkan.
            </p>
        @else
            @php
                /*
                 * Kanvas siaran terkunci 1920x1080 dan tidak bisa digulir: bagan
                 * 16 peserta lebih tinggi dari itu, dan yang hilang adalah
                 * separuh bawah undian. Karena ukuran pohonnya dihitung di
                 * server (App\Support\Bagan\PohonBagan), skala yang pas bisa
                 * ditentukan di sini juga -- bukan ditebak lewat JS setelah
                 * halaman digambar, yang berarti satu kedipan di siaran.
                 *
                 * Diperkecil, bukan digambar ulang: yang tampil tetap bagan
                 * yang sama persis dengan layar panitia.
                 */
                $kepalaTinggi = 90;   // eyebrow + judul + jaraknya
                $bantalan = 56;       // padding kartu, atas + bawah
                $tepi = 128;          // margin kanvas, kiri + kanan / atas + bawah

                $skala = min(
                    1,
                    (1920 - $tepi - $bantalan) / max(1, $pohon['lebar']),
                    (1080 - $tepi - $bantalan - $kepalaTinggi) / max(1, $pohon['tinggi'] + 28),
                );
            @endphp

            <div class="inline-block rounded-silat-besar bg-silat-siaran-kertas p-7"
                 style="box-shadow: 0 20px 80px rgba(0,0,0,.55); transform: scale({{ round($skala, 4) }}); transform-origin: top left">
                <div class="mb-5">
                    <p class="silat-angka text-[13px] font-bold tracking-[.2em] text-silat-latar uppercase">Bagan</p>
                    <h1 class="mt-1 text-[26px] leading-tight font-semibold tracking-[-0.02em] text-silat-latar">
                        {{ $weightClass->jenis_kelamin->label() }} {{ $weightClass->golongan_usia->label() }} — {{ $weightClass->name }}
                    </h1>
                </div>

                <x-si.pohon-bagan :pohon="$pohon" />
            </div>
        @endif
    </div>
</x-layouts.overlay>
