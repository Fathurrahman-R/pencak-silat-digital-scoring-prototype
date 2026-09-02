@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Ketua Pertandingan — '.$tournament->name">
    {{--
        Panel Ketua Pertandingan -- Pasal 13.4.

        Semua panel gelanggang lain menjawab "apa yang terjadi di partai ini".
        Panel ini menjawab pertanyaan yang berbeda: "apa yang menghambat
        kelancaran gelanggang sekarang". Karena itu ia dibuka di layar lebar,
        bukan HP: yang dilihatnya seluruh gelanggang sekaligus.

        Kolom kanan bukan pelengkap. "Butuh keputusanmu" adalah alasan utama
        panel ini ada -- perkara yang menunggu, masing-masing dengan tenggat
        sendiri, yang tenggatnya lewat naik ke atas. Yang sudah lewat batas
        waktulah yang benar-benar menghentikan gelanggang, bukan yang baru
        masuk.
    --}}
    <div x-data="panelKetua(@js($config))" class="flex min-h-dvh flex-col gap-4 p-4 lg:p-6">

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[11px] tracking-[.28em] text-silat-teks-redup uppercase">Ketua Pertandingan</p>
                <h1 class="mt-0.5 truncate text-[22px] leading-tight font-medium text-silat-teks">{{ $tournament->name }}</h1>
            </div>
            <div class="flex items-center gap-4">
                <span class="silat-angka text-[13px] text-silat-teks-redup"
                      x-text="gelanggang.length + ' gelanggang'"></span>
                <x-silat.indikator-koneksi />
            </div>
        </header>

        <p x-show="galat" x-text="galat" x-cloak
           class="shrink-0 rounded-silat bg-silat-garis px-3 py-1.5 text-center text-[13px] font-medium text-silat-teks"></p>
        <p x-show="pesan && ! galat" x-text="pesan" x-cloak
           class="shrink-0 rounded-silat bg-silat-panel px-3 py-1.5 text-center text-[13px] text-silat-teks-redup"></p>

        <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[1fr_380px]">

            {{-- ============ GELANGGANG ============ --}}
            <div class="flex flex-col gap-3">
                <template x-if="! memuat && gelanggang.length === 0">
                    <div class="border border-dashed border-silat-tepi-kendali px-5 py-8">
                        <p class="text-[16px] font-medium text-silat-teks">Belum ada gelanggang</p>
                        <p class="mt-1 max-w-[64ch] text-[14px] leading-relaxed text-silat-teks-redup">
                            Gelanggang muncul di sini setelah panitia menetapkannya untuk kejuaraan ini.
                        </p>
                    </div>
                </template>

                <template x-for="papan in gelanggang" :key="papan.arena.id">
                    <div class="rounded-silat bg-silat-panel p-4">
                        <div class="flex items-baseline justify-between gap-4 border-b border-silat-garis pb-2">
                            <span class="text-[16px] font-semibold text-silat-teks" x-text="papan.arena.name"></span>
                            <span class="silat-angka text-[12px] text-silat-teks-redup"
                                  x-text="papan.tanding
                                      ? 'Tanding · Partai ' + papan.tanding.id + ' · ' + papan.tanding.kelas
                                      : (papan.jurus ? 'Jurus · ' + papan.jurus.nomor : 'Tidak ada yang berjalan')"></span>
                        </div>

                        {{-- TANDING --}}
                        <template x-if="papan.tanding">
                            <div class="mt-3 flex flex-col gap-3">
                                <div class="flex items-center gap-4">
                                    {{-- Bidang sudut penuh: identitas sudut harus terbaca sama
                                         di mana pun, termasuk di ringkasan sekecil ini. --}}
                                    <div class="flex min-w-0 flex-1 items-center gap-3 rounded-silat bg-silat-merah-dalam px-3 py-2">
                                        <span class="min-w-0 flex-1 truncate text-[15px] text-silat-teks" x-text="papan.tanding.merah.nama"></span>
                                        <span class="silat-angka text-[26px] leading-none font-semibold text-silat-teks tabular-nums" x-text="papan.tanding.merah.skor"></span>
                                    </div>

                                    <div class="shrink-0 text-center">
                                        <p class="silat-angka text-[24px] leading-none font-medium text-silat-teks" x-text="waktu(papan.tanding.sisa_ms)"></p>
                                        <p class="silat-angka text-[11px] text-silat-teks-redup"
                                           x-text="'BABAK ' + papan.tanding.babak + '/' + papan.tanding.jumlah_babak"></p>
                                    </div>

                                    <div class="flex min-w-0 flex-1 items-center gap-3 rounded-silat bg-silat-biru-dalam px-3 py-2">
                                        <span class="silat-angka text-[26px] leading-none font-semibold text-silat-teks tabular-nums" x-text="papan.tanding.biru.skor"></span>
                                        <span class="min-w-0 flex-1 truncate text-right text-[15px] text-silat-teks" x-text="papan.tanding.biru.nama"></span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-[12px] text-silat-teks-redup">
                                    <span x-show="papan.tanding.aparat.wasit">Wasit <span class="text-silat-teks" x-text="papan.tanding.aparat.wasit"></span></span>
                                    <span x-show="papan.tanding.aparat.juri">Juri <span class="text-silat-teks" x-text="papan.tanding.aparat.juri"></span></span>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    @resource(rk('verifikasi-juri', ResourceAction::Create))
                                        <button type="button" x-on:click="mintaVerifikasi(papan)"
                                                x-show="! papan.tanding.verifikasi"
                                                class="min-h-[44px] rounded-silat bg-silat-aksi px-4 text-[14px] font-semibold text-silat-aksi-teks">
                                            Minta verifikasi juri
                                        </button>
                                    @endresource

                                    {{-- Verifikasi yang sudah berjalan menggantikan tombolnya:
                                         yang dibutuhkan sekarang bukan membuka satu lagi,
                                         melainkan melihat jawabannya masuk. --}}
                                    <a x-show="papan.tanding.verifikasi" x-cloak
                                       x-bind:href="alamat(cfg.partaiWasit, papan.tanding.id)"
                                       class="flex min-h-[44px] items-center gap-3 rounded-silat border border-silat-tepi-kendali px-4 text-[14px] text-silat-teks">
                                        <span x-text="'Verifikasi berjalan · ' + papan.tanding.verifikasi.terjawab + '/' + papan.tanding.verifikasi.jumlah_juri"></span>
                                        <span class="text-silat-teks-redup">Lihat jawaban juri</span>
                                    </a>

                                    @resource(rk('partai', ResourceAction::Update))
                                        <button type="button" x-on:click="hentikan(papan)"
                                                x-show="papan.tanding.status_babak === 'berjalan'"
                                                class="min-h-[44px] rounded-silat border border-silat-tepi-kendali px-4 text-[14px] text-silat-teks">
                                            Hentikan pertandingan
                                        </button>
                                    @endresource
                                </div>
                            </div>
                        </template>

                        {{-- JURUS --}}
                        <template x-if="papan.jurus && ! papan.tanding">
                            <div class="mt-3 flex flex-col gap-3">
                                <div class="flex items-baseline justify-between gap-4">
                                    <span class="min-w-0 truncate text-[17px] font-medium text-silat-teks" x-text="papan.jurus.pesilat"></span>
                                    <span class="silat-angka text-[24px] leading-none font-medium text-silat-teks"
                                          x-text="waktu(papan.jurus.durasi_ms)"></span>
                                </div>

                                {{--
                                    Waktu penampilan Jurus adalah tanggung jawab Ketua
                                    Pertandingan — Pasal 13.4.d.9. Karena itu acuan dan
                                    toleransinya ditulis di sini, bukan cuma jam berjalannya:
                                    yang harus diputuskan bukan "sudah berapa lama", tapi
                                    "sudah lewat toleransi atau belum".
                                --}}
                                <p class="silat-angka text-[12px] text-silat-teks-redup"
                                   x-text="'Waktu acuan ' + waktu(papan.jurus.acuan_ms) + ' · toleransi ±' + (papan.jurus.toleransi_ms / 1000) + ' detik'"></p>
                                <p class="text-[11px] leading-relaxed text-silat-teks-redup">
                                    Waktu penampilan Jurus adalah tanggung jawab Ketua Pertandingan — Pasal 13.4.d.9.
                                </p>
                            </div>
                        </template>

                        <template x-if="! papan.tanding && ! papan.jurus">
                            <p class="mt-3 text-[14px] text-silat-teks-redup">
                                Partai berikutnya tampil di sini begitu operator memulainya.
                            </p>
                        </template>
                    </div>
                </template>
            </div>

            {{-- ============ BUTUH KEPUTUSANMU ============ --}}
            <div class="flex flex-col gap-3">
                <div class="flex items-baseline justify-between border-b-2 border-silat-teks pb-2">
                    <span class="text-[11px] tracking-[.28em] text-silat-teks uppercase">Butuh keputusanmu</span>
                    <span class="silat-angka text-[13px] font-medium text-silat-teks tabular-nums" x-text="antrean.length"></span>
                </div>

                <template x-if="! memuat && antrean.length === 0">
                    <div class="border border-dashed border-silat-tepi-kendali px-4 py-6">
                        <p class="text-[15px] font-medium text-silat-teks">Tidak ada yang menunggu</p>
                        <p class="mt-1 text-[13px] leading-relaxed text-silat-teks-redup">
                            Protes manajer, protes VAR yang lewat tenggat, dan verifikasi juri yang berjalan
                            muncul di sini beserta batas waktunya.
                        </p>
                    </div>
                </template>

                <template x-for="perkara in antrean" :key="perkara.jenis + '-' + perkara.partai_id">
                    <div class="rounded-silat border-l-[3px] bg-silat-panel px-4 py-3"
                         x-bind:class="perkara.lewat ? 'border-silat-teguran' : 'border-silat-tepi-kendali'">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-[15px] font-semibold text-silat-teks" x-text="perkara.judul"></span>
                            <span class="silat-angka shrink-0 text-[12px]"
                                  x-bind:class="perkara.lewat ? 'text-silat-teguran' : 'text-silat-teks-redup'"
                                  x-text="perkara.jumlah ?? sisaTenggat(perkara.tenggat_at) ?? ''"></span>
                        </div>

                        <p class="mt-1 text-[13px] leading-relaxed text-silat-teks-redup" x-text="perkara.keterangan"></p>

                        <a x-bind:href="perkara.jenis === 'verifikasi'
                               ? alamat(cfg.partaiWasit, perkara.partai_id)
                               : alamat(cfg.partaiKeberatan, perkara.partai_id)"
                           class="mt-2 inline-flex min-h-[44px] items-center border-b border-silat-tepi-kendali text-[14px] font-medium text-silat-teks"
                           x-text="perkara.jenis === 'verifikasi' ? 'Lihat jawaban juri' : 'Buka perkara'"></a>
                    </div>
                </template>

                {{--
                    Tugas naskah yang BELUM punya padanan di sistem, ditulis
                    apa adanya alih-alih disembunyikan. Ketua Pertandingan yang
                    membaca panel ini berhak tahu apa yang tidak bisa
                    dilakukannya dari sini, supaya ia tidak menunggu tombol yang
                    tidak akan pernah muncul.
                --}}
                <div class="mt-auto border-t border-silat-garis pt-3">
                    <p class="text-[11px] tracking-[.1em] text-silat-teks-redup uppercase">Belum ada di sistem</p>
                    <ul class="mt-1.5 flex flex-col gap-1 text-[13px] leading-relaxed text-silat-teks-redup">
                        <li>Isyarat keluar garis pada penampilan Jurus — Pasal 13.4.d.7</li>
                        <li>Teruskan masalah ke Delegasi Teknik — Pasal 13.4.d.6</li>
                        <li>Keluarkan pendamping pesilat — Pasal 13.4.d.5; perintahnya dijalankan Dewan Wasit Juri, bukan langsung dari sini</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-layouts.silat>
