@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin :heading="$resource->label"
                 :description="$resource->description"
                 :breadcrumb="['Resource' => route('admin.resources.index'), $resource->key => null]">
    <x-slot:actions>
        <x-can :resource="rk('mappings', ResourceAction::View)">
            <x-si.tombol :tautan="route('admin.mappings.index', ['resource' => $resource->key])" varian="kedua" ukuran="kecil">
                <x-si.ikon nama="link" class="h-4 w-4" />
                Atur pemetaan
            </x-si.tombol>
        </x-can>

        <x-can :resource="rk('resources', ResourceAction::Update)">
            <x-si.tombol :tautan="route('admin.resources.edit', $resource)" ukuran="kecil" ikon="pencil">
                Ubah
            </x-si.tombol>
        </x-can>
    </x-slot:actions>

    <x-si.kartu judul="Resource key" keterangan="Salin key ini ke route, Blade, atau policy.">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-ink-muted">
                    <tr>
                        <th class="pb-2 pr-4">Resource key</th>
                        <th class="pb-2 pr-4">Permission</th>
                        <th class="pb-2">Dimiliki role</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($resource->mappings->sortBy(fn ($m) => $m->action->value) as $mapping)
                        <tr>
                            <td class="py-3 pr-4">
                                <code class="rounded-sm bg-code px-2 py-1 font-mono text-code-ink">{{ $mapping->key() }}</code>
                                <span class="ms-2 text-xs text-ink-muted">{{ $mapping->action->label() }}</span>
                            </td>

                            <td class="py-3 pr-4">
                                @if ($mapping->isMapped())
                                    <code>{{ $mapping->permission->name }}</code>
                                @else
                                    <x-si.badge varian="bahaya">belum dipetakan</x-si.badge>
                                @endif
                            </td>

                            <td class="py-3">
                                @if ($mapping->isMapped() && $mapping->permission->roles->isNotEmpty())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($mapping->permission->roles as $role)
                                            <x-si.badge varian="netral">{{ $role->name }}</x-si.badge>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-ink-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-si.kartu>

    <x-si.kartu judul="Cara memakainya" class="mt-6">
        <div class="space-y-4 text-sm">
            <div>
                <p class="mb-1 font-medium text-ink">Menjaga route</p>
<pre class="overflow-x-auto rounded-md border border-line bg-code p-4 font-mono text-xs text-code-ink"><code>Route::get('/{{ $resource->key }}', ...)
    -&gt;middleware('resource:{{ $resource->key }}.{{ ResourceAction::View->value }}');</code></pre>
            </div>

            <div>
                <p class="mb-1 font-medium text-ink">Menyembunyikan tombol</p>
<pre class="overflow-x-auto rounded-md border border-line bg-code p-4 font-mono text-xs text-code-ink"><code>&lt;x-can resource="{{ $resource->key }}.{{ ResourceAction::Create->value }}"&gt;
    &lt;x-si.tombol&gt;Tambah&lt;/x-si.tombol&gt;
&lt;/x-can&gt;</code></pre>
            </div>

            <div>
                <p class="mb-1 font-medium text-ink">Di kode PHP</p>
<pre class="overflow-x-auto rounded-md border border-line bg-code p-4 font-mono text-xs text-code-ink"><code>rk('{{ $resource->key }}', ResourceAction::Update);</code></pre>
            </div>
        </div>
    </x-si.kartu>
</x-layouts.admin>
