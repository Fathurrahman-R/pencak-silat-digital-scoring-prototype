<x-layouts.guest heading="Buat kata sandi baru">
    <x-auth.errors />

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-si.isian name="email" tipe="email" label="Email" :value="$request->email" wajib />

        <x-si.isian name="password" tipe="password" label="Kata sandi baru" wajib autocomplete="new-password" autofocus
                    bantuan="Minimal 8 karakter." />

        <x-si.isian name="password_confirmation" tipe="password" label="Ulangi kata sandi baru" wajib autocomplete="new-password" />

        <x-si.tombol tipe="submit" penuh>Simpan kata sandi</x-si.tombol>
    </form>
</x-layouts.guest>
