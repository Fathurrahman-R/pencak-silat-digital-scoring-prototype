<x-layouts.admin heading="Kejuaraan baru"
                 :breadcrumb="['Kejuaraan' => route('admin.turnamen.index'), 'Baru' => null]">
    <form method="POST" action="{{ route('admin.turnamen.store') }}" class="space-y-6">
        @csrf

        <x-ui.alert variant="info" title="Kelas dan nomor terisi otomatis">
            Kejuaraan baru langsung dibekali seluruh isi naskah 2025: setelan peraturan,
            174 kelas tanding, dan 64 nomor jurus. Matikan yang tidak dipertandingkan setelah ini.
        </x-ui.alert>

        @include('admin.turnamen.form')

        <div class="flex items-center gap-2">
            <x-ui.button type="submit">Simpan</x-ui.button>
            <x-ui.button :href="route('admin.turnamen.index')" variant="secondary">Batal</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
