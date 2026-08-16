@props([
    'sudut' => 'red',
    'kunciSkor' => 'merah',
    'rata' => 'kiri',
    'indikator' => false,
])

@php
    $latar = $sudut === 'blue' ? 'bg-silat-biru' : 'bg-silat-merah';
    $redup = $sudut === 'blue' ? 'text-[#9ebbea]' : 'text-[#f5afb2]';
    $samar = $sudut === 'blue' ? 'text-[#cbdbf7]' : 'text-[#fbd9da]';
    $namaSudut = $sudut === 'blue' ? 'Sudut biru' : 'Sudut merah';
    $kanan = $rata === 'kanan';
@endphp

<div class="{{ $latar }} flex flex-col justify-between rounded-silat p-4 {{ $kanan ? 'text-right' : '' }}">
    <div>
        <p class="text-[11px] tracking-[.08em] {{ $samar }}">{{ $namaSudut }}</p>
        <p class="text-[17px] font-medium text-silat-teks" x-text="(match.{{ $sudut }}?.athletes ?? []).join(', ') || '—'"></p>
        <p class="text-[13px] {{ $redup }}" x-text="match.{{ $sudut }}?.contingent ?? '—'"></p>
    </div>

    <p class="silat-angka mt-1 text-[56px] leading-none font-medium text-silat-teks" x-text="skorTotal.{{ $kunciSkor }}"></p>

    <div class="mt-3 flex flex-col gap-1.5 {{ $kanan ? 'items-end' : 'items-start' }}">
        @foreach (['pembinaan', 'teguran', 'peringatan'] as $jenis)
            <div class="flex items-center gap-2 {{ $kanan ? 'flex-row-reverse' : '' }}">
                <x-silat.ikon :nama="$jenis" :ukuran="14" :label="null" class="{{ $redup }}" />
                <span class="text-[11px] tracking-wide {{ $redup }}">{{ ucfirst($jenis) }}</span>
                <div class="flex gap-1 {{ $kanan ? 'flex-row-reverse' : '' }}" aria-hidden="true">
                    <template x-for="i in {{ $jenis === 'peringatan' ? 3 : 2 }}" :key="i">
                        <span
                            class="h-2.5 w-5 rounded-[2px]"
                            x-bind:class="i <= hukuman.{{ $kunciSkor }}.{{ $jenis }} ? 'bg-white/80' : 'bg-black/25'"
                        ></span>
                    </template>
                </div>
            </div>
        @endforeach
    </div>

    @if ($indikator)
        <div class="mt-3 flex gap-3 {{ $kanan ? 'flex-row-reverse justify-end' : '' }}" x-show="peraturan.jumlah_juri">
            <template x-for="i in peraturan.jumlah_juri" :key="i">
                <span
                    class="size-[14px] rounded-full"
                    x-bind:class="indikator.{{ $sudut }}.includes(i) ? 'bg-white' : 'bg-black/25'"
                ></span>
            </template>
        </div>
    @endif
</div>
