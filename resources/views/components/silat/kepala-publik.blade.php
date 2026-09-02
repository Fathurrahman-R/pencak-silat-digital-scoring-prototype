@props([
    'judul',
    'keterangan' => null,
    'tautan' => [],
    'aktif' => null,
])

{{--
    Bilah kepala wajah publik — mengikuti `publik-beranda-live.dc.html`.

    Satu baris: lambang, nama kejuaraan berikut tempat dan tanggalnya, lalu
    tautan halaman publik dan saklar suasana di kanan. Saklar berdiri sendiri
    di sini, bukan di setelan yang harus dicari: halaman ini dibuka orang yang
    tidak punya akun dan tidak akan mencari menu.
--}}

<header class="flex items-center gap-4 border-b border-silat-garis px-5 py-4.5 sm:px-10">
    <span class="grid size-8 shrink-0 place-items-center rounded-silat bg-silat-aksi text-[13px] font-bold text-silat-aksi-teks">
        {{ mb_strtoupper(mb_substr($judul, 0, 1)) }}
    </span>

    <div class="min-w-0">
        <p class="truncate text-[14.5px] font-semibold tracking-[-0.01em] text-silat-teks">{{ $judul }}</p>
        @if ($keterangan)
            <p class="silat-angka mt-0.5 truncate text-[11.5px] text-silat-teks-redup">{{ $keterangan }}</p>
        @endif
    </div>

    <nav class="ml-auto flex shrink-0 items-center gap-2">
        @foreach ($tautan as $label => $url)
            <a href="{{ $url }}"
               @class([
                   'hidden h-9.5 items-center rounded-silat px-3.5 text-[14px] no-underline sm:inline-flex',
                   'bg-silat-panel font-semibold text-silat-teks' => $aktif === $label,
                   'text-silat-teks-kedua' => $aktif !== $label,
               ])>{{ $label }}</a>
        @endforeach

        <button type="button" data-saklar-suasana aria-label="Ganti suasana terang atau gelap"
                class="ml-1.5 grid size-9.5 shrink-0 place-items-center rounded-silat border border-silat-garis text-silat-teks-kedua">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">
                <path d="M12 3v1M12 20v1M4.2 4.2l.7.7M19.1 19.1l.7.7M3 12h1M20 12h1M4.9 19.8l-.7.7M19.8 4.9l-.7-.7" />
                <circle cx="12" cy="12" r="4" />
            </svg>
        </button>
    </nav>
</header>
