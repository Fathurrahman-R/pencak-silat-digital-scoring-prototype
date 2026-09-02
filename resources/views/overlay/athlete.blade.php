@php $latar = $corner === 'blue' ? 'bg-silat-biru' : 'bg-silat-merah'; @endphp

<x-layouts.overlay title="Lower third atlet">
    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div x-show="adaPartai && {{ $corner }}" x-cloak class="absolute bottom-[64px] left-[64px] flex items-stretch"
             style="box-shadow: 0 12px 40px rgba(0,0,0,.45)">
            {{--
                Batang sudut 6px, bukan 2px. Overlay digambar di kanvas 1920px
                lalu dikecilkan ke layar penonton -- di HP, 2px dari 1920 tidak
                menyisakan satu piksel utuh pun, dan satu-satunya penanda merah
                atau biru pada lower third ini hilang sama sekali.
            --}}
            <div class="{{ $latar }} w-[6px]"></div>
            <div class="bg-silat-siaran-tengah px-6 py-4">
                <p class="text-[28px] font-medium text-silat-teks" x-text="{{ $corner }}?.nama"></p>
                <p class="text-[17px] text-silat-teks-redup" x-text="{{ $corner }}?.kontingen"></p>
            </div>
        </div>
    </div>
</x-layouts.overlay>
