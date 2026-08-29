@props([
    'judul' => 'Rincian',
    'lebar' => 'max-w-[440px]',
])

{{--
    Panel rincian yang isinya diambil saat dibuka.

    Satu komponen ini melayani seluruh tabel di halaman: baris mengirim URL
    fragmennya lewat event, bukan menanam satu panel per baris di DOM. Halaman
    daftar dengan 50 baris berarti 50 panel tersembunyi kalau tidak begitu,
    dan tiap satunya menahan salinan datanya sendiri.

        $dispatch('panel-rincian-buka', '{{ route('admin.users.panel', $user) }}')

    Fragmen yang dikembalikan server adalah HTML biasa tanpa layout.

    Kegagalan DINYATAKAN di dalam panel, bukan diam. Panel kosong yang tidak
    menjelaskan apa-apa membuat panitia menyimpulkan datanya memang tidak ada,
    lalu memasukkannya lagi — padahal yang terjadi hanya sambungan putus.
--}}

<div x-data="{
        terbuka: false,
        memuat: false,
        isi: '',
        galat: '',
        pemicu: null,
        async tampilkan(url) {
            this.pemicu = document.activeElement;
            this.terbuka = true;
            this.memuat = true;
            this.galat = '';
            this.isi = '';
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.panel?.focus());

            try {
                const jawaban = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (! jawaban.ok) {
                    this.galat = jawaban.status === 403
                        ? 'Anda tidak punya akses ke data ini.'
                        : 'Rincian tidak bisa dimuat (' + jawaban.status + ').';
                } else {
                    this.isi = await jawaban.text();
                }
            } catch (e) {
                this.galat = 'Rincian tidak bisa dimuat. Periksa sambungan lalu coba lagi.';
            } finally {
                this.memuat = false;
            }
        },
        tutup() {
            this.terbuka = false;
            document.body.style.overflow = '';
            this.pemicu?.focus();
        },
     }"
     x-on:panel-rincian-buka.window="tampilkan($event.detail)"
     x-on:panel-rincian-tutup.window="tutup()"
     x-on:keydown.escape.window="terbuka && tutup()">

    <template x-teleport="body">
        <div x-show="terbuka" x-cloak class="fixed inset-0 z-[85]"
             role="dialog" aria-modal="true" aria-label="{{ $judul }}">

            <div x-show="terbuka" x-on:click="tutup()" class="absolute inset-0 bg-black/50"></div>

            <div x-ref="panel" tabindex="-1" x-show="terbuka"
                 class="absolute inset-y-0 end-0 flex w-[94vw] {{ $lebar }} flex-col border-s border-line bg-surface-raised outline-none">

                <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                    <h3 class="text-[18px] font-semibold text-ink">{{ $judul }}</h3>

                    <button type="button" x-on:click="tutup()"
                            class="-me-2 grid size-11 shrink-0 place-items-center rounded-[var(--radius-kecil)] text-ink-muted hover:bg-surface-inset hover:text-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                        <span class="sr-only">Tutup</span>
                        <x-si.ikon nama="x" class="size-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-5 text-[14px] leading-relaxed text-ink-secondary">
                    {{-- Kata, bukan balok abu-abu berdenyut. Balok yang berdenyut
                         tidak memberi tahu apakah sesuatu sedang terjadi atau
                         sudah berhenti. --}}
                    <p x-show="memuat" class="text-ink-muted">Memuat rincian…</p>

                    <p x-show="galat" x-cloak class="font-medium text-danger" x-text="galat"></p>

                    <div x-show="! memuat && ! galat" x-html="isi"></div>
                </div>
            </div>
        </div>
    </template>
</div>
