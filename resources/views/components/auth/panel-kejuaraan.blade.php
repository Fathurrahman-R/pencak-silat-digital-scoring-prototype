{{--
    Panel sisi kanan layar masuk dan daftar.

    Menggantikan <x-auth.trust-panel> bawaan boilerplate, yang memuat kutipan
    bernama orang beserta jabatan dan perusahaan, plus angka "2.400+ tim
    keuangan · Rp 4,1T tagihan diproses · 99,9% uptime". Ketiganya karangan.
    Selain tidak ada hubungannya dengan pencak silat, testimoni dan metrik yang
    dikarang tidak boleh ikut terbit di halaman yang dibuka orang luar.

    Isi panel ini hanya menyatakan apa yang benar-benar dilakukan aplikasi dan
    siapa yang masuk lewat halaman ini -- tidak ada klaim, tidak ada angka.
    Komponennya masih tersedia di design system untuk yang membutuhkannya.
--}}

<div class="relative max-w-[420px]">
    <p class="eyebrow">Digital Scoring Pencak Silat</p>

    <p class="mt-3 font-display text-[26px] leading-snug font-semibold tracking-tight text-ink">
        Satu sistem dari pendaftaran kontingen sampai rekap medali.
    </p>

    <p class="mt-3 text-sm text-ink-secondary">
        Penilaian Tanding dan Jurus mengikuti Peraturan Pertandingan Pencak Silat
        Nasional 2025. Seluruh jalur pertandingan berjalan di jaringan lokal
        gelanggang, tanpa bergantung internet.
    </p>

    <dl class="mt-7 space-y-3.5">
        @foreach ([
            ['Wasit dan Juri', 'Masuk dari HP; partai yang ditugaskan langsung muncul di halaman depan.'],
            ['Operator IT', 'Menjalankan timer dan papan skor gelanggang.'],
            ['Dewan Wasit Juri', 'Meninjau riwayat nilai dan mengesahkan hasil partai.'],
            ['Official kontingen', 'Mendaftarkan pesilat, mengunggah berkas, dan melihat tagihan.'],
        ] as [$peran, $tugas])
            <div class="flex gap-3">
                <dt class="w-[132px] shrink-0 text-sm font-semibold text-ink">{{ $peran }}</dt>
                <dd class="text-[12.5px] leading-relaxed text-ink-muted">{{ $tugas }}</dd>
            </div>
        @endforeach
    </dl>
</div>
