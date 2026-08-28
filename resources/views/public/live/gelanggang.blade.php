<x-layouts.silat :title="'Live — '.$arena->name">
    <div x-data="overlayLive(@js($config))" class="mx-auto flex min-h-screen max-w-2xl flex-col gap-4 p-4">
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
            <div class="rounded-silat bg-silat-panel p-6 text-center">
                <p class="text-[14px] text-silat-teks-redup">Belum ada partai berjalan di gelanggang ini.</p>
            </div>
        </template>

        <div x-show="adaPartai" x-cloak class="flex flex-col gap-4">
            <div class="text-center">
                <p class="text-[12px] tracking-wide text-silat-teks-redup" x-text="kelas ? (kelas.jenis_kelamin+' '+kelas.golongan+' — '+kelas.nama) : ''"></p>
                <p class="text-[12px] text-silat-teks-redup" x-text="babakLabel"></p>
                <p class="silat-angka mt-1 text-[40px] font-medium text-silat-teks" x-text="tampilWaktu"></p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-silat bg-silat-merah p-4 text-center" x-bind:class="kilat === 'red' ? 'silat-kilat' : ''">
                    <p class="truncate text-[15px] font-medium text-silat-teks" x-text="red?.nama"></p>
                    <p class="truncate text-[12px] text-white/70" x-text="red?.kontingen"></p>
                    <p class="silat-angka mt-2 text-[48px] leading-none font-medium text-silat-teks" x-text="skorTotal.merah"></p>
                    <p class="mt-2 text-[11px] text-white/70">
                        Pembinaan <span x-text="hukuman.merah.pembinaan"></span>
                        · Teguran <span x-text="hukuman.merah.teguran"></span>
                        · Peringatan <span x-text="hukuman.merah.peringatan"></span>
                    </p>
                </div>

                <div class="rounded-silat bg-silat-biru p-4 text-center" x-bind:class="kilat === 'blue' ? 'silat-kilat' : ''">
                    <p class="truncate text-[15px] font-medium text-silat-teks" x-text="blue?.nama"></p>
                    <p class="truncate text-[12px] text-white/70" x-text="blue?.kontingen"></p>
                    <p class="silat-angka mt-2 text-[48px] leading-none font-medium text-silat-teks" x-text="skorTotal.biru"></p>
                    <p class="mt-2 text-[11px] text-white/70">
                        Pembinaan <span x-text="hukuman.biru.pembinaan"></span>
                        · Teguran <span x-text="hukuman.biru.teguran"></span>
                        · Peringatan <span x-text="hukuman.biru.peringatan"></span>
                    </p>
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
                <div class="rounded-silat bg-silat-panel p-4 text-center"
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
