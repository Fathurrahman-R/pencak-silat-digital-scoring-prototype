<x-layouts.guest-split heading="Buat akun baru"
                       description="Akun official kontingen untuk mendaftarkan pesilat ke kejuaraan.">
    <x-slot:aside>
        <x-auth.panel-kejuaraan />
    </x-slot:aside>

    <x-auth.errors />

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-ui.input name="name" label="Nama lengkap" required autofocus autocomplete="name" />

        <x-ui.input name="email" type="email" label="Email" placeholder="nama@contoh.id" required autocomplete="username" />

        <x-ui.input name="password" type="password" label="Kata sandi" placeholder="••••••••" required autocomplete="new-password"
                    hint="Minimal 8 karakter." />

        <x-ui.input name="password_confirmation" type="password" label="Ulangi kata sandi" placeholder="••••••••" required autocomplete="new-password" />

        <x-ui.button type="submit" block>Daftar</x-ui.button>

        <p class="text-sm text-ink-muted">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-medium text-link hover:underline">Masuk</a>
        </p>
    </form>
</x-layouts.guest-split>
