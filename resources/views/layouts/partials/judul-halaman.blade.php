{{--
    Kepala halaman: judul, keterangan, dan tombol aksinya.

    Jejak halaman TIDAK di sini lagi — DESIGN-SYSTEM.md §6 menempatkannya di
    bilah atas, dan urutan wajib di dalam `main` adalah judul → toolbar aksi
    (rata kanan) → tab → toolbar massal → konten.

    Judul tidak pernah dipotong: judul yang terpotong menghilangkan
    satu-satunya penanda halaman mana yang sedang dibuka. Membungkus ke baris
    kedua lebih baik daripada "Pengg…".

    Tombol aksi selalu di BARIS SENDIRI di bawah judul dan keterangannya, di
    semua lebar layar -- bukan hanya saat layar sempit. Sebaris dengan judul,
    tombolnya menarik keterangan halaman jadi kolom sempit yang membungkus
    lebih awal, dan judul dua baris menggeser tombolnya turun sendiri sehingga
    tingginya tidak pernah sama antar halaman. Di baris sendiri, keduanya
    memakai lebar penuh dan letaknya tetap sama di setiap halaman.

    Rata kanan di baris itu: tombol utama tetap berada di tempat yang sama
    dengan tombol utama layar lain, yaitu tepi kanan bidang isi.
--}}

@if ($heading || $description || $actions)
    <div class="flex flex-col gap-5">
        <div class="min-w-0">
            @if ($heading)
                <h1 class="text-[30px] leading-tight font-semibold tracking-[-0.025em] text-balance text-ink">{{ $heading }}</h1>
            @endif

            @if ($description)
                <p class="mt-1.5 max-w-[70ch] text-[15px] leading-[1.5] text-ink-muted">{{ $description }}</p>
            @endif
        </div>

        {{-- Rata kanan, tapi tetap di barisnya sendiri. Maksimal tiga tombol;
             sisanya masuk menu titik-tiga (§7). Urutan kiri→kanan: halus,
             sekunder, utama — yang utama paling dekat tepi kanan. --}}
        @if ($actions)
            <div class="flex flex-wrap items-center justify-end gap-2">{{ $actions }}</div>
        @endif
    </div>
@endif
