@php
    use App\Enums\ResourceAction;
    use App\Models\MatchOfficial;

    $wasitSaatIni = $match->officials->firstWhere('role', MatchOfficial::ROLE_WASIT);
    $juriSaatIni = $match->officials->where('role', MatchOfficial::ROLE_JURI)->sortBy('number');
@endphp

<x-layouts.admin heading="Aparat partai"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Jadwal' => route('admin.turnamen.jadwal.index', $tournament),
                     'Aparat' => null,
                 ]">
    <div class="space-y-4">
        <x-ui.card>
            <p class="font-medium text-ink">
                {{ $match->red?->athletes->pluck('name')->implode(', ') ?? 'Menunggu babak sebelumnya' }}
                <span class="text-ink-muted">vs</span>
                {{ $match->blue?->athletes->pluck('name')->implode(', ') ?? 'Menunggu babak sebelumnya' }}
            </p>
            <p class="text-xs text-ink-muted">
                {{ $match->bracket->weightClass->jenis_kelamin->label() }}
                {{ $match->bracket->weightClass->golongan_usia->label() }} — {{ $match->bracket->weightClass->name }}
                · {{ $match->bracket->namaBabak($match->round) }}
            </p>
        </x-ui.card>

        <x-ui.alert variant="info" title="Jumlah juri mengikuti setelan peraturan kejuaraan">
            Kejuaraan ini memakai {{ $jumlahJuri }} juri per partai kategori tanding. Wasit tidak boleh
            merangkap juri.
        </x-ui.alert>

        @resource(rk('penugasan-aparat', ResourceAction::Assign))
            <x-ui.card title="Tetapkan aparat">
                <form method="POST" action="{{ route('admin.turnamen.partai.aparat.store', [$tournament, $match]) }}"
                      class="space-y-4">
                    @csrf

                    <x-ui.select name="wasit_id" label="Wasit" :options="$wasitTersedia" :selected="$wasitSaatIni?->user_id"
                                 placeholder="Pilih wasit" />

                    @for ($nomor = 1; $nomor <= $jumlahJuri; $nomor++)
                        <x-ui.select :name="'juri_id['.($nomor - 1).']'" :label="'Juri '.$nomor" :options="$juriTersedia"
                                     :selected="$juriSaatIni->firstWhere('number', $nomor)?->user_id"
                                     :id="'juri-'.$nomor" placeholder="Pilih juri" />
                    @endfor

                    <x-ui.button type="submit">Simpan</x-ui.button>
                </form>
            </x-ui.card>
        @else
            <x-ui.card title="Aparat bertugas">
                <p class="text-sm text-ink">Wasit: {{ $wasitSaatIni?->user->name ?? '—' }}</p>
                @foreach ($juriSaatIni as $juri)
                    <p class="text-sm text-ink">{{ $juri->sebutan() }}: {{ $juri->user->name }}</p>
                @endforeach
            </x-ui.card>
        @endresource
    </div>
</x-layouts.admin>
