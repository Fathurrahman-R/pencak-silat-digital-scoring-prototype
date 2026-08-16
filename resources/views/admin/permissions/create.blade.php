<x-layouts.admin heading="Tambah permission"
                 :breadcrumb="['Permission' => route('admin.permissions.index'), 'Tambah' => null]">
    <form method="POST" action="{{ route('admin.permissions.store') }}" class="space-y-4">
        @csrf

        @include('admin.permissions.form')

        <div class="flex items-center gap-2">
            <x-ui.button type="submit">Simpan</x-ui.button>
            <x-ui.button :href="route('admin.permissions.index')" variant="secondary">Batal</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
