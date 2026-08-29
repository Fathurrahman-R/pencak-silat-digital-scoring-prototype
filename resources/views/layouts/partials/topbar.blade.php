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
        <x-si.ikon nama="menu" class="size-5" />
    </button>

    <x-si.jejak :daftar="$breadcrumb" :akar="config('app.name')" class="min-w-0" />

    <div class="min-w-0 flex-1"></div>

    {{-- Pintasan pencarian tampil sebagai kolom di layar lebar, dan menyusut
         jadi tombol ikon begitu ruangnya sempit. --}}
    <button type="button"
            x-on:click="$dispatch('cari-menu-buka')"
            class="hidden h-[34px] min-w-[180px] items-center gap-2.5 rounded-md border border-line bg-surface-sunken ps-3 pe-2 text-base2 text-ink-muted shadow-well transition hover:border-line-strong focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none sm:flex">
        <x-si.ikon nama="search" class="size-4" />
        <span class="flex-1 text-start">Cari…</span>
        <x-si.tuts>⌘K</x-si.tuts>
    </button>

    <button type="button"
            x-on:click="$dispatch('cari-menu-buka')"
            class="inline-flex size-[34px] items-center justify-center rounded-md border border-line-strong bg-[image:var(--mat-raised)] text-ink-secondary shadow-[var(--bevel),var(--lift)] transition hover:brightness-95 active:shadow-press active:translate-y-px focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none sm:hidden">
        <span class="sr-only">Cari halaman</span>
        <x-si.ikon nama="search" class="size-[17px]" />
    </button>

    <x-si.lonceng />

    <button type="button" data-theme-toggle
            class="inline-flex size-[34px] items-center justify-center rounded-md border border-line-strong bg-[image:var(--mat-raised)] text-ink-secondary shadow-[var(--bevel),var(--lift)] transition hover:brightness-95 active:shadow-press active:translate-y-px focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none"
            title="Ganti tema">
        <span class="sr-only">Ganti tema</span>
        <x-si.ikon nama="sun-moon" class="size-[17px]" />
    </button>

    <x-si.menu id="menu-pengguna" letak="bawah-kanan">
        <x-slot:pemicu>
            <x-si.foto :user="auth()->user()" ukuran="kecil" />
        </x-slot:pemicu>

        <li class="border-b border-line px-2.5 pt-1 pb-2.5" role="none">
            <div class="truncate text-[13px] font-semibold text-ink">{{ auth()->user()->name }}</div>
            <div class="truncate text-[13px] text-ink-muted">{{ auth()->user()->email }}</div>
        </li>

        <x-si.menu-butir :tautan="route('profile.edit')" class="mt-1.5">
            <x-si.ikon nama="user" />
            Profil saya
        </x-si.menu-butir>

        <li role="none">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                {{-- Setinggi butir menu lain (40px): butir yang rapat mudah
                     tertekan salah satu, dan salah satunya ini. --}}
                <button type="submit" role="menuitem"
                        class="flex min-h-10 w-full items-center gap-2.5 rounded-[var(--radius-kecil)] px-2.5 py-2 text-left text-[14px] text-danger hover:bg-danger-soft focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                    <x-si.ikon nama="log-out" class="size-4" />
                    Keluar
                </button>
            </form>
        </li>
    </x-si.menu>
</header>
