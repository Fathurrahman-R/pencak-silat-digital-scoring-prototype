<x-layouts.silat :title="'Juri Jurus — '.$performance->registration->athletes->pluck('name')->implode(', ')">
    <div x-data="jurusPanel(@js($config))" class="mx-auto flex min-h-screen max-w-md flex-col gap-4 p-4">
        <header class="text-center">
            <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-samar">JURI JURUS</p>
            <h1 class="text-[19px] font-medium text-silat-teks" x-text="peserta.nama"></h1>
            <p class="text-[13px] text-silat-teks-redup" x-text="peserta.kontingen"></p>
            <p class="text-[12px] text-silat-teks-redup">{{ $performance->jurusEvent->nama() }} · {{ ucfirst($performance->tahap) }}</p>
        </header>

        <p x-show="galat" x-text="galat" class="rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        <div class="rounded-silat bg-silat-panel p-4 text-center">
            <p class="silat-angka text-[36px] font-medium text-silat-teks" x-text="tampilWaktu"></p>
            <p class="mt-1 text-[12px] text-silat-teks-redup" x-text="{
                terjadwal: 'Belum dimulai', berlangsung: 'Sedang tampil', selesai: 'Penampilan selesai',
            }[performance.status]"></p>
        </div>

        <div class="rounded-silat bg-silat-panel p-5">
            <p class="mb-3 text-center text-[13px] text-silat-teks-redup">
                Nilai saya (9.00–10.00)
                <span x-show="nilaiSaya !== null" class="text-silat-emas">· tersimpan</span>
            </p>

            <form x-on:submit.prevent="kirimNilaiInput()" class="flex flex-col items-center gap-3">
                <input
                    type="number" step="0.01" min="9.00" max="10.00" required
                    x-model="nilaiInput"
                    class="silat-angka w-40 rounded-silat border border-silat-garis bg-silat-latar px-3 py-3 text-center text-[32px] text-silat-teks"
                >
                <button type="submit" class="w-full rounded-silat bg-silat-biru px-4 py-3 text-[14px] text-silat-teks">
                    Kirim nilai
                </button>
            </form>
        </div>

        <div class="rounded-silat bg-silat-panel p-4">
            <p class="mb-2 text-[13px] font-medium text-silat-teks">Catat pengurangan 0.01</p>
            <p class="mb-3 text-[11px] text-silat-teks-redup">
                Kesalahan rincian gerak, kesalahan urutan, gerakan tertinggal, atau senjata terlepas tanpa
                menyentuh matras.
            </p>

            <form x-data="{ alasan: '' }" x-on:submit.prevent="penguranganJuri(alasan).then((ok) => { if (ok) alasan = '' })"
                  class="flex items-center gap-2">
                <input type="text" x-model="alasan" placeholder="Alasan" required
                       class="flex-1 rounded-silat border border-silat-garis bg-silat-latar px-2 py-1.5 text-[12px] text-silat-teks placeholder:text-silat-teks-samar">
                <button type="submit" class="rounded-silat bg-silat-mati px-3 py-1.5 text-[12px] text-silat-teks">−0.01</button>
            </form>
        </div>
    </div>
</x-layouts.silat>
