<x-layouts.admin :heading="'Ubah resource '.$resource->key"
                 :breadcrumb="['Resource' => route('admin.resources.index'), $resource->key => route('admin.resources.show', $resource), 'Ubah' => null]">
    <x-si.callout varian="keterangan" class="mb-6">
        Mengganti nama resource mengubah bentuk key-nya (mis. <code>turnamen.view</code> jadi <code>kejuaraan.view</code>),
        tapi tidak mengubah nama permission yang ada di baliknya. Perbarui juga pemakaian key-nya di kode.
    </x-si.callout>

    <form method="POST" action="{{ route('admin.resources.update', $resource) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @include('admin.resources.form', ['resource' => $resource, 'actions' => $actions])

        <div class="flex items-center gap-2">
            <x-si.tombol tipe="submit">Simpan perubahan</x-si.tombol>
            <x-si.tombol :tautan="route('admin.resources.show', $resource)" varian="kedua">Batal</x-si.tombol>
        </div>
    </form>
</x-layouts.admin>
