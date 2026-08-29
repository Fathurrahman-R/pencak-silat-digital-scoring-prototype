<x-layouts.admin :heading="'Ubah role '.$role->name"
                 :breadcrumb="['Role' => route('admin.roles.index'), 'Ubah' => null]">
    @if ($role->isSuperAdmin())
        <x-si.callout varian="keterangan" class="mb-6">
            Role ini melewati seluruh pengecekan permission, jadi centangan di bawah tidak berpengaruh padanya.
        </x-si.callout>
    @endif

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @include('admin.roles.form', [
            'role' => $role,
            'resources' => $resources,
            'loosePermissions' => $loosePermissions,
        ])

        <div class="flex items-center gap-2">
            <x-si.tombol tipe="submit">Simpan perubahan</x-si.tombol>
            <x-si.tombol :tautan="route('admin.roles.index')" varian="kedua">Batal</x-si.tombol>
        </div>
    </form>
</x-layouts.admin>
