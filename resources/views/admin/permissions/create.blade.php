<x-layouts.admin heading="Tambah permission"
                 :breadcrumb="['Permission' => route('admin.permissions.index'), 'Tambah' => null]">
    <form method="POST" action="{{ route('admin.permissions.store') }}" class="space-y-4">
        @csrf

        @include('admin.permissions.form')

        <div class="flex items-center gap-2">
            <x-si.tombol tipe="submit">Simpan</x-si.tombol>
            <x-si.tombol :tautan="route('admin.permissions.index')" varian="kedua">Batal</x-si.tombol>
        </div>
    </form>
</x-layouts.admin>
