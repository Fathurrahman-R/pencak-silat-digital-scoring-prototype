<x-layouts.silat :title="'Live — '.$arena->name" permukaan="publik">
    @php
        /*
         * Jumlah petak per tingkat mengikuti tangga Pasal 11.6.d.4 di
         * config/scoring.php -- sama sumbernya dengan mesin scoring, jadi layar
         * penonton tidak pernah menjanjikan jatah yang berbeda dari yang
         * dihitung server.
         */
        $petakHukuman = [
            'pembinaan' => config('scoring.tanding.hukuman.pembinaan.jumlah_kolom', 2),
            'teguran' => config('scoring.tanding.hukuman.teguran.jumlah_kolom', 2),
            'peringatan' => config('scoring.tanding.hukuman.peringatan.jumlah_kolom', 3),
        ];
    @endphp

    <x-silat.kepala-publik :judul="$arena->tournament->name"
                           :keterangan="$arena->name"
                           :tautan="['Kejuaraan' => route('live.turnamen', $arena->tournament), 'Medali' => route('live.turnamen.medali', $arena->tournament)]"
                           aktif="Kejuaraan" />

    {{--
        Live score publik — mengikuti `publik-beranda-live.dc.html`.

        Suasana TERANG bawaan: halaman ini dibuka di tribun, di HP, di bawah
        matahari. Yang tetap gelap adalah bidang sudut, karena merah dan biru
        adalah identitas pesilat dan nilainya tidak berubah di permukaan mana
        pun.
    --}}
    <div x-data="overlayLive(@js($config))" class="mx-auto w-full max-w-[1180px] px-5 pt-7 pb-14 sm:px-10">
        <div class="mb-5 flex flex-wrap items-center gap-3">
            <span class="silat-angka inline-flex h-6 items-center rounded-silat-kecil bg-silat-aksi px-2.5 text-[10.5px] font-semibold tracking-[.1em] text-silat-aksi-teks uppercase"
                  x-show="adaPartai">Sedang berlangsung</span>
            <span class="silat-angka text-[12.5px] text-silat-teks-redup">
                {{ $arena->name }}<span x-show="match?.id"> · Partai <span x-text="match?.id"></span></span>
            </span>
            <span class="ml-auto text-[13.5px] text-silat-teks-redup"
                  x-text="kelas ? (kelas.jenis_kelamin + ' ' + kelas.golongan + ' — ' + kelas.nama) : ''"></span>
        </div>

        <template x-if="memuat">
            <p class="text-[13.5px] text-silat-teks-redup">Memuat…</p>
        </template>

        <template x-if="! memuat && ! adaPartai">
            <div class="rounded-silat-besar border border-dashed border-silat-tepi-kendali px-6 py-16 text-center">
                <p class="text-[16px] font-semibold text-silat-teks">Belum ada partai berjalan</p>
                <p class="mx-auto mt-1 max-w-[64ch] text-[14px] leading-relaxed text-silat-teks-redup">
                    Skor muncul di sini begitu operator memulai partai berikutnya di gelanggang ini.
                </p>
            </div>
        </template>

        {{--
            Konstanta petak hidup di x-data BERSARANG. Menyebarnya ke dalam
            `{...overlayLive(cfg), ...}` akan mengevaluasi getter milik
            overlayLive sekali lalu membekukan hasilnya jadi nilai statis --
            cacat yang sudah pernah ditemukan di panel juri Tanding.
        --}}
        <div x-show="adaPartai" x-cloak x-data="{ petakHukuman: @js($petakHukuman) }">
            <div class="grid overflow-hidden rounded-silat-dialog md:grid-cols-[1fr_200px_1fr]">

                <div class="bg-silat-merah-dalam p-8 text-white" x-bind:class="kilat === 'red' ? 'silat-kilat' : ''">
                    <p class="silat-angka text-[11px] tracking-[.14em] text-silat-teks-merah-samar uppercase">Sudut merah</p>
                    <p class="mt-2.5 truncate text-[30px] leading-[1.15] font-semibold tracking-[-0.025em]" x-text="red?.nama"></p>
                    <p class="mt-1 truncate text-[16px] text-silat-teks-merah-redup" x-text="red?.kontingen"></p>
                    <p class="silat-angka mt-4.5 text-[110px] leading-[0.85] font-medium" x-text="skorTotal.merah"></p>

                    {{-- Hukuman sebagai petak, bukan deret angka: "Pembinaan 1 ·
                         Teguran 0" menuntut penonton membaca tiga kali untuk tahu
                         satu hal — seberapa dekat pesilat ini ke sanksi berikutnya.
                         Istilah naskah ditulis penuh, tidak disingkat. --}}
                    <div class="mt-5.5">
                        @foreach (['pembinaan', 'teguran', 'peringatan'] as $jenis)
                            <div class="mt-1.5 flex items-center gap-2.5">
                                <span class="w-[86px] shrink-0 text-[13px] text-silat-teks-merah-redup">{{ ucfirst($jenis) }}</span>
                                <div class="flex gap-1.5" aria-hidden="true">
                                    <template x-for="i in petakHukuman['{{ $jenis }}']" :key="'m-{{ $jenis }}-'+i">
                                        <span class="grid h-6 w-[30px] place-items-center rounded-silat-kecil border-[1.5px] border-silat-teks-merah-samar text-silat-teks-merah-samar"
                                              x-bind:class="[
                                                  (hukuman.merah['{{ $jenis }}'] ?? 0) >= i ? 'bg-white border-white text-silat-panel' : '',
                                                  ('{{ $jenis }}' === 'peringatan' && i === petakHukuman['{{ $jenis }}']) ? 'border-dashed' : '',
                                              ]">
                                            <x-silat.ikon :nama="$jenis" :ukuran="12" :label="null" />
                                        </span>
                                    </template>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col items-center justify-center bg-silat-panel px-4 py-8">
                    {{-- Tahap bagan DAN babak keberapa, keduanya — sama seperti
                         scorebug siaran. "Perdelapan final" dan "Babak 1/3"
                         menjawab dua pertanyaan berbeda, dan penonton yang
                         berpindah dari siaran ke HP tidak boleh kehilangan
                         salah satunya. --}}
                    <p class="silat-angka text-[11px] tracking-[.12em] text-silat-teks-redup uppercase" x-text="babakLabel"></p>
                    <p class="silat-angka mt-3 text-[44px] leading-none font-medium text-silat-teks" x-text="tampilWaktu"></p>
                    <p class="silat-angka mt-2 text-[11px] tracking-[.12em] text-silat-teks-samar uppercase"
                       x-show="match?.current_round"
                       x-text="'Babak ' + match?.current_round + (jumlahBabak ? '/' + jumlahBabak : '')"></p>
                    <p class="silat-angka mt-3 text-[11px] tracking-[.12em] text-silat-teks-redup uppercase"
                       x-text="match?.status === 'selesai' ? 'Selesai' : 'Berjalan'"></p>
                </div>

                <div class="bg-silat-biru-dalam p-8 text-right text-white" x-bind:class="kilat === 'blue' ? 'silat-kilat' : ''">
                    <p class="silat-angka text-[11px] tracking-[.14em] text-silat-teks-biru-samar uppercase">Sudut biru</p>
                    <p class="mt-2.5 truncate text-[30px] leading-[1.15] font-semibold tracking-[-0.025em]" x-text="blue?.nama"></p>
                    <p class="mt-1 truncate text-[16px] text-silat-teks-biru" x-text="blue?.kontingen"></p>
                    <p class="silat-angka mt-4.5 text-[110px] leading-[0.85] font-medium" x-text="skorTotal.biru"></p>

                    <div class="mt-5.5">
                        @foreach (['pembinaan', 'teguran', 'peringatan'] as $jenis)
                            <div class="mt-1.5 flex items-center justify-end gap-2.5">
                                <div class="flex flex-row-reverse gap-1.5" aria-hidden="true">
                                    <template x-for="i in petakHukuman['{{ $jenis }}']" :key="'b-{{ $jenis }}-'+i">
                                        <span class="grid h-6 w-[30px] place-items-center rounded-silat-kecil border-[1.5px] border-silat-teks-biru-samar text-silat-teks-biru-samar"
                                              x-bind:class="[
                                                  (hukuman.biru['{{ $jenis }}'] ?? 0) >= i ? 'bg-white border-white text-silat-panel' : '',
                                                  ('{{ $jenis }}' === 'peringatan' && i === petakHukuman['{{ $jenis }}']) ? 'border-dashed' : '',
                                              ]">
                                            <x-silat.ikon :nama="$jenis" :ukuran="12" :label="null" />
                                        </span>
                                    </template>
                                </div>
                                <span class="w-[86px] shrink-0 text-[13px] text-silat-teks-biru">{{ ucfirst($jenis) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <p class="mt-3.5 text-[13px] leading-[1.6] text-silat-teks-redup">
                Hukuman ditulis dengan istilah naskah — Pembinaan, Teguran, Peringatan — bukan disingkat atau
                diterjemahkan. Angka di papan sudah memperhitungkan pengurangannya.
            </p>

            {{--
                `match?.status`, bukan `match.status`. Saat halaman digambar sebelum
                muatan pertama tiba, `match` masih null dan pembacaan langsung
                melempar "TypeError: Cannot read properties of null" ke console
                penonton.

                Alasan menang WAJIB lewat AlasanMenang: nilai mentahnya menyesatkan.
                `diskualifikasi` di sini berarti pemenang menang KARENA lawan
                didiskualifikasi. Dirender apa adanya di sebelah nama pemenang,
                kalimatnya terbaca "Candra Setiawan — diskualifikasi" — persis
                kebalikan dari yang terjadi.
            --}}
            <template x-if="match?.status === 'selesai'">
                <div class="mt-6 rounded-silat-besar border border-silat-garis px-5 py-4.5 text-center"
                     x-data="{ sebabLabel: @js(App\Support\Scoring\AlasanMenang::peta()) }">
                    <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-redup uppercase">Hasil</p>
                    <p class="mt-1.5 text-[18px] font-semibold text-silat-teks">
                        <span x-text="match?.winner_corner === 'red' ? red?.nama : blue?.nama"></span>
                        — <span x-text="sebabLabel[match?.win_reason] ?? match?.win_reason"></span>
                    </p>
                    <p class="mt-1 text-[13px] text-silat-teks-redup" x-show="! match?.ratified">
                        Menunggu pengesahan Dewan Wasit Juri.
                    </p>
                </div>
            </template>
        </div>

        <footer class="mt-10 flex items-center justify-center gap-3 text-center text-[12px] text-silat-teks-samar">
            <x-silat.indikator-koneksi />
            <span>Skor tampil realtime dari gelanggang, dan menyambung ulang otomatis bila koneksi terputus.</span>
        </footer>
    </div>
</x-layouts.silat>
