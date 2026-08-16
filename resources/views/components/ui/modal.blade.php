@props([
    'id',
    'title' => null,
    'size' => 'md',
    // Terbuka sejak halaman digambar. Untuk alur yang datang dari halaman lain
    // (mis. ?atlet=12 yang langsung membuka formulir pendaftarannya).
    'open' => false,
    // Nama field yang dikandung modal ini. Kalau salah satunya ditolak
    // validasi, modalnya terbuka sendiri saat halaman dimuat ulang.
    'errorsFor' => [],
])

{{--
    Modal hanya untuk hal yang butuh jawaban. Konfirmasi yang lewat begitu saja
    cukup pakai toast.

    Buka dari mana pun di halaman yang sama dengan memancarkan event:

        <x-ui.button x-on:click="$dispatch('modal-open', 'hapus-user')">Hapus</x-ui.button>

    Esc menutup, fokus kembali ke elemen pemicunya, dan halaman di belakang
    tidak ikut bergulir selama modal terbuka.

    Formulir yang gagal validasi mengembalikan halaman utuh — modalnya ikut
    tertutup, dan pesan galat di dalamnya tidak pernah terbaca. `errors-for`
    membuka kembali modal yang memuat field bersangkutan.
--}}

@php
    $sizes = [
        'sm' => 'max-w-[430px]',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];

    $bukaSendiri = $open || ($errorsFor !== [] && $errors->hasAny((array) $errorsFor));
@endphp

<div x-data="{
        open: false,
        trigger: null,
        show() {
            this.trigger = document.activeElement;
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.panel?.focus());
        },
        hide() {
            this.open = false;
            document.body.style.overflow = '';
            this.trigger?.focus();
        },
     }"
     @if ($bukaSendiri) x-init="show()" @endif
     x-on:modal-open.window="$event.detail === '{{ $id }}' && show()"
     x-on:modal-close.window="$event.detail === '{{ $id }}' && hide()"
     x-on:keydown.escape.window="open && hide()">

    <template x-teleport="body">
        <div x-show="open" x-cloak
             class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto p-6"
             role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">

            <div x-show="open"
                 x-transition:enter="transition duration-180 ease-out"
                 x-transition:enter-start="opacity-0"
                 x-on:click="hide()"
                 class="fixed inset-0 bg-[rgb(8_11_16/0.55)] backdrop-blur-[3px]"></div>

            <div x-ref="panel" tabindex="-1"
                 x-show="open"
                 x-transition:enter="transition duration-240 ease-rizz"
                 x-transition:enter-start="translate-y-2.5 scale-[0.98] opacity-0"
                 class="relative w-full {{ $sizes[$size] ?? $sizes['md'] }} rounded-xl border border-line bg-surface-raised shadow-lg outline-none">

                <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
                    <h3 id="{{ $id }}-title" class="font-display text-lg font-semibold text-ink">{{ $title }}</h3>

                    <button type="button" x-on:click="hide()"
                            class="-me-1.5 inline-flex size-8 shrink-0 items-center justify-center rounded-sm text-ink-muted transition hover:bg-surface-inset hover:text-ink focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-accent-soft">
                        <span class="sr-only">Tutup</span>
                        <x-ui.icon name="x" class="size-4" />
                    </button>
                </div>

                <div class="space-y-4 px-6 py-5 text-sm text-ink-secondary">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-line px-6 py-4">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </template>
</div>
