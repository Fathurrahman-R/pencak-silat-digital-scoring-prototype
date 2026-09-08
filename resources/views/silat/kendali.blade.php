@php
    use App\Enums\ResourceAction;
@endphp

<x-layouts.silat :title="'Kendali — '.$arena->name" :manifest="$manifestUrl ?? null">
    {{--
        Panel Pengendali Gelanggang.

        Satu perangkat memegang satu gelanggang: ia yang menentukan partai mana
        yang sedang dimainkan, menjalankan timer, dan memindahkan babak. Juri,
        wasit, dan dewan wasit juri mengikuti pilihannya tanpa menyentuh
        perangkat masing-masing.

        Tata letaknya sengaja dua kolom, bukan satu daftar panjang: antrean
        jadwal dibaca sambil melirik, sementara timer dan tombol babak ditekan
        sambil mengawasi matras. Yang kedua tidak boleh ikut tergulir saat yang
        pertama digulir.
    --}}
    {{-- Tinggi layar dikunci mulai `lg` saja -- di bawahnya kolom jadwal dan
         kolom kendali bertumpuk, dan keduanya tidak muat dalam satu layar
         ponsel. `overflow-hidden` di situ memotong tombol paling bawah tanpa
         menyisakan cara mencapainya. --}}
    <div x-data="partaiPanel(@js($config))" class="flex min-h-dvh flex-col lg:h-dvh lg:overflow-hidden">

        <header class="flex shrink-0 items-center gap-5 border-b border-silat-garis px-6 py-4">
            <div class="min-w-0">
                <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">
                    PENGENDALI · {{ $arena->name }}
                </p>
                <p class="mt-1 truncate text-[18px] font-medium tracking-[-0.01em] text-silat-teks">
                    <template x-if="match?.id">
                        <span>
                            Partai <span x-text="match.id"></span>
                            · <span x-text="(match.red?.athletes ?? []).join(', ') || '—'"></span>
                            vs <span x-text="(match.blue?.athletes ?? []).join(', ') || '—'"></span>
                        </span>
                    </template>
                    {{-- Gelanggang yang menayangkan Jurus bukan gelanggang yang
                         menganggur. Menulisnya "belum ada partai dipilih"
                         membuat pengendali mengira matrasnya kosong, lalu
                         menayangkan partai Tanding di atas penampilan yang
                         sedang dinilai juri di gelanggang yang sama. --}}
                    <template x-if="! match?.id && panel?.jurus?.tayang">
                        <span>
                            Jurus <span x-text="panel.jurus.tayang.nomor ?? '—'"></span>
                            · <span x-text="panel.jurus.tayang.peserta || '—'"></span>
                        </span>
                    </template>

                    <template x-if="! match?.id && ! panel?.jurus?.tayang">
                        <span class="text-silat-teks-redup">Belum ada partai dipilih</span>
                    </template>
                </p>
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-3">
                <x-silat.indikator-koneksi />

                {{-- Pendaratan otomatis tidak boleh mengunci: petugas yang
                     juga memegang peran lain harus punya jalan keluar. --}}
                <a href="{{ route('dashboard', ['dashboard' => 1]) }}"
                   class="silat-angka text-[11px] tracking-[.08em] text-silat-teks-samar uppercase">Dashboard</a>
            </div>
        </header>

        <template x-if="galat || pesan">
            <div class="mx-6 mt-4 flex shrink-0 flex-wrap items-center gap-3 rounded-silat bg-silat-panel px-4 py-2.5">
                <p class="min-w-0 flex-1 text-[13.5px] text-silat-teks-kedua" x-text="galat || pesan"></p>

                {{--
                    Jalan paksa berdiri DI SAMPING penolakannya, bukan di
                    tempat lain.

                    Pesannya sudah lama menyuruh pengendali "pindah paksa"
                    sementara tidak satu pun tombol di panel ini mengirimnya:
                    gelanggang yang partainya ditinggal berjalan -- perangkat
                    pengendali mati, partai batal di tengah -- tidak punya
                    jalan keluar sama sekali. Tombolnya sengaja terpisah dan
                    baru muncul setelah penolakan: memaksa tetap harus
                    dinyatakan, bukan ditemukan sebagai efek samping.
                --}}
                <template x-if="paksaTertunda">
                    <button type="button"
                            x-on:click="ulangiPaksa()"
                            class="h-11 shrink-0 rounded-silat border border-silat-tepi-kendali px-4 text-[13.5px] font-semibold text-silat-teks"
                            x-text="paksaTertunda.matchId === null && ! ('penampilanId' in paksaTertunda) ? 'Kosongkan paksa' : 'Pindah paksa'"></button>
                </template>
            </div>
        </template>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-5 px-6 pt-5 pb-6 lg:grid-cols-[1fr_340px]">

            {{--
                ANTREAN JADWAL.

                Digulir sendiri. Gelanggang berisi empat puluh partai bukan
                hal luar biasa, dan yang tidak boleh ikut terdorong keluar
                layar adalah kolom timer di sebelahnya.
            --}}
            <div class="flex min-h-0 min-w-0 flex-col">
                {{--
                    Pengendali gelanggang lain boleh MELIHAT panel ini -- ia
                    perlu tahu matras sebelah sedang di partai mana -- tapi
                    tidak boleh menekan apa pun di dalamnya. Tombolnya karena
                    itu tidak digambar sama sekali, bukan digambar lalu dijawab
                    403: yang menekan "Kosongkan gelanggang" di gelanggang
                    sebelah tidak selalu membaca pesan galatnya, dan sebagian
                    mengira gelanggangnya benar-benar kosong.
                --}}
                @unless ($config['bolehKendali'] ?? true)
                    <div class="mb-3 flex shrink-0 items-center gap-2.5 rounded-silat border border-silat-garis bg-silat-panel px-4 py-3">
                        <span class="size-2 shrink-0 rounded-full bg-silat-teks-redup"></span>
                        <p class="text-[13.5px] leading-[1.5] text-silat-teks-kedua">
                            Kamu bukan pengendali {{ $arena->name }} — layar ini hanya untuk dilihat.
                            Kendalinya ada di pengendali yang ditugaskan ke gelanggang ini.
                        </p>
                    </div>
                @endunless

                <div class="flex shrink-0 items-center gap-3.5 pb-3">
                    <p class="text-[17px] font-semibold tracking-[-0.01em] text-silat-teks">Antrean gelanggang</p>
                    <span class="silat-angka text-[11.5px] text-silat-teks-samar"
                          x-text="(panel?.antrean?.length ?? 0) + ' partai'"></span>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto rounded-silat-besar border border-silat-garis">
                    <template x-if="(panel?.antrean?.length ?? 0) === 0">
                        <p class="px-4 py-8 text-center text-[13.5px] text-silat-teks-redup">
                            Belum ada partai dijadwalkan ke gelanggang ini.
                        </p>
                    </template>

                    <template x-for="(partai, i) in (panel?.antrean ?? [])" :key="partai.id">
                        <div class="flex items-center gap-3.5 px-4 py-3.5"
                             x-bind:class="[
                                 i > 0 ? 'border-t border-silat-panel' : '',
                                 partai.aktif ? 'bg-silat-panel' : '',
                             ]">

                            <span class="silat-angka w-8 shrink-0 text-[12px] text-silat-teks-samar"
                                  x-text="partai.urutan ?? '—'"></span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[14px] text-silat-teks">
                                    <span x-text="partai.merah || '—'"></span>
                                    <span class="text-silat-teks-samar">vs</span>
                                    <span x-text="partai.biru || '—'"></span>
                                </p>
                                <p class="silat-angka mt-1 text-[11.5px] text-silat-teks-samar">
                                    Partai <span x-text="partai.id"></span>
                                    · <span x-text="partai.kelas ?? '—'"></span>
                                    · <span x-text="partai.status"></span>
                                </p>
                            </div>

                            {{-- Yang sedang tayang tidak menawarkan tombol
                                 pindah ke dirinya sendiri: tombol yang tidak
                                 mengubah apa pun tetap menuntut dibaca. --}}
                            <template x-if="partai.aktif">
                                <span class="silat-angka shrink-0 rounded-silat-kecil bg-silat-teks px-2.5 py-1.5 text-[11px] font-semibold text-silat-latar">
                                    TAYANG
                                </span>
                            </template>

                            @if ($config['bolehKendali'] ?? true)
                                <template x-if="! partai.aktif">
                                    <button type="button"
                                            x-on:click="pilihPartai(partai.id)"
                                            class="h-11 w-24 shrink-0 rounded-silat border border-silat-tepi-kendali text-[13px] font-medium text-silat-teks-kedua">
                                        Tayangkan
                                    </button>
                                </template>
                            @endif

                            {{-- Pemindahan antar gelanggang berdiri di baris
                                 partainya sendiri, bukan di layar terpisah:
                                 yang memutuskan sedang menatap antrean, dan
                                 keputusannya lahir dari membandingkan antrean
                                 itu dengan gelanggang sebelah. --}}
                            @if ($config['bolehKendali'] ?? true)
                            <template x-if="(panel?.serah?.gelanggang?.length ?? 0) > 0 && ! partai.aktif">
                                <select class="h-11 shrink-0 rounded-silat border border-silat-tepi-kendali bg-transparent px-2 text-[12.5px] text-silat-teks-kedua"
                                        aria-label="Pindahkan partai ini ke gelanggang lain"
                                        x-on:change="if ($event.target.value) { lepasKeGelanggang(partai.id, $event.target.value); $event.target.value = '' }">
                                    <option value="">Pindahkan…</option>
                                    <template x-for="lain in (panel?.serah?.gelanggang ?? [])" :key="lain.id">
                                        <option x-bind:value="lain.id" x-text="lain.nama"></option>
                                    </template>
                                </select>
                            </template>
                            @endif
                        </div>
                    </template>
                </div>
            </div>

            {{--
                ANTREAN JURUS.

                Muncul hanya kalau gelanggang ini memang kebagian nomor Jurus.
                Kejuaraan yang seluruhnya Tanding tidak perlu melihat satu
                baris pun tentangnya, dan daftar kosong berjudul "Jurus"
                membuat pengendali mencari sesuatu yang tidak ada.

                Satu gelanggang menayangkan satu hal, jadi menekan Tayangkan di
                sini melepas partai Tanding yang sedang tayang -- lewat
                penjagaan yang sama, jadi partai yang babaknya masih berjalan
                tetap menolak ditinggalkan tanpa pernyataan paksa.
            --}}
            <template x-if="(panel?.jurus?.antrean?.length ?? 0) > 0">
                <div class="mt-4 flex min-h-0 flex-col">
                    <div class="flex shrink-0 items-center gap-3.5 pb-3">
                        <p class="text-[17px] font-semibold tracking-[-0.01em] text-silat-teks">Antrean Jurus</p>
                        <span class="silat-angka text-[11.5px] text-silat-teks-samar"
                              x-text="(panel?.jurus?.antrean?.length ?? 0) + ' penampilan'"></span>

                        <a x-bind:href="panel?.jurus?.panel" target="_blank"
                           class="silat-angka ml-auto text-[11px] tracking-[.08em] text-silat-teks-samar uppercase">Panel Jurus</a>
                    </div>

                    <div class="max-h-64 min-h-0 overflow-y-auto rounded-silat-besar border border-silat-garis">
                        <template x-for="(satu, i) in (panel?.jurus?.antrean ?? [])" :key="satu.id">
                            <div class="flex items-center gap-3.5 px-4 py-3.5"
                                 x-bind:class="[i > 0 ? 'border-t border-silat-panel' : '', satu.aktif ? 'bg-silat-panel' : '']">
                                <span class="silat-angka w-8 shrink-0 text-[12px] text-silat-teks-samar"
                                      x-text="satu.urutan ?? '—'"></span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[14px] text-silat-teks" x-text="satu.peserta || '—'"></p>
                                    <p class="silat-angka mt-1 truncate text-[11.5px] text-silat-teks-samar">
                                        <span x-text="satu.nomor ?? '—'"></span>
                                        · <span x-text="satu.kontingen ?? '—'"></span>
                                        · <span x-text="satu.status"></span>
                                    </p>
                                </div>

                                <template x-if="satu.aktif">
                                    <span class="silat-angka shrink-0 rounded-silat-kecil bg-silat-teks px-2.5 py-1.5 text-[11px] font-semibold text-silat-latar">
                                        TAYANG
                                    </span>
                                </template>

                                @if ($config['bolehKendali'] ?? true)
                                    <template x-if="! satu.aktif">
                                        <button type="button"
                                                x-on:click="pilihPenampilan(satu.id)"
                                                class="h-11 w-24 shrink-0 rounded-silat border border-silat-tepi-kendali text-[13px] font-medium text-silat-teks-kedua">
                                            Tayangkan
                                        </button>
                                    </template>
                                @endif
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{--
                SERAH-TERIMA JADWAL.

                Dua daftar yang hanya muncul kalau ada isinya. Jendela di antara
                melepas dan mengambil sengaja TERLIHAT di kedua layar: jendela
                yang disembunyikan adalah jendela yang baru ketahuan saat
                pesilatnya sudah berdiri di matras yang salah.
            --}}
            <template x-if="(panel?.serah?.menunggu?.length ?? 0) > 0 || (panel?.serah?.ditawarkan?.length ?? 0) > 0 || (panel?.serah?.baruMasuk?.length ?? 0) > 0">
                <div class="mt-4 flex flex-col gap-4">
                    {{--
                        Partai yang sudah jadi milik gelanggang ini tapi belum
                        punya nomor urut -- hampir selalu hasil serah-terima.

                        Berdiri sendiri, bukan mengandalkan antrean: antrean
                        menaruh yang tanpa nomor di paling belakang lalu memotong
                        dua puluh, dan di gelanggang dengan ratusan partai
                        terjadwal, partai yang baru masuk tidak pernah terlihat
                        sama sekali.
                    --}}
                    <template x-if="(panel?.serah?.baruMasuk?.length ?? 0) > 0">
                        <div class="rounded-silat-besar border border-silat-garis">
                            <div class="border-b border-silat-garis px-4 py-3.5">
                                <p class="text-[15px] font-semibold tracking-[-0.01em] text-silat-teks">
                                    Baru masuk, belum ditempatkan
                                </p>
                                <p class="mt-1 text-[12.5px] text-silat-teks-samar">
                                    Sudah jadi milik gelanggang ini. Belum punya nomor urut, jadi tidak muncul di antrean.
                                </p>
                            </div>

                            <template x-for="(satu, i) in (panel?.serah?.baruMasuk ?? [])" :key="satu.id">
                                <div class="flex items-center gap-3.5 px-4 py-3.5"
                                     x-bind:class="i > 0 ? 'border-t border-silat-panel' : ''">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[14px] text-silat-teks">
                                            <span x-text="satu.merah || '—'"></span>
                                            <span class="text-silat-teks-samar">vs</span>
                                            <span x-text="satu.biru || '—'"></span>
                                        </p>
                                        <p class="silat-angka mt-1 text-[11.5px] text-silat-teks-samar">
                                            Partai <span x-text="satu.id"></span>
                                            · <span x-text="satu.kelas ?? '—'"></span>
                                        </p>
                                    </div>

                                    @if ($config['bolehKendali'] ?? true)
                                        <button type="button"
                                                x-on:click="pilihPartai(satu.id)"
                                                class="h-11 w-24 shrink-0 rounded-silat border border-silat-tepi-kendali text-[13px] font-medium text-silat-teks-kedua">
                                            Tayangkan
                                        </button>
                                    @endif
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="(panel?.serah?.ditawarkan?.length ?? 0) > 0">
                        <div class="rounded-silat-besar border border-silat-garis">
                            <div class="border-b border-silat-garis px-4 py-3.5">
                                <p class="text-[15px] font-semibold tracking-[-0.01em] text-silat-teks">
                                    Ditawarkan dari gelanggang lain
                                </p>
                                <p class="mt-1 text-[12.5px] text-silat-teks-samar">
                                    Belum masuk jadwal gelanggang ini sampai diambil.
                                </p>
                            </div>

                            <template x-for="(satu, i) in (panel?.serah?.ditawarkan ?? [])" :key="satu.id">
                                <div class="flex items-center gap-3.5 px-4 py-3.5"
                                     x-bind:class="i > 0 ? 'border-t border-silat-panel' : ''">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[14px] text-silat-teks">
                                            <span x-text="satu.jenis === 'jurus' ? 'Penampilan' : 'Partai'"></span>
                                            <span x-text="satu.baris_id"></span>
                                        </p>
                                        <p class="mt-1 text-[11.5px] text-silat-teks-samar">
                                            dari <span x-text="satu.asal ?? '—'"></span>
                                            <template x-if="satu.alasan">
                                                <span> · <span x-text="satu.alasan"></span></span>
                                            </template>
                                        </p>
                                    </div>

                                    <button type="button"
                                            x-on:click="ambilLepasan(satu.id)"
                                            class="h-11 w-24 shrink-0 rounded-silat bg-silat-teks text-[13px] font-semibold text-silat-latar">
                                        Ambil
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="(panel?.serah?.menunggu?.length ?? 0) > 0">
                        <div class="rounded-silat-besar border border-silat-garis">
                            <div class="border-b border-silat-garis px-4 py-3.5">
                                <p class="text-[15px] font-semibold tracking-[-0.01em] text-silat-teks">
                                    Dilepas, menunggu diambil
                                </p>
                                <p class="mt-1 text-[12.5px] text-silat-teks-samar">
                                    Sudah tidak ditayangkan di sini, dan belum jadi milik gelanggang tujuan.
                                </p>
                            </div>

                            <template x-for="(satu, i) in (panel?.serah?.menunggu ?? [])" :key="satu.id">
                                <div class="flex items-center gap-3.5 px-4 py-3.5"
                                     x-bind:class="i > 0 ? 'border-t border-silat-panel' : ''">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[14px] text-silat-teks">
                                            <span x-text="satu.jenis === 'jurus' ? 'Penampilan' : 'Partai'"></span>
                                            <span x-text="satu.baris_id"></span>
                                        </p>
                                        <p class="mt-1 text-[11.5px] text-silat-teks-samar">
                                            ke <span x-text="satu.tujuan ?? '—'"></span>
                                        </p>
                                    </div>

                                    <button type="button"
                                            x-on:click="batalkanLepas(satu.id)"
                                            class="h-11 w-24 shrink-0 rounded-silat border border-silat-tepi-kendali text-[13px] font-medium text-silat-teks-kedua">
                                        Batalkan
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- TIMER DAN BABAK. Tidak pernah tergulir. --}}
            <aside class="flex min-h-0 flex-col gap-4">
                <div class="flex flex-col items-center justify-center gap-3.5 rounded-silat-besar border border-silat-garis p-5">
                    <div class="flex gap-1.5">
                        <template x-for="i in (peraturan?.jumlah_babak ?? 0)" :key="i">
                            <span class="silat-angka rounded-silat-kecil px-2.5 py-1.5 text-[11px] tracking-[.06em]"
                                  x-bind:class="i === match?.current_round
                                      ? 'bg-silat-teks font-semibold text-silat-latar'
                                      : (i < (match?.current_round ?? 1)
                                          ? 'bg-silat-garis text-silat-teks-redup'
                                          : 'border border-silat-tepi-kendali text-silat-teks-samar')"
                                  x-text="'B' + i"></span>
                        </template>
                    </div>

                    {{-- Jam yang habis TIDAK digambar seperti jam yang
                         berjalan: warnanya berubah jadi warna teguran, supaya
                         yang melirik dari tepi matras melihat keadaannya tanpa
                         membaca satu huruf pun. --}}
                    <p class="silat-angka text-[64px] leading-none font-medium"
                       x-bind:class="waktuHabis
                           ? 'text-silat-teguran'
                           : (babakAktif?.status === 'berjalan' ? 'text-silat-teks' : 'text-silat-teks-redup')"
                       x-text="tampilWaktu" role="timer" aria-live="off">00:00</p>

                    <p class="silat-angka text-[11.5px] tracking-[.12em] text-silat-teks-samar uppercase"
                       x-text="sudahSelesai
                           ? 'Partai selesai'
                           : (waktuHabis
                               ? 'Babak selesai'
                               : ({ berjalan: 'Berjalan', jeda: 'Dijeda', belum_mulai: 'Belum dimulai', selesai: 'Babak selesai' }[babakAktif?.status] ?? 'Belum dimulai'))"></p>
                </div>

                @resource(rk('partai', ResourceAction::Update))
                @if ($config['bolehKendali'] ?? true)
                    <div class="flex flex-col gap-2" x-show="match?.id">
                        {{-- Tinggi 64px mengikuti batas sentuh gelanggang:
                             ditekan sambil mengawasi matras, bukan layar. --}}
                        <button type="button" x-show="babakUntukDimulai !== null && ! susulanTerbuka" x-on:click="mulaiBabak()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat bg-silat-aksi text-[16px] font-semibold text-silat-aksi-teks">
                            {{-- Angka babak dirangkai hanya kalau memang ada. Pada babak terakhir
                                 `babakUntukDimulai` bernilai null, dan tombol yang tersembunyi ini
                                 tetap berlabel "Mulai babak null" di dalam DOM. --}}
                            <span x-text="babakUntukDimulai === null
                                ? 'Mulai babak'
                                : (babakAktif?.status === 'belum_mulai' ? 'Mulai ulang babak ' : 'Mulai babak ') + babakUntukDimulai"></span>
                        </button>
                        <button type="button" x-show="babakAktif?.status === 'jeda' && ! susulanTerbuka" x-on:click="lanjutkan()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat bg-silat-aksi text-[16px] font-semibold text-silat-aksi-teks">Lanjutkan</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan'" x-on:click="jeda()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali text-[16px] font-semibold text-silat-teks-kedua">Jeda</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda'" x-on:click="selesaikanBabak()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali text-[16px] font-semibold text-silat-teks-kedua">Selesaikan babak</button>
                        {{--
                            Reset babak dan Akhiri partai dulu hanya berdiri di
                            Panel Papan, padahal panduan operasional menaruh
                            keduanya di tangan Pengendali Gelanggang -- yang
                            bekerja dari layar ini. Pengendali yang perlu
                            mengakhiri partai terpaksa membuka panel lain di
                            tengah gelanggang.
                        --}}
                        <button type="button" x-show="babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda'" x-on:click="resetBabak()"
                                class="h-13 rounded-silat border border-silat-tepi-kendali text-[14.5px] font-medium text-silat-teks-redup">Reset babak</button>
                    </div>
                @endresource

                    <x-silat.akhiri-partai />
                @endif


                @resource(rk('kendali-gelanggang', ResourceAction::Manage))
                @if ($config['bolehKendali'] ?? true)
                    {{--
                        BABAK SUSULAN.

                        Nilai atau hukuman yang terlewat di babak sebelumnya
                        dicatat dari sini. Babak berjalan dijeda selama itu, dan
                        seluruh panel di gelanggang beralih serentak.

                        Daftar babak yang boleh dibuka datang dari server
                        (`dapat_dibuka`), bukan disusun ulang di sini: panel yang
                        menyusun aturannya sendiri adalah panel yang suatu saat
                        menawarkan tombol untuk hal yang server tolak.
                    --}}
                    <div class="rounded-silat-besar border border-silat-garis p-4" x-show="match?.id">
                        <p class="silat-angka text-[10px] tracking-[.14em] text-silat-teks-redup uppercase">Input susulan</p>

                        <template x-if="susulanTerbuka">
                            <div class="mt-2.5">
                                <p class="text-[13.5px] leading-[1.6] text-amber-300">
                                    Babak <span x-text="susulan?.round"></span> sedang dibuka.
                                    Seluruh panel gelanggang mencatat ke babak itu.
                                </p>
                                <button type="button" x-on:click="tutupSusulan()"
                                        class="mt-3 h-12 w-full rounded-silat bg-silat-aksi text-[15px] font-semibold text-silat-aksi-teks">
                                    Tutup susulan
                                </button>
                            </div>
                        </template>

                        <template x-if="! susulanTerbuka">
                            <div class="mt-2.5 flex flex-wrap gap-2">
                                <template x-for="babak in (rounds ?? []).filter(r => r.dapat_dibuka)" :key="babak.round">
                                    <button type="button" x-on:click="bukaSusulan(babak.round)"
                                            class="h-11 rounded-silat border border-silat-tepi-kendali px-3.5 text-[13px] font-medium text-silat-teks-kedua">
                                        <span x-text="'Catat susulan babak ' + babak.round"></span>
                                    </button>
                                </template>

                                <template x-if="(rounds ?? []).filter(r => r.dapat_dibuka).length === 0">
                                    <p class="text-[13px] leading-[1.6] text-silat-teks-redup">
                                        Belum ada babak yang sudah selesai untuk dibuka kembali.
                                    </p>
                                </template>
                            </div>
                        </template>
                    </div>
                @endif
                @endresource

                @if ($config['bolehKendali'] ?? true)
                    {{--
                        Mengosongkan gelanggang. Bukan tombol besar, dan bukan di
                        dekat tombol babak: ia jarang dipakai, dan yang jarang
                        dipakai tidak boleh duduk di tempat yang tangan sudah hafal.
                    --}}
                    <button type="button" x-show="match?.id" x-on:click="pilihPartai(null)"
                            class="mt-auto h-12 shrink-0 rounded-silat border border-silat-tepi-kendali text-[13.5px] font-medium text-silat-teks-redup">
                        Kosongkan gelanggang
                    </button>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.silat>
