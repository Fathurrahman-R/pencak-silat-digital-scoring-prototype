@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Wasit — '.$match->bracket->weightClass->name">
    <div x-data="partaiPanel(@js($config))" class="mx-auto flex min-h-screen max-w-4xl flex-col gap-4 p-4">
        <header class="flex items-center justify-between gap-4">
            <div>
                <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-samar">WASIT</p>
                <h1 class="text-[17px] font-medium text-silat-teks">
                    {{ $match->bracket->weightClass->jenis_kelamin->label() }}
                    {{ $match->bracket->weightClass->golongan_usia->label() }} —
                    {{ $match->bracket->weightClass->name }}
                </h1>
            </div>

            <span
                class="rounded-full px-3 py-1 text-[11px] tracking-wide"
                x-bind:class="$store.koneksi.tersambung ? 'bg-emerald-500/15 text-emerald-300' : 'bg-red-500/15 text-red-300'"
                x-text="$store.koneksi.tersambung ? 'Tersambung' : 'Terputus'"
            ></span>
        </header>

        <p x-show="galat" x-text="galat" class="rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        <div class="rounded-silat bg-silat-panel p-4 text-center">
            <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-redup">
                Babak <span x-text="match.current_round ?? '–'"></span><span class="text-silat-teks-samar">/<span x-text="peraturan.jumlah_babak"></span></span>
            </p>
            <div
                class="silat-angka text-[32px] leading-none font-medium text-silat-teks"
                x-text="tampilWaktu"
                role="timer"
                aria-live="off"
            >00:00</div>

            @resource(rk('partai', ResourceAction::Update))
                <div class="mt-3 flex flex-wrap justify-center gap-2">
                    <button type="button" x-show="babakAktif?.status === 'berjalan'" x-on:click="jeda()"
                            class="rounded-silat bg-silat-mati px-4 py-2 text-[13px] text-silat-teks">Hentikan sementara</button>
                    <button type="button" x-show="babakAktif?.status === 'jeda'" x-on:click="lanjutkan()"
                            class="rounded-silat bg-silat-biru px-4 py-2 text-[13px] text-silat-teks">Lanjutkan</button>
                </div>
            @endresource
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-silat.papan-skor sudut="red" kunci-skor="merah" rata="kiri" />
            <x-silat.papan-skor sudut="blue" kunci-skor="biru" rata="kanan" />
        </div>

        @resource(rk('hukuman', ResourceAction::Create))
            <div class="grid gap-3 sm:grid-cols-2" x-data="{ corner: 'red', tingkat: 'ringan', catatan: '', hitunganMerah: 1, hitunganBiru: 1 }">
                @foreach (['red' => 'Merah', 'blue' => 'Biru'] as $sudutKey => $sudutLabel)
                    <div class="rounded-silat bg-silat-panel p-4">
                        <p class="mb-3 text-[13px] font-medium text-silat-teks">Sudut {{ $sudutLabel }}</p>

                        <div class="mb-3 grid grid-cols-3 gap-2">
                            <button type="button" x-on:click="kirimHukuman('{{ $sudutKey }}', 'ringan', null)"
                                    class="rounded-silat bg-silat-pembinaan px-2 py-2.5 text-[12px] text-silat-teks">
                                Ringan<br><span class="text-silat-teks-samar">(pembinaan)</span>
                            </button>
                            <button type="button" x-on:click="kirimHukuman('{{ $sudutKey }}', 'sedang', null)"
                                    class="rounded-silat bg-silat-teguran px-2 py-2.5 text-[12px] text-silat-teks">
                                Sedang<br><span class="text-silat-teks-samar">(teguran)</span>
                            </button>
                            <button type="button" x-on:click="kirimHukuman('{{ $sudutKey }}', 'berat', null)"
                                    class="rounded-silat bg-silat-peringatan px-2 py-2.5 text-[12px] text-silat-teks">
                                Berat<br><span class="text-silat-teks-samar">(peringatan)</span>
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                type="number" min="1" max="10"
                                x-model.number="{{ $sudutKey === 'red' ? 'hitunganMerah' : 'hitunganBiru' }}"
                                class="w-16 rounded-silat border border-silat-garis bg-silat-latar px-2 py-1.5 text-[13px] text-silat-teks"
                            >
                            <button type="button"
                                    x-on:click="kirimHitungan('{{ $sudutKey }}', {{ $sudutKey === 'red' ? 'hitunganMerah' : 'hitunganBiru' }})"
                                    class="flex-1 rounded-silat bg-silat-mati px-3 py-1.5 text-[12px] text-silat-teks">
                                Catat hitungan
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endresource
    </div>
</x-layouts.silat>
