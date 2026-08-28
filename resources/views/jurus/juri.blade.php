<x-layouts.silat :title="'Juri Jurus — '.$performance->registration->athletes->pluck('name')->implode(', ')">
    {{--
        Juri Jurus berdiri di tepi gelanggang memegang HP landscape, sama seperti
        juri Tanding. Bedanya ia memasukkan ANGKA, bukan menekan tombol teknik --
        dan angka desimal adalah hal yang paling sulit dimasukkan dengan benar
        sambil berdiri.

        Karena itu panel ini memakai papan tik sendiri, bukan `input[type=number]`.
        Papan tik bawaan HP memunculkan lapisan yang menutupi separuh layar,
        menuntut ketepatan menekan titik desimal, dan tidak pernah menampilkan
        batas 9.00-10.00 yang berlaku. Papan tik di sini hanya punya sepuluh
        angka: titik desimalnya ditempatkan sendiri oleh sistem.
    --}}
    <div x-data="jurusPanel(@js($config))"
         class="flex h-dvh flex-col gap-2 overflow-hidden p-2 select-none">

        <header class="flex shrink-0 items-center justify-between gap-3">
            <div class="flex min-w-0 items-baseline gap-3">
                <span class="truncate text-[17px] font-medium text-silat-teks" x-text="peserta.nama"></span>
                <span class="truncate text-[13px] text-silat-teks-redup" x-text="peserta.kontingen"></span>
                <span class="shrink-0 text-[12px] text-silat-teks-redup">{{ $performance->jurusEvent->nama() }} · {{ ucfirst($performance->tahap) }}</span>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span class="silat-angka text-[18px] font-medium text-silat-teks" x-text="tampilWaktu"></span>
                <x-silat.indikator-koneksi />
            </div>
        </header>

        <p x-show="galat" x-text="galat" x-cloak
           class="shrink-0 rounded-silat bg-red-500/15 px-3 py-1 text-center text-[12px] text-red-300"></p>
        <p x-show="pesan && ! galat" x-text="pesan" x-cloak
           class="shrink-0 rounded-silat bg-silat-panel px-3 py-1 text-center text-[12px] text-silat-teks-redup"></p>

        {{--
            State papan tik hidup di x-data BERSARANG, bukan disebar ke dalam
            x-data induk. Menyebar `{...jurusPanel(cfg), ...}` mengevaluasi
            getter milik jurusPanel sekali saat itu juga dan membekukan hasilnya
            jadi nilai statis -- cacat yang sudah pernah ditemukan di panel juri
            Tanding dan dicatat di sana. Scope bersarang tetap bisa memanggil
            kirimNilaiInput() dan penguranganJuri() milik induknya.
        --}}
        <div class="grid min-h-0 flex-1 grid-cols-[1fr_250px_260px] gap-3"
             x-data="{
                digit: '',
                get tampil() {
                    if (! this.digit) return '—.——';
                    const d = this.digit.padEnd(3, '_');
                    return d.length <= 3
                        ? `${d[0]}.${d[1]}${d[2]}`
                        : `${d.slice(0, 2)}.${d.slice(2, 4)}`;
                },
                get nilaiAngka() {
                    if (this.digit.length < 3) return null;
                    const n = this.digit.length <= 3
                        ? Number(`${this.digit[0]}.${this.digit.slice(1)}`)
                        : Number(`${this.digit.slice(0, 2)}.${this.digit.slice(2)}`);
                    return Number.isFinite(n) ? n : null;
                },
                get sah() {
                    const n = this.nilaiAngka;
                    return n !== null && n >= 9 && n <= 10;
                },
                tekan(a) { if (this.digit.length < 4) this.digit += String(a) },
                hapus() { this.digit = this.digit.slice(0, -1) },
                ulangi() { this.digit = '' },
                kirimDariPapanTik() {
                    if (! this.sah) return;
                    this.nilaiInput = this.nilaiAngka.toFixed(2);
                    return this.kirimNilaiInput();
                },
             }">

            {{-- Angka yang sedang disusun, dan tombol kirim --}}
            <div class="flex min-h-0 flex-col gap-2">
                <div class="flex shrink-0 items-baseline justify-between">
                    <span class="text-[11px] tracking-[.1em] text-silat-teks-redup uppercase">Nilai saya</span>
                    <span class="silat-angka text-[12px] text-silat-teks-redup">9.00 – 10.00</span>
                </div>

                <div class="flex flex-1 items-center justify-center rounded-silat border border-silat-tepi-kendali bg-silat-panel"
                     x-bind:class="digit && ! sah ? 'border-silat-peringatan' : ''">
                    <span class="silat-angka text-[56px] leading-none font-medium"
                          x-bind:class="digit ? 'text-silat-teks' : 'text-silat-teks-redup'"
                          x-text="tampil"
                          aria-live="polite"></span>
                </div>

                {{-- Nilai di luar 9.00-10.00 ditolak SEBELUM dikirim, dengan
                     menyebutkan batasnya -- bukan lewat pesan galat dari server
                     setelah juri menekan Kirim. --}}
                <p x-show="digit && ! sah" x-cloak class="shrink-0 text-[12px] text-silat-peringatan">
                    Nilai Jurus hanya antara 9.00 dan 10.00.
                </p>

                <p x-show="nilaiSaya !== null" x-cloak class="shrink-0 text-[12px] text-silat-teks-redup">
                    Terkirim: <span class="silat-angka text-silat-teks" x-text="nilaiSaya?.toFixed(2)"></span>
                </p>

                <button type="button" x-on:click="kirimDariPapanTik()" x-bind:disabled="! sah"
                        class="min-h-[var(--silat-sentuh-min)] shrink-0 rounded-silat bg-silat-aksi text-[17px] font-medium text-silat-aksi-teks disabled:opacity-45">
                    Kirim nilai
                </button>
            </div>

            {{-- Papan tik --}}
            <div class="grid min-h-0 grid-cols-3 grid-rows-4 gap-1.5">
                @foreach ([1,2,3,4,5,6,7,8,9] as $a)
                    <button type="button" x-on:click="tekan({{ $a }})"
                            class="silat-angka rounded-silat border border-silat-tepi-kendali text-[22px] font-medium text-silat-teks">{{ $a }}</button>
                @endforeach
                <button type="button" x-on:click="ulangi()"
                        class="rounded-silat border border-silat-tepi-kendali text-[13px] text-silat-teks-redup">Ulangi</button>
                <button type="button" x-on:click="tekan(0)"
                        class="silat-angka rounded-silat border border-silat-tepi-kendali text-[22px] font-medium text-silat-teks">0</button>
                <button type="button" x-on:click="hapus()" aria-label="Hapus satu angka"
                        class="flex items-center justify-center rounded-silat border border-silat-tepi-kendali text-silat-teks">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 5h11v14H9L3 12z"/><path d="M12 10l4 4M16 10l-4 4"/>
                    </svg>
                </button>
            </div>

            {{-- Pengurangan juri --}}
            <div class="flex min-h-0 flex-col gap-1.5 rounded-silat bg-silat-panel p-2">
                <div class="flex shrink-0 items-baseline justify-between">
                    <span class="text-[12px] font-medium text-silat-teks">Pengurangan</span>
                    <span class="silat-angka text-[12px] text-silat-teks-redup">−{{ number_format(config('scoring.jurus.pengurangan.juri', 0.01), 2) }} tiap kesalahan</span>
                </div>

                {{--
                    Alasannya dipilih, bukan diketik. Keempatnya sudah disebut
                    Pasal 12.1.e, jadi mengetiknya hanya memperlambat juri yang
                    sedang mengawasi penampilan -- dan menghasilkan tulisan yang
                    tidak seragam di berita acara.
                --}}
                @foreach ([
                    'Kesalahan rincian gerak',
                    'Kesalahan urutan',
                    'Gerakan tertinggal',
                    'Senjata terlepas',
                ] as $alasan)
                    <button type="button" x-on:click="penguranganJuri(@js($alasan))"
                            class="flex-1 rounded-silat border border-silat-tepi-kendali px-3 text-left text-[14px] text-silat-teks">
                        {{ $alasan }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.silat>
