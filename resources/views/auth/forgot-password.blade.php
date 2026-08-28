<x-layouts.guest heading="Lupa kata sandi"
                 description="Masukkan email Anda, kami kirimkan tautan untuk membuat kata sandi baru.">
    <x-auth.errors />

    @if (session('status'))
        <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-ui.input name="email" type="email" label="Email" placeholder="nama@contoh.id" required autofocus />

        <x-ui.button type="submit" block>Kirim tautan reset</x-ui.button>

        <p class="text-sm text-ink-muted">
            <a href="{{ route('login') }}" class="font-medium text-link hover:underline">Kembali ke halaman masuk</a>
        </p>
    </form>
</x-layouts.guest>
