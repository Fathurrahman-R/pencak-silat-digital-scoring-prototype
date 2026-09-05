<x-layouts.admin heading="Sinkron Gelanggang"
                 description="Pertukaran data antar laptop gelanggang"
                 :breadcrumb="['Sinkron Gelanggang' => null]">

    {{-- Perulangan penarikan tinggal di sini, bukan di server.

         Satu permintaan yang menarik sampai habis akan menahan satu proses
         php-cgi selama seluruh penarikan berlangsung, dan hanya ada delapan
         yang juga melayani tekanan tombol juri. Browser memanggil satu
         potongan pada satu waktu, melepaskan prosesnya di antara potongan,
         dan operator melihat angkanya bertambah alih-alih menunggu layar
         yang diam. --}}
    <div x-data="{
            berjalan: null,
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
                    for (let i = 0; i < 200; i++) {
                        const r = await fetch(@js(route('admin.sinkron.tarik')), {
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

                        if (data.selesai) break;
                    }
                } catch (e) {
                    this.galat[peer] = 'Tidak bisa menghubungi server ini.';
                } finally {
                    this.berjalan = null;
                }
            },
        }"
         class="flex flex-col gap-6">

        <x-si.kartu judul="Mesin ini">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div>
                    <dt class="text-[12px] text-silat-teks-samar">Nama node</dt>
                    <dd class="mt-1 text-[15px] font-medium">{{ $node }}</dd>
                </div>
                <div>
                    <dt class="text-[12px] text-silat-teks-samar">Peran</dt>
                    <dd class="mt-1 text-[15px] font-medium">
                        {{ $peran === 'global' ? 'Node global (data kejuaraan & arsip)' : 'Node gelanggang' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[12px] text-silat-teks-samar">Gelanggang dipegang</dt>
                    <dd class="mt-1 text-[15px] font-medium">{{ $arena !== '' ? $arena : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[12px] text-silat-teks-samar">Perubahan tercatat</dt>
                    <dd class="mt-1 text-[15px] font-medium">{{ number_format($perubahanSendiri) }}</dd>
                </div>
            </dl>
        </x-si.kartu>

        @unless ($tokenTerpasang)
            {{-- Token kosong berarti endpoint sinkron mati sama sekali, bukan
                 terbuka. Dinyatakan di layar supaya panitia tidak menghabiskan
                 waktu menebak kenapa peer tidak bisa menarik apa pun. --}}
            <x-si.callout varian="perhatian" judul="Mesin ini belum bisa ditarik peer">
                SINKRON_TOKEN belum diisi di .env, jadi endpoint sinkron tidak dihidupkan.
                Isi token yang sama di semua laptop, lalu jalankan <code>php artisan config:clear</code>.
            </x-si.callout>
        @endunless

        @forelse ($peer as $satu)
            <x-si.kartu :judul="$satu['nama']" :keterangan="$satu['url']">
                <div class="flex flex-wrap items-center gap-5">
                    <div>
                        <p class="text-[12px] text-silat-teks-samar">Terakhir ditarik</p>
                        <p class="mt-1 text-[14px]">
                            {{ $satu['ditarik_pada'] ? \Illuminate\Support\Carbon::parse($satu['ditarik_pada'])->diffForHumans() : 'Belum pernah' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[12px] text-silat-teks-samar">Kursor</p>
                        <p class="mt-1 text-[14px]">{{ number_format($satu['kursor']) }}</p>
                    </div>

                    <div class="ml-auto flex items-center gap-3">
                        <template x-if="hasil[@js($satu['nama'])]">
                            <p class="text-[13px] text-silat-teks-kedua">
                                <span x-text="hasil[@js($satu['nama'])].diterapkan"></span> diterapkan ·
                                <span x-text="hasil[@js($satu['nama'])].dihapus"></span> dihapus ·
                                <span x-text="hasil[@js($satu['nama'])].ditolak"></span> ditolak
                            </p>
                        </template>

                        <x-si.tombol tipe="button"
                                     x-on:click="tarik(@js($satu['nama']))"
                                     x-bind:disabled="berjalan !== null">
                            <span x-show="berjalan !== @js($satu['nama'])">Tarik dari peer ini</span>
                            <span x-show="berjalan === @js($satu['nama'])" x-cloak>Menarik…</span>
                        </x-si.tombol>
                    </div>
                </div>

                <template x-if="galat[@js($satu['nama'])]">
                    <p class="mt-4 rounded-silat bg-silat-panel px-4 py-2.5 text-[13.5px] text-silat-teks-kedua"
                       x-text="galat[@js($satu['nama'])]"></p>
                </template>

                @if ($satu['galat'])
                    <p class="mt-4 text-[13px] text-silat-teks-redup">
                        Galat terakhir tersimpan: {{ $satu['galat'] }}
                    </p>
                @endif
            </x-si.kartu>
        @empty
            <x-si.kartu>
                <x-si.kosong judul="Belum ada peer terdaftar"
                             syarat="Isi SINKRON_PEER di .env dengan daftar laptop lain, dipisah koma: nama|url|token" />
            </x-si.kartu>
        @endforelse
    </div>
</x-layouts.admin>
