@php
    $role ??= null;
    $selected = old('permissions', $role?->permissions->pluck('id')->all() ?? []);
    $selected = array_map('strval', $selected);
@endphp

<div class="space-y-4">
    <x-ui.card title="Identitas role">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="name" label="Nama sistem" :value="$role?->name" required
                        hint="Huruf kecil tanpa spasi, mis. editor_konten. Dipakai di kode." />

            <x-ui.input name="label" label="Label tampilan" :value="$role?->label"
                        hint="Nama yang dilihat pengguna, mis. Editor Konten." />

            <div class="sm:col-span-2">
                <x-ui.textarea name="description" label="Deskripsi" :value="$role?->description" rows="2" />
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Permission"
               subtitle="Baris adalah resource, kolom adalah aksi. Centang berarti role ini boleh melakukannya.">
        <div class="space-y-4">
            @forelse ($resources as $resource)
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-ink">{{ $resource->label }}</h3>
                        <code class="rounded-sm bg-code px-1.5 py-0.5 font-mono text-xs text-code-ink">{{ $resource->key }}</code>

                        @if ($resource->group)
                            <x-ui.badge>{{ $resource->group }}</x-ui.badge>
                        @endif

                        <button type="button"
                                class="ms-auto text-xs font-medium text-link hover:underline"
                                data-check-group="resource-{{ $resource->id }}">
                            Centang / lepas semua
                        </button>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($resource->mappings->sortBy(fn ($m) => $m->action->value) as $mapping)
                            @if ($mapping->isMapped())
                                <label class="flex items-start gap-2 rounded-lg border border-line p-3 text-sm">
                                    <input type="checkbox"
                                           name="permissions[]"
                                           value="{{ $mapping->permission_id }}"
                                           data-group="resource-{{ $resource->id }}"
                                           @checked(in_array((string) $mapping->permission_id, $selected, true))
                                           class="form-check mt-0.5">

                                    <span>
                                        <span class="block font-medium text-ink">{{ $mapping->action->label() }}</span>
                                        <span class="block text-xs text-ink-muted">{{ $mapping->permission->name }}</span>
                                    </span>
                                </label>
                            @else
                                <div class="flex items-start gap-2 rounded-lg border border-dashed border-danger p-3 text-sm">
                                    <span>
                                        <span class="block font-medium text-ink-muted line-through">{{ $mapping->action->label() }}</span>
                                        <span class="block text-xs text-danger">belum dipetakan ke permission</span>
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @empty
                <x-ui.empty-state title="Belum ada resource"
                                  description="Buat resource lebih dulu supaya permission-nya bisa dibagikan ke role." />
            @endforelse

            @if ($loosePermissions->isNotEmpty())
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-ink">
                        Permission lepas
                        <span class="font-normal text-ink-muted">— tidak dipakai resource key mana pun</span>
                    </h3>

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($loosePermissions as $permission)
                            <label class="flex items-start gap-2 rounded-lg border border-line p-3 text-sm">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                       @checked(in_array((string) $permission->id, $selected, true))
                                       class="form-check mt-0.5">
                                <span class="font-medium text-ink">{{ $permission->displayName() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-ui.card>
</div>

@push('scripts')
    <script>
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-check-group]');

            if (! trigger) {
                return;
            }

            var boxes = document.querySelectorAll('[data-group="' + trigger.dataset.checkGroup + '"]');
            var allChecked = Array.from(boxes).every(function (box) { return box.checked; });

            boxes.forEach(function (box) { box.checked = ! allChecked; });
        });
    </script>
@endpush
