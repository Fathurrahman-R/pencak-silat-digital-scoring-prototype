@props([
    // Alamat POST bulk-destroy.
    'aksi',
    // Kata benda yang dihapus, tunggal — "pengguna", "role", "kejuaraan".
    'benda',
    // Kalimat yang menyebut apa lagi yang ikut hilang. Wajib: tanpa ini
    // dialognya cuma bertanya "yakin?", dan yakin bukan informasi.
    'akibat',
])

{{--
    Tombol hapus borongan beserta dialognya.

    Dibuat satu komponen karena polanya berulang di lima layar, dan di empat
    di antaranya tombolnya MENGIRIM LANGSUNG — satu tekan menghapus setiap
    baris yang tercentang, tanpa satu pun konfirmasi dan tanpa menyebut berapa
    banyak yang terpilih. Menyalin dialognya empat kali berarti empat tempat
    yang harus diperbaiki lagi lain waktu.

    Dialognya menyebut JUMLAH yang terpilih. "Hapus terpilih" tidak memberi
    tahu apakah yang tercentang tiga baris atau tiga puluh, dan centang di
    tabel panjang mudah tertinggal dari halaman sebelumnya.

    Dipakai di dalam <x-slot:bulk> milik toolbar tabel, yang sudah berada di
    dalam cakupan Alpine `tableSelection` — `selected` datang dari sana.
--}}

@php($nama = 'hapus-borongan-'.\Illuminate\Support\Str::slug($benda))

<div>
    <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                 x-on:click="$dispatch('{{ $nama }}', { jumlah: selected.length, ids: selected })">
        Hapus terpilih
    </x-si.tombol>

    <div x-data="{ terbuka: false, jumlah: 0, ids: [] }"
         x-on:{{ $nama }}.window="jumlah = $event.detail.jumlah; ids = $event.detail.ids; terbuka = true"
         x-on:keydown.escape.window="terbuka = false">
        <div x-show="terbuka" x-cloak
             class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
             x-on:click.self="terbuka = false">
            <div class="w-full max-w-[460px] rounded-[var(--radius)] border border-danger bg-surface-raised p-5 text-left">
                <p class="text-[11px] tracking-[.1em] text-danger uppercase">Tidak bisa dibatalkan</p>
                <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">
                    Hapus <span x-text="jumlah"></span> {{ $benda }} sekaligus?
                </p>

                <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">{{ $akibat }}</p>

                <form method="POST" action="{{ $aksi }}" class="mt-4 flex items-center gap-2">
                    @csrf
                    <template x-for="id in ids" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>

                    <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                    <x-si.tombol tipe="submit" varian="bahaya-tegas">
                        Hapus <span x-text="jumlah"></span> {{ $benda }}
                    </x-si.tombol>
                </form>
            </div>
        </div>
    </div>
</div>
