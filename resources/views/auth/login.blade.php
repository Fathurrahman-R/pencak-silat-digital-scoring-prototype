<x-layouts.guest-split heading="Masuk"
                       description="Pakai akun yang diberikan panitia kejuaraan.">
    <x-slot:aside>
        <x-auth.panel-kejuaraan />
    </x-slot:aside>

    <x-auth.errors />

    @if (session('status'))
        <x-si.callout varian="berhasil">{{ session('status') }}</x-si.callout>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-si.isian name="email" tipe="email" label="Email" wajib autofocus autocomplete="username" />

        <x-si.isian name="password" tipe="password" label="Kata sandi" wajib autocomplete="current-password" />

        <div class="flex items-center justify-between gap-3">
            <x-si.centang name="remember" label="Ingat saya" :dicentang="old('remember')" />

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-link hover:underline">
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <x-si.tombol tipe="submit" penuh>Masuk</x-si.tombol>

        @if (Route::has('register'))
            <p class="text-sm text-ink-muted">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-medium text-link hover:underline">Daftar</a>
            </p>
        @endif
    </form>
</x-layouts.guest-split>
