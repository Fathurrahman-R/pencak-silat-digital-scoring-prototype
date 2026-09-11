@php use App\Enums\ResourceAction; @endphp

<x-layouts.silat :title="'Ketua Pertandingan — Jurus '.$arena->name">
    {{--
        Panel Ketua Pertandingan untuk gelanggang yang sedang menayangkan Jurus.

        Isinya sengaja sempit: skor berjalan penampilan yang tayang, dan
        perbandingan kedua sudut begitu keduanya disahkan. Ketua tidak menekan
        timer, tidak mengirim nilai, dan tidak menjatuhkan pengurangan -- semua
        itu ada di panel operator dan panel juri, dan menyalinnya ke sini hanya
        menambah tombol yang salah tekan di layar yang dipegang sambil berdiri.

        Yang MEMANG tugasnya ada di sini: menetapkan pemenang battle, termasuk
        memutus skor yang seri. Tombolnya berdiri tepat di bawah angka yang
        jadi dasarnya, bukan di halaman lain yang harus dibuka lebih dulu.
    --}}
    <div x-data="jurusPanel(@js($config))" class="flex min-h-dvh flex-col gap-4 p-5">

        <header class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="silat-angka text-[11px] tracking-[.14em] text-silat-teks-samar uppercase">
                    Ketua Pertandingan · {{ $arena->name }} · Jurus
                </p>
                <h1 class="mt-1 text-[20px] font-semibold tracking-[-0.02em] text-silat-teks" x-text="peserta.nama"></h1>
                <p class="text-[13px] text-silat-teks-redup" x-text="peserta.kontingen"></p>
                <p class="text-[13px] text-silat-teks-redup">
                    {{ $performance->jurusEvent->nama() }}
                    · {{ \App\Support\Bagan\TahapBaganJurus::label($performance->tahap) }}
                    @if ($performance->sudut)
                        · Sudut {{ $performance->sudut }}
                    @endif
                </p>
            </div>

            <x-silat.indikator-koneksi class="shrink-0" />
        </header>

        <p x-show="galat" x-text="galat" x-cloak class="rounded-silat bg-red-500/15 px-4 py-2 text-[13px] text-red-300"></p>
        <p x-show="pesan" x-text="pesan" x-cloak class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-silat bg-silat-panel p-4">
                <p class="text-[12px] text-silat-teks-redup">Median</p>
                <p class="silat-angka text-[26px] text-silat-teks" x-text="skor.median.toFixed(2)"></p>
            </div>
            <div class="rounded-silat bg-silat-panel p-4">
                <p class="text-[12px] text-silat-teks-redup">Pengurangan</p>
                <p class="silat-angka text-[26px] text-silat-teks" x-text="'−' + skor.total_pengurangan.toFixed(2)"></p>
            </div>
            <div class="rounded-silat bg-silat-panel p-4">
                <p class="text-[12px] text-silat-teks-redup">Skor Akhir</p>
                <p class="silat-angka text-[26px] font-semibold text-silat-teks"
                   x-text="performance.didiskualifikasi ? 'DQ' : skor.akhir.toFixed(2)"></p>
            </div>
        </div>

        {{-- Pilihan sudut saat seri hanya digambar untuk yang berhak
             mengesahkan hasil. Tombol yang pasti ditolak server adalah tombol
             yang membuat penekannya mengira sistemnya rusak. --}}
        @resource(rk('hasil-jurus', ResourceAction::Approve))
            <x-silat.komparasi-battle :keputusan="true" class="bg-silat-panel" />
        @elseresource
            <x-silat.komparasi-battle class="bg-silat-panel" />
        @endresource
    </div>
</x-layouts.silat>
