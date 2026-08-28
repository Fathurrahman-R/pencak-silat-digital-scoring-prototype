<x-layouts.guest heading="Lupa kata sandi"
                 description="Masukkan email Anda, kami kirimkan tautan untuk membuat kata sandi baru.">
    <x-auth.errors />

    @if (session('status'))
        <x-si.callout varian="berhasil">{{ session('status') }}</x-si.callout>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-si.isian name="email" tipe="email" label="Email" wajib autofocus />

        <x-si.tombol tipe="submit" penuh>Kirim tautan</x-si.tombol>

        <p class="text-sm text-ink-muted">
            <a href="{{ route('login') }}" class="font-medium text-link hover:underline">Kembali ke halaman masuk</a>
        </p>
    </form>
</x-layouts.guest>
