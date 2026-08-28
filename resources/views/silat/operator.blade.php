@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Operator — '.$match->bracket->weightClass->name">
    {{-- h-dvh, bukan min-h-screen: panel operator dipakai satu layar penuh di
         laptop gelanggang dan tidak pernah digulir. Sebelumnya papan skor
         berhenti di sekitar 60% tinggi layar dan sisanya kosong, padahal
         angkanya justru yang dibaca dari jarak meja. --}}
    {{--
        Panel operator memakai seluruh lebar layar, bukan kolom terpusat
        selebar 896px. Angka skor di sini dibaca dari jarak meja gelanggang,
        dan `max-w-4xl` memaksa kedua papan skor menyusut ke 266px sementara
        sisa layar dibiarkan kosong.

        `overflow-hidden` menggantikan `overflow-y-auto`: layar ini tidak boleh
        digulir sama sekali. Kalau isinya tidak muat, itu cacat tata letak yang
        harus ketahuan, bukan disembunyikan di balik gulir.
    --}}
    <div x-data="partaiPanel(@js($config))" class="flex h-dvh flex-col gap-4 overflow-hidden p-4">
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

            <x-silat.indikator-koneksi />
        </header>

        <p x-show="galat" x-text="galat" class="rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        <div x-show="tawaranWmp" x-cloak class="rounded-silat border border-silat-teguran/50 bg-silat-teguran/10 px-4 py-3 text-[13px] text-silat-teks">
            Sudut <span x-text="tawaranWmp === 'red' ? 'merah' : 'biru'" class="font-medium"></span>
            unggul cukup jauh untuk menang WMP — pilih "Akhiri partai" bila ingin menetapkannya.
        </div>

        <div x-show="sudahSelesai" x-cloak x-data="{ sebabLabel: @js(App\Support\Scoring\AlasanMenang::peta()) }"
             class="rounded-silat bg-silat-panel px-4 py-3 text-[13px] text-silat-teks">
            Partai selesai — <span x-text="sebabLabel[match?.win_reason] ?? match?.win_reason"></span>.
            <span x-show="match.ratified" class="text-silat-teks-redup">Sudah disahkan Dewan Wasit Juri.</span>
            <span x-show="! match.ratified" class="text-silat-teks-redup">Menunggu pengesahan Dewan Wasit Juri.</span>
        </div>

        {{--
            Tiga kolom: sudut merah, kendali, sudut biru.

            Kendali dan timer duduk di KOLOM TENGAH, bukan di baris tersendiri
            di atas papan skor. Dua alasannya:

            Pertama, papan skor jadi mengisi tinggi layar. Angka skor adalah
            yang dibaca dari jarak jauh, dan sebelumnya sepertiga tinggi layar
            habis untuk baris timer.

            Kedua, tidak satu pun kendali boleh terbaca sebagai milik salah satu
            sudut. Tombol yang duduk di dalam atau tepat di bawah blok berwarna
            akan terbaca begitu, sama seperti tombol berwarna merah terbaca
            berhubungan dengan pesilat merah.
        --}}
        <div class="grid min-h-0 flex-1 grid-cols-[1fr_300px_1fr] gap-4">
            <x-silat.papan-skor sudut="red" kunci-skor="merah" rata="kiri" :indikator="true" ukuran-angka="operator" />

            <div class="flex min-h-0 flex-col gap-4">
                @resource(rk('partai', ResourceAction::Update))
                    {{--
                        Tidak ada tombol kendali yang boleh berwarna merah atau
                        biru. Di layar ini kedua warna itu sudah punya arti tetap
                        -- identitas sudut pesilat -- dan tombol "Mulai babak"
                        berwarna merah sudut membuat operator sepersekian detik
                        mengira aksinya berhubungan dengan pesilat merah.

                        Labelnya menyebut nomor babaknya. Sebelumnya berbunyi
                        "Mulai babak berikutnya" bahkan pada partai yang belum
                        pernah dimulai sama sekali -- tidak ada babak sebelumnya
                        untuk dilanjutkan, dan papan masih menulis "Babak -/3".

                        Tinggi 64px mengikuti batas sentuh gelanggang: operator
                        menekannya sambil mengawasi matras, bukan layar.
                    --}}
                    <div class="flex shrink-0 flex-col gap-2">
                        <button type="button" x-show="babakUntukDimulai !== null" x-on:click="mulaiBabak()"
                                class="min-h-[var(--silat-sentuh-min)] rounded-silat bg-silat-aksi px-4 text-[15px] font-medium text-silat-aksi-teks">
                            <span x-text="(babakAktif?.status === 'belum_mulai' ? 'Mulai ulang babak ' : 'Mulai babak ') + babakUntukDimulai"></span>
                        </button>
                        <button type="button" x-show="babakAktif?.status === 'jeda'" x-on:click="lanjutkan()"
                                class="min-h-[var(--silat-sentuh-min)] rounded-silat bg-silat-aksi px-4 text-[15px] font-medium text-silat-aksi-teks">Lanjutkan</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan'" x-on:click="jeda()"
                                class="min-h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali px-4 text-[15px] text-silat-teks">Jeda</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda'" x-on:click="selesaikanBabak()"
                                class="min-h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali px-4 text-[15px] text-silat-teks">Selesaikan babak</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda'" x-on:click="resetBabak()"
                                class="min-h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali px-4 text-[15px] text-silat-teks-redup">Reset</button>
                    </div>
                @endresource

                {{-- Timer mengisi sisa tinggi kolom, jadi ia duduk di tengah optik. --}}
                <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-2">
                    <p class="silat-angka text-[12px] tracking-[.1em] text-silat-teks-redup">
                        Babak <span x-text="match.current_round ?? '–'"></span>/<span x-text="peraturan.jumlah_babak"></span>
                    </p>
                    <div
                        class="silat-angka text-[52px] leading-none font-medium text-silat-teks"
                        x-text="tampilWaktu"
                        role="timer"
                        aria-live="off"
                    >00:00</div>
                </div>
            </div>

            <x-silat.papan-skor sudut="blue" kunci-skor="biru" rata="kanan" :indikator="true" ukuran-angka="operator" />
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
                            class="rounded-silat bg-silat-aksi px-4 py-2 text-[13px] font-medium text-silat-aksi-teks">Akhiri partai</button>
                </div>
            </div>
        @endresource
    </div>
</x-layouts.silat>
