@props([
    'sudut' => 'red',
    'kunciSkor' => 'merah',
    'rata' => 'kiri',
    'indikator' => false,

    /*
     * Ukuran angka skor mengikuti jarak baca, bukan selera. Di panel operator
     * papan skor mengisi setengah layar dan dibaca dari jarak meja gelanggang,
     * jadi angkanya jauh lebih besar daripada di panel yang hanya menampilkan
     * skor sebagai konteks pendukung.
     */
    'ukuranAngka' => 'panel',
])

@php
    $latar = $sudut === 'blue' ? 'bg-silat-biru' : 'bg-silat-merah';

    // Lihat catatan di <x-silat.blok-sudut>: nuansa merah muda lama hanya 2.89:1
    // di atas #d42027 sementara padanan birunya 4.63:1, jadi identitas pesilat
    // merah selalu lebih sulit dibaca. Angka di bawah sudah dihitung >= 4.5:1.
    // Nuansa teks di dalam bidang sudut. Nilainya token, bukan hex mentah --
    // dulu #9ebbea dan #cbdbf7 yang sebenarnya lolos kontras (7.04 dan 9.83)
    // tapi berdiri sendiri di luar sistem.
    $redup = $sudut === 'blue' ? 'text-silat-teks-biru-samar' : 'text-silat-teks-merah';
    $samar = $sudut === 'blue' ? 'text-silat-teks-biru' : 'text-silat-teks-merah-redup';

    $angkaKelas = [
        'papan' => 'text-[104px] leading-[.86]',
        'operator' => 'text-[136px] leading-[.86]',
        'panel' => 'text-[56px] leading-none',
    ][$ukuranAngka] ?? 'text-[56px] leading-none';
    $namaSudut = $sudut === 'blue' ? 'Sudut biru' : 'Sudut merah';
    $kanan = $rata === 'kanan';
@endphp

<div class="{{ $latar }} flex flex-col justify-between rounded-silat p-4 {{ $kanan ? 'text-right' : '' }}">
    <div>
        <p class="text-[11px] tracking-[.08em] {{ $samar }}">{{ $namaSudut }}</p>
        <p class="text-[17px] font-medium text-silat-teks" x-text="(match.{{ $sudut }}?.athletes ?? []).join(', ') || '—'"></p>
        <p class="text-[13px] {{ $redup }}" x-text="match.{{ $sudut }}?.contingent ?? '—'"></p>
    </div>

    <p class="silat-angka mt-1 font-medium text-silat-teks {{ $angkaKelas }}" x-text="skorTotal.{{ $kunciSkor }}"></p>

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
