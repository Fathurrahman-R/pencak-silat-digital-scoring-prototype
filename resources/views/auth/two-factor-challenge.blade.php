<x-layouts.guest heading="Verifikasi dua langkah">
    <x-auth.errors />

    {{-- Dua form terpisah: kode dari aplikasi autentikator, atau kode pemulihan
         kalau perangkatnya hilang. Keduanya menuju route yang sama. --}}

    <div x-data="{ recovery: false }" class="space-y-4">
        <p class="text-sm text-ink-muted" x-show="! recovery">
            Masukkan kode 6 digit dari aplikasi autentikator Anda.
        </p>

        <p class="text-sm text-ink-muted" x-show="recovery" x-cloak>
            Masukkan salah satu kode pemulihan yang Anda simpan saat mengaktifkan verifikasi dua langkah.
        </p>

        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
            @csrf

            <div x-show="! recovery">
                <x-si.isian name="code" label="Kode autentikasi" inputmode="numeric" autocomplete="one-time-code" autofocus />
            </div>

            <div x-show="recovery" x-cloak>
                <x-si.isian name="recovery_code" label="Kode pemulihan" autocomplete="one-time-code" />
            </div>

            <x-si.tombol tipe="submit" penuh>Verifikasi</x-si.tombol>
        </form>

        <button type="button" class="text-sm font-medium text-link hover:underline"
                x-on:click="recovery = ! recovery">
            <span x-show="! recovery">Gunakan kode pemulihan</span>
            <span x-show="recovery" x-cloak>Gunakan kode autentikator</span>
        </button>

        {{-- Ikon perisai dilepas: ia tidak membawa arti yang tidak sudah
             dikatakan kalimatnya, dan lambang keamanan justru dipakai
             halaman tiruan untuk terlihat resmi. --}}
        <x-si.callout varian="perhatian">
            Jangan bagikan kode ini ke siapa pun, termasuk yang mengaku dari panitia. Kode kedaluwarsa
            dalam waktu singkat dan hanya berlaku sekali pakai.
        </x-si.callout>
    </div>
</x-layouts.guest>
