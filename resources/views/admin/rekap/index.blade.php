<x-layouts.admin heading="Rekap & Laporan"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Rekap & Laporan' => null,
                 ]">
    <x-slot:actions>
        <x-si.tombol :tautan="route('admin.turnamen.rekap.ekspor.medali', $tournament)" varian="kedua" ukuran="kecil">
            Ekspor medali (CSV)
        </x-si.tombol>
        <x-si.tombol :tautan="route('admin.turnamen.rekap.ekspor.medali-pdf', $tournament)" varian="kedua" ukuran="kecil">
            Cetak medali (PDF)
        </x-si.tombol>
        <x-si.tombol :tautan="route('admin.turnamen.rekap.ekspor.peserta', $tournament)" varian="kedua" ukuran="kecil">
            Ekspor peserta (CSV)
        </x-si.tombol>
        <x-si.tombol :tautan="route('admin.turnamen.rekap.ekspor.jadwal', $tournament)" varian="kedua" ukuran="kecil">
            Ekspor jadwal (CSV)
        </x-si.tombol>
    </x-slot:actions>

    <div class="space-y-4">
        {{--
            Kolom berjudul dengan angka rata kanan, bukan label yang diulang di
            tiap baris ("Emas 5 Perak 3 Perunggu 2").

            Label berulang menuntut pembacanya membaca kata sebelum angka pada
            setiap baris, dan angkanya tidak pernah lurus antar-baris — padahal
            membandingkan perolehan antar kontingen justru satu-satunya gunanya
            tabel peringkat. Kolom jumlah baru: sebelumnya pembaca harus
            menjumlah tiga angka sendiri.
        --}}
        <x-si.kartu judul="Peringkat umum kontingen">
            @if ($peringkatUmum->isEmpty())
                <x-si.kosong judul="Belum ada medali"
                             syarat="Medali muncul setelah hasil partai atau penampilan Jurus disahkan Dewan Wasit Juri." />
            @else
                <div class="flex items-baseline justify-between gap-4 border-b-2 border-line pb-2">
                    <span class="text-[11px] tracking-[.1em] text-ink-muted uppercase">Kontingen</span>
                    <div class="flex items-baseline gap-4 text-[11px] tracking-[.1em] text-ink-muted uppercase">
                        <span class="w-12 text-right">Emas</span>
                        <span class="w-12 text-right">Perak</span>
                        <span class="w-14 text-right">Perunggu</span>
                        <span class="w-12 text-right">Jumlah</span>
                    </div>
                </div>

                @foreach ($peringkatUmum as $i => $baris)
                    @php($juara = $i === 0)
                    <div class="flex items-baseline gap-4 border-b border-line py-2.5 last:border-0">
                        <span @class(['w-6 font-mono text-[14px] tabular-nums', 'font-semibold text-ink' => $juara, 'text-ink-muted' => ! $juara])>{{ $i + 1 }}</span>
                        <span @class(['min-w-0 flex-1 truncate text-[15px] text-ink', 'font-semibold' => $juara])>{{ $baris['kontingen'] }}</span>
                        <span class="w-12 text-right font-mono text-[15px] font-medium text-ink tabular-nums">{{ $baris['emas'] }}</span>
                        <span class="w-12 text-right font-mono text-[15px] text-ink-secondary tabular-nums">{{ $baris['perak'] }}</span>
                        <span class="w-14 text-right font-mono text-[15px] text-ink-secondary tabular-nums">{{ $baris['perunggu'] }}</span>
                        <span class="w-12 text-right font-mono text-[15px] font-medium text-ink tabular-nums">{{ $baris['emas'] + $baris['perak'] + $baris['perunggu'] }}</span>
                    </div>
                @endforeach
            @endif
        </x-si.kartu>

        {{--
            Emoji medali dibuang. Ia dirender berbeda di tiap sistem — datar di
            Windows, timbul di iOS, kadang kotak kosong — dan tidak pernah jadi
            bagian sistem desain, jadi tidak ada satu pun angka kontras yang
            berlaku untuknya. Halaman medali publik sudah melepasnya lebih dulu;
            layar panitia yang dipakai menyusun berita acara justru lebih tidak
            boleh bergantung padanya.
        --}}
        <x-si.kartu judul="Juara kelas Tanding">
            @if ($tanding->isEmpty())
                <x-si.kosong judul="Belum ada kelas yang selesai"
                             syarat="Juara muncul setelah hasil final disahkan Dewan Wasit Juri." />
            @else
                @foreach ($tanding as $baris)
                    <div class="flex flex-col gap-1 border-b border-line py-3 last:border-0 sm:flex-row sm:items-baseline sm:gap-6">
                        <div class="w-full shrink-0 truncate text-[14px] text-ink-secondary sm:w-[240px]">
                            {{ $baris['kelas']->jenis_kelamin->label() }} {{ $baris['kelas']->golongan_usia->label() }} — {{ $baris['kelas']->name }}
                        </div>

                        <div class="min-w-0 flex-1 flex flex-col gap-1">
                            <div class="flex items-baseline gap-3">
                                <span class="w-[68px] shrink-0 text-[11px] tracking-[.1em] text-ink uppercase">Emas</span>
                                <span class="min-w-0 flex-1 truncate text-[15px] font-medium text-ink">
                                    {{ $baris['emas']->athletes->pluck('name')->implode(', ') }}
                                    <span class="font-normal text-ink-muted">· {{ $baris['emas']->contingent->name }}</span>
                                </span>
                            </div>

                            <div class="flex items-baseline gap-3">
                                <span class="w-[68px] shrink-0 text-[11px] tracking-[.1em] text-ink-muted uppercase">Perak</span>
                                <span class="min-w-0 flex-1 truncate text-[14px] text-ink-secondary">
                                    {{ $baris['perak']->athletes->pluck('name')->implode(', ') }}
                                    <span class="text-ink-muted">· {{ $baris['perak']->contingent->name }}</span>
                                </span>
                            </div>

                            @if ($baris['perunggu']->isNotEmpty())
                                <div class="flex items-baseline gap-3">
                                    <span class="w-[68px] shrink-0 text-[11px] tracking-[.1em] text-ink-muted uppercase">Perunggu</span>
                                    <span class="min-w-0 flex-1 text-[14px] text-ink-secondary">
                                        {{ $baris['perunggu']->map(fn ($r) => $r->athletes->pluck('name')->implode(', ').' · '.$r->contingent->name)->implode('; ') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </x-si.kartu>

        <x-si.kartu judul="Juara nomor Jurus">
            @if ($jurus->isEmpty())
                <x-si.kosong judul="Belum ada nomor yang selesai"
                             syarat="Juara muncul setelah skor penampilan disahkan Dewan Wasit Juri." />
            @else
                @foreach ($jurus as $baris)
                    <div class="flex flex-col gap-1 border-b border-line py-3 last:border-0 sm:flex-row sm:items-baseline sm:gap-6">
                        <div class="w-full shrink-0 truncate text-[14px] text-ink-secondary sm:w-[240px]">
                            {{ $baris['nomor']->nama() }}
                        </div>

                        <div class="min-w-0 flex-1 flex flex-col gap-1">
                            @foreach ([['emas', 'Emas'], ['perak', 'Perak'], ['perunggu', 'Perunggu']] as [$kunci, $label])
                                @if ($baris[$kunci])
                                    <div class="flex items-baseline gap-3">
                                        <span @class([
                                            'w-[68px] shrink-0 text-[11px] tracking-[.1em] uppercase',
                                            'text-ink' => $kunci === 'emas',
                                            'text-ink-muted' => $kunci !== 'emas',
                                        ])>{{ $label }}</span>
                                        <span @class([
                                            'min-w-0 flex-1 truncate',
                                            'text-[15px] font-medium text-ink' => $kunci === 'emas',
                                            'text-[14px] text-ink-secondary' => $kunci !== 'emas',
                                        ])>
                                            {{ $baris[$kunci]->athletes->pluck('name')->implode(', ') }}
                                            <span class="font-normal text-ink-muted">· {{ $baris[$kunci]->contingent->name }}</span>
                                        </span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </x-si.kartu>
    </div>
</x-layouts.admin>
