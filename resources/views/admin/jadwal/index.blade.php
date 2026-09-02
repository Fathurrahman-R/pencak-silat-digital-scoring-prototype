@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Jadwal"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Jadwal' => null,
                 ]">
    <div class="space-y-4">
        <x-si.callout varian="keterangan" judul="Hanya partai yang kedua sudutnya sudah pasti yang muncul di sini">
            Partai yang masih menunggu pemenang babak sebelumnya belum bisa dijadwalkan. Jadwal di
            sini adalah urutan tayang, bukan jam: partai berjalan menurut nomor urutnya, dan yang
            sedang dipertandingkan ditandai statusnya.
        </x-si.callout>

        @foreach ($arenas as $arena)
            {{-- Blok php, bukan bentuk sebaris: yang sebaris hanya menerima satu
                 ekspresi utuh dalam satu baris.

                 Nama direktif TIDAK ditulis dengan tanda at di dalam komentar
                 Blade. Direktif dikompilasi sebelum komentar dihapus, jadi yang
                 tertulis di sini pun ikut diproses -- dan satu direktif liar di
                 dalam komentar menelan seluruh blok di bawahnya. --}}
            @php
                $kurangAparat = $arena->matches->filter(
                    fn ($p) => ! ($aparat[$p->id]['wasit'] ?? false)
                        || ($aparat[$p->id]['juri'] ?? 0) < $jumlahJuri,
                )->count();
            @endphp

            <x-si.kartu>
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-3 border-b border-line pb-2">
                    <span class="text-[16px] font-semibold text-ink">{{ $arena->name }}</span>
                    <span class="flex items-baseline gap-4 text-[13px] text-ink-muted">
                        <span>{{ $arena->matches->count() }} partai</span>
                        @if ($kurangAparat > 0)
                            <span class="font-semibold text-warning">{{ $kurangAparat }} aparat belum lengkap</span>
                        @endif
                    </span>
                </div>

                @forelse ($arena->matches as $partai)
                    @php
                        $punyaWasit = $aparat[$partai->id]['wasit'] ?? false;
                        $jumlahTerpasang = $aparat[$partai->id]['juri'] ?? 0;
                        $lengkap = $punyaWasit && $jumlahTerpasang >= $jumlahJuri;
                    @endphp

                    <div @class([
                        'flex flex-wrap items-center gap-3 border-b border-line py-2.5 last:border-0',
                        'bg-warning-soft -mx-2 px-2' => ! $lengkap,
                    ])>
                        {{--
                            Nomor urut bisa DIKETIK LANGSUNG.

                            Sebelumnya urutan hanya bisa digeser satu langkah lewat
                            dua tombol berikon panah, masing-masing mengirim form dan
                            memuat ulang halaman: memindahkan partai dari urutan 14 ke
                            2 menuntut dua belas klik. Satu isian menggantikannya
                            dengan satu ketikan.
                        --}}
                        @resource(rk('jadwal', ResourceAction::Assign))
                            <form method="POST" action="{{ route('admin.turnamen.jadwal.pindahkan', [$tournament, $partai]) }}"
                                  class="shrink-0">
                                @csrf
                                <label class="sr-only" for="urutan-{{ $partai->id }}">Nomor urut partai {{ $partai->id }}</label>
                                <input id="urutan-{{ $partai->id }}" name="urutan" type="number" min="1"
                                       value="{{ $partai->order_in_arena }}"
                                       x-on:change="$el.form.requestSubmit()"
                                       class="h-9 w-14 rounded-[var(--radius)] border border-line-strong bg-surface-raised text-center font-mono text-[14px] font-semibold text-ink tabular-nums">
                            </form>
                        {{-- Cabang else milik Blade::if bernama sesuai direktifnya
                             sendiri; else polos di sini menghasilkan galat
                             sintaks PHP. --}}
                        @elseresource
                            <span class="w-14 shrink-0 text-center font-mono text-[14px] font-semibold tabular-nums">
                                {{ $partai->order_in_arena }}
                            </span>
                        @endresource

                        <div class="min-w-[240px] flex-1">
                            <p class="text-[15px] text-ink">
                                {{ $partai->red?->athletes->pluck('name')->implode(', ') }}
                                <span class="text-ink-muted">lawan</span>
                                {{ $partai->blue?->athletes->pluck('name')->implode(', ') }}
                            </p>
                            <p class="mt-0.5 text-[12px] text-ink-muted">
                                {{ $partai->bracket->weightClass->jenis_kelamin->label() }}
                                {{ $partai->bracket->weightClass->golongan_usia->label() }} — {{ $partai->bracket->weightClass->name }}
                                · {{ $partai->bracket->namaBabak($partai->round) }}
                            </p>
                        </div>

                        {{-- Kelengkapan aparat dinyatakan, bukan disembunyikan.
                             Panel juri tidak menerima nilai sampai ketiganya
                             ditugaskan, dan itu baru ketahuan saat partai dimulai
                             di depan penonton. --}}
                        <div class="w-[150px] shrink-0 text-right">
                            @if ($lengkap)
                                <x-si.badge varian="sukses">Aparat lengkap</x-si.badge>
                            @else
                                <x-si.badge varian="perhatian">
                                    @if (! $punyaWasit)
                                        Wasit belum ada
                                    @else
                                        Kurang {{ $jumlahJuri - $jumlahTerpasang }} juri
                                    @endif
                                </x-si.badge>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @resource(rk('penugasan-aparat', ResourceAction::View))
                                <a href="{{ route('admin.turnamen.partai.aparat.show', [$tournament, $partai]) }}"
                                   class="inline-flex h-9 items-center rounded-[var(--radius)] px-3 text-[13px] {{ $lengkap ? 'border border-line bg-surface-raised font-medium text-ink' : 'bg-accent font-semibold text-accent-on' }}">
                                    {{ $lengkap ? 'Ubah aparat' : 'Lengkapi aparat' }}
                                </a>
                            @endresource

                            {{--
                                Satu pintu ke panel gelanggang, bukan lima tombol
                                berjajar. Operator, wasit, Dewan Wasit Juri, dan
                                keberatan jarang dibuka dari layar jadwal; lima
                                sasaran berdekatan di tiap baris membuat salah tekan
                                jadi hal yang mudah.
                            --}}
                            @resource(rk('partai', ResourceAction::View))
                                <div x-data="{ buka: false }" class="relative">
                                    <button type="button" x-on:click="buka = ! buka"
                                            x-on:click.outside="buka = false"
                                            class="inline-flex h-9 items-center rounded-[var(--radius)] border border-line bg-surface-raised px-3 text-[13px] font-medium text-ink">
                                        Buka panel
                                    </button>

                                    <div x-show="buka" x-cloak
                                         class="absolute right-0 z-30 mt-1 flex w-[190px] flex-col overflow-hidden rounded-[var(--radius)] border border-line bg-surface-raised shadow-lg">
                                        @foreach ([
                                            'operator' => 'Operator',
                                            'wasit' => 'Wasit',
                                            'dewan-juri' => 'Dewan Wasit Juri',
                                            'keberatan' => 'Keberatan',
                                        ] as $rute => $label)
                                            <a href="{{ route('admin.turnamen.partai.'.$rute, [$tournament, $partai]) }}"
                                               class="border-b border-line px-3 py-2.5 text-[14px] text-ink last:border-0 hover:bg-surface-inset">
                                                {{ $label }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endresource

                            @resource(rk('jadwal', ResourceAction::Assign))
                                {{-- Kata, bukan silang. Silang berarti "tutup" di
                                     mana-mana; di sini ia menghapus penjadwalan. --}}
                                <form method="POST" action="{{ route('admin.turnamen.jadwal.lepas', [$tournament, $partai]) }}">
                                    @csrf
                                    <x-si.tombol tipe="submit" varian="kedua" ukuran="kecil">Lepas jadwal</x-si.tombol>
                                </form>
                            @endresource
                        </div>
                    </div>
                @empty
                    <p class="py-2 text-[14px] text-ink-secondary">Belum ada partai dijadwalkan ke gelanggang ini.</p>
                @endforelse
            </x-si.kartu>
        @endforeach

        <x-si.kartu judul="Belum dijadwalkan">
            @forelse ($belumDijadwalkan as $partai)
                <div class="flex flex-wrap items-center gap-3 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[240px] flex-1">
                        <p class="text-sm text-ink">
                            {{ $partai->red?->athletes->pluck('name')->implode(', ') }}
                            <span class="text-ink-muted">vs</span>
                            {{ $partai->blue?->athletes->pluck('name')->implode(', ') }}
                        </p>
                        <p class="text-xs text-ink-muted">
                            {{ $partai->bracket->weightClass->jenis_kelamin->label() }}
                            {{ $partai->bracket->weightClass->golongan_usia->label() }} — {{ $partai->bracket->weightClass->name }}
                            · {{ $partai->bracket->namaBabak($partai->round) }}
                        </p>
                    </div>

                    @resource(rk('penugasan-aparat', ResourceAction::View))
                        <x-si.tombol :tautan="route('admin.turnamen.partai.aparat.show', [$tournament, $partai])"
                                     varian="kedua" ukuran="kecil">
                            Aparat
                        </x-si.tombol>
                    @endresource

                    @resource(rk('jadwal', ResourceAction::Assign))
                        <form method="POST" action="{{ route('admin.turnamen.jadwal.tetapkan', [$tournament, $partai]) }}"
                              class="flex flex-wrap items-end gap-2">
                            @csrf

                            <div class="w-44">
                                <x-si.pilihan name="arena_id" :options="$arenas->pluck('name', 'id')" placeholder="Gelanggang" />
                            </div>

                            <x-si.tombol tipe="submit" varian="kedua" ukuran="kecil">Jadwalkan</x-si.tombol>
                        </form>
                    @endresource
                </div>
            @empty
                <x-si.kosong judul="Semua partai yang siap sudah dijadwalkan"
                             syarat="Partai yang masih menunggu pemenang babak sebelumnya akan muncul di sini setelah lawannya pasti." />
            @endforelse
        </x-si.kartu>
    </div>
</x-layouts.admin>
