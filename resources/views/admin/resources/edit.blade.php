<x-layouts.admin :heading="'Ubah resource '.$resource->key"
                 :breadcrumb="['Resource' => route('admin.resources.index'), $resource->key => route('admin.resources.show', $resource), 'Ubah' => null]">
    <x-ui.alert variant="info" class="mb-6">
        Mengganti nama resource mengubah bentuk key-nya (mis. <code>turnamen.view</code> jadi <code>kejuaraan.view</code>),
        tapi tidak mengubah nama permission yang ada di baliknya. Perbarui juga pemakaian key-nya di kode.
    </x-ui.alert>

    <form method="POST" action="{{ route('admin.resources.update', $resource) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @include('admin.resources.form', ['resource' => $resource, 'actions' => $actions])

        <div class="flex items-center gap-2">
            <x-ui.button type="submit">Simpan perubahan</x-ui.button>
            <x-ui.button :href="route('admin.resources.show', $resource)" variant="secondary">Batal</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
