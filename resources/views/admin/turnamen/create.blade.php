<x-layouts.admin heading="Kejuaraan baru"
                 :breadcrumb="['Kejuaraan' => route('admin.turnamen.index'), 'Baru' => null]">
    <form method="POST" action="{{ route('admin.turnamen.store') }}" class="space-y-4">
        @csrf

        <x-si.callout varian="keterangan" judul="Kelas dan nomor terisi otomatis">
            Kejuaraan baru langsung dibekali seluruh isi naskah 2025: setelan peraturan,
            174 kelas tanding, dan 64 nomor jurus. Matikan yang tidak dipertandingkan setelah ini.
        </x-si.callout>

        @include('admin.turnamen.form')

        <div class="flex items-center gap-2">
            <x-si.tombol tipe="submit">Simpan</x-si.tombol>
            <x-si.tombol :tautan="route('admin.turnamen.index')" varian="kedua">Batal</x-si.tombol>
        </div>
    </form>
</x-layouts.admin>
