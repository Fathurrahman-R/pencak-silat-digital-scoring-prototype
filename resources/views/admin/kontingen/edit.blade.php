<x-layouts.admin :heading="$contingent->name"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kontingen' => route('admin.turnamen.kontingen.index', $tournament),
                     $contingent->name => null,
                 ]">
    <form method="POST" action="{{ route('admin.turnamen.kontingen.update', [$tournament, $contingent]) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @include('admin.kontingen.form', ['contingent' => $contingent])

        <div class="flex items-center gap-2">
            <x-ui.button type="submit">Simpan perubahan</x-ui.button>
            <x-ui.button :href="route('admin.turnamen.kontingen.index', $tournament)" variant="secondary">Kembali</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
