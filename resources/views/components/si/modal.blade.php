@props([
    'id',
    'judul' => null,
    'ukuran' => 'sedang',
    // Terbuka sejak halaman digambar. Untuk alur yang datang dari halaman lain
    // (mis. ?atlet=12 yang langsung membuka formulir pendaftarannya).
    'terbuka' => false,
    // Nama isian yang dikandung modal ini. Kalau salah satunya ditolak
    // validasi, modalnya terbuka sendiri saat halaman dimuat ulang.
    'galatUntuk' => [],
])

{{--
    Modal — padanan <x-ui.modal> dengan perilaku yang sama persis, rupa yang
    mengikuti lapisan si/*.

    Modal hanya untuk hal yang butuh jawaban. Kabar yang lewat begitu saja
    cukup pakai <x-si.pesan-kilat>.

    Buka dari mana pun di halaman yang sama dengan memancarkan event:

        <x-si.tombol x-on:click="$dispatch('modal-open', 'hapus-user')">Hapus</x-si.tombol>

    Esc menutup, fokus kembali ke elemen pemicunya, dan halaman di belakang
    tidak ikut bergulir selama modal terbuka.

    Formulir yang gagal validasi mengembalikan halaman utuh — modalnya ikut
    tertutup, dan pesan galat di dalamnya tidak pernah terbaca. `galat-untuk`
    membuka kembali modal yang memuat isian bersangkutan.

    Tombol tutup membawa kata "Tutup" untuk pembaca layar; silangnya sendiri
    hanya penanda. Ikon telanjang tanpa nama tidak terbaca oleh yang tidak
    melihatnya.
--}}

@php
    $lebar = [
        'kecil' => 'max-w-[430px]',
        'sedang' => 'max-w-lg',
        'besar' => 'max-w-2xl',
        'lebar' => 'max-w-4xl',
    ];

    $bukaSendiri = $terbuka || ($galatUntuk !== [] && $errors->hasAny((array) $galatUntuk));
@endphp

<div x-data="{
        open: false,
        pemicu: null,
        show() {
            this.pemicu = document.activeElement;
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.panel?.focus());
        },
        hide() {
            this.open = false;
            document.body.style.overflow = '';
            this.pemicu?.focus();
        },
     }"
     @if ($bukaSendiri) x-init="show()" @endif
     x-on:modal-open.window="$event.detail === '{{ $id }}' && show()"
     x-on:modal-close.window="$event.detail === '{{ $id }}' && hide()"
     x-on:keydown.escape.window="open && hide()">

    <template x-teleport="body">
        <div x-show="open" x-cloak
             class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto p-6"
             role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-judul">

            <div x-show="open" x-on:click="hide()" class="fixed inset-0 bg-black/50"></div>

            <div x-ref="panel" tabindex="-1" x-show="open"
                 class="relative w-full {{ $lebar[$ukuran] ?? $lebar['sedang'] }} rounded-[var(--radius)] border border-line bg-surface-raised outline-none">

                <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                    <h3 id="{{ $id }}-judul" class="text-[18px] font-semibold text-ink">{{ $judul }}</h3>

                    <button type="button" x-on:click="hide()"
                            class="-me-2 grid size-11 shrink-0 place-items-center rounded-[var(--radius-kecil)] text-ink-muted hover:bg-surface-inset hover:text-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                        <span class="sr-only">Tutup</span>
                        <x-si.ikon nama="x" class="size-5" />
                    </button>
                </div>

                <div class="flex flex-col gap-4 px-5 py-5 text-[14px] leading-relaxed text-ink-secondary">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-line px-5 py-4">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </template>
</div>
