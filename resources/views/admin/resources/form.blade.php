@php
    $resource ??= null;

    $checked = old('actions', $resource
        ? $resource->mappings->map(fn ($m) => $m->action->value)->all()
        : array_map(fn ($a) => $a->value, array_filter($actions, fn ($a) => $a->isDefault())));

    $checked = array_map('strval', $checked);
@endphp

<div class="grid gap-6 lg:grid-cols-3" x-data="{ key: @js(old('key', $resource?->key ?? '')) }">
    <div class="lg:col-span-2 space-y-4">
        <x-ui.card title="Identitas resource">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.input name="key" label="Nama resource" :value="$resource?->key" required
                                x-model="key"
                                hint="Huruf kecil, tanpa spasi. Contoh: laporan_bulanan. Aksi tidak perlu diketik — pilih di sebelah kanan." />
                </div>

                <x-ui.input name="label" label="Label tampilan" :value="$resource?->label" required
                            hint="Mis. Laporan Bulanan." />

                <x-ui.input name="group" label="Grup" :value="$resource?->group"
                            hint="Untuk mengelompokkan di daftar dan menu." />

                <div class="sm:col-span-2">
                    <x-ui.textarea name="description" label="Deskripsi" :value="$resource?->description" rows="2" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Pratinjau resource key"
                   subtitle="Inilah string yang nanti dipakai di route, Blade, dan menu.">
            <div class="flex flex-wrap gap-2" id="key-preview">
                <template x-for="action in $store.selectedActions.list" :key="action">
                    <code class="rounded-sm bg-code px-2 py-1 font-mono text-sm text-code-ink"
                          x-text="(key || 'resource') + '.' + action"></code>
                </template>
            </div>

            <p class="mt-3 text-sm text-ink-muted" x-show="$store.selectedActions.list.length === 0">
                Belum ada aksi yang dipilih.
            </p>
        </x-ui.card>
    </div>

    <div>
        <x-ui.card title="Aksi" subtitle="Setiap aksi yang dicentang otomatis dibuatkan permission dan langsung dipetakan.">
            <div class="space-y-2">
                @foreach ($actions as $action)
                    <label class="flex items-start gap-2 rounded-lg border border-line p-3">
                        <input type="checkbox" name="actions[]" value="{{ $action->value }}"
                               @checked(in_array($action->value, $checked, true))
                               x-on:change="$store.selectedActions.toggle('{{ $action->value }}', $event.target.checked)"
                               x-init="$store.selectedActions.toggle('{{ $action->value }}', $el.checked)"
                               class="form-check mt-0.5">

                        <span class="text-sm">
                            <span class="flex items-center gap-2 font-medium text-ink">
                                {{ $action->label() }}
                                <code class="text-xs font-normal text-ink-muted">{{ $action->value }}</code>
                                @if ($action->isDestructive())
                                    <x-ui.badge variant="danger" pill>berisiko</x-ui.badge>
                                @endif
                            </span>
                            <span class="block text-xs text-ink-muted">{{ $action->description() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            @error('actions')
                <p class="mt-3 text-sm text-danger">{{ $message }}</p>
            @enderror
        </x-ui.card>
    </div>
</div>

@push('scripts')
    <script>
        // Daftar aksi terpilih dipakai bersama oleh kolom centang dan pratinjau
        // key, jadi disimpan di store Alpine, bukan di salah satu komponen.
        document.addEventListener('alpine:init', function () {
            Alpine.store('selectedActions', {
                list: [],
                toggle: function (action, checked) {
                    var index = this.list.indexOf(action);

                    if (checked && index === -1) {
                        this.list.push(action);
                    }

                    if (! checked && index !== -1) {
                        this.list.splice(index, 1);
                    }
                },
            });
        });
    </script>
@endpush
