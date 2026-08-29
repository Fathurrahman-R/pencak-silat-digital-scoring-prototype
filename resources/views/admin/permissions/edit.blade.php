<x-layouts.admin :heading="'Ubah '.$permission->name"
                 :breadcrumb="['Permission' => route('admin.permissions.index'), 'Ubah' => null]">
    <form method="POST" action="{{ route('admin.permissions.update', $permission) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @include('admin.permissions.form', ['permission' => $permission])

        <div class="flex items-center gap-2">
            <x-si.tombol tipe="submit">Simpan perubahan</x-si.tombol>
            <x-si.tombol :tautan="route('admin.permissions.index')" varian="kedua">Batal</x-si.tombol>
        </div>
    </form>
</x-layouts.admin>
