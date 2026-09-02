{{--
    Layar Wasit meminta verifikasi juri, dan melihat hasilnya -- Pasal 13.

    Sama seperti layar juri, ini MENGGANTIKAN isi panel wasit, tidak menumpang
    di atasnya. Panel wasit dirancang untuk 844x390 dan sudah penuh oleh tangga
    hukuman; menambahkan bagian verifikasi ke bawahnya akan memaksa gulir di
    layar yang dipegang sambil berdiri di matras.

    Dipakai dua kali dengan isi berbeda:

    - `x-data="{ menyusun: true }"` — wasit sedang merangkai pertanyaan
    - verifikasi berjalan — menunggu jawaban, lalu menyatakan akibat

    Tiga hal yang wajib ada menurut brief §7: jenis pertanyaan, kejadian yang
    boleh dilewati, dan peringatan bahwa panel juri berhenti menerima nilai.
--}}

@props(['tournament', 'match'])

<div class="flex h-full min-h-0 flex-col gap-2">

    {{-- ============================================================
         MENYUSUN PERTANYAAN
         ============================================================ --}}
    <template x-if="! verifikasiBerjalan">
        <div class="flex min-h-0 flex-1 flex-col gap-2.5"
             x-data="{
                 jenis: 'jatuhan',
                 tingkat: 'sedang',
                 kejadian: null,
                 get siap() { return this.jenis === 'jatuhan' || this.tingkat !== null },
                 kirim() {
                     const rujukan = this.kejadian
                         ? (this.kejadian.tipe === 'nilai'
                             ? { score_event_id: this.kejadian.id }
                             : { penalty_id: this.kejadian.id })
                         : {};

                     return mintaVerifikasi(this.jenis, this.jenis === 'pelanggaran' ? this.tingkat : null, rujukan);
                 },
             }">

            <p class="shrink-0 text-[19px] leading-tight font-semibold tracking-[-0.02em] text-silat-teks">
                Apa yang kamu tanyakan ke juri?
            </p>

            {{-- Jenis pertanyaan. Yang terpilih ditandai tepi tebal DAN bidang
                 yang naik, bukan warna: tidak ada warna yang bebas arti di
                 panel gelanggang. --}}
            <div class="grid shrink-0 grid-cols-2 gap-2.5">
                @foreach ([
                    ['jatuhan', 'Jatuhan', 'Sah atau tidak, dan milik sudut mana'],
                    ['pelanggaran', 'Pelanggaran', 'Terjadi atau tidak, dan siapa pelakunya'],
                ] as [$nilai, $judul, $pertanyaan])
                    <button type="button" x-on:click="jenis = '{{ $nilai }}'"
                            x-bind:class="jenis === '{{ $nilai }}'
                                ? 'border-2 border-silat-teks bg-silat-garis text-silat-teks'
                                : 'border-[1.5px] border-silat-tepi-petak text-silat-teks-kedua'"
                            class="flex h-[var(--silat-sentuh-min)] flex-col items-start justify-center gap-[3px] rounded-silat px-3.5 text-left">
                        <span class="text-[16px] leading-tight font-semibold">{{ $judul }}</span>
                        <span class="text-[12px] leading-tight text-silat-teks-redup">{{ $pertanyaan }}</span>
                    </button>
                @endforeach
            </div>

            {{--
                Tingkat sanksi dipilih SEKARANG, bukan setelah juri menjawab.
                Wasit sudah tahu pelanggaran apa yang dilihatnya; yang ia
                ragukan cuma sudutnya. Menetapkannya di sini membuat akibat
                verifikasi bisa dinyatakan penuh sebelum diterapkan.
            --}}
            <div x-show="jenis === 'pelanggaran'" x-cloak class="flex shrink-0 items-center gap-2.5">
                <span class="silat-angka shrink-0 text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">Sanksi</span>
                <div class="flex flex-1 gap-2">
                    @foreach ([['ringan', 'Pembinaan'], ['sedang', 'Teguran'], ['berat', 'Peringatan']] as [$kirim, $resmi])
                        <button type="button" x-on:click="tingkat = '{{ $kirim }}'"
                                x-bind:class="tingkat === '{{ $kirim }}'
                                    ? 'bg-silat-aksi text-silat-aksi-teks'
                                    : 'border border-silat-tepi-petak text-silat-teks-kedua'"
                                class="h-10 flex-1 rounded-silat text-[13px] font-medium">{{ $resmi }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Kejadian yang dirujuk. Boleh dilewati: sebagian pertanyaan
                 memang tentang apa yang baru saja terjadi di depan mata. --}}
            <div class="flex shrink-0 items-center gap-2.5">
                <span class="silat-angka shrink-0 text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">Kejadian</span>
                <select x-on:change="kejadian = $event.target.value === '' ? null : riwayat.slice(0, 8)[Number($event.target.value)]"
                        aria-label="Kejadian yang dirujuk"
                        class="h-10 flex-1 rounded-silat border border-silat-tepi-petak bg-silat-panel px-2.5 text-[13.5px] text-silat-teks">
                    <option value="">Tidak merujuk kejadian tertentu</option>
                    <template x-for="(baris, i) in riwayat.slice(0, 8)" :key="baris.tipe + baris.id">
                        <option x-bind:value="i"
                                x-text="new Date(baris.waktu).toLocaleTimeString('id-ID') + ' · ' + baris.label"></option>
                    </template>
                </select>
            </div>

            {{--
                Peringatannya berdiri sendiri di atas tombol kirim, bukan
                diselipkan sebagai keterangan kecil. Menghentikan panel juri
                adalah akibat yang harus dibaca sebelum menekan.
            --}}
            <div class="flex shrink-0 items-start gap-2.5 rounded-silat bg-silat-panel px-3.5 py-3">
                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-silat-teguran"></span>
                <p class="text-[13px] leading-[1.55] text-silat-teks-kedua">
                    Begitu dikirim, <strong class="font-semibold">panel <span x-text="peraturan.jumlah_juri"></span> juri berhenti menerima nilai</strong>
                    dan berganti jadi layar jawaban. Nilai yang belum sempat mereka tekan untuk kejadian ini akan hilang.
                </p>
            </div>

            <div class="mt-auto flex shrink-0 gap-2">
                <button type="button" x-on:click="$dispatch('tutup-verifikasi')"
                        class="h-[var(--silat-sentuh-min)] rounded-silat border-[1.5px] border-silat-tepi-petak px-[18px] text-[15px] font-medium text-silat-teks-kedua">Batal</button>
                <button type="button" x-on:click="kirim()" x-bind:disabled="! siap"
                        class="h-[var(--silat-sentuh-min)] flex-1 rounded-silat bg-silat-aksi text-[16px] font-semibold text-silat-aksi-teks disabled:opacity-45">
                    Kirim ke <span x-text="peraturan.jumlah_juri"></span> juri
                </button>
            </div>
        </div>
    </template>

    {{-- ============================================================
         VERIFIKASI BERJALAN — menunggu, lalu menyatakan akibat
         ============================================================ --}}
    <template x-if="verifikasiBerjalan">
        <div class="flex min-h-0 flex-1 flex-col gap-2">
            <div class="flex shrink-0 items-baseline justify-between gap-4">
                <p class="text-[15px] font-semibold text-silat-teks" x-text="verifikasi?.pertanyaan"></p>
                <p class="silat-angka text-[11px] text-silat-teks-redup"
                   x-text="'Babak ' + (verifikasi?.round ?? '–') + ' · pertandingan dihentikan'"></p>
            </div>

            <div class="grid min-h-0 flex-1 grid-cols-[1fr_1fr] gap-3">

                {{-- Jawaban per juri berikut cap waktunya --}}
                <div class="flex min-h-0 flex-col">
                    <p class="shrink-0 border-b border-silat-garis pb-1 text-[11px] tracking-[.1em] text-silat-teks-redup uppercase">
                        Jawaban juri
                    </p>

                    <template x-for="j in (verifikasi?.jawaban ?? [])" :key="j.judge_user_id">
                        <div class="flex items-baseline gap-3 border-b border-silat-garis py-1.5">
                            <span class="w-[52px] shrink-0 text-[13px] text-silat-teks-redup" x-text="j.sebutan"></span>
                            <span class="min-w-0 flex-1 truncate text-[15px] font-medium text-silat-teks"
                                  x-text="j.jawaban_label ?? 'Sudah menjawab'"></span>
                            <span class="silat-angka shrink-0 text-[12px] text-silat-teks-redup"
                                  x-text="new Date(j.server_ts).toLocaleTimeString('id-ID')"></span>
                        </div>
                    </template>

                    <template x-for="m in (verifikasi?.menunggu ?? [])" :key="m.judge_user_id">
                        <div class="flex items-baseline gap-3 border-b border-silat-garis py-1.5">
                            <span class="w-[52px] shrink-0 text-[13px] text-silat-teks-redup" x-text="m.sebutan"></span>
                            <span class="min-w-0 flex-1 text-[15px] text-silat-teks-redup">Menunggu jawaban</span>
                            <span class="silat-angka shrink-0 text-[12px] text-silat-teks-redup">—</span>
                        </div>
                    </template>

                    <p class="mt-1.5 shrink-0 text-[11px] leading-relaxed text-silat-teks-redup">
                        Jawaban tiap juri tidak terlihat juri lain sampai ketiganya selesai — supaya juri
                        yang belum menjawab tidak ikut arus.
                    </p>
                </div>

                {{-- Hitungan dan akibat --}}
                <div class="flex min-h-0 flex-col gap-2">
                    <p class="shrink-0 border-b border-silat-garis pb-1 text-[11px] tracking-[.1em] text-silat-teks-redup uppercase">
                        Hasil sementara
                    </p>

                    <div class="flex shrink-0 gap-2">
                        @foreach ([
                            ['red', 'Merah', 'bg-silat-merah-dalam'],
                            ['tidak_ada', 'Tidak ada', 'border border-silat-tepi-petak'],
                            ['blue', 'Biru', 'bg-silat-biru-dalam'],
                        ] as [$kunci, $judul, $gaya])
                            <div class="flex flex-1 flex-col items-center justify-center gap-0.5 rounded-silat {{ $gaya }} py-2">
                                <span class="text-[11px] tracking-[.1em] text-silat-teks uppercase">{{ $judul }}</span>
                                <span class="silat-angka text-[26px] leading-none font-semibold text-silat-teks tabular-nums"
                                      x-text="verifikasi?.hitungan?.['{{ $kunci }}'] ?? 0"></span>
                            </div>
                        @endforeach
                    </div>

                    {{--
                        Akibatnya dinyatakan SEBELUM tombolnya ditekan. Yang
                        menekan "Terapkan" harus tahu persis apa yang akan
                        terbit -- kalimat ini datang dari satu tempat yang sama
                        dengan yang nanti tercatat di riwayat, jadi keduanya
                        tidak bisa berbeda.
                    --}}
                    <div x-show="verifikasi?.hasil" x-cloak
                         class="shrink-0 rounded-silat border-l-[3px] border-silat-teks bg-silat-panel px-3 py-2">
                        <p class="text-[11px] tracking-[.1em] text-silat-teks-redup uppercase">Yang akan terjadi</p>
                        <p class="mt-0.5 text-[15px] leading-snug font-medium text-silat-teks" x-text="verifikasi?.akibat"></p>
                        <p class="mt-1 text-[11px] leading-relaxed text-silat-teks-redup">
                            Hasil verifikasi masuk riwayat Dewan Wasit Juri beserta jawaban tiap juri,
                            dan bisa diprotes pelatih lewat Kartu Protes.
                        </p>
                    </div>

                    <p x-show="! verifikasi?.hasil" x-cloak
                       class="shrink-0 text-[13px] leading-relaxed text-silat-teks-redup">
                        Menunggu <span x-text="verifikasi?.ambang"></span> jawaban yang sama.
                        Hasil terbit begitu ambang tercapai — jawaban juri yang belum masuk tidak lagi mengubahnya.
                    </p>

                    <div class="mt-auto flex shrink-0 gap-2">
                        {{--
                            Keduanya TIDAK men-dispatch 'tutup-verifikasi' dari
                            sini. Aksinya menyegarkan state sebelum promise-nya
                            selesai, `verifikasiBerjalan` jadi false, dan
                            <template x-if> membongkar tombol ini dari DOM --
                            sehingga event yang dikirim setelahnya menggelembung
                            dari elemen yang sudah lepas dan tidak sampai ke
                            mana pun. Penutupannya diurus x-effect di panel
                            wasit, yang tidak ikut dibongkar.
                        --}}
                        <button type="button" x-on:click="batalkanVerifikasi()"
                                class="min-h-[48px] rounded-silat border border-silat-tepi-petak px-4 text-[14px] text-silat-teks-kedua">
                            Batalkan
                        </button>
                        <button type="button" x-on:click="terapkanVerifikasi()"
                                x-bind:disabled="! verifikasi?.hasil"
                                class="min-h-[48px] flex-1 rounded-silat bg-silat-aksi text-[15px] font-semibold text-silat-aksi-teks disabled:opacity-45"
                                x-text="verifikasi?.hasil
                                    ? 'Terapkan dan lanjutkan pertandingan'
                                    : 'Menunggu ' + ((verifikasi?.menunggu ?? []).map(m => m.sebutan).join(', ') || 'juri')"></button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
