@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Dewan Juri — '.$match->bracket->weightClass->name">
    <div x-data="partaiPanel(@js($config))" class="mx-auto flex min-h-screen max-w-4xl flex-col gap-4 p-4">
        <header class="flex items-center justify-between gap-4">
            <div>
                <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-samar">DEWAN JURI</p>
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

                <span
                    class="rounded-full px-3 py-1 text-[11px] tracking-wide"
                    x-bind:class="$store.koneksi.tersambung ? 'bg-emerald-500/15 text-emerald-300' : 'bg-red-500/15 text-red-300'"
                    x-text="$store.koneksi.tersambung ? 'Tersambung' : 'Terputus'"
                ></span>
            </div>
        </header>

        <p x-show="galat" x-text="galat" class="rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-silat.papan-skor sudut="red" kunci-skor="merah" rata="kiri" />
            <x-silat.papan-skor sudut="blue" kunci-skor="biru" rata="kanan" />
        </div>

        {{-- Pengesahan hasil --}}
        <div x-show="sudahSelesai" x-cloak class="rounded-silat bg-silat-panel p-4">
            <p class="text-[13px] text-silat-teks">
                Partai selesai — <span x-text="match.win_reason"></span>,
                sudut <span x-text="match.winner_registration_id === match.red?.registration_id ? 'merah' : 'biru'"></span> menang.
            </p>

            @resource(rk('hasil-partai', ResourceAction::Approve))
                <button type="button" x-show="! match.ratified" x-on:click="sahkan()"
                        class="mt-3 rounded-silat bg-silat-biru px-4 py-2 text-[13px] text-silat-teks">
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

                <div class="divide-y divide-silat-garis">
                    <template x-for="baris in riwayat" :key="baris.tipe + '-' + baris.id">
                        <div class="flex items-center justify-between gap-3 py-2.5" x-data="{ alasan: '' }">
                            <div class="min-w-0">
                                <p class="text-[13px] text-silat-teks">
                                    <span x-text="baris.corner === 'red' ? 'Merah' : 'Biru'"></span>
                                    · Babak <span x-text="baris.round"></span>
                                    · <span x-text="baris.label"></span>
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <input
                                    type="text" x-model="alasan" placeholder="Alasan pembatalan"
                                    class="w-40 rounded-silat border border-silat-garis bg-silat-latar px-2 py-1.5 text-[12px] text-silat-teks placeholder:text-silat-teks-samar"
                                >
                                <button
                                    type="button"
                                    x-on:click="baris.tipe === 'nilai' ? batalkanNilai(baris.id, alasan) : batalkanHukuman(baris.id, alasan)"
                                    x-bind:disabled="! alasan"
                                    class="rounded-silat bg-silat-mati px-3 py-1.5 text-[12px] text-silat-teks disabled:opacity-40"
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
