@php use App\Enums\ResourceAction; @endphp

<x-layouts.fragmen :title="'Role — '.$role->name" :breadcrumb="['Role' => route('admin.roles.index'), $role->name => null]">
    <div class="flex flex-col gap-5">
        <div class="flex items-center gap-2">
            <h4 class="font-display text-base font-semibold text-ink">{{ $role->name }}</h4>

            @if ($role->isSuperAdmin())
                <x-ui.badge variant="purple" pill>super admin</x-ui.badge>
            @endif

            @if ($role->is_locked)
                <x-ui.badge variant="warning" pill>inti</x-ui.badge>
            @endif
        </div>

        @if ($role->label)
            <p class="text-base2 text-ink-secondary">{{ $role->label }}</p>
        @endif

        <dl class="flex flex-col gap-3.5 text-base2">
            <div class="flex gap-3.5">
                <dt class="w-[110px] shrink-0 text-ink-muted">Permission</dt>
                <dd class="text-ink">{{ $role->isSuperAdmin() ? 'Semua' : $role->permissions->count() }}</dd>
            </div>

            <div class="flex gap-3.5">
                <dt class="w-[110px] shrink-0 text-ink-muted">Pengguna</dt>
                <dd class="text-ink">{{ $role->users()->count() }}</dd>
            </div>
        </dl>

        @unless ($role->isSuperAdmin())
            <div class="flex flex-wrap gap-1.5">
                @forelse ($role->permissions as $permission)
                    <code class="rounded-sm bg-code px-1.5 py-0.5 font-mono text-xs text-code-ink">{{ $permission->name }}</code>
                @empty
                    <span class="text-sm2 text-ink-muted">Belum ada permission.</span>
                @endforelse
            </div>
        @endunless

        <div class="flex flex-wrap gap-2 border-t border-line pt-4">
            <x-can :resource="rk('roles', ResourceAction::Update)">
                <x-ui.button :href="route('admin.roles.edit', $role)" size="sm">
                    <x-ui.icon name="pencil" class="size-4" />
                    Ubah role
                </x-ui.button>
            </x-can>
        </div>
    </div>
</x-layouts.fragmen>
