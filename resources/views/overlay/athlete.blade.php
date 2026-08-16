@php $latar = $corner === 'blue' ? 'bg-silat-biru' : 'bg-silat-merah'; @endphp

<x-layouts.overlay title="Lower third atlet">
    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div x-show="adaPartai && {{ $corner }}" x-cloak class="absolute bottom-[64px] left-[64px] flex items-stretch overflow-hidden rounded-silat"
             style="box-shadow: 0 12px 40px rgba(0,0,0,.45)">
            <div class="{{ $latar }} w-[2px]"></div>
            <div class="bg-silat-panel px-6 py-4">
                <p class="text-[28px] font-medium text-silat-teks" x-text="{{ $corner }}?.nama"></p>
                <p class="text-[17px] text-silat-teks-redup" x-text="{{ $corner }}?.kontingen"></p>
            </div>
        </div>
    </div>
</x-layouts.overlay>
