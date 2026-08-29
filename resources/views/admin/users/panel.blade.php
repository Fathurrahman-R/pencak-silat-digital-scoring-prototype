@php use App\Enums\ResourceAction; @endphp

<x-layouts.fragmen :title="'Pengguna — '.$user->name" :breadcrumb="['Pengguna' => route('admin.users.index'), $user->name => null]">
    {{--
        Fragmen panel detail. Dikembalikan tanpa layout dan disisipkan drawer, jadi
        di sini tidak boleh ada <x-layouts.*>, <head>, atau skrip.
    --}}

    <div class="flex flex-col gap-5">
        <div class="flex items-center gap-3">
            <span class="relative inline-flex">
                <x-si.foto :user="$user" ukuran="sedang" />
                <x-si.titik-hadir :keadaan="$user->is_active ? 'aktif' : 'mati'" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="truncate font-display text-base font-semibold text-ink">{{ $user->name }}</div>
                <div class="truncate text-sm2 text-ink-muted">{{ $user->email }}</div>
            </div>

            <x-si.badge :varian="$user->is_active ? 'success' : 'danger'" dot>
                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
            </x-si.badge>
        </div>

        <dl class="flex flex-col gap-3.5 text-base2">
            <div class="flex gap-3.5">
                <dt class="w-[120px] shrink-0 text-ink-muted">Role</dt>
                <dd class="flex flex-wrap gap-1">
                    @forelse ($user->roles as $role)
                        <x-si.badge :varian="$role->isSuperAdmin() ? 'purple' : 'primary'">{{ $role->displayName() }}</x-si.badge>
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
                <x-si.tombol :tautan="route('admin.users.edit', $user)" ukuran="kecil" ikon="pencil">
                    Ubah pengguna
                </x-si.tombol>
            </x-can>
        </div>
    </div>
</x-layouts.fragmen>
