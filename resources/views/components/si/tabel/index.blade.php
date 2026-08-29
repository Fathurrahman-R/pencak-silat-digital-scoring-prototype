@props([
    'headers' => [],
    'table' => null,
    // Daftar id baris di halaman ini. Diisi berarti tabel bisa dipilih:
    // kolom centang muncul di depan dan slot `bulk` di toolbar ikut hidup.
    'selectable' => [],
    // Diisi berarti tiap baris punya penanda di ujung dan bisa diklik untuk
    // membuka panel detail.
    'openable' => false,
])

{{--
    Kerangka tabel panitia.

    Padanan <x-ui.table> dengan API yang sengaja dibuat sepadan — `headers`,
    `table`, `selectable`, `openable`, slot `toolbar` dan `footer` — supaya
    memindahkan empat puluh sembilan layar admin cukup dengan mengganti nama
    komponennya, bukan menulis ulang tiap pemanggilan. Yang berubah rupanya,
    bukan cara memakainya.

    Tiga hal yang berbeda dari pendahulunya:

    1. Permukaan rata. Tidak ada `shadow-lift`; kedalaman dinyatakan warna
       permukaan dan satu garis. Bayangan di bawah tabel membuat angka lebih
       sulit dibaca, dan di layar panitia angka yang menang.
    2. Kolom yang bisa diurutkan menyatakan dirinya sendiri lewat kata
       "urutkan" pada `title`, dan arah urutan yang sedang berlaku ditulis
       sebagai teks tersembunyi untuk pembaca layar — bukan hanya arah panah.
    3. Keadaan kosong wajib menyebutkan apa yang membuka isinya; slot `kosong`
       menerima <x-si.kosong> yang menuntut prop `syarat`.

    Hanya bagian tabelnya yang menggulir mendatar, supaya toolbar dan
    penomoran halaman tetap di tempatnya saat kolomnya banyak.
--}}

@php($bisaDipilih = $selectable !== [])

<div class="overflow-hidden rounded-[var(--radius)] border border-line bg-surface-raised"
     @if ($bisaDipilih) x-data="tableSelection(@js(array_values($selectable)))" @endif>

    @isset($toolbar)
        <div class="border-b border-line px-4 py-3">{{ $toolbar }}</div>
    @endisset

    <div class="overflow-x-auto">
        <table {{ $attributes->class('w-full text-left text-[14px] text-ink-secondary') }}>
            <thead class="bg-surface-inset">
                <tr>
                    @if ($bisaDipilih)
                        <th scope="col" class="w-11 py-2.5 ps-4 pe-0">
                            <input type="checkbox"
                                   aria-label="Pilih semua baris di halaman ini"
                                   :checked="allChecked" x-on:change="toggleAll()"
                                   class="size-[18px] rounded-[var(--radius-kecil)] border-2 border-line-strong bg-surface-raised checked:border-accent checked:bg-accent">
                        </th>
                    @endif

                    @isset($head)
                        {{ $head }}
                    @else
                        @foreach ($headers as $kolom => $label)
                            @php($bisaDiurut = $table && is_string($kolom) && $table->isSortable($kolom))

                            <th scope="col"
                                @if ($bisaDiurut && $table->sortColumn() === $kolom)
                                    aria-sort="{{ $table->sortDirection() === 'desc' ? 'descending' : 'ascending' }}"
                                @endif
                                class="px-4 py-2.5 text-[11px] font-semibold tracking-[.1em] whitespace-nowrap text-ink-muted uppercase">
                                @if ($bisaDiurut)
                                    <a href="{{ $table->sortUrl($kolom) }}"
                                       title="Urutkan menurut {{ $label }}"
                                       class="inline-flex items-center gap-1.5 hover:text-ink">
                                        {{ $label }}

                                        @if ($table->sortColumn() === $kolom)
                                            {{-- Arah urutan ditulis untuk pembaca layar juga.
                                                 Panah sendirian tidak terbaca oleh yang tidak
                                                 melihatnya. --}}
                                            <span class="sr-only">
                                                — sedang diurutkan {{ $table->sortDirection() === 'desc' ? 'menurun' : 'menaik' }}
                                            </span>
                                            <x-si.ikon nama="chevron-up"
                                                       class="size-3.5 {{ $table->sortDirection() === 'desc' ? 'rotate-180' : '' }}" />
                                        @else
                                            <x-si.ikon nama="chevrons-up-down" class="size-3.5 opacity-40" />
                                        @endif
                                    </a>
                                @else
                                    {{ $label }}
                                @endif
                            </th>
                        @endforeach
                    @endisset

                    @if ($openable)
                        <th scope="col" class="w-11"><span class="sr-only">Buka rincian</span></th>
                    @endif
                </tr>
            </thead>

            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>

    {{-- Keadaan kosong menyebutkan apa yang membuka isinya — lihat
         <x-si.kosong>, yang menuntut prop `syarat`. --}}
    @isset($kosong)
        {{ $kosong }}
    @endisset

    @isset($footer)
        <div class="border-t border-line px-4 py-3 text-[13px] text-ink-muted">{{ $footer }}</div>
    @endisset
</div>
