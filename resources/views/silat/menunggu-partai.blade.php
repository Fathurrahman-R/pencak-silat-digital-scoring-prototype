<x-layouts.silat :title="'Menunggu partai — '.$arena->name" :manifest="$manifestUrl ?? null">
    {{--
        Layar tunggu gelanggang.

        Bukan 404, dan bukan halaman kosong. Gelanggang yang belum dipilihkan
        partai adalah keadaan yang normal — pagi hari sebelum partai pertama,
        dan jeda di antara dua kelas. Yang membukanya di detik itu tidak sedang
        salah alamat; ia sedang menunggu, dan yang dibutuhkannya cuma satu
        kalimat yang menyatakan apa yang sedang ditunggu.

        Halaman ini ikut terhubung Echo lewat `partaiPanel`, jadi begitu
        pengendali memilih partai ia berpindah sendiri tanpa disentuh — persis
        seperti panel yang sudah berjalan.
    --}}
    <div x-data="partaiPanel(@js($config))"
         class="flex min-h-dvh flex-col items-center justify-center gap-5 px-6 text-center">

        <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">
            {{ $arena->name }}
        </p>

        <p class="text-[22px] leading-[1.35] font-semibold tracking-[-0.02em] text-silat-teks">
            Menunggu pengendali memilih partai
        </p>

        <p class="max-w-[42ch] text-[14px] leading-[1.7] text-silat-teks-redup">
            Panel ini akan terbuka sendiri begitu partai gelanggang
            {{ $arena->name }} ditetapkan. Tidak perlu memuat ulang halaman
            maupun mencari alamatnya.
        </p>

        <x-silat.indikator-koneksi />
    </div>
</x-layouts.silat>
