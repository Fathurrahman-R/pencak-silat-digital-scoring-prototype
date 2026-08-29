<x-layouts.admin heading="Tambah resource"
                 :breadcrumb="['Resource' => route('admin.resources.index'), 'Tambah' => null]">
    <form method="POST" action="{{ route('admin.resources.store') }}" class="space-y-4">
        @csrf

        @include('admin.resources.form', ['actions' => $actions])

        <div class="flex items-center gap-2">
            <x-si.tombol tipe="submit">Simpan</x-si.tombol>
            <x-si.tombol :tautan="route('admin.resources.index')" varian="kedua">Batal</x-si.tombol>
        </div>
    </form>
</x-layouts.admin>
