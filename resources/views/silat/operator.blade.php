@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Operator — '.$match->bracket->weightClass->name">
    {{--
        Panel operator — mengikuti `operator-b-eksperimen.dc.html`.

        Dua blok sudut BERTUMPUK (merah atas, biru bawah) mengisi kolom kiri,
        seluruh kendali berdiri di kolom kanan selebar 352px. Susunan ini
        membuat skor, hukuman, dan indikator juri kedua sudut sekolom dan bisa
        dibandingkan langsung, dan tidak ada satu pun kendali yang duduk di
        dalam bidang berwarna sehingga terbaca sebagai milik salah satu sudut.

        `h-dvh` dan `overflow-hidden`: layar ini tidak pernah digulir. Kalau
        isinya tidak muat, itu cacat tata letak yang harus ketahuan.
    --}}
    <div x-data="partaiPanel(@js($config))" class="flex h-dvh flex-col overflow-hidden">
        <div class="grid min-h-0 flex-1 grid-cols-[1fr_352px]">

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
                <template x-if="galat || pesan || tawaranWmp || sudahSelesai">
                    <div class="flex shrink-0 items-center gap-2.5 border-t border-silat-garis bg-silat-panel px-7 py-3.5">
                        <span class="size-2 shrink-0 rounded-full bg-silat-teks-redup"></span>
                        <p class="text-[14px] leading-[1.5] text-silat-teks-kedua">
                            <span x-show="galat" x-text="galat"></span>
                            <span x-show="pesan && ! galat" x-text="pesan"></span>
                            <span x-show="tawaranWmp && ! galat && ! pesan">
                                Sudut <span x-text="tawaranWmp === 'red' ? 'merah' : 'biru'" class="font-medium text-silat-teks"></span>
                                unggul cukup jauh untuk Menang WMP — pilih “Akhiri partai” bila ingin menetapkannya.
                            </span>
                            <span x-show="sudahSelesai && ! galat && ! pesan && ! tawaranWmp"
                                  x-data="{ sebabLabel: @js(App\Support\Scoring\AlasanMenang::peta()) }">
                                Partai selesai — <span x-text="sebabLabel[match?.win_reason] ?? match?.win_reason" class="text-silat-teks"></span>.
                                <span x-show="match.ratified">Sudah disahkan Dewan Wasit Juri.</span>
                                <span x-show="! match.ratified">Menunggu pengesahan Dewan Wasit Juri.</span>
                            </span>
                        </p>
                    </div>
                </template>
            </div>

            <aside class="flex min-h-0 flex-col border-l border-silat-garis bg-silat-latar">
                <div class="shrink-0 border-b border-silat-garis px-5 py-4.5">
                    <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">
                        {{ $match->arena?->name ?? 'Gelanggang' }} · Partai {{ $match->id }}
                    </p>
                    <p class="mt-1.5 text-[15px] leading-[1.4] font-medium text-silat-teks">
                        {{ $match->bracket->weightClass->jenis_kelamin->label() }}
                        {{ $match->bracket->weightClass->golongan_usia->label() }} —
                        {{ $match->bracket->weightClass->name }}
                    </p>
                    <p class="mt-1 text-[13.5px] text-silat-teks-redup">{{ $match->bracket->namaBabak($match->round) }}</p>

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
                        <button type="button" x-show="babakUntukDimulai !== null" x-on:click="mulaiBabak()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat bg-silat-aksi text-[16px] font-semibold text-silat-aksi-teks">
                            <span x-text="(babakAktif?.status === 'belum_mulai' ? 'Mulai ulang babak ' : 'Mulai babak ') + babakUntukDimulai"></span>
                        </button>
                        <button type="button" x-show="babakAktif?.status === 'jeda'" x-on:click="lanjutkan()"
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
                            <button type="button" x-show="! sudahSelesai" x-on:click="dialog = true"
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
                                        Akhiri Partai {{ $match->id }}?
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
