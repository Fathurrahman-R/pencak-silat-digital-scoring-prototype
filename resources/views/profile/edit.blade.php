<x-layouts.admin heading="Profil saya"
                 description="Data akun, keamanan, dan foto profil."
                 :breadcrumb="['Profil' => null]">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-si.kartu judul="Data profil">
                <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <x-si.isian name="name" label="Nama lengkap" :value="$user->name" wajib />
                    <x-si.isian name="email" tipe="email" label="Email" :value="$user->email" wajib
                                bantuan="Mengganti email akan meminta verifikasi ulang." />

                    @if (! $user->hasVerifiedEmail())
                        <x-si.callout varian="perhatian">Email Anda belum diverifikasi.</x-si.callout>
                    @endif

                    <x-si.tombol tipe="submit">Simpan</x-si.tombol>
                </form>
            </x-si.kartu>

            <x-si.kartu judul="Ganti kata sandi">
                {{-- Route dan validasinya disediakan Fortify (Features::updatePasswords). --}}
                <form method="POST" action="{{ route('user-password.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <x-si.isian name="current_password" tipe="password" label="Kata sandi saat ini" wajib autocomplete="current-password" />
                    <x-si.isian name="password" tipe="password" label="Kata sandi baru" wajib autocomplete="new-password" />
                    <x-si.isian name="password_confirmation" tipe="password" label="Ulangi kata sandi baru" wajib autocomplete="new-password" />

                    <x-si.tombol tipe="submit">Perbarui kata sandi</x-si.tombol>
                </form>
            </x-si.kartu>

            <x-si.kartu judul="Verifikasi dua langkah"
                        subjudul="Menambah kode sekali pakai dari aplikasi autentikator saat masuk.">
                @if ($user->two_factor_secret)
                    <div class="space-y-4">
                        <x-si.badge varian="sukses">Aktif</x-si.badge>

                        <div class="rounded-lg border border-line p-4">
                            {!! $user->twoFactorQrCodeSvg() !!}
                        </div>

                        <details class="text-sm">
                            <summary class="cursor-pointer font-medium text-ink">Lihat kode pemulihan</summary>
                            <ul class="mt-2 space-y-1 font-mono text-xs text-ink-secondary">
                                @foreach (json_decode(decrypt($user->two_factor_recovery_codes), true) as $code)
                                    <li>{{ $code }}</li>
                                @endforeach
                            </ul>
                        </details>

                        <form method="POST" action="{{ route('two-factor.disable') }}">
                            @csrf
                            @method('DELETE')
                            <x-si.tombol tipe="submit" varian="bahaya" ukuran="kecil">Matikan</x-si.tombol>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('two-factor.enable') }}">
                        @csrf
                        <x-si.tombol tipe="submit" ukuran="kecil">Aktifkan</x-si.tombol>
                    </form>
                @endif
            </x-si.kartu>
        </div>

        <div class="space-y-4">
            <x-si.kartu judul="Foto profil">
                <div class="flex flex-col items-center gap-4">
                    <x-si.foto :user="$user" ukuran="lebar" />

                    <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="w-full space-y-3">
                        @csrf
                        <x-si.unggah name="avatar" accept="image/*" bantuan="JPG atau PNG, maksimal 2 MB." />
                        <x-si.tombol tipe="submit" ukuran="kecil" block>Unggah</x-si.tombol>
                    </form>

                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="w-full">
                            @csrf
                            @method('DELETE')
                            <x-si.tombol tipe="submit" varian="kedua" ukuran="kecil" block>Hapus foto</x-si.tombol>
                        </form>
                    @endif
                </div>
            </x-si.kartu>

            <x-si.kartu judul="Role saya">
                <div class="flex flex-wrap gap-1">
                    @forelse ($user->roles as $role)
                        <x-si.badge :varian="$role->isSuperAdmin() ? 'purple' : 'primary'">{{ $role->displayName() }}</x-si.badge>
                    @empty
                        <span class="text-sm text-ink-muted">Belum punya role.</span>
                    @endforelse
                </div>
            </x-si.kartu>

            <x-si.kartu judul="Hapus akun" subjudul="Tindakan ini permanen dan tidak bisa dibatalkan.">
                <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil" x-on:click="$dispatch('modal-open', 'hapus-akun')">
                    Hapus akun saya
                </x-si.tombol>

                <x-si.modal id="hapus-akun" judul="Hapus akun" ukuran="kecil">
                    <p>Semua data yang terkait akun ini akan hilang. Masukkan kata sandi untuk mengonfirmasi.</p>

                    <form method="POST" action="{{ route('profile.destroy') }}" id="form-hapus-akun" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <x-si.isian name="password" tipe="password" label="Kata sandi" wajib />
                    </form>

                    <x-slot:footer>
                        <x-si.tombol varian="kedua" tipe="button" x-on:click="$dispatch('modal-close', 'hapus-akun')">Batal</x-si.tombol>
                        <x-si.tombol varian="bahaya" tipe="submit" form="form-hapus-akun">Hapus akun</x-si.tombol>
                    </x-slot:footer>
                </x-si.modal>
            </x-si.kartu>
        </div>
    </div>
</x-layouts.admin>
