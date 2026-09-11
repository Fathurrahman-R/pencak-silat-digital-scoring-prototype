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
            <div class="flex items-start justify-between gap-4">
                <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">
                    {{ $battle->bracket->jurusEvent->jenis->label() }}
                    · {{ $battle->bracket->jurusEvent->golongan_usia->label() }}
                    · {{ $battle->bracket->jurusEvent->jenis_kelamin->label() }}
                </p>

                <x-silat.indikator-koneksi class="shrink-0" />
            </div>
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

            <p x-show="galat" x-text="galat" x-cloak
               class="mt-3 rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>

            {{--
                Selama salah satu sudut belum disahkan, blok perbandingan di
                bawah belum digambar. Kalimat ini yang menyatakan kenapa --
                tanpa itu halaman terlihat seperti halaman yang gagal memuat.
            --}}
            <template x-if="! memuat && ! siap">
                <p class="mt-3 text-[14px] text-silat-teks-redup">
                    Perbandingan terbit setelah <span class="text-silat-teks">kedua sudut disahkan</span>.
                    Sebelum itu angkanya masih bisa berubah oleh pengurangan Pengawas.
                </p>
            </template>
        </header>

        {{-- Komparasi memakai komponen yang sama dengan papan gelanggang,
             panel juri, panel operator, dan panel ketua. Empat salinan markup
             berarti empat tempat yang harus diperbaiki saat susunan angkanya
             bergeser, dan yang keempat akan tertinggal. --}}
        <div class="mx-auto max-w-[900px]">
            <x-silat.komparasi-battle sumber="perbandingan"
                                      :keputusan="auth()->user()?->can(rk('hasil-jurus', ResourceAction::Approve)) ?? false" />
        </div>
    </div>
</x-layouts.silat>
