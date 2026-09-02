@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Kejuaraan"
                 description="Setiap kejuaraan memegang salinan peraturannya sendiri, beserta kelas tanding dan nomor jurus yang dipertandingkan."
                 :breadcrumb="['Kejuaraan' => null]">
    <x-slot:actions>
        @resource(rk('turnamen', ResourceAction::Export))
            <x-si.tombol :tautan="route('admin.turnamen.export', request()->query())" varian="kedua" ukuran="kecil" ikon="download">
                Ekspor CSV
            </x-si.tombol>
        @endresource

        @resource(rk('turnamen', ResourceAction::Create))
            <x-si.tombol :tautan="route('admin.turnamen.create')" ukuran="kecil" ikon="plus">
                Kejuaraan baru
            </x-si.tombol>
        @endresource
    </x-slot:actions>

    <x-si.tabel :table="$table"
                :selectable="$tournaments->pluck('id')->all()"
                openable
                :headers="['name' => 'Nama', 0 => 'Penyelenggara', 'starts_on' => 'Jadwal', 1 => 'Gelanggang', 'status' => 'Status', 2 => '']">
        <x-slot:toolbar>
            <x-si.tabel.toolbar :table="$table" placeholder="Cari nama, penyelenggara, tempat…">
                <x-slot:chips>
                    <x-si.saring param="status" semua="Semua status" :pilihan="$statuses" />
                </x-slot:chips>

                @resource(rk('turnamen', ResourceAction::Delete))
                    <x-slot:bulk>
                        {{--
                            Sebelumnya tombol ini MENGIRIM LANGSUNG: satu tekan
                            menghapus setiap kejuaraan yang tercentang, beserta
                            seluruh gelanggang, kelas, nomor jurus, pendaftaran,
                            dan hasil pertandingannya — tanpa satu pun
                            konfirmasi.

                            Sekarang lewat dialog yang menyebut berapa yang
                            terpilih.
                        --}}
                        <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                     x-on:click="$dispatch('hapus-borongan', { jumlah: selected.length, ids: selected })">
                            Hapus terpilih
                        </x-si.tombol>
                    </x-slot:bulk>
                @endresource
            </x-si.tabel.toolbar>
        </x-slot:toolbar>

        @foreach ($tournaments as $tournament)
            <x-si.tabel.baris :id="$tournament->id" :panel="route('admin.turnamen.panel', $tournament)">
                <x-si.tabel.sel header>
                    {{ $tournament->name }}

                    {{-- Penanda, bukan tombol: satu-satunya kejuaraan yang
                         sedang dikerjakan, dan barisnya sendiri yang
                         menyebutnya. Tanpa ini tidak ada cara tahu isi sidebar
                         itu milik baris yang mana. --}}
                    @if ($turnamenAktif?->is($tournament))
                        <x-si.badge ikon="check" class="ms-1.5 align-middle">Sedang dibuka</x-si.badge>
                    @endif

                    @if ($tournament->venue)
                        <span class="block truncate text-xs font-normal text-ink-muted">{{ $tournament->venue }}</span>
                    @endif
                </x-si.tabel.sel>

                <x-si.tabel.sel>{{ $tournament->organizer ?? '—' }}</x-si.tabel.sel>

                <x-si.tabel.sel>
                    @if ($tournament->starts_on)
                        {{ $tournament->starts_on->translatedFormat('d M Y') }}
                        @if ($tournament->ends_on && ! $tournament->ends_on->isSameDay($tournament->starts_on))
                            <span class="text-ink-muted">– {{ $tournament->ends_on->translatedFormat('d M Y') }}</span>
                        @endif
                    @else
                        —
                    @endif
                </x-si.tabel.sel>

                <x-si.tabel.sel numeric>{{ $tournament->arenas_count }}</x-si.tabel.sel>

                <x-si.tabel.sel>
                    <x-si.badge :varian="match ($tournament->status) {
                                App\Enums\StatusTurnamen::Berjalan => 'sukses',
                                App\Enums\StatusTurnamen::Selesai => 'netral',
                                default => 'perhatian',
                                }">{{ $tournament->status->label() }}</x-si.badge>
                </x-si.tabel.sel>

                <x-si.tabel.sel align="right">
                    <div class="flex justify-end gap-1" data-row-action>
                        {{-- Satu-satunya cara berpindah kejuaraan, dan ia
                             mengatakannya sendiri.

                             Dulu perpindahan itu efek samping: "Ubah" ikut
                             membuka kejuaraan yang barisnya ditekan, begitu
                             pula panel intip yang muncul saat baris diklik.
                             Menyunting alamat tempat pertandingan mengganti
                             seluruh isi sidebar, dan tidak ada satu kata pun
                             yang memberi tahu. --}}
                        @if (! $turnamenAktif?->is($tournament))
                            <form method="POST" action="{{ route('admin.turnamen.buka', $tournament) }}">
                                @csrf
                                <x-si.tombol tipe="submit" ukuran="kecil">Buka</x-si.tombol>
                            </form>
                        @endif

                        @resource(rk('turnamen', ResourceAction::Update))
                            <x-si.tombol :tautan="route('admin.turnamen.edit', $tournament)"
                                         varian="kedua" ukuran="kecil">
                                Ubah
                            </x-si.tombol>
                        @endresource

                        @resource(rk('turnamen', ResourceAction::Delete))
                            {{-- Kata, bukan tong sampah telanjang. Menghapus
                                 kejuaraan menghapus seluruh isinya. --}}
                            <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                         data-aksi="{{ route('admin.turnamen.destroy', $tournament) }}"
                                         data-nama="{{ $tournament->name }}"
                                         data-gelanggang="{{ $tournament->arenas_count }}"
                                         x-on:click="$dispatch('hapus-turnamen', $el.dataset)">
                                Hapus
                            </x-si.tombol>


                        @endresource
                    </div>
                </x-si.tabel.sel>
            </x-si.tabel.baris>
        @endforeach

        @if ($tournaments->isEmpty())
            <x-slot:kosong>
                <x-si.kosong judul="Belum ada kejuaraan"
                             syarat="Kejuaraan baru langsung dibekali kelas tanding dan nomor jurus sesuai naskah 2025." />
            </x-slot:kosong>
        @endif

        <x-slot:footer>{{ $tournaments->links() }}</x-slot:footer>
    </x-si.tabel>

    <x-si.panel-rincian judul="Detail kejuaraan" />

    @resource(rk('turnamen', ResourceAction::Delete))
        {{--
            Dua dialog, satu untuk seluruh halaman masing-masing: menghapus satu
            kejuaraan, dan menghapus yang tercentang sekaligus.

            Yang borongan sebelumnya TIDAK PUNYA KONFIRMASI SAMA SEKALI — satu
            tekan menghapus setiap kejuaraan yang tercentang beserta seluruh
            gelanggang, kelas, nomor jurus, pendaftaran, dan hasil
            pertandingannya.
        --}}
        <div x-data="{ terbuka: false, aksi: '', nama: '', gelanggang: 0 }"
             x-on:hapus-turnamen.window="aksi = $event.detail.aksi; nama = $event.detail.nama;
                                         gelanggang = $event.detail.gelanggang; terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[460px] rounded-[var(--radius)] border border-danger bg-surface-raised p-5">
                    <p class="text-[11px] tracking-[.1em] text-danger uppercase">Tidak bisa dibatalkan</p>
                    <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">
                        Hapus kejuaraan <span x-text="nama"></span>?
                    </p>

                    <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                        Ikut terhapus: <span class="font-semibold text-ink" x-text="gelanggang"></span> gelanggang,
                        seluruh kelas tanding dan nomor jurusnya, beserta setiap pendaftaran, hasil
                        pertandingan, dan berita acara yang sudah tercatat di dalamnya.
                    </p>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <x-si.tombol tipe="submit" varian="bahaya-tegas">Hapus kejuaraan</x-si.tombol>
                    </form>
                </div>
            </div>
        </div>

        <div x-data="{ terbuka: false, jumlah: 0, ids: [] }"
             x-on:hapus-borongan.window="jumlah = $event.detail.jumlah; ids = $event.detail.ids; terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[460px] rounded-[var(--radius)] border border-danger bg-surface-raised p-5">
                    <p class="text-[11px] tracking-[.1em] text-danger uppercase">Tidak bisa dibatalkan</p>
                    <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">
                        Hapus <span x-text="jumlah"></span> kejuaraan sekaligus?
                    </p>

                    <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                        Setiap kejuaraan yang tercentang terhapus beserta seluruh gelanggang, kelas tanding,
                        nomor jurus, pendaftaran, hasil pertandingan, dan berita acaranya.
                    </p>

                    <form method="POST" action="{{ route('admin.turnamen.bulk-destroy') }}" class="mt-4 flex items-center gap-2">
                        @csrf
                        <template x-for="id in ids" :key="id">
                            <input type="hidden" name="ids[]" :value="id">
                        </template>

                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <x-si.tombol tipe="submit" varian="bahaya-tegas">
                            Hapus <span x-text="jumlah"></span> kejuaraan
                        </x-si.tombol>
                    </form>
                </div>
            </div>
        </div>
    @endresource
</x-layouts.admin>
