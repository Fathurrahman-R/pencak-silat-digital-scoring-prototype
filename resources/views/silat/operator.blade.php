@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Operator — '.$match->bracket->weightClass->name">
    <div x-data="partaiPanel(@js($config))" class="mx-auto flex min-h-screen max-w-4xl flex-col gap-4 p-4">
        <header class="flex items-center justify-between gap-4">
            <div>
                <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-samar">OPERATOR GELANGGANG</p>
                <h1 class="text-[17px] font-medium text-silat-teks">
                    {{ $match->bracket->weightClass->jenis_kelamin->label() }}
                    {{ $match->bracket->weightClass->golongan_usia->label() }} —
                    {{ $match->bracket->weightClass->name }}
                    · {{ $match->bracket->namaBabak($match->round) }}
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

        <div x-show="tawaranWmp" x-cloak class="rounded-silat border border-silat-emas/40 bg-silat-emas/10 px-4 py-3 text-[13px] text-silat-teks">
            Sudut <span x-text="tawaranWmp === 'red' ? 'merah' : 'biru'" class="font-medium"></span>
            unggul cukup jauh untuk menang WMP — pilih "Akhiri partai" bila ingin menetapkannya.
        </div>

        <div x-show="sudahSelesai" x-cloak class="rounded-silat bg-silat-panel px-4 py-3 text-[13px] text-silat-teks">
            Partai selesai — <span x-text="match.win_reason"></span>.
            <span x-show="match.ratified" class="text-silat-teks-redup">Sudah disahkan dewan juri.</span>
            <span x-show="! match.ratified" class="text-silat-teks-redup">Menunggu pengesahan dewan juri.</span>
        </div>

        {{-- Timer --}}
        <div class="rounded-silat bg-silat-panel p-6 text-center">
            <p class="silat-angka text-[12px] tracking-[.1em] text-silat-teks-redup">
                Babak <span x-text="match.current_round ?? '–'"></span><span class="text-silat-teks-samar">/<span x-text="peraturan.jumlah_babak"></span></span>
            </p>
            <div
                class="silat-angka text-[52px] leading-none font-medium text-silat-teks"
                x-text="tampilWaktu"
                role="timer"
                aria-live="off"
            >00:00</div>

            @resource(rk('partai', ResourceAction::Update))
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <button type="button" x-show="babakUntukDimulai !== null" x-on:click="mulaiBabak()"
                            class="rounded-silat bg-silat-merah px-4 py-2 text-[13px] text-silat-teks">
                        <span x-text="babakAktif?.status === 'belum_mulai' ? 'Mulai ulang babak' : 'Mulai babak berikutnya'"></span>
                    </button>
                    <button type="button" x-show="babakAktif?.status === 'berjalan'" x-on:click="jeda()"
                            class="rounded-silat bg-silat-mati px-4 py-2 text-[13px] text-silat-teks">Jeda</button>
                    <button type="button" x-show="babakAktif?.status === 'jeda'" x-on:click="lanjutkan()"
                            class="rounded-silat bg-silat-biru px-4 py-2 text-[13px] text-silat-teks">Lanjutkan</button>
                    <button type="button" x-show="babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda'" x-on:click="resetBabak()"
                            class="rounded-silat bg-silat-mati px-4 py-2 text-[13px] text-silat-teks-redup">Reset</button>
                    <button type="button" x-show="babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda'" x-on:click="selesaikanBabak()"
                            class="rounded-silat bg-silat-mati px-4 py-2 text-[13px] text-silat-teks">Selesaikan babak</button>
                </div>
            @endresource
        </div>

        {{-- Papan skor --}}
        <div class="grid gap-3 sm:grid-cols-2">
            <x-silat.papan-skor sudut="red" kunci-skor="merah" rata="kiri" :indikator="true" />
            <x-silat.papan-skor sudut="blue" kunci-skor="biru" rata="kanan" :indikator="true" />
        </div>

        {{-- Akhiri partai --}}
        @resource(rk('partai', ResourceAction::Manage))
            <div x-show="! sudahSelesai" x-cloak class="rounded-silat bg-silat-panel p-4">
                <p class="mb-3 text-[13px] text-silat-teks-redup">Akhiri partai</p>
                <div class="flex flex-wrap items-center gap-2" x-data="{ corner: 'red', sebab: 'angka' }">
                    <select x-model="corner" class="rounded-silat border border-silat-garis bg-silat-latar px-3 py-2 text-[13px] text-silat-teks">
                        <option value="red">Sudut merah</option>
                        <option value="blue">Sudut biru</option>
                    </select>
                    <select x-model="sebab" class="rounded-silat border border-silat-garis bg-silat-latar px-3 py-2 text-[13px] text-silat-teks">
                        <option value="angka">Menang angka</option>
                        <option value="teknik">Menang teknik</option>
                        <option value="mutlak">Menang mutlak</option>
                        <option value="wmp">Menang WMP</option>
                        <option value="undur_diri">Menang undur diri</option>
                        <option value="cedera">Cedera</option>
                        <option value="wo">WO</option>
                    </select>
                    <button type="button" x-on:click="akhiri(corner, sebab)"
                            class="rounded-silat bg-silat-merah px-4 py-2 text-[13px] text-silat-teks">Akhiri partai</button>
                </div>
            </div>
        @endresource
    </div>
</x-layouts.silat>
