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

            <x-silat.indikator-koneksi />
        </header>

        <p x-show="galat" x-text="galat" class="rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        {{--
            Papan skor ikut di sini karena protes tidak pernah soal kejadian yang
            berdiri sendiri — ia soal kejadian pada kedudukan tertentu. Yang
            memutus perlu melihat angka dan hukuman berjalan tanpa berpindah
            layar; sebelumnya panel ini hanya berisi formulir, dan konteksnya
            harus dicari di panel lain sementara tenggat VAR terus berjalan.
        --}}
        <div class="grid gap-3 sm:grid-cols-2">
            <x-silat.papan-skor sudut="red" kunci-skor="merah" rata="kiri" />
            <x-silat.papan-skor sudut="blue" kunci-skor="biru" rata="kanan" />
        </div>

        {{-- VAR -- Pasal 15 --}}
        <div class="rounded-silat bg-silat-panel p-4">
            <p class="mb-3 text-[13px] font-medium text-silat-teks">Protes VAR</p>

            {{--
                Sisa kartu protes digambar sebagai kartu, bukan disebut sebagai
                angka di dalam kalimat. Pelatih memegang kartu fisik di
                gelanggang (Pasal 15.2.a: dua kartu untuk tiga babak), dan yang
                ditanyakan saat protes diajukan selalu "masih ada berapa" --
                pertanyaan yang dijawab bentuk lebih cepat daripada angka.
            --}}
            <div class="mb-4 grid grid-cols-2 gap-3">
                @foreach (['merah' => 'silat-merah', 'biru' => 'silat-biru'] as $sisi => $warna)
                    <div class="flex items-center justify-between gap-3 rounded-silat border-l-[3px] border-{{ $warna }} bg-silat-latar px-3 py-2">
                        <span class="text-[13px] text-silat-teks">Sisa kartu sudut {{ $sisi }}</span>
                        <span class="flex gap-1.5" x-bind:aria-label="'Sisa ' + keberatan.kartu.{{ $sisi }} + ' kartu'">
                            <template x-for="n in {{ config('scoring.var.kartu_protes.tanding', 2) }}" :key="n">
                                <span class="h-7 w-5 rounded-[3px]"
                                      x-bind:class="n <= keberatan.kartu.{{ $sisi }}
                                          ? 'bg-silat-aksi'
                                          : 'border border-silat-tepi-petak'"></span>
                            </template>
                        </span>
                    </div>
                @endforeach
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
                    <button type="submit" class="rounded-silat bg-silat-aksi px-3 py-1.5 text-[12px] font-medium text-silat-aksi-teks">
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
                            {{--
                                Tenggat adalah hal paling menekan di layar ini,
                                jadi ia digambar paling besar. Sebelumnya ia teks
                                12px sebaris dengan isian catatan -- seukuran
                                keterangan, padahal lewat lima menit keputusannya
                                berpindah tangan ke verifikasi juri yang dipimpin
                                Ketua Pertandingan (Pasal 15).

                                Tombolnya juga naik ke 64px. Pemutus protes
                                menekannya sambil dilihat pelatih dan penonton;
                                sasaran 30px bukan ukuran untuk keputusan yang
                                tidak bisa ditarik kembali.
                            --}}
                            <div x-show="! review.keputusan" class="mt-3 flex flex-col gap-3 rounded-silat border p-3"
                                 x-bind:class="review.lewat_tenggat ? 'border-silat-peringatan' : 'border-silat-teguran'">

                                <div class="flex items-baseline justify-between gap-4">
                                    <span class="text-[12px] tracking-[.08em] text-silat-teks-redup uppercase">
                                        <span x-show="! review.lewat_tenggat">Sisa waktu memutus</span>
                                        <span x-show="review.lewat_tenggat" x-cloak>Tenggat lewat</span>
                                    </span>
                                    <span class="silat-angka text-[36px] leading-none font-medium"
                                          x-bind:class="review.lewat_tenggat ? 'text-silat-peringatan' : 'text-silat-teguran'"
                                          x-text="review.lewat_tenggat
                                              ? '00:00'
                                              : String(Math.floor(review.sisa_detik / 60)).padStart(2, '0') + ':' + String(review.sisa_detik % 60).padStart(2, '0')"
                                          aria-live="off"></span>
                                </div>

                                <p x-show="review.lewat_tenggat" x-cloak class="text-[13px] leading-relaxed text-silat-teks">
                                    Lewat lima menit, keputusannya berpindah ke verifikasi juri yang dipimpin Ketua Pertandingan.
                                </p>

                                <input type="text" x-model="catatan" placeholder="Catatan keputusan"
                                       class="min-h-[var(--silat-sentuh-min)] w-full rounded-silat border border-silat-tepi-kendali bg-silat-latar px-3 text-[14px] text-silat-teks placeholder:text-silat-teks-redup">

                                <div class="flex gap-2">
                                    <button type="button" x-on:click="putuskanVar(review.id, 'sah', catatan)"
                                            class="min-h-[var(--silat-sentuh-min)] flex-1 rounded-silat bg-silat-aksi text-[16px] font-medium text-silat-aksi-teks">Sah</button>
                                    <button type="button" x-on:click="putuskanVar(review.id, 'tidak_sah', catatan)"
                                            class="min-h-[var(--silat-sentuh-min)] flex-1 rounded-silat border border-silat-tepi-kendali text-[16px] font-medium text-silat-teks">Tidak sah</button>
                                </div>

                                <p class="text-[12px] leading-relaxed text-silat-teks-redup">
                                    <strong class="font-medium text-silat-teks">Sah</strong> berarti nilai tetap berdiri.
                                    <strong class="font-medium text-silat-teks">Tidak sah</strong> membatalkan nilai itu, dan skor berubah di semua layar.
                                </p>
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
                                <span x-show="protes.final" class="ml-1 text-[11px] text-silat-teks">FINAL</span>
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
                            class="mt-3 rounded-silat bg-silat-aksi px-3 py-1.5 text-[12px] font-medium text-silat-aksi-teks">
                        Ajukan protes manajer tingkat pertama
                    </button>
                @endresource
            </div>
        @endresource
    </div>
</x-layouts.silat>
