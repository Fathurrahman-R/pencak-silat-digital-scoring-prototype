<x-layouts.silat :title="'Papan Jurus — '.$arena->name">
    {{--
        Papan gelanggang mode Jurus.

        Bukan salinan papan Tanding: keduanya tidak berbagi satu elemen pun.
        Papan Tanding punya dua bidang sudut, timer babak, petak babak, dan
        papan hasil per babak; yang ini punya satu penampil, nilai tiap juri,
        dua tingkat pengurangan, dan komparasi battle. Bahkan akar Alpine-nya
        berbeda factory. Satu berkas berisi dua bentuk sepanjang tiga ratus
        baris adalah dua view yang kebetulan tinggal bersama, dan yang di bawah
        akan membusuk tanpa ada yang tahu.

        Dibaca dari jarak meja gelanggang sampai tribun, jadi angkanya besar dan
        kendalinya tidak ada sama sekali -- yang menekan tombol adalah panel
        operator, di alamatnya sendiri.
    --}}
    @php
        $acuanMs = $performance->jurusEvent->waktuAcuanMs($performance->tahap);
        $acuanDetik = $acuanMs ? (int) round($acuanMs / 1000) : null;
        $jam = fn (int $d) => sprintf('%02d:%02d', intdiv($d, 60), $d % 60);
    @endphp

    <div x-data="jurusPanel(@js($config))" class="flex min-h-dvh flex-col gap-4 p-5 sm:p-7">

        <header class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="silat-angka text-[11px] tracking-[.14em] text-silat-teks-samar uppercase">
                    {{ $arena->name }} · Jurus
                </p>
                <p class="mt-1 text-[15px] text-silat-teks-redup">
                    {{ $performance->jurusEvent->nama() }}
                    · {{ \App\Support\Bagan\TahapBaganJurus::label($performance->tahap) }}
                </p>
            </div>

            <x-silat.indikator-koneksi class="shrink-0" />
        </header>

        {{-- Identitas penampil, sebesar yang terbaca dari tribun. Sudutnya
             dinyatakan sebagai bidang warna, arti yang sama dengan papan skor
             Tanding, overlay, dan bagan. --}}
        <div class="rounded-silat-besar p-6"
             @class([
                 'bg-silat-merah-dalam' => $performance->sudut === 'merah',
                 'bg-silat-biru-dalam' => $performance->sudut === 'biru',
                 'bg-silat-panel' => $performance->sudut === null,
             ])>
            @if ($performance->sudut)
                <p class="silat-angka text-[12px] tracking-[.14em] text-silat-teks-samar uppercase">
                    Sudut {{ $performance->sudut }}
                </p>
            @endif

            <p class="mt-1 text-[34px] leading-[1.15] font-semibold tracking-[-0.02em] text-silat-teks"
               x-text="peserta.nama"></p>
            <p class="mt-1 text-[18px] text-silat-teks-redup" x-text="peserta.kontingen"></p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(300px,420px)]">
            <div class="rounded-silat-besar bg-silat-panel p-6">
                <div class="flex items-end justify-between gap-6">
                    <div>
                        <p class="silat-angka text-[104px] leading-none font-medium text-silat-teks"
                           x-text="tampilWaktu"></p>
                        <p class="mt-1 text-[15px] text-silat-teks-redup" x-text="{
                            terjadwal: 'Belum dimulai', berlangsung: 'Sedang tampil', selesai: 'Selesai',
                        }[performance.status]"></p>
                    </div>

                    @if ($acuanDetik)
                        <div class="text-right">
                            <p class="silat-angka text-[28px] font-medium text-silat-teks">{{ $jam($acuanDetik) }}</p>
                            <p class="text-[13px] text-silat-teks-redup">waktu acuan</p>
                        </div>
                    @endif
                </div>

                <div class="mt-6 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-silat bg-silat-latar p-3">
                        <p class="text-[12px] text-silat-teks-redup">Median</p>
                        <p class="silat-angka text-[30px] text-silat-teks" x-text="skor.median.toFixed(2)"></p>
                    </div>
                    <div class="rounded-silat bg-silat-latar p-3">
                        <p class="text-[12px] text-silat-teks-redup">Pengurangan</p>
                        <p class="silat-angka text-[30px] text-silat-teks" x-text="'−' + skor.total_pengurangan.toFixed(2)"></p>
                    </div>
                    <div class="rounded-silat bg-silat-latar p-3">
                        <p class="text-[12px] text-silat-teks-redup">Skor Akhir</p>
                        <p class="silat-angka text-[30px] font-semibold text-silat-teks"
                           x-text="performance.didiskualifikasi ? 'DQ' : skor.akhir.toFixed(2)"></p>
                    </div>
                </div>
            </div>

            <div class="rounded-silat-besar bg-silat-panel p-5">
                <p class="silat-angka text-[11px] tracking-[.14em] text-silat-teks-redup uppercase">Nilai juri</p>

                <template x-if="nilaiJuri.length === 0">
                    <p class="mt-2 text-[15px] text-silat-teks-redup">Belum ada juri yang mengirim nilai.</p>
                </template>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <template x-for="n in nilaiJuri" :key="n.judge_user_id">
                        <div class="rounded-silat bg-silat-latar px-3 py-2 text-center">
                            <p class="truncate text-[12px] text-silat-teks-redup" x-text="n.nama"></p>
                            <p class="silat-angka text-[24px] text-silat-teks" x-text="n.value.toFixed(2)"></p>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Komparasi muncul sendiri begitu kedua sudut disahkan. Tidak ada
             tombol keputusan di papan: yang memutuskan Ketua Pertandingan, di
             panelnya sendiri. --}}
        <x-silat.komparasi-battle class="bg-silat-panel" />
    </div>
</x-layouts.silat>
