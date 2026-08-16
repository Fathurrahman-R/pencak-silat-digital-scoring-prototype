<x-layouts.admin heading="Tambah role"
                 :breadcrumb="['Role' => route('admin.roles.index'), 'Tambah' => null]">
    <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-4">
        @csrf

        @include('admin.roles.form', ['resources' => $resources, 'loosePermissions' => $loosePermissions])

        <div class="flex items-center gap-2">
            <x-ui.button type="submit">Simpan</x-ui.button>
            <x-ui.button :href="route('admin.roles.index')" variant="secondary">Batal</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
