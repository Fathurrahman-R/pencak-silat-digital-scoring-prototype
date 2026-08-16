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
            <x-ui.button type="button" variant="secondary" size="sm"
                         x-on:click="$dispatch('modal-open', 'daftar-jurus')">
                <x-ui.icon name="drama" class="h-4 w-4" />
                Daftarkan nomor jurus
            </x-ui.button>

            <x-ui.button type="button" size="sm" x-on:click="$dispatch('modal-open', 'daftar-tanding')">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Daftarkan kelas tanding
            </x-ui.button>
        @endresource
    </x-slot:actions>

    @include('admin.kontingen.tabs')

    <div class="space-y-4">
        <x-ui.card>
            @forelse ($registrations as $registration)
                <div class="flex flex-wrap items-start gap-4 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[260px] flex-1">
                        <p class="font-medium text-ink">{{ $registration->namaNomor() }}</p>
                        <p class="text-xs text-ink-muted">
                            {{ $registration->athletes->pluck('name')->implode(', ') }}
                        </p>
                    </div>

                    <x-ui.badge :variant="$registration->status->variant()">
                        {{ $registration->status->label() }}
                    </x-ui.badge>

                    <div class="flex gap-1">
                        @if ($registration->status->bolehDisuntingKontingen())
                            @resource(rk('pendaftaran', ResourceAction::Update))
                                <form method="POST"
                                      action="{{ route('admin.turnamen.kontingen.pendaftaran.ajukan', [$tournament, $contingent, $registration]) }}">
                                    @csrf
                                    <x-ui.button type="submit" size="xs" variant="secondary">Ajukan</x-ui.button>
                                </form>
                            @endresource
                        @endif

                        @resource(rk('pendaftaran', ResourceAction::Delete))
                            <x-ui.button type="button" variant="secondary" size="xs" title="Batalkan"
                                         x-on:click="$dispatch('modal-open', 'batal-daftar-{{ $registration->id }}')">
                                <x-ui.icon name="trash-2" class="h-4 w-4 text-danger" />
                            </x-ui.button>

                            <x-ui.modal :id="'batal-daftar-'.$registration->id" title="Batalkan pendaftaran" size="sm">
                                Batalkan pendaftaran <strong>{{ $registration->namaNomor() }}</strong>?

                                <x-slot:footer>
                                    <x-ui.button variant="secondary" type="button"
                                                 x-on:click="$dispatch('modal-close', 'batal-daftar-{{ $registration->id }}')">Tidak</x-ui.button>

                                    <form method="POST"
                                          action="{{ route('admin.turnamen.kontingen.pendaftaran.destroy', [$tournament, $contingent, $registration]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="danger" type="submit">Batalkan</x-ui.button>
                                    </form>
                                </x-slot:footer>
                            </x-ui.modal>
                        @endresource
                    </div>
                </div>
            @empty
                <x-ui.empty-state title="Belum ada pendaftaran nomor"
                                  description="Kelas tanding yang ditawarkan sudah disaring menurut gender, golongan usia, dan berat klaim tiap atlet." />
            @endforelse
        </x-ui.card>
    </div>

    @resource(rk('pendaftaran', ResourceAction::Create))
        {{--
            Kelas disaring di sisi klien dari peta yang sudah dihitung server
            per atlet. Membiarkan official memilih dari 174 kelas lalu ditolak
            validasi adalah cara tercepat membuat orang berhenti memakai
            sistemnya.
        --}}
        <x-ui.modal id="daftar-tanding" title="Daftarkan kelas tanding" size="md"
                    :open="request()->filled('atlet') || ($errors->any() && old('_form') === 'daftar-tanding')">
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

                    <x-ui.select name="athlete_id" id="atlet-tanding" label="Atlet" required
                                 placeholder="Pilih atlet…" x-model="atlet"
                                 :options="$athletes->mapWithKeys(fn ($a) => [
                                     $a->id => $a->name.' — '.$a->jenis_kelamin->label().', '
                                         .($a->golonganUsia($tournament)?->label() ?? 'di luar golongan'),
                                 ])->all()" />

                    <x-ui.select name="weight_class_id" id="kelas-tanding" label="Kelas" required
                                 hint="Hanya kelas yang cocok dengan gender, golongan usia, dan berat klaim atlet terpilih.">
                        <template x-for="k in kelas" :key="k.id">
                            <option :value="k.id" x-text="k.label"></option>
                        </template>
                    </x-ui.select>

                    <p class="text-xs text-warning" x-show="atlet && kelas.length === 0" x-cloak>
                        Tidak ada kelas yang cocok. Golongan usianya mungkin tidak memakai kelas
                        berat, atau berat klaimnya di luar seluruh tangga kelas.
                    </p>
                </form>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" type="button"
                             x-on:click="$dispatch('modal-close', 'daftar-tanding')">Batal</x-ui.button>
                <x-ui.button type="submit" form="daftar-tanding-form">Daftarkan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal id="daftar-jurus" title="Daftarkan nomor jurus" size="md"
                    :open="$errors->any() && old('_form') === 'daftar-jurus'">
            <form method="POST" id="daftar-jurus-form"
                  action="{{ route('admin.turnamen.kontingen.pendaftaran.jurus', [$tournament, $contingent]) }}"
                  class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="daftar-jurus">

                <x-ui.select name="jurus_event_id" id="nomor-jurus" label="Nomor" required
                             placeholder="Pilih nomor…"
                             :options="$nomorJurus->mapWithKeys(fn ($n) => [
                                 $n->id => $n->nama().' ('.$n->jenis->jumlahPesilat().' pesilat)',
                             ])->all()" />

                <x-ui.select name="athlete_ids" id="pesilat-jurus" label="Pesilat" required multiple size="8"
                             hint="Tahan Ctrl untuk memilih lebih dari satu. Ganda diisi dua pesilat, Regu tiga, dan seluruhnya harus dari kontingen yang sama."
                             :options="$athletes->mapWithKeys(fn ($a) => [
                                 $a->id => $a->name.' — '.$a->jenis_kelamin->label().', '
                                     .($a->golonganUsia($tournament)?->label() ?? 'di luar golongan'),
                             ])->all()" />
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" type="button"
                             x-on:click="$dispatch('modal-close', 'daftar-jurus')">Batal</x-ui.button>
                <x-ui.button type="submit" form="daftar-jurus-form">Daftarkan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endresource
</x-layouts.admin>
