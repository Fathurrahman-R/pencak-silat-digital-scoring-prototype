{{--
    Bilah utilitas aplikasi — menempel di tepi atas, satu garis di bawahnya.

    Yang ADA di sini cuma chrome aplikasi: laci menu di layar sempit, cari,
    tema, akun. Semuanya milik aplikasi, bukan milik halaman, dan isinya tidak
    pernah berubah dari satu layar ke layar berikutnya — jadi tingginya tetap,
    dan tidak ada yang bisa mendorong apa pun ke baris kedua.

    Yang TIDAK di sini lagi: jejak halaman, judul, keterangan, dan tombol aksi
    halaman. Keempatnya panjangnya ditentukan isi halaman, dan begitu jejaknya
    dalam — "Kejuaraan > Piala Direktur Politeknik Negeri Pontianak > Rekap &
    Laporan" — bilah ini pecah jadi empat baris, judulnya terselip di tengah
    jejak yang membungkus, dan empat tombol ekspor berdesakan dengan kolom
    cari. Keempatnya sekarang berdiri di atas isi halaman, di mana lebarnya
    memang selebar halaman.

    Lonceng notifikasi DIBUANG. Ia dipanggil tanpa sumber data, jadi isinya
    selamanya "Belum ada notifikasi" — ikon yang menempati ruang di bilah
    tersempit aplikasi dan tidak pernah bisa membawa kabar apa pun. Kalau
    notifikasi suatu saat dibangun, ia kembali bersama datanya.
--}}

<header class="sticky top-0 z-30 flex h-14 shrink-0 items-center justify-end gap-2 border-b border-line bg-surface-raised px-4 sm:px-6">

    {{-- Laci menu hanya ada di layar sempit; di layar lebar sidebar-nya sudah
         berdiri sendiri. `me-auto` mendorong sisanya ke kanan. --}}
    <button type="button"
            x-on:click="$store.shell.toggleSidebar()"
            class="-ms-1.5 me-auto grid size-10 shrink-0 place-items-center rounded-[var(--radius-kecil)] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none lg:hidden">
        <span class="sr-only">Buka menu</span>
        <x-si.ikon nama="menu" class="size-5" />
    </button>

    {{-- Pintasan pencarian tampil sebagai kolom di layar lebar, dan menyusut
         jadi tombol ikon begitu ruangnya sempit. Tuts-nya disebut di kolomnya:
         pintasan yang tidak pernah ditulis tidak akan ditemukan orang yang
         tidak diberitahu. --}}
    <button type="button"
            x-on:click="$dispatch('cari-menu-buka')"
            class="hidden h-10 min-w-[200px] items-center gap-2.5 rounded-[var(--radius-kecil)] border border-line-strong bg-surface ps-3 pe-2 text-[14px] text-ink-muted hover:text-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none md:flex">
        <x-si.ikon nama="search" class="size-4" />
        <span class="flex-1 text-start">Cari halaman…</span>
        <x-si.tuts>⌘K</x-si.tuts>
    </button>

    <button type="button"
            x-on:click="$dispatch('cari-menu-buka')"
            class="grid size-10 place-items-center rounded-[var(--radius-kecil)] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none md:hidden">
        <span class="sr-only">Cari halaman</span>
        <x-si.ikon nama="search" class="size-5" />
    </button>

    <button type="button" data-theme-toggle
            class="grid size-10 place-items-center rounded-[var(--radius-kecil)] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
        <span class="sr-only">Ganti tema</span>
        <x-si.ikon nama="sun-moon" class="size-5" />
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
