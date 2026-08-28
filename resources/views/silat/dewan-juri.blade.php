@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Dewan Wasit Juri — '.$match->bracket->weightClass->name">
    {{--
        Panel ini dibuka justru saat satu nilai disengketakan, dan yang dibaca
        adalah daftar panjang berisi puluhan baris. `max-w-4xl` memaksa tiap
        baris menyempit sampai waktu, penekan, dan alasan pembatalan berdesakan
        di kolom yang sama.
    --}}
    <div x-data="partaiPanel(@js($config))" class="flex min-h-screen flex-col gap-4 p-4">
        <header class="flex items-center justify-between gap-4">
            <div>
                <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-samar">DEWAN WASIT JURI</p>
                <h1 class="text-[17px] font-medium text-silat-teks">
                    {{ $match->bracket->weightClass->jenis_kelamin->label() }}
                    {{ $match->bracket->weightClass->golongan_usia->label() }} —
                    {{ $match->bracket->weightClass->name }}
                </h1>
            </div>

            <div class="flex items-center gap-2">
                @resource(rk('hasil-partai', ResourceAction::Print))
                    <a href="{{ route('admin.turnamen.partai.berita-acara', [$tournament, $match]) }}" target="_blank"
                       class="rounded-full bg-white/5 px-3 py-1 text-[11px] tracking-wide text-silat-teks hover:bg-white/10">
                        Berita acara (PDF)
                    </a>
                @endresource

                <x-silat.indikator-koneksi />
            </div>
        </header>

        <p x-show="galat" x-text="galat" class="rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-silat.papan-skor sudut="red" kunci-skor="merah" rata="kiri" />
            <x-silat.papan-skor sudut="blue" kunci-skor="biru" rata="kanan" />
        </div>

        {{-- Pengesahan hasil --}}
        <div x-show="sudahSelesai" x-cloak x-data="{ sebabLabel: @js(App\Support\Scoring\AlasanMenang::peta()) }"
             class="rounded-silat bg-silat-panel p-4">
            <p class="text-[13px] text-silat-teks">
                Partai selesai — <span x-text="sebabLabel[match?.win_reason] ?? match?.win_reason"></span>,
                sudut <span x-text="match.winner_registration_id === match.red?.registration_id ? 'merah' : 'biru'"></span> menang.
            </p>

            @resource(rk('hasil-partai', ResourceAction::Approve))
                <button type="button" x-show="! match.ratified" x-on:click="sahkan()"
                        class="mt-3 rounded-silat bg-silat-aksi px-4 py-2 text-[13px] font-medium text-silat-aksi-teks">
                    Sahkan hasil
                </button>
                <p x-show="match.ratified" class="mt-3 text-[13px] text-emerald-300">Sudah disahkan.</p>
            @endresource
        </div>

        <div x-show="! sudahSelesai" x-cloak class="rounded-silat bg-silat-panel p-4 text-[13px] text-silat-teks-redup">
            Partai masih berjalan. Pengesahan hanya bisa dilakukan setelah partai diakhiri operator.
        </div>

        {{-- Riwayat nilai & hukuman -- koreksi lewat baris pembatal, bukan menyunting riwayat --}}
        @resource(rk('hasil-partai', ResourceAction::Update))
            <div class="rounded-silat bg-silat-panel p-4">
                <p class="mb-3 text-[13px] font-medium text-silat-teks">Riwayat nilai &amp; hukuman</p>

                <template x-if="riwayat.length === 0">
                    <p class="text-[13px] text-silat-teks-redup">Belum ada nilai atau hukuman tercatat.</p>
                </template>

                {{--
                    Setelah hasil disahkan, seluruh baris terkunci -- dan
                    alasannya dinyatakan, bukan sekadar tombolnya dimatikan.
                    Tombol mati tanpa penjelasan sama membingungkannya dengan
                    tombol yang gagal diam-diam.
                --}}
                <div x-show="match.ratified" x-cloak
                     class="mb-3 rounded-silat border-l-[3px] border-silat-tepi-kendali bg-silat-latar px-3 py-2">
                    <p class="text-[13px] text-silat-teks">Hasil sudah disahkan, jadi riwayat ini terkunci.</p>
                    <p class="mt-0.5 text-[12px] text-silat-teks-redup">Koreksi sesudah pengesahan hanya lewat protes manajer — Pasal 15 ayat 4.</p>
                </div>

                <div class="divide-y divide-silat-garis">
                    <template x-for="baris in riwayat" :key="baris.tipe + '-' + baris.id">
                        <div class="flex items-center justify-between gap-3 py-2.5" x-data="{ alasan: '' }">
                            {{--
                                Waktu dan penekan bukan hiasan: panel ini dibuka justru
                                ketika satu nilai disengketakan, dan tanpa keduanya belasan
                                baris berbunyi persis sama ("Biru · Babak 1 · Pukulan (1)").
                                Dewan juri jadi tidak punya cara menunjuk baris mana yang
                                keliru. Cap waktunya sudah lama ikut di muatan riwayat dan
                                sudah dicetak di berita acara PDF — hanya belum pernah
                                ditampilkan di layar yang paling membutuhkannya.
                            --}}
                            <div class="min-w-0">
                                {{--
                                    Sudut ditandai warna DAN kata. Belasan baris
                                    di sini berbunyi hampir sama, dan mata
                                    memisahkan dua kolom warna jauh lebih cepat
                                    daripada membaca kata pertama tiap baris.
                                --}}
                                <p class="flex items-center gap-2 text-[13px] text-silat-teks">
                                    <span class="size-2.5 shrink-0 rounded-full"
                                          x-bind:class="baris.corner === 'red' ? 'bg-silat-merah' : 'bg-silat-biru'"></span>
                                    <span x-text="baris.corner === 'red' ? 'Merah' : 'Biru'"></span>
                                    · Babak <span x-text="baris.round"></span>
                                    · <span x-text="baris.label"></span>
                                </p>
                                <p class="mt-0.5 text-[11px] text-silat-teks-redup">
                                    <span class="silat-angka" x-text="new Date(baris.waktu).toLocaleTimeString('id-ID', { hour12: false })"></span>
                                    <template x-if="baris.oleh">
                                        <span>· <span x-text="baris.oleh"></span></span>
                                    </template>
                                </p>
                            </div>

                            {{--
                                Membatalkan nilai mengubah hasil resmi, jadi
                                sasarannya mengikuti batas sentuh gelanggang dan
                                alasannya wajib -- keduanya tercetak di berita
                                acara. Setelah pengesahan, tombolnya benar-benar
                                mati; server menolak permintaannya juga, jadi UI
                                di sini tidak berdiri sendiri sebagai penjaga.
                            --}}
                            <div class="flex shrink-0 items-center gap-2">
                                <input
                                    type="text" x-model="alasan" placeholder="Alasan pembatalan"
                                    x-bind:disabled="match.ratified"
                                    class="min-h-[var(--silat-sentuh-min)] w-56 rounded-silat border border-silat-tepi-kendali bg-silat-latar px-3 text-[13px] text-silat-teks placeholder:text-silat-teks-redup disabled:opacity-45"
                                >
                                <button
                                    type="button"
                                    x-on:click="baris.tipe === 'nilai' ? batalkanNilai(baris.id, alasan) : batalkanHukuman(baris.id, alasan)"
                                    x-bind:disabled="! alasan || match.ratified"
                                    class="min-h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali px-4 text-[14px] text-silat-teks disabled:opacity-45"
                                >
                                    Batalkan
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        @endresource
    </div>
</x-layouts.silat>
