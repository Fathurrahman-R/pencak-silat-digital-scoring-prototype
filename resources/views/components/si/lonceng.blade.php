@props([
    // Tiap butir: ['ikon' => 'receipt', 'nada' => 'aksen|sukses|perhatian|bahaya',
    //              'teks' => '…', 'waktu' => '2 jam lalu', 'tautan' => null]
    'daftar' => [],
    'semua' => null,
])

{{--
    Lonceng notifikasi.

    Penanda "ada yang baru" berupa TITIK, bukan angka: jumlah pastinya ada di
    dalam menu, dan angka kecil di sudut ikon tidak terbaca sambil berjalan.

    Titiknya tidak sendirian membawa arti -- tombolnya membawa kata
    "Notifikasi" untuk pembaca layar, dan jumlahnya disebut di sana juga.
--}}

@php
    $nadaKelas = [
        'aksen' => 'text-accent',
        'sukses' => 'text-success',
        'perhatian' => 'text-warning',
        'bahaya' => 'text-danger',
        'redup' => 'text-ink-muted',
    ];

    $jumlah = count($daftar);
@endphp

<div x-data="{ buka: false }"
     x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu?.focus() }"
     x-on:click.outside="buka = false"
     class="relative inline-flex">

    <button type="button" x-ref="pemicu" x-on:click="buka = ! buka"
            :aria-expanded="buka" aria-haspopup="menu"
            class="relative inline-flex size-[34px] items-center justify-center rounded-[var(--radius-kecil)] border border-line-strong bg-surface-raised text-ink-secondary hover:text-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
        {{-- Direktif yang menempel di huruf sebelumnya tidak dikenali Blade:
             `Notifikasi@if` terbaca sebagai teks biasa, dan @endif-nya jadi
             yatim. Karena itu ditulis di barisnya sendiri. --}}
        <span class="sr-only">
            Notifikasi
            @if ($jumlah)
                — {{ $jumlah }} belum dibaca
            @endif
        </span>
        <x-si.ikon nama="bell" class="size-[17px]" />

        @if ($jumlah)
            <span class="absolute end-[7px] top-[6px] size-[7px] rounded-full bg-danger ring-[1.5px] ring-surface-raised"></span>
        @endif
    </button>

    <div role="menu" x-show="buka" x-cloak
         class="absolute end-0 top-full z-50 mt-1.5 w-[320px] overflow-hidden rounded-[var(--radius)] border border-line bg-surface-raised">

        <div class="border-b border-line px-3.5 py-3 text-[14px] font-semibold text-ink">Notifikasi</div>

        @forelse ($daftar as $butir)
            <a @if ($butir['tautan'] ?? null) href="{{ $butir['tautan'] }}" @endif
               class="flex gap-2.5 border-b border-line px-3.5 py-3 hover:bg-surface-inset">
                <x-si.ikon :nama="$butir['ikon'] ?? 'info'"
                           class="mt-px size-4 shrink-0 {{ $nadaKelas[$butir['nada'] ?? 'redup'] ?? $nadaKelas['redup'] }}" />
                <div class="min-w-0 flex-1">
                    <div class="text-[14px] leading-relaxed text-ink">{{ $butir['teks'] }}</div>
                    @if ($butir['waktu'] ?? null)
                        <div class="mt-0.5 text-[13px] text-ink-muted">{{ $butir['waktu'] }}</div>
                    @endif
                </div>
            </a>
        @empty
            <p class="px-3.5 py-6 text-center text-[14px] text-ink-muted">Belum ada notifikasi.</p>
        @endforelse

        @if ($semua)
            <a href="{{ $semua }}" class="block px-3.5 py-2.5 text-center text-[14px] font-medium text-ink underline underline-offset-2 hover:bg-surface-inset">
                Lihat semua
            </a>
        @endif
    </div>
</div>
