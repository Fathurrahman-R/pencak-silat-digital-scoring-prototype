@php
    $nav = [
        'Fondasi' => [
            '#prinsip' => 'Prinsip',
            '#warna' => 'Warna',
            '#tipografi' => 'Tipografi',
            '#spacing' => 'Spacing & grid',
            '#permukaan' => 'Permukaan & kaca',
            '#material' => 'Material',
            '#motion' => 'Motion',
            '#ikon' => 'Ikon',
        ],
    ];

    $principles = [
        ['icon' => 'align-left', 'title' => 'Data dulu, gaya belakangan', 'body' => 'Kalau sebuah efek visual membuat angka lebih sulit dibaca, efek itu yang pergi.'],
        ['icon' => 'layers', 'title' => 'Kaca itu lapisan, bukan tema', 'body' => 'Blur hanya di sidebar, topbar, hero, dan kartu metrik. Tabel, form, dan teks panjang selalu di permukaan solid.'],
        ['icon' => 'ruler', 'title' => 'Satu keputusan, satu tempat', 'body' => 'Nilai mentah tidak pernah ditulis di komponen. Semua lewat token semantik di app.css.'],
        ['icon' => 'contrast', 'title' => 'AA bukan target, tapi syarat', 'body' => 'Teks minimal 4.5:1, elemen UI 3:1 — di kedua mode, termasuk di atas kaca.'],
    ];

    $surfaces = [
        ['token' => 'surface', 'use' => 'Latar halaman', 'class' => 'bg-surface'],
        ['token' => 'surface-raised', 'use' => 'Card, panel, popover', 'class' => 'bg-surface-raised'],
        ['token' => 'surface-sunken', 'use' => 'Sidebar, header tabel, input', 'class' => 'bg-surface-sunken'],
        ['token' => 'surface-inset', 'use' => 'Hover, track, well', 'class' => 'bg-surface-inset'],
    ];

    $status = [
        ['token' => 'accent', 'class' => 'bg-accent'],
        ['token' => 'success', 'class' => 'bg-success'],
        ['token' => 'warning', 'class' => 'bg-warning'],
        ['token' => 'danger', 'class' => 'bg-danger'],
        ['token' => 'info', 'class' => 'bg-info'],
    ];

    $charts = ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6'];

    $type = [
        ['name' => 'display / 52 / 600', 'sample' => 'Pendapatan', 'class' => 'font-display text-[52px] font-semibold tracking-[-0.03em] leading-[1.1]'],
        ['name' => 'h1 / 30 / 600', 'sample' => 'Ringkasan bulanan', 'class' => 'font-display text-[30px] font-semibold tracking-[-0.02em]'],
        ['name' => 'h2 / 22 / 600', 'sample' => 'Transaksi terakhir', 'class' => 'font-display text-[22px] font-semibold tracking-[-0.02em]'],
        ['name' => 'h3 / 17 / 600', 'sample' => 'Metode pembayaran', 'class' => 'font-display text-[17px] font-semibold'],
        ['name' => 'body / 15 / 400', 'sample' => 'Ukuran default seluruh antarmuka. Panjang baris ideal 60–75 karakter.', 'class' => 'text-[15px] text-ink-secondary'],
        ['name' => 'small / 13 / 400', 'sample' => 'Helper text, isi tabel padat, caption di bawah chart', 'class' => 'text-[13px] text-ink-secondary'],
        ['name' => 'mono / 13 / 400', 'sample' => 'IDR 24.850.000 · INV-2048 · 99,94%', 'class' => 'font-mono text-[13px]'],
    ];

    $spacing = [
        ['name' => '1', 'px' => '4px', 'w' => 'w-1', 'use' => 'Jarak ikon ke teks'],
        ['name' => '2', 'px' => '8px', 'w' => 'w-2', 'use' => 'Antar-kontrol berdekatan'],
        ['name' => '3', 'px' => '12px', 'w' => 'w-3', 'use' => 'Padding dalam chip'],
        ['name' => '4', 'px' => '16px', 'w' => 'w-4', 'use' => 'Jarak antar-kartu'],
        ['name' => '5', 'px' => '20px', 'w' => 'w-5', 'use' => 'Padding kartu'],
        ['name' => '6', 'px' => '24px', 'w' => 'w-6', 'use' => 'Gutter grid docs'],
        ['name' => '9', 'px' => '38px', 'w' => 'w-9', 'use' => 'Tinggi kontrol default'],
        ['name' => '16', 'px' => '64px', 'w' => 'w-16', 'use' => 'Jarak antar-seksi'],
    ];

    $motion = [
        ['name' => 'instant · 120ms', 'use' => 'Hover, fokus, perubahan warna'],
        ['name' => 'base · 200ms', 'use' => 'Dropdown, tab, toggle'],
        ['name' => 'deliberate · 280ms', 'use' => 'Modal, drawer, toast'],
        ['name' => 'stagger · 40ms', 'use' => 'Jeda antar item list, maksimal 6 item'],
    ];

    // Contoh kode wajib lahir di dalam blok @php. Blade mengompilasi tag <x-…>
    // di mana pun ia menemukannya — termasuk di dalam nilai atribut — dan
    // isinya akan ikut dirender, bukan ditampilkan sebagai teks. Blok @php
    // disimpan utuh sebelum tahap itu berjalan.
    $iconCode = <<<'BLADE'
    <x-ui.icon name="trash-2" class="size-4" />
    <x-ui.icon name="trash-2" class="size-5" />
    <x-ui.icon name="trash-2" class="size-6" />
    BLADE;

    $icons = ['house', 'users', 'shield-check', 'key', 'file-text', 'link', 'settings', 'search',
        'plus', 'pencil', 'trash-2', 'download', 'upload', 'check', 'x', 'circle-alert',
        'triangle-alert', 'info', 'chevron-down', 'chevron-right', 'sliders-horizontal', 'inbox',
        'trending-up', 'log-out'];
