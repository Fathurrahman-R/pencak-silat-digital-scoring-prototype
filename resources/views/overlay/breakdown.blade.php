<x-layouts.overlay title="Rincian nilai & hukuman">
    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div x-show="adaPartai" x-cloak class="absolute top-[64px] right-[64px] flex flex-col gap-3 rounded-silat bg-silat-panel p-5"
             style="width: 460px; box-shadow: 0 12px 40px rgba(0,0,0,.45)">
            @foreach (['merah' => 'red', 'biru' => 'blue'] as $kunciSkor => $sudut)
                @php $redup = $sudut === 'blue' ? 'text-[#9ebbea]' : 'text-[#f5afb2]'; @endphp
                <div
                    class="flex items-center justify-between gap-3 rounded-[4px] px-2 py-1.5 transition-opacity"
                    x-bind:class="kilat === '{{ $sudut }}' ? 'silat-kilat bg-white/10' : ''"
                >
                    <div class="min-w-0">
                        <p class="truncate text-[16px] font-medium text-silat-teks" x-text="{{ $sudut }}?.nama"></p>
                        <div class="mt-1 flex gap-3">
                            @foreach (['pembinaan', 'teguran', 'peringatan'] as $jenis)
                                <div class="flex items-center gap-1">
                                    <x-silat.ikon :nama="$jenis" :ukuran="12" :label="null" class="{{ $redup }}" />
                                    <div class="flex gap-[3px]" aria-hidden="true">
                                        <template x-for="i in {{ $jenis === 'peringatan' ? 3 : 2 }}" :key="i">
                                            <span class="h-[9px] w-[16px] rounded-[1px]"
                                                  x-bind:class="i <= hukuman.{{ $kunciSkor }}.{{ $jenis }} ? 'bg-white/85' : 'bg-white/15'"></span>
                                        </template>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <p class="silat-angka shrink-0 text-[32px] leading-none font-medium text-silat-teks" x-text="skorTotal.{{ $kunciSkor }}"></p>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.overlay>
