@props([
    // Ekspresi Alpine yang menghasilkan muatan PerbandinganBattle.
    'sumber' => 'komparasi',

    // Menggambar pilihan pemenang milik Ketua Pertandingan saat skornya seri.
    'keputusan' => false,
])

{{--
    Komparasi nilai kedua sudut satu battle Jurus.

    SATU komponen untuk empat permukaan -- papan gelanggang, panel juri, panel
    ketua, dan panel operator -- karena keempatnya menjawab pertanyaan yang
    sama dan harus menjawabnya dengan angka yang sama. Empat salinan markup
    berarti empat tempat yang harus diperbaiki saat susunan angkanya bergeser,
    dan yang keempat akan tertinggal: pelatih yang membandingkan layar papan
    dengan layar ketua lalu menemukan dua angka berbeda punya alasan sah untuk
    tidak percaya pada keduanya.

    Digambar hanya ketika `siap` -- kedua penampilan sudah DISAHKAN. Sebelum
    itu angkanya masih bisa berubah: pengurangan Pengawas dijatuhkan sesudah
    penampilan berhenti, dan pembatalannya mengubah angka lagi. Perbandingan
    yang muncul lebih awal akan berganti angka di depan penonton.

    Merah kiri, biru kanan -- mengikuti sisi gelanggang, bukan urutan tampil.
    Sudut biru memang tampil lebih dulu (Pasal 12.1.d.7), tapi menukar
    posisinya di layar akan membuat pembacanya salah mengira siapa berdiri di
    mana. Arti yang sama sudah dipakai papan skor, overlay, dan bagan.
--}}

