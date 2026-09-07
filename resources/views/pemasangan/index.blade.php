{{--
    Pemasangan node — halaman yang hanya hidup sebelum ada akun.

    Sengaja polos: tidak ada sidebar, tidak ada menu, tidak ada lencana
    kesehatan. Semuanya disusun dari peran orang yang sedang login, dan di
    layar ini belum ada seorang pun yang login. Yang ada cuma satu kalimat
    penjelas, daftar peer dari `.env`, dan satu tombol.

    Halaman ini menghilang sendiri begitu penarikan pertama membawa akun dari
    node global — lihat App\Http\Middleware\PemasanganAwal.
--}}
<x-layouts.guest title="Pemasangan node">
    <div x-data="{
            berjalan: null,
            selesai: false,
            hasil: {},
            galat: {},

            async tarik(peer) {
                if (this.berjalan) return;

                this.berjalan = peer;
                this.galat[peer] = null;
                this.hasil[peer] = { diterapkan: 0, dihapus: 0, ditolak: 0, potongan: 0 };

                try {
                    // Batas putaran, supaya kekeliruan di sisi peer tidak
                    // membuat halaman ini berputar tanpa akhir.
                    for (let i = 0; i < 2000; i++) {
                        const r = await fetch(@js(route('pemasangan.tarik')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({ peer }),
                        });

                        const data = await r.json();

                        if (! r.ok) {
                            this.galat[peer] = data.pesan ?? 'Penarikan gagal.';
                            break;
                        }

                        this.hasil[peer].diterapkan += data.diterapkan;
                        this.hasil[peer].dihapus += data.dihapus;
                        this.hasil[peer].ditolak += data.ditolak;
                        this.hasil[peer].potongan += 1;

                        if (data.selesai) { this.selesai = true; break; }
                    }
                } catch (e) {
                    this.galat[peer] = 'Tidak bisa menghubungi server ini.';
                } finally {
                    this.berjalan = null;
                }
            },
         }"
         class="flex flex-col gap-6">

        <div>
            <h1 class="text-[22px] font-semibold tracking-[-0.02em] text-ink">Pemasangan node</h1>
            <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                Mesin ini belum punya satu pun akun. Seluruh akun panitia lahir di node global
                dan datang lewat sinkron, jadi penarikan pertama dilakukan dari sini — tanpa login.
                Sesudah data masuk, halaman ini menutup dirinya sendiri dan Anda masuk memakai
                akun yang baru saja ditarik.
            </p>
        </div>

        <div class="rounded-[var(--radius-besar)] border border-line bg-surface p-4">
            <p class="text-[11px] font-semibold tracking-[.12em] text-ink-muted uppercase">Mesin ini</p>
            <div class="mt-2 grid grid-cols-[auto_1fr] gap-x-5 gap-y-1 text-[14px]">
                <span class="text-ink-secondary">Nama node</span><span class="text-ink">{{ $node }}</span>
                <span class="text-ink-secondary">Peran</span><span class="text-ink">{{ $peran }}</span>
                <span class="text-ink-secondary">Gelanggang</span><span class="text-ink">{{ $arena !== '' ? $arena : '—' }}</span>
            </div>
        </div>

        @foreach ($peer as $satu)
            <div class="rounded-[var(--radius-besar)] border border-line bg-surface p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[15px] font-medium text-ink">{{ $satu['nama'] }}</p>
                        <p class="silat-angka mt-0.5 text-[12.5px] text-ink-muted">{{ $satu['url'] }}</p>
                    </div>

                    {{-- Atribut Alpine dibangun sebagai ekspresi PHP: @js() di dalam
                         nilai atribut komponen Blade tidak pernah dikompilasi. --}}
                    <x-si.tombol tipe="button"
                                 :x-on:click="'tarik('.\Illuminate\Support\Js::from($satu['nama']).')'"
                                 x-bind:disabled="berjalan !== null">
                        <span x-show="berjalan !== @js($satu['nama'])">Tarik data dari peer ini</span>
                        <span x-show="berjalan === @js($satu['nama'])" x-cloak>Menarik…</span>
                    </x-si.tombol>
                </div>

                <template x-if="hasil[@js($satu['nama'])]">
                    <p class="mt-3 text-[13px] text-ink-secondary">
                        <span x-text="hasil[@js($satu['nama'])].diterapkan"></span> diterapkan ·
                        <span x-text="hasil[@js($satu['nama'])].dihapus"></span> dihapus ·
                        <span x-text="hasil[@js($satu['nama'])].ditolak"></span> ditolak ·
                        <span x-text="hasil[@js($satu['nama'])].potongan"></span> potongan
                    </p>
                </template>

                <template x-if="galat[@js($satu['nama'])]">
                    <p class="mt-3 rounded-[var(--radius)] bg-danger-soft px-3 py-2 text-[13px] text-ink"
                       x-text="galat[@js($satu['nama'])]"></p>
                </template>
            </div>
        @endforeach

        <div x-show="selesai" x-cloak
             class="rounded-[var(--radius-besar)] border border-success-line bg-success-soft p-4">
            <p class="text-[14px] text-ink">
                Penarikan selesai. Masuk memakai akun dari node global —
                <a href="{{ route('login') }}" class="font-semibold underline">buka halaman masuk</a>.
                Halaman pemasangan ini tidak bisa dibuka lagi.
            </p>
        </div>
    </div>
</x-layouts.guest>
