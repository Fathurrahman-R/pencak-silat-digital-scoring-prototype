@php
    use App\Enums\JenisBerkas;
    use App\Enums\ResourceAction;
    use App\Enums\StatusPendaftaran;
@endphp

<x-layouts.admin heading="Verifikasi pendaftaran"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Verifikasi' => null,
                 ]">
    <div class="space-y-4">
        <x-ui.alert variant="info" title="Dua syarat harus terpenuhi bersamaan">
            Berkas persyaratan peserta lengkap, dan tagihan kontingennya lunas. Keduanya ditegakkan
            saat tombol ditekan — bukan hanya ditampilkan sebagai peringatan.
        </x-ui.alert>

        {{-- Penyaring tinggal di kepala kartu yang disaringnya. --}}
        <x-ui.card title="Pendaftaran">
            <x-slot:actions>
                <x-ui.button :href="route('admin.turnamen.verifikasi.index', [$tournament, 'status' => StatusPendaftaran::Diajukan->value])"
                             :variant="$status === StatusPendaftaran::Diajukan->value ? 'primary' : 'secondary'" size="sm">
                    Menunggu ({{ $jumlahMenunggu }})
                </x-ui.button>

                @foreach ($statuses as $nilai => $label)
                    @continue($nilai === StatusPendaftaran::Diajukan->value)

                    <x-ui.button :href="route('admin.turnamen.verifikasi.index', [$tournament, 'status' => $nilai])"
                                 :variant="$status === $nilai ? 'primary' : 'secondary'" size="sm">
                        {{ $label }}
                    </x-ui.button>
                @endforeach

                <x-ui.button :href="route('admin.turnamen.verifikasi.index', [$tournament, 'status' => 'semua'])"
                             :variant="$status === 'semua' ? 'primary' : 'secondary'" size="sm">
                    Semua
                </x-ui.button>
            </x-slot:actions>

            @forelse ($registrations as $registration)
                @php
                    $invoice = $registration->contingent->invoice;
                    $lunas = $invoice?->lunas() ?? false;

                    $kurang = collect($registration->athletes)
                        ->flatMap(fn ($a) => array_map(
                            fn (JenisBerkas $j) => "{$a->name}: {$j->label()}",
                            $a->berkasKurang($tournament),
                        ))
                        ->all();

                    $siap = $lunas && $kurang === [];
                @endphp

                <div class="border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="flex flex-wrap items-start gap-4">
                        <div class="min-w-[260px] flex-1">
                            <p class="font-medium text-ink">{{ $registration->namaNomor() }}</p>
                            <p class="text-xs text-ink-muted">
                                {{ $registration->contingent->name }} ·
                                {{ $registration->athletes->pluck('name')->implode(', ') }}
                            </p>
                        </div>

                        <x-ui.badge :variant="$registration->status->variant()">
                            {{ $registration->status->label() }}
                        </x-ui.badge>

                        <div class="flex gap-1">
                            @if ($registration->status === StatusPendaftaran::Diajukan)
                                @resource(rk('pendaftaran', ResourceAction::Approve))
                                    <form method="POST"
                                          action="{{ route('admin.turnamen.verifikasi.setujui', [$tournament, $registration]) }}">
                                        @csrf
                                        <x-ui.button type="submit" size="xs" :disabled="! $siap">Sahkan</x-ui.button>
                                    </form>
                                @endresource

                                @resource(rk('pendaftaran', ResourceAction::Reject))
                                    <x-ui.button type="button" variant="secondary" size="xs"
                                                 x-on:click="$dispatch('modal-open', 'tolak-{{ $registration->id }}')">
                                        Tolak
                                    </x-ui.button>
                                @endresource
                            @else
                                @resource(rk('pendaftaran', ResourceAction::Approve))
                                    <form method="POST"
                                          action="{{ route('admin.turnamen.verifikasi.tinjau-ulang', [$tournament, $registration]) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" size="xs">Tinjau ulang</x-ui.button>
                                    </form>
                                @endresource
                            @endif
                        </div>
                    </div>

                    {{--
                        Sebab belum bisa disahkan ditulis di barisnya sendiri.
                        Tombol mati tanpa keterangan membuat panitia menebak,
                        lalu menelepon official yang juga tidak tahu.
                    --}}
                    @if ($registration->status === StatusPendaftaran::Diajukan && ! $siap)
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            @unless ($lunas)
                                <span class="text-warning">
                                    Tagihan {{ $invoice?->status->label() ?? 'belum terbit' }}
                                </span>
                            @endunless

                            @foreach ($kurang as $catatan)
                                <span class="text-warning">Berkas kurang — {{ $catatan }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($registration->status === StatusPendaftaran::Ditolak && $registration->rejection_reason)
                        <p class="mt-2 rounded-md bg-danger-soft px-3 py-2 text-xs text-danger">
                            Alasan: {{ $registration->rejection_reason }}
                        </p>
                    @endif

                    @if ($registration->verified_at && $registration->verifier)
                        <p class="mt-2 text-xs text-ink-muted">
                            Diperiksa {{ $registration->verifier->name }} ·
                            {{ $registration->verified_at->translatedFormat('d M Y, H:i') }}
                        </p>
                    @endif
                </div>

                @if ($registration->status === StatusPendaftaran::Diajukan)
                    @resource(rk('pendaftaran', ResourceAction::Reject))
                        <x-ui.modal :id="'tolak-'.$registration->id" title="Tolak pendaftaran" size="md">
                            <form method="POST" id="tolak-form-{{ $registration->id }}"
                                  action="{{ route('admin.turnamen.verifikasi.tolak', [$tournament, $registration]) }}"
                                  class="space-y-4">
                                @csrf

                                <div class="rounded-lg bg-surface-inset p-3 text-base2">
                                    <p class="text-ink">{{ $registration->namaNomor() }}</p>
                                    <p class="text-ink-muted">{{ $registration->contingent->name }}</p>
                                </div>

                                <x-ui.textarea name="rejection_reason" label="Alasan penolakan" rows="3" required
                                               :id="'alasan-'.$registration->id"
                                               hint="Dibaca official kontingen. Sebutkan apa yang harus diperbaiki, bukan sekadar bahwa berkasnya kurang." />
                            </form>

                            <x-slot:footer>
                                <x-ui.button variant="secondary" type="button"
                                             x-on:click="$dispatch('modal-close', 'tolak-{{ $registration->id }}')">Batal</x-ui.button>
                                <x-ui.button variant="danger" type="submit" form="tolak-form-{{ $registration->id }}">Tolak</x-ui.button>
                            </x-slot:footer>
                        </x-ui.modal>
                    @endresource
                @endif
            @empty
                <x-ui.empty-state title="Tidak ada pendaftaran di tahap ini"
                                  description="Pendaftaran masuk ke antrean setelah official mengajukannya dan berkas wajibnya lengkap." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.admin>
