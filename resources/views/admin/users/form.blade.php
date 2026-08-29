@php
    // Dipanggil lewat @include dari create/edit. $user null berarti form tambah.
    $user ??= null;
    $isEdit = $user !== null;
@endphp

<x-si.kartu :padding="false">
    <div class="grid gap-5 p-[26px] [grid-template-columns:repeat(auto-fit,minmax(250px,1fr))]">
        <x-si.isian name="name" label="Nama lengkap" :value="$user?->name" wajib />

        <x-si.isian name="email" tipe="email" label="Email" :value="$user?->email" wajib />

        <x-si.isian name="password" tipe="password" label="Kata sandi"
                    :wajib="! $isEdit"
                    autocomplete="new-password"
                    :bantuan="$isEdit ? 'Kosongkan bila tidak ingin mengubah kata sandi.' : 'Minimal 8 karakter.'" />

        <x-si.isian name="password_confirmation" tipe="password" label="Ulangi kata sandi"
                    :wajib="! $isEdit" autocomplete="new-password" />
    </div>

    <div class="px-[26px] pb-[26px]">
        <h3 class="text-lg2 font-semibold text-ink">Status &amp; peran</h3>
        <p class="mt-0.5 text-base2 text-ink-secondary">Peran menentukan permission yang dimiliki pengguna.</p>

        <div class="mt-2 flex items-center justify-between gap-4 border-b border-line py-[13px]">
            <div>
                <div class="text-[14.5px] font-medium text-ink">Akun aktif</div>
                <div class="text-sm2 text-ink-muted">Akun nonaktif tidak bisa masuk dan sesinya langsung diakhiri.</div>
            </div>
            <x-si.saklar name="is_active" :dicentang="old('is_active', $user?->is_active ?? true)" class="shrink-0" />
        </div>

        <div class="mt-3.5 flex flex-wrap gap-x-6 gap-y-3">
            @foreach ($roles as $role)
                <x-si.centang name="roles[]"
                              :value="$role->name"
                              :label="$role->displayName()"
                              :bantuan="$role->description"
                              :id="'role_'.$role->id"
                              :dicentang="in_array($role->name, old('roles', $user?->roles->pluck('name')->all() ?? []), true)" />
            @endforeach
        </div>

        @error('roles')
            <p class="mt-2 text-sm2 text-danger">{{ $message }}</p>
        @enderror
    </div>
</x-si.kartu>
