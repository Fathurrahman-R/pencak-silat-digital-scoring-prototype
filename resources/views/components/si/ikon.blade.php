@props([
    'nama',
    'class' => 'size-4',
])

{{--
    Pembungkus tipis di atas mallardduck/blade-lucide-icons.

    `nama` adalah nama ikon Lucide apa adanya — lihat https://lucide.dev/icons.
    Dirender sebagai SVG inline, jadi tidak ada permintaan berkas tambahan dan
    warnanya ikut `currentColor`. Nama yang salah melempar galat saat render,
    bukan diam-diam kosong.

    Kembaran <x-ui.icon> yang dipindahkan ke sini. si/tombol dan si/badge
    memanggilnya, dan selama panggilan itu menunjuk ke `ui/`, lapisan komponen
    baru tidak bisa berdiri tanpa lapisan lama yang justru sedang dihapus.

    Bedanya dengan <x-silat.ikon>: yang itu piktogram pencak silat yang
    digambar sendiri — pukulan, tendangan, jatuhan, tangga sanksi — karena
    tidak ada set ikon umum yang punya lambangnya. Yang ini ikon antarmuka
    umum: panah, centang, pensil.

    Ikon di sistem ini tidak pernah berdiri sendiri sebagai tombol. Ia selalu
    berdampingan dengan kata, karena orang yang jarang memakai aplikasi tidak
    menebak arti gambar — lihat docs/BRIEF-DESAIN.md §6.
--}}

<x-dynamic-component :component="'lucide-'.$nama"
                     {{ $attributes->class($class) }}
                     stroke-width="2"
                     aria-hidden="true" />
