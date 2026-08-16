<x-layouts.admin heading="Tambah resource"
                 :breadcrumb="['Resource' => route('admin.resources.index'), 'Tambah' => null]">
    <form method="POST" action="{{ route('admin.resources.store') }}" class="space-y-4">
        @csrf

        @include('admin.resources.form', ['actions' => $actions])

        <div class="flex items-center gap-2">
            <x-ui.button type="submit">Simpan</x-ui.button>
            <x-ui.button :href="route('admin.resources.index')" variant="secondary">Batal</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
