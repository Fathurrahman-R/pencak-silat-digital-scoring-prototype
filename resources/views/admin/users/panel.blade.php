@php use App\Enums\ResourceAction; @endphp

<x-layouts.fragmen :title="'Pengguna — '.$user->name" :breadcrumb="['Pengguna' => route('admin.users.index'), $user->name => null]">
    {{--
        Fragmen panel detail. Dikembalikan tanpa layout dan disisipkan drawer, jadi
        di sini tidak boleh ada <x-layouts.*>, <head>, atau skrip.
    --}}

    <div class="flex flex-col gap-5">
        <div class="flex items-center gap-3">
            <span class="relative inline-flex">
                <x-ui.avatar :user="$user" size="md" />
                <x-ui.presence-dot :status="$user->is_active ? 'online' : 'offline'" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="truncate font-display text-base font-semibold text-ink">{{ $user->name }}</div>
                <div class="truncate text-sm2 text-ink-muted">{{ $user->email }}</div>
            </div>

            <x-ui.badge :variant="$user->is_active ? 'success' : 'danger'" dot>
                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
            </x-ui.badge>
        </div>

        <dl class="flex flex-col gap-3.5 text-base2">
            <div class="flex gap-3.5">
                <dt class="w-[120px] shrink-0 text-ink-muted">Role</dt>
                <dd class="flex flex-wrap gap-1">
                    @forelse ($user->roles as $role)
                        <x-ui.badge :variant="$role->isSuperAdmin() ? 'purple' : 'primary'">{{ $role->displayName() }}</x-ui.badge>
                    @empty
                        <span class="text-ink-muted">—</span>
                    @endforelse
                </dd>
            </div>

            <div class="flex gap-3.5">
                <dt class="w-[120px] shrink-0 text-ink-muted">Email terverifikasi</dt>
                <dd class="text-ink">{{ $user->email_verified_at?->translatedFormat('d M Y H:i') ?? 'Belum' }}</dd>
            </div>

            <div class="flex gap-3.5">
                <dt class="w-[120px] shrink-0 text-ink-muted">Dibuat</dt>
                <dd class="text-ink">{{ $user->created_at?->translatedFormat('d M Y H:i') }}</dd>
            </div>

            <div class="flex gap-3.5">
                <dt class="w-[120px] shrink-0 text-ink-muted">Diperbarui</dt>
                <dd class="text-ink">{{ $user->updated_at?->translatedFormat('d M Y H:i') }}</dd>
            </div>
        </dl>

        <div class="flex flex-wrap gap-2 border-t border-line pt-4">
            <x-can :resource="rk('users', ResourceAction::Update)">
                <x-ui.button :href="route('admin.users.edit', $user)" size="sm">
                    <x-ui.icon name="pencil" class="size-4" />
                    Ubah pengguna
                </x-ui.button>
            </x-can>
        </div>
    </div>
</x-layouts.fragmen>
