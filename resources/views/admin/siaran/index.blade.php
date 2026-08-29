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

        <x-si.callout varian="keterangan" judul="Cara memasangnya di vMix">
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
        </x-si.callout>

        @forelse ($gelanggang as $baris)
            <x-si.kartu :judul="$baris['arena']->name"
                        :keterangan="'Gelanggang #'.$baris['arena']->id.($baris['arena']->code ? ' · kode '.$baris['arena']->code : '')">
                <div class="divide-y divide-line">
                    @foreach ($baris['halaman'] as $halaman)
                        <div class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-ink">
                                    {{ $halaman['nama'] }}
                                    <x-si.badge>{{ $halaman['channel'] }}</x-si.badge>
                                </p>
                                <p class="truncate text-xs text-ink-muted">{{ $halaman['isi'] }}</p>
                                <p class="mt-1 truncate font-mono text-xs text-ink-secondary">{{ $halaman['url'] }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                                             x-on:click="salin('{{ $halaman['url'] }}')">
                                    <span x-show="tersalin !== '{{ $halaman['url'] }}'">Salin alamat</span>
                                    <span x-show="tersalin === '{{ $halaman['url'] }}'" x-cloak>Tersalin</span>
                                </x-si.tombol>

                                <x-si.tombol :tautan="$halaman['url']" varian="polos" ukuran="kecil" target="_blank" rel="noopener">
                                    Pratinjau
                                </x-si.tombol>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-si.kartu>
        @empty
            <x-si.kartu>
                <x-si.kosong judul="Belum ada gelanggang"
                             syarat="Overlay mengikuti partai aktif tiap gelanggang. Tambahkan gelanggang lebih dulu lewat menu Kejuaraan aktif → Gelanggang." />
            </x-si.kartu>
        @endforelse

        {{-- Bagan berdiri terpisah dari kelima halaman di atas: ia mengikuti
             kelas tanding, bukan gelanggang, dan dipakai sebagai tayangan
             pengisi di antara partai. --}}
        <x-si.kartu judul="Bagan untuk tayangan antar partai"
                    keterangan="Dipasang sebagai input tersendiri, bukan sebagai Overlay Channel">
            @if ($kelas->isEmpty())
                <x-si.kosong judul="Belum ada bagan"
                             syarat="Susun bagan lebih dulu lewat menu Pertandingan → Bagan." />
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
                                <x-si.tombol tipe="button" varian="kedua" ukuran="kecil" x-on:click="salin('{{ $url }}')">
                                    <span x-show="tersalin !== '{{ $url }}'">Salin alamat</span>
                                    <span x-show="tersalin === '{{ $url }}'" x-cloak>Tersalin</span>
                                </x-si.tombol>

                                <x-si.tombol :tautan="$url" varian="polos" ukuran="kecil" target="_blank" rel="noopener">
                                    Pratinjau
                                </x-si.tombol>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-si.kartu>
    </div>
</x-layouts.admin>
