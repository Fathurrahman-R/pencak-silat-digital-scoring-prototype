@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Jadwal"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Jadwal' => route('admin.turnamen.jadwal.index', $tournament),
                     'Jurus' => null,
                 ]">
    <x-slot:actions>
        @resource(rk('jadwal', ResourceAction::Print))
            <a href="{{ route('admin.turnamen.jadwal.jurus.cetak', $tournament) }}" target="_blank"
               class="inline-flex h-9 items-center rounded-[var(--radius)] border border-line-strong px-3 text-[13px] font-semibold text-ink">
                Cetak PDF
            </a>
        @endresource
    </x-slot:actions>

    <div class="space-y-4">
        <x-si.tab-halaman :daftar="[
            'Tanding' => route('admin.turnamen.jadwal.index', $tournament),
            'Jurus' => route('admin.turnamen.jadwal.jurus.index', $tournament),
        ]" />

        <x-si.callout varian="keterangan" judul="Battle dijadwalkan berpasangan, bukan per sudut">
            Satu battle masuk gelanggang sebagai dua penampilan berurutan — sudut biru lebih dulu,
            sesuai Pasal 12.1.d.7. Keduanya tidak bisa dipisah ke gelanggang berbeda. Nomor
            berformat peringkat tidak punya battle, jadi penampilannya dijadwalkan satu per satu.
        </x-si.callout>

        @foreach ($arenas as $arena)
            <x-si.kartu>
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-3 border-b border-line pb-2">
                    <span class="text-[16px] font-semibold text-ink">{{ $arena->name }}</span>
                    <span class="text-[13px] text-ink-muted">{{ $arena->jurusPerformances->count() }} penampilan</span>
                </div>

                @forelse ($arena->jurusPerformances as $penampilan)
                    <div class="flex flex-wrap items-center gap-3 border-b border-line py-2.5 last:border-0">
                        @resource(rk('jadwal', ResourceAction::Assign))
                            <form method="POST"
                                  action="{{ route('admin.turnamen.jadwal.jurus.penampilan.pindahkan', [$tournament, $penampilan]) }}"
                                  class="shrink-0">
                                @csrf
                                <label class="sr-only" for="urutan-{{ $penampilan->id }}">
                                    Nomor urut penampilan {{ $penampilan->id }}
                                </label>
                                <input id="urutan-{{ $penampilan->id }}" name="urutan" type="number" min="1"
                                       value="{{ $penampilan->order_in_arena }}"
                                       x-on:change="$el.form.requestSubmit()"
                                       class="h-9 w-14 rounded-[var(--radius)] border border-line-strong bg-surface-raised text-center font-mono text-[14px] font-semibold text-ink tabular-nums">
                            </form>
                        @elseresource
                            <span class="w-14 shrink-0 text-center font-mono text-[14px] font-semibold tabular-nums">
                                {{ $penampilan->order_in_arena }}
                            </span>
                        @endresource

                        {{-- Sudut ditandai warna, bukan kata saja: pembacanya
                             mencocokkannya dengan bidang sudut di papan dan di
                             bagan, yang sudah memakai arti warna yang sama. --}}
                        <span @class([
                            'w-14 shrink-0 rounded-[var(--radius)] py-1 text-center text-[12px] font-semibold uppercase',
                            'bg-corner-red text-corner-red-on' => $penampilan->sudut === 'merah',
                            'bg-corner-blue text-corner-blue-on' => $penampilan->sudut === 'biru',
                            'border border-line text-ink-muted' => $penampilan->sudut === null,
                        ])>{{ $penampilan->sudut ?? '—' }}</span>

                        <div class="min-w-[240px] flex-1">
                            <p class="text-[15px] text-ink">
                                {{ $penampilan->registration?->athletes->pluck('name')->implode(', ') }}
                            </p>
                            <p class="mt-0.5 text-[12px] text-ink-muted">
                                {{ $penampilan->jurusEvent->nama() }}
                                · {{ $penampilan->registration?->contingent->name }}
                            </p>
                        </div>

                        @resource(rk('jadwal', ResourceAction::Assign))
                            @if ($penampilan->jurus_battle_id === null)
                                <form method="POST"
                                      action="{{ route('admin.turnamen.jadwal.jurus.penampilan.lepas', [$tournament, $penampilan]) }}">
                                    @csrf
                                    <x-si.tombol tipe="submit" varian="kedua" ukuran="kecil">Lepas jadwal</x-si.tombol>
                                </form>
                            @else
                                <form method="POST"
                                      action="{{ route('admin.turnamen.jadwal.jurus.battle.lepas', [$tournament, $penampilan->jurus_battle_id]) }}">
                                    @csrf
                                    <x-si.tombol tipe="submit" varian="kedua" ukuran="kecil">Lepas battle</x-si.tombol>
                                </form>
                            @endif
                        @endresource
                    </div>
                @empty
                    <p class="py-2 text-[14px] text-ink-secondary">Belum ada penampilan Jurus dijadwalkan ke gelanggang ini.</p>
                @endforelse
            </x-si.kartu>
        @endforeach

        <x-si.kartu judul="Battle belum dijadwalkan">
            @forelse ($battleSiap as $battle)
                <div class="flex flex-wrap items-center gap-3 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[240px] flex-1">
                        {{-- Sudut ditandai bidang warna, bukan tinta berwarna.
                             Tinta merah tua di atas permukaan gelap kehilangan
                             kontrasnya, dan token sudut memang tidak punya
                             varian suasana gelap -- ia selalu jadi bidang
                             dengan teks putih di atasnya. --}}
                        <p class="flex flex-wrap items-center gap-2 text-sm text-ink">
                            <span class="rounded-[var(--radius)] bg-corner-red px-2 py-0.5 font-semibold text-corner-red-on">
                                {{ $battle->red?->athletes->pluck('name')->implode(', ') }}
                            </span>
                            <span class="text-ink-muted">lawan</span>
                            <span class="rounded-[var(--radius)] bg-corner-blue px-2 py-0.5 font-semibold text-corner-blue-on">
                                {{ $battle->blue?->athletes->pluck('name')->implode(', ') }}
                            </span>
                        </p>
                        <p class="text-xs text-ink-muted">
                            {{ $battle->bracket->jurusEvent->nama() }}
                            · {{ \App\Support\Bagan\TahapBaganJurus::label(
                                    \App\Support\Bagan\TahapBaganJurus::untuk($battle->round, $battle->bracket->size),
                               ) }}
                        </p>
                    </div>

                    @resource(rk('jadwal', ResourceAction::Assign))
                        <form method="POST"
                              action="{{ route('admin.turnamen.jadwal.jurus.battle.tetapkan', [$tournament, $battle]) }}"
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
                <x-si.kosong judul="Tidak ada battle yang menunggu gelanggang"
                             syarat="Battle yang lawannya masih menunggu ronde sebelumnya akan muncul di sini setelah kedua sudutnya pasti." />
            @endforelse
        </x-si.kartu>

        <x-si.kartu judul="Penampilan belum dijadwalkan">
            @forelse ($penampilanSiap as $penampilan)
                <div class="flex flex-wrap items-center gap-3 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[240px] flex-1">
                        <p class="text-sm text-ink">{{ $penampilan->registration?->athletes->pluck('name')->implode(', ') }}</p>
                        <p class="text-xs text-ink-muted">
                            {{ $penampilan->jurusEvent->nama() }}
                            · {{ $penampilan->registration?->contingent->name }}
                        </p>
                    </div>

                    @resource(rk('jadwal', ResourceAction::Assign))
                        <form method="POST"
                              action="{{ route('admin.turnamen.jadwal.jurus.penampilan.tetapkan', [$tournament, $penampilan]) }}"
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
                <x-si.kosong judul="Semua penampilan yang siap sudah dijadwalkan"
                             syarat="Nomor berformat peringkat yang penampilannya belum dibuat akan muncul di sini setelah digenerate." />
            @endforelse
        </x-si.kartu>
    </div>
</x-layouts.admin>
