@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Timbang badan"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Timbang badan' => null,
                 ]">
    <div class="space-y-4">
        <x-ui.alert variant="info" title="Hasil ditetapkan saat penimbangan">
            Lolos atau tidaknya ditetapkan terhadap kelas yang berlaku saat ditimbang, lalu tidak
            dihitung ulang lagi. Penimbangan ulang dicatat sebagai baris baru — yang sebelumnya
            tetap tersimpan.
        </x-ui.alert>

        {{-- Kolom cari punya kedalaman (shadow-well), jadi ia duduk di kepala
             kartu daftarnya, bukan mengambang di atas latar. --}}
        <x-ui.card title="Peserta yang ditimbang">
            <x-slot:actions>
                <form method="GET" class="w-[230px] max-w-full">
                    <x-ui.input name="q" :value="$cari" placeholder="Cari nama atlet…" />
                </form>
            </x-slot:actions>

            @forelse ($registrations as $registration)
                @php
                    $athlete = $registration->athletes->first();
                    $kelas = $registration->weightClass;
                    $terakhir = $registration->weightIns->first();
                @endphp

                <div class="flex flex-wrap items-center gap-3 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[220px] flex-1">
                        <p class="font-medium text-ink">{{ $athlete?->name ?? '—' }}</p>
                        <p class="text-xs text-ink-muted">
                            {{ $registration->contingent->name }} · {{ $kelas->name }} ({{ $kelas->rentang() }})
                        </p>
                    </div>

                    <div class="min-w-[170px]">
                        @if ($terakhir)
                            <x-ui.badge :variant="$terakhir->passed ? 'success' : 'danger'">
                                {{ $terakhir->weight }} kg · {{ $terakhir->passed ? 'Lolos' : 'Tidak lolos' }}
                            </x-ui.badge>
                            <p class="mt-1 text-xs text-ink-muted">
                                {{ $terakhir->weighed_at->translatedFormat('d M, H:i') }}
                                @if ($registration->weightIns->count() > 1)
                                    · {{ $registration->weightIns->count() }} kali ditimbang
                                @endif
                            </p>
                        @else
                            <x-ui.badge variant="neutral">Belum ditimbang</x-ui.badge>
                            @if ($athlete?->weight_claim)
                                <p class="mt-1 text-xs text-ink-muted">Klaim {{ $athlete->weight_claim }} kg</p>
                            @endif
                        @endif
                    </div>

                    @resource(rk('timbang-badan', ResourceAction::Create))
                        <form method="POST"
                              action="{{ route('admin.turnamen.timbang.store', [$tournament, $registration]) }}"
                              class="flex items-end gap-2">
                            @csrf

                            <div class="w-[120px]">
                                <x-ui.input type="number" step="0.1" name="weight" label="Berat (kg)"
                                            :id="'berat-'.$registration->id" required />
                            </div>

                            <x-ui.button type="submit" size="sm">
                                {{ $terakhir ? 'Timbang ulang' : 'Catat' }}
                            </x-ui.button>
                        </form>
                    @endresource
                </div>
            @empty
                <x-ui.empty-state title="Tidak ada peserta yang perlu ditimbang"
                                  description="Pra Usia Dini dan Usia Dini 1 tidak menjalani timbang badan, dan kategori Jurus tidak mengenal kelas berat." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.admin>
