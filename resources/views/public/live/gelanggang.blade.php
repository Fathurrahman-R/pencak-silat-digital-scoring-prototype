<x-layouts.silat :title="'Live — '.$arena->name">
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

    <div x-data="overlayLive(@js($config))" class="mx-auto flex min-h-screen max-w-[720px] flex-col gap-4 p-4">
        <header class="flex items-center justify-between gap-4">
            <div>
                {{-- Bukan emas: silat.css menetapkan emas hanya berarti juara/medali.
                     Dipakai sebagai hiasan judul, ia berhenti menandakan apa pun. --}}
                <p class="text-[13px] tracking-[.14em] text-silat-teks-redup">LIVE SCORE</p>
                <h1 class="text-[20px] font-medium text-silat-teks">{{ $arena->tournament->name }}</h1>
                <p class="text-[13px] text-silat-teks-redup">{{ $arena->name }}</p>
            </div>

            <x-silat.indikator-koneksi />
        </header>

        <template x-if="memuat">
            <p class="text-[13px] text-silat-teks-redup">Memuat…</p>
        </template>

        <template x-if="! memuat && ! adaPartai">
            <div class="border border-dashed border-silat-tepi-kendali px-5 py-8 text-center">
                <p class="text-[16px] font-medium text-silat-teks">Belum ada partai berjalan</p>
                <p class="mt-1 text-[14px] leading-relaxed text-silat-teks-redup">
                    Skor muncul di sini begitu operator memulai partai berikutnya di gelanggang ini.
                </p>
            </div>
        </template>

        <div x-show="adaPartai" x-cloak class="flex flex-col gap-4">
            <div class="text-center">
                <p class="text-[12px] tracking-wide text-silat-teks-redup" x-text="kelas ? (kelas.jenis_kelamin+' '+kelas.golongan+' — '+kelas.nama) : ''"></p>
                <p class="text-[12px] text-silat-teks-redup" x-text="babakLabel"></p>
                <p class="silat-angka mt-1 text-[40px] font-medium text-silat-teks" x-text="tampilWaktu"></p>
            </div>

            {{--
                Bertumpuk di HP, berdampingan begitu ada ruang. Penonton tribun
                membuka halaman ini dengan HP tegak; dua kolom di layar 390px
                menyisakan 180px per sudut, dan nama pesilat langsung terpotong.

                Bidangnya memakai merah-dalam dan biru-dalam, sama seperti papan
                skor panel. Selain menyeragamkan keduanya, itu yang membuat petak
                hukuman terbaca: tepi petak #8a8a90 hanya mencapai 1.52 di atas
                merah cerah #d42027, tapi 3.15 di atas #7a1418.
            --}}
            {{--
                Konstanta petak hidup di x-data BERSARANG. Menyebarnya ke dalam
                `{...overlayLive(cfg), ...}` akan mengevaluasi getter milik
                overlayLive sekali lalu membekukan hasilnya jadi nilai statis --
                cacat yang sudah pernah ditemukan di panel juri Tanding.
            --}}
            <div class="grid gap-3 sm:grid-cols-2"
                 x-data="{
                    petakHukuman: @js($petakHukuman),
                    warnaHukuman: { pembinaan: 'bg-silat-pembinaan', teguran: 'bg-silat-teguran', peringatan: 'bg-silat-peringatan' },
                 }">
                <div class="bg-silat-merah-dalam p-4" x-bind:class="kilat === 'red' ? 'silat-kilat' : ''">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[11px] tracking-[.12em] text-silat-teks-merah-redup uppercase">Sudut merah</p>
                            <p class="truncate text-[17px] font-medium text-silat-teks-merah" x-text="red?.nama"></p>
                            <p class="truncate text-[13px] text-silat-teks-merah-samar" x-text="red?.kontingen"></p>
                        </div>
                        <p class="silat-angka shrink-0 text-[56px] leading-none font-medium text-silat-teks" x-text="skorTotal.merah"></p>
                    </div>

                    {{-- Hukuman sebagai petak, bukan deret angka. "Pembinaan 1 ·
                         Teguran 0 · Peringatan 0" menuntut penonton membaca tiga
                         kali untuk tahu satu hal: seberapa dekat pesilat ini ke
                         sanksi berikutnya. --}}
                    <div class="mt-3 flex gap-4">
                        <template x-for="jenis in ['pembinaan','teguran','peringatan']" :key="'m-'+jenis">
                            <div class="flex flex-col items-start gap-1.5">
                                <div class="flex gap-1">
                                    <template x-for="i in petakHukuman[jenis]" :key="'m-'+jenis+'-'+i">
                                        <span class="size-6 border"
                                              x-bind:class="[
                                                  (hukuman.merah[jenis] ?? 0) >= i ? warnaHukuman[jenis] + ' border-transparent' : 'border-silat-tepi-petak',
                                                  (jenis === 'peringatan' && i === petakHukuman[jenis]) ? 'border-dashed' : '',
                                              ]"></span>
                                    </template>
                                </div>
                                <span class="text-[10px] tracking-[.06em] text-silat-teks-merah-samar uppercase" x-text="jenis"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="bg-silat-biru-dalam p-4" x-bind:class="kilat === 'blue' ? 'silat-kilat' : ''">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[11px] tracking-[.12em] text-silat-teks-biru-samar uppercase">Sudut biru</p>
                            <p class="truncate text-[17px] font-medium text-silat-teks-biru" x-text="blue?.nama"></p>
                            <p class="truncate text-[13px] text-silat-teks-biru-samar" x-text="blue?.kontingen"></p>
                        </div>
                        <p class="silat-angka shrink-0 text-[56px] leading-none font-medium text-silat-teks" x-text="skorTotal.biru"></p>
                    </div>

                    <div class="mt-3 flex gap-4">
                        <template x-for="jenis in ['pembinaan','teguran','peringatan']" :key="'b-'+jenis">
                            <div class="flex flex-col items-start gap-1.5">
                                <div class="flex gap-1">
                                    <template x-for="i in petakHukuman[jenis]" :key="'b-'+jenis+'-'+i">
                                        <span class="size-6 border"
                                              x-bind:class="[
                                                  (hukuman.biru[jenis] ?? 0) >= i ? warnaHukuman[jenis] + ' border-transparent' : 'border-silat-tepi-petak',
                                                  (jenis === 'peringatan' && i === petakHukuman[jenis]) ? 'border-dashed' : '',
                                              ]"></span>
                                    </template>
                                </div>
                                <span class="text-[10px] tracking-[.06em] text-silat-teks-biru-samar uppercase" x-text="jenis"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{--
                `match?.status`, bukan `match.status`. Saat halaman digambar sebelum
                muatan pertama tiba, `match` masih null dan pembacaan langsung
                melempar "TypeError: Cannot read properties of null" ke console
                penonton.

                Alasan menang WAJIB lewat AlasanMenang: nilai mentahnya menyesatkan.
                `diskualifikasi` di sini berarti pemenang menang KARENA lawan
                didiskualifikasi. Dirender apa adanya di sebelah nama pemenang,
                kalimatnya terbaca "Candra Setiawan — diskualifikasi" — persis
                kebalikan dari yang terjadi, di layar yang justru paling banyak
                ditonton orang luar.
            --}}
            <template x-if="match?.status === 'selesai'">
                <div class="border-t-2 border-silat-teks pt-4 text-center"
                     x-data="{ sebabLabel: @js(App\Support\Scoring\AlasanMenang::peta()) }">
                    <p class="text-[13px] text-silat-teks-redup">Hasil</p>
                    <p class="text-[16px] font-medium text-silat-teks">
                        <span x-text="match?.winner_corner === 'red' ? red?.nama : blue?.nama"></span>
                        — <span x-text="sebabLabel[match?.win_reason] ?? match?.win_reason"></span>
                    </p>
                    <p class="mt-1 text-[12px] text-silat-teks-redup" x-show="! match?.ratified">Menunggu pengesahan Dewan Wasit Juri.</p>
                </div>
            </template>
        </div>

        <footer class="mt-auto pt-6 text-center text-[11px] text-silat-teks-samar">
            Skor tampil realtime dari gelanggang. Halaman ini menyambung ulang otomatis bila koneksi terputus.
        </footer>
    </div>
</x-layouts.silat>
