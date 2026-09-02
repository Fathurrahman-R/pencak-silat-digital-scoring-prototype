@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Wasit — '.$match->bracket->weightClass->name">
    {{--
        Wasit memegang HP dalam orientasi landscape 844x390 sambil berdiri di
        matras. Tinggi layar tinggal ~390px, jadi tidak ada ruang untuk kepala
        tebal maupun papan skor penuh -- skor dibaca dari papan gelanggang, yang
        di sini cukup diringkas jadi dua angka di kepala.

        Alurnya dua langkah: pilih sudut dulu, baru jatuhkan hukuman. Susunan
        lama menampilkan enam tombol hukuman sekaligus (tiga per sudut) sehingga
        satu tekan langsung jatuh -- lebih cepat, tapi enam sasaran berdekatan di
        layar selebar telapak tangan membuat salah sudut jadi kesalahan yang
        mudah dan mahal. Sudut yang sedang terpilih dinyatakan besar-besar di
        label langkah 2, jadi tidak ada keadaan "terpilih tapi lupa".
    --}}
    {{--
        `menyusunVerifikasi` hidup di x-data ANAK, bukan disebar ke
        partaiPanel(cfg) lewat {...partaiPanel(cfg), menyusunVerifikasi: false}.
        Penyebaran objek mengevaluasi getter-nya sekali lalu membekukan
        hasilnya jadi nilai statis -- cacat yang sudah pernah membuat label
        babak macet permanen di panel juri (commit fa068e0).
    --}}
    <div x-data="partaiPanel(@js($config))"
         class="flex h-dvh flex-col gap-2 overflow-hidden p-2 select-none">
      <div x-data="{ menyusunVerifikasi: false, _adaYangBerjalan: false }"
           x-on:tutup-verifikasi="menyusunVerifikasi = false"
           {{--
               Menutup layar penyusunan begitu sebuah verifikasi yang tadinya
               berjalan berakhir -- diterapkan maupun dibatalkan.

               Tidak cukup "tutup kalau tidak ada yang berjalan": wasit yang
               baru menekan "Minta verifikasi juri" juga belum punya verifikasi
               berjalan, dan layarnya akan tertutup seketika. Yang menentukan
               adalah PERALIHANNYA, jadi keadaan sebelumnya ikut dicatat.
           --}}
           x-effect="
               if (verifikasiBerjalan) {
                   _adaYangBerjalan = true;
               } else if (_adaYangBerjalan) {
                   _adaYangBerjalan = false;
                   menyusunVerifikasi = false;
               }
           "
           class="flex min-h-0 flex-1 flex-col gap-2">

        <header class="flex shrink-0 items-center justify-between gap-4">
            <div class="flex items-baseline gap-3">
                <span class="silat-angka text-[13px] font-medium text-silat-teks">Partai {{ $match->id }}</span>
                <span class="silat-angka text-[12px] text-silat-teks-redup">
                    Babak <span x-text="match.current_round ?? '–'"></span>/<span x-text="peraturan.jumlah_babak"></span>
                </span>
                <span class="silat-angka text-[15px] font-medium text-silat-teks" x-text="tampilWaktu" role="timer" aria-live="off">00:00</span>

                {{--
                    Wasit yang menghentikan pertandingan adalah tugas Pasal
                    13.6.c.3, jadi kendalinya harus ada di panelnya sendiri --
                    bukan hanya di panel operator. Ia duduk tepat di sebelah
                    timer supaya jelas apa yang dihentikan.
                --}}
                @resource(rk('partai', ResourceAction::Update))
                    <button type="button" x-show="babakAktif?.status === 'berjalan'" x-on:click="jeda()"
                            class="h-[30px] rounded-silat-kecil border border-silat-tepi-petak px-[11px] text-[12.5px] text-silat-teks-kedua">Hentikan</button>
                    <button type="button" x-show="babakAktif?.status === 'jeda'" x-on:click="lanjutkan()"
                            class="h-[30px] rounded-silat-kecil bg-silat-aksi px-[11px] text-[12.5px] font-medium text-silat-aksi-teks">Lanjutkan</button>
                @endresource

                {{--
                    Pemicu verifikasi duduk di sebelah "Hentikan" karena
                    keduanya dipakai berurutan: wasit menghentikan pertandingan
                    lebih dulu, lalu bertanya. Kalau verifikasi sedang berjalan,
                    tombolnya hilang -- yang tampil sudah layar verifikasinya
                    sendiri.
                --}}
                @resource(rk('verifikasi-juri', ResourceAction::Create))
                    <button type="button"
                            x-show="! verifikasiBerjalan && ! menyusunVerifikasi && ! sudahSelesai"
                            x-on:click="menyusunVerifikasi = true"
                            class="h-[30px] rounded-silat-kecil border border-silat-tepi-petak px-[11px] text-[12.5px] text-silat-teks-kedua">
                        Minta verifikasi juri
                    </button>
                @endresource
            </div>

            <div class="flex items-center gap-3">
                {{-- Skor ringkas: wasit tidak perlu papan penuh, tapi perlu tahu
                     kedudukan saat memutuskan hukuman berat. --}}
                <span class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-silat-merah"></span>
                    <span class="silat-angka text-[15px] font-medium text-silat-teks" x-text="skorTotal.merah"></span>
                    <span class="text-silat-teks-redup">—</span>
                    <span class="silat-angka text-[15px] font-medium text-silat-teks" x-text="skorTotal.biru"></span>
                    <span class="size-2 rounded-full bg-silat-biru"></span>
                </span>

                <x-silat.indikator-koneksi />
            </div>
        </header>

        {{-- Galat tidak berbidang merah: merah hanya berarti sudut pesilat. --}}
        <p x-show="galat" x-text="galat" x-cloak
           class="shrink-0 rounded-silat-kecil bg-silat-garis px-2.5 py-1 text-center text-[12px] font-medium text-silat-teks"></p>
        <p x-show="pesan && ! galat" x-text="pesan" x-cloak
           class="shrink-0 rounded-silat-kecil bg-silat-garis px-2.5 py-1 text-center text-[12px] text-silat-teks-kedua"></p>

        {{--
            Verifikasi MENGGANTIKAN tangga hukuman, tidak menumpang di bawahnya.
            Panel ini dirancang untuk 844x390 dan sudah penuh; menambah bagian
            baru berarti memaksa gulir di layar yang dipegang sambil berdiri.
        --}}
        <template x-if="verifikasiBerjalan || menyusunVerifikasi">
            <div class="flex min-h-0 flex-1 flex-col">
                <x-silat.verifikasi-wasit :tournament="$tournament" :match="$match" />
            </div>
        </template>

        <template x-if="! verifikasiBerjalan && ! menyusunVerifikasi">
          <div class="flex min-h-0 flex-1 flex-col">
        @resource(rk('hukuman', ResourceAction::Create))
            <div class="grid min-h-0 flex-1 grid-cols-[296px_1fr] gap-2.5"
                 x-data="{ sudut: 'red', hitungan: 1,
                           get sudutLabel() { return this.sudut === 'red' ? 'Sudut merah' : 'Sudut biru' },
                           get kunciSisi() { return this.sudut === 'red' ? 'merah' : 'biru' } }">

                {{-- Langkah 1 --}}
                <div class="flex min-h-0 flex-col gap-2">
                    <p class="silat-angka shrink-0 text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">1 · Pesilat mana</p>

                    {{-- Nama kelas ditulis UTUH, tidak dirangkai dari variabel:
                         Tailwind hanya menghasilkan kelas yang ditemukannya di
                         sumber. --}}
                    @foreach (['red' => ['merah', 'bg-silat-merah'], 'blue' => ['biru', 'bg-silat-biru']] as $kunci => [$nama, $bidang])
                        <button type="button" x-on:click="sudut = '{{ $kunci }}'"
                                x-bind:class="sudut === '{{ $kunci }}'
                                    ? '{{ $bidang }} ring-2 ring-white'
                                    : 'border-[1.5px] border-silat-tepi-petak'"
                                class="flex min-h-[var(--silat-sentuh-min)] shrink-0 flex-col items-start justify-center gap-0.5 rounded-silat px-4 text-left">
                            <span class="silat-angka text-[10px] tracking-[.14em] text-silat-teks uppercase opacity-85">Sudut {{ $nama }}</span>
                            <span class="truncate text-[16px] font-semibold text-silat-teks"
                                  x-text="(match.{{ $kunci }}?.athletes ?? []).join(', ') || '—'"></span>
                        </button>
                    @endforeach

                    {{--
                        Nilai mutlak jatuhan.

                        Berdiri di panel wasit, bukan panel juri: nilainya
                        mutlak dan sederajat dengan hukuman -- yang memutuskan
                        orang yang berdiri di gelanggang dan melihat jatuhnya,
                        bukan tiga juri yang dikonsensuskan.

                        Tidak melewati verifikasi. Wasit yang melihat jelas
                        menekan langsung; yang ragu bertanya ke juri lewat
                        tombol Verifikasi di kepala panel, lalu jawabannya
                        muncul di sini sebagai saran.

                        Arah nilainya disebut eksplisit -- "+3 UNTUK sudut
                        merah" -- karena sudut yang sama di kolom sebelah
                        berarti sebaliknya: yang DIHUKUM. Satu pemilih sudut
                        melayani dua aksi berlawanan arah, dan itu tempat salah
                        tekan lahir.
                    --}}
                    {{-- Blok php, bukan bentuk sebaris: @php() sebaris tidak mengenal
                         tanda kurung bersarang, dan pemanggilan berantai di dalamnya
                         dikompilasi jadi PHP yang menelan blok di bawahnya. --}}
                    @php
                        $nilaiJatuhan = $tournament->peraturan()->nilaiUntuk('jatuhan');
                    @endphp

                    <template x-if="saranJatuhan">
                        <p class="shrink-0 rounded-silat bg-silat-panel px-3 py-2 text-[12px] leading-snug text-silat-teks-kedua">
                            Jawaban juri:
                            <span class="font-semibold text-silat-teks" x-text="saranJatuhan.hasil_label"></span>.
                            Nilainya tetap kamu yang terbitkan.
                        </p>
                    </template>

                    <button type="button" x-on:click="terbitkanJatuhan(sudut)"
                            x-bind:disabled="! match.current_round"
                            x-bind:aria-label="'Jatuhan, tambah {{ $nilaiJatuhan }} nilai untuk ' + sudutLabel"
                            class="flex min-h-[var(--silat-sentuh-min)] shrink-0 items-center justify-between gap-3 rounded-silat bg-silat-aksi px-4 text-silat-aksi-teks disabled:opacity-40">
                        <span class="flex items-center gap-3">
                            <x-silat.ikon nama="jatuhan" :ukuran="26" :label="null" class="shrink-0" />
                            <span class="flex flex-col items-start">
                                <span class="text-[16px] leading-tight font-semibold">Jatuhan</span>
                                <span class="text-[11px] leading-tight opacity-80" x-text="'untuk ' + sudutLabel"></span>
                            </span>
                        </span>
                        <span class="silat-angka text-[18px] font-semibold">+{{ $nilaiJatuhan }}</span>
                    </button>

                    {{-- Hitungan teknik: juga ditekan sambil berdiri, jadi tingginya
                         mengikuti batas sentuh yang sama. --}}
                    <div class="mt-auto flex shrink-0 flex-col gap-1.5 rounded-silat bg-silat-panel p-2">
                        <div class="flex items-baseline justify-between">
                            <span class="text-[12px] font-medium text-silat-teks">Hitungan jatuh</span>
                            <span class="text-[11px] text-silat-teks-redup" x-text="sudutLabel"></span>
                        </div>
                        <div class="flex items-stretch gap-1.5">
                            <button type="button" x-on:click="hitungan = Math.max(1, hitungan - 1)"
                                    aria-label="Kurangi hitungan"
                                    class="silat-angka min-h-[var(--silat-sentuh-min)] w-[52px] rounded-silat-kecil border border-silat-tepi-petak text-[22px] text-silat-teks">−</button>
                            <div class="silat-angka flex min-h-[var(--silat-sentuh-min)] flex-1 items-center justify-center rounded-silat-kecil border border-silat-tepi-petak text-[26px] font-medium text-silat-teks"
                                 x-text="hitungan" aria-live="polite"></div>
                            <button type="button" x-on:click="hitungan = Math.min(10, hitungan + 1)"
                                    aria-label="Tambah hitungan"
                                    class="silat-angka min-h-[var(--silat-sentuh-min)] w-[52px] rounded-silat-kecil border border-silat-tepi-petak text-[22px] text-silat-teks">+</button>
                            <button type="button" x-on:click="kirimHitungan(sudut, hitungan)"
                                    class="min-h-[var(--silat-sentuh-min)] w-[88px] rounded-silat-kecil bg-silat-aksi text-[14.5px] font-semibold text-silat-aksi-teks">Catat</button>
                        </div>
                    </div>
                </div>

                {{-- Langkah 2 --}}
                <div class="flex min-h-0 flex-col gap-2">
                    <p class="silat-angka shrink-0 text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">
                        2 · Hukuman apa · <span class="text-silat-teks" x-text="sudutLabel"></span>
                    </p>

                    {{--
                        Kata yang dibaca lebih dulu adalah istilah naskah -- Pembinaan,
                        Teguran, Peringatan (Pasal 11.6.d.4) -- bukan ringan/sedang/berat.
                        Wasit menghafal peraturan dalam istilah itu, dan tiap tingkat punya
                        akibat angka yang berbeda; memaksanya menerjemahkan sendiri saat
                        pertandingan berjalan adalah tempat kesalahan lahir. Sebutan
                        sehari-hari tetap ditahan sebagai baris kedua.

                        Warna teks mengikuti latar tombolnya, bukan satu aturan seragam:
                        di atas oranye teguran (#d98324) teks putih hanya mencapai 2.75:1,
                        sedangkan teks gelap 6.37:1.

                        Tiap tombol menampilkan SISA JATAH tingkat itu dalam bentuk petak.
                        Wasit jadi melihat "peringatan tinggal satu petak lagi sebelum
                        diskualifikasi" SEBELUM menekan, bukan sesudahnya.
                    --}}
                    @php
                        $tingkatHukuman = [
                            ['kirim' => 'ringan', 'jenis' => 'pembinaan', 'resmi' => 'Pembinaan', 'sehari' => 'ringan',
                             'latar' => 'bg-silat-pembinaan', 'teks' => 'text-silat-teks', 'kedua' => 'text-white/80', 'nilai' => '0'],
                            ['kirim' => 'sedang', 'jenis' => 'teguran', 'resmi' => 'Teguran', 'sehari' => 'sedang',
                             'latar' => 'bg-silat-teguran', 'teks' => 'text-[color:var(--teguran-teks)]', 'kedua' => 'text-[color:var(--teguran-teks)]/75', 'nilai' => '−1 / −2'],
                            ['kirim' => 'berat', 'jenis' => 'peringatan', 'resmi' => 'Peringatan', 'sehari' => 'berat',
                             'latar' => 'bg-silat-peringatan', 'teks' => 'text-silat-peringatan-teks', 'kedua' => 'text-[color:var(--peringatan-teks)]/66', 'nilai' => '−5 / −10'],
                        ];
                    @endphp

                    @foreach ($tingkatHukuman as $tingkat)
                        @php($jatah = config("scoring.tanding.hukuman.{$tingkat['jenis']}.jumlah_kolom", 0))
                        <button type="button"
                                x-on:click="kirimHukuman(sudut, '{{ $tingkat['kirim'] }}', null)"
                                x-bind:aria-label="'{{ $tingkat['resmi'] }} untuk ' + sudutLabel"
                                class="flex min-h-[var(--silat-sentuh-min)] flex-1 items-center justify-between gap-4 rounded-silat {{ $tingkat['latar'] }} px-5 {{ $tingkat['teks'] }}">
                            <span class="flex items-center gap-4">
                                <x-silat.ikon :nama="$tingkat['jenis']" :ukuran="30" :label="null" class="shrink-0" />
                                <span class="flex flex-col items-start">
                                    <span class="text-[22px] leading-tight font-bold">{{ $tingkat['resmi'] }}</span>
                                    <span class="text-[12px] leading-tight {{ $tingkat['kedua'] }}">sebutan gelanggang: {{ $tingkat['sehari'] }}</span>
                                </span>
                            </span>

                            <span class="flex items-center gap-3">
                                <span class="flex gap-1" aria-hidden="true">
                                    @for ($i = 1; $i <= $jatah; $i++)
                                        <span class="size-5 rounded-silat-kecil border {{ $tingkat['jenis'] === 'peringatan' && $i === $jatah ? 'border-dashed' : '' }}"
                                              x-bind:class="(hukuman?.[kunciSisi]?.{{ $tingkat['jenis'] }} ?? 0) >= {{ $i }}
                                                  ? 'bg-current border-transparent'
                                                  : 'border-current opacity-60'"></span>
                                    @endfor
                                </span>
                                <span class="silat-angka text-[14px] font-medium">{{ $tingkat['nilai'] }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endresource
          </div>
        </template>
      </div>
    </div>
</x-layouts.silat>
