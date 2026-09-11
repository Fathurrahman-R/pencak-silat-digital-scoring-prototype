@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Operator — '.$match->bracket->weightClass->name" :manifest="$manifestUrl ?? null">
    {{--
        Panel operator.

        Dua blok sudut BERDAMPINGAN (merah kiri, biru kanan) mengisi kolom
        kiri, seluruh kendali berdiri di kolom kanan selebar 400px. Berdampingan
        kedua angka skor duduk pada garis mata yang sama, jadi perbandingannya
        selesai dalam satu pandang — bertumpuk, mata harus turun setengah layar
        dan angka yang barusan dibaca sudah hilang dari ingatan. Tidak ada satu
        pun kendali yang duduk di dalam bidang berwarna sehingga terbaca sebagai
        milik salah satu sudut.

        `h-dvh` dan `overflow-hidden`: di meja operator layar ini tidak pernah
        digulir. Kalau isinya tidak muat, itu cacat tata letak yang harus
        ketahuan.

        Di bawah `lg` kolom kendali turun ke bawah dan halaman boleh digulir;
        di bawah `sm` kedua blok sudut ikut bertumpuk. Kolom tetap selebar
        400px di layar 375px membuat kolom sudut menyusut sampai barisan ikon
        hukumannya menimpa tombol "Mulai babak" -- panel jadi tidak terbaca
        justru saat operator terpaksa memakai ponsel sebagai cadangan.
    --}}
    <div x-data="partaiPanel(@js($config))" class="flex min-h-dvh flex-col lg:h-dvh lg:overflow-hidden">
        {{-- Tanpa ini papan gelanggang hanya menampilkan jam yang berhenti, dan
             tidak seorang pun di gelanggang bisa tahu apakah itu cedera,
             protes, atau susulan yang sedang dicatat. --}}
        <x-silat.pita-susulan />

        @if ($match->arena_id === null)
            {{--
                Partai tanpa gelanggang tidak punya satu pun kanal siaran:
                skornya tersimpan, tapi panel juri, panel wasit, overlay vMix,
                dan halaman penonton tidak akan pernah menerimanya. Diam-diam
                itu terbaca sebagai perangkat lain yang rusak, jadi keadaannya
                dikatakan di muka.
            --}}
            <div class="flex shrink-0 items-center gap-2.5 border-b border-silat-garis bg-silat-panel px-7 py-3.5">
                <span class="size-2 shrink-0 rounded-full bg-silat-teks-redup"></span>
                <p class="text-[17px] leading-[1.5] text-silat-teks-kedua">
                    Partai ini belum ditempatkan di gelanggang. Nilai tetap tersimpan, tapi
                    <span class="text-silat-teks">panel lain, overlay, dan halaman penonton tidak menerima pembaruannya</span>
                    sampai partai dijadwalkan lewat menu Jadwal.
                </p>
            </div>
        @endif

        {{--
            Tiga lajur: MERAH · KENDALI · BIRU.

            Kolom kendali duduk di ANTARA kedua sudut, bukan di tepi kanan.
            Timer adalah satu-satunya hal yang dibaca bergantian dengan kedua
            angka skor, dan di tepi kanan ia berjarak selebar layar dari sudut
            merah -- mata operator menyeberangi seluruh papan tiap kali. Di
            tengah, ketiga hal yang diawasi berdiri berdekatan, dan lorong
            kendali sekaligus memisahkan dua bidang berwarna yang tanpa itu
            bersentuhan langsung.

            Sesudah hasil DISAHKAN, kedua blok sudut menghilang dan papan hasil
            mengambil tempatnya -- lajurnya kembali dua, dan kolom kendali
            berpindah ke kanan seperti sedia kala. Itulah gunanya jumlah kolom
            diikat ke `match.ratified`, bukan ditulis tetap.
        --}}
        <div class="grid min-h-0 flex-1 grid-cols-1"
             x-bind:class="match?.ratified
                 ? 'lg:grid-cols-[1fr_400px]'
                 : 'lg:grid-cols-[1fr_400px_1fr] lg:grid-rows-[minmax(0,1fr)_auto]'">
                {{--
                    Blok skor menyerahkan tempatnya begitu hasil DISAHKAN.

                    Selama partai berjalan dan selama hasilnya masih menunggu
                    Dewan Wasit Juri, dua blok inilah isi layar: angka besar
                    yang dibaca dari pinggir matras. Sesudah disahkan, angka
                    itu tidak berubah lagi dan tidak ada lagi yang perlu
                    diawasi sekilas -- yang dibutuhkan justru rinciannya: dari
                    mana angka akhir itu datang, babak mana yang menentukan.
                    Menaruh keduanya bertumpuk membuat papan hasil terdorong ke
                    bawah lipatan layar, dan operator harus menggulir untuk
                    melihat hal yang justru paling penting saat itu.
                --}}
            {{--
                Kedua sudut adalah lajur grid tersendiri, bukan satu kotak
                berisi dua. Di bawah `lg` grid ini runtuh jadi satu kolom dan
                urutan DOM-lah yang berlaku: merah, kendali, biru. Itu urutan
                yang benar untuk ponsel tegak -- kendali duduk di antara kedua
                sudut di sana juga, dalam jangkauan ibu jari.
            --}}
            <template x-if="! match?.ratified">
                <x-silat.blok-operator sudut="red" kunci-skor="merah"
                                       class="lg:col-start-1 lg:row-start-1" />
            </template>

            <aside class="flex min-h-0 flex-col border-t border-silat-garis bg-silat-latar lg:col-start-2 lg:row-start-1 lg:border-t-0 lg:border-x">
                {{-- Rata tengah, mengikuti timer dan tombol di bawahnya. Kolom
                     ini berdiri di tengah papan sekarang, dan satu blok yang
                     rata kiri di puncak kolom yang seluruh isinya rata tengah
                     terbaca sebagai bagian yang belum selesai dirapikan. --}}
                {{--
                    Dibaca dari tepi matras, bukan dari kursi di depan layar.

                    Ketiga baris ini sebelumnya 13-19px dengan warna paling
                    samar di seluruh palet -- ukuran surat, bukan ukuran papan.
                    Yang paling sering ditanyakan orang di sekitar meja adalah
                    "ini partai berapa" dan "kelas apa", dan keduanya justru
                    yang paling kecil. Kelas dinaikkan jadi 30px putih penuh,
                    nomor partai jadi 17px dengan warna kedua, dan babak bagan
                    -- yang menempel pada kelas -- ikut naik jadi 20px.
                --}}
                <div class="shrink-0 border-b border-silat-garis px-5 py-5 text-center">
                    <p class="silat-angka text-[17px] font-medium tracking-[.1em] text-silat-teks-kedua uppercase">
                        <span x-text="(identitas.gelanggang ?? 'Gelanggang') + ' · Partai ' + (identitas.partai ?? '—')"></span>
                    </p>
                    {{-- Dari state, bukan dari $match: panel gelanggang berpindah
                         partai tanpa memuat ulang halaman, dan apa pun yang
                         dicetak Blade akan membeku di partai yang ditinggalkan. --}}
                    <p class="mt-2 text-[30px] leading-[1.2] font-semibold tracking-[-0.02em] text-silat-teks"
                       x-text="[identitas.jenis_kelamin, identitas.golongan].filter(Boolean).join(' ') + ' — ' + (identitas.kelas ?? '—')"></p>
                    <p class="mt-1.5 text-[20px] leading-[1.3] font-medium text-silat-teks-kedua"
                       x-text="identitas.babak_bagan ?? '—'"></p>

                    <div class="mt-3 flex justify-center">
                        <x-silat.indikator-koneksi />
                    </div>
                </div>

                {{-- Timer mengisi sisa tinggi kolom di layar lebar, jadi ia duduk
                     di tengah optik. Di ponsel ketiga lajur bertumpuk, dan kolom
                     kendali yang ikut memanjang mendorong sudut biru ke bawah
                     lipatan -- di sana tingginya mengikuti isinya saja. --}}
                <div class="flex min-h-0 flex-col items-center justify-center gap-4 p-5 lg:flex-1">
                    {{-- Petak babak sengaja sebesar tombol: dari meja operator
                         ia dibaca sambil mengawasi matras, bukan ditatap. --}}
                    <div class="flex gap-2.5">
                        <template x-for="i in peraturan.jumlah_babak" :key="i">
                            <span class="silat-angka grid h-14 w-16 place-items-center rounded-silat-kecil text-[22px] tracking-[.04em]"
                                  x-bind:class="i === match.current_round
                                      ? 'bg-silat-teks font-semibold text-silat-latar'
                                      : (i < (match.current_round ?? 1)
                                          ? 'bg-silat-garis text-silat-teks-redup'
                                          : 'border border-silat-tepi-kendali text-silat-teks-samar')"
                                  x-text="'B' + i"></span>
                        </template>
                    </div>

                    {{-- Jam yang habis TIDAK digambar seperti jam yang
                         berjalan: warnanya berubah jadi warna teguran, supaya
                         yang melirik dari tepi matras melihat keadaannya tanpa
                         membaca satu huruf pun. --}}
                    <p class="silat-angka text-[104px] leading-none font-medium"
                       x-bind:class="waktuHabis
                           ? 'text-silat-teguran'
                           : (babakAktif?.status === 'berjalan' ? 'text-silat-teks' : 'text-silat-teks-redup')"
                       x-text="tampilWaktu" role="timer" aria-live="off">00:00</p>

                    {{-- Keadaan babak ikut dibesarkan: ia yang membedakan jam
                         yang berhenti karena dijeda dari jam yang berhenti
                         karena waktunya habis. --}}
                    <p class="silat-angka text-[20px] font-medium tracking-[.1em] text-silat-teks-kedua uppercase"
                       x-text="sudahSelesai
                           ? 'Partai selesai'
                           : (waktuHabis
                               ? 'Babak selesai'
                               : ({ berjalan: 'Berjalan', jeda: 'Dijeda', belum_mulai: 'Belum dimulai', selesai: 'Babak selesai' }[babakAktif?.status] ?? 'Belum dimulai'))"></p>
                </div>

                <div class="flex shrink-0 flex-col gap-2 border-t border-silat-garis p-5">
                    @resource(rk('partai', ResourceAction::Update))
                        {{--
                            Tidak ada tombol kendali yang boleh berwarna merah
                            atau biru: kedua warna itu sudah berarti identitas
                            sudut, dan tombol "Mulai babak" berwarna sudut
                            membuat operator sepersekian detik mengira aksinya
                            berhubungan dengan pesilat merah.

                            Tinggi 64px mengikuti batas sentuh gelanggang:
                            ditekan sambil mengawasi matras, bukan layar.
                        --}}
                        <button type="button" x-show="babakUntukDimulai !== null && ! susulanTerbuka" x-on:click="mulaiBabak()"
                                class="h-16 rounded-silat bg-silat-aksi text-[20px] font-semibold text-silat-aksi-teks">
                            {{-- Angka babak dirangkai hanya kalau memang ada. Pada babak terakhir
                                 `babakUntukDimulai` bernilai null, dan tombol yang tersembunyi ini
                                 tetap berlabel "Mulai babak null" di dalam DOM. --}}
                            <span x-text="babakUntukDimulai === null
                                ? 'Mulai babak'
                                : (babakAktif?.status === 'belum_mulai' ? 'Mulai ulang babak ' : 'Mulai babak ') + babakUntukDimulai"></span>
                        </button>
                        <button type="button" x-show="babakAktif?.status === 'jeda' && ! susulanTerbuka && ! sudahSelesai" x-on:click="lanjutkan()"
                                class="h-16 rounded-silat bg-silat-aksi text-[20px] font-semibold text-silat-aksi-teks">Lanjutkan</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan' && ! sudahSelesai" x-on:click="jeda()"
                                class="h-16 rounded-silat border border-silat-tepi-kendali text-[20px] font-semibold text-silat-teks-kedua">Jeda</button>
                        <button type="button" x-show="(babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda') && ! sudahSelesai" x-on:click="selesaikanBabak()"
                                class="h-16 rounded-silat border border-silat-tepi-kendali text-[20px] font-semibold text-silat-teks-kedua">Selesaikan babak</button>
                        <button type="button" x-show="(babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda') && ! sudahSelesai" x-on:click="resetBabak()"
                                class="h-14 rounded-silat border border-silat-tepi-kendali text-[17px] font-medium text-silat-teks-redup">Reset babak</button>
                    @endresource

                    <x-silat.akhiri-partai />
                </div>
            </aside>

            <template x-if="! match?.ratified">
                <x-silat.blok-operator sudut="blue" kunci-skor="biru"
                                       class="lg:col-start-3 lg:row-start-1" />
            </template>

            {{--
                Papan hasil. Muncul hanya sesudah partai selesai, sebagai
                lanjutan pita di atasnya: pita menyebut SEBAB kemenangan
                dalam satu baris, papan ini menjelaskan dari mana angkanya
                datang -- skor tiap babak dan rincian tiap teknik.

                Digulir sendiri. Layar operator sengaja tidak pernah
                digulir seluruhnya (lihat `overflow-hidden` di atas), tapi
                papan ini muncul justru saat tidak ada lagi yang harus
                ditekan cepat, jadi menggulir isinya tidak menghalangi
                apa pun -- sementara membiarkannya mendorong kolom timer
                ke luar layar akan menghalangi banyak.
            --}}
            <template x-if="sudahSelesai">
                <div class="min-h-0 overflow-y-auto border-t border-silat-garis"
                     {{-- Disahkan: papan ini MENGGANTIKAN kedua blok sudut, jadi ia
                          mengambil lajur pertama dan tinggi penuh. Belum
                          disahkan: ia sebaris penuh di bawah ketiga lajur,
                          selebar papan -- rincian per babak butuh lebar, dan
                          saat itu tidak ada lagi yang harus ditekan cepat. --}}
                     x-bind:class="match?.ratified
                         ? 'p-0 lg:col-start-1 lg:row-start-1 flex-1'
                         : 'p-5 lg:col-span-3 lg:row-start-2 max-h-[38vh]'">
                    {{-- Sesudah disahkan papan ini MENGGANTIKAN blok skor,
                         jadi ia mengisi kolomnya penuh tanpa sela dan
                         tanpa sudut membulat -- bukan kartu yang mengambang
                         di tengah bidang kosong. --}}
                    <x-silat.papan-hasil x-bind:class="match?.ratified ? 'min-h-full rounded-none' : ''" />
                </div>
            </template>
        </div>

        {{-- Verifikasi juri — Pasal 13. Berdiri di ATAS pita keadaan
             karena selama ia berjalan pertandingan berhenti, dan yang
             ditunggu seluruh gelanggang cuma satu: berapa juri sudah
             menjawab, dan apa hasilnya. --}}
        <x-silat.verifikasi-operator />

        {{-- Hasil yang SUDAH diterapkan Wasit, di atas panel skor. Ia mengambil
             layar karena pertandingan memang sedang berhenti untuknya, dan
             karena itulah satu-satunya hal yang perlu dibaca seluruh meja pada
             detik itu. --}}
        <x-silat.verifikasi-hasil />

        {{--
            Pita keadaan. Satu tempat untuk semua kabar panel ini —
            galat, pesan, tawaran WMP, dan hasil partai — supaya
            operator tahu satu baris mana yang harus dibaca, dan tinggi
            layar tidak berubah tiap kali salah satunya muncul.

            Tidak berbidang merah maupun oranye: warna di panel ini
            sudah dipakai habis oleh identitas sudut.
        --}}
        <template x-if="galat || pesan || tawaranWmp || tawaranSerentak || sudahSelesai">
            <div class="flex shrink-0 items-center gap-2.5 border-t border-silat-garis bg-silat-panel px-7 py-3.5">
                <span class="size-2 shrink-0 rounded-full bg-silat-teks-redup"></span>
                <p class="text-[17px] leading-[1.5] text-silat-teks-kedua">
                    <span x-show="galat" x-text="galat"></span>
                    <span x-show="pesan && ! galat" x-text="pesan"></span>
                    <span x-show="tawaranWmp && ! galat && ! pesan && ! tawaranSerentak">
                        Sudut <span x-text="tawaranWmp === 'red' ? 'merah' : 'biru'" class="font-medium text-silat-teks"></span>
                        unggul cukup jauh untuk Menang WMP — pilih “Akhiri partai” bila ingin menetapkannya.
                    </span>

                    {{--
                        Kedua pesilat sama-sama tidak bangkit sampai
                        hitungan ke-10 — Pasal 11.6.c huruf b dan c.

                        Berdiri di DEPAN tawaran WMP karena keadaannya
                        lebih genting dan penyelesaiannya berbeda:
                        naskah menetapkan berat badan teringan bila
                        keduanya belum bernilai di babak I, dan nilai
                        terbanyak bila sudah. Sistem menghitung, aparat
                        yang menekan — persis pola tawaran WMP.
                    --}}
                    <span x-show="tawaranSerentak && ! galat && ! pesan">
                        Kedua pesilat tidak bangkit sampai hitungan ke-10.
                        <span x-show="tawaranSerentak?.sebab === 'berat_badan_teringan'">
                            Belum ada nilai di babak I — pemenangnya berat badan teringan.
                        </span>
                        <span x-show="tawaranSerentak?.sebab === 'nilai_terbanyak'">
                            Sudah ada nilai — pemenangnya nilai terbanyak.
                        </span>
                        <span x-show="tawaranSerentak?.pemenang">
                            Sudut <span class="font-medium text-silat-teks"
                                        x-text="tawaranSerentak?.pemenang === 'red' ? 'merah' : 'biru'"></span>.
                        </span>
                        <span x-show="! tawaranSerentak?.pemenang" class="text-silat-teks">
                            Angkanya sama — aparat yang menetapkan sudutnya.
                        </span>
                    </span>

                    <span x-show="sudahSelesai && ! galat && ! pesan && ! tawaranWmp && ! tawaranSerentak"
                          x-data="{ sebabLabel: @js(App\Support\Scoring\AlasanMenang::peta()) }">
                        Partai selesai — <span x-text="sebabLabel[match?.win_reason] ?? match?.win_reason" class="text-silat-teks"></span>.
                        <span x-show="match.ratified">Sudah disahkan Dewan Wasit Juri.</span>
                        <span x-show="! match.ratified">Menunggu pengesahan Dewan Wasit Juri.</span>
                    </span>
                </p>
            </div>
        </template>

    </div>
</x-layouts.silat>
