@php use App\Enums\ResourceAction; @endphp

@php($terkunci = ! $tournament->status->bolehUbahTarif())

<x-layouts.admin heading="Tarif pendaftaran"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Tarif' => null,
                 ]">
    <div class="space-y-4">
        @if ($terkunci)
            <x-si.callout varian="perhatian" judul="Tarif terkunci">
                Kejuaraan sudah {{ strtolower($tournament->status->label()) }}. Mengubah tarif sekarang
                berarti dua kontingen membayar harga berbeda untuk nomor yang sama, dan yang membayar
                lebih dulu tidak punya cara mengetahuinya.
            </x-si.callout>
        @else
            <x-si.callout varian="keterangan" judul="Tarif khusus mengalahkan tarif umum">
                Tulis satu tarif umum tanpa kategori dan golongan, lalu tambahkan pengecualian
                seperlunya. Baris yang menyebut golongan usia mengalahkan yang mengosongkannya, dan
                yang menyebut kategori mengalahkan yang tidak.
            </x-si.callout>
        @endif

        <x-si.kartu judul="Biaya per nomor">
            @forelse ($tarifNomor as $tarif)
                <div class="flex items-center gap-4 border-b border-line py-3 last:border-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-base2 text-ink">{{ $tarif->keterangan() }}</p>
                    </div>

                    <p class="silat-angka font-mono text-base2 text-ink">{{ $tarif->rupiah() }}</p>

                    @unless ($terkunci)
                        @resource(rk('tarif', ResourceAction::Update))
                            {{--
                                Sebelumnya tombol ini menghapus tarif SEKETIKA,
                                tanpa satu pun konfirmasi — satu ikon tong sampah
                                16px, dan tagihan seluruh kontingen berubah.

                                Sekarang berkata, dan lewat dialog yang menyebut
                                akibatnya.
                            --}}
                            <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                         data-aksi="{{ route('admin.turnamen.tarif.destroy', [$tournament, $tarif]) }}"
                                         data-nama="{{ $tarif->keterangan() }}"
                                         data-jumlah="{{ $tarif->rupiah() }}"
                                         x-on:click="$dispatch('hapus-tarif', $el.dataset)">
                                Hapus
                            </x-si.tombol>
                        @endresource
                    @endunless
                </div>
            @empty
                <x-si.kosong judul="Belum ada tarif"
                             syarat="Tanpa tarif, tagihan kontingen akan bernilai nol dan tidak bisa dikunci." />
            @endforelse

            @unless ($terkunci)
                @resource(rk('tarif', ResourceAction::Update))
                    <form method="POST" action="{{ route('admin.turnamen.tarif.store', $tournament) }}"
                          class="mt-5 grid items-end gap-3 border-t border-line pt-5 sm:grid-cols-[1fr_1fr_160px_auto]">
                        @csrf

                        <x-si.pilihan name="kategori" label="Kategori"
                                      :options="['' => 'Semua kategori'] + $kategori" />

                        <x-si.pilihan name="golongan_usia" label="Golongan usia"
                                      :options="['' => 'Semua golongan'] + $golongan" />

                        <x-si.isian tipe="number" name="amount" label="Nominal (Rp)" wajib />

                        <x-si.tombol tipe="submit">Simpan</x-si.tombol>
                    </form>
                @endresource
            @endunless
        </x-si.kartu>

        <x-si.kartu judul="Biaya tetap kontingen">
            <p class="mb-4 text-base2 text-ink-muted">
                Ditagih sekali per kontingen, berapa pun jumlah atletnya. Kosongkan dengan nilai 0
                bila tidak dipakai.
            </p>

            @unless ($terkunci)
                @resource(rk('tarif', ResourceAction::Update))
                    <form method="POST" action="{{ route('admin.turnamen.tarif.kontingen', $tournament) }}"
                          class="grid items-end gap-3 sm:grid-cols-[1fr_160px_auto]">
                        @csrf

                        <x-si.isian name="label" label="Keterangan"
                                    :value="$tarifKontingen?->label ?? 'Biaya tetap kontingen'" />

                        <x-si.isian tipe="number" name="amount" label="Nominal (Rp)"
                                    :value="$tarifKontingen?->amount ?? 0" wajib />

                        <x-si.tombol tipe="submit">Simpan</x-si.tombol>
                    </form>
                @endresource
            @else
                <p class="silat-angka font-mono text-base2 text-ink">
                    {{ $tarifKontingen?->rupiah() ?? 'Rp 0' }}
                </p>
            @endunless
        </x-si.kartu>
    </div>

    {{--
        Dialog hapus tarif.

        Menghapus tarif mengubah tagihan SETIAP kontingen yang punya pendaftaran
        di nomor itu — termasuk yang tagihannya sudah dikirim. Angka yang sudah
        dibayar tidak ikut kembali sendiri, dan bendahara baru tahu saat
        jumlahnya tidak cocok.
    --}}
    @resource(rk('tarif', ResourceAction::Update))
        <div x-data="{ terbuka: false, aksi: '', nama: '', jumlah: '' }"
             x-on:hapus-tarif.window="aksi = $event.detail.aksi; nama = $event.detail.nama;
                                      jumlah = $event.detail.jumlah; terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[460px] rounded-[var(--radius)] border border-danger bg-surface-raised p-5">
                    <p class="text-[11px] tracking-[.1em] text-danger uppercase">Mengubah tagihan yang sudah terbit</p>
                    <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">Hapus tarif ini?</p>

                    <div class="mt-3 flex items-baseline justify-between gap-3 rounded-[var(--radius)] border border-line bg-surface-inset px-3.5 py-2.5">
                        <span class="text-[14px] text-ink" x-text="nama"></span>
                        <span class="font-mono text-[15px] font-semibold text-ink tabular-nums" x-text="jumlah"></span>
                    </div>

                    <p class="mt-3 text-[14px] leading-relaxed text-ink-secondary">
                        Tagihan setiap kontingen yang punya pendaftaran di nomor ini ikut berubah, termasuk
                        yang tagihannya sudah dikirim. Angka yang sudah dibayar tidak kembali sendiri.
                    </p>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <x-si.tombol tipe="submit" varian="bahaya-tegas">Hapus tarif</x-si.tombol>
                    </form>
                </div>
            </div>
        </div>
    @endresource
</x-layouts.admin>
