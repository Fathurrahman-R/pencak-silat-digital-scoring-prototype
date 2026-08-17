<x-layouts.admin heading="Rekap & Laporan"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Rekap & Laporan' => null,
                 ]">
    <x-slot:actions>
        <x-ui.button :href="route('admin.turnamen.rekap.ekspor.medali', $tournament)" variant="secondary" size="sm">
            Ekspor medali (CSV)
        </x-ui.button>
        <x-ui.button :href="route('admin.turnamen.rekap.ekspor.peserta', $tournament)" variant="secondary" size="sm">
            Ekspor peserta (CSV)
        </x-ui.button>
        <x-ui.button :href="route('admin.turnamen.rekap.ekspor.jadwal', $tournament)" variant="secondary" size="sm">
            Ekspor jadwal (CSV)
        </x-ui.button>
    </x-slot:actions>

    <div class="space-y-4">
        <x-ui.card title="Peringkat Umum Kontingen">
            @if ($peringkatUmum->isEmpty())
                <x-ui.empty-state title="Belum ada medali" description="Medali muncul setelah hasil partai atau penampilan Jurus disahkan." />
            @else
                <div class="divide-y divide-line">
                    @foreach ($peringkatUmum as $i => $baris)
                        <div class="flex items-center gap-3 py-2.5">
                            <span class="w-6 text-center font-mono text-sm text-ink-muted">{{ $i + 1 }}</span>
                            <span class="flex-1 text-sm text-ink">{{ $baris['kontingen'] }}</span>
                            <span class="text-xs text-ink-muted">Emas {{ $baris['emas'] }}</span>
                            <span class="text-xs text-ink-muted">Perak {{ $baris['perak'] }}</span>
                            <span class="text-xs text-ink-muted">Perunggu {{ $baris['perunggu'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Juara Kelas Tanding">
            @if ($tanding->isEmpty())
                <x-ui.empty-state title="Belum ada kelas yang selesai" description="Juara muncul setelah hasil final disahkan dewan juri." />
            @else
                <div class="divide-y divide-line">
                    @foreach ($tanding as $baris)
                        <div class="py-2.5">
                            <p class="text-sm text-ink">
                                {{ $baris['kelas']->jenis_kelamin->label() }} {{ $baris['kelas']->golongan_usia->label() }} — {{ $baris['kelas']->name }}
                            </p>
                            <p class="text-xs text-ink-muted">
                                🥇 {{ $baris['emas']->athletes->pluck('name')->implode(', ') }} ({{ $baris['emas']->contingent->name }})
                                · 🥈 {{ $baris['perak']->athletes->pluck('name')->implode(', ') }} ({{ $baris['perak']->contingent->name }})
                                @if ($baris['perunggu']->isNotEmpty())
                                    · 🥉 {{ $baris['perunggu']->map(fn ($r) => $r->athletes->pluck('name')->implode(', ').' ('.$r->contingent->name.')')->implode(', ') }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Juara Nomor Jurus">
            @if ($jurus->isEmpty())
                <x-ui.empty-state title="Belum ada nomor yang selesai" description="Juara muncul setelah skor penampilan disahkan." />
            @else
                <div class="divide-y divide-line">
                    @foreach ($jurus as $baris)
                        <div class="py-2.5">
                            <p class="text-sm text-ink">{{ $baris['nomor']->nama() }}</p>
                            <p class="text-xs text-ink-muted">
                                @if ($baris['emas']) 🥇 {{ $baris['emas']->athletes->pluck('name')->implode(', ') }} ({{ $baris['emas']->contingent->name }}) @endif
                                @if ($baris['perak']) · 🥈 {{ $baris['perak']->athletes->pluck('name')->implode(', ') }} ({{ $baris['perak']->contingent->name }}) @endif
                                @if ($baris['perunggu']) · 🥉 {{ $baris['perunggu']->athletes->pluck('name')->implode(', ') }} ({{ $baris['perunggu']->contingent->name }}) @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