<div x-data="{ get k() { return {{ $sumber }} ?? null } }" x-show="k?.siap" x-cloak
     {{ $attributes->merge(['class' => 'rounded-silat-besar border border-silat-garis p-4']) }}>

    <div class="flex items-baseline justify-between gap-3 pb-3">
        <p class="silat-angka text-[10px] tracking-[.14em] text-silat-teks-redup uppercase">
            Perbandingan nilai
        </p>
        <p class="text-[13px] text-silat-teks-kedua">
            Selisih
            <span class="silat-angka font-medium text-silat-teks" x-text="(k?.selisih ?? 0).toFixed(2)"></span>
        </p>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        @foreach ([['merah', 'Sudut merah'], ['biru', 'Sudut biru']] as [$kunci, $judul])
            <div class="rounded-silat border p-4"
                 x-bind:class="k?.battle?.winner_registration_id
                     && k.{{ $kunci }}
                     && k.battle.winner_registration_id === k.{{ $kunci }}.registration_id
                     ? '{{ $kunci === 'merah' ? 'border-silat-merah bg-silat-merah-dalam' : 'border-silat-biru bg-silat-biru-dalam' }}'
                     : 'border-silat-garis'">

                <template x-if="k?.{{ $kunci }}">
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="silat-angka text-[10px] tracking-[.14em] uppercase
                                      {{ $kunci === 'merah' ? 'text-silat-teks-merah-samar' : 'text-silat-teks-biru-samar' }}">
                                {{ $judul }}
                            </p>
                            <template x-if="k.battle?.winner_registration_id === k.{{ $kunci }}.registration_id">
                                <span class="silat-angka rounded-silat-kecil bg-silat-teks px-2 py-1 text-[10px] font-semibold text-silat-latar">
                                    MENANG
                                </span>
                            </template>
                        </div>

                        <p class="mt-1.5 text-[16px] leading-[1.3] font-semibold text-silat-teks"
                           x-text="k.{{ $kunci }}.nama || '—'"></p>
                        <p class="mt-0.5 text-[13px] text-silat-teks-redup" x-text="k.{{ $kunci }}.kontingen"></p>

                        {{-- Diskualifikasi ditulis apa adanya: 0.00 yang tidak
                             dijelaskan terbaca sebagai kegagalan sistem, bukan
                             keputusan Pengawas. --}}
                        <p class="silat-angka mt-3 text-[44px] leading-none font-semibold text-silat-teks"
                           x-text="k.{{ $kunci }}.akhir.toFixed(2)"></p>
                        <template x-if="k.{{ $kunci }}.didiskualifikasi">
                            <p class="mt-1 text-[13px] font-medium text-silat-teks-kedua">Didiskualifikasi</p>
                        </template>

                        {{-- Nilai TIAP juri, bukan cuma mediannya. Median
                             menyembunyikan sebaran: enam juri yang semuanya
                             menilai 9.70 dan enam juri yang menilai antara 9.40
                             dan 10.00 menghasilkan angka akhir yang sama persis.
                             Yang kedua pantas ditanyakan, dan pertanyaannya
                             tidak bisa diajukan kalau angkanya tidak terlihat. --}}
                        <div class="mt-3 border-t border-silat-garis pt-2">
                            <p class="silat-angka text-[10px] tracking-[.14em] text-silat-teks-redup uppercase">Nilai juri</p>

                            <template x-for="(nilai, i) in k.{{ $kunci }}.nilai_juri" :key="i">
                                <div class="flex items-baseline justify-between py-0.5">
                                    <span class="text-[12.5px] text-silat-teks-kedua" x-text="nilai.nama ?? ('Juri ' + (i + 1))"></span>
                                    <span class="silat-angka text-[14px] text-silat-teks" x-text="nilai.value.toFixed(2)"></span>
                                </div>
                            </template>

                            <template x-if="k.{{ $kunci }}.nilai_juri.length === 0">
                                <p class="py-1 text-[12.5px] text-silat-teks-redup">Belum ada juri yang menilai.</p>
                            </template>
                        </div>

                        <div class="mt-3 border-t border-silat-garis pt-2">
                            <div class="flex items-baseline justify-between py-0.5">
                                <span class="text-[12.5px] text-silat-teks-kedua">Median</span>
                                <span class="silat-angka text-[14px] text-silat-teks" x-text="k.{{ $kunci }}.median.toFixed(2)"></span>
                            </div>
                            {{-- Besaran satuannya ikut disebut, dibaca dari
                                 setelan penilaian dan bukan diketik: 0.01 milik
                                 juri untuk rincian gerak, 0.50 milik Pengawas
                                 untuk waktu, gelanggang, dan pakaian (Pasal
                                 12.1.e). Menjumlahkannya jadi satu angka
                                 menghapus perbedaan yang paling sering
                                 disengketakan. --}}
                            <div class="flex items-baseline justify-between py-0.5">
                                <span class="text-[12.5px] text-silat-teks-kedua">
                                    Pengurangan juri ({{ number_format(config('scoring.jurus.pengurangan.juri', 0.01), 2) }})
                                </span>
                                <span class="silat-angka text-[14px] text-silat-teks" x-text="'−' + k.{{ $kunci }}.pengurangan_juri.toFixed(2)"></span>
                            </div>
                            <div class="flex items-baseline justify-between py-0.5">
                                <span class="text-[12.5px] text-silat-teks-kedua">
                                    Pengurangan pengawas ({{ number_format(config('scoring.jurus.pengurangan.pengawas', 0.5), 2) }})
                                </span>
                                <span class="silat-angka text-[14px] text-silat-teks" x-text="'−' + k.{{ $kunci }}.pengurangan_pengawas.toFixed(2)"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        @endforeach
    </div>

    @if ($keputusan)
        {{--
            Skor akhir yang sama persis TIDAK diputus sistem.

            Pemecah seri berjenjang -- hukuman terendah, waktu terdekat ke
            acuan, standar deviasi -- adalah aturan peringkat nomor berformat
            penampilan, dan rantainya berakhir pada undian. Undian yang
            dijalankan diam-diam di battle berarti seorang pesilat tersingkir
            oleh angka acak yang tidak pernah diumumkan kepada siapa pun.
            Di battle, yang memutuskan Ketua Pertandingan, dan alasannya
            tercetak di berita acara.
        --}}
        <template x-if="k?.seri && ! k?.battle?.selesai">
            <div class="mt-4 rounded-silat border border-silat-teguran p-4" x-data="{ alasanSeri: '' }">
                <p class="text-[15px] font-semibold text-silat-teks">Skor akhir kedua sudut sama</p>
                <p class="mt-1 text-[13px] leading-relaxed text-silat-teks-redup">
                    Sistem tidak menetapkan pemenangnya sendiri. Ketua Pertandingan yang memilih sudut,
                    dan alasannya wajib ditulis — ia ikut tercetak di berita acara.
                </p>

                <label class="sr-only" for="alasan-seri">Alasan keputusan</label>
                <input id="alasan-seri" type="text" x-model="alasanSeri"
                       placeholder="Alasan keputusan"
                       class="mt-3 h-11 w-full rounded-silat border border-silat-garis bg-silat-latar px-3 text-[14px] text-silat-teks placeholder:text-silat-teks-samar">

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" x-bind:disabled="! alasanSeri.trim()"
                            x-on:click="putuskanBattle(k.merah.registration_id, alasanSeri)"
                            class="h-11 rounded-silat bg-silat-merah px-5 text-[14px] font-semibold text-silat-teks disabled:opacity-40">
                        Menangkan sudut merah
                    </button>
                    <button type="button" x-bind:disabled="! alasanSeri.trim()"
                            x-on:click="putuskanBattle(k.biru.registration_id, alasanSeri)"
                            class="h-11 rounded-silat bg-silat-biru px-5 text-[14px] font-semibold text-silat-teks disabled:opacity-40">
                        Menangkan sudut biru
                    </button>
                </div>
            </div>
        </template>

        <template x-if="k?.siap && ! k?.seri && ! k?.battle?.selesai">
            <div class="mt-4 flex items-center gap-3">
                <button type="button" x-on:click="putuskanBattle(null, null)"
                        class="h-11 rounded-silat bg-silat-aksi px-5 text-[14px] font-semibold text-silat-aksi-teks">
                    Tetapkan pemenang
                </button>
                <span class="text-[12.5px] text-silat-teks-redup">Skor akhirnya berbeda — pemenangnya ditentukan angka.</span>
            </div>
        </template>
    @endif
</div>
