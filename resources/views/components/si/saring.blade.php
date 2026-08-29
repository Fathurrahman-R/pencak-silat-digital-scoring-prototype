@props([
    // Nama query string yang dikendalikan penyaring ini.
    'param' => 'status',
    // ['nilai' => 'Label']
    'pilihan' => [],
    // Label untuk keadaan "tidak disaring".
    'semua' => 'Semua',
    /*
     * Nilai yang sedang berlaku. Biasanya tidak perlu diisi -- diambil dari
     * query string. Diisi HANYA kalau halaman punya penyaring bawaan yang
     * berlaku meski query string kosong; tanpa itu "Semua" akan menyala padahal
     * daftarnya sedang tersaring, dan panitia menyimpulkan data yang tidak
     * muncul itu memang tidak ada.
     */
    'sekarang' => null,
])

{{--
    Penyaring untuk pilihan yang sedikit: semuanya terbaca sekaligus, tanpa
    perlu dibuka dulu.

    Tiap pilihan adalah tautan biasa yang mempertahankan query lain dan
    mengembalikan halaman ke satu -- tanpa itu penyaring baru bisa mendarat di
    halaman 7 yang kosong, dan orang menyimpulkan hasilnya nihil.

    Yang sedang berlaku dibedakan oleh warna DAN tebal huruf DAN garis tepi,
    bukan warna saja: bedanya harus terbaca di proyektor gelanggang yang
    warnanya pudar.
--}}

@php
    $sekarang ??= request()->query($param);
    $dasar = request()->query();
    unset($dasar['page']);

    $tautan = function (?string $nilai) use ($dasar, $param) {
        $query = $dasar;

        if ($nilai === null) {
            unset($query[$param]);
        } else {
            $query[$param] = $nilai;
        }

        return request()->url().($query === [] ? '' : '?'.http_build_query($query));
    };

    $dasarKelas = 'inline-flex h-9 items-center rounded-[var(--radius-kecil)] border px-3 text-[14px] transition-colors';
    /*
     * Pasangan yang sama dengan tombol utama: bg-accent di atas
     * text-accent-on, kontras 18.01.
     *
     * Sebelumnya `bg-ink text-surface`. Di suasana gelap keduanya putih --
     * chip yang sedang berlaku tampil sebagai kotak putih kosong tanpa satu
     * pun huruf, dan penyaring yang aktif jadi tak bernama. Kontrasnya 1.0,
     * dan pengukur kontras tidak menangkapnya karena pasangan itu tidak ada
     * di daftarnya.
     */
    $nyala = 'border-accent bg-accent font-semibold text-accent-on';
    $padam = 'border-line-strong bg-surface-raised text-ink-secondary hover:text-ink';
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
    <a href="{{ $tautan(null) }}"
       @if (blank($sekarang)) aria-current="page" @endif
       class="{{ $dasarKelas }} {{ blank($sekarang) ? $nyala : $padam }}">{{ $semua }}</a>

    @foreach ($pilihan as $nilai => $label)
        @php ($aktif = (string) $sekarang === (string) $nilai)
        <a href="{{ $tautan((string) $nilai) }}"
           @if ($aktif) aria-current="page" @endif
           class="{{ $dasarKelas }} {{ $aktif ? $nyala : $padam }}">{{ $label }}</a>
    @endforeach
</div>
