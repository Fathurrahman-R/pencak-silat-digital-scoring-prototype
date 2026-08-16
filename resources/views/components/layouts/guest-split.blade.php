@props([
    'title' => null,
    'heading' => null,
    'description' => null,
])

{{--
    Layar autentikasi utama (Masuk/Daftar): form solid di kiri, panel
    kepercayaan di kanan — mengikuti seksi 05 "Auth" pada
    `document/design-system/RizzxxUI Screens.dc.html`. Panel kanan hilang di
    bawah `lg`, form tetap penuh lebar supaya alur masuk tidak pernah
    terhalang oleh dekorasi.

    Panelnya permukaan solid, bukan kaca: kaca dipakai hanya sidebar dan
    topbar aplikasi.

    Flow yang lebih pendek dan sensitif (2FA, reset kata sandi, konfirmasi)
    sengaja tetap memakai `x-layouts.guest` — kartu tunggal tanpa panel
    kepercayaan, supaya perhatian pengguna tidak dialihkan di tengah langkah
    keamanan.
--}}

<x-layouts.base :title="$title ?? $heading">
    <div class="min-h-screen">
        <div class="grid min-h-screen grid-cols-1 lg:grid-cols-2">
            <div class="flex flex-col justify-center bg-surface-raised px-6 py-12 sm:px-12 lg:py-14">
                <div class="mx-auto w-full max-w-[360px]">
                    <a href="{{ url('/') }}" class="mb-9 flex items-center gap-2.5">
                        <span class="flex size-[26px] items-center justify-center rounded-sm bg-accent font-display text-sm font-bold text-accent-on">
                            {{ mb_substr(config('app.name'), 0, 1) }}
                        </span>
                        <span class="font-display text-base font-semibold tracking-tight text-ink">{{ config('app.name') }}</span>
                    </a>

                    @if ($heading)
                        <h1 class="font-display text-[28px] font-semibold text-ink">{{ $heading }}</h1>
                    @endif

                    @if ($description)
                        <p class="mt-2 text-sm text-ink-secondary">{{ $description }}</p>
                    @endif

                    <div class="mt-7 space-y-4">
                        {{ $slot }}
                    </div>
                </div>
            </div>

            <div class="relative hidden flex-col justify-end overflow-hidden p-9 lg:flex"
                 style="background-image: radial-gradient(90% 90% at 70% 10%, var(--accent-soft) 0%, transparent 55%), var(--mat-base)">
                <div class="bg-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>

                {{ $aside ?? '' }}
            </div>
        </div>

        <button type="button" data-theme-toggle
                class="fixed end-4 top-4 inline-flex size-9 items-center justify-center rounded-md border border-line-strong bg-[image:var(--mat-raised)] text-ink-secondary shadow-[var(--bevel),var(--lift)] transition hover:brightness-95 active:translate-y-px active:shadow-press focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none">
            <span class="sr-only">Ganti tema</span>
            <x-ui.icon name="sun-moon" class="size-4" />
        </button>
    </div>
</x-layouts.base>
