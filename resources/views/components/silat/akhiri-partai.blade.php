@php use App\Enums\ResourceAction; @endphp

{{--
    Dialog "Akhiri partai", dipakai Panel Papan DAN Panel Kendali.

    Sebelumnya hanya ada di Panel Papan. Panduan operasional menaruh
    "mengakhiri partai (KO, WMP, mutlak, dst.)" di tangan Pengendali
    Gelanggang, di Panel Kendali -- dan pengendali yang mencarinya di sana
    tidak menemukan apa pun. Satu berkas dipakai dua panel supaya daftar
    alasan menang tidak pernah bercabang di antara keduanya.

    Bergantung pada state panel yang memuatnya: `sudahSelesai`, `match`,
    `skorTotal`, `identitas`, `tawaranWmp`, `tawaranSerentak`, dan `akhiri()`.
--}}
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
                                    {{-- `match?.` bukan `match.`: dialog ini ikut dirender di panel
                                         kendali, yang memang dibuka pada gelanggang yang belum
                                         dipilihkan partai. Alpine tetap mengevaluasi isi x-show
                                         yang bernilai salah, jadi tanda tanyanya bukan kehati-
                                         hatian berlebih -- tanpa itu tiap pembukaan gelanggang
                                         kosong melempar TypeError. --}}
                                    <span class="truncate" x-text="(match?.{{ $kunci }}?.athletes ?? []).join(', ') || 'Sudut {{ $nama }}'"></span>
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
