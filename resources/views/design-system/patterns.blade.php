@php
    $nav = [
        'Pakai' => [
            '#layout' => 'Pola layout',
            '#voice' => 'Voice & tone',
            '#token' => 'Token untuk developer',
            '#layar' => 'Layar contoh',
        ],
    ];

    $voice = [
        ['good' => 'Faktur belum terkirim. Periksa alamat email penagihan.', 'bad' => 'Oops! Ada yang salah 😕'],
        ['good' => 'Hapus 3 faktur? Tindakan ini tidak bisa dibatalkan.', 'bad' => 'Apakah Anda benar-benar yakin ingin melanjutkan?'],
        ['good' => 'Belum ada data bulan ini.', 'bad' => 'Wah, sepertinya di sini masih kosong nih!'],
    ];

    $screens = config('design-system.screens', []);
@endphp

<x-layouts.docs title="Pola &amp; panduan" :nav="$nav">
    <div class="pb-4">
        <span class="eyebrow text-accent!">Pakai</span>
        <h1 class="mt-3.5 max-w-[18ch] font-display text-[36px] leading-[1.05] font-semibold tracking-[-0.03em] text-ink sm:text-[44px]">
            Cara memakainya di halaman sungguhan.
        </h1>
        <p class="mt-4 max-w-[62ch] text-[17px] text-ink-secondary">
            Token dan komponen baru berarti sesuatu kalau dirangkai dengan cara yang sama setiap kali. Tiga pola layout
            di bawah ini menutup hampir semua halaman yang dibangun dengan boilerplate ini.
        </p>
    </div>

    {{-- ───────────────────────────────────────────────────────── Layout --}}
    <x-docs.section id="layout" number="16" eyebrow="Pola Layout">
        <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <div class="mb-3.5 grid h-24 grid-cols-[26px_1fr] overflow-hidden rounded-sm border border-line">
                    <div class="border-e border-line bg-surface-sunken"></div>
                    <div class="flex flex-col">
                        <div class="h-[18px] border-b border-line bg-surface-sunken"></div>
                        <div class="flex-1 bg-surface"></div>
                    </div>
                </div>
                <h4 class="font-display text-[15px] font-semibold text-ink">Shell aplikasi</h4>
                <p class="mt-1 text-[13.5px] text-ink-secondary">Sidebar kaca + topbar kaca mengambang, berpadding 16px, konten fluid.</p>
            </div>

            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <div class="mb-3.5 grid h-24 grid-cols-3 gap-px overflow-hidden rounded-sm border border-line bg-line">
                    <div class="bg-surface"></div><div class="bg-surface"></div><div class="bg-surface"></div>
                </div>
                <h4 class="font-display text-[15px] font-semibold text-ink">Baris metrik</h4>
                <p class="mt-1 text-[13.5px] text-ink-secondary">Tiga sampai empat kartu kaca di paling atas dashboard.</p>
            </div>

            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <div class="mb-3.5 grid h-24 grid-cols-[1.6fr_1fr] gap-px overflow-hidden rounded-sm border border-line bg-line">
                    <div class="bg-surface"></div><div class="bg-surface-sunken"></div>
                </div>
                <h4 class="font-display text-[15px] font-semibold text-ink">Daftar + panel detail</h4>
                <p class="mt-1 text-[13.5px] text-ink-secondary">Pola utama internal tools. Panel kanan 380–420px.</p>
            </div>

            <div class="rounded-lg border border-line bg-surface-raised p-4">
                <div class="mb-3.5 grid h-24 grid-cols-2 gap-px overflow-hidden rounded-sm border border-line">
                    <div class="bg-surface"></div>
                    <div class="bg-surface-sunken"></div>
                </div>
                <h4 class="font-display text-[15px] font-semibold text-ink">Auth split</h4>
                <p class="mt-1 text-[13.5px] text-ink-secondary">Masuk/Daftar saja. Form solid + panel kaca kepercayaan, hilang di bawah <code class="font-mono text-[12px]">lg</code>.</p>
            </div>
        </div>
    </x-docs.section>

    {{-- ────────────────────────────────────────────────── Voice & tone --}}
    <x-docs.section id="voice" number="17" eyebrow="Voice &amp; Tone" title="Tenang, langsung, tidak menggurui">
        <div class="overflow-hidden rounded-lg border border-line">
            <div class="grid grid-cols-2 gap-px bg-line">
                <div class="bg-surface-sunken px-4 py-3 font-mono text-[11px] tracking-[0.08em] text-success">TULIS BEGINI</div>
                <div class="bg-surface-sunken px-4 py-3 font-mono text-[11px] tracking-[0.08em] text-danger">BUKAN BEGINI</div>

                @foreach ($voice as $pair)
                    <div class="bg-surface-raised px-4 py-4 text-sm text-ink">{{ $pair['good'] }}</div>
                    <div class="bg-surface-raised px-4 py-4 text-sm text-ink-muted">{{ $pair['bad'] }}</div>
                @endforeach
            </div>
        </div>

        <p class="mt-3.5 max-w-[64ch] text-sm text-ink-secondary">
            Bahasa Indonesia formal-ringan. Sapa pengguna dengan kalimat aktif, hindari “Anda” berulang. Angka memakai
            titik sebagai pemisah ribuan, mata uang selalu berawalan Rp.
        </p>
    </x-docs.section>

    {{-- ────────────────────────────────────────── Token untuk developer --}}
    <x-docs.section id="token" number="18" eyebrow="Serah terima" title="Token sebagai konfigurasi Tailwind"
                    lead="Warna didefinisikan sekali sebagai CSS variable, lalu dipetakan ke nama semantik di blok @theme. Ganti tema cukup dengan mengubah data-theme di elemen html — tidak ada kelas dark: yang berserakan.">

        <div class="overflow-hidden rounded-md border border-line">
            <pre class="overflow-x-auto bg-code p-5 font-mono text-[12.5px] leading-relaxed text-code-ink"><code>/* resources/css/app.css */

