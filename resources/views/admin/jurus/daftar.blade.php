<x-layouts.admin heading="Kategori Jurus"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kategori Jurus' => null,
                 ]">
    <x-ui.card title="Nomor Jurus">
        @if ($jurusEvents->isEmpty())
            <x-ui.empty-state title="Belum ada nomor Jurus"
                               description="Nomor tersusun otomatis dari naskah 2025 saat turnamen dibuat." />
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

                        <x-ui.button :href="route('admin.turnamen.jurus.index', [$tournament, $event])" variant="secondary" size="sm">
                            Kelola penampilan
                        </x-ui.button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>
</x-layouts.admin>
