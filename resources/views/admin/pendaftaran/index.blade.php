@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Pendaftaran nomor"
                 :description="$contingent->name.' · '.$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kontingen' => route('admin.turnamen.kontingen.index', $tournament),
                     $contingent->name => route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent]),
                     'Pendaftaran' => null,
                 ]">
    <x-slot:actions>
        @resource(rk('pendaftaran', ResourceAction::Create))
            <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                         x-on:click="$dispatch('modal-open', 'daftar-jurus')" ikon="drama">
                Daftarkan nomor jurus
            </x-si.tombol>

            <x-si.tombol tipe="button" ukuran="kecil" x-on:click="$dispatch('modal-open', 'daftar-tanding')" ikon="plus">
                Daftarkan kelas tanding
            </x-si.tombol>
        @endresource
    </x-slot:actions>

    @include('admin.kontingen.tabs')

    <div class="space-y-4">
        <x-si.kartu>
            @forelse ($registrations as $registration)
                <div class="flex flex-wrap items-start gap-4 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[260px] flex-1">
                        <p class="font-medium text-ink">{{ $registration->namaNomor() }}</p>
                        <p class="text-xs text-ink-muted">
                            {{ $registration->athletes->pluck('name')->implode(', ') }}
                        </p>
                    </div>

                    <x-si.badge :varian="$registration->status->varian()">
                        {{ $registration->status->label() }}
                    </x-si.badge>

                    <div class="flex gap-1">
                        @if ($registration->status->bolehDisuntingKontingen())
                            @resource(rk('pendaftaran', ResourceAction::Update))
                                <form method="POST"
                                      action="{{ route('admin.turnamen.kontingen.pendaftaran.ajukan', [$tournament, $contingent, $registration]) }}">
                                    @csrf
                                    <x-si.tombol tipe="submit" ukuran="kecil" varian="kedua">Ajukan</x-si.tombol>
                                </form>
                            @endresource
                        @endif

                        @resource(rk('pendaftaran', ResourceAction::Delete))
                            {{-- Kata, bukan tong sampah telanjang. Muatan lewat
                                 data-*: tanda kutip di dalam JSON memutus
                                 pembacaan ekspresi atribut. --}}
                            <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                         data-aksi="{{ route('admin.turnamen.kontingen.pendaftaran.destroy', [$tournament, $contingent, $registration]) }}"
                                         data-nomor="{{ $registration->namaNomor() }}"
                                         data-atlet="{{ $registration->athletes->pluck('name')->implode(', ') }}"
                                         data-status="{{ $registration->status->label() }}"
                                         x-on:click="$dispatch('batal-pendaftaran', $el.dataset)">
                                Batalkan
                            </x-si.tombol>
                        @endresource
                    </div>
                </div>
            @empty
                <x-si.kosong judul="Belum ada pendaftaran nomor"
                             syarat="Daftarkan atlet ke kelas tanding atau nomor jurus lewat tombol di kanan atas. Kelas yang ditawarkan sudah disaring menurut gender, golongan usia, dan berat klaim tiap atlet." />
            @endforelse
        </x-si.kartu>
    </div>

    @resource(rk('pendaftaran', ResourceAction::Create))
        {{--
            Kelas disaring di sisi klien dari peta yang sudah dihitung server
            per atlet. Membiarkan official memilih dari 174 kelas lalu ditolak
            validasi adalah cara tercepat membuat orang berhenti memakai
            sistemnya.
        --}}
        <x-si.modal id="daftar-tanding" judul="Daftarkan kelas tanding" ukuran="sedang"
                    :terbuka="request()->filled('atlet') || ($errors->any() && old('_form') === 'daftar-tanding')">
            <div x-data="{
                    peta: {{ Js::from($kelasPerAtlet) }},
                    atlet: @js((string) old('athlete_id', request('atlet', ''))),
                    get kelas() { return this.peta[this.atlet] ?? [] },
                 }">
                <form method="POST" id="daftar-tanding-form"
                      action="{{ route('admin.turnamen.kontingen.pendaftaran.tanding', [$tournament, $contingent]) }}"
                      class="space-y-4">
                    @csrf
                    {{-- Penanda formulir pengirim, supaya hanya modal yang gagal
                         yang terbuka kembali. --}}
                    <input type="hidden" name="_form" value="daftar-tanding">

                    <x-si.pilihan name="athlete_id" id="atlet-tanding" label="Atlet" wajib
                                  placeholder="Pilih atlet…" x-model="atlet"
                                  :options="$athletes->mapWithKeys(fn ($a) => [
                                  $a->id => $a->name.' — '.$a->jenis_kelamin->label().', '
                                  .($a->golonganUsia($tournament)?->label() ?? 'di luar golongan'),
                                  ])->all()" />

                    <x-si.pilihan name="weight_class_id" id="kelas-tanding" label="Kelas" wajib
                                  bantuan="Hanya kelas yang cocok dengan gender, golongan usia, dan berat klaim atlet terpilih.">
                        <template x-for="k in kelas" :key="k.id">
                            <option :value="k.id" x-text="k.label"></option>
                        </template>
                    </x-si.pilihan>

                    <p class="text-xs text-warning" x-show="atlet && kelas.length === 0" x-cloak>
                        Tidak ada kelas yang cocok. Golongan usianya mungkin tidak memakai kelas
                        berat, atau berat klaimnya di luar seluruh tangga kelas.
                    </p>
                </form>
            </div>

            <x-slot:footer>
                <x-si.tombol varian="kedua" tipe="button"
                             x-on:click="$dispatch('modal-close', 'daftar-tanding')">Batal</x-si.tombol>
                <x-si.tombol tipe="submit" form="daftar-tanding-form">Daftarkan</x-si.tombol>
            </x-slot:footer>
        </x-si.modal>

        <x-si.modal id="daftar-jurus" judul="Daftarkan nomor jurus" ukuran="sedang"
                    :terbuka="$errors->any() && old('_form') === 'daftar-jurus'">
            <form method="POST" id="daftar-jurus-form"
                  action="{{ route('admin.turnamen.kontingen.pendaftaran.jurus', [$tournament, $contingent]) }}"
                  class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="daftar-jurus">

                <x-si.pilihan name="jurus_event_id" id="nomor-jurus" label="Nomor" wajib
                              placeholder="Pilih nomor…"
                              :options="$nomorJurus->mapWithKeys(fn ($n) => [
                              $n->id => $n->nama().' ('.$n->jenis->jumlahPesilat().' pesilat)',
                              ])->all()" />

                <x-si.pilihan name="athlete_ids" id="pesilat-jurus" label="Pesilat" wajib multiple size="8"
                              bantuan="Tahan Ctrl untuk memilih lebih dari satu. Ganda diisi dua pesilat, Regu tiga, dan seluruhnya harus dari kontingen yang sama."
                              :options="$athletes->mapWithKeys(fn ($a) => [
                              $a->id => $a->name.' — '.$a->jenis_kelamin->label().', '
                              .($a->golonganUsia($tournament)?->label() ?? 'di luar golongan'),
                              ])->all()" />
            </form>

            <x-slot:footer>
                <x-si.tombol varian="kedua" tipe="button"
                             x-on:click="$dispatch('modal-close', 'daftar-jurus')">Batal</x-si.tombol>
                <x-si.tombol tipe="submit" form="daftar-jurus-form">Daftarkan</x-si.tombol>
            </x-slot:footer>
        </x-si.modal>
    @endresource

    {{--
        SATU dialog batal untuk seluruh halaman, bukan satu per baris.

        Membatalkan pendaftaran menarik atlet dari nomor itu — bukan menghapus
        atletnya. Bedanya disebutkan, karena "batalkan" tanpa keterangan
        terbaca seperti menghapus orangnya, dan official yang ragu akan
        menelepon panitia alih-alih menekan tombolnya sendiri.
    --}}
    @resource(rk('pendaftaran', ResourceAction::Delete))
        <div x-data="{ terbuka: false, aksi: '', nomor: '', atlet: '', status: '' }"
             x-on:batal-pendaftaran.window="aksi = $event.detail.aksi; nomor = $event.detail.nomor;
                                            atlet = $event.detail.atlet; status = $event.detail.status;
                                            terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[460px] rounded-[var(--radius)] border border-line bg-surface-raised p-5">
                    <p class="text-[20px] leading-tight font-semibold text-ink">
                        Batalkan pendaftaran <span x-text="nomor"></span>?
                    </p>

                    <div class="mt-3 flex items-baseline justify-between gap-3 rounded-[var(--radius)] border border-line bg-surface-inset px-3.5 py-2.5">
                        <span class="text-[14px] text-ink" x-text="atlet"></span>
                        <span class="shrink-0 text-[13px] text-ink-muted" x-text="status"></span>
                    </div>

                    <p class="mt-3 text-[14px] leading-relaxed text-ink-secondary">
                        Atletnya tetap terdaftar di kontingen — yang ditarik hanya pendaftarannya di nomor
                        ini. Bisa didaftarkan lagi selama pendaftaran kejuaraan masih dibuka.
                    </p>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <x-si.tombol tipe="submit" varian="bahaya">Batalkan pendaftaran</x-si.tombol>
                    </form>
                </div>
            </div>
        </div>
    @endresource
</x-layouts.admin>
