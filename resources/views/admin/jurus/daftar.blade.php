<x-layouts.admin heading="Kategori Jurus"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kategori Jurus' => null,
                 ]">
    <x-si.kartu judul="Nomor Jurus">
        @if ($jurusEvents->isEmpty())
            <x-si.kosong judul="Belum ada nomor Jurus"
                         syarat="Nomor tersusun otomatis dari naskah 2025 saat turnamen dibuat." />
        @else
            <div class="divide-y divide-line">
                @foreach ($jurusEvents as $event)
                    <div class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-[260px] flex-1">
                            <p class="text-sm text-ink">{{ $event->nama() }}</p>
                            <p class="text-xs text-ink-muted">
                                {{ $event->registrations_sah_count }} pendaftaran terverifikasi ·
                                {{ $event->performances_count }} penampilan dibuat
                            </p>
                        </div>

                        <x-si.tombol :tautan="route('admin.turnamen.jurus.index', [$tournament, $event])" varian="kedua" ukuran="kecil">
                            Kelola penampilan
                        </x-si.tombol>
                    </div>
                @endforeach
            </div>
        @endif
    </x-si.kartu>
</x-layouts.admin>
