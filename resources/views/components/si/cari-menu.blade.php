{{--
    Pencarian menu lintas halaman, dibuka dengan ⌘K (Ctrl+K di Windows dan
    Linux) atau lewat tombol Cari di topbar.

    Sumbernya NavigationBuilder yang sama dengan sidebar, jadi daftarnya sudah
    tersaring resource key milik pengguna -- tidak ada jalan pintas ke halaman
    yang tidak boleh dibuka.

    Pintasan papan ketik BUKAN satu-satunya jalan masuk: topbar punya
    tombolnya, dan tombol itu menyebut pintasannya. Antarmuka yang hanya bisa
    dibuka lewat kombinasi tuts tidak akan ditemukan orang yang tidak
    diberitahu.
--}}

@php
    /*
     * `semuaItem()` mengembalikan item yang sudah dilepas dari seksinya, dengan
     * nama seksinya ikut menempel. Menyusun ulang pohonnya di sini akan jadi
     * salinan kedua dari aturan yang sama -- dan salinan kedua itulah yang
     * ketinggalan begitu susunan menunya berubah.
     */
    $perintah = collect(app(App\Support\Navigation\NavigationBuilder::class)->semuaItem())
        ->filter(fn (array $satu): bool => filled($satu['url']))
        ->map(fn (array $satu): array => [
            'label' => $satu['label'],
            'grup' => $satu['seksi'] ?? 'Menu',
            'url' => $satu['url'],
        ])
        ->values();
@endphp

<div x-data="{
        perintah: @js($perintah),
        buka: false,
        kata: '',
        aktif: 0,
        get hasil() {
            const q = this.kata.trim().toLowerCase();
            if (q === '') return this.perintah;
            return this.perintah.filter(p => (p.label + ' ' + p.grup).toLowerCase().includes(q));
        },
        tampilkan() {
            this.buka = true;
            this.kata = '';
            this.aktif = 0;
            this.$nextTick(() => this.$refs.cari?.focus());
        },
        geser(langkah) {
            const jumlah = this.hasil.length;
            if (jumlah === 0) return;
            this.aktif = (this.aktif + langkah + jumlah) % jumlah;
        },
        pergi() {
            const pilihan = this.hasil[this.aktif];
            if (pilihan) window.location.href = pilihan.url;
        },
     }"
     x-on:cari-menu-buka.window="tampilkan()"
     x-on:keydown.window.prevent.cmd.k="tampilkan()"
     x-on:keydown.window.prevent.ctrl.k="tampilkan()"
     x-on:keydown.escape.window="buka = false">

    <template x-teleport="body">
        <div x-show="buka" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center p-6 pt-[12vh]"
             role="dialog" aria-modal="true" aria-label="Cari halaman">

            <div x-show="buka" x-on:click="buka = false" class="absolute inset-0 bg-[rgba(9,9,11,.45)]"></div>

            <div x-show="buka"
                 class="relative w-full max-w-lg overflow-hidden rounded-[var(--radius-dialog)] border border-line bg-surface-raised shadow-lg">

                <div class="flex items-center gap-3 border-b border-line px-4">
                    <x-si.ikon nama="search" class="size-4 shrink-0 text-ink-muted" />

                    <input type="text" x-ref="cari" x-model="kata"
                           aria-label="Cari halaman"
                           x-on:input="aktif = 0"
                           x-on:keydown.arrow-down.prevent="geser(1)"
                           x-on:keydown.arrow-up.prevent="geser(-1)"
                           x-on:keydown.enter.prevent="pergi()"
                           placeholder="Cari halaman…"
                           class="h-12 flex-1 border-0 bg-transparent text-[14px] text-ink outline-none placeholder:text-ink-muted">

                    <x-si.tuts>Esc</x-si.tuts>
                </div>

                <div class="max-h-80 overflow-y-auto p-1.5">
                    <template x-for="(satu, urutan) in hasil" :key="satu.url">
                        <a :href="satu.url"
                           x-on:mouseenter="aktif = urutan"
                           class="flex min-h-11 items-center gap-2.5 rounded-[var(--radius-kecil)] px-2.5 py-2.5 text-[14px]"
                           :class="urutan === aktif ? 'bg-surface-inset text-ink' : 'text-ink-secondary'">
                            <span class="flex-1" x-text="satu.label"></span>
                            <span class="font-mono text-[12px] text-ink-muted" x-text="satu.grup"></span>
                        </a>
                    </template>

                    <p x-show="hasil.length === 0" class="px-2.5 py-3 text-[14px] text-ink-muted">
                        Tidak ada menu yang cocok. Coba kata lain, atau tutup dengan Esc.
                    </p>
                </div>
            </div>
        </div>
    </template>
</div>
