@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Operator Jurus — '.$performance->registration->athletes->pluck('name')->implode(', ')">
    <div x-data="jurusPanel(@js($config))" class="mx-auto flex min-h-screen max-w-2xl flex-col gap-4 p-4">
        <header>
            <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-samar">OPERATOR JURUS</p>
            <h1 class="text-[18px] font-medium text-silat-teks" x-text="peserta.nama"></h1>
            <p class="text-[13px] text-silat-teks-redup" x-text="peserta.kontingen"></p>
            <p class="text-[12px] text-silat-teks-redup">{{ $performance->jurusEvent->nama() }} · {{ ucfirst($performance->tahap) }}</p>
        </header>

        <p x-show="galat" x-text="galat" class="rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        <div class="rounded-silat bg-silat-panel p-6 text-center">
            <p class="silat-angka text-[48px] font-medium text-silat-teks" x-text="tampilWaktu"></p>
            <p class="mt-1 text-[12px] text-silat-teks-redup" x-text="{
                terjadwal: 'Belum dimulai', berlangsung: 'Sedang tampil', selesai: 'Selesai',
            }[performance.status]"></p>

            @resource(rk('penampilan-jurus', ResourceAction::Update))
                <div class="mt-4 flex justify-center gap-2">
                    <button type="button" x-show="performance.status === 'terjadwal'" x-on:click="mulai()"
                            class="rounded-silat bg-silat-biru px-5 py-2 text-[13px] text-silat-teks">
                        Mulai
                    </button>
                    <button type="button" x-show="performance.status === 'berlangsung'" x-on:click="berhenti()"
                            class="rounded-silat bg-silat-merah px-5 py-2 text-[13px] text-silat-teks">
                        Selesai
                    </button>
                </div>
            @endresource
        </div>

        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-silat bg-silat-panel p-3">
                <p class="text-[11px] text-silat-teks-redup">Median</p>
                <p class="silat-angka text-[20px] text-silat-teks" x-text="skor.median.toFixed(2)"></p>
            </div>
            <div class="rounded-silat bg-silat-panel p-3">
                <p class="text-[11px] text-silat-teks-redup">Pengurangan</p>
                <p class="silat-angka text-[20px] text-silat-teks" x-text="'−' + skor.total_pengurangan.toFixed(2)"></p>
            </div>
            <div class="rounded-silat bg-silat-panel p-3">
                <p class="text-[11px] text-silat-teks-redup">Skor Akhir</p>
                <p class="silat-angka text-[20px] font-medium text-silat-emas" x-text="performance.didiskualifikasi ? 'DQ' : skor.akhir.toFixed(2)"></p>
            </div>
        </div>

        <div class="rounded-silat bg-silat-panel p-4">
            <p class="mb-2 text-[13px] font-medium text-silat-teks">Nilai juri</p>
            <template x-if="nilaiJuri.length === 0">
                <p class="text-[13px] text-silat-teks-redup">Belum ada juri yang mengirim nilai.</p>
            </template>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <template x-for="n in nilaiJuri" :key="n.judge_user_id">
                    <div class="rounded-[4px] bg-silat-latar px-3 py-2 text-center">
                        <p class="truncate text-[11px] text-silat-teks-redup" x-text="n.nama"></p>
                        <p class="silat-angka text-[16px] text-silat-teks" x-text="n.value.toFixed(2)"></p>
                    </div>
                </template>
            </div>
        </div>

        <div class="rounded-silat bg-silat-panel p-4">
            <p class="mb-2 text-[13px] font-medium text-silat-teks">Pengurangan</p>

            @resource(rk('pengurangan-jurus', ResourceAction::Create))
                <form x-data="{ alasan: '' }" x-on:submit.prevent="penguranganPengawas(alasan).then((ok) => { if (ok) alasan = '' })"
                      class="mb-3 flex flex-wrap items-center gap-2">
                    <input type="text" x-model="alasan" placeholder="Alasan pengurangan 0.50" required
                           class="min-w-[220px] flex-1 rounded-silat border border-silat-garis bg-silat-latar px-2 py-1.5 text-[12px] text-silat-teks placeholder:text-silat-teks-samar">
                    <button type="submit" class="rounded-silat bg-silat-mati px-3 py-1.5 text-[12px] text-silat-teks">Catat −0.50</button>
                    <button type="button" x-show="! performance.didiskualifikasi" x-on:click="diskualifikasi()"
                            class="rounded-silat bg-red-500/20 px-3 py-1.5 text-[12px] text-red-300">Diskualifikasi</button>
                </form>
            @endresource

            <template x-if="pengurangan.length === 0">
                <p class="text-[13px] text-silat-teks-redup">Belum ada pengurangan.</p>
            </template>

            <div class="divide-y divide-silat-garis">
                <template x-for="d in pengurangan" :key="d.id">
                    <div class="flex items-center justify-between gap-3 py-2" x-data="{ alasanBatal: '' }">
                        <p class="text-[13px] text-silat-teks">
                            <span class="silat-angka" x-text="'−' + d.jumlah.toFixed(2)"></span>
                            · <span x-text="d.tier === 'juri' ? 'Juri' : 'Pengawas'"></span>
                            · <span x-text="d.alasan"></span>
                        </p>

                        @resource(rk('hasil-jurus', ResourceAction::Update))
                            <div class="flex shrink-0 items-center gap-1">
                                <input type="text" x-model="alasanBatal" placeholder="Alasan batal"
                                       class="w-32 rounded-silat border border-silat-garis bg-silat-latar px-2 py-1 text-[11px] text-silat-teks placeholder:text-silat-teks-samar">
                                <button type="button" x-on:click="batalkanPengurangan(d.id, alasanBatal)" x-bind:disabled="! alasanBatal"
                                        class="rounded-silat bg-silat-mati px-2 py-1 text-[11px] text-silat-teks disabled:opacity-40">
                                    Batal
                                </button>
                            </div>
                        @endresource
                    </div>
                </template>
            </div>
        </div>

        @resource(rk('hasil-jurus', ResourceAction::Approve))
            <div class="rounded-silat bg-silat-panel p-4 text-center">
                <template x-if="performance.status === 'selesai' && ! performance.ratified">
                    <button type="button" x-on:click="sahkan()" class="rounded-silat bg-silat-biru px-5 py-2 text-[13px] text-silat-teks">
                        Sahkan skor akhir
                    </button>
                </template>
                <p x-show="performance.ratified" class="text-[13px] text-emerald-300">Sudah disahkan.</p>
                <p x-show="performance.status !== 'selesai'" class="text-[13px] text-silat-teks-redup">
                    Pengesahan hanya bisa dilakukan setelah penampilan selesai.
                </p>
            </div>
        @endresource
    </div>
</x-layouts.silat>
