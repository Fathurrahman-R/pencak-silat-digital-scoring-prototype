@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Kategori Jurus"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kategori Jurus' => null,
                 ]">
    <x-si.kartu judul="Nomor Jurus">
        @if ($jurusEvents->isEmpty())
            <x-si.kosong judul="Belum ada nomor Jurus"
                         syarat="Nomor tersusun otomatis dari naskah 2025 saat turnamen dibuat." />
        @else
            <div class="divide-y divide-line">
                @foreach ($jurusEvents as $event)
                    <div class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-[260px] flex-1">
                            <p class="text-sm text-ink">{{ $event->nama() }}</p>
                            <p class="text-xs text-ink-muted">
                                {{ $event->registrations_sah_count }} pendaftaran terverifikasi ·
                                {{ $event->performances_count }} penampilan dibuat
                            </p>
                        </div>

                        {{--
                            Format dipilih di sini, bukan disimpulkan migrasi.

                            Naskah 2025 hanya mengenal sistem gugur (Pasal
                            12.1.b.1), tapi kolomnya berbawaan `penampilan`
                            supaya kejuaraan yang sudah tersusun tidak berubah
                            bentuk di tengah jalan. Panitia yang memutuskan
                            kapan pindah.
                        --}}
                        @resource(rk('nomor-jurus', ResourceAction::Update))
                            <form method="POST" action="{{ route('admin.turnamen.jurus.format', [$tournament, $event]) }}"
                                  class="flex shrink-0 items-center gap-2">
                                @csrf
                                <select name="format" class="rounded-md border border-line bg-surface px-2.5 py-1.5 text-xs"
                                        onchange="this.form.requestSubmit()">
                                    @foreach (App\Enums\FormatJurus::cases() as $pilihan)
                                        <option value="{{ $pilihan->value }}" @selected($event->format === $pilihan)>
                                            {{ $pilihan->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                <noscript><x-si.tombol tipe="submit" varian="kedua" ukuran="kecil">Simpan</x-si.tombol></noscript>
                            </form>
                        @endresource

                        @unless (resource_allows(rk('nomor-jurus', ResourceAction::Update)))
                            <x-si.badge :varian="$event->format->pakaiBagan() ? 'info' : 'netral'">
                                {{ $event->format->label() }}
                            </x-si.badge>
                        @endunless

                        {{-- Sekretariat membuka layar ini untuk formatnya, dan
                             tidak memegang penampilannya: tombol yang pasti
                             dijawab 403 lebih buruk daripada tombol yang tidak
                             ada. --}}
                        @resource(rk('penampilan-jurus', ResourceAction::View))
                            <x-si.tombol :tautan="route('admin.turnamen.jurus.index', [$tournament, $event])" varian="kedua" ukuran="kecil">
                                Kelola penampilan
                            </x-si.tombol>
                        @endresource
                    </div>
                @endforeach
            </div>
        @endif
    </x-si.kartu>
</x-layouts.admin>
