@props([
    'quote' => null,
    'name' => null,
    'role' => null,
    'initials' => null,
    'stats' => [],
])

{{--
    Panel kepercayaan untuk sisi kiri layar auth split — kutipan pelanggan +
    metrik ringkas. Isinya contoh; ganti dengan testimoni dan angka asli
    sebelum rilis, sama seperti data faktur contoh di halaman lain boilerplate
    ini.

    Permukaannya solid: kaca hanya dipakai dua panel shell aplikasi (sidebar
    dan topbar), tidak di kartu isi.
--}}

<div class="relative">
    <div class="rounded-xl border border-line bg-surface-raised p-6 shadow-lift">
        <x-ui.icon name="quote" class="size-5 text-accent" />
        <p class="mt-3.5 font-display text-lg leading-snug font-medium tracking-tight text-ink">{{ $quote }}</p>

        <div class="mt-5 flex items-center gap-2.5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full border border-line bg-surface-inset text-[12.5px] font-semibold text-ink-secondary">
                {{ $initials }}
            </span>
            <div>
                <div class="text-sm font-semibold text-ink">{{ $name }}</div>
                <div class="text-[12.5px] text-ink-muted">{{ $role }}</div>
            </div>
        </div>
    </div>

    @if ($stats !== [])
        <div class="relative mt-6 flex flex-wrap gap-7">
            @foreach ($stats as $stat)
                <div>
                    <div class="font-display text-2xl font-semibold text-ink">{{ $stat['value'] }}</div>
                    <div class="text-[12.5px] text-ink-muted">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
