@php
    use App\Enums\JenisBerkas;
    use App\Enums\JenisKelamin;
    use App\Enums\ResourceAction;
@endphp

<x-layouts.admin heading="Atlet"
                 :description="$contingent->name.' · '.$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kontingen' => route('admin.turnamen.kontingen.index', $tournament),
                     $contingent->name => null,
                 ]">
    <x-slot:actions>
        @resource(rk('atlet', ResourceAction::Create))
            <x-ui.button type="button" size="sm" x-on:click="$dispatch('modal-open', 'atlet-baru')">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Tambah atlet
            </x-ui.button>
        @endresource
    </x-slot:actions>

    @include('admin.kontingen.tabs')

    <div class="space-y-4">
        {{-- Kolom cari punya kedalaman (shadow-well), jadi ia harus duduk di
             permukaan — kepala kartu daftarnya — bukan mengambang di atas
             latar. --}}
        <x-ui.card title="Daftar atlet" :subtitle="$athletes->total().' atlet terdaftar'">
            <x-slot:actions>
                <form method="GET" class="w-[230px] max-w-full">
                    <x-ui.input name="q" :value="request('q')" placeholder="Cari nama atlet…" />
                </form>
            </x-slot:actions>

            @forelse ($athletes as $athlete)
                @php
                    $golongan = $athlete->golonganUsia($tournament);
                    $kurang = $athlete->berkasKurang($tournament);
                @endphp

                <div class="flex flex-wrap items-center gap-3 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[220px] flex-1">
                        <p class="font-medium text-ink">{{ $athlete->name }}</p>
                        <p class="text-xs text-ink-muted">
                            {{ $athlete->jenis_kelamin->label() }} ·
                            {{ $athlete->birth_date->translatedFormat('d M Y') }} ·
                            {{ $athlete->umurSaatKejuaraan($tournament) }} tahun saat kejuaraan
                            @if ($athlete->weight_claim)
                                · {{ $athlete->weight_claim }} kg
                            @endif
                        </p>
                    </div>

                    <div class="min-w-[140px]">
                        @if ($golongan)
                            <x-ui.badge variant="neutral">{{ $golongan->label() }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="danger">Di luar golongan</x-ui.badge>
                        @endif
                    </div>

                    {{--
                        Kelengkapan berkas ditampilkan per atlet, bukan sebagai
                        satu angka di halaman kontingen. Yang perlu diperbaiki
                        official adalah berkas milik orang tertentu, dan daftar
                        wajibnya pun berbeda antar atlet.
                    --}}
                    <div class="min-w-[200px]">
                        @if ($kurang === [])
                            <x-ui.badge variant="success">Berkas lengkap</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning">Kurang {{ count($kurang) }} berkas</x-ui.badge>
                            <p class="mt-1 text-xs text-ink-muted">
                                {{ implode(', ', array_map(fn (JenisBerkas $j) => $j->label(), $kurang)) }}
                            </p>
                        @endif
                    </div>

                    {{--
                        Tombol BERKATA, bukan empat ikon telanjang berjajar.

                        Susunan lama memasang clipboard, penjepit kertas, pensil,
                        dan tong sampah sebagai ikon 16px yang hanya berlabel
                        `title` — dan tooltip tidak pernah muncul di layar sentuh.
                        Tong sampah di ujung deret itu menghapus atlet beserta
                        seluruh berkas dan pendaftarannya.
                    --}}
                    <div class="flex items-center gap-2">
                        @resource(rk('pendaftaran', ResourceAction::Create))
                            {{-- Membuka formulir pendaftaran nomor dengan atlet ini
                                 sudah terpilih, bukan menyuruh mencarinya lagi. --}}
                            <a href="{{ route('admin.turnamen.kontingen.pendaftaran.index', [$tournament, $contingent, 'atlet' => $athlete->id]) }}"
                               class="inline-flex h-9 items-center rounded-[var(--radius)] border border-line bg-surface-raised px-3 text-[13px] font-medium text-ink">
                                Daftarkan nomor
                            </a>
                        @endresource

                        @resource(rk('atlet', ResourceAction::Update))
                            <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                                         x-on:click="$dispatch('modal-open', 'berkas-{{ $athlete->id }}')">
                                Berkas
                            </x-si.tombol>

                            <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                                         x-on:click="$dispatch('modal-open', 'atlet-ubah-{{ $athlete->id }}')">
                                Ubah
                            </x-si.tombol>
                        @endresource

                        @resource(rk('atlet', ResourceAction::Delete))
                            {{-- Muatan lewat data-*, bukan @js() di dalam ekspresi
                                 atribut: tanda kutip di dalam JSON memutus
                                 pembacaannya. --}}
                            <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                         data-aksi="{{ route('admin.turnamen.kontingen.atlet.destroy', [$tournament, $contingent, $athlete]) }}"
                                         data-nama="{{ $athlete->name }}"
                                         data-berkas="{{ $athlete->documents->count() }}"
                                         x-on:click="$dispatch('hapus-atlet', $el.dataset)">
                                Hapus
                            </x-si.tombol>
                        @endresource
                    </div>
                </div>

                @resource(rk('atlet', ResourceAction::Update))
                    <x-ui.modal :id="'atlet-ubah-'.$athlete->id" title="Ubah atlet" size="md"
                                :open="$errors->any() && old('_form') === 'atlet-ubah-'.$athlete->id">
                        <form method="POST" id="ubah-atlet-{{ $athlete->id }}"
                              action="{{ route('admin.turnamen.kontingen.atlet.update', [$tournament, $contingent, $athlete]) }}"
                              class="space-y-4">
                            @csrf
                            @method('PUT')

                            {{-- Penanda formulir mana yang barusan dikirim, supaya
                                 modal yang gagal validasi — dan hanya modal itu —
                                 terbuka kembali dengan isian terakhirnya. --}}
                            <input type="hidden" name="_form" value="atlet-ubah-{{ $athlete->id }}">

                            @include('admin.atlet.form', ['athlete' => $athlete, 'suffix' => $athlete->id])
                        </form>

                        <x-slot:footer>
                            <x-ui.button variant="secondary" type="button"
                                         x-on:click="$dispatch('modal-close', 'atlet-ubah-{{ $athlete->id }}')">Batal</x-ui.button>
                            <x-ui.button type="submit" form="ubah-atlet-{{ $athlete->id }}">Simpan</x-ui.button>
                        </x-slot:footer>
                    </x-ui.modal>

                    <x-ui.modal :id="'berkas-'.$athlete->id" :title="'Berkas '.$athlete->name" size="lg">
                        <div class="space-y-5">
                            @foreach ($athlete->documents as $document)
                                <div class="flex items-center gap-3 border-b border-line pb-3">
                                    <x-ui.icon name="file-text" class="size-5 shrink-0 text-ink-muted" />

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-base2 text-ink">{{ $document->jenis->label() }}</p>
                                        <p class="truncate text-xs text-ink-muted">
                                            {{ $document->original_name }} · {{ $document->ukuran() }}
                                        </p>
                                    </div>

                                    <x-ui.button size="xs" variant="secondary"
                                                 :href="route('admin.turnamen.kontingen.atlet.berkas.show', [$tournament, $contingent, $athlete, $document])"
                                                 target="_blank">
                                        Lihat
                                    </x-ui.button>

                                    {{--
                                        Sebelumnya satu ikon tong sampah yang
                                        menghapus berkas SEKETIKA. Yang harus
                                        mengunggah ulang bukan panitia melainkan
                                        official kontingen, lewat akunnya sendiri —
                                        satu salah tekan di sini jadi satu panggilan
                                        telepon dan satu pendaftaran yang tertahan.
                                    --}}
                                    <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                                 data-aksi="{{ route('admin.turnamen.kontingen.atlet.berkas.destroy', [$tournament, $contingent, $athlete, $document]) }}"
                                                 data-jenis="{{ $document->jenis->label() }}"
                                                 data-atlet="{{ $athlete->name }}"
                                                 x-on:click="$dispatch('hapus-berkas', $el.dataset)">
                                        Hapus
                                    </x-si.tombol>
                                </div>
                            @endforeach

                            <form method="POST"
                                  action="{{ route('admin.turnamen.kontingen.atlet.berkas.store', [$tournament, $contingent, $athlete]) }}"
                                  enctype="multipart/form-data" class="space-y-4">
                                @csrf

                                <x-ui.select name="jenis" label="Jenis berkas"
                                             :id="'jenis-berkas-'.$athlete->id"
                                             :options="collect(JenisBerkas::cases())
                                                 ->mapWithKeys(fn (JenisBerkas $j) => [$j->value => $j->label()])
                                                 ->all()" required />

                                <x-ui.file-upload name="berkas" label="Berkas" required
                                                  :id="'berkas-file-'.$athlete->id"
                                                  accept=".jpg,.jpeg,.png,.pdf"
                                                  hint="JPG, PNG, atau PDF. Paling besar 4 MB. Mengunggah jenis yang sama akan menggantikan berkas sebelumnya." />

                                <x-ui.button type="submit" size="sm">Unggah</x-ui.button>
                            </form>

                            <div class="rounded-lg bg-surface-inset p-3 text-xs text-ink-muted">
                                Berkas wajib untuk atlet ini:
                                {{ implode(', ', array_map(fn (JenisBerkas $j) => $j->label(), $athlete->berkasWajib($tournament))) }}.
                            </div>
                        </div>

                        <x-slot:footer>
                            <x-ui.button variant="secondary" type="button"
                                         x-on:click="$dispatch('modal-close', 'berkas-{{ $athlete->id }}')">Tutup</x-ui.button>
                        </x-slot:footer>
                    </x-ui.modal>
                @endresource


            @empty
                <x-ui.empty-state title="Belum ada atlet"
                                  description="Golongan usia dihitung sendiri dari tanggal lahir terhadap tanggal kejuaraan dimulai." />
            @endforelse

            <x-slot:footer>{{ $athletes->links() }}</x-slot:footer>
        </x-ui.card>
    </div>

    @resource(rk('atlet', ResourceAction::Create))
        {{--
            Menambahkan atlet dan mendaftarkan nomornya adalah satu pekerjaan,
            jadi satu formulir. Kelas tandingnya tidak perlu dipilih — jenis
            kelamin, tanggal lahir, dan berat badan di atasnya sudah menentukan
            satu kelas; yang tidak bisa ditentukan sendiri dilaporkan sebagai
            catatan setelah tersimpan.
        --}}
        <x-ui.modal id="atlet-baru" title="Tambah atlet" size="md"
                    :open="$errors->any() && old('_form') === 'atlet-baru'">
            <form method="POST" id="atlet-baru-form"
                  action="{{ route('admin.turnamen.kontingen.atlet.store', [$tournament, $contingent]) }}"
                  class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="atlet-baru">

                @include('admin.atlet.form', ['athlete' => null, 'suffix' => 'baru'])

                @resource(rk('pendaftaran', ResourceAction::Create))
                    <div class="space-y-3 border-t border-line pt-4">
                        <p class="text-[13px] font-semibold text-ink">Sekalian daftarkan nomor</p>

                        <x-ui.toggle name="daftar_tanding" id="daftar-tanding-baru"
                                     label="Daftarkan ke kelas tandingnya"
                                     :checked="filter_var(old('daftar_tanding', '1'), FILTER_VALIDATE_BOOL)"
                                     hint="Kelas dipilih otomatis dari jenis kelamin, golongan usia, dan berat badan di atas." />

                        @if ($nomorJurusPerorangan->isNotEmpty())
                            <x-ui.select name="jurus_event_id" id="jurus-baru" label="Nomor jurus perorangan"
                                         placeholder="Tidak mendaftar nomor jurus"
                                         :selected="old('jurus_event_id')"
                                         :options="$nomorJurusPerorangan->mapWithKeys(fn ($n) => [$n->id => $n->nama()])->all()"
                                         hint="Nomor Ganda dan Regu didaftarkan di halaman Pendaftaran nomor, karena butuh dua sampai tiga pesilat." />
                        @endif
                    </div>
                @endresource
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" type="button"
                             x-on:click="$dispatch('modal-close', 'atlet-baru')">Batal</x-ui.button>
                <x-ui.button type="submit" form="atlet-baru-form">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endresource

    {{--
        Dialog hapus berkas.

        Berkas yang dihapus harus diunggah ulang oleh OFFICIAL KONTINGEN lewat
        akunnya sendiri — panitia tidak bisa menggantikannya. Satu salah tekan
        di sini jadi satu panggilan telepon dan satu pendaftaran yang tertahan
        sampai berkasnya kembali.
    --}}
    @resource(rk('atlet', ResourceAction::Update))
        <div x-data="{ terbuka: false, aksi: '', jenis: '', atlet: '' }"
             x-on:hapus-berkas.window="aksi = $event.detail.aksi; jenis = $event.detail.jenis;
                                       atlet = $event.detail.atlet; terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[440px] rounded-[var(--radius)] border border-danger bg-surface-raised p-5">
                    <p class="text-[20px] leading-tight font-semibold text-ink">
                        Hapus <span x-text="jenis"></span> milik <span x-text="atlet"></span>?
                    </p>

                    <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                        Yang mengunggah ulang adalah official kontingen lewat akunnya sendiri — panitia
                        tidak bisa menggantikannya. Pendaftaran atlet ini tertahan sampai berkasnya kembali.
                    </p>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <x-si.tombol tipe="submit" varian="bahaya-tegas">Hapus berkas</x-si.tombol>
                    </form>
                </div>
            </div>
        </div>
    @endresource

    {{--
        SATU dialog hapus untuk seluruh halaman, bukan satu per baris.

        Susunan lama merender dialog untuk setiap atlet; satu kontingen berisi
        dua puluh lima atlet berarti dua puluh lima dialog tersembunyi di
        halaman yang sama.

        Dialog Ubah dan Berkas MASIH per baris: keduanya memuat formulir dan
        daftar unggahan milik atlet tertentu, dan menyatukannya menuntut isinya
        diambil lewat permintaan terpisah — perubahan arsitektur halaman, bukan
        perapian tampilan. Dicatat supaya tidak terlupakan.
    --}}
    @resource(rk('atlet', ResourceAction::Delete))
        <div x-data="{ terbuka: false, aksi: '', nama: '', berkas: 0 }"
             x-on:hapus-atlet.window="aksi = $event.detail.aksi; nama = $event.detail.nama;
                                      berkas = $event.detail.berkas; terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[460px] rounded-[var(--radius)] border border-danger bg-surface-raised p-5">
                    <p class="text-[11px] tracking-[.1em] text-danger uppercase">Tidak bisa dibatalkan</p>
                    <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">
                        Hapus atlet <span x-text="nama"></span>?
                    </p>

                    <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                        Ikut terhapus: <span class="font-semibold text-ink" x-text="berkas"></span> berkas
                        yang sudah diunggah, beserta seluruh pendaftaran nomornya. Kalau atlet ini sudah
                        masuk bagan, tempatnya jadi kosong dan lawannya menang tanpa bertanding.
                    </p>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <x-si.tombol tipe="submit" varian="bahaya-tegas">Hapus atlet</x-si.tombol>
                    </form>
                </div>
            </div>
        </div>
    @endresource
</x-layouts.admin>
