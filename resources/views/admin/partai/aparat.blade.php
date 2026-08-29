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
        <x-si.kartu>
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
        </x-si.kartu>

        <x-si.callout varian="keterangan" judul="Jumlah juri mengikuti setelan peraturan kejuaraan">
            Kejuaraan ini memakai {{ $jumlahJuri }} juri per partai kategori tanding. Wasit tidak boleh
            merangkap juri.
        </x-si.callout>

        @resource(rk('penugasan-aparat', ResourceAction::Assign))
            {{--
                Yang sedang bertugas di gelanggang lain tetap muncul di daftar,
                tapi mati dan bersebab.

                Menghapusnya sama sekali akan membuat panitia yang mencari nama
                dan tidak menemukannya mengira orangnya belum terdaftar, lalu
                membuat akun kedua. Menampilkannya tanpa keterangan membuatnya
                memilih orang yang tidak akan datang.
            --}}
            @php
                $opsiAparat = function ($daftar, $terpilih) use ($bentrok) {
                    return collect($daftar)->map(fn ($nama, $id) => [
                        'id' => $id,
                        'nama' => $nama,
                        'terpilih' => (int) $terpilih === (int) $id,
                        'sebab' => $bentrok[$id] ?? null,
                    ]);
                };
            @endphp

            @php
                $sudahBentrok = collect([$wasitSaatIni?->user_id])
                    ->merge($juriSaatIni->pluck('user_id'))
                    ->filter()
                    ->filter(fn ($id) => isset($bentrok[$id]))
                    ->values();
            @endphp

            {{--
                Aparat yang SUDAH ditugaskan di partai ini tetap bisa dipilih —
                kalau dimatikan, peramban tidak mengirim nilainya sama sekali
                dan formulirnya gagal dengan alasan yang keliru ("wasit wajib
                diisi").

                Tapi kalau ia bentrok, penjaga server akan menolak penyimpanan.
                Menyatakannya di sini menghemat satu kali tekan-lalu-ditolak.
            --}}
            @if ($sudahBentrok->isNotEmpty())
                <x-si.callout varian="bahaya" judul="Aparat yang sudah tertugas di partai ini sedang bertugas di tempat lain">
                    Penugasan ini tidak bisa disimpan ulang sebelum yang bentrok diganti:
                    @foreach ($sudahBentrok as $id)
                        <span class="block">
                            {{ $wasitTersedia[$id] ?? $juriTersedia[$id] ?? 'Aparat' }} — {{ $bentrok[$id] }}.
                        </span>
                    @endforeach
                </x-si.callout>
            @endif

            <x-si.kartu judul="Tetapkan aparat"
                        keterangan="Satu orang tidak bisa berdiri di dua gelanggang sekaligus. Yang sedang bertugas di jam berdekatan tampil mati beserta sebabnya.">
                <form method="POST" action="{{ route('admin.turnamen.partai.aparat.store', [$tournament, $match]) }}"
                      class="flex flex-col gap-4">
                    @csrf

                    <div class="flex flex-col gap-1.5">
                        <label for="wasit_id" class="text-[14px] font-semibold text-ink">
                            Wasit <span class="font-normal text-ink-muted">— wajib diisi</span>
                        </label>
                        <select id="wasit_id" name="wasit_id" required
                                class="h-11 w-full rounded-[var(--radius)] border border-line-strong bg-surface-raised px-3 text-[15px] text-ink">
                            <option value="">Pilih wasit…</option>
                            @foreach ($opsiAparat($wasitTersedia, $wasitSaatIni?->user_id) as $opsi)
                                <option value="{{ $opsi['id'] }}"
                                        @selected($opsi['terpilih'])
                                        @disabled($opsi['sebab'] !== null && ! $opsi['terpilih'])>
                                    {{ $opsi['nama'] }}{{ $opsi['sebab'] ? ' — '.$opsi['sebab'] : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @for ($nomor = 1; $nomor <= $jumlahJuri; $nomor++)
                        @php($terpilihJuri = $juriSaatIni->firstWhere('number', $nomor)?->user_id)

                        <div class="flex flex-col gap-1.5">
                            <label for="juri-{{ $nomor }}" class="text-[14px] font-semibold text-ink">
                                Juri {{ $nomor }} <span class="font-normal text-ink-muted">— wajib diisi</span>
                            </label>
                            <select id="juri-{{ $nomor }}" name="juri_id[{{ $nomor - 1 }}]" required
                                    class="h-11 w-full rounded-[var(--radius)] border border-line-strong bg-surface-raised px-3 text-[15px] text-ink">
                                <option value="">Pilih juri…</option>
                                @foreach ($opsiAparat($juriTersedia, $terpilihJuri) as $opsi)
                                    <option value="{{ $opsi['id'] }}"
                                            @selected($opsi['terpilih'])
                                            @disabled($opsi['sebab'] !== null && ! $opsi['terpilih'])>
                                        {{ $opsi['nama'] }}{{ $opsi['sebab'] ? ' — '.$opsi['sebab'] : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endfor

                    <div class="flex items-center gap-2 border-t border-line pt-3">
                        <x-si.tombol tipe="submit">Simpan penugasan</x-si.tombol>
                        <a href="{{ route('admin.turnamen.jadwal.index', $tournament) }}"
                           class="inline-flex h-11 items-center rounded-[var(--radius)] border border-line px-4 text-[15px] font-medium text-ink">
                            Kembali ke jadwal
                        </a>
                    </div>
                </form>
            </x-si.kartu>
        @else
            <x-si.kartu judul="Aparat bertugas">
                <p class="text-sm text-ink">Wasit: {{ $wasitSaatIni?->user->name ?? '—' }}</p>
                @foreach ($juriSaatIni as $juri)
                    <p class="text-sm text-ink">{{ $juri->sebutan() }}: {{ $juri->user->name }}</p>
                @endforeach
            </x-si.kartu>
        @endresource
    </div>
</x-layouts.admin>
