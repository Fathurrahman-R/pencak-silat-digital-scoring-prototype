@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Bagan"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Bagan' => null,
                 ]">
    <div class="space-y-4">
        <x-ui.alert variant="info" title="Bagan disusun dari peserta yang sudah disahkan">
            Peserta yang berkasnya belum lengkap atau tagihan kontingennya belum lunas tidak ikut
            masuk hitungan, meski sudah mendaftar. Susun bagan setelah verifikasi dan timbang badan
            selesai untuk kelas yang bersangkutan.
        </x-ui.alert>

        <x-ui.card title="Kelas tanding">
            @forelse ($kelas as $k)
                @php
                    $bracket = $k->bracket;
                    $bisaSusun = $k->peserta_sah >= 2;
                @endphp

                <div class="flex flex-wrap items-center gap-4 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[220px] flex-1">
                        <p class="font-medium text-ink">
                            {{ $k->jenis_kelamin->label() }} {{ $k->golongan_usia->label() }} — {{ $k->name }}
                        </p>
                        <p class="text-xs text-ink-muted">{{ $k->rentang() }}</p>
                    </div>

                    <span class="text-sm text-ink-muted">{{ $k->peserta_sah }} peserta sah</span>

                    @if ($bracket && $bracket->terkunci())
                        <x-ui.badge variant="success">Terkunci · {{ $bracket->size }} tempat</x-ui.badge>
                    @elseif ($bracket)
                        <x-ui.badge variant="warning">Draf · {{ $bracket->size }} tempat</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">Belum disusun</x-ui.badge>
                    @endif

                    <div class="flex gap-1">
                        @if ($bracket)
                            <x-ui.button :href="route('admin.turnamen.bagan.show', [$tournament, $k])" variant="secondary" size="xs">
                                Lihat
                            </x-ui.button>
                        @endif

                        @resource(rk('bagan', ResourceAction::Create))
                            @if (! $bracket?->terkunci())
                                <form method="POST" action="{{ route('admin.turnamen.bagan.susun', [$tournament, $k]) }}"
                                      x-on:submit="{{ $bracket ? "confirm('Bagan {$k->name} sudah ada — susun ulang dari peserta sah saat ini?') || event.preventDefault()" : '' }}">
                                    @csrf
                                    <x-ui.button type="submit" size="xs" :disabled="! $bisaSusun">
                                        {{ $bracket ? 'Susun ulang' : 'Susun bagan' }}
                                    </x-ui.button>
                                </form>
                            @endif
                        @endresource
                    </div>
                </div>
            @empty
                <x-ui.empty-state title="Belum ada kelas tanding"
                                  description="Kelas tanding diturunkan dari naskah peraturan saat kejuaraan dibuat." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.admin>
