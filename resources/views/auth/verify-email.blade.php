<x-layouts.guest heading="Verifikasi email Anda"
                 description="Kami sudah mengirim tautan verifikasi ke email Anda. Klik tautan itu untuk melanjutkan.">
    @if (session('status') === 'verification-link-sent')
        <x-si.callout varian="berhasil">Tautan verifikasi baru sudah dikirim.</x-si.callout>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-si.tombol tipe="submit">Kirim ulang tautan</x-si.tombol>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-si.tombol tipe="submit" varian="kedua">Keluar</x-si.tombol>
        </form>
    </div>
</x-layouts.guest>
