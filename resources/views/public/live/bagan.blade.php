<x-layouts.silat permukaan="publik" :title="'Bagan — '.$weightClass->name">
    {{--
        Bagan publik memakai POHON yang sama persis dengan bagan panitia
        (<x-si.pohon-bagan>, koordinatnya dari App\Support\Bagan\PohonBagan),
        bukan daftar per tahap seperti sebelumnya.

        Penonton tribun, panitia, dan pemirsa siaran membaca satu undian yang
        sama. Tiga gambar berbeda untuk satu undian membuat ketiganya berdebat
        tentang bagan yang sebenarnya identik -- "yang di layar panitia beda
        dengan yang di HP saya" adalah pertanyaan yang tidak seharusnya ada.

        Di HP tegak, pohonnya digulir mendatar di dalam pembungkusnya sendiri;
        halamannya tidak ikut bergeser.
    --}}
    <div class="mx-auto flex min-h-screen w-full max-w-[1400px] flex-col gap-6 p-4">
        <header>
            <a href="{{ route('live.turnamen', $tournament) }}" class="text-[12px] text-silat-teks-redup">
                &larr; {{ $tournament->name }}
            </a>
            <h1 class="mt-1 text-[22px] leading-tight font-medium text-silat-teks">
                {{ $weightClass->jenis_kelamin->label() }} {{ $weightClass->golongan_usia->label() }} — {{ $weightClass->name }}
            </h1>
        </header>

        @if (! $bracket)
            <p class="text-[14px] leading-relaxed text-silat-teks-redup">
                Bagan kelas ini belum tersusun. Ia terbit setelah verifikasi pendaftaran dan timbang badan selesai.
            </p>
        @else
            {{-- Bidang kertas, sama seperti kartu yang memuat bagan di panel
                 panitia: pohonnya digambar dengan token panitia, dan token itu
                 menuntut latar terang di belakangnya. --}}
            <div class="rounded-silat-besar bg-surface-raised p-4 sm:p-5">
                <x-si.pohon-bagan :pohon="$pohon" />
            </div>
        @endif
    </div>
</x-layouts.silat>
