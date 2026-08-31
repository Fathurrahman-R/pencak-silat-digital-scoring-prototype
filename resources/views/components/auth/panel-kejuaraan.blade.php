{{--
    Panel sisi kanan layar masuk dan daftar.

    Menggantikan <x-auth.trust-panel> bawaan boilerplate, yang memuat kutipan
    bernama orang beserta jabatan dan perusahaan, plus angka "2.400+ tim
    keuangan · Rp 4,1T tagihan diproses · 99,9% uptime". Ketiganya karangan.
    Selain tidak ada hubungannya dengan pencak silat, testimoni dan metrik yang
    dikarang tidak boleh ikut terbit di halaman yang dibuka orang luar.

    Yang menggantikannya bukan klaim lain, melainkan ANGKA YANG BENAR-BENAR ADA
    di basis data: kejuaraan mana yang sedang berjalan, berapa kontingen yang
    terdaftar, berapa kelas yang dipertandingkan. Kalau tidak ada kejuaraan
    berjalan, bagian itu tidak muncul sama sekali -- bukan diganti angka contoh.

    Query-nya dijalankan di sini, bukan dititipkan ke controller auth. Halaman
    masuk punya empat controller berbeda (login, register, forgot, reset) dan
    ketiganya tidak punya urusan dengan data kejuaraan; menaruhnya di komponen
    membuat panel ini berdiri sendiri.
--}}

@php
    /*
        Yang dihitung hanya kelas yang benar-benar punya peserta.

        Katalog kelas dari naskah aturan 2025 berisi 174 baris, dan satu
        kejuaraan hanya menjalankan sebagian kecilnya. Menghitung seluruh
        katalog membuat halaman ini mengumumkan "174 kelas dipertandingkan"
        untuk kejuaraan yang sebenarnya menjalankan dua kelas.
    */
    $kejuaraan = App\Models\Tournament::query()
        ->where('status', App\Enums\StatusTurnamen::Berjalan)
        ->withCount([
            'contingents',
            'arenas',
            'weightClasses as weight_classes_count' => fn ($query) => $query->has('registrations'),
        ])
        ->orderBy('starts_on')
        ->first();
@endphp

<div class="flex h-full flex-col justify-between gap-10">
    <div>
        <div class="text-[11px] tracking-[.28em] text-[#8a8a90] uppercase">Digital Scoring Pencak Silat</div>

        @if ($kejuaraan)
            <div class="mt-6 text-[11px] tracking-[.28em] text-[#8a8a90] uppercase">Sedang berlangsung</div>
            <div class="mt-2 text-[28px] leading-tight font-bold">{{ $kejuaraan->name }}</div>
            <div class="mt-2 font-mono text-[13px] text-[#8a8a90]">
                {{ $kejuaraan->venue ? strtoupper($kejuaraan->venue).' · ' : '' }}{{ $kejuaraan->starts_on?->translatedFormat('d M Y') }}
            </div>

            <div class="mt-8">
                @foreach ([
                    [$kejuaraan->contingents_count, 'kontingen'],
                    [$kejuaraan->weight_classes_count, 'kelas dipertandingkan'],
                    [$kejuaraan->arenas_count, 'gelanggang'],
                ] as [$angka, $label])
                    <div class="flex items-baseline gap-6 border-b border-[#2a2a2c] py-3">
                        <span class="w-[84px] font-mono text-[30px] leading-none font-semibold tabular-nums">{{ $angka }}</span>
                        <span class="text-[15px] text-[#8a8a90]">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-6 text-[24px] leading-snug font-semibold">
                Satu sistem dari pendaftaran kontingen sampai rekap medali.
            </div>
            <p class="mt-3 max-w-[46ch] text-[15px] leading-relaxed text-[#8a8a90]">
                Penilaian Tanding dan Jurus mengikuti Peraturan Pertandingan Pencak Silat 2025.
                Seluruh jalur pertandingan berjalan di jaringan lokal gelanggang, tanpa bergantung internet.
            </p>
        @endif
    </div>

    <div>
        <div class="border-b-2 border-white pb-3 text-[11px] tracking-[.28em] text-[#8a8a90] uppercase">
            Yang masuk lewat sini
        </div>

        @foreach ([
            ['Wasit dan juri', 'Panel gelanggang untuk partai yang ditugaskan hari itu'],
            ['Operator', 'Timer dan papan skor gelanggang'],
            ['Dewan Wasit Juri', 'Meninjau riwayat nilai dan mengesahkan hasil'],
            ['Panitia dan official', 'Jadwal, bagan, verifikasi, timbang badan, tagihan'],
        ] as [$peran, $tugas])
            <div class="flex items-baseline gap-6 border-b border-[#2a2a2c] py-3">
                <span class="w-[150px] shrink-0 text-[15px] font-semibold">{{ $peran }}</span>
                <span class="text-[14px] leading-relaxed text-[#8a8a90]">{{ $tugas }}</span>
            </div>
        @endforeach
    </div>
</div>
