@php($navigation = app(App\Support\Navigation\NavigationBuilder::class)->build())

{{--
    Navigasi samping — kolom kertas yang MENEMPEL di tepi kiri layar.

    Bukan panel mengambang. Sebelumnya ia panel kaca berpadding 16px di semua
    sisi, melayang di atas latar bersemburat aksen: rupa warisan boilerplate.
    Brief §3 menuntut yang sebaliknya untuk panitia — "kertas kerja, kontras
    tinggi, tanpa kaca dan tanpa noise". Kedalaman dinyatakan warna permukaan
    dan SATU garis, dan tidak ada satu piksel pun jarak yang tidak membawa
    arti.

    Lebar dan penyembunyian label dikendalikan CSS lewat `data-sidebar` di
    <html> (lihat app.css). Alpine hanya membalik nilainya, jadi tidak ada
    lompatan tata letak saat halaman pertama kali digambar.

    Di bawah 1024px ia berperilaku sebagai laci yang menutupi konten.
--}}

<div x-show="$store.shell.sidebarOpen" x-cloak
     x-on:click="$store.shell.toggleSidebar()"
     class="fixed inset-0 z-40 bg-black/50 lg:hidden"
     aria-hidden="true"></div>

<aside x-on:keydown.escape.window="$store.shell.sidebarOpen = false"
       :class="$store.shell.sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 start-0 z-50 flex w-64 -translate-x-full flex-col border-e border-line bg-surface-raised transition-[transform,width] duration-200 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-[var(--shell-sidebar)] lg:shrink-0 lg:translate-x-0"
       aria-label="Menu utama">

    {{-- Kepala: identitas aplikasi saja. Tombol ciut tinggal di kaki, karena
         di lebar rail kepala ini cuma muat satu benda — dan kalau yang muat
         itu tombolnya, tautan ke beranda hilang. --}}
    <div class="flex h-14 shrink-0 items-center border-b border-line px-3">
        <a href="{{ route('dashboard') }}" data-rail="center"
           class="flex min-w-0 flex-1 items-center gap-2.5 overflow-hidden whitespace-nowrap">
            <span class="grid size-7 shrink-0 place-items-center rounded-[var(--radius-kecil)] bg-accent text-[13px] font-bold text-accent-on">
                {{ mb_substr(config('app.name'), 0, 1) }}
            </span>
            <span class="min-w-0 flex-1" data-rail="hide">
                <span class="block truncate text-[14px] font-semibold text-ink">{{ config('app.name') }}</span>
                <span class="block truncate text-[11px] text-ink-muted">
                    {{ app()->isProduction() ? 'Server kejuaraan' : 'Lingkungan '.app()->environment() }}
                </span>
            </span>
        </a>
    </div>

    <nav class="flex flex-1 flex-col gap-px overflow-x-hidden overflow-y-auto px-2 py-3">
        @foreach ($navigation as $item)
            @if ($item['children'] === [])
                <a href="{{ $item['url'] ?? '#' }}"
                   title="{{ $item['label'] }}"
                   data-rail="center"
                   @if ($item['active']) aria-current="page" @endif
                   @class([
                       'flex min-h-10 items-center gap-2.5 overflow-hidden rounded-[var(--radius-kecil)] px-2.5 text-[14px] whitespace-nowrap',
                       // Yang sedang dibuka ditandai bidang tinta penuh, bukan
                       // rona lembut: di layar terang, aksen lembut di atas
                       // kertas hampir tidak terbaca sebagai "sedang di sini".
                       'bg-accent font-semibold text-accent-on' => $item['active'],
                       'text-ink-secondary hover:bg-surface-inset hover:text-ink' => ! $item['active'],
                   ])>
                    @if ($item['icon'])
                        <x-si.ikon :nama="$item['icon']" class="size-[18px] shrink-0" />
                    @endif
                    <span class="flex-1 truncate" data-rail="hide">{{ $item['label'] }}</span>

                    @if ($item['badge'])
                        <span @class([
                            'shrink-0 rounded-[var(--radius-kecil)] px-1.5 py-px text-[12px] font-semibold',
                            'bg-accent-on/15 text-accent-on' => $item['active'],
                            'bg-warning-soft text-warning' => ! $item['active'],
                        ]) data-rail="hide">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @else
                <div x-data="{ expanded: @js($item['active']) }">
                    {{-- Diklik saat rail: lebarkan dulu, baru buka grupnya.
                         Membuka grup di lebar rail hanya menampilkan daftar
                         tanpa label. --}}
                    <button type="button"
                            x-on:click="$store.shell.collapsed
                                ? ($store.shell.toggleCollapsed(), expanded = true)
                                : (expanded = ! expanded)"
                            title="{{ $item['label'] }}"
                            data-rail="center"
                            @class([
                                'flex min-h-10 w-full items-center gap-2.5 overflow-hidden rounded-[var(--radius-kecil)] px-2.5 text-[14px] whitespace-nowrap',
                                'focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none',
                                // Grup yang MEMUAT halaman sekarang ditandai lebih
                                // lembut daripada halamannya sendiri: dua bidang
                                // gelap bertumpuk membuat keduanya terbaca sebagai
                                // dua tempat. Di lebar rail, tanda ini satu-satunya
                                // yang tersisa -- anak menunya tidak digambar sama
                                // sekali di sana.
                                'bg-surface-inset font-semibold text-ink' => $item['active'],
                                'text-ink-secondary hover:bg-surface-inset hover:text-ink' => ! $item['active'],
                            ])
                            :aria-expanded="expanded">
                        @if ($item['icon'])
                            <x-si.ikon :nama="$item['icon']" class="size-[18px] shrink-0" />
                        @endif
                        <span class="min-w-0 flex-1 text-start" data-rail="hide">
                            <span class="block truncate">{{ $item['label'] }}</span>

                            @if ($item['caption'])
                                <span class="block truncate text-[11px] leading-tight text-ink-muted">{{ $item['caption'] }}</span>
                            @endif
                        </span>
                        <span class="flex shrink-0" data-rail="hide" :class="expanded && 'rotate-180'">
                            <x-si.ikon nama="chevron-down" class="size-4" />
                        </span>
                    </button>

                    {{-- Anak menu ditandai satu garis tegak, bukan indentasi
                         saja: indentasi sendirian hilang begitu labelnya
                         panjang dan terpotong. --}}
                    <div x-show="expanded && ! $store.shell.collapsed" x-cloak
                         class="my-px ms-[19px] flex flex-col gap-px border-s border-line ps-2">
                        @foreach ($item['children'] as $child)
                            <a href="{{ $child['url'] ?? '#' }}"
                               @if ($child['active']) aria-current="page" @endif
                               @class([
                                   'flex min-h-9 items-center gap-2 truncate rounded-[var(--radius-kecil)] px-2.5 text-[14px]',
                                   'bg-accent font-semibold text-accent-on' => $child['active'],
                                   'text-ink-secondary hover:bg-surface-inset hover:text-ink' => ! $child['active'],
                               ])>
                                <span class="flex-1 truncate">{{ $child['label'] }}</span>

                                @if ($child['badge'])
                                    <span @class([
                                        'shrink-0 rounded-[var(--radius-kecil)] px-1.5 py-px text-[12px] font-semibold',
                                        'bg-accent-on/15 text-accent-on' => $child['active'],
                                        'bg-warning-soft text-warning' => ! $child['active'],
                                    ])>{{ $child['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-line p-2">
        {{-- Tombol ciut tinggal di sini justru supaya ia SELALU terlihat.
             Di kepala, ia harus menghilang saat rail — dan begitu menghilang,
             tidak ada lagi cara melebarkan menunya kembali. --}}
        <button type="button"
                x-on:click="$store.shell.toggleCollapsed()"
                :title="$store.shell.collapsed ? 'Lebarkan menu' : 'Ciutkan menu'"
                data-rail="center"
                class="mb-px hidden min-h-10 w-full items-center gap-2.5 rounded-[var(--radius-kecil)] px-2.5 text-[14px] text-ink-secondary hover:bg-surface-inset hover:text-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none lg:flex">
            <span class="flex shrink-0">
                <span x-show="! $store.shell.collapsed"><x-si.ikon nama="panel-left-close" class="size-[18px]" /></span>
                <span x-show="$store.shell.collapsed" x-cloak><x-si.ikon nama="panel-left-open" class="size-[18px]" /></span>
            </span>
            <span class="flex-1 text-start" data-rail="hide">Ciutkan menu</span>
        </button>

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
               class="mb-px flex min-h-10 items-center gap-2.5 overflow-hidden rounded-[var(--radius-kecil)] px-2.5 text-[14px] whitespace-nowrap text-ink-secondary hover:bg-surface-inset hover:text-ink">
                <x-si.ikon nama="swatch-book" class="size-[18px] shrink-0" />
                <span class="truncate" data-rail="hide">Peraga komponen</span>
            </a>
        @endif

        {{-- Identitas pengguna TIDAK diulang di sini. Nama, email, tautan
             profil, dan tombol keluar sudah tinggal di menu akun di bilah
             kepala; menampilkannya dua kali di satu layar membuat orang
             mengira keduanya membuka hal yang berbeda. --}}
    </div>
</aside>
