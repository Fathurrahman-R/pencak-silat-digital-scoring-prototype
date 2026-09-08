@php
    $navigasi = app(App\Support\Navigation\NavigationBuilder::class);
    $menu = $navigasi->build();
    $turnamenAktif = $navigasi->turnamenAktif();
@endphp

{{--
    Navigasi samping — kolom kertas yang MENEMPEL di tepi kiri layar.

    Susunannya DATAR: judul seksi, lalu itemnya langsung di bawahnya. Tidak ada
    grup yang bisa dilipat.

    Grup lipat dibuang karena harganya tidak sepadan. Sebagian besar role hanya
    memegang satu atau dua izin — Wasit satu, Juri satu —
    jadi yang mereka lihat adalah grup terlipat berisi satu item, dan satu klik
    hanya untuk membukanya. Sisanya, yang izinnya luas, membuka semua grup di
    kunjungan pertama lalu tidak pernah menutupnya lagi. Yang tersisa dari
    mekanisme itu cuma keadaan yang harus dijaga dan satu lapis interaksi yang
    tidak pernah dipakai.

    Lebar dan penyembunyian label dikendalikan CSS lewat `data-sidebar` di
    <html> (lihat app.css). Alpine hanya membalik nilainya, jadi tidak ada
    lompatan tata letak saat halaman pertama kali digambar.

    Di bawah 1024px ia berperilaku sebagai laci yang menutupi konten.
--}}

<div x-show="$store.shell.sidebarOpen" x-cloak
     x-on:click="$store.shell.tutupSidebar()"
     class="fixed inset-0 z-40 bg-black/50 lg:hidden"
     aria-hidden="true"></div>

