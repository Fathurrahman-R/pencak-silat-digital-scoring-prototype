<x-layouts.admin :heading="'Ubah '.$permission->name"
                 :breadcrumb="['Permission' => route('admin.permissions.index'), 'Ubah' => null]">
    <form method="POST" action="{{ route('admin.permissions.update', $permission) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @include('admin.permissions.form', ['permission' => $permission])

        <div class="flex items-center gap-2">
            <x-ui.button type="submit">Simpan perubahan</x-ui.button>
            <x-ui.button :href="route('admin.permissions.index')" variant="secondary">Batal</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
