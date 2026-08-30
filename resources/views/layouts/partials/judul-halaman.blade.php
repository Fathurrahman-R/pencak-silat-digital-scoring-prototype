@php($breadcrumb ??= [])

{{--
    Kepala halaman: jejak, judul, keterangan, dan tombol aksinya.

    Ia berdiri di atas isi halaman, sebidang dengan isinya, bukan di dalam
    bilah utilitas. Alasannya lebar: jejak halaman aplikasi ini bisa empat
    tingkat dan salah satunya nama kejuaraan yang panjang, sementara sebagian
    layar membawa empat tombol ekspor sekaligus. Di dalam bilah setinggi tetap,
    keduanya bertabrakan dengan kolom cari dan judulnya terselip di antara
    jejak yang membungkus. Di sini ia punya selebar halaman, dan boleh
    membungkus ke bawah tanpa menggeser apa pun.

    Judul sebaris dengan tombol aksinya — aksi itu milik halaman ini, dan
    letaknya menyatakan itu tanpa perlu kata.

    Ikut menggulir bersama isi, tidak menempel: yang perlu selalu terjangkau
    adalah cari dan akun, dan keduanya tinggal di bilah yang menempel.
--}}

@if ($breadcrumb !== [] || $heading || $description || $actions)
    <div>
        {{-- Jejak berdiri di barisnya sendiri, selebar halaman. Sebaris dengan
             judul ia berbagi ruang dengan tombol aksi, dan di layar yang
             tombolnya banyak — empat tombol ekspor di Rekap — sisanya tidak
             cukup untuk jejak sedalam empat tingkat. Ia lalu membungkus, dan
             judul di bawahnya ikut turun. --}}
        @if ($breadcrumb !== [])
            <x-si.jejak :daftar="$breadcrumb" :akar="config('app.name')" class="mb-1.5 min-w-0" />
        @endif

        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">

            {{-- `basis-full` di bawah sm memaksa tombol turun ke barisnya
                 sendiri. Tanpa itu keduanya berbagi satu baris di layar
                 sempit, dan yang menyusut selalu yang kiri — judul halaman
                 terpotong jadi "Pengg…" sementara tombol di kanan tetap
                 utuh. --}}
            <div class="min-w-0 basis-full sm:basis-0 sm:flex-1">
                @if ($heading)
                    {{-- Tidak dipotong: judul yang terpotong menghilangkan
                         satu-satunya penanda halaman mana yang sedang dibuka.
                         Membungkus ke baris kedua lebih baik daripada
                         "Pengg…". --}}
                    <h1 class="text-[22px] leading-tight font-semibold text-balance text-ink">{{ $heading }}</h1>
                @endif

                @if ($description)
                    <p class="mt-1 max-w-[70ch] text-[14px] leading-relaxed text-ink-muted">{{ $description }}</p>
                @endif
            </div>

            @if ($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endif
        </div>
    </div>
@endif
