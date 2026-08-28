@php($breadcrumb ??= [])

{{--
    Topbar adalah panel kaca yang mengambang di dalam shell — bukan bar
    full-bleed. Jaraknya ke tepi diurus padding shell, jadi di sini hanya perlu
    `sticky top-4` supaya ia berhenti tepat di posisi diamnya saat digulir.
--}}

<header class="glass sticky top-[var(--shell-pad)] z-30 flex h-[58px] shrink-0 items-center gap-3 rounded-xl px-4">
    <button type="button"
            x-on:click="$store.shell.toggleSidebar()"
            class="inline-flex size-[34px] shrink-0 items-center justify-center rounded-md text-ink-secondary transition hover:bg-surface-inset hover:text-ink focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none lg:hidden">
        <span class="sr-only">Buka menu</span>
        <x-ui.icon name="menu" class="size-5" />
    </button>

    <x-ui.breadcrumb :items="$breadcrumb" :root="config('app.name')" class="min-w-0" />

    <div class="min-w-0 flex-1"></div>

    {{-- Pintasan pencarian tampil sebagai kolom di layar lebar, dan menyusut
         jadi tombol ikon begitu ruangnya sempit. --}}
    <button type="button"
            x-on:click="$dispatch('command-palette-open')"
            class="hidden h-[34px] min-w-[180px] items-center gap-2.5 rounded-md border border-line bg-surface-sunken ps-3 pe-2 text-base2 text-ink-muted shadow-well transition hover:border-line-strong focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none sm:flex">
        <x-ui.icon name="search" class="size-4" />
        <span class="flex-1 text-start">Cari…</span>
        <x-ui.kbd variant="well">⌘K</x-ui.kbd>
    </button>

    <button type="button"
            x-on:click="$dispatch('command-palette-open')"
            class="inline-flex size-[34px] items-center justify-center rounded-md border border-line-strong bg-[image:var(--mat-raised)] text-ink-secondary shadow-[var(--bevel),var(--lift)] transition hover:brightness-95 active:shadow-press active:translate-y-px focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none sm:hidden">
        <span class="sr-only">Cari halaman</span>
        <x-ui.icon name="search" class="size-[17px]" />
    </button>

    <x-ui.notification-menu />

    <button type="button" data-theme-toggle
            class="inline-flex size-[34px] items-center justify-center rounded-md border border-line-strong bg-[image:var(--mat-raised)] text-ink-secondary shadow-[var(--bevel),var(--lift)] transition hover:brightness-95 active:shadow-press active:translate-y-px focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none"
            title="Ganti tema">
        <span class="sr-only">Ganti tema</span>
        <x-ui.icon name="sun-moon" class="size-[17px]" />
    </button>

    <x-ui.dropdown id="user-menu" placement="bottom-end">
        <x-slot:trigger>
            <x-ui.avatar :user="auth()->user()" size="sm" />
        </x-slot:trigger>

        <li class="border-b border-line px-2.5 pt-1 pb-2.5" role="none">
            <div class="truncate text-[13px] font-semibold text-ink">{{ auth()->user()->name }}</div>
            <div class="truncate text-xs2 text-ink-muted">{{ auth()->user()->email }}</div>
        </li>

        <x-ui.dropdown-item :href="route('profile.edit')" class="mt-1.5">
            <x-ui.icon name="user" />
            Profil saya
        </x-ui.dropdown-item>

        <li role="none">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" role="menuitem"
                        class="flex w-full items-center gap-2.5 rounded-sm px-2.5 py-2 text-left text-sm text-danger transition hover:bg-danger-soft">
                    <x-ui.icon name="log-out" class="size-4" />
                    Keluar
                </button>
            </form>
        </li>
    </x-ui.dropdown>
</header>
