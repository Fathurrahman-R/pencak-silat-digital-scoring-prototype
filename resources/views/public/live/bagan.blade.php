<x-layouts.silat :title="'Bagan — '.$weightClass->name">
    <div class="mx-auto flex min-h-screen max-w-5xl flex-col gap-4 p-4">
        <header>
            <a href="{{ route('live.turnamen', $tournament) }}" class="text-[12px] text-silat-teks-redup hover:text-silat-teks">
                &larr; {{ $tournament->name }}
            </a>
            <h1 class="mt-1 text-[20px] font-medium text-silat-teks">
                {{ $weightClass->jenis_kelamin->label() }} {{ $weightClass->golongan_usia->label() }} — {{ $weightClass->name }}
            </h1>
        </header>

        @if (! $bracket)
            <p class="text-[13px] text-silat-teks-redup">Bagan kelas ini belum tersusun.</p>
        @else
            <x-silat.bagan-pohon :bracket="$bracket" :babak="$babak" />
        @endif
    </div>
</x-layouts.silat>
