{{--
    Bilah utilitas aplikasi — menempel di tepi atas, satu garis di bawahnya.

    DESIGN-SYSTEM.md §6: tinggi tetap 60px, isi kiri remah roti, isi kanan
    tombol ikon 40px dan avatar 36px. TIDAK ada kolom pencarian di bilah ini
    maupun di sidebar — pencarian halaman dibuka lewat tombol ikonnya sendiri
    (dan ⌘K), sehingga bilah tidak pernah pecah jadi dua baris.

    Judul halaman, keterangan, dan tombol aksinya TIDAK di sini: ketiganya
    panjangnya ditentukan isi halaman dan berdiri di bidang isi, tempat
    lebarnya memang selebar halaman.

    Lonceng notifikasi DIBUANG. Ia dipanggil tanpa sumber data, jadi isinya
    selamanya "Belum ada notifikasi" — ikon yang menempati ruang di bilah
    tersempit aplikasi dan tidak pernah bisa membawa kabar apa pun. Kalau
    notifikasi suatu saat dibangun, ia kembali bersama datanya.
--}}

@php($breadcrumb ??= [])

<header class="sticky top-0 z-30 flex h-15 shrink-0 items-center gap-2 border-b border-line bg-surface-raised px-4 sm:px-6">

    {{-- Laci menu hanya ada di layar sempit; di layar lebar sidebar-nya sudah
         berdiri sendiri. --}}
    <button type="button"
            x-on:click="$store.shell.toggleSidebar()"
            class="-ms-1.5 grid size-10 shrink-0 place-items-center rounded-[var(--radius)] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-[3px] focus-visible:ring-ink/10 focus-visible:outline-none lg:hidden">
        <span class="sr-only">Buka menu</span>
        <x-si.ikon nama="menu" class="size-5" />
    </button>

    @if ($breadcrumb !== [])
        <x-si.jejak :daftar="$breadcrumb" :akar="config('app.name')" class="min-w-0" />
    @endif

    <div class="ms-auto flex shrink-0 items-center gap-1">
        <button type="button"
                x-on:click="$dispatch('cari-menu-buka')"
                title="Cari halaman (⌘K)"
                class="grid size-10 place-items-center rounded-[var(--radius)] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-[3px] focus-visible:ring-ink/10 focus-visible:outline-none">
            <span class="sr-only">Cari halaman</span>
            <x-si.ikon nama="search" class="size-4" />
        </button>

        <button type="button" data-theme-toggle
                class="grid size-10 place-items-center rounded-[var(--radius)] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-[3px] focus-visible:ring-ink/10 focus-visible:outline-none">
            <span class="sr-only">Ganti suasana terang atau gelap</span>
            <x-si.ikon nama="sun-moon" class="size-4" />
        </button>

        <x-si.menu id="menu-pengguna" letak="bawah-kanan" class="ms-1">
            <x-slot:pemicu>
                <x-si.foto :user="auth()->user()" ukuran="kecil" />
            </x-slot:pemicu>

            <li class="border-b border-line px-2.5 pt-1 pb-2.5" role="none">
                <div class="truncate text-[13px] font-semibold text-ink">{{ auth()->user()->name }}</div>
                <div class="truncate text-[12.5px] text-ink-muted">{{ auth()->user()->email }}</div>
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
                            class="flex min-h-10 w-full items-center gap-2.5 rounded-[var(--radius)] px-2.5 py-2 text-left text-[13.5px] text-danger hover:bg-danger-soft focus-visible:ring-[3px] focus-visible:ring-ink/10 focus-visible:outline-none">
                        <x-si.ikon nama="log-out" class="size-4" />
                        Keluar
                    </button>
                </form>
            </li>
        </x-si.menu>
    </div>
</header>
