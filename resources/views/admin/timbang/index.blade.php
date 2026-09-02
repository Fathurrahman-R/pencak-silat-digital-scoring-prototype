@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Timbang badan"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Timbang badan' => null,
                 ]">
    {{--
        Layar ini menggugurkan orang dari kejuaraan.

        Mencatat berat di luar batas kelas menyatakan pesilat gugur, lawannya
        menang tanpa bertanding, dan bagannya menyusut. Susunan lama
        menjalankannya seketika lewat satu tombol "Catat" berukuran kecil,
        tanpa satu kalimat pun sebelum atau sesudah — sementara yang menekannya
        berdiri di dekat timbangan, dikejar antrean, menatap jarum.

        Tiga hal yang menjawabnya, semuanya di panel kanan:

        1. Batas kelas ditulis besar SEBELUM angkanya diketik. Sebelumnya ia
           teks 12px di baris identitas, dan tidak dibaca siapa pun.
        2. Penilaian lolos/tidak muncul SAAT mengetik, bukan setelah menekan.
        3. Tombolnya menyebutkan akibat, bukan tindakan.
    --}}
    <div class="grid gap-4 lg:grid-cols-[1fr_400px]" x-data>

        {{-- ================= ANTREAN ================= --}}
        <div class="flex flex-col gap-3">

            <x-si.kartu padat>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="saringan" value="{{ $saringan }}">
                        <x-si.isian name="q" :value="$cari" class="w-[260px]"
                                    placeholder="Cari nama atlet atau kontingen…" />
                        <x-si.tombol tipe="submit" varian="kedua">Cari</x-si.tombol>
                    </form>

                    <div class="flex items-baseline gap-4">
                        <div class="text-right">
                            <div class="text-[11px] tracking-[.1em] text-ink-muted uppercase">Belum ditimbang</div>
                            <div class="font-mono text-[20px] font-semibold tabular-nums">{{ $hitungan['belum'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[11px] tracking-[.1em] text-ink-muted uppercase">Gugur</div>
                            <div class="font-mono text-[20px] font-semibold text-danger tabular-nums">{{ $hitungan['gugur'] }}</div>
                        </div>
                    </div>
                </div>

                {{-- Chip membawa hitungannya sendiri: petugas perlu tahu masih
                     ada berapa yang belum ditimbang sebelum menekan chipnya. --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @foreach ([
                        'belum' => 'Belum ditimbang',
                        'lolos' => 'Lolos',
                        'gugur' => 'Tidak lolos',
                        'semua' => 'Semua',
                    ] as $nilai => $label)
                        <a href="{{ request()->fullUrlWithQuery(['saringan' => $nilai, 'peserta' => null, 'page' => null]) }}"
                           @class([
                               'inline-flex h-9 items-center gap-2 rounded-full px-3.5 text-[13px]',
                               'bg-accent text-accent-on font-semibold' => $saringan === $nilai,
                               'border border-line bg-surface-raised text-ink font-medium' => $saringan !== $nilai,
                           ])>
                            {{ $label }}
                            <span @class(['font-mono tabular-nums', 'opacity-75' => $saringan === $nilai, 'text-ink-muted' => $saringan !== $nilai])>
                                {{ $hitungan[$nilai] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </x-si.kartu>

            <x-si.kartu padat>
                <div class="-mx-4 -mt-4 mb-0 border-b border-line bg-surface-inset px-4 py-2 text-[11px] tracking-[.1em] text-ink-secondary uppercase">
                    Antrean · urut menurut jadwal partai paling awal
                </div>

                @forelse ($registrations as $registration)
                    @php
                        $athlete = $registration->athletes->first();
                        $kelas = $registration->weightClass;
                        $terakhir = $registration->weightIns->first();
                        $jadwal = $registration->matchesAsRed->merge($registration->matchesAsBlue)
                            ->pluck('scheduled_at')->filter()->sort()->first();
                        $sedang = $terpilih && $terpilih->id === $registration->id;
                    @endphp

                    <a href="{{ request()->fullUrlWithQuery(['peserta' => $registration->id]) }}"
                       @class([
                           'flex flex-wrap items-center gap-3 border-b border-line px-2 py-3 last:border-0',
                           'bg-success-soft border-l-[3px] border-l-success' => $sedang,
                       ])>
                        <div class="min-w-[220px] flex-1">
                            <p class="text-[16px] font-semibold text-ink">{{ $athlete?->name ?? '—' }}</p>
                            <p class="mt-0.5 text-[13px] text-ink-secondary">
                                {{ $registration->contingent->name }} · {{ $kelas->name }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <div class="font-mono text-[13px] text-ink-muted tabular-nums">
                                {{ $jadwal?->translatedFormat('d M, H:i') ?? 'Belum terjadwal' }}
                            </div>
                            @if (! $terakhir && $athlete?->weight_claim)
                                <div class="mt-0.5 text-[12px] text-ink-muted">Klaim {{ $athlete->weight_claim }} kg</div>
                            @endif
                        </div>

                        <div class="w-[172px] shrink-0 text-right">
                            @if ($terakhir)
                                <x-si.badge :varian="$terakhir->passed ? 'sukses' : 'bahaya'">
                                    {{ $terakhir->weight }} kg · {{ $terakhir->passed ? 'Lolos' : 'Tidak lolos' }}
                                </x-si.badge>
                                <p class="mt-1 text-[12px] text-ink-muted">
                                    {{ $terakhir->weighed_at->translatedFormat('d M, H:i') }}
                                    @if ($registration->weightIns->count() > 1)
                                        · {{ $registration->weightIns->count() }} kali
                                    @endif
                                </p>
                            @elseif ($sedang)
                                <x-si.badge varian="netral">Sedang ditimbang</x-si.badge>
                            @else
                                <x-si.badge varian="netral">Belum ditimbang</x-si.badge>
                            @endif
                        </div>
                    </a>

                    {{-- Sebab dan akibat gugur ditulis, bukan cuma label merah. --}}
                    @if ($terakhir && ! $terakhir->passed)
                        <div class="mx-2 mt-2 mb-3 rounded-[var(--radius)] border-l-[3px] border-danger bg-danger-soft px-4 py-2.5">
                            <p class="text-[13px] leading-relaxed text-danger">
                                {{ $terakhir->weight }} kg di luar batas {{ $kelas->name }} ({{ $kelas->rentang() }}).
                                Gugur dari kelas ini; lawannya menang tanpa bertanding.
                            </p>
                        </div>
                    @endif
                @empty
                    <x-si.kosong judul="Tidak ada peserta di penyaring ini"
                                 syarat="Pra Usia Dini dan Usia Dini 1 tidak menjalani timbang badan, dan kategori Jurus tidak mengenal kelas berat. Ubah penyaring di atas untuk melihat golongan lain." />
                @endforelse
            </x-si.kartu>
        </div>

        {{-- ================= PANEL TIMBANG ================= --}}
        <div class="lg:sticky lg:top-4 lg:self-start">
            @if ($terpilih && $batas)
                @resource(rk('timbang-badan', ResourceAction::Create))
                    @php($sebelumnya = $terpilih->weightIns)

                    {{--
                        Seluruh panel hidup di satu x-data. `berat` diketik,
                        dan getter `nilai` menghitung ulang tiap ketukan --
                        itu yang membuat "di dalam batas" muncul sebelum tangan
                        petugas berpindah ke tombol.

                        Aturan batas terbuka/tertutup ikut dibawa dari server:
                        naskah memakai keduanya, dan "di atas 50 s.d 54" berarti
                        50,0 kg TIDAK lolos sedangkan 54,0 kg lolos.
                    --}}
                    <div x-data="{
                             berat: '',
                             batas: @js($batas),
                             get angka() {
                                 const n = parseFloat(String(this.berat).replace(',', '.'));
                                 return Number.isFinite(n) ? n : null;
                             },
                             /* Berat dibaca kembali dengan koma: papan angka HP
                                mengirim titik dan tidak ada gunanya menolaknya,
                                tapi yang dibaca petugas harus ejaan Indonesia.
                                Tanda kutip ganda TIDAK BOLEH dipakai di dalam
                                blok ini -- ia menutup atribut x-data lebih awal
                                dan seluruh sisanya berhenti jadi kode. */
                             get beratTampil() {
                                 return String(this.berat).replace('.', ',');
                             },
                             get nilai() {
                                 if (this.angka === null) return null;
                                 const { min, max, min_eksklusif } = this.batas;
                                 if (min !== null && (min_eksklusif ? this.angka <= min : this.angka < min)) {
                                     return { lolos: false, selisih: +(min - this.angka).toFixed(1), sisi: 'kurang' };
                                 }
                                 if (max !== null && this.angka > max) {
                                     return { lolos: false, selisih: +(this.angka - max).toFixed(1), sisi: 'lebih' };
                                 }
                                 const sisa = max === null ? null : +(max - this.angka).toFixed(1);
                                 return { lolos: true, sisa };
                             },
                         }"
                         class="flex flex-col gap-4 rounded-[var(--radius)] border border-line bg-surface-raised p-4">

                        <div class="border-b border-line pb-3">
                            <div class="text-[11px] tracking-[.1em] text-ink-muted uppercase">Sedang ditimbang</div>
                            <div class="mt-0.5 text-[20px] font-semibold text-ink">{{ $batas['pesilat'] }}</div>
                            <div class="text-[13px] text-ink-secondary">{{ $terpilih->contingent->name }}</div>
                        </div>

                        {{-- Batas kelas: hal kedua terbesar di panel. --}}
                        <div class="flex items-center justify-between gap-3 rounded-[var(--radius)] border border-line bg-surface-inset p-3.5">
                            <div>
                                <div class="text-[11px] tracking-[.1em] text-ink-muted uppercase">Batas {{ $batas['nama'] }}</div>
                                <div class="mt-0.5 text-[13px] text-ink-secondary">{{ $batas['golongan'] }}</div>
                            </div>
                            <div class="font-mono text-[22px] font-semibold tabular-nums">{{ $batas['rentang'] }}</div>
                        </div>

                        <form method="POST" action="{{ route('admin.turnamen.timbang.store', [$tournament, $terpilih]) }}"
                              class="flex flex-col gap-4">
                            @csrf

                            <div class="flex flex-col gap-1.5">
                                <label for="weight" class="text-[14px] font-semibold text-ink">
                                    Berat hasil timbangan <span class="font-normal text-ink-muted">— wajib diisi</span>
                                </label>
                                <div class="flex h-16 items-center gap-2.5 rounded-[var(--radius)] border-2 bg-surface-raised px-4"
                                     x-bind:class="nilai === null ? 'border-line-strong' : (nilai.lolos ? 'border-success' : 'border-danger')">
                                    <input id="weight" name="weight" type="number" step="0.1" min="10" max="200" required
                                           x-model="berat" autofocus
                                           class="silat-angka w-full bg-transparent font-mono text-[34px] font-semibold tabular-nums outline-none"
                                           x-bind:class="nilai && ! nilai.lolos ? 'text-danger' : 'text-ink'"
                                           placeholder="0,0">
                                    <span class="shrink-0 text-[20px] text-ink-muted">kg</span>
                                </div>

                                {{-- Penilaian saat mengetik. --}}
                                <template x-if="nilai && nilai.lolos">
                                    <div class="flex items-center gap-2 rounded-[var(--radius)] bg-success-soft px-3 py-2">
                                        <span class="size-2.5 shrink-0 rounded-full bg-success"></span>
                                        <span class="text-[14px] font-semibold text-success">Di dalam batas — lolos</span>
                                        <span class="text-[13px] text-success/85" x-show="nilai.sisa !== null"
                                              x-text="String(nilai.sisa).replace('.', ',') + ' kg di bawah batas atas'"></span>
                                    </div>
                                </template>

                                <template x-if="nilai && ! nilai.lolos">
                                    <div class="flex items-start gap-2 rounded-[var(--radius)] bg-danger-soft px-3 py-2.5">
                                        <span class="mt-1.5 size-2.5 shrink-0 rounded-full bg-danger"></span>
                                        <div>
                                            <div class="text-[14px] font-semibold text-danger"
                                                 x-text="(nilai.sisi === 'lebih' ? 'Lebih ' : 'Kurang ') + String(nilai.selisih).replace('.', ',') + ' kg dari batas'"></div>
                                            <div class="mt-0.5 text-[13px] leading-relaxed text-danger/90">
                                                Mencatat angka ini <strong>menggugurkan {{ $batas['pesilat'] }} dari {{ $batas['nama'] }}</strong>.
                                                Lawannya menang tanpa bertanding, dan bagan kelas itu menyusut.
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{--
                                Tombol menyebutkan AKIBAT, bukan tindakan.
                                "Catat" tidak memberi tahu apa pun; yang di
                                bawah ini memberi tahu persis apa yang terjadi
                                setelah jari diangkat.
                            --}}
                            <button type="submit"
                                    x-bind:disabled="nilai === null"
                                    x-bind:class="nilai && ! nilai.lolos
                                        ? 'bg-danger text-danger-on'
                                        : 'bg-accent text-accent-on'"
                                    class="flex min-h-14 items-center justify-center rounded-[var(--radius)] px-3 text-center text-[16px] leading-tight font-semibold disabled:opacity-45"
                                    x-text="nilai === null
                                        ? 'Isi berat lebih dulu'
                                        : (nilai.lolos
                                            ? 'Catat ' + beratTampil + ' kg — lolos'
                                            : 'Catat ' + beratTampil + ' kg dan gugurkan dari {{ $batas['nama'] }}')">
                            </button>
                        </form>

                        <div class="flex flex-col gap-1.5 border-t border-line pt-3">
                            <div class="text-[11px] tracking-[.1em] text-ink-muted uppercase">Riwayat penimbangan</div>

                            @forelse ($sebelumnya as $baris)
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="text-[13px] text-ink-secondary">
                                        {{ $baris->passed ? 'Lolos' : 'Tidak lolos' }}
                                    </span>
                                    <span class="font-mono text-[13px] tabular-nums">
                                        {{ $baris->weight }} kg · {{ $baris->weighed_at->translatedFormat('H:i') }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-[13px] leading-relaxed text-ink-secondary">Belum pernah ditimbang.</p>
                            @endforelse

                            <p class="mt-1 text-[13px] leading-relaxed text-ink-secondary">
                                Penimbangan ulang dicatat sebagai baris baru — yang sebelumnya tetap tersimpan dan
                                ikut tercetak di berita acara. Hasil ditetapkan terhadap kelas yang berlaku saat
                                ditimbang, lalu tidak dihitung ulang lagi.
                            </p>
                        </div>
                    </div>
                @endresource
            @else
                <x-si.kartu>
                    <x-si.kosong judul="Pilih peserta dari antrean"
                                 syarat="Panel penimbangan terbuka setelah satu peserta dipilih. Batas kelasnya ikut tampil di sini, sebelum beratnya diisi." />
                </x-si.kartu>
            @endif
        </div>
    </div>
</x-layouts.admin>
