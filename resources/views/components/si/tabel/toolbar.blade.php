@props([
    'table' => null,
    'placeholder' => 'Cari…',
    // Jumlah baris yang tampil dan jumlah seluruhnya. Diisi berarti toolbar
    // menyatakan angkanya.
    'tampil' => null,
    'total' => null,
])

{{--
    Baris pencarian, penyaring, dan tindakan borongan di atas tabel.

    Penyaring dengan sedikit pilihan lebih baik jadi chip: pilihannya terbaca
    sekaligus dan langsung berlaku. Yang pilihannya banyak tetap <select> di
    dalam slot `filters`; formnya method GET, jadi nilainya otomatis jadi query
    string yang dibaca TableBuilder.

    JUMLAH HASIL DITULIS SEBAGAI ANGKA, bukan disimpulkan pembaca dari panjang
    daftar. Panitia yang menyaring tiga ratus pendaftaran perlu tahu berapa yang
    tersisa tanpa menggulir sampai bawah — dan tanpa itu, penyaring yang
    tidak sengaja aktif terbaca sebagai data yang hilang.
--}}

<div class="flex flex-wrap items-center gap-3">
    <form method="GET" class="flex flex-wrap items-center gap-2.5">
        <div class="relative">
            <x-si.ikon nama="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-ink-muted" />

            <input type="search"
                   name="{{ $table?->searchParameter() ?? 'q' }}"
                   value="{{ $table?->search() }}"
                   placeholder="{{ $placeholder }}"
                   class="block h-9 w-[260px] max-w-full rounded-[var(--radius)] border border-line-strong bg-surface-raised ps-9 pe-3 text-[13.5px] text-ink outline-none placeholder:text-ink-muted focus:border-accent focus:ring-[3px] focus:ring-ink/10">
        </div>

        @isset($filters)
            {{ $filters }}

            {{-- Tombol Terapkan hanya berarti kalau ada kontrol yang menunggu
                 dikirim. Chip mengirim dirinya sendiri lewat tautan. --}}
            <x-si.tombol tipe="submit" varian="kedua" ukuran="kecil">Terapkan</x-si.tombol>
        @endisset
    </form>

    @isset($chips)
        {{ $chips }}
    @endisset

    @if ($tampil !== null && $total !== null)
        <div class="flex items-baseline gap-1.5 text-[12.5px] text-ink-muted">
            <span>Menampilkan</span>
            <span class="font-mono text-[14px] font-medium text-ink tabular-nums">{{ $tampil }}</span>
            <span>dari</span>
            <span class="font-mono text-[14px] font-medium text-ink tabular-nums">{{ $total }}</span>
        </div>
    @endif

    @if ($table?->hasActiveFilters())
        {{-- "Kosongkan penyaring", bukan "Reset". Kata Inggris di layar yang
             dipakai orang yang tidak terbiasa dengan aplikasi web menuntut
             tebakan, dan yang ditebak di sini adalah tombol yang mengubah
             seluruh isi daftar. --}}
        <a href="{{ $table->resetUrl() }}" class="text-[13px] font-medium text-ink underline underline-offset-2">
            Kosongkan penyaring
        </a>
    @endif

    @isset($bulk)
        <div class="ms-auto flex items-center gap-2.5">
            <span class="text-[13px] text-ink-muted" x-show="selected.length" x-cloak
                  x-text="selected.length + ' dipilih'"></span>

            {{-- Tindakan borongan tidak pernah bisa ditekan saat tidak ada yang
                 dipilih: pointer-events dimatikan, bukan cuma dipudarkan. --}}
            <div class="flex items-center gap-2"
                 :class="selected.length ? 'opacity-100' : 'pointer-events-none opacity-0'">
                {{ $bulk }}
            </div>
        </div>
    @endisset
</div>
