@php($breadcrumb ??= [])

{{--
    Bilah kepala halaman — MENEMPEL di tepi atas, satu garis di bawahnya.

    Menggantikan dua benda yang sebelumnya bertumpuk: panel kaca mengambang
    berisi jejak halaman dan tombol utilitas, lalu blok judul terpisah di
    bawahnya. Dua bidang, dua jarak, dan judul halaman yang tidak pernah
    sebaris dengan tombol aksinya.

    Sekarang satu bilah, persis seperti yang digambar keenam artboard panitia:
    keterangan kecil di atas judul, tombol di kanan, satu garis memisahkannya
    dari isi halaman.

    Utilitas aplikasi — cari, notifikasi, tema, akun — duduk di kanan setelah
    pemisah. Ia chrome aplikasi, bukan aksi halaman, dan pemisahnya menyatakan
    itu tanpa perlu kata.
--}}

<header class="sticky top-0 z-30 flex shrink-0 flex-wrap items-end justify-between gap-x-6 gap-y-3 border-b border-line bg-surface-raised px-4 py-3 sm:px-6">

    <div class="flex min-w-0 flex-1 items-start gap-3">
        {{-- Laci menu hanya ada di layar sempit; di layar lebar sidebar-nya
             sudah berdiri sendiri. --}}
        <button type="button"
                x-on:click="$store.shell.toggleSidebar()"
                class="-ms-1.5 grid size-10 shrink-0 place-items-center rounded-[var(--radius-kecil)] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none lg:hidden">
            <span class="sr-only">Buka menu</span>
            <x-si.ikon nama="menu" class="size-5" />
        </button>

        <div class="min-w-0 flex-1">
            @if ($breadcrumb !== [])
                <x-si.jejak :daftar="$breadcrumb" :akar="config('app.name')" class="min-w-0" />
            @endif

            @if ($heading)
                <h1 class="mt-0.5 truncate text-[22px] leading-tight font-semibold text-ink">{{ $heading }}</h1>
            @endif

            @if ($description)
                <p class="mt-1 max-w-[70ch] text-[14px] leading-relaxed text-ink-muted">{{ $description }}</p>
            @endif
        </div>
    </div>

    {{-- Tanpa `shrink-0`: kelompok ini harus boleh menyusut supaya isinya
         membungkus ke baris berikutnya. Dengan `shrink-0` ia mempertahankan
         lebar isinya dan melewati tepi layar — di 375px, tombol tema dan
         akun terpotong di luar viewport tanpa cara menggulir ke sana. --}}
    <div class="flex flex-wrap items-center justify-end gap-2">
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            <div class="mx-1 hidden h-6 w-px bg-line sm:block"></div>
        @endisset

        {{-- Pintasan pencarian tampil sebagai kolom di layar lebar, dan
             menyusut jadi tombol ikon begitu ruangnya sempit. Tuts-nya disebut
             di kolomnya: pintasan yang tidak pernah ditulis tidak akan
             ditemukan orang yang tidak diberitahu. --}}
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

        <x-si.lonceng />

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
    </div>
</header>
