@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Kontingen"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kontingen' => null,
                 ]">
    <x-slot:actions>
        @resource(rk('kontingen', ResourceAction::Create))
            <x-si.tombol :tautan="route('admin.turnamen.kontingen.create', $tournament)" ikon="plus" ukuran="kecil">
                Daftarkan kontingen
            </x-si.tombol>
        @endresource
    </x-slot:actions>

    <x-si.tabel :table="$table"
                openable
                :headers="['name' => 'Kontingen', 'region' => 'Daerah', 0 => 'Official', 1 => 'Atlet', 2 => 'Pendaftaran', 3 => '']">
        <x-slot:toolbar>
            <x-si.tabel.toolbar :table="$table" placeholder="Cari kontingen, daerah, kontak…"
                                :tampil="$contingents->count()" :total="$contingents->total()" />
        </x-slot:toolbar>

        @foreach ($contingents as $contingent)
            <x-si.tabel.baris :panel="route('admin.turnamen.kontingen.panel', [$tournament, $contingent])">
                <x-si.tabel.sel header>
                    {{ $contingent->name }}
                    @if ($contingent->contact_name)
                        <span class="block truncate text-xs font-normal text-ink-muted">
                            {{ $contingent->contact_name }}{{ $contingent->contact_phone ? ' · '.$contingent->contact_phone : '' }}
                        </span>
                    @endif
                </x-si.tabel.sel>

                <x-si.tabel.sel>{{ $contingent->region ?? '—' }}</x-si.tabel.sel>

                <x-si.tabel.sel>
                    @if ($contingent->official)
                        {{ $contingent->official->name }}
                    @else
                        <span class="text-ink-muted">Belum ditentukan</span>
                    @endif
                </x-si.tabel.sel>

                {{-- Angka rata kanan bernumeral tabular: kolom jumlah yang tidak
                     lurus menuntut pembacanya membandingkan panjang, bukan nilai. --}}
                <x-si.tabel.sel numeric>{{ $contingent->athletes_count }}</x-si.tabel.sel>
                <x-si.tabel.sel numeric>{{ $contingent->registrations_count }}</x-si.tabel.sel>

                <x-si.tabel.sel align="right">
                    {{--
                        Tombol BERKATA, bukan lima ikon telanjang berjajar.

                        Susunan lama memasang Atlet, Tagihan, Pendaftaran, Ubah, dan
                        Hapus sebagai lima ikon 16px yang hanya berlabel `title` —
                        dan tooltip tidak pernah muncul di layar sentuh. Tong sampah
                        di ujung deret itu menghapus kontingen beserta seluruh atlet
                        dan pendaftarannya.

                        Yang sering dipakai tetap terbuka; sisanya masuk satu menu.
                    --}}
                    <div class="flex justify-end gap-2" data-row-action>
                        @resource(rk('atlet', ResourceAction::View))
                            <a href="{{ route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent]) }}"
                               class="inline-flex h-9 items-center rounded-[var(--radius)] border border-line bg-surface-raised px-3 text-[13px] font-medium text-ink">
                                Atlet
                            </a>
                        @endresource

                        @resource(rk('pendaftaran', ResourceAction::View))
                            <a href="{{ route('admin.turnamen.kontingen.pendaftaran.index', [$tournament, $contingent]) }}"
                               class="inline-flex h-9 items-center rounded-[var(--radius)] border border-line bg-surface-raised px-3 text-[13px] font-medium text-ink">
                                Pendaftaran
                            </a>
                        @endresource

                        <div x-data="{ buka: false }" class="relative">
                            <button type="button" x-on:click="buka = ! buka" x-on:click.outside="buka = false"
                                    class="inline-flex h-9 items-center rounded-[var(--radius)] border border-line bg-surface-raised px-3 text-[13px] font-medium text-ink">
                                Lainnya
                            </button>

                            <div x-show="buka" x-cloak
                                 class="absolute right-0 z-30 mt-1 flex w-[190px] flex-col overflow-hidden rounded-[var(--radius)] border border-line bg-surface-raised shadow-lg">
                                @resource(rk('invoice', ResourceAction::View))
                                    <a href="{{ route('admin.turnamen.kontingen.tagihan.show', [$tournament, $contingent]) }}"
                                       class="border-b border-line px-3 py-2.5 text-left text-[14px] text-ink last:border-0 hover:bg-surface-inset">
                                        Tagihan
                                    </a>
                                @endresource

                                @resource(rk('kontingen', ResourceAction::Update))
                                    <a href="{{ route('admin.turnamen.kontingen.edit', [$tournament, $contingent]) }}"
                                       class="border-b border-line px-3 py-2.5 text-left text-[14px] text-ink last:border-0 hover:bg-surface-inset">
                                        Ubah kontingen
                                    </a>
                                @endresource

                                @resource(rk('kontingen', ResourceAction::Delete))
                                    {{-- Muatan lewat data-*, bukan @js() di dalam ekspresi
                                         atribut: tanda kutip di dalam JSON memutus
                                         pembacaannya. --}}
                                    <button type="button"
                                            data-aksi="{{ route('admin.turnamen.kontingen.destroy', [$tournament, $contingent]) }}"
                                            data-nama="{{ $contingent->name }}"
                                            data-atlet="{{ $contingent->athletes_count }}"
                                            data-daftar="{{ $contingent->registrations_count }}"
                                            x-on:click="buka = false; $dispatch('hapus-kontingen', $el.dataset)"
                                            class="px-3 py-2.5 text-left text-[14px] font-semibold text-danger hover:bg-danger-soft">
                                        Hapus kontingen
                                    </button>
                                @endresource
                            </div>
                        </div>
                    </div>
                </x-si.tabel.sel>
            </x-si.tabel.baris>
        @endforeach

        @if ($contingents->isEmpty())
            <x-slot:kosong>
                <x-si.kosong judul="Belum ada kontingen"
                             syarat="Kontingen masuk setelah official mendaftarkannya lewat akunnya sendiri, atau setelah panitia menambahkannya di sini. Satu tagihan terbit per kontingen." />
            </x-slot:kosong>
        @endif

        <x-slot:footer>{{ $contingents->links() }}</x-slot:footer>
    </x-si.tabel>

    <x-si.panel-rincian judul="Detail kontingen" />

    {{--
        SATU dialog hapus untuk seluruh halaman, bukan satu per baris.
        Susunan lama merender dialog untuk setiap kontingen; satu halaman berisi
        dua puluh lima baris berarti dua puluh lima dialog tersembunyi.

        Menghapus kontingen ikut menghapus seluruh atlet dan pendaftarannya,
        jadi jumlahnya disebutkan — "seluruh atlet dan pendaftarannya" tidak
        memberi tahu berapa banyak yang hilang.
    --}}
    @resource(rk('kontingen', ResourceAction::Delete))
        <div x-data="{ terbuka: false, aksi: '', nama: '', atlet: 0, daftar: 0, ketikan: '' }"
             x-on:hapus-kontingen.window="aksi = $event.detail.aksi; nama = $event.detail.nama;
                                          atlet = $event.detail.atlet; daftar = $event.detail.daftar;
                                          ketikan = ''; terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[480px] rounded-[var(--radius)] border border-danger bg-surface-raised p-5">
                    <p class="text-[11px] tracking-[.1em] text-danger uppercase">Tidak bisa dibatalkan</p>
                    <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">
                        Hapus kontingen <span x-text="nama"></span>?
                    </p>

                    <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                        Ikut terhapus: <span class="font-semibold text-ink" x-text="atlet"></span> atlet dan
                        <span class="font-semibold text-ink" x-text="daftar"></span> pendaftaran, beserta seluruh
                        riwayat timbang badannya. Partai yang sudah berjalan tidak ikut terhapus, tapi sudutnya
                        jadi kosong.
                    </p>

                    {{-- Nama diketik ulang bukan untuk menguji panitia, melainkan
                         karena mengetik memaksa mata membaca APA yang sedang
                         dihapus. --}}
                    <div class="mt-3 flex flex-col gap-1.5">
                        <label for="ketik-nama-kontingen" class="text-[13px] font-semibold text-ink">
                            Ketik <span class="font-mono" x-text="nama"></span> untuk melanjutkan
                        </label>
                        <input id="ketik-nama-kontingen" type="text" x-model="ketikan"
                               placeholder="Ketik nama kontingen…"
                               class="h-11 w-full rounded-[var(--radius)] border border-line-strong bg-surface-raised px-3 text-[15px] text-ink outline-none placeholder:text-ink-muted">
                    </div>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <button type="submit" x-bind:disabled="ketikan !== nama"
                                class="inline-flex h-11 items-center rounded-[var(--radius)] bg-danger px-4 text-[15px] font-semibold text-danger-on disabled:opacity-45">
                            Hapus kontingen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endresource
</x-layouts.admin>