@endphp

<x-layouts.docs title="Fondasi" :nav="$nav">
    <div class="pb-4">
        <span class="eyebrow text-accent!">Design system</span>
        <h1 class="mt-3.5 max-w-[15ch] font-display text-[40px] leading-[1.05] font-semibold tracking-[-0.03em] text-ink sm:text-[52px]">
            Sistem untuk produk bisnis yang padat informasi.
        </h1>
        <p class="mt-4 max-w-[62ch] text-[17px] text-ink-secondary">
            Fondasi visual untuk admin dashboard, SaaS, dan internal tools. Netral abu kebiruan, satu aksen biru,
            tipografi geometrik, dan kaca yang dipakai seperlunya — bukan sebagai dekorasi.
        </p>

        <div class="mt-6 flex flex-wrap gap-2">
            @foreach (['Terang & gelap setara', 'WCAG AA ketat', 'Token semantik', 'Tanpa varian dark:'] as $chip)
                <span class="rounded-full border border-line bg-surface-raised px-3 py-1.5 font-mono text-xs text-ink-secondary">{{ $chip }}</span>
            @endforeach
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────── Prinsip --}}
    <x-docs.section id="prinsip" number="01" eyebrow="Prinsip">
        <div class="grid gap-px overflow-hidden rounded-lg border border-line bg-line sm:grid-cols-2">
            @foreach ($principles as $principle)
                <div class="bg-surface-raised p-6">
                    <x-ui.icon :name="$principle['icon']" class="size-5 text-accent" />
                    <h4 class="mt-3 font-display text-[17px] font-semibold text-ink">{{ $principle['title'] }}</h4>
                    <p class="mt-2 text-sm text-ink-secondary">{{ $principle['body'] }}</p>
                </div>
            @endforeach
        </div>
    </x-docs.section>

    {{-- ────────────────────────────────────────────────────────── Warna --}}
    <x-docs.section id="warna" number="02" eyebrow="Warna"
                    title="Token semantik, bukan nama warna"
                    lead="Developer tidak perlu tahu #131923. Yang perlu diketahui: panel duduk di surface-raised. Setiap token punya pasangan di kedua mode, dan berganti sendiri saat data-theme berubah.">

        <h4 class="eyebrow mb-3">Permukaan</h4>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($surfaces as $surface)
                <x-docs.swatch :token="$surface['token']" :use="$surface['use']" :class="$surface['class']" />
            @endforeach
        </div>

        <h4 class="eyebrow mt-8 mb-3">Teks &amp; garis</h4>
        <div class="overflow-hidden rounded-md border border-line bg-surface-raised">
            <div class="flex items-center gap-4 border-b border-line px-4 py-3">
                <span class="w-40 shrink-0 font-mono text-[12.5px]">ink</span>
                <span class="flex-1 text-[15px] text-ink">Judul, angka, label utama</span>
            </div>
            <div class="flex items-center gap-4 border-b border-line px-4 py-3">
                <span class="w-40 shrink-0 font-mono text-[12.5px]">ink-secondary</span>
                <span class="flex-1 text-[15px] text-ink-secondary">Paragraf, deskripsi, isi tabel</span>
            </div>
            <div class="flex items-center gap-4 border-b border-line px-4 py-3">
                <span class="w-40 shrink-0 font-mono text-[12.5px]">ink-muted</span>
                <span class="flex-1 text-[15px] text-ink-muted">Caption, timestamp, helper — bukan untuk teks penting</span>
            </div>
            <div class="flex items-center gap-4 border-b border-line px-4 py-3">
                <span class="w-40 shrink-0 font-mono text-[12.5px]">line</span>
                <span class="h-px flex-1 bg-line"></span>
                <span class="font-mono text-xs text-ink-muted">pemisah</span>
            </div>
            <div class="flex items-center gap-4 px-4 py-3">
                <span class="w-40 shrink-0 font-mono text-[12.5px]">line-strong</span>
                <span class="h-px flex-1 bg-line-strong"></span>
                <span class="font-mono text-xs text-ink-muted">input, fokus</span>
            </div>
        </div>

        <h4 class="eyebrow mt-8 mb-3">Aksen &amp; status</h4>
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($status as $item)
                <x-docs.swatch :token="$item['token']" :class="$item['class']" />
            @endforeach
        </div>

        <p class="mt-3 text-[13.5px] text-ink-muted">
            Warna status hanya dipakai untuk menyampaikan arti. Begitu dipakai sebagai hiasan, warnanya berhenti
            berbicara.
        </p>

        <h4 class="eyebrow mt-8 mb-3">Palet chart</h4>
        <div class="flex flex-wrap gap-3">
            @foreach ($charts as $chart)
                <div class="flex items-center gap-2 rounded-full border border-line bg-surface-raised py-1.5 pe-3.5 ps-1.5">
                    <span class="size-6 rounded-full bg-{{ $chart }}"></span>
                    <span class="font-mono text-xs text-ink-secondary">{{ $chart }}</span>
                </div>
            @endforeach
        </div>
    </x-docs.section>

    {{-- ────────────────────────────────────────────────────── Tipografi --}}
    <x-docs.section id="tipografi" number="03" eyebrow="Tipografi"
                    title="Sora untuk suara, Space Grotesk untuk kerja"
                    lead="Sora dipakai di heading dan angka besar — geometrik, sedikit berkarakter. Space Grotesk menangani seluruh antarmuka. IBM Plex Mono untuk nilai teknis dan kode.">

        <div class="overflow-hidden rounded-lg border border-line bg-surface-raised">
            @foreach ($type as $row)
                <div class="flex flex-wrap items-baseline gap-5 px-5 py-4 not-last:border-b not-last:border-line">
                    <span class="w-32 shrink-0 font-mono text-[11.5px] text-ink-muted">{{ $row['name'] }}</span>
                    <span class="{{ $row['class'] }} max-w-[52ch]">{{ $row['sample'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">
                    Angka di tabel dan metrik pakai mono dengan <code class="font-mono text-[13px]">tabular-nums</code>
                    supaya kolomnya lurus. Tersedia sebagai utility <code class="font-mono text-[13px]">num</code>.
                </p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">
                    Sora untuk paragraf panjang — bentuk geometrisnya melelahkan di bawah 16px.
                </p>
            </div>
        </div>
    </x-docs.section>

    {{-- ──────────────────────────────────────────────── Spacing & grid --}}
    <x-docs.section id="spacing" number="04" eyebrow="Spacing &amp; Grid"
                    title="Skala 4px, kepadatan sedang"
                    lead="Tinggi kontrol default 38px. Semua jarak kelipatan 4 — tidak ada 5px, 15px, atau 22px di mana pun.">

        <div class="flex flex-col gap-2.5 rounded-lg border border-line bg-surface-raised px-5 py-5">
            @foreach ($spacing as $step)
                <div class="flex items-center gap-4">
                    <span class="w-16 shrink-0 font-mono text-xs text-ink-muted">{{ $step['name'] }}</span>
                    <span class="w-12 shrink-0 font-mono text-xs">{{ $step['px'] }}</span>
                    <span class="h-3 {{ $step['w'] }} shrink-0 rounded-[3px] border border-accent bg-accent-soft"></span>
                    <span class="text-[12.5px] text-ink-muted">{{ $step['use'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-3">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="eyebrow">Grid</div>
                <div class="mt-1.5 font-display text-2xl font-semibold text-ink">12 kolom</div>
                <p class="mt-1.5 text-[13.5px] text-ink-secondary">Gutter 24px untuk docs; shell aplikasi berpadding 16px tanpa batas lebar.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="eyebrow">Breakpoint</div>
                <div class="mt-1.5 font-display text-2xl font-semibold text-ink">640 · 1024 · 1440</div>
                <p class="mt-1.5 text-[13.5px] text-ink-secondary">Sidebar runtuh jadi drawer di bawah 1024.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="eyebrow">Target sentuh</div>
                <div class="mt-1.5 font-display text-2xl font-semibold text-ink">44 × 44</div>
                <p class="mt-1.5 text-[13.5px] text-ink-secondary">Minimum di mobile, walau visualnya lebih kecil.</p>
            </div>
        </div>
    </x-docs.section>

    {{-- ────────────────────────────────────────────── Permukaan & kaca --}}
    <x-docs.section id="permukaan" number="05" eyebrow="Permukaan, Radius &amp; Kaca"
                    title="Tiga tingkat elevasi, satu lapisan kaca"
                    lead="Radius lembut: 6px untuk elemen kecil, 8–10px untuk kontrol dan card, 14px untuk panel besar dan modal.">

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-md border border-line bg-surface-raised p-5 shadow-sm">
                <div class="font-mono text-xs text-ink-muted">shadow-sm</div>
                <div class="mt-1 font-display font-semibold text-ink">Diam</div>
                <p class="mt-1 text-[13px] text-ink-secondary">Card di dalam halaman</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-5 shadow-md">
                <div class="font-mono text-xs text-ink-muted">shadow-md</div>
                <div class="mt-1 font-display font-semibold text-ink">Mengambang</div>
                <p class="mt-1 text-[13px] text-ink-secondary">Dropdown, popover</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-5 shadow-lg">
                <div class="font-mono text-xs text-ink-muted">shadow-lg</div>
                <div class="mt-1 font-display font-semibold text-ink">Terangkat</div>
                <p class="mt-1 text-[13px] text-ink-secondary">Modal, drawer</p>
            </div>
        </div>

        {{-- Kaca hanya terbaca kalau ada tekstur di belakangnya, jadi contohnya
             pun harus berdiri di atas latar bergaris. --}}
        <div class="bg-shell relative mt-7 overflow-hidden rounded-xl border border-line p-9">
            <div class="bg-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>

            <div class="relative grid gap-4 sm:grid-cols-3">
                <x-ui.stat label="MRR" value="Rp 412jt" delta="+12,4%" trend="up" />
                <x-ui.stat label="Retensi" value="94,2%" delta="Stabil" trend="flat" />
                <x-ui.stat label="Churn" value="1,8%" delta="+0,3%" trend="down" />
            </div>
        </div>

        <p class="mt-2.5 text-[13.5px] text-ink-muted">
            Resep kaca ada di satu utility: <code class="font-mono text-[12.5px]">.glass</code> —
            blur 16px, saturate 160%, tint, border 1px, shadow. Dipakai hanya oleh sidebar dan topbar, yang memang
            berdiri di atas latar bertekstur; di atas warna rata kaca cuma jadi abu-abu, dan isi halaman yang
            ditumpuk di atasnya jadi lebih sulit dibaca.
        </p>

        <p class="mt-1.5 text-[13.5px] text-ink-muted">
            Latar itu sendiri punya utility-nya: <code class="font-mono text-[12.5px]">.bg-shell</code> —
            semburat aksen di pojok kiri atas di atas permukaan rata, dipasang di
            <code class="font-mono text-[12.5px]">&lt;x-layouts.admin&gt;</code>, ditumpuk
            <code class="font-mono text-[12.5px]">.bg-grid</code> 26px yang sama dengan halaman publik.
        </p>
    </x-docs.section>

    {{-- ─────────────────────────────────────────────────────── Material --}}
    <x-docs.section id="material" number="06" eyebrow="Material &amp; Kedalaman"
                    title="Kedalaman menandai fungsi, bukan gaya"
                    lead="Aturannya satu kalimat: yang menonjol bisa ditekan, yang cekung bisa diisi, dan konten selalu datar. Satu sumber cahaya, selalu dari atas. Tidak ada tekstur figuratif — materialnya abstrak.">

        <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="mat-raised rounded-lg border border-line p-5">
                <div class="font-mono text-xs text-ink-muted">mat-raised</div>
                <div class="mt-1.5 font-display text-[15px] font-semibold text-ink">Bisa ditekan</div>
                <p class="mt-1 text-[13px] text-ink-secondary">Tombol, chip, tuas aktif</p>
            </div>
            <div class="mat-well rounded-lg border border-line p-5">
                <div class="font-mono text-xs text-ink-muted">mat-well</div>
                <div class="mt-1.5 font-display text-[15px] font-semibold text-ink">Bisa diisi</div>
                <p class="mt-1 text-[13px] text-ink-secondary">Input, track, segmented</p>
            </div>
            <div class="mat-press rounded-lg border border-line bg-[image:var(--mat-raised)] p-5">
                <div class="font-mono text-xs text-ink-muted">mat-press</div>
                <div class="mt-1.5 font-display text-[15px] font-semibold text-ink">Sedang ditekan</div>
                <p class="mt-1 text-[13px] text-ink-secondary">Hanya selama jari menempel</p>
            </div>
            <div class="glass rounded-lg p-5">
                <div class="font-mono text-xs text-ink-muted">glass</div>
                <div class="mt-1.5 font-display text-[15px] font-semibold text-ink">Mengambang</div>
                <p class="mt-1 text-[13px] text-ink-secondary">Hanya sidebar dan topbar</p>
            </div>
        </div>

        {{-- Panel kontrol: bidang material yang menampung kontrol fisik. Bayangan
             luarnya `lift-lg`, satu tingkat di atas `lift` milik tombol, supaya
             panelnya jelas menaungi isinya dan bukan sebaliknya. --}}
        <div class="mat-panel mt-4 flex flex-wrap items-center gap-5 rounded-xl p-[26px]">
            <x-ui.segmented :options="['minggu' => 'Minggu', 'bulan' => 'Bulan', 'tahun' => 'Tahun']" selected="bulan" />
            <x-ui.stepper name="contoh_lisensi" :value="12" :min="1" class="w-fit" />
            <span class="text-[13px] text-ink-muted">
                <code class="font-mono text-[12.5px]">.mat-panel</code> — tuas duduk di dalam well, yang aktif menonjol keluar.
            </span>
        </div>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">Satu sumber cahaya, selalu dari atas. Semua bevel dan bayangan mengikuti arah yang sama.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">Emboss pada teks, dan kedalaman pada baris tabel — keduanya langsung menggagalkan AA.</p>
            </div>
        </div>
    </x-docs.section>

    {{-- ───────────────────────────────────────────────────────── Motion --}}
    <x-docs.section id="motion" number="07" eyebrow="Motion" title="Cepat, satu arah, bisa dimatikan">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($motion as $item)
                <div class="rounded-md border border-line bg-surface-raised p-4">
                    <div class="font-mono text-xs text-ink-muted">{{ $item['name'] }}</div>
                    <p class="mt-1.5 text-[13.5px] text-ink-secondary">{{ $item['use'] }}</p>
                </div>
            @endforeach
        </div>

        <p class="mt-4 text-[13.5px] text-ink-secondary">
            Easing standar: <code class="font-mono text-[12.5px]">cubic-bezier(.2,.8,.2,1)</code>, tersedia sebagai
            <code class="font-mono text-[12.5px]">ease-rizz</code>. Seluruh animasi mati sendiri saat sistem meminta
            <code class="font-mono text-[12.5px]">prefers-reduced-motion</code>.
        </p>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">
                    Ganti keadaan hover/aktif lewat <code class="font-mono text-[12.5px]">filter</code> (mis.
                    <code class="font-mono text-[12.5px]">hover:brightness-95</code>), <code class="font-mono text-[12.5px]">box-shadow</code>,
                    <code class="font-mono text-[12.5px]">opacity</code>, atau <code class="font-mono text-[12.5px]">transform</code> — semuanya bisa diinterpolasi browser.
                </p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">
                    Menukar <code class="font-mono text-[12.5px]">background-image</code> (gradien material) ke warna polos lewat
                    <code class="font-mono text-[12.5px]">hover:bg-none</code>. Gradien tidak bisa ditransisikan — hasilnya
                    patah sekali ganti, bukan meluncur.
                </p>
            </div>
        </div>
    </x-docs.section>

    {{-- ─────────────────────────────────────────────────────────── Ikon --}}
    <x-docs.section id="ikon" number="08" eyebrow="Ikon"
                    title="Lucide, stroke 1.5, ukuran 16 / 20 / 24"
                    lead="Satu set saja, dirender sebagai SVG inline tanpa JavaScript. Ikon selalu berdampingan dengan teks, kecuali ikon-tombol yang punya tooltip. Warnanya mengikuti teks di sekitarnya, bukan aksen.">

        <div class="grid grid-cols-3 gap-0.5 rounded-lg border border-line bg-surface-raised p-2 sm:grid-cols-6 lg:grid-cols-8">
            @foreach ($icons as $icon)
                <div class="flex flex-col items-center gap-2 rounded-sm px-1.5 py-3.5 transition hover:bg-surface-inset">
                    <x-ui.icon :name="$icon" class="size-5 text-ink-secondary" />
                    <span class="max-w-full truncate font-mono text-[10.5px] text-ink-muted">{{ $icon }}</span>
                </div>
            @endforeach
        </div>

        <x-docs.example class="mt-4" :code="$iconCode">
            <x-ui.icon name="trash-2" class="size-4" />
            <x-ui.icon name="trash-2" class="size-5" />
            <x-ui.icon name="trash-2" class="size-6" />
        </x-docs.example>
    </x-docs.section>
</x-layouts.docs>
