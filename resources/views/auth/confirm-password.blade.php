<x-layouts.guest heading="Konfirmasi kata sandi"
                 description="Bagian ini butuh konfirmasi identitas. Masukkan kembali kata sandi Anda.">
    <x-auth.errors />

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <x-si.isian name="password" tipe="password" label="Kata sandi" wajib autofocus autocomplete="current-password" />

        <x-si.tombol tipe="submit" penuh>Konfirmasi</x-si.tombol>
    </form>
</x-layouts.guest>