<aside x-on:keydown.escape.window="$store.shell.tutupSidebar()"
       :class="$store.shell.sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 start-0 z-50 flex w-[264px] -translate-x-full flex-col border-e border-line bg-surface-sunken transition-[transform,width] duration-200 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-[var(--shell-sidebar)] lg:shrink-0 lg:translate-x-0"
       aria-label="Menu utama">

    {{--
        Kepala: lambang aplikasi, lalu NAMA KEJUARAAN YANG SEDANG DIBUKA.

        Baris kedua ini sebelumnya berisi nama lingkungan ("Lingkungan local"),
        yang tidak berarti apa pun bagi panitia. Sementara itu penanda kejuaraan
        aktif tinggal di judul grup menu — dan grup itu ikut hilang saat
        menunya diratakan.

        Ia harus ada di suatu tempat yang selalu terlihat: dua belas menu di
        bawah menunjuk ke dalam kejuaraan ini, dan salah kejuaraan berarti
        diam-diam menyunting data yang keliru.
    --}}
    <div data-rail="brand" class="flex h-15 shrink-0 items-center gap-2 border-b border-line px-3">
        {{-- `data-brand-link` menandai SELURUH tautan brand, bukan cuma
             labelnya: saat menu diciutkan ia hilang utuh, kotak lambang
             sekalian. Lihat alasannya di app.css. --}}
        <a href="{{ route('dashboard') }}" data-rail="center" data-brand-link
           class="flex min-w-0 flex-1 items-center gap-2.5 overflow-hidden whitespace-nowrap">
            <span class="grid size-7 shrink-0 place-items-center rounded-[var(--radius-kecil)] bg-accent text-[13px] font-bold text-accent-on">
                {{ mb_substr(config('app.name'), 0, 1) }}
            </span>
            <span class="min-w-0 flex-1" data-rail="hide">
                <span class="block truncate text-[14px] font-semibold text-ink">{{ config('app.name') }}</span>
                <span class="block truncate text-[11px] text-ink-muted">
                    {{ $turnamenAktif?->name ?? 'Belum ada kejuaraan dibuka' }}
                </span>
            </span>
        </a>

        {{--
            Tombol TUTUP laci, khusus lebar HP dan tablet.

            Di bawah 1024px sidebar adalah laci yang menutupi halaman, dan
            satu-satunya cara menutupnya dulu adalah menekan latar gelap di
            sisisnya atau menekan Esc — papan ketik yang tidak dipunyai
            pemakai HP, dan sasaran yang tidak pernah menyatakan dirinya
            sebagai tombol. Laci yang dibuka harus punya jalan keluar yang
            terlihat, di tempat ibu jari sudah berada: sudut yang sama dengan
            tombol yang membukanya.
        --}}
        <button type="button"
                x-on:click="$store.shell.tutupSidebar()"
                title="Tutup menu"
                class="grid size-10 shrink-0 place-items-center rounded-[var(--radius)] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-[3px] focus-visible:ring-ink/10 focus-visible:outline-none lg:hidden">
            <span class="sr-only">Tutup menu</span>
            <x-si.ikon nama="x" class="size-5" />
        </button>

        {{-- Tombol ciut duduk di BARIS BRAND (DESIGN-SYSTEM.md §6), 32×32.
             Saat menu diciutkan ia tetap tampil sebagai satu-satunya tombol di
             baris itu — tanpa itu tidak ada lagi cara melebarkannya kembali. --}}
        <button type="button"
                x-on:click="$store.shell.toggleCollapsed()"
                :title="$store.shell.collapsed ? 'Lebarkan menu' : 'Ciutkan menu'"
                class="hidden size-8 shrink-0 place-items-center rounded-[var(--radius)] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-[3px] focus-visible:ring-ink/10 focus-visible:outline-none lg:grid">
            <span class="sr-only">Ciutkan atau lebarkan menu</span>
            <span x-show="! $store.shell.collapsed"><x-si.ikon nama="panel-left-close" class="size-4" /></span>
            <span x-show="$store.shell.collapsed" x-cloak><x-si.ikon nama="panel-left-open" class="size-4" /></span>
        </button>
    </div>

    <nav class="flex flex-1 flex-col overflow-x-hidden overflow-y-auto px-3 py-3">
        @foreach ($menu as $entri)
            @if ($entri['tipe'] === 'seksi')
                {{--
                    Judul seksi disembunyikan di lebar rail, dan penggantinya
                    satu garis: tanpa itu, keenam seksi meleleh jadi satu deret
                    ikon panjang tanpa satu pun jeda.
                --}}
                {{-- Disembunyikan pakai KELAS, bukan atribut `hidden`: preflight
                     Tailwind menulis `[hidden]{display:none!important}`, dan aturan
                     rail di app.css tidak bisa mengalahkannya. --}}
                <div class="mt-4 hidden h-px shrink-0 bg-line" data-rail="show"></div>

                <div class="mt-4.5 mb-1.5 shrink-0 px-3 font-mono text-[11px] tracking-[.04em] text-ink-faint uppercase first:mt-0"
                     data-rail="hide">
                    {{ $entri['label'] }}
                </div>

                @foreach ($entri['items'] as $item)
                    @include('layouts.partials.menu-item', ['item' => $item])
                @endforeach
            @else
                @include('layouts.partials.menu-item', ['item' => $entri])
            @endif
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-line p-2">
        {{--
            Peraga komponen adalah alat pengembang. Sebelumnya ia tampil untuk
            siapa pun yang login selama halamannya aktif, termasuk juri yang
            membuka aplikasi dari HP di pinggir gelanggang — satu tautan yang
            tidak berarti apa pun baginya, di menu yang seharusnya hanya berisi
            pekerjaannya. Route-nya sendiri tetap dijaga
            config('design-system.enabled'); ini soal siapa yang melihat
            pintunya.
        --}}
        @if (config('design-system.enabled') && resource_allows(rk('resources', App\Enums\ResourceAction::View)))
            <a href="{{ route('design-system.si') }}"
               title="Peraga komponen"
               data-rail="center"
               class="flex h-9.5 items-center gap-2.5 overflow-hidden rounded-[var(--radius)] px-3 text-[13.5px] whitespace-nowrap text-ink-secondary hover:bg-surface-inset hover:text-ink">
                <x-si.ikon nama="swatch-book" class="size-4 shrink-0" />
                <span class="truncate" data-rail="hide">Peraga komponen</span>
            </a>
        @endif

        {{-- Identitas pengguna TIDAK diulang di sini. Nama, email, tautan
             profil, dan tombol keluar sudah tinggal di menu akun di bilah
             kepala; menampilkannya dua kali di satu layar membuat orang
             mengira keduanya membuka hal yang berbeda. --}}
    </div>
</aside>
