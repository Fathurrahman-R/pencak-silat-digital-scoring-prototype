@php use App\Enums\ResourceAction; @endphp

<div class="flex flex-col gap-5">
    <div class="flex items-start justify-between gap-3">
        <h4 class="font-display text-base font-semibold text-ink">{{ $tournament->name }}</h4>
        <x-ui.badge :variant="$tournament->status->variant()">{{ $tournament->status->label() }}</x-ui.badge>
    </div>

    @if ($tournament->description)
        <p class="text-base2 text-ink-secondary">{{ $tournament->description }}</p>
    @endif

    <dl class="flex flex-col gap-3.5 text-base2">
        <div class="flex gap-3.5">
            <dt class="w-[130px] shrink-0 text-ink-muted">Penyelenggara</dt>
            <dd class="text-ink">{{ $tournament->organizer ?? '—' }}</dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[130px] shrink-0 text-ink-muted">Tempat</dt>
            <dd class="text-ink">{{ $tournament->venue ?? '—' }}</dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[130px] shrink-0 text-ink-muted">Jadwal</dt>
            <dd class="text-ink">
                {{ $tournament->starts_on?->translatedFormat('d M Y') ?? '—' }}
                @if ($tournament->ends_on)
                    – {{ $tournament->ends_on->translatedFormat('d M Y') }}
                @endif
            </dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[130px] shrink-0 text-ink-muted">Pendaftaran</dt>
            <dd class="text-ink">
                @if ($tournament->registration_closes_at)
                    Ditutup {{ $tournament->registration_closes_at->translatedFormat('d M Y, H:i') }}
                @else
                    Belum dijadwalkan
                @endif
            </dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[130px] shrink-0 text-ink-muted">Gelanggang</dt>
            <dd class="text-ink">{{ $tournament->arenas_count }}</dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[130px] shrink-0 text-ink-muted">Kelas tanding</dt>
            <dd class="text-ink">{{ $tournament->weight_classes_count }}</dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[130px] shrink-0 text-ink-muted">Nomor jurus</dt>
            <dd class="text-ink">{{ $tournament->jurus_events_count }}</dd>
        </div>
    </dl>

    <div class="flex flex-wrap gap-2 border-t border-line pt-4">
        @resource(rk('turnamen', ResourceAction::Update))
            <x-ui.button :href="route('admin.turnamen.edit', $tournament)" size="sm">
                <x-ui.icon name="pencil" class="size-4" />
                Ubah kejuaraan
            </x-ui.button>
        @endresource

        @resource(rk('gelanggang', ResourceAction::View))
            <x-ui.button :href="route('admin.turnamen.gelanggang.index', $tournament)" variant="secondary" size="sm">
                <x-ui.icon name="layout-grid" class="size-4" />
                Gelanggang
            </x-ui.button>
        @endresource
    </div>
</div>
