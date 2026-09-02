{{--
    Pesan hasil tindakan yang dibawa session flash.

        return redirect()->route('admin.users.index')->with('success', 'Pengguna disimpan.');

    Kunci yang dikenali: success, error, warning, info.

    TIDAK HILANG SENDIRI. Pendahulunya, <x-ui.toast>, memasang
    `setTimeout(() => show = false, 6000)`. Enam detik cukup buat orang yang
    sudah tahu pesan apa yang ditunggunya, dan tidak cukup buat orang yang
    baru pertama memakai aplikasi — justru orang yang paling perlu membaca
    kalimatnya sampai habis. Pesan yang lenyap sendiri juga tidak bisa dibaca
    ulang: yang telanjur hilang hanya bisa dipanggil kembali dengan mengulang
    tindakannya, dan sebagian tindakan panitia tidak boleh diulang.

    Sekarang ia menunggu ditutup, atau hilang saat halaman berganti — memang
    itu arti "flash".

    `role="status"` membuat pembaca layar mengumumkannya tanpa merebut fokus
    dari tempat orang sedang bekerja. Pesan galat memakai `role="alert"`
    karena ia harus memotong.
--}}

@php
    $pesan = collect(['success', 'error', 'warning', 'info'])
        ->filter(fn (string $kunci): bool => session()->has($kunci))
        ->map(fn (string $kunci): array => ['jenis' => $kunci, 'isi' => session($kunci)])
        ->values();

    $gaya = [
        'success' => ['ikon' => 'circle-check', 'warna' => 'text-success'],
        'error' => ['ikon' => 'circle-x', 'warna' => 'text-danger'],
        'warning' => ['ikon' => 'triangle-alert', 'warna' => 'text-warning'],
        'info' => ['ikon' => 'info', 'warna' => 'text-info'],
    ];
@endphp

@if ($pesan->isNotEmpty())
    <div class="fixed end-6 bottom-6 z-[90] flex flex-col gap-3">
        @foreach ($pesan as $satu)
            @php($g = $gaya[$satu['jenis']] ?? $gaya['info'])

            <div role="{{ $satu['jenis'] === 'error' ? 'alert' : 'status' }}"
                 x-data="{ tampil: true }"
                 x-show="tampil" x-cloak
                 class="flex w-full max-w-[380px] items-start gap-3 rounded-[var(--radius)] border border-line bg-surface-raised p-4 shadow-lg">
                <x-si.ikon :nama="$g['ikon']" class="mt-0.5 size-[20px] shrink-0 {{ $g['warna'] }}" />

                <div class="flex-1 text-[13.5px] leading-relaxed text-ink">{{ $satu['isi'] }}</div>

                {{-- Sasaran 44px: tombol tutup 16px meleset di layar sentuh, dan
                     pesan yang tidak bisa ditutup akan menghalangi isi halaman. --}}
                <button type="button" x-on:click="tampil = false"
                        class="-m-2 grid size-11 shrink-0 place-items-center rounded-[var(--radius-kecil)] text-ink-muted hover:bg-surface-inset hover:text-ink focus-visible:ring-[3px] focus-visible:ring-ink/10 focus-visible:outline-none">
                    <span class="sr-only">Tutup pesan</span>
                    <x-si.ikon nama="x" class="size-4" />
                </button>
            </div>
        @endforeach
    </div>
@endif
