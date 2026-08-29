@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin :heading="$jurusEvent->nama()"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     $jurusEvent->nama() => null,
                 ]">
    <div class="space-y-4">
        @resource(rk('penampilan-jurus', ResourceAction::Create))
            <x-si.kartu judul="Buat penampilan">
                <p class="mb-3 text-sm text-ink-muted">
                    Membuat satu penampilan untuk tiap pendaftaran terverifikasi pada nomor ini yang belum
                    punya penampilan di tahap yang dipilih.
                </p>
                <form method="POST" action="{{ route('admin.turnamen.jurus.generate', [$tournament, $jurusEvent]) }}" class="flex items-center gap-2">
                    @csrf
                    <select name="tahap" class="rounded-md border border-line bg-surface px-3 py-2 text-sm">
                        <option value="penyisihan">Penyisihan</option>
                        <option value="semifinal">Semifinal</option>
                        <option value="final" selected>Final</option>
                    </select>
                    <x-si.tombol tipe="submit" varian="utama" ukuran="kecil">Buat penampilan</x-si.tombol>
                </form>
            </x-si.kartu>
        @endresource

        <x-si.kartu judul="Peringkat sementara">
            @if ($peringkat->isEmpty())
                <x-si.kosong judul="Belum ada penampilan"
                             syarat="Buat penampilan lebih dulu di atas." />
            @else
                <div class="divide-y divide-line">
                    @foreach ($peringkat as $i => $p)
                        <div class="flex flex-wrap items-center gap-3 py-3">
                            <span class="w-6 shrink-0 text-center font-mono text-sm text-ink-muted">{{ $i + 1 }}</span>

                            <div class="min-w-[220px] flex-1">
                                <p class="text-sm text-ink">
                                    {{ $p->registration->athletes->pluck('name')->implode(', ') }}
                                </p>
                                <p class="text-xs text-ink-muted">
                                    {{ $p->registration->contingent->name }} · {{ ucfirst($p->tahap) }}
                                    · {{ match($p->status) { 'terjadwal' => 'Terjadwal', 'berlangsung' => 'Berlangsung', default => 'Selesai' } }}
                                    @if ($p->disahkan()) · <span class="text-success">Sah</span> @endif
                                </p>
                            </div>

                            <span class="font-mono text-sm text-ink">
                                {{ $p->didiskualifikasi ? 'DQ' : number_format(app(\App\Support\Jurus\JurusScoreCalculator::class)->skorAkhir($p), 2) }}
                            </span>

                            @resource(rk('penampilan-jurus', ResourceAction::View))
                                <x-si.tombol :tautan="route('admin.turnamen.jurus.penampilan.operator', [$tournament, $p])" varian="kedua" ukuran="kecil">
                                    Operator
                                </x-si.tombol>
                            @endresource

                            @resource(rk('penilaian', ResourceAction::Create))
                                <x-si.tombol :tautan="route('admin.turnamen.jurus.penampilan.juri', [$tournament, $p])" varian="kedua" ukuran="kecil">
                                    Juri
                                </x-si.tombol>
                            @endresource
                        </div>
                    @endforeach
                </div>
            @endif
        </x-si.kartu>
    </div>
</x-layouts.admin>
