<x-layouts.silat :title="'Live — '.$arena->name">
    <div x-data="overlayLive(@js($config))" class="mx-auto flex min-h-screen max-w-2xl flex-col gap-4 p-4">
        <header class="flex items-center justify-between gap-4">
            <div>
                <p class="text-[13px] tracking-[.14em] text-silat-emas">LIVE SCORE</p>
                <h1 class="text-[20px] font-medium text-silat-teks">{{ $arena->tournament->name }}</h1>
                <p class="text-[13px] text-silat-teks-redup">{{ $arena->name }}</p>
            </div>

            <span
                class="rounded-full px-3 py-1 text-[11px] tracking-wide"
                x-bind:class="$store.koneksi.tersambung ? 'bg-emerald-500/15 text-emerald-300' : 'bg-red-500/15 text-red-300'"
                x-text="$store.koneksi.tersambung ? 'Tersambung' : 'Menyambung ulang…'"
            ></span>
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
                        Binaan <span x-text="hukuman.merah.pembinaan"></span>
                        · Teguran <span x-text="hukuman.merah.teguran"></span>
                        · Peringatan <span x-text="hukuman.merah.peringatan"></span>
                    </p>
                </div>

                <div class="rounded-silat bg-silat-biru p-4 text-center" x-bind:class="kilat === 'blue' ? 'silat-kilat' : ''">
                    <p class="truncate text-[15px] font-medium text-silat-teks" x-text="blue?.nama"></p>
                    <p class="truncate text-[12px] text-white/70" x-text="blue?.kontingen"></p>
                    <p class="silat-angka mt-2 text-[48px] leading-none font-medium text-silat-teks" x-text="skorTotal.biru"></p>
                    <p class="mt-2 text-[11px] text-white/70">
                        Binaan <span x-text="hukuman.biru.pembinaan"></span>
                        · Teguran <span x-text="hukuman.biru.teguran"></span>
                        · Peringatan <span x-text="hukuman.biru.peringatan"></span>
                    </p>
                </div>
            </div>

            <template x-if="match.status === 'selesai'">
                <div class="rounded-silat bg-silat-panel p-4 text-center">
                    <p class="text-[13px] text-silat-teks-redup">Hasil</p>
                    <p class="text-[16px] font-medium text-silat-teks">
                        <span x-text="match.winner_corner === 'red' ? red?.nama : blue?.nama"></span>
                        — <span x-text="match.win_reason"></span>
                    </p>
                    <p class="mt-1 text-[12px] text-silat-teks-redup" x-show="! match.ratified">Menunggu pengesahan dewan juri.</p>
                </div>
            </template>
        </div>

        <footer class="mt-auto pt-6 text-center text-[11px] text-silat-teks-samar">
            Skor tampil realtime dari gelanggang. Halaman ini menyambung ulang otomatis bila koneksi terputus.
        </footer>
    </div>
</x-layouts.silat>
