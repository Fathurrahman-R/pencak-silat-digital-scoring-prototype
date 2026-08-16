<x-layouts.admin :heading="'Ubah role '.$role->name"
                 :breadcrumb="['Role' => route('admin.roles.index'), 'Ubah' => null]">
    @if ($role->isSuperAdmin())
        <x-ui.alert variant="info" class="mb-6">
            Role ini melewati seluruh pengecekan permission, jadi centangan di bawah tidak berpengaruh padanya.
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @include('admin.roles.form', [
            'role' => $role,
            'resources' => $resources,
            'loosePermissions' => $loosePermissions,
        ])

        <div class="flex items-center gap-2">
            <x-ui.button type="submit">Simpan perubahan</x-ui.button>
            <x-ui.button :href="route('admin.roles.index')" variant="secondary">Batal</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
