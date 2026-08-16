@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Keberatan — '.$match->bracket->weightClass->name">
    <div x-data="partaiPanel(@js($config))" class="mx-auto flex min-h-screen max-w-4xl flex-col gap-4 p-4">
        <header class="flex items-center justify-between gap-4">
            <div>
                <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-samar">KEBERATAN</p>
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

        {{-- VAR -- Pasal 15 --}}
        <div class="rounded-silat bg-silat-panel p-4">
            <p class="mb-3 text-[13px] font-medium text-silat-teks">Protes VAR</p>

            <div class="mb-4 grid grid-cols-2 gap-3">
                <p class="rounded-silat bg-silat-latar px-3 py-2 text-[13px] text-silat-teks">
                    Sudut Merah — sisa kartu <span class="silat-angka font-medium" x-text="keberatan.kartu.merah"></span>
                </p>
                <p class="rounded-silat bg-silat-latar px-3 py-2 text-[13px] text-silat-teks">
                    Sudut Biru — sisa kartu <span class="silat-angka font-medium" x-text="keberatan.kartu.biru"></span>
                </p>
            </div>

            @resource(rk('var', ResourceAction::Create))
                <form x-data="{ corner: 'red', kejadian: '' }"
                      x-on:submit.prevent="ajukanVar(corner, kejadian).then((ok) => { if (ok) kejadian = '' })"
                      class="mb-4 flex flex-wrap items-center gap-2">
                    <select x-model="corner" class="rounded-silat border border-silat-garis bg-silat-latar px-2 py-1.5 text-[12px] text-silat-teks">
                        <option value="red">Merah</option>
                        <option value="blue">Biru</option>
                    </select>
                    <input type="text" x-model="kejadian" placeholder="Kejadian yang disengketakan" required
                           class="min-w-[220px] flex-1 rounded-silat border border-silat-garis bg-silat-latar px-2 py-1.5 text-[12px] text-silat-teks placeholder:text-silat-teks-samar">
                    <button type="submit" class="rounded-silat bg-silat-emas px-3 py-1.5 text-[12px] text-silat-latar">
                        Ajukan protes
                    </button>
                </form>
            @endresource

            <template x-if="keberatan.var_reviews.length === 0">
                <p class="text-[13px] text-silat-teks-redup">Belum ada protes VAR.</p>
            </template>

            <div class="divide-y divide-silat-garis">
                <template x-for="review in keberatan.var_reviews" :key="review.id">
                    <div class="py-2.5" x-data="{ catatan: '' }">
                        <p class="text-[13px] text-silat-teks">
                            <span x-text="review.corner === 'red' ? 'Merah' : 'Biru'"></span>
                            · Babak <span x-text="review.round"></span>
                            · <span x-text="review.kejadian"></span>
                        </p>

                        <template x-if="review.keputusan">
                            <p class="mt-1 text-[12px]" x-bind:class="review.keputusan === 'sah' ? 'text-emerald-300' : 'text-red-300'">
                                Keputusan: <span x-text="review.keputusan === 'sah' ? 'Sah' : 'Tidak Sah'"></span>
                                <span x-show="review.catatan" x-text="'— ' + review.catatan"></span>
                            </p>
                        </template>

                        @resource(rk('var', ResourceAction::Approve))
                            <div x-show="! review.keputusan" class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-[12px]" x-bind:class="review.lewat_tenggat ? 'text-red-300' : 'text-silat-teks-redup'"
                                      x-text="review.lewat_tenggat ? 'Tenggat 5 menit lewat -- lanjutkan lewat verifikasi juri.' : ('Sisa ' + review.sisa_detik + ' detik')"></span>
                                <input type="text" x-model="catatan" placeholder="Catatan keputusan"
                                       class="w-40 rounded-silat border border-silat-garis bg-silat-latar px-2 py-1.5 text-[12px] text-silat-teks placeholder:text-silat-teks-samar">
                                <button type="button" x-on:click="putuskanVar(review.id, 'sah', catatan)"
                                        class="rounded-silat bg-emerald-500/20 px-3 py-1.5 text-[12px] text-emerald-300">Sah</button>
                                <button type="button" x-on:click="putuskanVar(review.id, 'tidak_sah', catatan)"
                                        class="rounded-silat bg-red-500/20 px-3 py-1.5 text-[12px] text-red-300">Tidak Sah</button>
                            </div>
                        @endresource
                    </div>
                </template>
            </div>
        </div>

        {{-- Protes Manajer -- Pasal 15 ayat 4 --}}
        @resource(rk('protes-manajer', ResourceAction::View))
            <div class="rounded-silat bg-silat-panel p-4">
                <p class="mb-3 text-[13px] font-medium text-silat-teks">Protes Manajer</p>

                <template x-if="keberatan.protes_manajer.length === 0">
                    <p class="text-[13px] text-silat-teks-redup">Belum ada protes manajer untuk partai ini.</p>
                </template>

                <div class="divide-y divide-silat-garis">
                    <template x-for="protes in keberatan.protes_manajer" :key="protes.id">
                        <div class="py-2.5" x-data="{ catatan: '' }">
                            <p class="text-[13px] text-silat-teks">
                                <span x-text="protes.level === 'pertama' ? 'Tingkat pertama (Ketua Pertandingan)' : 'Banding (Delegasi Teknik)'"></span>
                                <span x-show="protes.final" class="ml-1 text-[11px] text-silat-emas">FINAL</span>
                            </p>

                            <template x-if="protes.keputusan">
                                <p class="mt-1 text-[12px]" x-bind:class="protes.keputusan === 'diterima' ? 'text-emerald-300' : 'text-red-300'">
                                    Keputusan: <span x-text="protes.keputusan === 'diterima' ? 'Diterima' : 'Ditolak'"></span>
                                    <span x-show="protes.catatan" x-text="'— ' + protes.catatan"></span>
                                </p>
                            </template>

                            @resource(rk('protes-manajer', ResourceAction::Approve))
                                <div x-show="! protes.keputusan" class="mt-2 flex flex-wrap items-center gap-2">
                                    <input type="text" x-model="catatan" placeholder="Catatan keputusan"
                                           class="w-40 rounded-silat border border-silat-garis bg-silat-latar px-2 py-1.5 text-[12px] text-silat-teks placeholder:text-silat-teks-samar">
                                    <button type="button" x-on:click="putuskanProtesManajer(protes.id, 'diterima', catatan)"
                                            class="rounded-silat bg-emerald-500/20 px-3 py-1.5 text-[12px] text-emerald-300">Terima</button>
                                    <button type="button" x-on:click="putuskanProtesManajer(protes.id, 'ditolak', catatan)"
                                            class="rounded-silat bg-red-500/20 px-3 py-1.5 text-[12px] text-red-300">Tolak</button>
                                </div>
                            @endresource

                            @resource(rk('protes-manajer', ResourceAction::Create))
                                <button type="button" x-show="protes.level === 'pertama' && protes.keputusan && !protes.final"
                                        x-on:click="bandingProtesManajer(protes.id, '')"
                                        class="mt-2 rounded-silat bg-silat-biru px-3 py-1.5 text-[12px] text-silat-teks">
                                    Ajukan banding ke Delegasi Teknik
                                </button>
                            @endresource
                        </div>
                    </template>
                </div>

                @resource(rk('protes-manajer', ResourceAction::Create))
                    <button type="button" x-show="sudahSelesai && keberatan.protes_manajer.length === 0"
                            x-on:click="ajukanProtesManajer('')"
                            class="mt-3 rounded-silat bg-silat-emas px-3 py-1.5 text-[12px] text-silat-latar">
                        Ajukan protes manajer tingkat pertama
                    </button>
                @endresource
            </div>
        @endresource
    </div>
</x-layouts.silat>
