@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Operator Jurus — '.$performance->registration->athletes->pluck('name')->implode(', ')">
    @php
        /*
         * Waktu acuan datang dari JurusEvent, toleransi dan ambang
         * diskualifikasi dari setelan kejuaraan -- bukan ditulis di layar
         * sebagai angka tetap, dan bukan lagi dari config. Ketiganya berbeda
         * per nomor DAN per golongan usia: Usia Dini boleh melenceng 10 detik,
         * Dewasa hanya 5, dan panitia menggesernya lewat menu Peraturan.
         *
         * Nilainya tetap selama halaman hidup, jadi ia dihitung di sini alih-alih
         * ikut muatan JSON yang disegarkan tiap perubahan skor.
         */
        $acuanMs = $performance->jurusEvent->waktuAcuanMs($performance->tahap);
        $waktuJurus = $performance->jurusEvent->tournament->peraturan()
            ->jurusWaktuUntuk($performance->jurusEvent->golongan_usia);
        $toleransi = $waktuJurus['toleransi_detik'];
        $ambangDq = $waktuJurus['diskualifikasi_lewat_detik'];
        $acuanDetik = $acuanMs ? (int) round($acuanMs / 1000) : null;
        $jam = fn (int $d) => sprintf('%02d:%02d', intdiv($d, 60), $d % 60);
    @endphp

    <div x-data="jurusPanel(@js($config))" class="flex min-h-screen flex-col gap-4 p-4">
        <header class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-samar">OPERATOR JURUS</p>
                <h1 class="text-[18px] font-medium text-silat-teks" x-text="peserta.nama"></h1>
                <p class="text-[13px] text-silat-teks-redup" x-text="peserta.kontingen"></p>
                <p class="text-[12px] text-silat-teks-redup">{{ $performance->jurusEvent->nama() }} · {{ ucfirst($performance->tahap) }}</p>
            </div>

            <x-silat.indikator-koneksi class="shrink-0" />
        </header>

        <p x-show="galat" x-text="galat" class="rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        <div class="rounded-silat bg-silat-panel p-6">
            <div class="flex items-end justify-between gap-6">
                {{--
                    Jamnya sendiri yang menyatakan penampilan ini di dalam atau
                    di luar rentang aman.
                    ------------------------------------------------------------
                    Rentangnya memang tertulis di bawah ("Aman 01:15–01:25"),
                    tapi membandingkan dua angka di dua tempat adalah pekerjaan
                    yang harus dilakukan operator SETIAP penampilan, sambil
                    mengawasi matras. Terlihat pada QA: penampilan 00:02 pada
                    acuan 01:20 lewat tanpa satu pun tanda di layar.

                    Yang dilakukan tetap cuma MENANDAI. Pengurangan waktu
                    diputuskan Pengawas dan ditekan manual (Pasal 12.1.e.1.a);
                    panel tidak boleh menjatuhkannya sendiri.
                --}}
                @php($amanBawah = $acuanDetik ? $acuanDetik - $toleransi : null)
                @php($amanAtas = $acuanDetik ? $acuanDetik + $toleransi : null)
                @php($batasGugur = $acuanDetik ? $acuanDetik + $ambangDq : null)

                <div x-data="{
                        get detik() { return Math.round((performance?.duration_ms ?? 0) / 1000) },
                        get diluarAman() {
                            @if ($acuanDetik)
                                return performance?.status === 'selesai'
                                    && (this.detik < {{ $amanBawah }} || this.detik > {{ $amanAtas }});
                            @else
                                return false;
                            @endif
                        },
                        get gugur() {
                            @if ($acuanDetik)
                                return performance?.status === 'selesai' && this.detik > {{ $batasGugur }};
                            @else
                                return false;
                            @endif
                        },
                     }">
                    <p class="silat-angka text-[64px] leading-none font-medium"
                       x-bind:class="gugur ? 'text-silat-peringatan' : (diluarAman ? 'text-silat-teguran' : 'text-silat-teks')"
                       x-text="tampilWaktu"></p>
                    <p class="mt-1 text-[12px] text-silat-teks-redup" x-text="{
                        terjadwal: 'Belum dimulai', berlangsung: 'Sedang tampil', selesai: 'Selesai',
                    }[performance.status]"></p>

                    @if ($acuanDetik)
                        <p x-show="diluarAman && ! gugur" x-cloak
                           class="mt-1 text-[12px] font-medium text-silat-teguran">
                            Di luar rentang aman — Pengawas boleh menjatuhkan pengurangan waktu.
                        </p>
                        <p x-show="gugur" x-cloak
                           class="mt-1 text-[12px] font-medium text-silat-peringatan">
                            Lewat {{ $jam($batasGugur) }} — penampilan ini bisa didiskualifikasi.
                        </p>
                    @endif
                </div>

                @if ($acuanDetik)
                    <div class="text-right">
                        <p class="silat-angka text-[22px] font-medium text-silat-teks">{{ $jam($acuanDetik) }}</p>
                        <p class="text-[12px] text-silat-teks-redup">waktu acuan · {{ ucfirst($performance->tahap) }}</p>
                    </div>
                @endif
            </div>

            @if ($acuanDetik)
                {{--
                    Pita zona waktu. Angka telanjang memaksa operator menghitung
                    selisihnya sendiri sambil penampilan berjalan -- padahal yang
                    perlu diketahuinya cuma "masih aman, sudah kena pengurangan,
                    atau sudah lewat batas diskualifikasi".

                    Pasal 12.1.e.1.a: melenceng lebih dari toleransi berarti
                    pengurangan; jauh melewatinya berarti diskualifikasi yang
                    ditetapkan Pengawas -- bukan otomatis dari selisih waktu.
                --}}
                <div class="mt-5 flex flex-col gap-2">
                    {{-- Lebar tiap zona ditulis sebagai style, bukan kelas
                         `flex-[...]`: nilainya dihitung saat render dari config,
                         dan Tailwind hanya membangkitkan kelas yang terbaca
                         statis di berkas. --}}
                    <div class="flex h-2.5 overflow-hidden rounded-[3px]">
                        <div class="bg-silat-tepi-kendali" style="flex: {{ max(1, $acuanDetik - $toleransi) }}"></div>
                        <div class="bg-silat-hidup" style="flex: {{ $toleransi * 2 }}"></div>
                        <div class="bg-silat-teguran" style="flex: {{ max(1, $ambangDq - $toleransi) }}"></div>
                        <div class="bg-silat-peringatan" style="flex: {{ $toleransi * 2 }}"></div>
                    </div>

                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-[13px] text-silat-teks">
                        <span class="flex items-center gap-2">
                            <span class="size-3 rounded-[2px] bg-silat-hidup"></span>
                            Aman {{ $jam($acuanDetik - $toleransi) }}–{{ $jam($acuanDetik + $toleransi) }}
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="size-3 rounded-[2px] bg-silat-teguran"></span>
                            Lewat toleransi — pengurangan {{ number_format(config('scoring.jurus.pengurangan.pengawas', 0.5), 2) }}
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="size-3 rounded-[2px] bg-silat-peringatan"></span>
                            Lewat {{ $jam($acuanDetik + $ambangDq) }} — diskualifikasi
                        </span>
                    </div>
                </div>
            @endif

            @resource(rk('penampilan-jurus', ResourceAction::Update))
                <div class="mt-4 flex justify-center gap-2">
                    <button type="button" x-show="performance.status === 'terjadwal'" x-on:click="mulai()"
                            class="rounded-silat bg-silat-aksi px-5 py-2 text-[13px] font-medium text-silat-aksi-teks">
                        Mulai
                    </button>
                    <button type="button" x-show="performance.status === 'berlangsung'" x-on:click="berhenti()"
                            class="rounded-silat bg-silat-merah px-5 py-2 text-[13px] text-silat-teks">
                        Selesai
                    </button>
                </div>
            @endresource
        </div>

        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-silat bg-silat-panel p-3">
                <p class="text-[11px] text-silat-teks-redup">Median</p>
                <p class="silat-angka text-[20px] text-silat-teks" x-text="skor.median.toFixed(2)"></p>
            </div>
            <div class="rounded-silat bg-silat-panel p-3">
                <p class="text-[11px] text-silat-teks-redup">Pengurangan</p>
                <p class="silat-angka text-[20px] text-silat-teks" x-text="'−' + skor.total_pengurangan.toFixed(2)"></p>
            </div>
            <div class="rounded-silat bg-silat-panel p-3">
                <p class="text-[11px] text-silat-teks-redup">Skor Akhir</p>
                <p class="silat-angka text-[20px] font-medium text-silat-teks" x-text="performance.didiskualifikasi ? 'DQ' : skor.akhir.toFixed(2)"></p>
            </div>
        </div>

        <div class="rounded-silat bg-silat-panel p-4">
            <p class="mb-2 text-[13px] font-medium text-silat-teks">Nilai juri</p>
            <template x-if="nilaiJuri.length === 0">
                <p class="text-[13px] text-silat-teks-redup">Belum ada juri yang mengirim nilai.</p>
            </template>
            {{--
                Dua nilai tengah ditandai tepi terang: dari keduanyalah median
                lahir. Tanpa penanda, operator melihat deret angka yang tampak
                setara dan tidak punya cara menjelaskan ke pelatih dari mana
                skor akhirnya datang.

                Pasal 12.1.f: nilai akhir adalah MEDIAN, bukan penjumlahan
                setelah membuang nilai tertinggi dan terendah. Cara itu berasal
                dari edisi peraturan lama dan tidak berlaku di naskah 2025 --
                perbedaan yang paling sering ditanyakan pelatih.
            --}}
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3"
                 x-data="{
                    get indeksTengah() {
                        const n = this.nilaiJuri.length;
                        if (n < 2) return [];
                        const urut = [...this.nilaiJuri]
                            .map((x, i) => ({ i, v: x.value }))
                            .sort((a, b) => a.v - b.v);
                        return n % 2 === 0
                            ? [urut[n / 2 - 1].i, urut[n / 2].i]
                            : [urut[(n - 1) / 2].i];
                    },
                 }">
                <template x-for="(n, i) in nilaiJuri" :key="n.judge_user_id">
                    <div class="rounded-[4px] px-3 py-2 text-center"
                         x-bind:class="indeksTengah.includes(i)
                             ? 'bg-silat-latar border border-silat-tepi-petak'
                             : 'bg-silat-latar border border-transparent'">
                        <p class="truncate text-[11px] text-silat-teks-redup" x-text="n.nama"></p>
                        <p class="silat-angka text-[16px] text-silat-teks" x-text="n.value.toFixed(2)"></p>
                        <p x-show="indeksTengah.includes(i)" x-cloak
                           class="text-[10px] tracking-[.06em] text-silat-teks-redup uppercase">nilai tengah</p>
                    </div>
                </template>
            </div>

            <p x-show="nilaiJuri.length >= 2" x-cloak class="mt-2 text-[12px] leading-relaxed text-silat-teks-redup">
                Skor akhir memakai <strong class="font-medium text-silat-teks">median</strong> dari nilai juri — bukan penjumlahan setelah membuang nilai tertinggi dan terendah.
            </p>
        </div>

        <div class="rounded-silat bg-silat-panel p-4">
            <p class="mb-2 text-[13px] font-medium text-silat-teks">Pengurangan</p>

            @resource(rk('pengurangan-jurus', ResourceAction::Create))
                {{--
                    Alasannya dipilih, bukan diketik. Kelimanya sudah disebut
                    Pasal 12.1.e sebagai sebab pengurangan Pengawas, jadi
                    mengetiknya hanya memperlambat orang yang sedang mengawasi
                    penampilan -- dan menghasilkan tulisan yang tidak seragam di
                    berita acara, padahal alasan itu ikut tercetak di sana.
                --}}
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach ([
                        'Pelanggaran waktu',
                        'Keluar gelanggang',
                        'Senjata menyentuh lantai',
                        'Pakaian tidak sesuai',
                        'Menahan gerakan lebih dari 5 detik',
                    ] as $alasan)
                        <button type="button" x-on:click="penguranganPengawas(@js($alasan))"
                                class="min-h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali px-4 text-[14px] text-silat-teks">
                            {{ $alasan }}
                        </button>
                    @endforeach
                </div>

                {{--
                    Diskualifikasi berdiri terpisah dari daftar pengurangan.
                    Pasal 12.1.e.4.h menyebutnya sebagai skor 0.00 yang
                    DITETAPKAN Pengawas, bukan akibat otomatis dari selisih
                    waktu -- dan ia tidak bisa ditarik kembali, jadi ia tidak
                    boleh duduk sebaris dengan tombol yang ditekan berkali-kali.
                --}}
                {{--
                    Satu tekanan lagi sebelum jadi, karena tekanan pertamanya
                    tidak bisa dibatalkan.

                    Sampai uji lapangan hari ini, tombol ini langsung
                    menjatuhkan diskualifikasi: satu sentuhan keliru di layar
                    yang dipegang sambil berdiri mengubah skor seorang pesilat
                    jadi 0.00, dan tidak ada satu pun jalan di sistem untuk
                    mengembalikannya. Pengurangan 0.50 punya tombol "Batal";
                    yang ini tidak, jadi penjagaannya harus berdiri SEBELUM
                    tekanan, bukan sesudahnya. Bentuknya sengaja sama dengan
                    dialog "Akhiri partai" di panel Tanding: batal di kiri,
                    akibatnya ditulis kalimat penuh.
                --}}
                <div x-show="! performance.didiskualifikasi" x-cloak class="mb-3 flex items-center gap-3"
                     x-data="{ dialogDq: false }">
                    <button type="button" x-on:click="dialogDq = true"
                            class="min-h-[var(--silat-sentuh-min)] rounded-silat border border-silat-peringatan px-5 text-[14px] font-medium text-silat-peringatan">
                        Diskualifikasi
                    </button>
                    <span class="text-[12px] leading-relaxed text-silat-teks-redup">
                        Skor menjadi {{ number_format(config('scoring.jurus.skor_diskualifikasi', 0), 2) }} dan penampilan tidak bisa dinilai lagi.
                    </span>

                    <div x-show="dialogDq" x-cloak x-on:keydown.escape.window="dialogDq = false"
                         class="fixed inset-0 z-90 grid place-items-center bg-black/75 p-6">
                        <div class="w-full max-w-[520px] rounded-silat-besar border border-silat-garis bg-silat-panel p-6.5"
                             role="dialog" aria-modal="true" aria-labelledby="judul-dq">
                            <p id="judul-dq" class="text-[21px] font-semibold tracking-[-0.02em] text-silat-teks">
                                Diskualifikasi <span x-text="peserta.nama"></span>?
                            </p>
                            <p class="mt-3 text-[14.5px] leading-[1.7] text-silat-teks-redup">
                                Skor akhirnya menjadi
                                {{ number_format(config('scoring.jurus.skor_diskualifikasi', 0), 2) }}, penampilan ini
                                tidak bisa dinilai lagi, dan keputusannya <span class="text-silat-teks">tidak bisa
                                ditarik kembali lewat sistem</span>. Kalau batal, tidak ada yang berubah.
                            </p>

                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" x-on:click="dialogDq = false"
                                        class="min-h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali px-5 text-[14.5px] text-silat-teks-kedua">
                                    Tidak jadi
                                </button>
                                <button type="button" x-on:click="dialogDq = false; diskualifikasi()"
                                        class="min-h-[var(--silat-sentuh-min)] rounded-silat border border-silat-peringatan px-5 text-[14.5px] font-semibold text-silat-peringatan">
                                    Diskualifikasi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endresource

            <template x-if="pengurangan.length === 0">
                <p class="text-[13px] text-silat-teks-redup">Belum ada pengurangan.</p>
            </template>

            <div class="divide-y divide-silat-garis">
                <template x-for="d in pengurangan" :key="d.id">
                    <div class="flex items-center justify-between gap-3 py-2" x-data="{ alasanBatal: '' }">
                        <p class="text-[13px] text-silat-teks">
                            <span class="silat-angka" x-text="'−' + d.jumlah.toFixed(2)"></span>
                            · <span x-text="d.tier === 'juri' ? 'Juri' : 'Pengawas'"></span>
                            · <span x-text="d.alasan"></span>
                        </p>

                        @resource(rk('hasil-jurus', ResourceAction::Update))
                            <div class="flex shrink-0 items-center gap-1">
                                <input type="text" x-model="alasanBatal" placeholder="Alasan batal"
                                       class="w-32 rounded-silat border border-silat-garis bg-silat-latar px-2 py-1 text-[11px] text-silat-teks placeholder:text-silat-teks-samar">
                                <button type="button" x-on:click="batalkanPengurangan(d.id, alasanBatal)" x-bind:disabled="! alasanBatal"
                                        class="rounded-silat bg-silat-mati px-2 py-1 text-[11px] text-silat-teks disabled:opacity-40">
                                    Batal
                                </button>
                            </div>
                        @endresource
                    </div>
                </template>
            </div>
        </div>

        @resource(rk('hasil-jurus', ResourceAction::Approve))
            <div class="rounded-silat bg-silat-panel p-4 text-center">
                <template x-if="performance.status === 'selesai' && ! performance.ratified">
                    <button type="button" x-on:click="sahkan()" class="rounded-silat bg-silat-aksi px-5 py-2 text-[13px] font-medium text-silat-aksi-teks">
                        Sahkan skor akhir
                    </button>
                </template>
                <p x-show="performance.ratified" class="text-[13px] text-emerald-300">Sudah disahkan.</p>
                <p x-show="performance.status !== 'selesai'" class="text-[13px] text-silat-teks-redup">
                    Pengesahan hanya bisa dilakukan setelah penampilan selesai.
                </p>
            </div>
        @endresource

        {{-- Muncul sendiri begitu kedua sudut battle disahkan. Operator yang
             mengumumkan pemenang membaca dasarnya dari sini, tanpa berpindah
             ke halaman battle. --}}
        <x-silat.komparasi-battle class="bg-silat-panel" />
    </div>
</x-layouts.silat>
