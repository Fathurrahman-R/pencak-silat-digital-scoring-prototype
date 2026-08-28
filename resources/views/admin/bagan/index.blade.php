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
            <x-slot:actions>
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    @if ($tampil !== 'terpakai')
                        <input type="hidden" name="tampil" value="{{ $tampil }}">
                    @endif

                    <x-ui.input name="q" :value="$cari" placeholder="Cari kelas…" class="w-[200px]" />
                </form>
            </x-slot:actions>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <x-ui.filter-chips param="tampil" all="Semua kelas" :current="$tampil === 'semua' ? null : $tampil" :options="[
                    'terpakai' => 'Ada peserta atau bagan',
                    'tersusun' => 'Sudah disusun',
                ]" />

                <p class="text-xs text-ink-muted">
                    Menampilkan {{ $kelas->count() }} dari {{ $jumlahSemua }} kelas.
                    @if ($tampil === 'terpakai' && $jumlahSemua > $jumlahTerpakai)
                        {{ $jumlahSemua - $jumlahTerpakai }} kelas tanpa peserta disembunyikan —
                        pilih <span class="text-ink">Semua kelas</span> untuk melihatnya.
                    @endif
                </p>
            </div>

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
                @if ($cari !== '' || $tampil !== 'semua')
                    <x-ui.empty-state title="Tidak ada kelas yang cocok"
                                      description="Belum ada kelas dengan peserta sah atau bagan. Ubah penyaring di atas untuk melihat seluruh kelas yang diturunkan dari naskah." />
                @else
                    <x-ui.empty-state title="Belum ada kelas tanding"
                                      description="Kelas tanding diturunkan dari naskah peraturan saat kejuaraan dibuat." />
                @endif
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.admin>
