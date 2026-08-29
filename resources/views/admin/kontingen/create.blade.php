<x-layouts.admin heading="Daftarkan kontingen"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kontingen' => route('admin.turnamen.kontingen.index', $tournament),
                     'Baru' => null,
                 ]">
    <form method="POST" action="{{ route('admin.turnamen.kontingen.store', $tournament) }}" class="space-y-4">
        @csrf

        @include('admin.kontingen.form')

        <div class="flex items-center gap-2">
            <x-si.tombol tipe="submit">Simpan</x-si.tombol>
            <x-si.tombol :tautan="route('admin.turnamen.kontingen.index', $tournament)" varian="kedua">Batal</x-si.tombol>
        </div>
    </form>
</x-layouts.admin>
