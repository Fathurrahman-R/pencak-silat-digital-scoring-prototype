@props(['pohon'])

{{--
    Pohon bagan.

    Slot diletakkan dengan koordinat mutlak yang dihitung di
    BracketController::pohon(), bukan dengan flex. Garis penghubung harus
    bertemu TEPAT di tengah slot pasangannya; `space-around` mendekatinya tapi
    meleset satu-dua piksel begitu tinggi slot atau jumlah peserta berubah —
    dan pohon yang garisnya meleset menyesatkan pembacanya tentang siapa
    bertemu siapa, kesalahan paling mahal di layar ini.

    Bidang sudut penuh, bukan batang tepi. Merah kiri biru kanan sudah jadi
    arti tetap di papan skor, overlay, dan panel gelanggang; di bagan pun
    sudut harus terbaca tanpa menghitung urutan slot.

    Slot yang penghuninya belum pasti tetap netral. Mewarnainya lebih dulu
    berarti menjanjikan sudut yang belum diputuskan.

    Bagan 32 peserta selebar 1500px — pembungkusnya digulir mendatar, bukan
    diperkecil sampai namanya tidak terbaca.
--}}

<div class="overflow-x-auto">
    <div class="relative" style="width: {{ $pohon['lebar'] }}px; height: {{ $pohon['tinggi'] + 28 }}px">

        @foreach ($pohon['kolom'] as $kolom)
            <div class="absolute text-[11px] tracking-[.1em] text-ink-muted uppercase"
                 style="left: {{ $kolom['x'] }}px; top: 0">
                {{ $kolom['judul'] }}
            </div>

            @foreach ($kolom['slot'] as $slot)
                @php
                    $kosong = ($slot['kosong'] ?? false) || ($slot['menunggu'] ?? false);
                    $warna = match (true) {
                        $kosong => '',
                        $slot['sudut'] === 'merah' => 'bg-corner-red text-white',
                        default => 'bg-corner-blue text-white',
                    };
                @endphp

                <div @class([
                        'absolute flex items-center gap-2 overflow-hidden rounded-[var(--radius)] px-2.5',
                        $warna => ! $kosong,
                        'border border-dashed border-line bg-surface-inset text-ink-secondary' => ($slot['kosong'] ?? false),
                        'border border-line bg-surface-inset text-ink-secondary' => ($slot['menunggu'] ?? false),
                     ])
                     style="left: {{ $kolom['x'] }}px; top: {{ $slot['y'] + 28 }}px; width: {{ $kolom['lebar'] }}px; height: {{ $pohon['slot_tinggi'] }}px">

                    @isset($slot['nomor'])
                        <span class="shrink-0 font-mono text-[12px] tabular-nums {{ $kosong ? '' : 'opacity-75' }}">
                            {{ $slot['nomor'] }}
                        </span>
                    @endisset

                    @if ($kosong)
                        <span class="min-w-0 flex-1 truncate text-[13px]">
                            {{ ($slot['kosong'] ?? false) ? 'Kosong — bye' : 'Menunggu babak sebelumnya' }}
                        </span>
                    @else
                        <span class="min-w-0 flex-1 truncate text-[14px] font-semibold">{{ $slot['nama'] }}</span>

                        @if (! empty($slot['kontingen']))
                            <span class="shrink-0 truncate text-[12px] {{ $slot['sudut'] === 'merah' ? 'text-corner-red-on' : 'text-corner-blue-on' }}"
                                  style="max-width: 40%">{{ $slot['kontingen'] }}</span>
                        @endif

                        @if ($slot['bye'] ?? false)
                            <span class="shrink-0 text-[11px] font-semibold uppercase opacity-85">bye</span>
                        @endif
                    @endif
                </div>
            @endforeach
        @endforeach

        {{-- Garis penghubung digambar setelah slot supaya ia tidak tertutup. --}}
        @foreach ($pohon['garis'] as $g)
            @if ($g['jenis'] === 'h')
                <div class="absolute border-t border-line-strong"
                     style="left: {{ $g['x'] }}px; top: {{ $g['y'] + 28 }}px; width: {{ $g['panjang'] }}px"></div>
            @else
                <div class="absolute border-l border-line-strong"
                     style="left: {{ $g['x'] }}px; top: {{ $g['y'] + 28 }}px; height: {{ $g['panjang'] }}px"></div>
            @endif
        @endforeach
    </div>
</div>
