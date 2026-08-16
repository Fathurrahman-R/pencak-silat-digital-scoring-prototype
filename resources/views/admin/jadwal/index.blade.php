@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Jadwal"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Jadwal' => null,
                 ]">
    <div class="space-y-4">
        <x-ui.alert variant="info" title="Hanya partai yang kedua sudutnya sudah pasti yang muncul di sini">
            Partai yang masih menunggu pemenang babak sebelumnya belum bisa dijadwalkan. Satu atlet
            yang sudah dijadwalkan di satu gelanggang akan ditolak bila dijadwalkan ulang di gelanggang
            lain pada waktu yang berdekatan.
        </x-ui.alert>

        @foreach ($arenas as $arena)
            <x-ui.card :title="$arena->name">
                @forelse ($arena->matches as $partai)
                    <div class="flex flex-wrap items-center gap-3 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                        <span class="w-6 shrink-0 text-center font-mono text-sm text-ink-muted">
                            {{ $partai->order_in_arena }}
                        </span>

                        <div class="min-w-[240px] flex-1">
                            <p class="text-sm text-ink">
                                {{ $partai->red?->athletes->pluck('name')->implode(', ') }}
                                <span class="text-ink-muted">vs</span>
                                {{ $partai->blue?->athletes->pluck('name')->implode(', ') }}
                            </p>
                            <p class="text-xs text-ink-muted">
                                {{ $partai->bracket->weightClass->jenis_kelamin->label() }}
                                {{ $partai->bracket->weightClass->golongan_usia->label() }} — {{ $partai->bracket->weightClass->name }}
                                · {{ $partai->bracket->namaBabak($partai->round) }}
                            </p>
                        </div>

                        <span class="font-mono text-sm text-ink-muted">
                            {{ $partai->scheduled_at?->translatedFormat('d M, H:i') }}
                        </span>

                        @resource(rk('penugasan-aparat', ResourceAction::View))
                            <x-ui.button :href="route('admin.turnamen.partai.aparat.show', [$tournament, $partai])"
                                         variant="secondary" size="xs">
                                Aparat
                            </x-ui.button>
                        @endresource

                        @resource(rk('partai', ResourceAction::View))
                            <x-ui.button :href="route('admin.turnamen.partai.operator', [$tournament, $partai])"
                                         variant="secondary" size="xs">
                                Operator
                            </x-ui.button>
                        @endresource

                        @resource(rk('hukuman', ResourceAction::View))
                            <x-ui.button :href="route('admin.turnamen.partai.wasit', [$tournament, $partai])"
                                         variant="secondary" size="xs">
                                Wasit
                            </x-ui.button>
                        @endresource

                        @resource(rk('hasil-partai', ResourceAction::View))
                            <x-ui.button :href="route('admin.turnamen.partai.dewan-juri', [$tournament, $partai])"
                                         variant="secondary" size="xs">
                                Dewan juri
                            </x-ui.button>
                        @endresource

                        @resource(rk('jadwal', ResourceAction::Assign))
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('admin.turnamen.jadwal.urutkan', [$tournament, $partai]) }}">
                                    @csrf
                                    <input type="hidden" name="arah" value="naik">
                                    <x-ui.button type="submit" variant="secondary" size="xs" title="Naikkan urutan">
                                        <x-ui.icon name="chevron-up" class="h-4 w-4" />
                                    </x-ui.button>
                                </form>

                                <form method="POST" action="{{ route('admin.turnamen.jadwal.urutkan', [$tournament, $partai]) }}">
                                    @csrf
                                    <input type="hidden" name="arah" value="turun">
                                    <x-ui.button type="submit" variant="secondary" size="xs" title="Turunkan urutan">
                                        <x-ui.icon name="chevron-down" class="h-4 w-4" />
                                    </x-ui.button>
                                </form>

                                <form method="POST" action="{{ route('admin.turnamen.jadwal.lepas', [$tournament, $partai]) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="xs" title="Lepas jadwal">
                                        <x-ui.icon name="x" class="h-4 w-4" />
                                    </x-ui.button>
                                </form>
                            </div>
                        @endresource
                    </div>
                @empty
                    <p class="py-2 text-sm text-ink-muted">Belum ada partai dijadwalkan ke gelanggang ini.</p>
                @endforelse
            </x-ui.card>
        @endforeach

        <x-ui.card title="Belum dijadwalkan">
            @forelse ($belumDijadwalkan as $partai)
                <div class="flex flex-wrap items-center gap-3 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[240px] flex-1">
                        <p class="text-sm text-ink">
                            {{ $partai->red?->athletes->pluck('name')->implode(', ') }}
                            <span class="text-ink-muted">vs</span>
                            {{ $partai->blue?->athletes->pluck('name')->implode(', ') }}
                        </p>
                        <p class="text-xs text-ink-muted">
                            {{ $partai->bracket->weightClass->jenis_kelamin->label() }}
                            {{ $partai->bracket->weightClass->golongan_usia->label() }} — {{ $partai->bracket->weightClass->name }}
                            · {{ $partai->bracket->namaBabak($partai->round) }}
                        </p>
                    </div>

                    @resource(rk('penugasan-aparat', ResourceAction::View))
                        <x-ui.button :href="route('admin.turnamen.partai.aparat.show', [$tournament, $partai])"
                                     variant="secondary" size="xs">
                            Aparat
                        </x-ui.button>
                    @endresource

                    @resource(rk('jadwal', ResourceAction::Assign))
                        <form method="POST" action="{{ route('admin.turnamen.jadwal.tetapkan', [$tournament, $partai]) }}"
                              class="flex flex-wrap items-end gap-2">
                            @csrf

                            <div class="w-44">
                                <x-ui.select name="arena_id" :options="$arenas->pluck('name', 'id')" placeholder="Gelanggang" />
                            </div>

                            <div class="w-52">
                                <x-ui.input type="datetime-local" name="scheduled_at" />
                            </div>

                            <x-ui.button type="submit" variant="secondary" size="sm">Jadwalkan</x-ui.button>
                        </form>
                    @endresource
                </div>
            @empty
                <x-ui.empty-state title="Semua partai yang siap sudah dijadwalkan"
                                  description="Partai yang masih menunggu pemenang babak sebelumnya akan muncul di sini setelah lawannya pasti." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.admin>
