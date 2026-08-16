@props([
    'title' => null,
    'heading' => null,
    'description' => null,
    'breadcrumb' => [],
])

{{--
    Shell aplikasi: satu bidang berpadding dengan dua panel kaca yang mengambang
    di atasnya. Sidebar dan topbar tidak pernah menempel ke tepi viewport —
    jarak 16px di semua sisi dan 16px antar panel yang membuat kacanya terbaca
    sebagai lapisan, bukan sebagai bagian dari halaman.
--}}

<x-layouts.base :title="$title ?? $heading" backdrop="shell">
    <div class="relative flex min-h-screen items-start gap-[var(--shell-gap)] p-[var(--shell-pad)]">
        @include('layouts.partials.sidebar')

        <main class="flex min-w-0 flex-1 flex-col gap-[var(--shell-gap)]">
            @include('layouts.partials.topbar', ['breadcrumb' => $breadcrumb, 'heading' => $heading])

            @if ($heading || isset($actions))
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        @if ($heading)
                            <h1 class="font-display text-[27px] font-semibold text-ink">{{ $heading }}</h1>
                        @endif

                        @if ($description)
                            <p class="mt-0.5 max-w-[64ch] text-base2 text-ink-muted">{{ $description }}</p>
                        @endif
                    </div>

                    @isset($actions)
                        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                    @endisset
                </div>
            @endif

            {{--
                Ringkasan galat validasi berdiri satu kali di sini, bukan
                diulang di tiap halaman. Sebagian besar formulir aplikasi ini
                tinggal di dalam modal; modalnya tertutup lagi setelah
                halaman digambar ulang, jadi tanpa ringkasan ini pengguna
                melihat halaman yang tampak tidak berubah dan tidak tahu
                permintaannya ditolak.
            --}}
            @if ($errors->any())
                <x-ui.alert variant="danger" title="Ada yang perlu diperbaiki">
                    @if ($errors->count() === 1)
                        {{ $errors->first() }}
                    @else
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.alert>
            @endif

            {{ $slot }}
        </main>
    </div>

    <x-ui.command-palette />
</x-layouts.base>
