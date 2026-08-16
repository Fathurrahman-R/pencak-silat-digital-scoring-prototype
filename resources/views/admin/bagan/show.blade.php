@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Bagan {{ $weightClass->name }}"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Bagan' => route('admin.turnamen.bagan.index', $tournament),
                     $weightClass->name => null,
                 ]">
    <x-slot:actions>
        @if ($bracket->terkunci())
            <x-ui.badge variant="success">
                Terkunci oleh {{ $bracket->locker?->name ?? '—' }} · {{ $bracket->locked_at->translatedFormat('d M Y, H:i') }}
            </x-ui.badge>

            @resource(rk('bagan', ResourceAction::Delete))
                <x-ui.button type="button" variant="secondary" size="sm"
                             x-on:click="$dispatch('modal-open', 'buka-kunci')">
                    Buka kunci
                </x-ui.button>
            @endresource
        @else
            @resource(rk('bagan', ResourceAction::Update))
                <x-ui.button type="button" size="sm" x-on:click="$dispatch('modal-open', 'kunci-bagan')">
                    <x-ui.icon name="lock" class="h-4 w-4" />
                    Kunci bagan
                </x-ui.button>
            @endresource
        @endif
    </x-slot:actions>

    <div class="space-y-4">
        @unless ($bracket->terkunci())
            <x-ui.alert variant="warning" title="Bagan ini masih draf">
                Susunannya masih bisa ditukar. Setelah dikunci, tempat yang bergeser berarti kontingen
                menyiapkan lawan yang keliru — kesalahan yang tidak bisa diperbaiki di hari-H.
            </x-ui.alert>

            @resource(rk('bagan', ResourceAction::Update))
                <x-ui.card title="Tukar tempat">
                    <form method="POST" action="{{ route('admin.turnamen.bagan.tukar', [$tournament, $weightClass]) }}"
                          class="flex flex-wrap items-end gap-3">
                        @csrf

                        <div class="w-56">
                            <x-ui.select name="posisi_a" label="Tempat pertama" :options="$bracket->slots->mapWithKeys(fn ($s) => [
                                $s->position => 'Posisi '.$s->position.' — '.($s->registration ? $s->registration->contingent->name.' ('.$s->registration->athletes->pluck('name')->implode(', ').')' : 'Bye'),
                            ])" placeholder="Pilih tempat" />
                        </div>

                        <div class="w-56">
                            <x-ui.select name="posisi_b" label="Tempat kedua" :options="$bracket->slots->mapWithKeys(fn ($s) => [
                                $s->position => 'Posisi '.$s->position.' — '.($s->registration ? $s->registration->contingent->name.' ('.$s->registration->athletes->pluck('name')->implode(', ').')' : 'Bye'),
                            ])" placeholder="Pilih tempat" />
                        </div>

                        <x-ui.button type="submit" variant="secondary" size="sm">Tukar</x-ui.button>
                    </form>
                </x-ui.card>
            @endresource
        @endunless

        @foreach ($babak as $round => $partaiSatuBabak)
            <x-ui.card :title="$bracket->namaBabak($round)">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($partaiSatuBabak->sortBy('position') as $partai)
                        <div class="rounded-lg border border-line p-3">
                            <p class="mb-2 text-xs text-ink-muted">Partai {{ $partai->position }}</p>

                            @foreach (['red' => $partai->red, 'blue' => $partai->blue] as $sudut => $peserta)
                                <div @class([
                                    'flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm',
                                    'bg-success-soft text-success' => $partai->winner_registration_id && $partai->winner_registration_id === $peserta?->id,
                                ])>
                                    <span class="min-w-0 truncate">
                                        @if ($peserta)
                                            {{ $peserta->athletes->pluck('name')->implode(', ') }}
                                            <span class="text-ink-muted">· {{ $peserta->contingent->name }}</span>
                                        @else
                                            <span class="text-ink-muted">
                                                {{ $partai->bye() ? 'Bye' : 'Menunggu babak sebelumnya' }}
                                            </span>
                                        @endif
                                    </span>

                                    <span class="shrink-0 text-[10px] tracking-wide text-ink-muted uppercase">
                                        {{ $sudut === 'red' ? 'Merah' : 'Biru' }}
                                    </span>
                                </div>
                            @endforeach

                            @if ($partai->win_reason)
                                <p class="mt-1.5 text-[11px] text-ink-muted">
                                    Menang {{ $partai->win_reason === 'bye' ? 'bye' : $partai->win_reason }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach
    </div>

    @unless ($bracket->terkunci())
        @resource(rk('bagan', ResourceAction::Update))
            <x-ui.modal id="kunci-bagan" title="Kunci bagan" size="sm">
                Setelah dikunci, susunan <strong>{{ $weightClass->name }}</strong> tidak bisa disusun ulang
                maupun ditukar lagi. Yakin melanjutkan?

                <x-slot:footer>
                    <x-ui.button variant="secondary" type="button"
                                 x-on:click="$dispatch('modal-close', 'kunci-bagan')">Batal</x-ui.button>

                    <form method="POST" action="{{ route('admin.turnamen.bagan.kunci', [$tournament, $weightClass]) }}">
                        @csrf
                        <x-ui.button type="submit">Kunci</x-ui.button>
                    </form>
                </x-slot:footer>
            </x-ui.modal>
        @endresource
    @else
        @resource(rk('bagan', ResourceAction::Delete))
            <x-ui.modal id="buka-kunci" title="Buka kunci bagan" size="sm">
                <form method="POST" id="buka-kunci-form"
                      action="{{ route('admin.turnamen.bagan.buka-kunci', [$tournament, $weightClass]) }}"
                      class="space-y-4">
                    @csrf

                    <x-ui.alert variant="danger" title="Tindakan ini tercatat di jejak audit">
                        Kontingen mungkin sudah melihat bagan ini dan menyiapkan lawannya. Gunakan hanya
                        untuk memperbaiki kesalahan penyusunan, bukan untuk mengubah hasil undian.
                    </x-ui.alert>

                    <x-ui.textarea name="alasan" label="Alasan" rows="3" required
                                   hint="Dibaca dari jejak audit bila kelak dipertanyakan." />
                </form>

                <x-slot:footer>
                    <x-ui.button variant="secondary" type="button"
                                 x-on:click="$dispatch('modal-close', 'buka-kunci')">Batal</x-ui.button>
                    <x-ui.button variant="danger" type="submit" form="buka-kunci-form">Buka kunci</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endresource
    @endif
</x-layouts.admin>
