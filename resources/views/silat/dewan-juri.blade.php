@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Dewan Wasit Juri — '.$match->bracket->weightClass->name" :manifest="$manifestUrl ?? null">
    {{--
        Panel Dewan Wasit Juri — mengikuti `panel-dewan-wasit-juri.dc.html`.

        Dua kolom: riwayat mengisi kolom kiri selebar sisa layar, ringkasan dan
        pengesahan berdiri di kolom kanan selebar 420px yang menempel saat
        digulir. Panel ini dibuka justru ketika satu nilai disengketakan, dan
        yang dibaca adalah daftar panjang — kolom sempit membuat waktu,
        penekan, dan alasan pembatalan berdesakan di tempat yang sama.
    --}}
    <div x-data="partaiPanel(@js($config))" class="flex min-h-screen flex-col">
        <header class="flex shrink-0 items-center gap-5 border-b border-silat-garis px-6 py-4">
            <div class="min-w-0">
                {{-- Sebutan badan ini disatukan jadi "Dewan Wasit Juri" -- lihat
                     SilatRoleSeeder dan blok tanda tangan berita acara. Ditulis
                     huruf besar sungguhan di sini (bukan CSS `uppercase`), mengikuti
                     gaya eyebrow di `panel-dewan-wasit-juri.dc.html`. --}}
                <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar">
                    DEWAN WASIT JURI · {{ mb_strtoupper($match->arena?->name ?? 'GELANGGANG') }}
                </p>
                <p class="mt-1 truncate text-[18px] font-medium tracking-[-0.01em] text-silat-teks">
                    {{ $match->bracket->weightClass->jenis_kelamin->label() }}
                    {{ $match->bracket->weightClass->golongan_usia->label() }} —
                    <span x-text="[identitas.kelas, identitas.babak_bagan, 'Partai ' + (identitas.partai ?? '—')]
                        .filter(Boolean).join(' · ')"></span>
                </p>
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-2.5">
                @resource(rk('hasil-partai', ResourceAction::Print))
                    <a href="{{ route('admin.turnamen.partai.berita-acara', [$tournament, $match]) }}" target="_blank"
                       class="flex h-9.5 items-center rounded-silat border border-silat-tepi-kendali px-3.5 text-[13.5px] text-silat-teks-kedua">
                        Berita acara (PDF)
                    </a>
                @endresource

                <x-silat.indikator-koneksi />
            </div>
        </header>

        <template x-if="galat || pesan">
            <p class="mx-6 mt-4 shrink-0 rounded-silat bg-silat-panel px-4 py-2.5 text-[13.5px] text-silat-teks-kedua"
               x-text="galat || pesan"></p>
        </template>

        {{-- Kolom papan skor 420px baru dipasang mulai `lg`, sama seperti panel
             kendali dan papan. Tanpa penjaga itu satu-satunya nilai yang
             berlaku juga di layar 375px, dan halamannya tergulir ke samping
             sejauh 89px -- panel ini memang dibuka dari HP saat Dewan Wasit
             Juri memeriksa baris nilai di pinggir matras. --}}
        <div class="grid flex-1 grid-cols-1 items-start gap-5 px-6 pt-5 pb-7 *:min-w-0 lg:grid-cols-[1fr_420px]">

            {{-- Riwayat: koreksi lewat baris pembatal, bukan menyunting riwayat --}}
            <div class="flex min-w-0 flex-col gap-3.5"
                 x-data="{ dibatalkan: null, alasan: '' }">
                <div class="flex items-center gap-3.5">
                    <p class="text-[17px] font-semibold tracking-[-0.01em] text-silat-teks">Riwayat nilai &amp; hukuman</p>
                    <span class="silat-angka text-[11.5px] text-silat-teks-samar"
                          x-text="riwayat.length + ' baris · urut terbaru'"></span>
                </div>

                {{--
                    Setelah hasil disahkan, seluruh baris terkunci — dan
                    alasannya dinyatakan, bukan sekadar tombolnya dimatikan.
                    Tombol mati tanpa penjelasan sama membingungkannya dengan
                    tombol yang gagal diam-diam.
                --}}
                <div x-show="match.ratified" x-cloak
                     class="rounded-r-silat border-l-[3px] border-silat-tepi-petak bg-silat-panel px-4 py-3.5">
                    <p class="text-[14px] text-silat-teks">Hasil sudah disahkan, jadi riwayat ini terkunci.</p>
                    <p class="mt-1 text-[13px] text-silat-teks-redup">
                        Koreksi sesudah pengesahan hanya lewat protes manajer — Pasal 15 ayat 4.
                    </p>
                </div>

                <div class="overflow-hidden rounded-silat-besar border border-silat-garis">
                    <template x-if="riwayat.length === 0">
                        <p class="px-4 py-8 text-center text-[13.5px] text-silat-teks-redup">
                            Belum ada nilai atau hukuman tercatat.
                        </p>
                    </template>

                    <template x-for="(baris, i) in riwayat" :key="baris.tipe + '-' + baris.id">
                        <div class="flex items-center gap-3.5 px-4 py-3.5"
                             x-bind:class="i > 0 ? 'border-t border-silat-panel' : ''">
                            {{-- Sudut ditandai warna DAN kata: belasan baris di
                                 sini berbunyi hampir sama, dan mata memisahkan
                                 dua warna jauh lebih cepat daripada membaca
                                 kata pertama tiap baris. --}}
                            <span class="size-2.5 shrink-0 rounded-full"
                                  x-bind:class="baris.corner === 'red' ? 'bg-silat-merah' : 'bg-silat-biru'"></span>

                            <div class="min-w-0 flex-1">
                                <p class="text-[14px] text-silat-teks">
                                    <span x-text="baris.corner === 'red' ? 'Merah' : 'Biru'"></span>
                                    · Babak <span x-text="baris.round"></span>
                                    · <span x-text="baris.label"></span>
                                </p>
                                {{-- Waktu dan penekan bukan hiasan: tanpa keduanya
                                     belasan baris berbunyi persis sama, dan tidak ada
                                     cara menunjuk baris mana yang keliru. --}}
                                <p class="silat-angka mt-1 text-[11.5px] text-silat-teks-samar">
                                    <span x-text="new Date(baris.waktu).toLocaleTimeString('id-ID', { hour12: false })"></span>
                                    <template x-if="baris.oleh">
                                        <span> · <span x-text="baris.oleh"></span></span>
                                    </template>
                                </p>
                            </div>

                            <span class="silat-angka w-11 shrink-0 text-right text-[15px] font-medium text-silat-teks"
                                  x-text="baris.nilai ?? ''"></span>

                            @resource(rk('hasil-partai', ResourceAction::Update))
                                <button type="button"
                                        x-show="! match.ratified"
                                        x-on:click="dibatalkan = baris; alasan = ''"
                                        class="h-10 w-24 shrink-0 rounded-silat border border-silat-tepi-kendali text-[13px] font-medium text-silat-teks-kedua">
                                    Batalkan
                                </button>
                                <span x-show="match.ratified" x-cloak
                                      class="silat-angka w-24 shrink-0 text-right text-[11px] text-silat-teks-samar">Terkunci</span>
                            @endresource
                        </div>
                    </template>
                </div>

                {{--
                    LAJUR TEKANAN JURI.

                    Riwayat di atas cuma memperlihatkan nilai yang TERBIT.
                    Tekanan yang tidak cukup disepakati tidak meninggalkan jejak
                    di layar mana pun -- padahal itulah yang ditanyakan pelatih
                    saat memprotes: "juri saya menekan, kenapa tidak jadi
                    nilai?". Sebelum ada blok ini, satu-satunya jawabannya
                    adalah membuka basis data, di tengah tenggat protes lima
                    menit.

                    Tiga keadaan dibedakan dengan kata, bukan cuma warna:
                    tekanan yang ikut menerbitkan nilai, yang ditolak sistem,
                    dan yang berdiri SENDIRIAN -- sah, tercatat, tapi tidak
                    menemukan juri lain di dalam jendela kesepakatan. Yang
                    ketiga itulah jawaban yang selama ini tidak terlihat, dan
                    yang membuat juri-nya tidak terbaca sebagai lalai.
                --}}
                <template x-if="tekanan !== null && tekanan !== undefined">
                    <div class="flex flex-col gap-3.5">
                        <div class="flex items-center gap-3.5">
                            <p class="text-[17px] font-semibold tracking-[-0.01em] text-silat-teks">Tekanan juri</p>
                            <span class="silat-angka text-[11.5px] text-silat-teks-samar"
                                  x-text="tekanan.length + ' tekanan · urut terbaru'"></span>
                        </div>

                        <div class="overflow-hidden rounded-silat-besar border border-silat-garis">
                            <template x-if="tekanan.length === 0 && ! riwayatDipangkasPada">
                                <p class="px-4 py-8 text-center text-[13.5px] text-silat-teks-redup">
                                    Belum ada juri yang menekan.
                                </p>
                            </template>

                            {{-- Riwayat yang sudah dipangkas dinyatakan, bukan
                                 dibiarkan kosong: daftar kosong pada partai
                                 yang dipangkas terbaca sama persis dengan
                                 partai yang memang tidak pernah ditekan
                                 siapa pun -- dan yang kedua itu serius. --}}
                            <template x-if="tekanan.length === 0 && riwayatDipangkasPada">
                                <p class="px-4 py-8 text-center text-[13.5px] text-silat-teks-redup">
                                    Riwayat tekanan sudah dipangkas setelah arsip buktinya diterima.
                                    Rinciannya ada di paket arsip dan berita acara.
                                </p>
                            </template>

                            <template x-for="(t, i) in tekanan" :key="t.id">
                                <div class="flex items-center gap-3 px-4 py-2.5"
                                     x-bind:class="i > 0 ? 'border-t border-silat-panel' : ''">

                                    <span class="w-2 shrink-0 self-stretch rounded-full"
                                          x-bind:class="t.corner === 'red' ? 'bg-silat-merah' : 'bg-silat-biru'"
                                          role="img"
                                          x-bind:aria-label="t.corner === 'red' ? 'Sudut merah' : 'Sudut biru'"></span>

                                    <span class="silat-angka w-14 shrink-0 text-[11.5px] text-silat-teks-samar"
                                          x-text="'B' + t.round"></span>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[13.5px] text-silat-teks">
                                            <span x-text="t.juri ?? 'Juri tidak dikenal'"></span>
                                            <span class="text-silat-teks-samar"> · </span>
                                            <span x-text="t.teknik"></span>
                                        </p>
                                        <p class="silat-angka mt-0.5 text-[11px] text-silat-teks-samar"
                                           x-text="new Date(t.waktu).toLocaleTimeString('id-ID', { hour12: false }) + '.' + String(new Date(t.waktu).getMilliseconds()).padStart(3, '0')"></p>
                                    </div>

                                    {{-- Katanya yang membedakan, bukan warnanya
                                         saja: tiga keadaan yang akibatnya jauh
                                         berbeda tidak boleh dipisahkan hanya
                                         oleh rona yang berdekatan. --}}
                                    <span class="silat-angka w-24 shrink-0 rounded-silat-kecil px-2 py-1 text-center text-[11px]"
                                          x-bind:class="{
                                              'bg-silat-teks text-silat-panel': t.status === 'terbit',
                                              'border border-silat-tepi-petak text-silat-teks-kedua': t.status === 'sendirian',
                                              'border border-dashed border-silat-tepi-petak text-silat-teks-samar': t.status === 'ditolak',
                                          }"
                                          x-text="t.status === 'terbit' ? 'Jadi nilai' : (t.status === 'sendirian' ? 'Sendirian' : 'Ditolak')"
                                          x-bind:title="t.alasan_tolak ?? ''"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{--
                    Membatalkan nilai mengubah hasil resmi, jadi konfirmasinya
                    menyebut akibatnya dengan kalimat lengkap dan alasannya
                    wajib — keduanya ikut tercetak di berita acara. Server
                    menolak permintaannya juga setelah pengesahan, jadi layar
                    ini tidak berdiri sendiri sebagai penjaga.
                --}}
                <div x-show="dibatalkan" x-cloak x-on:keydown.escape.window="dibatalkan = null"
                     class="fixed inset-0 z-90 grid place-items-center bg-black/75 p-6">
                    <div class="w-full max-w-[520px] rounded-silat-besar border border-silat-garis bg-silat-panel p-6.5"
                         role="dialog" aria-modal="true">
                        <p class="text-[20px] font-semibold tracking-[-0.02em] text-silat-teks">
                            Batalkan <span x-text="dibatalkan?.label"></span>
                            sudut <span x-text="dibatalkan?.corner === 'red' ? 'merah' : 'biru'"></span>?
                        </p>
                        <p class="mt-3 text-[14px] leading-[1.7] text-silat-teks-redup">
                            Nilainya dicabut dan skor berubah seketika di seluruh papan — termasuk live score
                            publik dan overlay siaran. Riwayatnya tidak dihapus: barisnya tetap ada dan ditandai
                            dibatalkan. Kalau batal, tidak ada yang berubah.
                        </p>

                        <div class="mt-4.5">
                            <label for="alasan-pembatalan" class="mb-2 block text-[13.5px] font-medium text-silat-teks">
                                Alasan pembatalan
                                <span class="font-normal text-silat-teks-redup">— ikut tercetak di berita acara</span>
                            </label>
                            <textarea id="alasan-pembatalan" x-model="alasan" rows="3"
                                      placeholder="Contoh: Tendangan tidak sah, pesilat biru sudah keluar garis sebelum kontak."
                                      class="w-full resize-none rounded-silat border border-silat-tepi-kendali bg-silat-latar px-3 py-2.5 text-[14px] leading-[1.55] text-silat-teks placeholder:text-silat-teks-samar"></textarea>
                        </div>

                        <div class="mt-5.5 flex justify-end gap-2.5">
                            <button type="button" x-on:click="dibatalkan = null"
                                    class="h-13 rounded-silat border border-silat-tepi-kendali px-4.5 text-[15px] font-medium text-silat-teks-kedua">
                                Tidak jadi
                            </button>
                            <button type="button" x-bind:disabled="! alasan"
                                    x-on:click="(dibatalkan.tipe === 'nilai'
                                        ? batalkanNilai(dibatalkan.id, alasan)
                                        : batalkanHukuman(dibatalkan.id, alasan)); dibatalkan = null"
                                    class="h-13 rounded-silat bg-silat-aksi px-5 text-[15px] font-semibold text-silat-aksi-teks disabled:opacity-45">
                                Batalkan nilai
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sticky top-5 flex flex-col gap-3.5">
                <div class="grid grid-cols-2 gap-2.5">
                    <x-silat.papan-skor sudut="red" kunci-skor="merah" />
                    <x-silat.papan-skor sudut="blue" kunci-skor="biru" />
                </div>

                {{--
                    Papan hasil, di atas kartu pengesahan.

                    Peninjau butuh melihat rincian angka SEBELUM menekan
                    sahkan, bukan sesudah -- dan sampai sekarang satu-satunya
                    tempat rincian itu ada adalah berita acara PDF, yang baru
                    bisa dicetak setelah pengesahan.
                --}}
                <template x-if="sudahSelesai">
                    <x-silat.papan-hasil />
                </template>

                {{-- Pengesahan hasil --}}
                <div class="rounded-silat-besar border border-silat-garis p-4.5"
                     x-data="{ sebabLabel: @js(App\Support\Scoring\AlasanMenang::peta()) }">
                    <p class="text-[15px] font-semibold text-silat-teks"
                       x-text="match.ratified
                           ? 'Hasil sudah disahkan'
                           : (sudahSelesai ? 'Partai selesai — menunggu pengesahan' : 'Partai masih berjalan')"></p>

                    <p class="mt-2 text-[13.5px] leading-[1.65] text-silat-teks-redup">
                        <span x-show="sudahSelesai">
                            <span x-text="sebabLabel[match?.win_reason] ?? match?.win_reason"></span>,
                            sudut <span x-text="match.winner_registration_id === match.red?.registration_id ? 'merah' : 'biru'"></span> menang
                            (<span x-text="skorTotal.merah"></span>–<span x-text="skorTotal.biru"></span>).
                            <span x-show="! match.ratified">
                                Periksa riwayat di sebelah lebih dulu — setelah disahkan, tidak ada baris yang bisa dibatalkan lagi.
                            </span>
                            <span x-show="match.ratified">
                                Hasilnya sudah masuk ke bagan dan berita acaranya bisa diunduh.
                            </span>
                        </span>
                        <span x-show="! sudahSelesai">
                            Pengesahan hanya bisa dilakukan setelah partai diakhiri operator. Sampai itu terjadi,
                            kamu tetap bisa membatalkan nilai yang keliru.
                        </span>
                    </p>

                    @resource(rk('hasil-partai', ResourceAction::Approve))
                        <button type="button" x-show="sudahSelesai && ! match.ratified" x-cloak x-on:click="sahkan()"
                                class="mt-3.5 h-14 w-full rounded-silat bg-silat-aksi text-[15px] font-semibold text-silat-aksi-teks">
                            Sahkan hasil Partai <span x-text="identitas.partai ?? match?.id"></span>
                        </button>

                        <div x-show="match.ratified" x-cloak
                             class="mt-3.5 flex items-center gap-2.5 rounded-silat bg-silat-panel px-3.5 py-3">
                            <span class="size-2 shrink-0 rounded-full bg-silat-hidup"></span>
                            <p class="text-[13.5px] text-silat-teks-kedua">Hasil partai ini sudah disahkan.</p>
                        </div>
                    @endresource
                </div>

                {{--
                    Kartu protes yang sedang berjalan, di layar yang sama.

                    Pasal 15 ayat 3 huruf d menetapkan protes VAR diputus Wasit
                    Komisi Protes bersama Pengawas/Dewan Wasit Juri dan Wasit.
                    Selama blok ini hanya hidup di panel keberatan, yang duduk
                    di kursi Dewan Wasit Juri harus berpindah halaman untuk
                    membacanya -- sementara tenggat lima menitnya berjalan.
                --}}
                <x-silat.blok-keberatan />

                <div class="rounded-silat-besar border border-silat-garis p-4.5">
                    <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">Catatan pembatalan</p>
                    <p class="mt-2.5 text-[13.5px] leading-[1.7] text-silat-teks-redup">
                        Nilai tidak pernah dihapus dari riwayat. Membatalkan menambahkan baris pembatal berikut
                        alasannya, dan keduanya ikut tercetak di berita acara — jadi apa yang terjadi di gelanggang
                        tetap bisa dibaca ulang setahun kemudian.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.silat>
