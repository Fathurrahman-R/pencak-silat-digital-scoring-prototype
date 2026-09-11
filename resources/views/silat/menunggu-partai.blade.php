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

        {{--
            Gelanggang yang sedang menayangkan JURUS menyebutkan sebabnya.

            Wasit, Dewan Wasit Juri, dan Komisi Protes tidak bertugas di nomor
            Jurus. Kalimat "menunggu pengendali memilih partai" di situ keliru
            dua kali: pengendali SUDAH memilih, dan yang dipilihnya bukan
            urusan peran ini. Yang membacanya akan menyangka panelnya rusak
            atau pengendalinya lupa.
        --}}
        @if (($config['sebabMenunggu'] ?? null) === 'jurus')
            <p class="text-[22px] leading-[1.35] font-semibold tracking-[-0.02em] text-silat-teks">
                Gelanggang ini sedang menayangkan Jurus
            </p>

            <p class="max-w-[42ch] text-[14px] leading-[1.7] text-silat-teks-redup">
                Peran ini tidak bertugas di nomor Jurus. Panel akan terbuka sendiri begitu
                {{ $arena->name }} kembali menayangkan partai Tanding — tidak perlu memuat
                ulang halaman maupun mencari alamatnya.
            </p>
        @else
            <p class="text-[22px] leading-[1.35] font-semibold tracking-[-0.02em] text-silat-teks">
                Menunggu pengendali memilih partai
            </p>

            <p class="max-w-[42ch] text-[14px] leading-[1.7] text-silat-teks-redup">
                Panel ini akan terbuka sendiri begitu partai gelanggang
                {{ $arena->name }} ditetapkan. Tidak perlu memuat ulang halaman
                maupun mencari alamatnya.
            </p>
        @endif

        <x-silat.indikator-koneksi />
    </div>
</x-layouts.silat>
