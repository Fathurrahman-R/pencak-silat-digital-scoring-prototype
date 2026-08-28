<x-layouts.guest-split heading="Buat akun baru"
                       description="Akun official kontingen untuk mendaftarkan pesilat ke kejuaraan.">
    <x-slot:aside>
        <x-auth.panel-kejuaraan />
    </x-slot:aside>

    <x-auth.errors />

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-si.isian name="name" label="Nama lengkap" wajib autofocus autocomplete="name" />

        <x-si.isian name="email" tipe="email" label="Email" wajib autocomplete="username" />

        <x-si.isian name="password" tipe="password" label="Kata sandi" wajib autocomplete="new-password"
                    bantuan="Minimal 8 karakter." />

        <x-si.isian name="password_confirmation" tipe="password" label="Ulangi kata sandi" wajib autocomplete="new-password" />

        <x-si.tombol tipe="submit" penuh>Daftar</x-si.tombol>

        <p class="text-sm text-ink-muted">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-medium text-link hover:underline">Masuk</a>
        </p>
    </form>
</x-layouts.guest-split>
