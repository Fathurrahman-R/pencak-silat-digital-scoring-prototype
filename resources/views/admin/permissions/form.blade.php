@php($permission ??= null)

<x-si.kartu judul="Data permission">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-si.isian name="name" label="Nama" :value="$permission?->name" wajib
                    bantuan="Dipakai saat pengecekan izin, mis. laporan.export atau akses-keuangan." />

        <x-si.isian name="label" label="Label tampilan" :value="$permission?->label"
                    bantuan="Nama yang enak dibaca di daftar permission." />

        <x-si.isian name="group" label="Grup" :value="$permission?->group"
                    bantuan="Untuk mengelompokkan permission di UI." />

        <div class="sm:col-span-2">
            <x-si.isian-panjang name="description" label="Deskripsi" :value="$permission?->description" baris="2" />
        </div>
    </div>
</x-si.kartu>

@if ($permission && $permission->mappings->isNotEmpty())
    <x-si.kartu judul="Dipakai resource key" subjudul="Mengganti nama permission tidak memutus pemetaan ini." class="mt-6">
        <ul class="space-y-2 text-sm">
            @foreach ($permission->mappings as $mapping)
                <li class="flex items-center gap-2">
                    <x-si.ikon nama="link" class="h-4 w-4 text-ink-muted" />
                    <code>{{ $mapping->key() }}</code>
                </li>
            @endforeach
        </ul>
    </x-si.kartu>
@endif
