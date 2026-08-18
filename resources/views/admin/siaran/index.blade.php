<x-layouts.admin heading="Overlay Siaran"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Overlay Siaran' => null,
                 ]">
    {{-- Alpine sederhana: satu penanda mana alamat yang barusan disalin,
         supaya operator tahu klik-nya jadi. Dikosongkan sendiri setelah
         sedetik; tidak ada state lain yang perlu diingat halaman ini. --}}
    <div x-data="{
            tersalin: null,
            async salin(url) {
                try {
                    await navigator.clipboard.writeText(url);
                } catch (e) {
                    const t = document.createElement('textarea');
                    t.value = url;
                    document.body.appendChild(t);
                    t.select();
                    document.execCommand('copy');
                    t.remove();
                }
                this.tersalin = url;
                setTimeout(() => { if (this.tersalin === url) this.tersalin = null; }, 1200);
            }
         }"
         class="space-y-4">

        <x-ui.alert variant="info" title="Cara memasangnya di vMix">
            <span class="block">
                <strong class="font-semibold">Add Input → Web Browser</strong>, tempelkan alamat di bawah,
                centang <strong class="font-semibold">Transparent Background</strong>, dan set resolusi
                <strong class="font-semibold">1920×1080</strong>. Pasang tiap halaman sebagai input terpisah supaya
                bisa ditayangkan dan disembunyikan sendiri-sendiri dari vMix.
            </span>
            <span class="mt-1.5 block">
                Halaman overlay hanya bisa dibuka dari jaringan lokal dan tidak pernah diteruskan lewat tunnel publik.
                Kalau vMix berjalan di mesin lain, ganti bagian host alamat dengan alamat LAN server ini.
            </span>
        </x-ui.alert>

        @forelse ($gelanggang as $baris)
            <x-ui.card :title="$baris['arena']->name"
                       :subtitle="'Gelanggang #'.$baris['arena']->id.($baris['arena']->code ? ' · kode '.$baris['arena']->code : '')">
                <div class="divide-y divide-line">
                    @foreach ($baris['halaman'] as $halaman)
                        <div class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-ink">
                                    {{ $halaman['nama'] }}
                                    <x-ui.badge size="sm">{{ $halaman['channel'] }}</x-ui.badge>
                                </p>
                                <p class="truncate text-xs text-ink-muted">{{ $halaman['isi'] }}</p>
                                <p class="mt-1 truncate font-mono text-xs text-ink-secondary">{{ $halaman['url'] }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-ui.button type="button" variant="secondary" size="sm"
                                             x-on:click="salin('{{ $halaman['url'] }}')">
                                    <span x-show="tersalin !== '{{ $halaman['url'] }}'">Salin alamat</span>
                                    <span x-show="tersalin === '{{ $halaman['url'] }}'" x-cloak>Tersalin</span>
                                </x-ui.button>

                                <x-ui.button :href="$halaman['url']" variant="ghost" size="sm" target="_blank" rel="noopener">
                                    Pratinjau
                                </x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @empty
            <x-ui.card>
                <x-ui.empty-state icon="tv-minimal"
                                  title="Belum ada gelanggang"
                                  description="Overlay mengikuti partai aktif tiap gelanggang. Tambahkan gelanggang lebih dulu lewat menu Kejuaraan aktif → Gelanggang." />
            </x-ui.card>
        @endforelse

        {{-- Bagan berdiri terpisah dari kelima halaman di atas: ia mengikuti
             kelas tanding, bukan gelanggang, dan dipakai sebagai tayangan
             pengisi di antara partai. --}}
        <x-ui.card title="Bagan untuk tayangan antar partai"
                   subtitle="Dipasang sebagai input tersendiri, bukan sebagai Overlay Channel">
            @if ($kelas->isEmpty())
                <x-ui.empty-state icon="network"
                                  title="Belum ada bagan"
                                  description="Susun bagan lebih dulu lewat menu Pertandingan → Bagan." />
            @else
                <div class="divide-y divide-line">
                    @foreach ($kelas as $satu)
                        @php $url = $bracketUrl.'?kelas='.$satu->id; @endphp

                        <div class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-ink">
                                    {{ $satu->jenis_kelamin->label() }} {{ $satu->golongan_usia->label() }} — {{ $satu->name }}
                                </p>
                                <p class="mt-1 truncate font-mono text-xs text-ink-secondary">{{ $url }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-ui.button type="button" variant="secondary" size="sm" x-on:click="salin('{{ $url }}')">
                                    <span x-show="tersalin !== '{{ $url }}'">Salin alamat</span>
                                    <span x-show="tersalin === '{{ $url }}'" x-cloak>Tersalin</span>
                                </x-ui.button>

                                <x-ui.button :href="$url" variant="ghost" size="sm" target="_blank" rel="noopener">
                                    Pratinjau
                                </x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
