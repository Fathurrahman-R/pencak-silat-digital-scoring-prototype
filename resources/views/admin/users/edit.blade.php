<x-layouts.admin :heading="'Ubah '.$user->name"
                 :breadcrumb="['Pengguna' => route('admin.users.index'), 'Ubah' => null]">
    <form method="POST" action="{{ route('admin.users.update', $user) }}"
          x-data="{ dirty: false }" x-on:input="dirty = true" x-on:change="dirty = true">
        @csrf
        @method('PUT')

        @include('admin.users.form', ['user' => $user, 'roles' => $roles])

        <div class="mt-4 flex items-center gap-3 rounded-lg border border-line bg-surface-raised px-[26px] py-[13px]">
            <span class="flex-1 text-base2 text-ink-muted"
                  x-text="dirty ? 'Ada perubahan belum disimpan' : 'Semua perubahan tersimpan'"></span>
            <x-si.tombol :tautan="route('admin.users.index')" varian="kedua" class="h-[34px]">Batal</x-si.tombol>
            <x-si.tombol tipe="submit" class="h-[34px]">Simpan perubahan</x-si.tombol>
        </div>
    </form>
</x-layouts.admin>
