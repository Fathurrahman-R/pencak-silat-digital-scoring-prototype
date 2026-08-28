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

            <x-silat.indikator-koneksi />
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
                            class="rounded-silat bg-silat-aksi px-4 py-2 text-[13px] font-medium text-silat-aksi-teks">Lanjutkan</button>
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

                        {{--
                            Kata yang dibaca lebih dulu adalah istilah naskah — Pembinaan,
                            Teguran, Peringatan (Pasal 11.6.d.4) — bukan ringan/sedang/berat.
                            Wasit menghafal peraturan dalam istilah itu, dan tiap tingkat
                            punya akibat angka yang berbeda; memaksanya menerjemahkan sendiri
                            saat pertandingan berjalan adalah tempat kesalahan lahir.

                            Sebutan sehari-hari tetap ditahan sebagai baris kedua, karena
                            sebagian wasit memang memakainya di gelanggang. Yang berubah
                            hanya mana yang jadi label utama.

                            Warna teks mengikuti latar tombolnya, bukan satu aturan seragam:
                            di atas oranye teguran (#d98324) teks putih hanya mencapai
                            2.75:1, sedangkan teks gelap 7.63:1. Menyeragamkannya jadi putih
                            akan mengulang persis cacat yang diperbaiki di sini.
                        --}}
                        <div class="mb-3 grid grid-cols-3 gap-2">
                            @php
                                $tingkatHukuman = [
                                    ['kirim' => 'ringan', 'resmi' => 'Pembinaan', 'sehari' => 'ringan', 'latar' => 'bg-silat-pembinaan', 'teks' => 'text-silat-teks', 'kedua' => 'text-white'],
                                    ['kirim' => 'sedang', 'resmi' => 'Teguran', 'sehari' => 'sedang', 'latar' => 'bg-silat-teguran', 'teks' => 'text-silat-latar', 'kedua' => 'text-silat-latar/75'],
                                    ['kirim' => 'berat', 'resmi' => 'Peringatan', 'sehari' => 'berat', 'latar' => 'bg-silat-peringatan', 'teks' => 'text-silat-teks', 'kedua' => 'text-white'],
                                ];
                            @endphp

                            @foreach ($tingkatHukuman as $tingkat)
                                <button type="button"
                                        x-on:click="kirimHukuman('{{ $sudutKey }}', '{{ $tingkat['kirim'] }}', null)"
                                        aria-label="{{ $tingkat['resmi'] }} untuk sudut {{ $sudutLabel }}"
                                        class="flex min-h-16 flex-col items-center justify-center rounded-silat {{ $tingkat['latar'] }} px-2 py-2 {{ $tingkat['teks'] }}">
                                    <span class="text-[14px] leading-tight font-medium">{{ $tingkat['resmi'] }}</span>
                                    <span class="text-[11px] leading-tight {{ $tingkat['kedua'] }}">({{ $tingkat['sehari'] }})</span>
                                </button>
                            @endforeach
                        </div>

                        {{-- Hitungan teknik juga ditekan sambil berdiri, jadi tingginya
                             mengikuti batas sentuh yang sama (--silat-sentuh-min: 64px).
                             Sebelumnya 30px, yang tidak pernah masuk akal untuk aksi
                             waktu-kritis di tengah hitungan wasit. --}}
                        <div class="flex items-stretch gap-2">
                            <input
                                type="number" min="1" max="10"
                                aria-label="Hitungan ke berapa untuk sudut {{ $sudutLabel }}"
                                x-model.number="{{ $sudutKey === 'red' ? 'hitunganMerah' : 'hitunganBiru' }}"
                                class="silat-angka min-h-16 w-20 rounded-silat border border-silat-garis bg-silat-latar px-2 text-center text-[18px] text-silat-teks"
                            >
                            <button type="button"
                                    x-on:click="kirimHitungan('{{ $sudutKey }}', {{ $sudutKey === 'red' ? 'hitunganMerah' : 'hitunganBiru' }})"
                                    class="min-h-16 flex-1 rounded-silat bg-silat-mati px-3 text-[14px] text-silat-teks">
                                Catat hitungan
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endresource
    </div>
</x-layouts.silat>
