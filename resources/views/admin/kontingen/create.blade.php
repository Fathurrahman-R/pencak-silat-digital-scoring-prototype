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
            <x-ui.button type="submit">Simpan</x-ui.button>
            <x-ui.button :href="route('admin.turnamen.kontingen.index', $tournament)" variant="secondary">Batal</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
