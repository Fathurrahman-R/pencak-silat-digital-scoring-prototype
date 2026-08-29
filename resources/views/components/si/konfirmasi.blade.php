@props([
    'nama',
    'judul',
    'akibat',
    'aksi',
    'metode' => 'POST',
    'label' => 'Lanjutkan',
    'varian' => 'bahaya-tegas',

    // Diisi kalau tindakannya tidak bisa dipulihkan: pengguna harus mengetik
    // nama objeknya sebelum tombolnya hidup.
    'ketik' => null,

    // Kalau kosong, dialog memakai kalimat bawaan. Diisi bila membatalkan
    // punya akibatnya sendiri yang perlu disebut.
    'bilaBatal' => 'Kalau batal, tidak ada yang berubah.',
])

{{--
    Dialog konfirmasi -- docs/BRIEF-DESAIN.md §9.

    Ini komponen yang paling menentukan apakah panitia berani memakai aplikasi.
    Aturannya:

      1. Judul berupa pertanyaan dengan objeknya disebut, bukan "Anda yakin?".
      2. Akibatnya dinyatakan kalimat lengkap, termasuk apa yang jadi tidak
         bisa diubah lagi.
      3. Apa yang terjadi kalau batal ikut disebut.
      4. Tombol berbahaya TIDAK berada di posisi refleks: batal di kiri, dan
         urutan tekan yang dihafal dari dialog biasa tidak berubah jadi
         bencana di sini.
      5. Untuk tindakan yang tidak bisa dipulihkan, dialog tidak menutup
         karena klik di luar maupun Esc.
--}}

@php
    $terkunci = filled($ketik);
@endphp

<div
    x-data="{
        buka: false,
        ketikan: '',
        terkunci: @js($terkunci),
        sasaran: @js($ketik),
        get siap() { return ! this.terkunci || this.ketikan.trim() === this.sasaran },
        tutup() { if (! this.terkunci) { this.buka = false; this.ketikan = '' } },
    }"
    x-on:buka-konfirmasi-{{ $nama }}.window="buka = true"
    x-on:keydown.escape.window="tutup()"
>
    <template x-if="buka">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="judul-{{ $nama }}">

            <div class="absolute inset-0 bg-[rgb(8_8_10/0.72)]" x-on:click="tutup()" aria-hidden="true"></div>

            <div class="relative w-full max-w-[520px] rounded-[var(--radius-besar)] border border-line bg-surface-raised shadow-[var(--bayang-modal)]">
                <div class="flex flex-col gap-3 px-6 pt-6">
                    <h2 id="judul-{{ $nama }}" class="text-[22px] leading-snug font-bold text-ink">{{ $judul }}</h2>
                    <p class="text-[15px] leading-relaxed text-ink-secondary">{{ $akibat }}</p>
                    @if ($bilaBatal)
                        <p class="text-[14px] leading-relaxed text-ink-muted">{{ $bilaBatal }}</p>
                    @endif
                </div>

                @if ($terkunci)
                    <div class="flex flex-col gap-2 px-6 pt-5">
                        <label for="ketik-{{ $nama }}" class="text-[14px] text-ink-secondary">
                            Ketik <strong class="font-semibold text-ink">{{ $ketik }}</strong> untuk melanjutkan
                        </label>
                        <input id="ketik-{{ $nama }}" x-model="ketikan" type="text" autocomplete="off"
                               class="h-[var(--sentuh-admin)] rounded-[var(--radius)] border border-line-strong bg-surface px-3.5 text-[15px] text-ink outline-none focus:ring-2 focus:ring-surface focus:ring-offset-2 focus:ring-offset-ink">
                    </div>
                @endif

                <div class="mt-6 flex items-center justify-between gap-3 border-t border-line px-6 py-4">
                    <x-si.tombol varian="polos" tipe="button" x-on:click="buka = false; ketikan = ''">
                        Batal
                    </x-si.tombol>

                    <form method="POST" action="{{ $aksi }}">
                        @csrf
                        @if (strtoupper($metode) !== 'POST')
                            @method($metode)
                        @endif
                        {{ $slot }}
                        <x-si.tombol :varian="$varian" x-bind:disabled="! siap">
                            {{ $label }}
                        </x-si.tombol>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
