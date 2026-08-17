{{--
    Bagan antar partai. Statis, tanpa Alpine/Echo -- susunan bagan tidak
    berubah selama satu partai berlangsung, jadi tidak ada yang perlu
    disegarkan realtime di sini. Kelasnya dipilih lewat ?kelas=ID (lihat
    OverlayController::bracket), karena satu turnamen bisa punya ratusan
    kelas dan overlay tidak bisa menebak mana yang mau ditayangkan.

    Rekap medali penuh menyusul Fase 8; belum ada mesin hitungnya.
--}}

<x-layouts.overlay title="Bagan">
    <div class="relative h-full w-full p-[64px]">
        @if (! $bracket)
            <p class="text-[18px] text-silat-teks-redup">
                Tambahkan <span class="silat-angka text-silat-teks">?kelas=ID</span> ke alamat sumber ini untuk memilih kelas tanding yang ditayangkan.
            </p>
        @else
            <div class="mb-6">
                <p class="text-[13px] tracking-[.1em] text-silat-teks-redup">BAGAN</p>
                <h1 class="text-[28px] font-medium text-silat-teks">
                    {{ $weightClass->jenis_kelamin->label() }} {{ $weightClass->golongan_usia->label() }} — {{ $weightClass->name }}
                </h1>
            </div>

            <x-silat.bagan-pohon :bracket="$bracket" :babak="$babak" />
        @endif
    </div>
</x-layouts.overlay>
