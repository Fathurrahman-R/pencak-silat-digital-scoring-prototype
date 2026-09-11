@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Gelanggang"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Gelanggang' => null,
                 ]">
    <x-slot:actions>
        @resource(rk('gelanggang', ResourceAction::Create))
            <x-si.tombol tipe="button" ukuran="kecil" x-on:click="$dispatch('modal-open', 'gelanggang-baru')" ikon="plus">
                Tambah gelanggang
            </x-si.tombol>
        @endresource
    </x-slot:actions>

    <div class="space-y-4">
        <x-si.callout varian="keterangan" judul="Kode gelanggang dipakai di alamat siaran">
            Kode inilah yang muncul di alamat halaman siaran langsung dan overlay vMix, jadi
            sebaiknya pendek dan tidak diubah lagi setelah kejuaraan berjalan.
        </x-si.callout>

        <x-si.kartu>
            @forelse ($arenas as $arena)
                <div class="flex items-center gap-4 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-inset font-mono text-sm text-ink">
                        {{ $arena->code }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-ink">{{ $arena->name }}</p>
                        {{-- Operator ditampilkan di sini, bukan disembunyikan di
                             dalam modal: gelanggang tanpa operator tidak bisa
                             dijalankan sama sekali, jadi kekosongannya harus
                             terlihat sebelum hari-H. --}}
                        <p class="truncate text-xs text-ink-muted">
                            Urutan {{ $arena->sort_order }} ·
                            {{-- Pengendali disebut lebih dulu: gelanggang tanpa
                                 operator masih bisa bertanding tanpa papan
                                 tampilan, gelanggang tanpa pengendali tidak
                                 bisa memulai babak sama sekali. --}}
                            @if ($arena->pengendali->isEmpty())
                                <span class="text-danger">belum ada pengendali</span>
                            @else
                                Pengendali: {{ $arena->pengendali->pluck('name')->implode(', ') }}
                            @endif
                            ·
                            @if ($arena->operators->isEmpty())
                                <span class="text-ink-muted">belum ada operator</span>
                            @else
                                Operator: {{ $arena->operators->pluck('name')->implode(', ') }}
                            @endif
                        </p>
                    </div>

                    <x-si.badge :varian="$arena->is_active ? 'sukses' : 'netral'">
                        {{ $arena->is_active ? 'Aktif' : 'Nonaktif' }}
                    </x-si.badge>

                    <div class="flex gap-1">
                        {{--
                            Gerbangnya `penugasan-aparat.assign`, BUKAN
                            `gelanggang.update`.

                            Menempatkan orang di kursi bukan menyunting
                            gelanggangnya, dan pemegang kedua kewenangan itu
                            memang berbeda: Ketua Pertandingan menugaskan
                            aparat, Operator IT yang menyunting gelanggangnya.
                            Menaruh tombol ini di dalam gerbang yang salah
                            membuat satu-satunya orang yang boleh menugaskan
                            aparat tidak pernah melihat tombolnya -- cacat yang
                            sama persis dengan `nomor-jurus.update` milik
                            Sekretariat, dan tertangkap uji yang sama.
                        --}}
                        @resource(rk('penugasan-aparat', ResourceAction::Assign))
                            <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                                         x-on:click="$dispatch('modal-open', 'gelanggang-aparat-{{ $arena->id }}')">
                                Aparat
                            </x-si.tombol>

                            {{--
                                Aparat gelanggang, dan satu-satunya tempat
                                penugasannya.

                                Kursinya yang ditugaskan, bukan partainya:
                                orang yang duduk di kursi Juri 1 Gelanggang A
                                pagi ini duduk di sana sampai sore, dan
                                menugaskannya ulang tiap partai adalah empat
                                baris dikali empat puluh partai. Begitu
                                pengendali menunjuk sebuah partai, isi kursi
                                di sini disalin ke `match_officials` — jadi
                                nomor juri, otorisasi tiap tekanan tombol, dan
                                berita acara tetap tercatat per partai.
                            --}}
                            @php
                                $kursi = $arena->aparat->keyBy(
                                    fn ($satu) => $satu->role.($satu->number === null ? '' : $satu->number),
                                );
                            @endphp

                            <x-si.modal :id="'gelanggang-aparat-'.$arena->id"
                                        :judul="'Aparat '.$arena->name" ukuran="kecil">
                                <form method="POST" action="{{ route('admin.turnamen.gelanggang.aparat', [$tournament, $arena]) }}"
                                      id="aparat-gelanggang-{{ $arena->id }}" class="space-y-3">
                                    @csrf

                                    <p class="pb-1 text-sm text-ink-muted">
                                        Penugasan berlaku sepanjang hari. Kursi yang belum ada orangnya boleh
                                        dikosongkan — isi belakangan tanpa mengubah apa pun yang sudah berjalan.
                                    </p>

                                    <x-si.pilihan name="wasit_id" label="Wasit"
                                                  :options="$calonKetua->pluck('name', 'id')"
                                                  :selected="$kursi->get('wasit')?->user_id"
                                                  placeholder="— kosong —"
                                                  :id="'wasit-'.$arena->id" />

                                    @foreach (range(1, $jumlahJuri) as $nomor)
                                        <x-si.pilihan :name="'juri_id['.($nomor - 1).']'" :label="'Juri '.$nomor"
                                                      :options="$calonJuri->pluck('name', 'id')"
                                                      :selected="$kursi->get('juri'.$nomor)?->user_id"
                                                      placeholder="— kosong —"
                                                      :id="'juri-'.$arena->id.'-'.$nomor" />
                                    @endforeach

                                    <x-si.pilihan name="dewan_id" label="Dewan Wasit Juri"
                                                  :options="$calonKetua->pluck('name', 'id')"
                                                  :selected="$kursi->get('dewan-juri')?->user_id"
                                                  placeholder="— kosong —"
                                                  :id="'dewan-'.$arena->id" />

                                    <x-si.pilihan name="komisi_id" label="Komisi Protes"
                                                  :options="$calonKetua->pluck('name', 'id')"
                                                  :selected="$kursi->get('komisi-protes')?->user_id"
                                                  placeholder="— kosong —"
                                                  :id="'komisi-'.$arena->id" />

                                    <x-si.pilihan name="ketua_id" label="Ketua Pertandingan"
                                                  :options="$calonKetua->pluck('name', 'id')"
                                                  :selected="$kursi->get('ketua-pertandingan')?->user_id"
                                                  placeholder="— kosong —"
                                                  :id="'ketua-'.$arena->id" />

                                    @if ($calonKetua->isEmpty() && $calonJuri->isEmpty())
                                        <x-si.kosong judul="Belum ada akun aparat"
                                                     syarat="Buat penggunanya lebih dulu di menu Sistem, lalu beri peran Ketua Pertandingan atau Juri." />
                                    @endif
                                </form>

                                <x-slot:footer>
                                    <x-si.tombol varian="kedua" tipe="button"
                                                 x-on:click="$dispatch('modal-close', 'gelanggang-aparat-{{ $arena->id }}')">Batal</x-si.tombol>
                                    <x-si.tombol tipe="submit" form="aparat-gelanggang-{{ $arena->id }}">Simpan</x-si.tombol>
                                </x-slot:footer>
                            </x-si.modal>
                        @endresource

                        @resource(rk('gelanggang', ResourceAction::Update))
                            {{-- Kata, bukan pensil telanjang: tooltip tidak pernah
                                 muncul di layar sentuh. --}}
                            <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                                         x-on:click="$dispatch('modal-open', 'gelanggang-ubah-{{ $arena->id }}')">
                                Ubah
                            </x-si.tombol>

                            <x-si.modal :id="'gelanggang-ubah-'.$arena->id" judul="Ubah gelanggang" ukuran="kecil">
                                <form method="POST" action="{{ route('admin.turnamen.gelanggang.update', [$tournament, $arena]) }}"
                                      id="ubah-gelanggang-{{ $arena->id }}" class="space-y-4">
                                    @csrf
                                    @method('PUT')

                                    <x-si.isian name="name" label="Nama gelanggang" :value="$arena->name" wajib
                                                :id="'nama-'.$arena->id" />
                                    <x-si.isian name="code" label="Kode" :value="$arena->code" wajib
                                                :id="'kode-'.$arena->id" />
                                    <x-si.isian tipe="number" name="sort_order" label="Urutan" :value="$arena->sort_order"
                                                :id="'urutan-'.$arena->id" />
                                    <x-si.saklar name="is_active" label="Aktif" :dicentang="$arena->is_active"
                                                 :id="'aktif-'.$arena->id" />
                                </form>

                                <x-slot:footer>
                                    <x-si.tombol varian="kedua" tipe="button"
                                                 x-on:click="$dispatch('modal-close', 'gelanggang-ubah-{{ $arena->id }}')">Batal</x-si.tombol>
                                    <x-si.tombol tipe="submit" form="ubah-gelanggang-{{ $arena->id }}">Simpan</x-si.tombol>
                                </x-slot:footer>
                            </x-si.modal>

                            <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                                         x-on:click="$dispatch('modal-open', 'gelanggang-pengendali-{{ $arena->id }}')">
                                Pengendali
                            </x-si.tombol>

                            <x-si.modal :id="'gelanggang-pengendali-'.$arena->id"
                                        :judul="'Pengendali '.$arena->name" ukuran="kecil">
                                <form method="POST" action="{{ route('admin.turnamen.gelanggang.pengendali', [$tournament, $arena]) }}"
                                      id="pengendali-gelanggang-{{ $arena->id }}" class="space-y-1">
                                    @csrf

                                    <p class="pb-2 text-sm text-ink-muted">
                                        Pengendali memegang timer, perpindahan babak, dan pergantian jadwal
                                        gelanggang ini. Juri, wasit, dan dewan wasit juri mengikuti partai yang
                                        dipilihnya tanpa harus berpindah alamat sendiri.
                                    </p>

                                    @forelse ($calonPengendali as $calon)
                                        <x-si.centang name="pengendali_id[]" :value="$calon->id" :label="$calon->name"
                                                      :dicentang="$arena->pengendali->contains($calon->id)"
                                                      :id="'pengendali-'.$arena->id.'-'.$calon->id" />
                                    @empty
                                        <x-si.kosong judul="Belum ada akun Pengendali Gelanggang"
                                                     syarat="Buat penggunanya lebih dulu di menu Sistem, lalu beri peran Pengendali Gelanggang." />
                                    @endforelse
                                </form>

                                <x-slot:footer>
                                    <x-si.tombol varian="kedua" tipe="button"
                                                 x-on:click="$dispatch('modal-close', 'gelanggang-pengendali-{{ $arena->id }}')">Batal</x-si.tombol>
                                    <x-si.tombol tipe="submit" form="pengendali-gelanggang-{{ $arena->id }}">Simpan</x-si.tombol>
                                </x-slot:footer>
                            </x-si.modal>

                            <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                                         x-on:click="$dispatch('modal-open', 'gelanggang-operator-{{ $arena->id }}')">
                                Operator
                            </x-si.tombol>

                            <x-si.modal :id="'gelanggang-operator-'.$arena->id"
                                        :judul="'Operator '.$arena->name" ukuran="kecil">
                                <form method="POST" action="{{ route('admin.turnamen.gelanggang.operator', [$tournament, $arena]) }}"
                                      id="operator-gelanggang-{{ $arena->id }}" class="space-y-1">
                                    @csrf

                                    <p class="pb-2 text-sm text-ink-muted">
                                        Operator menjalankan papan tampilan gelanggang dan perangkat siarannya.
                                        Timer dan jalannya partai dipegang Pengendali Gelanggang, bukan peran ini.
                                        Penugasan berlaku sepanjang kejuaraan, termasuk untuk partai yang
                                        dijadwalkan kemudian.
                                    </p>

                                    @forelse ($calonOperator as $calon)
                                        <x-si.centang name="operator_id[]" :value="$calon->id" :label="$calon->name"
                                                      :dicentang="$arena->operators->contains($calon->id)"
                                                      :id="'operator-'.$arena->id.'-'.$calon->id" />
                                    @empty
                                        <x-si.kosong judul="Belum ada akun Operator IT"
                                                     syarat="Buat penggunanya lebih dulu di menu Sistem, lalu beri peran Operator IT." />
                                    @endforelse
                                </form>

                                <x-slot:footer>
                                    <x-si.tombol varian="kedua" tipe="button"
                                                 x-on:click="$dispatch('modal-close', 'gelanggang-operator-{{ $arena->id }}')">Batal</x-si.tombol>
                                    <x-si.tombol tipe="submit" form="operator-gelanggang-{{ $arena->id }}">Simpan</x-si.tombol>
                                </x-slot:footer>
                            </x-si.modal>
                        @endresource

                        @resource(rk('gelanggang', ResourceAction::Delete))
                            {{-- Muatan lewat data-*: tanda kutip di dalam JSON
                                 memutus pembacaan ekspresi atribut. --}}
                            <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                         data-aksi="{{ route('admin.turnamen.gelanggang.destroy', [$tournament, $arena]) }}"
                                         data-nama="{{ $arena->name }}"
                                         data-partai="{{ $arena->matches()->count() }}"
                                         x-on:click="$dispatch('hapus-gelanggang', $el.dataset)">
                                Hapus
                            </x-si.tombol>
                        @endresource
                    </div>
                </div>
            @empty
                <x-si.kosong judul="Belum ada gelanggang"
                             syarat="Satu kejuaraan dapat menjalankan beberapa gelanggang sekaligus, masing-masing dengan wasit juri dan papan skornya sendiri." />
            @endforelse
        </x-si.kartu>
    </div>

    @resource(rk('gelanggang', ResourceAction::Create))
        <x-si.modal id="gelanggang-baru" judul="Tambah gelanggang" ukuran="kecil">
            <form method="POST" action="{{ route('admin.turnamen.gelanggang.store', $tournament) }}"
                  id="gelanggang-baru-form" class="space-y-4">
                @csrf

                <x-si.isian name="name" label="Nama gelanggang" wajib
                            bantuan="Mis. Gelanggang 1." />
                <x-si.isian name="code" label="Kode" wajib
                            bantuan="Huruf, angka, dan tanda hubung. Mis. G1." />
                <x-si.saklar name="is_active" label="Aktif" dicentang />
            </form>

            <x-slot:footer>
                <x-si.tombol varian="kedua" tipe="button"
                             x-on:click="$dispatch('modal-close', 'gelanggang-baru')">Batal</x-si.tombol>
                <x-si.tombol tipe="submit" form="gelanggang-baru-form">Simpan</x-si.tombol>
            </x-slot:footer>
        </x-si.modal>
    @endresource

    {{--
        SATU dialog hapus untuk seluruh halaman, bukan satu per gelanggang.

        Menghapus gelanggang bukan tindakan kecil: partai yang dijadwalkan di
        sana kehilangan tempatnya, dan alamat overlay siaran yang sudah dipasang
        di vMix ikut mati. Keduanya disebut, beserta jumlah partainya —
        "yakin menghapus?" tidak memberi tahu apa pun tentang itu.
    --}}
    @resource(rk('gelanggang', ResourceAction::Delete))
        <div x-data="{ terbuka: false, aksi: '', nama: '', partai: 0 }"
             x-on:hapus-gelanggang.window="aksi = $event.detail.aksi; nama = $event.detail.nama;
                                           partai = $event.detail.partai; terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[460px] rounded-[var(--radius)] border border-danger bg-surface-raised p-5">
                    <p class="text-[11px] tracking-[.1em] text-danger uppercase">Tidak bisa dibatalkan</p>
                    <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">
                        Hapus gelanggang <span x-text="nama"></span>?
                    </p>

                    <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                        <span x-show="partai > 0">
                            <span class="font-semibold text-ink" x-text="partai"></span> partai kehilangan
                            tempatnya dan harus dijadwalkan ulang.
                        </span>
                        Alamat overlay siaran gelanggang ini ikut mati — kalau sudah dipasang di vMix,
                        sumbernya berhenti mengirim gambar.
                    </p>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <x-si.tombol tipe="submit" varian="bahaya-tegas">Hapus gelanggang</x-si.tombol>
                    </form>
                </div>
            </div>
        </div>
    @endresource
</x-layouts.admin>