:root {
    --surface-raised: #FFFFFF;
    --accent: #3D5FE0;
}

:root[data-theme='dark'] {
    --surface-raised: #131923;
    --accent: #4F7CFF;
}

@theme inline {
    --color-surface-raised: var(--surface-raised);
    --color-accent: var(--accent);
}</code></pre>
        </div>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <h4 class="font-display text-[15px] font-semibold text-ink">Utility yang perlu diingat</h4>
                <ul class="mt-2.5 space-y-1.5 text-[13.5px] text-ink-secondary">
                    <li><code class="font-mono text-[12.5px]">glass</code> — khusus dua panel shell: sidebar dan topbar</li>
                    <li><code class="font-mono text-[12.5px]">mat-raised</code> / <code class="font-mono text-[12.5px]">mat-well</code> / <code class="font-mono text-[12.5px]">mat-press</code> — material</li>
                    <li><code class="font-mono text-[12.5px]">bg-grid</code> / <code class="font-mono text-[12.5px]">bg-noise</code> / <code class="font-mono text-[12.5px]">bg-glow</code> — lapisan latar</li>
                    <li><code class="font-mono text-[12.5px]">num</code> — angka mono dengan digit tabular</li>
                    <li><code class="font-mono text-[12.5px]">eyebrow</code> — label mono huruf besar</li>
                    <li><code class="font-mono text-[12.5px]">form-check</code> — kotak centang di markup sendiri</li>
                </ul>
            </div>

            <div class="rounded-md border border-line bg-surface-raised p-4">
                <h4 class="font-display text-[15px] font-semibold text-ink">Mengganti warna aksen</h4>
                <p class="mt-2.5 text-[13.5px] text-ink-secondary">
                    Satu project turunan biasanya cuma perlu mengganti aksen. Ubah <code class="font-mono text-[12.5px]">--accent</code>,
                    <code class="font-mono text-[12.5px]">--accent-hover</code>, <code class="font-mono text-[12.5px]">--accent-soft</code>,
                    dan <code class="font-mono text-[12.5px]">--mat-accent</code> di kedua blok tema — sisanya ikut sendiri.
                </p>
                <p class="mt-2.5 text-[13.5px] text-ink-secondary">
                    <code class="font-mono text-[12.5px]">--accent-on</code> adalah warna teks di atas aksen; pastikan
                    kontrasnya tetap lolos AA setelah diganti.
                </p>
            </div>
        </div>
    </x-docs.section>

    {{-- ────────────────────────────────────────────────── Layar contoh --}}
    <x-docs.section id="layar" number="19" eyebrow="Layar Contoh" title="Lima layar bukti"
                    lead="Dirender penuh dengan komponen yang sama. Tidak menyentuh database dan tidak butuh login, jadi tetap bisa dibuka di project baru yang seedernya belum dijalankan.">

        <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($screens as $key => $meta)
                <a href="{{ route('design-system.screen', $key) }}"
                   class="group rounded-lg border border-line bg-surface-raised p-5 transition hover:border-line-strong">
                    <span class="eyebrow">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h4 class="mt-2 font-display text-[17px] font-semibold text-ink">{{ $meta['title'] }}</h4>
                    <p class="mt-1.5 text-[13.5px] text-ink-secondary">{{ $meta['summary'] }}</p>
                    <span class="mt-3.5 inline-flex items-center gap-1.5 text-[13px] text-link">
                        Buka layar
                        <x-ui.icon name="arrow-right" class="size-3.5 transition-transform group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>
    </x-docs.section>
</x-layouts.docs>
