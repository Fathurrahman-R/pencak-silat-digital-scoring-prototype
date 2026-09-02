@props([
    // Kata benda yang dihapus, tunggal — "pengguna", "role", "kejuaraan".
    'benda',
    // Kalimat yang menyebut apa lagi yang ikut hilang. Wajib: tanpa ini
    // dialognya cuma bertanya "yakin?", dan yakin bukan informasi.
    'akibat',
])

{{--
    Dialog hapus untuk satu baris tabel — SATU untuk seluruh halaman, bukan
    satu per baris.

    Bentuk lamanya menanam satu modal di dalam tiap baris. Halaman pengguna
    dengan 50 baris berarti 50 dialog di DOM, masing-masing membawa salinan
    nama dan formulir DELETE-nya sendiri, dan hanya satu yang akan dipakai.

    Barisnya memasang muatannya di data-* lalu memancarkannya:

        <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                     data-aksi="{{ route('admin.users.destroy', $user) }}"
                     data-nama="{{ $user->name }}"
                     x-on:click="$dispatch('hapus-pengguna', $el.dataset)">
            Hapus
        </x-si.tombol>

    Lewat data-*, bukan @js(): tanda kutip di dalam JSON menutup atribut lebih
    awal, dan Alpine melaporkannya sebagai "Invalid or unexpected token" di
    tempat yang tidak ada hubungannya dengan penyebabnya.

    Objeknya DISEBUT NAMANYA di judul. "Yakin menghapus?" tidak memberi tahu
    baris mana yang tertekan — dan di tabel rapat, baris yang tertekan sering
    bukan baris yang dimaksud.
--}}

@php($nama = 'hapus-'.\Illuminate\Support\Str::slug($benda))

<div x-data="{ terbuka: false, aksi: '', nama: '', rincian: '' }"
     x-on:{{ $nama }}.window="aksi = $event.detail.aksi; nama = $event.detail.nama;
                             rincian = $event.detail.rincian ?? ''; terbuka = true"
     x-on:keydown.escape.window="terbuka = false">

    <div x-show="terbuka" x-cloak
         class="fixed inset-0 z-[80] flex items-center justify-center bg-[rgba(9,9,11,.45)] p-4"
         x-on:click.self="terbuka = false">
        <div class="w-full max-w-[460px] rounded-[var(--radius-dialog)] border border-danger-line bg-surface-raised p-5.5 text-left">
            <p class="text-[11px] tracking-[.1em] text-danger uppercase">Tidak bisa dibatalkan</p>
            <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">
                Hapus {{ $benda }} <span x-text="nama"></span>?
            </p>

            {{-- Angka yang berbeda tiap baris — berapa pengguna kehilangan
                 role ini, berapa partai kehilangan gelanggangnya. Barisnya
                 yang tahu, jadi barisnya yang mengirimkannya lewat
                 data-rincian. --}}
            <p x-show="rincian" x-cloak class="mt-2 text-[14px] leading-relaxed text-ink"
               x-text="rincian"></p>

            <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">{{ $akibat }}</p>

            <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                @csrf
                @method('DELETE')

                {{-- Batal di kiri: urutan tekan yang dihafal dari dialog biasa
                     tidak boleh berubah jadi bencana di sini. --}}
                <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                <x-si.tombol tipe="submit" varian="bahaya-tegas">Hapus {{ $benda }}</x-si.tombol>
            </form>
        </div>
    </div>
</div>
