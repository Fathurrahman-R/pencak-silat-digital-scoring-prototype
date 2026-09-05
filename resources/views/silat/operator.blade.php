@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Operator — '.$match->bracket->weightClass->name" :manifest="$manifestUrl ?? null">
    {{--
        Panel operator — mengikuti `operator-b-eksperimen.dc.html`.

        Dua blok sudut BERTUMPUK (merah atas, biru bawah) mengisi kolom kiri,
        seluruh kendali berdiri di kolom kanan selebar 352px. Susunan ini
        membuat skor, hukuman, dan indikator juri kedua sudut sekolom dan bisa
        dibandingkan langsung, dan tidak ada satu pun kendali yang duduk di
        dalam bidang berwarna sehingga terbaca sebagai milik salah satu sudut.

        `h-dvh` dan `overflow-hidden`: di meja operator layar ini tidak pernah
        digulir. Kalau isinya tidak muat, itu cacat tata letak yang harus
        ketahuan.

        Di bawah `lg` susunannya ditumpuk dan boleh digulir. Dua kolom tetap
        selebar 352px di layar 375px membuat kolom sudut menyusut sampai
        barisan ikon hukumannya menimpa tombol "Mulai babak" -- panel jadi
        tidak terbaca justru saat operator terpaksa memakai ponsel sebagai
        cadangan.
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
                <p class="text-[14px] leading-[1.5] text-silat-teks-kedua">
                    Partai ini belum ditempatkan di gelanggang. Nilai tetap tersimpan, tapi
                    <span class="text-silat-teks">panel lain, overlay, dan halaman penonton tidak menerima pembaruannya</span>
                    sampai partai dijadwalkan lewat menu Jadwal.
                </p>
            </div>
        @endif

        <div class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[1fr_352px]">

            {{-- `min-w-0`: kolom `1fr` tidak boleh menyusut di bawah lebar
                 min-content isinya kecuali diberi izin, dan isi kolom ini
                 adalah nama pesilat berukuran 60px. Tanpa ini, di layar pendek
                 dan lebar (HP dipegang miring) kolom kiri memaksa lebarnya
                 sendiri dan mendorong seluruh kolom kendali ke luar layar --
                 tombol "Mulai" dan "Akhiri partai" ikut terpotong. --}}
            <div class="flex min-h-0 min-w-0 flex-col">
                <x-silat.blok-operator sudut="red" kunci-skor="merah" />
                <x-silat.blok-operator sudut="blue" kunci-skor="biru" />

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
                        <p class="text-[14px] leading-[1.5] text-silat-teks-kedua">
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
                    <div class="min-h-0 flex-1 overflow-y-auto border-t border-silat-garis p-5">
                        <x-silat.papan-hasil />
                    </div>
                </template>
            </div>

            <aside class="flex min-h-0 flex-col border-t border-silat-garis bg-silat-latar lg:border-t-0 lg:border-l">
                <div class="shrink-0 border-b border-silat-garis px-5 py-4.5">
                    <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">
                        <span x-text="(identitas.gelanggang ?? 'Gelanggang') + ' · Partai ' + (identitas.partai ?? '—')"></span>
                    </p>
                    {{-- Dari state, bukan dari $match: panel gelanggang berpindah
                         partai tanpa memuat ulang halaman, dan apa pun yang
                         dicetak Blade akan membeku di partai yang ditinggalkan. --}}
                    <p class="mt-1.5 text-[15px] leading-[1.4] font-medium text-silat-teks"
                       x-text="[identitas.jenis_kelamin, identitas.golongan].filter(Boolean).join(' ') + ' — ' + (identitas.kelas ?? '—')"></p>
                    <p class="mt-1 text-[13.5px] text-silat-teks-redup" x-text="identitas.babak_bagan ?? '—'"></p>

                    <div class="mt-3">
                        <x-silat.indikator-koneksi />
                    </div>
                </div>

                {{-- Timer mengisi sisa tinggi kolom, jadi ia duduk di tengah optik. --}}
                <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-4 p-5">
                    <div class="flex gap-1.5">
                        <template x-for="i in peraturan.jumlah_babak" :key="i">
                            <span class="silat-angka rounded-silat-kecil px-2.5 py-1.5 text-[11px] tracking-[.06em]"
                                  x-bind:class="i === match.current_round
                                      ? 'bg-silat-teks font-semibold text-silat-latar'
                                      : (i < (match.current_round ?? 1)
                                          ? 'bg-silat-garis text-silat-teks-redup'
                                          : 'border border-silat-tepi-kendali text-silat-teks-samar')"
                                  x-text="'B' + i"></span>
                        </template>
                    </div>

                    <p class="silat-angka text-[84px] leading-none font-medium text-silat-teks"
                       x-bind:class="babakAktif?.status === 'berjalan' ? 'text-silat-teks' : 'text-silat-teks-redup'"
                       x-text="tampilWaktu" role="timer" aria-live="off">00:00</p>

                    <p class="silat-angka text-[11.5px] tracking-[.12em] text-silat-teks-samar uppercase"
                       x-text="sudahSelesai
                           ? 'Partai selesai'
                           : ({ berjalan: 'Berjalan', jeda: 'Dijeda', belum_mulai: 'Belum dimulai', selesai: 'Babak selesai' }[babakAktif?.status] ?? 'Belum dimulai')"></p>
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
                                class="h-[var(--silat-sentuh-min)] rounded-silat bg-silat-aksi text-[16px] font-semibold text-silat-aksi-teks">
                            <span x-text="(babakAktif?.status === 'belum_mulai' ? 'Mulai ulang babak ' : 'Mulai babak ') + babakUntukDimulai"></span>
                        </button>
                        <button type="button" x-show="babakAktif?.status === 'jeda' && ! susulanTerbuka" x-on:click="lanjutkan()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat bg-silat-aksi text-[16px] font-semibold text-silat-aksi-teks">Lanjutkan</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan'" x-on:click="jeda()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali text-[16px] font-semibold text-silat-teks-kedua">Jeda</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda'" x-on:click="selesaikanBabak()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali text-[16px] font-semibold text-silat-teks-kedua">Selesaikan babak</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda'" x-on:click="resetBabak()"
                                class="h-13 rounded-silat border border-silat-tepi-kendali text-[14.5px] font-medium text-silat-teks-redup">Reset babak</button>
                    @endresource

                    @resource(rk('partai', ResourceAction::Manage))
                        <div class="mt-1 flex gap-2" x-data="{ dialog: false, corner: 'red', sebab: 'angka' }">
                            {{--
                                Dialog dibuka SUDAH terisi bila sistem sedang
                                menawarkan penyelesaian: yang menekan tinggal
                                membaca dan menyetujui, bukan menerjemahkan
                                sendiri pita di atas jadi dua pilihan di bawah.
                                Keduanya tetap bisa diubah — tawaran, bukan
                                keputusan.
                            --}}
                            <button type="button" x-show="! sudahSelesai"
                                    x-on:click="
                                        if (tawaranSerentak) {
                                            sebab = tawaranSerentak.sebab;
                                            corner = tawaranSerentak.pemenang ?? corner;
                                        } else if (tawaranWmp) {
                                            sebab = 'wmp';
                                            corner = tawaranWmp;
                                        }
                                        dialog = true;
                                    "
                                    class="h-13 flex-1 rounded-silat border border-silat-tepi-kendali text-[14.5px] font-medium text-silat-teks-kedua">
                                Akhiri partai
                            </button>

                            {{--
                                Dialog konfirmasi menyebut akibatnya dengan
                                kalimat lengkap, termasuk apa yang jadi tidak
                                bisa diubah dan apa yang terjadi kalau batal.
                                Batal berdiri di kiri.
                            --}}
                            <div x-show="dialog" x-cloak x-on:keydown.escape.window="dialog = false"
                                 class="fixed inset-0 z-90 grid place-items-center bg-black/75 p-6">
                                <div class="w-full max-w-[560px] rounded-silat-besar border border-silat-garis bg-silat-panel p-6.5"
                                     role="dialog" aria-modal="true" aria-labelledby="judul-akhiri">
                                    <p id="judul-akhiri" class="text-[21px] font-semibold tracking-[-0.02em] text-silat-teks">
                                        Akhiri Partai <span x-text="identitas.partai ?? match?.id"></span>?
                                    </p>
                                    <p class="mt-3 text-[14.5px] leading-[1.7] text-silat-teks-redup">
                                        Setelah partai diakhiri, nilai dan hukuman tidak bisa diubah lagi kecuali lewat
                                        pembatalan Dewan Wasit Juri, dan hasilnya diteruskan ke bagan. Kalau batal, tidak
                                        ada yang berubah.
                                    </p>

                                    <div class="mt-5.5 grid gap-4">
                                        <div>
                                            <p class="mb-2 text-[13.5px] font-medium text-silat-teks-kedua">Pemenang</p>
                                            <div class="grid gap-2.5">
                                                @php
                                                    // Nama kelas ditulis UTUH, tidak dirangkai dari variabel:
                                                    // Tailwind hanya menghasilkan kelas yang ditemukannya di
                                                    // sumber, dan kelas yang dirangkai saat render tidak pernah
                                                    // punya aturan CSS sama sekali.
                                                    $pilihanPemenang = [
                                                        ['red', 'merah', 'merah', 'bg-silat-merah-dalam', 'bg-silat-merah'],
                                                        ['blue', 'biru', 'biru', 'bg-silat-biru-dalam', 'bg-silat-biru'],
                                                    ];
                                                @endphp
                                                @foreach ($pilihanPemenang as [$kunci, $nama, $kunciSkor, $bidang, $titik])
                                                    <button type="button" x-on:click="corner = '{{ $kunci }}'"
                                                            x-bind:class="corner === '{{ $kunci }}'
                                                                ? 'border-2 border-silat-teks {{ $bidang }} text-silat-teks'
                                                                : 'border border-silat-tepi-kendali text-silat-teks-kedua'"
                                                            class="flex h-14 items-center gap-2.5 rounded-silat px-3.5 text-left text-[15px] font-medium">
                                                        <span class="{{ $titik }} size-2.5 shrink-0 rounded-full"></span>
                                                        <span class="truncate" x-text="(match.{{ $kunci }}?.athletes ?? []).join(', ') || 'Sudut {{ $nama }}'"></span>
                                                        <span class="silat-angka ml-auto text-[17px]" x-text="skorTotal.{{ $kunciSkor }}"></span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div>
                                            <p class="mb-2 text-[13.5px] font-medium text-silat-teks-kedua">Alasan menang</p>
                                            <select x-model="sebab" aria-label="Alasan menang"
                                                    class="h-11 w-full rounded-silat border border-silat-tepi-kendali bg-silat-latar px-3 text-[14px] text-silat-teks">
                                                <option value="angka">Menang angka</option>
                                                <option value="teknik">Menang teknik</option>
                                                <option value="mutlak">Menang mutlak</option>
                                                <option value="wmp">Menang WMP</option>
                                                <option value="undur_diri">Menang undur diri</option>
                                                <option value="cedera">Menang karena lawan cedera</option>
                                                <option value="wo">Menang WO</option>
                                                {{--
                                                    Dua penyelesaian saat KEDUA pesilat tidak
                                                    bangkit (Pasal 11.6.c huruf b dan c). Tanpa
                                                    baris ini keadaan itu tidak punya alasan
                                                    menang yang bisa dipilih sama sekali,
                                                    sekalipun servernya menerimanya.
                                                --}}
                                                <option value="berat_badan_teringan">Menang berat badan teringan</option>
                                                <option value="nilai_terbanyak">Menang nilai terbanyak</option>
                                            </select>
                                            <p class="mt-2 text-[13px] leading-[1.55] text-silat-teks-samar">
                                                Pilih alasan yang diputuskan wasit. Yang tercatat di berita acara adalah
                                                yang dipilih di sini, bukan yang disimpulkan dari skor.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-6 flex justify-end gap-2.5">
                                        <button type="button" x-on:click="dialog = false"
                                                class="h-13 rounded-silat border border-silat-tepi-kendali px-4.5 text-[15px] font-medium text-silat-teks-kedua">
                                            Tidak jadi
                                        </button>
                                        <button type="button" x-on:click="akhiri(corner, sebab); dialog = false"
                                                class="h-13 rounded-silat bg-silat-aksi px-5 text-[15px] font-semibold text-silat-aksi-teks">
                                            Akhiri partai
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endresource
                </div>
            </aside>
        </div>
    </div>
</x-layouts.silat>
