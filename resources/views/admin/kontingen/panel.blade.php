@php use App\Enums\ResourceAction; @endphp

<div class="flex flex-col gap-5">
    <h4 class="font-display text-base font-semibold text-ink">{{ $contingent->name }}</h4>

    <dl class="flex flex-col gap-3.5 text-base2">
        <div class="flex gap-3.5">
            <dt class="w-[110px] shrink-0 text-ink-muted">Daerah</dt>
            <dd class="text-ink">{{ $contingent->region ?? '—' }}</dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[110px] shrink-0 text-ink-muted">Official</dt>
            <dd class="text-ink">{{ $contingent->official?->name ?? 'Belum ditentukan' }}</dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[110px] shrink-0 text-ink-muted">Kontak</dt>
            <dd class="text-ink">
                {{ $contingent->contact_name ?? '—' }}
                @if ($contingent->contact_phone)
                    <span class="block text-ink-muted">{{ $contingent->contact_phone }}</span>
                @endif
            </dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[110px] shrink-0 text-ink-muted">Atlet</dt>
            <dd class="text-ink">{{ $contingent->athletes_count }}</dd>
        </div>

        <div class="flex gap-3.5">
            <dt class="w-[110px] shrink-0 text-ink-muted">Pendaftaran</dt>
            <dd class="text-ink">{{ $contingent->registrations_count }}</dd>
        </div>
    </dl>

    <div class="flex flex-wrap gap-2 border-t border-line pt-4">
        @resource(rk('atlet', ResourceAction::View))
            <x-ui.button :href="route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent])" size="sm">
                <x-ui.icon name="users" class="size-4" />
                Kelola atlet
            </x-ui.button>
        @endresource
    </div>
</div>
