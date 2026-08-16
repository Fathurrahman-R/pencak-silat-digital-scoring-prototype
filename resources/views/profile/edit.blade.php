<x-layouts.admin heading="Profil saya"
                 description="Data akun, keamanan, dan foto profil."
                 :breadcrumb="['Profil' => null]">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card title="Data profil">
                <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <x-ui.input name="name" label="Nama lengkap" :value="$user->name" required />
                    <x-ui.input name="email" type="email" label="Email" :value="$user->email" required
                                hint="Mengganti email akan meminta verifikasi ulang." />

                    @if (! $user->hasVerifiedEmail())
                        <x-ui.alert variant="warning">Email Anda belum diverifikasi.</x-ui.alert>
                    @endif

                    <x-ui.button type="submit">Simpan</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card title="Ganti kata sandi">
                {{-- Route dan validasinya disediakan Fortify (Features::updatePasswords). --}}
                <form method="POST" action="{{ route('user-password.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <x-ui.input name="current_password" type="password" label="Kata sandi saat ini" required autocomplete="current-password" />
                    <x-ui.input name="password" type="password" label="Kata sandi baru" required autocomplete="new-password" />
                    <x-ui.input name="password_confirmation" type="password" label="Ulangi kata sandi baru" required autocomplete="new-password" />

                    <x-ui.button type="submit">Perbarui kata sandi</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card title="Verifikasi dua langkah"
                       subtitle="Menambah kode sekali pakai dari aplikasi autentikator saat masuk.">
                @if ($user->two_factor_secret)
                    <div class="space-y-4">
                        <x-ui.badge variant="success" dot>Aktif</x-ui.badge>

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
                            <x-ui.button type="submit" variant="danger" size="sm">Matikan</x-ui.button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('two-factor.enable') }}">
                        @csrf
                        <x-ui.button type="submit" size="sm">Aktifkan</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.card title="Foto profil">
                <div class="flex flex-col items-center gap-4">
                    <x-ui.avatar :user="$user" size="xl" />

                    <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="w-full space-y-3">
                        @csrf
                        <x-ui.file-upload name="avatar" accept="image/*" hint="JPG atau PNG, maksimal 2 MB." />
                        <x-ui.button type="submit" size="sm" block>Unggah</x-ui.button>
                    </form>

                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="w-full">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="secondary" size="sm" block>Hapus foto</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card title="Role saya">
                <div class="flex flex-wrap gap-1">
                    @forelse ($user->roles as $role)
                        <x-ui.badge :variant="$role->isSuperAdmin() ? 'purple' : 'primary'">{{ $role->displayName() }}</x-ui.badge>
                    @empty
                        <span class="text-sm text-ink-muted">Belum punya role.</span>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card title="Hapus akun" subtitle="Tindakan ini permanen dan tidak bisa dibatalkan.">
                <x-ui.button type="button" variant="danger" size="sm" x-on:click="$dispatch('modal-open', 'hapus-akun')">
                    Hapus akun saya
                </x-ui.button>

                <x-ui.modal id="hapus-akun" title="Hapus akun" size="sm">
                    <p>Semua data yang terkait akun ini akan hilang. Masukkan kata sandi untuk mengonfirmasi.</p>

                    <form method="POST" action="{{ route('profile.destroy') }}" id="form-hapus-akun" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <x-ui.input name="password" type="password" label="Kata sandi" required />
                    </form>

                    <x-slot:footer>
                        <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('modal-close', 'hapus-akun')">Batal</x-ui.button>
                        <x-ui.button variant="danger" type="submit" form="form-hapus-akun">Hapus akun</x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
