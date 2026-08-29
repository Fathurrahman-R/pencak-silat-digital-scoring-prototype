<x-layouts.admin heading="Tambah role"
                 :breadcrumb="['Role' => route('admin.roles.index'), 'Tambah' => null]">
    <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-4">
        @csrf

        @include('admin.roles.form', ['resources' => $resources, 'loosePermissions' => $loosePermissions])

        <div class="flex items-center gap-2">
            <x-si.tombol tipe="submit">Simpan</x-si.tombol>
            <x-si.tombol :tautan="route('admin.roles.index')" varian="kedua">Batal</x-si.tombol>
        </div>
    </form>
</x-layouts.admin>
