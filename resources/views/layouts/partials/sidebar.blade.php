@php($navigation = app(App\Support\Navigation\NavigationBuilder::class)->build())

{{--
    Sidebar adalah panel kaca yang berdiri di dalam shell berpadding, setinggi
    layar dikurangi padding atas dan bawah. Diciutkan jadi rail ikon; label
    hilang dan atribut title mengambil alih.

    Lebar dan penyembunyian label dikendalikan CSS lewat `data-sidebar` di
    <html> (lihat app.css). Alpine hanya membalik nilainya, jadi tidak ada
    lompatan saat halaman pertama kali digambar.

    Di bawah 1024px panel ini berperilaku sebagai drawer yang menutupi konten.
--}}

<div x-show="$store.shell.sidebarOpen" x-cloak
     x-on:click="$store.shell.toggleSidebar()"
     x-transition:enter="transition duration-180 ease-out"
     x-transition:enter-start="opacity-0"
     class="fixed inset-0 z-40 bg-[rgb(8_11_16/0.5)] lg:hidden"
     aria-hidden="true"></div>

<aside x-on:keydown.escape.window="$store.shell.sidebarOpen = false"
       :class="$store.shell.sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 start-0 z-50 flex w-64 -translate-x-full flex-col transition-[transform,width] duration-260 ease-rizz lg:sticky lg:top-[var(--shell-pad)] lg:z-auto lg:h-[calc(100vh-2*var(--shell-pad))] lg:w-[var(--shell-sidebar)] lg:shrink-0 lg:translate-x-0"
       aria-label="Menu utama">

    <div class="glass relative flex h-full flex-col gap-0.5 rounded-none px-2.5 py-3 lg:rounded-xl">
        {{-- Tombol ciut hanya masuk akal di layar lebar; di layar sempit
             panelnya memang menutup penuh. --}}
        <button type="button"
                x-on:click="$store.shell.toggleCollapsed()"
                :title="$store.shell.collapsed ? 'Lebarkan menu' : 'Ciutkan menu'"
                class="absolute end-[-13px] top-5 z-10 hidden size-[26px] items-center justify-center rounded-full border border-line-strong bg-[image:var(--mat-raised)] text-ink-secondary shadow-[var(--bevel),var(--lift)] transition hover:brightness-95 active:translate-y-px active:shadow-press focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none lg:flex">
            <span class="sr-only">Ciutkan menu</span>
            <span class="flex transition-transform duration-220 ease-rizz" data-rail="flip">
                <x-ui.icon name="chevron-left" class="size-3.5" />
            </span>
        </button>

        <a href="{{ route('dashboard') }}" data-rail="center"
           class="mb-3 flex items-center gap-2.5 overflow-hidden px-1.5 py-1 whitespace-nowrap">
            <span class="flex size-[26px] shrink-0 items-center justify-center rounded-sm bg-[image:var(--mat-accent)] font-display text-[13px] font-bold text-accent-on shadow-lift">
                {{ mb_substr(config('app.name'), 0, 1) }}
            </span>
            <span class="min-w-0 flex-1" data-rail="hide">
                <span class="block truncate font-display text-[14.5px] font-semibold tracking-tight text-ink">
                    {{ config('app.name') }}
                </span>
                <span class="block truncate text-[11px] text-ink-muted">
                    {{ app()->isProduction() ? 'Workspace produksi' : 'Workspace '.app()->environment() }}
                </span>
            </span>
        </a>

        <div class="eyebrow h-4 px-2.5 pb-1.5" data-rail="hide">Menu</div>

        <nav class="flex flex-1 flex-col gap-0.5 overflow-x-hidden overflow-y-auto">
            @foreach ($navigation as $item)
                @if ($item['children'] === [])
                    <a href="{{ $item['url'] ?? '#' }}"
                       title="{{ $item['label'] }}"
                       data-rail="center"
                       @class([
                           'flex items-center gap-2.5 overflow-hidden rounded-md px-2.5 py-[9px] text-sm whitespace-nowrap transition-colors duration-160',
                           'bg-accent-soft font-semibold text-accent shadow-[var(--bevel)]' => $item['active'],
                           'text-ink-secondary hover:bg-surface-inset hover:text-ink' => ! $item['active'],
                       ])>
                        @if ($item['icon'])
                            <x-ui.icon :name="$item['icon']" class="size-[17px] shrink-0" />
                        @endif
                        <span class="flex-1 truncate" data-rail="hide">{{ $item['label'] }}</span>

                        @if ($item['badge'])
                            <span class="shrink-0 rounded-full bg-warning-soft px-[7px] py-px text-[11px] font-semibold text-warning"
                                  data-rail="hide">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @else
                    <div x-data="{ expanded: @js($item['active']) }">
                        {{-- Diklik saat rail: lebarkan dulu, baru buka grupnya. --}}
                        <button type="button"
                                x-on:click="$store.shell.collapsed
                                    ? ($store.shell.toggleCollapsed(), expanded = true)
                                    : (expanded = ! expanded)"
                                title="{{ $item['label'] }}"
                                data-rail="center"
                                class="flex w-full items-center gap-2.5 overflow-hidden rounded-md px-2.5 py-[9px] text-sm whitespace-nowrap text-ink-secondary transition-colors duration-160 hover:bg-surface-inset hover:text-ink"
                                :aria-expanded="expanded">
                            @if ($item['icon'])
                                <x-ui.icon :name="$item['icon']" class="size-[17px] shrink-0" />
                            @endif
                            <span class="min-w-0 flex-1 text-start" data-rail="hide">
                                <span class="block truncate">{{ $item['label'] }}</span>

                                @if ($item['caption'])
                                    <span class="block truncate text-[10.5px] leading-tight text-ink-muted">{{ $item['caption'] }}</span>
                                @endif
                            </span>
                            <span class="flex shrink-0 transition-transform duration-200" data-rail="hide"
                                  :class="expanded && 'rotate-180'">
                                <x-ui.icon name="chevron-down" class="size-3.5" />
                            </span>
                        </button>

                        <div x-show="expanded && ! $store.shell.collapsed" x-cloak class="flex flex-col gap-0.5 py-0.5">
                            @foreach ($item['children'] as $child)
                                <a href="{{ $child['url'] ?? '#' }}"
                                   @class([
                                       'flex items-center gap-2 truncate rounded-md py-1.5 ps-10 pe-2.5 text-base2 transition-colors duration-160',
                                       'bg-accent-soft font-semibold text-accent shadow-[var(--bevel)]' => $child['active'],
                                       'text-ink-secondary hover:bg-surface-inset hover:text-ink' => ! $child['active'],
                                   ])>
                                    <span class="flex-1 truncate">{{ $child['label'] }}</span>

                                    @if ($child['badge'])
                                        <span class="shrink-0 rounded-full bg-warning-soft px-[7px] py-px text-[11px] font-semibold text-warning">{{ $child['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        @if (config('design-system.enabled'))
            <a href="{{ route('design-system.foundation') }}"
               title="Design system"
               data-rail="center"
               class="flex items-center gap-2.5 overflow-hidden rounded-md px-2.5 py-[9px] text-sm whitespace-nowrap text-ink-secondary transition-colors duration-160 hover:bg-surface-inset hover:text-ink">
                <x-ui.icon name="swatch-book" class="size-[17px] shrink-0" />
                <span class="truncate" data-rail="hide">Design system</span>
            </a>
        @endif

        <div class="mt-1 border-t border-line pt-2">
            <a href="{{ route('profile.edit') }}"
               title="{{ auth()->user()->name }}"
               data-rail="center"
               class="flex items-center gap-2.5 overflow-hidden rounded-md px-2 py-1.5 whitespace-nowrap transition-colors duration-160 hover:bg-surface-inset">
                <x-ui.avatar :user="auth()->user()" size="xs2" />
                <span class="min-w-0 flex-1" data-rail="hide">
                    <span class="block truncate text-[13px] font-semibold text-ink">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs2 text-ink-muted">{{ auth()->user()->email }}</span>
                </span>
            </a>
        </div>
    </div>
</aside>
