@props([
    // [['teks' => 'Tagihan INV-2048 lunas', 'waktu' => '2 jam lalu'], …]
    // Butir pertama dianggap yang terbaru.
    'daftar' => [],
])

{{--
    Urutan kejadian, terbaru di atas.

    Yang terbaru ditandai titik beraksen DAN huruf tebal. Titik sendirian
    menaruh seluruh bedanya pada warna, dan bedanya kecil.
--}}

<ol {{ $attributes->class('flex flex-col gap-4') }}>
    @foreach ($daftar as $butir)
        <li class="flex gap-3">
            <span class="mt-[7px] size-2 shrink-0 rounded-full {{ $loop->first ? 'bg-accent' : 'bg-line-strong' }}"></span>

            <div class="min-w-0 flex-1">
                <p @class(['text-[13.5px] leading-relaxed text-ink', 'font-semibold' => $loop->first])>
                    {{ $butir['teks'] }}
                </p>

                @if ($butir['waktu'] ?? null)
                    <p class="mt-0.5 text-[12.5px] text-ink-muted">{{ $butir['waktu'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
