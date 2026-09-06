@php
    use App\Enums\ResourceAction;
    use App\Support\Bagan\TahapBaganJurus;
@endphp

<x-layouts.silat :title="'Battle Jurus — '.$battle->bracket->jurusEvent->jenis->label()">
    {{--
        Komparasi nilai kedua sudut, dibaca sesudah battle selesai.

        Skor akhir Jurus adalah median enam juri dikurangi pengurangan. "9.72
        lawan 9.70" tidak menjelaskan apa pun sampai pembacanya tahu apakah
        bedanya datang dari penilaian juri atau dari satu pengurangan 0.50 yang
        dijatuhkan Pengawas — dan pelatih yang mengangkat kartu protes
        menanyakan persis itu.

        Karena itu halaman ini tidak berhenti di angka akhir: nilai TIAP juri
        ikut tampil (median menyembunyikan sebaran), dan pengurangannya dipisah
        menurut siapa yang menjatuhkannya.

        Merah kiri, biru kanan — mengikuti sisi gelanggang, bukan urutan tampil.
        Sudut biru memang tampil lebih dulu (Pasal 12.1.d.7), tapi menukar
        posisinya di layar akan membuat pembacanya salah mengira siapa berdiri
        di mana.
    --}}
    <div x-data="perbandinganBattle(@js($config))" class="min-h-dvh px-5 py-6">

        <header class="mx-auto max-w-[900px] pb-5">
            <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">
                {{ $battle->bracket->jurusEvent->jenis->label() }}
                · {{ $battle->bracket->jurusEvent->golongan_usia->label() }}
                · {{ $battle->bracket->jurusEvent->jenis_kelamin->label() }}
            </p>
            <p class="mt-1 text-[20px] font-semibold tracking-[-0.02em] text-silat-teks">
                {{ TahapBaganJurus::label(TahapBaganJurus::untuk($battle->round, $battle->bracket->size)) }}
                · Battle {{ $battle->id }}
            </p>

            <template x-if="battle?.selesai">
                <p class="mt-2 text-[14px] text-silat-teks-kedua">
                    Selisih skor akhir
                    <span class="silat-angka font-medium text-silat-teks" x-text="(selisih ?? 0).toFixed(2)"></span>
                </p>
            </template>
            <template x-if="battle && ! battle.selesai">
                <p class="mt-2 text-[14px] text-silat-teks-redup">
                    Battle belum selesai — angka di bawah masih bisa berubah.
                </p>
            </template>

            {{--
                Penetapan pemenang berdiri di sini, di halaman yang menampilkan
                dasar keputusannya -- bukan di daftar battle, tempat yang
                menekan tombol belum melihat angka apa pun.

                Server menolak kalau salah satu penampilan belum disahkan;
                tombolnya tetap ditawarkan supaya alasannya terbaca, bukan
                disembunyikan sehingga yang menekan bertanya-tanya kenapa tidak
                ada tombol.
            --}}
            @resource(rk('hasil-jurus', ResourceAction::Approve))
                <template x-if="battle && ! battle.selesai">
                    <form method="POST"
                          action="{{ route('admin.turnamen.jurus.battle.putuskan', [$tournament, $battle]) }}"
                          class="mt-3">
                        @csrf
                        <button type="submit"
                                class="h-11 rounded-silat bg-silat-aksi px-4 text-[14px] font-semibold text-silat-aksi-teks">
                            Tetapkan pemenang
                        </button>
                    </form>
                </template>
            @endresource
        </header>

        <div class="mx-auto grid max-w-[900px] gap-4 md:grid-cols-2">
            @foreach ([['merah', 'Sudut merah'], ['biru', 'Sudut biru']] as [$kunci, $judul])
                <div class="rounded-silat-besar border p-5"
                     x-bind:class="menang('{{ $kunci }}')
                         ? '{{ $kunci === 'merah' ? 'border-silat-merah bg-silat-merah-dalam' : 'border-silat-biru bg-silat-biru-dalam' }}'
                         : 'border-silat-garis'">

                    <template x-if="! {{ $kunci }}">
                        <p class="py-10 text-center text-[13.5px] text-silat-teks-redup">
                            Sudut ini belum punya penampilan.
                        </p>
                    </template>

                    <template x-if="{{ $kunci }}">
                        <div>
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="silat-angka text-[10px] tracking-[.14em] uppercase
                                          {{ $kunci === 'merah' ? 'text-silat-teks-merah-samar' : 'text-silat-teks-biru-samar' }}">
                                    {{ $judul }}
                                </p>
                                <template x-if="menang('{{ $kunci }}')">
                                    <span class="silat-angka rounded-silat-kecil bg-silat-teks px-2 py-1 text-[10px] font-semibold text-silat-latar">
                                        MENANG
                                    </span>
                                </template>
                            </div>

                            <p class="mt-1.5 text-[16px] leading-[1.3] font-semibold text-silat-teks"
                               x-text="{{ $kunci }}.nama || '—'"></p>
                            <p class="mt-0.5 text-[13px] text-silat-teks-redup" x-text="{{ $kunci }}.kontingen"></p>

                            {{-- Skor akhir. Diskualifikasi ditulis apa adanya:
                                 0.00 yang tidak dijelaskan terbaca sebagai
                                 kegagalan sistem, bukan keputusan Pengawas. --}}
                            <p class="silat-angka mt-4 text-[52px] leading-none font-semibold text-silat-teks"
                               x-text="{{ $kunci }}.akhir.toFixed(2)"></p>
                            <template x-if="{{ $kunci }}.didiskualifikasi">
                                <p class="mt-1 text-[13px] font-medium text-silat-teks-kedua">Didiskualifikasi</p>
                            </template>

                            {{-- Nilai tiap juri. Median menyembunyikan sebaran:
                                 enam juri yang seragam dan enam juri yang
                                 berselisih jauh menghasilkan angka yang sama. --}}
                            <div class="mt-4 border-t border-silat-garis pt-3">
                                <p class="silat-angka text-[10px] tracking-[.14em] text-silat-teks-redup uppercase">Nilai juri</p>

                                <div class="mt-2 flex flex-col gap-px">
                                    <template x-for="(nilai, i) in {{ $kunci }}.nilai_juri" :key="i">
                                        <div class="flex items-baseline justify-between py-1">
                                            <span class="text-[12.5px] text-silat-teks-kedua" x-text="nilai.nama ?? ('Juri ' + (i + 1))"></span>
                                            <span class="silat-angka text-[14px] text-silat-teks" x-text="nilai.value.toFixed(2)"></span>
                                        </div>
                                    </template>

                                    <template x-if="{{ $kunci }}.nilai_juri.length === 0">
                                        <p class="py-1 text-[12.5px] text-silat-teks-redup">Belum ada juri yang menilai.</p>
                                    </template>
                                </div>
                            </div>

                            {{-- Pengurangan dipisah menurut penjatuhnya: 0.01
                                 oleh juri untuk rincian gerak, 0.50 oleh
                                 Pengawas untuk waktu/gelanggang/pakaian
                                 (Pasal 12.1.e). Menjumlahkannya jadi satu angka
                                 menghapus perbedaan yang paling sering
                                 disengketakan. --}}
                            <div class="mt-3 border-t border-silat-garis pt-3">
                                <div class="flex items-baseline justify-between py-1">
                                    <span class="text-[12.5px] text-silat-teks-kedua">Median</span>
                                    <span class="silat-angka text-[14px] text-silat-teks" x-text="{{ $kunci }}.median.toFixed(2)"></span>
                                </div>
                                <div class="flex items-baseline justify-between py-1">
                                    <span class="text-[12.5px] text-silat-teks-kedua">Pengurangan juri (0.01)</span>
                                    <span class="silat-angka text-[14px] text-silat-teks" x-text="'−' + {{ $kunci }}.pengurangan_juri.toFixed(2)"></span>
                                </div>
                                <div class="flex items-baseline justify-between py-1">
                                    <span class="text-[12.5px] text-silat-teks-kedua">Pengurangan pengawas (0.50)</span>
                                    <span class="silat-angka text-[14px] text-silat-teks" x-text="'−' + {{ $kunci }}.pengurangan_pengawas.toFixed(2)"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.silat>
