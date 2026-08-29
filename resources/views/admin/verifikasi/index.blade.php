@php
    use App\Enums\ResourceAction;
    use App\Enums\StatusPendaftaran;
@endphp

<x-layouts.admin heading="Verifikasi pendaftaran"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Verifikasi' => null,
                 ]">
    {{--
        Layar dengan baris terbanyak di seluruh aplikasi panitia, dan yang
        paling sering dibuka ulang sepanjang hari pendaftaran.

        Susunan lama tidak punya satu pun kotak cari, dan memuat SETIAP
        pendaftaran kejuaraan sekaligus beserta dokumen tiap atletnya —
        padahal panitia yang membukanya sedang mencari satu orang yang namanya
        baru saja disebut lewat pengeras suara.

        Tiga perubahan: pencarian, penomoran halaman, dan panel berkas yang
        terbuka di samping daftarnya. Memaksa panitia berpindah halaman untuk
        memeriksa akta berarti kehilangan tempat di daftar tiga ratus baris.
    --}}
    <div class="grid gap-4 xl:grid-cols-[1fr_360px]">

        {{-- ================= DAFTAR ================= --}}
        <div class="flex flex-col gap-3">

            <x-si.callout judul="Dua syarat harus terpenuhi bersamaan">
                Berkas persyaratan peserta lengkap, dan tagihan kontingennya lunas. Keduanya ditegakkan
                saat tombol ditekan — bukan hanya ditampilkan sebagai peringatan.
            </x-si.callout>

            <x-si.kartu padat>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <x-si.isian name="q" :value="$cari" class="w-[280px]"
                                    placeholder="Cari nama atlet, kontingen, atau kelas…" />
                        <x-si.tombol tipe="submit" varian="kedua">Cari</x-si.tombol>
                    </form>

                    <div class="flex items-baseline gap-2 text-[13px] text-ink-muted">
                        <span>Menampilkan</span>
                        <span class="font-mono text-[15px] font-semibold text-ink tabular-nums">{{ $registrations->count() }}</span>
                        <span>dari</span>
                        <span class="font-mono text-[15px] font-semibold text-ink tabular-nums">{{ $registrations->total() }}</span>
                    </div>
                </div>

                {{-- Chip membawa hitungannya sendiri, dan hitungan itu tidak ikut
                     menyusut saat daftarnya tersaring. --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @foreach ($statuses as $nilai => $label)
                        <a href="{{ request()->fullUrlWithQuery(['status' => $nilai, 'peserta' => null, 'page' => null]) }}"
                           @class([
                               'inline-flex h-9 items-center gap-2 rounded-full px-3.5 text-[13px]',
                               'bg-accent text-accent-on font-semibold' => $status === $nilai,
                               'border border-line bg-surface-raised text-ink font-medium' => $status !== $nilai,
                           ])>
                            {{ $label }}
                            <span @class(['font-mono tabular-nums', 'opacity-75' => $status === $nilai, 'text-ink-muted' => $status !== $nilai])>
                                {{ $hitungan[$nilai] ?? 0 }}
                            </span>
                        </a>
                    @endforeach

                    <a href="{{ request()->fullUrlWithQuery(['status' => 'semua', 'peserta' => null, 'page' => null]) }}"
                       @class([
                           'inline-flex h-9 items-center gap-2 rounded-full px-3.5 text-[13px]',
                           'bg-accent text-accent-on font-semibold' => $status === 'semua',
                           'border border-line bg-surface-raised text-ink font-medium' => $status !== 'semua',
                       ])>
                        Semua
                        <span @class(['font-mono tabular-nums', 'opacity-75' => $status === 'semua', 'text-ink-muted' => $status !== 'semua'])>
                            {{ $hitungan['semua'] ?? 0 }}
                        </span>
                    </a>
                </div>
            </x-si.kartu>

            <x-si.kartu padat>
                @forelse ($registrations as $registration)
                    @php
                        $invoice = $registration->contingent->invoice;
                        $lunas = $invoice?->lunas() ?? false;

                        $kurang = $registration->athletes
                            ->flatMap(fn ($a) => array_map(
                                fn ($jenis) => ['atlet' => $a->name, 'berkas' => $jenis->label()],
                                $a->berkasKurang($tournament),
                            ))
                            ->all();

                        $siap = $lunas && $kurang === [];
                        $menunggu = $registration->status === StatusPendaftaran::Diajukan;
                        $sedang = $terpilih && $terpilih->id === $registration->id;
                    @endphp

                    <div @class([
                        'border-b border-line py-3 last:border-0',
                        'bg-surface-inset' => $sedang,
                        'px-2 -mx-2' => true,
                    ])>
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="min-w-[240px] flex-1">
                                <p class="text-[15px] font-semibold text-ink">{{ $registration->namaNomor() }}</p>
                                <p class="mt-0.5 text-[13px] text-ink-secondary">
                                    {{ $registration->contingent->name }} ·
                                    {{ $registration->athletes->pluck('name')->implode(', ') }}
                                </p>
                            </div>

                            <x-si.badge :varian="match ($registration->status) {
                                StatusPendaftaran::Terverifikasi => 'sukses',
                                StatusPendaftaran::Ditolak, StatusPendaftaran::Gugur => 'bahaya',
                                StatusPendaftaran::Diajukan => 'perhatian',
                                default => 'netral',
                            }">{{ $registration->status->label() }}</x-si.badge>

                            <div class="flex shrink-0 items-center gap-2">
                                <a href="{{ request()->fullUrlWithQuery(['peserta' => $registration->id]) }}"
                                   class="inline-flex h-9 items-center rounded-[var(--radius)] border border-line bg-surface-raised px-3 text-[13px] font-medium text-ink">
                                    Lihat berkas
                                </a>

                                @if ($menunggu)
                                    @resource(rk('pendaftaran', ResourceAction::Approve))
                                        <form method="POST" action="{{ route('admin.turnamen.verifikasi.setujui', [$tournament, $registration]) }}">
                                            @csrf
                                            <x-si.tombol tipe="submit" ukuran="kecil" :nonaktif="! $siap">Sahkan</x-si.tombol>
                                        </form>
                                    @endresource

                                    @resource(rk('pendaftaran', ResourceAction::Reject))
                                        <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                                     x-on:click="$dispatch('modal-open', 'tolak-pendaftaran')
                                                                 || 0; window.__tolak = @js([
                                                                     'aksi' => route('admin.turnamen.verifikasi.tolak', [$tournament, $registration]),
                                                                     'nama' => $registration->namaNomor(),
                                                                 ])">Tolak</x-si.tombol>
                                    @endresource
                                @else
                                    @resource(rk('pendaftaran', ResourceAction::Approve))
                                        <form method="POST" action="{{ route('admin.turnamen.verifikasi.tinjau-ulang', [$tournament, $registration]) }}">
                                            @csrf
                                            <x-si.tombol tipe="submit" varian="kedua" ukuran="kecil">Tinjau ulang</x-si.tombol>
                                        </form>
                                    @endresource
                                @endif
                            </div>
                        </div>

                        {{--
                            Sebab tombol Sahkan mati ditulis sebagai kalimat utuh
                            beserta tombol menuju tempat menyelesaikannya. Panitia
                            yang membaca "tagihan belum lunas" tetap harus tahu ke
                            mana perginya, dan potongan kecil berwarna oranye tidak
                            memberi tahu itu.
                        --}}
                        @if ($menunggu && ! $siap)
                            <div class="mt-2 flex flex-col gap-1.5 rounded-[var(--radius)] border-l-[3px] border-warning bg-warning-soft px-3.5 py-2.5">
                                <p class="text-[13px] font-semibold text-warning">
                                    Belum bisa disahkan — {{ (int) ! $lunas + count($kurang) }} hal tertahan
                                </p>

                                @unless ($lunas)
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        {{-- Satu baris utuh: Blade menyisipkan baris baru di
                                             setiap pergantian baris sumber, dan kalimat yang
                                             terpotong di tengah tidak bisa dicari maupun
                                             diperiksa uji sebagai satu kalimat. --}}
                                        <span class="text-[13px] text-warning">Tagihan {{ $registration->contingent->name }} {{ $invoice ? mb_strtolower($invoice->status->label()) : 'belum terbit' }}.</span>
                                        @if ($invoice)
                                            <a href="{{ route('admin.turnamen.kontingen.tagihan.show', [$tournament, $registration->contingent]) }}"
                                               class="inline-flex h-8 shrink-0 items-center rounded-[var(--radius-kecil)] border border-warning px-3 text-[12px] font-semibold text-warning">
                                                Buka tagihan
                                            </a>
                                        @endif
                                    </div>
                                @endunless

                                @foreach ($kurang as $catatan)
                                    <span class="text-[13px] text-warning">
                                        Berkas kurang — {{ $catatan['atlet'] }}: {{ $catatan['berkas'] }}.
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if ($registration->status === StatusPendaftaran::Ditolak && $registration->rejection_reason)
                            <div class="mt-2 rounded-[var(--radius)] border-l-[3px] border-danger bg-danger-soft px-3.5 py-2.5">
                                <p class="text-[13px] leading-relaxed text-danger">
                                    <strong>Alasan:</strong> {{ $registration->rejection_reason }}
                                </p>
                                @if ($registration->verified_at && $registration->verifier)
                                    <p class="mt-1 text-[12px] text-danger/85">
                                        {{ $registration->verifier->name }} ·
                                        {{ $registration->verified_at->translatedFormat('d M Y, H:i') }}
                                    </p>
                                @endif
                            </div>
                        @elseif ($registration->verified_at && $registration->verifier)
                            <p class="mt-2 text-[12px] text-ink-muted">
                                Diperiksa {{ $registration->verifier->name }} ·
                                {{ $registration->verified_at->translatedFormat('d M Y, H:i') }}
                            </p>
                        @endif
                    </div>
                @empty
                    <x-si.kosong judul="Tidak ada pendaftaran di penyaring ini"
                                 syarat="Pendaftaran masuk setelah official kontingen mengajukannya lewat akunnya sendiri. Ubah penyaring atau kosongkan pencarian untuk melihat yang lain." />
                @endforelse

                @if ($registrations->hasPages())
                    <div class="mt-3 border-t border-line pt-3">
                        {{ $registrations->links() }}
                    </div>
                @endif
            </x-si.kartu>
        </div>

        {{-- ================= PANEL BERKAS ================= --}}
        <div class="xl:sticky xl:top-4 xl:self-start">
            @if ($terpilih && $berkas)
                <x-si.kartu padat>
                    <div class="border-b border-line pb-3">
                        <div class="text-[11px] tracking-[.1em] text-ink-muted uppercase">Berkas peserta</div>
                        <div class="mt-0.5 text-[16px] font-semibold text-ink">{{ $terpilih->namaNomor() }}</div>
                        <div class="text-[13px] text-ink-secondary">{{ $terpilih->contingent->name }}</div>
                    </div>

                    @foreach ($berkas as $peserta)
                        @if (count($berkas) > 1)
                            <div class="mt-3 text-[13px] font-semibold text-ink">{{ $peserta['atlet'] }}</div>
                        @endif

                        <div class="mt-2 flex flex-col gap-2">
                            @foreach ($peserta['berkas'] as $b)
                                <div @class([
                                    'flex items-start justify-between gap-2 rounded-[var(--radius)] border px-3 py-2.5',
                                    'border-line' => $b['ada'],
                                    'border-danger bg-danger-soft' => ! $b['ada'],
                                ])>
                                    <div class="min-w-0">
                                        <div @class(['text-[14px] font-medium', 'text-ink' => $b['ada'], 'text-danger' => ! $b['ada']])>
                                            {{ $b['label'] }}
                                        </div>
                                        <div @class(['mt-0.5 text-[12px]', 'text-ink-muted' => $b['ada'], 'text-danger/85' => ! $b['ada']])>
                                            {{ $b['ada']
                                                ? 'Diunggah '.$b['diunggah_at']?->translatedFormat('d M Y')
                                                : 'Belum diunggah official' }}
                                        </div>
                                    </div>
                                    <x-si.badge :varian="$b['ada'] ? 'sukses' : 'bahaya'">
                                        {{ $b['ada'] ? 'Ada' : 'Kurang' }}
                                    </x-si.badge>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <p class="mt-3 border-t border-line pt-3 text-[13px] leading-relaxed text-ink-secondary">
                        Kekurangan berkas dikembalikan ke official kontingen lewat akunnya sendiri —
                        panitia tidak mengunggahkan berkas milik orang lain.
                    </p>
                </x-si.kartu>
            @else
                <x-si.kartu>
                    <x-si.kosong judul="Pilih pendaftaran untuk melihat berkasnya"
                                 syarat="Berkas terbuka di sini, di samping daftarnya, supaya tempatmu di daftar tidak hilang saat memeriksa." />
                </x-si.kartu>
            @endif
        </div>
    </div>

    {{--
        SATU dialog tolak untuk seluruh halaman, bukan satu per baris.
        Susunan lama merender dialog untuk setiap pendaftaran; dua ratus baris
        berarti dua ratus dialog tersembunyi di satu halaman yang sama.
    --}}
    @resource(rk('pendaftaran', ResourceAction::Reject))
        <div x-data="{ terbuka: false, aksi: '', nama: '' }"
             x-on:modal-open.window="if ($event.detail === 'tolak-pendaftaran') { aksi = window.__tolak.aksi; nama = window.__tolak.nama; terbuka = true }"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[480px] rounded-[var(--radius)] border border-line bg-surface-raised p-5">
                    <p class="text-[18px] font-semibold text-ink">
                        Tolak pendaftaran <span x-text="nama"></span>?
                    </p>
                    <p class="mt-1 text-[14px] leading-relaxed text-ink-secondary">
                        Pendaftaran dikembalikan ke official kontingen beserta alasannya, bukan dihapus.
                        Official bisa memperbaiki dan mengajukannya lagi selama pendaftaran masih dibuka.
                    </p>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex flex-col gap-3">
                        @csrf
                        <x-si.isian name="rejection_reason" label="Alasan penolakan" wajib
                                    bantuan="Ditulis apa adanya ke official. Sebutkan berkas atau syarat mana yang harus diperbaiki." />

                        <div class="flex items-center gap-2">
                            <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                            <x-si.tombol tipe="submit" varian="bahaya">Tolak pendaftaran</x-si.tombol>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endresource
</x-layouts.admin>
