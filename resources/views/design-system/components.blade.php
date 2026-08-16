@php
    $nav = [
        'Komponen' => [
            '#tombol' => 'Tombol',
            '#form' => 'Form',
            '#input-lanjutan' => 'Input lanjutan',
            '#data' => 'Data',
            '#feedback' => 'Feedback',
            '#overlay' => 'Overlay & menu',
            '#konten' => 'Konten & status',
            '#layout' => 'Layout',
            '#grafik' => 'Grafik & progres',
            '#pengungkapan' => 'Pengungkapan & status',
            '#lanjutan' => 'Filter, unggah & jarak jauh',
        ],
    ];

    $rows = [
        ['id' => 'INV-2048', 'client' => 'PT Nusantara Jaya', 'status' => 'Lunas', 'variant' => 'success', 'amount' => 'Rp 24.850.000'],
        ['id' => 'INV-2047', 'client' => 'Sinar Abadi', 'status' => 'Menunggu', 'variant' => 'warning', 'amount' => 'Rp 8.120.000'],
        ['id' => 'INV-2046', 'client' => 'Delta Logistik', 'status' => 'Jatuh tempo', 'variant' => 'danger', 'amount' => 'Rp 3.400.000'],
        ['id' => 'INV-2045', 'client' => 'Aksara Media', 'status' => 'Lunas', 'variant' => 'success', 'amount' => 'Rp 12.700.000'],
    ];

    $clients = [
        'nusantara' => 'PT Nusantara Jaya',
        'sinar' => 'Sinar Abadi',
        'delta' => 'Delta Logistik',
        'aksara' => 'Aksara Media',
        'kopi' => 'Kopi Rakyat',
    ];

    /*
     * Objek ringan untuk mendemokan x-ui.avatar-stack / x-ui.presence-dot
     * tanpa menyentuh database — avatarUrl()-nya SVG inisial yang sama
     * dengan App\Models\User::avatarUrl(), bukan layanan gambar dari luar.
     */
    $avatarTeam = collect(['Maya Wardhani', 'Doni Prasetyo', 'Rani Kartika', 'Bagus Santoso', 'Wulan Sari'])
        ->map(function (string $name) {
            return new class($name)
            {
                public function __construct(public string $name) {}

                public function avatarUrl(): string
                {
                    $initials = collect(explode(' ', $this->name))
                        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
                        ->implode('');

                    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">'
                        .'<rect width="64" height="64" rx="32" fill="#1f2a37"/>'
                        .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="system-ui, sans-serif" font-size="24" fill="#e5e7eb">'.$initials.'</text></svg>';

                    return 'data:image/svg+xml;base64,'.base64_encode($svg);
                }
            };
        });

    /*
     * Contoh kode wajib lahir di dalam blok @php ini.
     *
     * Blade mengompilasi tag <x-…> di mana pun ia menemukannya — termasuk di
     * dalam nilai atribut — sehingga contoh yang ditulis langsung di markup
     * akan ikut dirender, bukan ditampilkan sebagai teks. Blok @php disimpan
     * utuh sebelum tahap kompilasi itu berjalan, jadi aman.
     */
    $code = [];

    $code['button'] = <<<'BLADE'
    <x-ui.button>Simpan perubahan</x-ui.button>
    <x-ui.button variant="secondary">Sekunder</x-ui.button>
    <x-ui.button variant="ghost">Ghost</x-ui.button>
    <x-ui.button variant="danger">
        <x-ui.icon name="trash-2" class="size-4" />
        Hapus
    </x-ui.button>
    <x-ui.button disabled>Nonaktif</x-ui.button>
    BLADE;

    $code['button-size'] = <<<'BLADE'
    <x-ui.button size="sm">sm · 32</x-ui.button>
    <x-ui.button size="md">md · 40</x-ui.button>
    <x-ui.button size="lg">lg · 48</x-ui.button>

    <x-ui.button size="icon" variant="secondary" title="Pengaturan">
        <x-ui.icon name="settings" />
    </x-ui.button>
    <x-ui.button size="icon-round" variant="secondary" title="Aksi lain">
        <x-ui.icon name="more-horizontal" />
    </x-ui.button>
    BLADE;

    $code['form'] = <<<'BLADE'
    <x-ui.input name="company" label="Nama perusahaan" hint="Muncul di faktur dan email." />
    <x-ui.input name="amount" label="Nilai kontrak" prefix="Rp" />
    <x-ui.select name="plan" label="Paket" :options="['starter' => 'Starter']" />
    <x-ui.datepicker name="starts_at" label="Mulai berlaku" />
    <x-ui.textarea name="notes" label="Catatan internal" rows="3" />
    BLADE;

    $code['choice'] = <<<'BLADE'
    <x-ui.checkbox name="auto" label="Kirim faktur otomatis" checked />
    <x-ui.radio name="cycle" value="monthly" label="Bulanan" checked />
    <x-ui.toggle name="sandbox" label="Mode sandbox" />
    BLADE;

    $code['upload'] = <<<'BLADE'
    <x-ui.file-upload name="logo" label="Logo perusahaan" accept="image/*"
                      hint="PNG atau SVG, maksimal 2 MB." />
    BLADE;

    $code['advanced'] = <<<'BLADE'
    <x-ui.combobox name="client" label="Klien" :options="$clients" />
    <x-ui.tag-input name="recipients" label="Penerima" :value="['finance@contoh.id']" />
    <x-ui.stepper name="seats" label="Jumlah lisensi" :value="12" :min="1" :max="99" />
    <x-ui.segmented :options="['minggu' => 'Minggu', 'bulan' => 'Bulan']" selected="bulan" />
    BLADE;

    $code['overlay'] = <<<'BLADE'
    <x-ui.button type="button" x-on:click="$dispatch('modal-open', 'hapus-faktur')">
        Buka modal
    </x-ui.button>

    <x-ui.modal id="hapus-faktur" title="Hapus 3 faktur?" size="sm">
        <p>Faktur yang sudah terkirim akan ditarik. Tindakan ini tidak bisa dibatalkan.</p>

        <x-slot:footer>
            <x-ui.button type="button" variant="secondary"
                         x-on:click="$dispatch('modal-close', 'hapus-faktur')">Batal</x-ui.button>
            <x-ui.button type="button" variant="danger">Hapus faktur</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
    BLADE;

    $code['badge'] = <<<'BLADE'
    <x-ui.badge>Netral</x-ui.badge>
    <x-ui.badge variant="success" pill dot>Lunas</x-ui.badge>
    <x-ui.badge variant="danger" pill dot>Jatuh tempo</x-ui.badge>
    BLADE;

    $code['stat'] = <<<'BLADE'
    <x-ui.stat label="MRR" value="Rp 412jt" delta="+12,4%" trend="up" />
    <x-ui.stat label="Churn" value="1,8%" delta="+0,3%" trend="down" />
    BLADE;

    $code['misc'] = <<<'BLADE'
    <x-ui.spinner size="md" />
    <x-ui.avatar :user="auth()->user()" size="md" />
    <x-ui.breadcrumb :items="['Penagihan' => route('billing.index'), 'Faktur' => null]" />
    BLADE;

    $code['tabs'] = <<<'BLADE'
    <x-ui.tabs :tabs="['semua' => 'Semua', 'lunas' => 'Lunas']">
        <x-slot:semua>…</x-slot:semua>
        <x-slot:lunas>…</x-slot:lunas>
    </x-ui.tabs>
    BLADE;

    $code['layout-guest-split'] = <<<'BLADE'
    <x-layouts.guest-split heading="Masuk ke workspace"
                           description="Pakai akun kerja Anda untuk melanjutkan.">
        <x-slot:aside>
            <x-auth.trust-panel quote="…" name="Maya Wardhani"
                                role="Finance Lead" initials="MW"
                                :stats="[['value' => '2.400+', 'label' => 'tim keuangan']]" />
        </x-slot:aside>

        <x-auth.errors />

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <x-ui.input name="email" type="email" label="Email kerja" required />
            <x-ui.input name="password" type="password" label="Kata sandi" required />
            <x-ui.button type="submit" block>Masuk</x-ui.button>
        </form>
    </x-layouts.guest-split>
    BLADE;

    $code['trust-panel'] = <<<'BLADE'
    <x-auth.trust-panel
        quote="Penutupan buku yang dulu tiga hari sekarang selesai sebelum makan siang."
        name="Maya Wardhani" role="Finance Lead · Nusantara Logistik" initials="MW"
        :stats="[
            ['value' => '2.400+', 'label' => 'tim keuangan'],
            ['value' => 'Rp 4,1T', 'label' => 'tagihan diproses'],
        ]" />
    BLADE;

    $code['progress'] = <<<'BLADE'
    <x-ui.progress label="Kuota penyimpanan" value="72" caption="72%" />
    <x-ui.progress value="40" tone="chart-2" height="4" />
    BLADE;

    $code['ratio-bar'] = <<<'BLADE'
    <x-ui.ratio-bar :segments="[
        ['label' => 'Lunas', 'value' => 62, 'tone' => 'chart-1'],
        ['label' => 'Menunggu', 'value' => 24, 'tone' => 'chart-2'],
        ['label' => 'Jatuh tempo', 'value' => 14, 'tone' => 'chart-3'],
    ]" />
    <x-ui.legend :items="[
        ['label' => 'Lunas', 'tone' => 'chart-1', 'value' => 'Rp 210jt'],
        ['label' => 'Menunggu', 'tone' => 'chart-2', 'value' => 'Rp 81jt'],
        ['label' => 'Jatuh tempo', 'tone' => 'chart-3', 'value' => 'Rp 47jt'],
    ]" />
    BLADE;

    $code['bar-chart'] = <<<'BLADE'
    <x-ui.bar-chart :series="[
        ['label' => 'Jan', 'value' => 42], ['label' => 'Feb', 'value' => 58],
        ['label' => 'Mar', 'value' => 37], ['label' => 'Apr', 'value' => 64],
        ['label' => 'Mei', 'value' => 51],
    ]" height="140" />
    BLADE;

    $code['slider'] = <<<'BLADE'
    <x-ui.slider name="ds_threshold" label="Ambang notifikasi" :value="65" />
    BLADE;

    $code['accordion'] = <<<'BLADE'
    <x-ui.accordion>
        <x-ui.accordion-item title="Kenapa faktur saya belum terkirim?">
            Periksa alamat email penagihan di halaman klien — email yang salah
            menahan pengiriman otomatis.
        </x-ui.accordion-item>
        <x-ui.accordion-item title="Bagaimana cara mengubah siklus tagihan?">
            Buka Pengaturan → Penagihan, lalu pilih siklus baru. Perubahan
            berlaku mulai periode berikutnya.
        </x-ui.accordion-item>
    </x-ui.accordion>
    BLADE;

    $code['timeline-wizard'] = <<<'BLADE'
    <x-ui.timeline :items="[
        ['text' => 'INV-2048 dibayar Rp 24.850.000', 'time' => '2 jam lalu'],
        ['text' => 'INV-2048 dikirim ke Sinar Abadi', 'time' => 'Kemarin, 14.20'],
        ['text' => 'Faktur dibuat', 'time' => '3 hari lalu'],
    ]" />

    <x-ui.wizard :steps="['Data akun', 'Hak akses', 'Konfirmasi']" :current="2" />
    BLADE;

    $code['kbd-code'] = <<<'BLADE'
    <x-ui.kbd>⌘K</x-ui.kbd>
    <x-ui.kbd variant="well">Esc</x-ui.kbd>

    <x-ui.code-block language="php">
    Route::get('/faktur/{invoice}', ShowInvoice::class);
    </x-ui.code-block>
    BLADE;

    $code['avatar-stack'] = <<<'BLADE'
    <x-ui.avatar-stack :users="$team" :limit="4" />

    <div class="relative inline-flex">
        <x-ui.avatar :user="$team->first()" size="md" />
        <x-ui.presence-dot status="online" />
    </div>
    BLADE;

    $code['dropzone'] = <<<'BLADE'
    <x-ui.dropzone name="ds_dropzone" label="Unggah lampiran"
                   hint="PDF atau gambar, maksimal 10 MB." />
    BLADE;

    $code['filter-chips'] = <<<'BLADE'
    <x-ui.filter-chips param="status"
        :options="['lunas' => 'Lunas', 'menunggu' => 'Menunggu', 'jatuh-tempo' => 'Jatuh tempo']" />
    BLADE;

    $code['notification-menu'] = <<<'BLADE'
    <x-ui.notification-menu url="#" :items="[
        ['icon' => 'receipt', 'tone' => 'success', 'text' => 'INV-2048 dibayar', 'time' => '2 jam lalu'],
        ['icon' => 'triangle-alert', 'tone' => 'warning', 'text' => 'Kartu akan kedaluwarsa', 'time' => 'Kemarin'],
    ]" />
    BLADE;

    $code['drawer-remote'] = <<<'BLADE'
    <x-ui.button type="button" variant="secondary"
                 x-on:click="$dispatch('drawer-remote-open', '/tidak-ada')">
        Buka panel jarak jauh
    </x-ui.button>

    <x-ui.drawer-remote title="Detail entitas" />
    BLADE;
@endphp

<x-layouts.docs title="Komponen" :nav="$nav">
    <div class="pb-4">
        <span class="eyebrow text-accent!">Komponen</span>
        <h1 class="mt-3.5 max-w-[18ch] font-display text-[36px] leading-[1.05] font-semibold tracking-[-0.03em] text-ink sm:text-[44px]">
            Semuanya dirender dari komponen yang sesungguhnya.
        </h1>
        <p class="mt-4 max-w-[62ch] text-[17px] text-ink-secondary">
            Tidak ada tangkapan layar dan tidak ada salinan markup. Setiap contoh di halaman ini memanggil berkas yang
            sama di <code class="font-mono text-[15px]">resources/views/components/ui/</code> dengan yang dipakai
            aplikasi — jadi kalau komponennya berubah, halaman ini ikut berubah.
        </p>
    </div>

    {{-- ───────────────────────────────────────────────────────── Tombol --}}
    <x-docs.section id="tombol" number="09" eyebrow="Tombol" title="Satu aksi utama per layar">
        <x-docs.example sunken :code="$code['button']">
            <x-ui.button type="button">Simpan perubahan</x-ui.button>
            <x-ui.button type="button" variant="secondary">Sekunder</x-ui.button>
            <x-ui.button type="button" variant="ghost">Ghost</x-ui.button>
            <x-ui.button type="button" variant="danger">
                <x-ui.icon name="trash-2" class="size-4" />
                Hapus
            </x-ui.button>
            <x-ui.button type="button" disabled>Nonaktif</x-ui.button>
        </x-docs.example>

        <x-docs.example title="Ukuran" class="mt-4" sunken :code="$code['button-size']">
            <x-ui.button type="button" size="sm">sm · 32</x-ui.button>
            <x-ui.button type="button" size="md">md · 40</x-ui.button>
            <x-ui.button type="button" size="lg">lg · 48</x-ui.button>

            <x-ui.button type="button" size="icon" variant="secondary" title="Pengaturan">
                <x-ui.icon name="settings" />
            </x-ui.button>
            <x-ui.button type="button" size="icon-round" variant="secondary" title="Aksi lain">
                <x-ui.icon name="more-horizontal" />
            </x-ui.button>
        </x-docs.example>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">Label berupa kata kerja spesifik: “Simpan perubahan”, “Kirim undangan”.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">Dua tombol aksen bersebelahan — pembaca jadi tidak tahu mana yang utama.</p>
            </div>
        </div>
    </x-docs.section>

    {{-- ─────────────────────────────────────────────────────────── Form --}}
    <x-docs.section id="form" number="10" eyebrow="Form" title="Label di atas, error yang menjelaskan"
                    lead="Komponen form membaca $errors sendiri — cukup sebut name, dan pesan validasinya muncul tanpa kode tambahan.">

        <x-docs.example :code="$code['form']">
            <div class="grid w-full gap-5 sm:grid-cols-2">
                <x-ui.input name="ds_company" label="Nama perusahaan" placeholder="PT Contoh Sejahtera"
                            hint="Muncul di faktur dan email." />

                <x-ui.select name="ds_plan" label="Paket"
                             :options="['starter' => 'Starter', 'growth' => 'Growth', 'enterprise' => 'Enterprise']" />

                <x-ui.datepicker name="ds_starts_at" label="Mulai berlaku" />

                <x-ui.input name="ds_amount" label="Nilai kontrak" prefix="Rp" placeholder="0" />

                <div class="sm:col-span-2">
                    <x-ui.textarea name="ds_notes" label="Catatan internal" rows="3" placeholder="Opsional" />
                </div>
            </div>
        </x-docs.example>

        <x-docs.example title="Pilihan" class="mt-4" :code="$code['choice']">
            <div class="flex flex-wrap items-center gap-8">
                <x-ui.checkbox name="ds_auto" label="Kirim faktur otomatis" checked />

                <div class="flex gap-5">
                    <x-ui.radio name="ds_cycle" value="monthly" label="Bulanan" checked />
                    <x-ui.radio name="ds_cycle" value="yearly" label="Tahunan" />
                </div>

                <x-ui.toggle name="ds_sandbox" label="Mode sandbox" />
            </div>
        </x-docs.example>

        <x-docs.example title="Unggah berkas" class="mt-4" :code="$code['upload']">
            <div class="w-full max-w-md">
                <x-ui.file-upload name="ds_logo" label="Logo perusahaan" accept="image/*" hint="PNG atau SVG, maksimal 2 MB." />
            </div>
        </x-docs.example>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">Error menyebutkan cara memperbaikinya, bukan sekadar “tidak valid”.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">Placeholder sebagai pengganti label — hilang begitu orang mulai mengetik.</p>
            </div>
        </div>
    </x-docs.section>

    {{-- ──────────────────────────────────────────────── Input lanjutan --}}
    <x-docs.section id="input-lanjutan" number="11" eyebrow="Input Lanjutan" title="Untuk data yang tidak muat di satu baris">
        <x-docs.example :code="$code['advanced']">
            <div class="grid w-full gap-6 sm:grid-cols-2">
                <x-ui.combobox name="ds_client" label="Combobox — cari klien" :options="$clients"
                               hint="Menyaring saat mengetik. Enter memilih hasil teratas." />

                <x-ui.tag-input name="ds_recipients" label="Tag input — penerima"
                                :value="['finance@nusantara.co.id', 'maya@contoh.id']"
                                hint="Enter atau koma menambahkan. Backspace di kolom kosong menghapus tag terakhir." />

                <x-ui.stepper name="ds_seats" label="Stepper — jumlah lisensi" :value="12" :min="1" :max="99" />

                <div>
                    <span class="mb-1.5 block text-[13px] font-semibold text-ink">Segmented — periode</span>
                    <x-ui.segmented :options="['minggu' => 'Minggu', 'bulan' => 'Bulan', 'tahun' => 'Tahun']" selected="bulan" />
                </div>
            </div>
        </x-docs.example>
    </x-docs.section>

    {{-- ─────────────────────────────────────────────────────────── Data --}}
    <x-docs.section id="data" number="12" eyebrow="Tampilan Data" title="Tabel selalu di permukaan solid"
                    lead="Kolom angka rata kanan, memakai mono dan tabular-nums, supaya digitnya lurus antar-baris.">

        <x-ui.table :headers="['Faktur', 'Klien', 'Status', 'Nilai']">
            @foreach ($rows as $row)
                <x-ui.table.row>
                    <x-ui.table.cell class="num text-[12.5px]">{{ $row['id'] }}</x-ui.table.cell>
                    <x-ui.table.cell>{{ $row['client'] }}</x-ui.table.cell>
                    <x-ui.table.cell>
                        <x-ui.badge :variant="$row['variant']" pill dot>{{ $row['status'] }}</x-ui.badge>
                    </x-ui.table.cell>
                    <x-ui.table.cell numeric>{{ $row['amount'] }}</x-ui.table.cell>
                </x-ui.table.row>
            @endforeach
        </x-ui.table>

        <h4 class="eyebrow mt-6 mb-3">Bisa dipilih, aksi per baris</h4>
        <p class="mb-3 max-w-[64ch] text-[13.5px] text-ink-secondary">
            Kolom centang muncul lewat prop <code class="font-mono text-[12.5px]">selectable</code> di
            <code class="font-mono text-[12.5px]">x-ui.table</code> (daftar id yang boleh dipilih) — hanya di sana.
            Baris membacanya sendiri lewat <code class="font-mono text-[12.5px]">@@aware</code>, jadi header dan baris
            tidak mungkin punya jumlah kolom yang berbeda. Baris yang terkunci memberi
            <code class="font-mono text-[12.5px]">id</code> <code class="font-mono text-[12.5px]">null</code>: selnya
            tetap digambar, tapi kosong. Aksi per baris memakai <code class="font-mono text-[12.5px]">variant="secondary" size="xs"</code>,
            bukan <code class="font-mono text-[12.5px]">ghost</code>: tombol raised sesuai spesifikasi material, ghost hanya untuk aksi di dalam permukaan yang sudah punya kedalaman sendiri (toolbar, dropdown).
        </p>

        <x-ui.table :headers="['Faktur', 'Klien', '']" :selectable="[1, 2]">
            <x-ui.table.row :id="1">
                <x-ui.table.cell class="num text-[12.5px]">INV-2048</x-ui.table.cell>
                <x-ui.table.cell>PT Nusantara Jaya</x-ui.table.cell>
                <x-ui.table.cell align="right">
                    <div class="flex justify-end gap-1">
                        <x-ui.button type="button" variant="secondary" size="xs" title="Ubah">
                            <x-ui.icon name="pencil" class="size-4" />
                        </x-ui.button>
                        <x-ui.button type="button" variant="secondary" size="xs" title="Hapus">
                            <x-ui.icon name="trash-2" class="size-4 text-danger" />
                        </x-ui.button>
                    </div>
                </x-ui.table.cell>
            </x-ui.table.row>
            <x-ui.table.row :id="null">
                <x-ui.table.cell class="num text-[12.5px]">INV-2047</x-ui.table.cell>
                <x-ui.table.cell>
                    Sinar Abadi
                    <span class="ms-1.5 text-xs2 text-ink-muted">(terkunci)</span>
                </x-ui.table.cell>
                <x-ui.table.cell align="right">
                    <div class="flex justify-end gap-1">
                        <x-ui.button type="button" variant="secondary" size="xs" title="Lihat">
                            <x-ui.icon name="eye" class="size-4" />
                        </x-ui.button>
                    </div>
                </x-ui.table.cell>
            </x-ui.table.row>
        </x-ui.table>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">Beri setiap baris kolom yang sama, terkunci atau tidak. Sel kosong, bukan sel yang hilang.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">Merender kolom centang hanya kalau baris itu punya <code class="font-mono text-[12.5px]">id</code> — baris lain ikut bergeser.</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <x-ui.card title="Skeleton" subtitle="Untuk konten yang bentuknya sudah diketahui.">
                <x-ui.skeleton :lines="4" />
            </x-ui.card>

            <x-ui.card padding="false">
                <x-ui.empty-state title="Belum ada faktur jatuh tempo"
                                  description="Semua tagihan bulan ini sudah lunas. Faktur berikutnya terbit 1 September.">
                    <x-ui.button type="button" variant="secondary" size="sm">Buat faktur manual</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        </div>
    </x-docs.section>

    {{-- ─────────────────────────────────────────────────────── Feedback --}}
    <x-docs.section id="feedback" number="13" eyebrow="Feedback" title="Sela orang hanya kalau perlu keputusan">
        <div class="flex flex-col gap-3">
            <x-ui.alert variant="info" title="Periode penagihan berubah">
                Mulai 1 September, faktur terbit setiap tanggal 1.
            </x-ui.alert>
            <x-ui.alert variant="success" title="Pembayaran diterima">
                INV-2048 lunas Rp 24.850.000.
            </x-ui.alert>
            <x-ui.alert variant="warning" title="Kartu akan kedaluwarsa">
                Perbarui sebelum 30 September agar layanan tidak terputus.
            </x-ui.alert>
            <x-ui.alert variant="danger" title="Sinkronisasi gagal" dismissible>
                Tiga faktur tidak terkirim ke akuntansi. Coba lagi atau unduh manual.
            </x-ui.alert>
        </div>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">Toast untuk konfirmasi yang lewat begitu saja. Modal hanya kalau butuh jawaban.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">Modal bertumpuk, atau toast berisi error yang butuh tindakan.</p>
            </div>
        </div>
    </x-docs.section>

    {{-- ────────────────────────────────────────────────────────── Overlay --}}
    <x-docs.section id="overlay" number="14" eyebrow="Overlay &amp; Menu" title="Satu primitif, empat perilaku"
                    lead="Dropdown, tooltip, drawer, dan modal memakai fondasi yang sama: permukaan terangkat, tutup dengan Esc, kembalikan fokus ke pemicunya. Yang membedakan hanya posisi dan seberapa besar penyelaan yang dibenarkan.">

        <x-docs.example :code="$code['overlay']">
            <x-ui.dropdown label="Tindakan">
                <x-ui.dropdown-item href="#">
                    <x-ui.icon name="pencil" />
                    Ubah faktur
                </x-ui.dropdown-item>
                <x-ui.dropdown-item href="#">
                    <x-ui.icon name="download" />
                    Unduh PDF
                </x-ui.dropdown-item>
                <x-ui.dropdown-item href="#" shortcut="⌘R">
                    <x-ui.icon name="send" />
                    Kirim ulang
                </x-ui.dropdown-item>
                <x-ui.dropdown-item href="#" danger>
                    <x-ui.icon name="trash-2" />
                    Hapus
                </x-ui.dropdown-item>
            </x-ui.dropdown>

            <x-ui.tooltip text="Tooltip menjelaskan, bukan mengulang label">
                <x-ui.button type="button" variant="secondary">
                    <x-ui.icon name="info" class="size-4" />
                    Arahkan kursor
                </x-ui.button>
            </x-ui.tooltip>

            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('drawer-open', 'ds-drawer')">
                <x-ui.icon name="panel-right" class="size-4" />
                Buka drawer
            </x-ui.button>

            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('modal-open', 'ds-modal')">
                Buka modal
            </x-ui.button>
        </x-docs.example>

        <x-ui.drawer id="ds-drawer" title="Filter faktur">
            <p>Drawer dipakai untuk tugas sampingan yang butuh konteks halaman tetap terlihat.</p>

            <div class="mt-5 space-y-5">
                <x-ui.select name="ds_drawer_status" label="Status"
                             :options="['lunas' => 'Lunas', 'menunggu' => 'Menunggu', 'jatuh-tempo' => 'Jatuh tempo']"
                             placeholder="Semua" />
                <x-ui.datepicker name="ds_drawer_from" label="Terbit sejak" />
            </div>

            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('drawer-close', 'ds-drawer')">Batal</x-ui.button>
                <x-ui.button type="button">Terapkan filter</x-ui.button>
            </x-slot:footer>
        </x-ui.drawer>

        <x-ui.modal id="ds-modal" title="Hapus 3 faktur?" size="sm">
            <p>Faktur yang sudah terkirim ke klien akan ditarik. Tindakan ini tidak bisa dibatalkan.</p>

            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('modal-close', 'ds-modal')">Batal</x-ui.button>
                <x-ui.button type="button" variant="danger" x-on:click="$dispatch('modal-close', 'ds-modal')">Hapus faktur</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    </x-docs.section>

    {{-- ───────────────────────────────────────────────── Konten & status --}}
    <x-docs.section id="konten" number="15" eyebrow="Konten &amp; Status" title="Potongan kecil yang dipakai di mana-mana">
        <x-docs.example title="Badge" :code="$code['badge']">
            <x-ui.badge>Netral</x-ui.badge>
            <x-ui.badge variant="primary">Aksen</x-ui.badge>
            <x-ui.badge variant="success" pill dot>Lunas</x-ui.badge>
            <x-ui.badge variant="warning" pill dot>Menunggu</x-ui.badge>
            <x-ui.badge variant="danger" pill dot>Jatuh tempo</x-ui.badge>
            <x-ui.badge variant="info" size="md">Info</x-ui.badge>
        </x-docs.example>

        <x-docs.example title="Kartu metrik" class="mt-4" sunken :code="$code['stat']">
            <div class="grid w-full gap-4 sm:grid-cols-3">
                <x-ui.stat label="MRR" value="Rp 412jt" delta="+12,4%" trend="up" icon="trending-up" />
                <x-ui.stat label="Faktur terbit" value="1.284" delta="Stabil" trend="flat" />
                <x-ui.stat label="Churn" value="1,8%" delta="+0,3%" trend="down" />
            </div>
        </x-docs.example>

        <x-docs.example title="Spinner, avatar, breadcrumb" class="mt-4" :code="$code['misc']">
            <div class="flex w-full flex-wrap items-center gap-8">
                <x-ui.spinner size="sm" />
                <x-ui.spinner size="md" />
                <x-ui.breadcrumb :items="['Penagihan' => url('#'), 'Faktur' => null]" />
            </div>
        </x-docs.example>

        <x-docs.example title="Tab" class="mt-4" :code="$code['tabs']">
            <div class="w-full">
                <x-ui.tabs :tabs="['semua' => 'Semua', 'lunas' => 'Lunas', 'tertunggak' => 'Tertunggak']">
                    <x-slot:semua>
                        <p class="text-sm text-ink-secondary">1.284 faktur pada periode ini.</p>
                    </x-slot:semua>
                    <x-slot:lunas>
                        <p class="text-sm text-ink-secondary">1.190 faktur sudah dibayar penuh.</p>
                    </x-slot:lunas>
                    <x-slot:tertunggak>
                        <p class="text-sm text-ink-secondary">94 faktur lewat jatuh tempo.</p>
                    </x-slot:tertunggak>
                </x-ui.tabs>
            </div>
        </x-docs.example>
    </x-docs.section>

    {{-- ─────────────────────────────────────────────────────────── Layout --}}
    <x-docs.section id="layout" number="16" eyebrow="Layout" title="Lima layout, satu fondasi"
                    lead="Semua layout membungkus x-layouts.base — pilih berdasarkan konteks halaman, bukan selera. Masing-masing sudah membawa keputusan tentang tekstur latar dan lebar konten, jadi tidak perlu diulang di setiap halaman.">

        <div class="overflow-x-auto rounded-md border border-line">
            <table class="w-full text-left text-[13.5px]">
                <thead>
                    <tr class="border-b border-line bg-surface-sunken text-[11.5px] tracking-[0.05em] text-ink-muted uppercase">
                        <th class="px-4 py-2.5 font-semibold">Layout</th>
                        <th class="px-4 py-2.5 font-semibold">Dipakai untuk</th>
                        <th class="px-4 py-2.5 font-semibold">Props &amp; slot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr>
                        <td class="px-4 py-3 font-mono text-[12.5px] text-ink">x-layouts.base</td>
                        <td class="px-4 py-3 text-ink-secondary">Fondasi <code class="font-mono text-[12.5px]">&lt;html&gt;</code>/<code class="font-mono text-[12.5px]">&lt;body&gt;</code> untuk semua layout lain. Jarang dipakai langsung.</td>
                        <td class="px-4 py-3 text-ink-secondary"><code class="font-mono text-[12.5px]">backdrop</code> (page/shell), <code class="font-mono text-[12.5px]">texture</code></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-[12.5px] text-ink">x-layouts.admin</td>
                        <td class="px-4 py-3 text-ink-secondary">Semua halaman di dalam aplikasi — sidebar dan topbar kaca mengambang.</td>
                        <td class="px-4 py-3 text-ink-secondary"><code class="font-mono text-[12.5px]">heading</code>, <code class="font-mono text-[12.5px]">description</code>, <code class="font-mono text-[12.5px]">breadcrumb</code>, slot <code class="font-mono text-[12.5px]">actions</code></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-[12.5px] text-ink">x-layouts.guest-split</td>
                        <td class="px-4 py-3 text-ink-secondary">Masuk &amp; Daftar. Form solid di kiri, panel kepercayaan kaca di kanan (hilang di bawah <code class="font-mono text-[12.5px]">lg</code>), penuh viewport.</td>
                        <td class="px-4 py-3 text-ink-secondary"><code class="font-mono text-[12.5px]">heading</code>, <code class="font-mono text-[12.5px]">description</code>, slot <code class="font-mono text-[12.5px]">aside</code></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-[12.5px] text-ink">x-layouts.guest</td>
                        <td class="px-4 py-3 text-ink-secondary">Flow pendek &amp; sensitif — 2FA, reset/konfirmasi kata sandi, verifikasi email. Kartu tunggal, tanpa dekorasi.</td>
                        <td class="px-4 py-3 text-ink-secondary"><code class="font-mono text-[12.5px]">heading</code>, <code class="font-mono text-[12.5px]">description</code></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-[12.5px] text-ink">x-layouts.docs</td>
                        <td class="px-4 py-3 text-ink-secondary">Halaman /design-system ini sendiri — topbar + daftar isi sticky, bisa dibuka tanpa login.</td>
                        <td class="px-4 py-3 text-ink-secondary"><code class="font-mono text-[12.5px]">title</code>, <code class="font-mono text-[12.5px]">nav</code></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h4 class="eyebrow mt-6 mb-3">Auth split, dengan panel kepercayaan</h4>
        <div class="overflow-hidden rounded-md border border-line">
            <pre class="overflow-x-auto bg-code p-5 font-mono text-[12.5px] leading-relaxed text-code-ink"><code>{{ trim($code['layout-guest-split']) }}</code></pre>
        </div>
        <p class="mt-3 max-w-[64ch] text-[13.5px] text-ink-secondary">
            Slot <code class="font-mono text-[12.5px]">aside</code> hanya dirender di layar <code class="font-mono text-[12.5px]">lg</code> ke atas — isinya biasanya
            <code class="font-mono text-[12.5px]">x-auth.trust-panel</code> di bawah ini. Kutipan dan angkanya contoh, ganti sebelum rilis.
        </p>

        <x-docs.example title="x-auth.trust-panel" class="mt-4" :code="$code['trust-panel']">
            <div class="relative w-full max-w-sm overflow-hidden rounded-lg p-7"
                 style="background-image: radial-gradient(90% 90% at 70% 10%, var(--accent-soft) 0%, transparent 55%), var(--mat-base)">
                <div class="bg-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>

                <x-auth.trust-panel
                    quote="Penutupan buku yang dulu tiga hari sekarang selesai sebelum makan siang."
                    name="Maya Wardhani" role="Finance Lead · Nusantara Logistik" initials="MW"
                    :stats="[
                        ['value' => '2.400+', 'label' => 'tim keuangan'],
                        ['value' => 'Rp 4,1T', 'label' => 'tagihan diproses'],
                    ]" />
            </div>
        </x-docs.example>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">Pakai <code class="font-mono text-[12.5px]">guest-split</code> hanya untuk Masuk/Daftar — dua pintu masuk utama yang layak diberi ruang meyakinkan.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">Memakai panel kepercayaan di 2FA atau reset kata sandi — perhatian pengguna harus tetap di langkah keamanannya, pakai <code class="font-mono text-[12.5px]">x-layouts.guest</code>.</p>
            </div>
        </div>
    </x-docs.section>

    {{-- ───────────────────────────────────────────────── Grafik & progres --}}
    <x-docs.section id="grafik" number="17" eyebrow="Grafik &amp; Progres" title="Data yang berubah, dibaca sekilas"
                    lead="Warna deret tidak pernah ditulis literal — semuanya lewat token chart-1..6 di app.css, jadi satu tempat mengatur palet grafik di seluruh aplikasi.">

        <x-docs.example title="Progress" :code="$code['progress']">
            <div class="w-full max-w-sm space-y-4">
                <x-ui.progress label="Kuota penyimpanan" value="72" caption="72%" />
                <x-ui.progress value="40" tone="chart-2" height="4" />
            </div>
        </x-docs.example>

        <x-docs.example title="Ratio bar & legend" class="mt-4" :code="$code['ratio-bar']">
            <div class="w-full max-w-sm space-y-3">
                <x-ui.ratio-bar :segments="[
                    ['label' => 'Lunas', 'value' => 62, 'tone' => 'chart-1'],
                    ['label' => 'Menunggu', 'value' => 24, 'tone' => 'chart-2'],
                    ['label' => 'Jatuh tempo', 'value' => 14, 'tone' => 'chart-3'],
                ]" />
                <x-ui.legend :items="[
                    ['label' => 'Lunas', 'tone' => 'chart-1', 'value' => 'Rp 210jt'],
                    ['label' => 'Menunggu', 'tone' => 'chart-2', 'value' => 'Rp 81jt'],
                    ['label' => 'Jatuh tempo', 'tone' => 'chart-3', 'value' => 'Rp 47jt'],
                ]" />
            </div>
        </x-docs.example>

        <x-docs.example title="Bar chart — ApexCharts" class="mt-4" :code="$code['bar-chart']">
            <div class="w-full">
                <x-ui.bar-chart :series="[
                    ['label' => 'Jan', 'value' => 42], ['label' => 'Feb', 'value' => 58],
                    ['label' => 'Mar', 'value' => 37], ['label' => 'Apr', 'value' => 64],
                    ['label' => 'Mei', 'value' => 51],
                ]" height="140" />
            </div>
        </x-docs.example>

        <p class="mt-4 max-w-[64ch] text-[13.5px] text-ink-secondary">
            <code class="font-mono text-[12.5px]">x-ui.bar-chart</code> dirender <a href="https://apexcharts.com" class="text-link hover:underline" target="_blank" rel="noopener">ApexCharts</a>,
            diimpor dinamis (bukan di bundle utama) lewat <code class="font-mono text-[12.5px]">Alpine.data('apexBarChart', …)</code>
            di <code class="font-mono text-[12.5px]">resources/js/app.js</code> — halaman yang tidak menampilkan grafik tidak ikut menanggung beratnya.
            Warna dan tema tooltip dibaca ulang dari token CSS setiap <code class="font-mono text-[12.5px]">theme:changed</code>, jadi ganti tema tidak perlu memuat ulang halaman.
        </p>

        <x-docs.example title="Slider" class="mt-4" :code="$code['slider']">
            <div class="w-full max-w-sm">
                <x-ui.slider name="ds_threshold" label="Ambang notifikasi" :value="65" />
            </div>
        </x-docs.example>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">Batang tidak pernah diberi bevel/lift — ini data, bukan kontrol yang bisa ditekan.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">Menulis warna hex langsung di <code class="font-mono text-[12.5px]">tones</code> — pakai nama token <code class="font-mono text-[12.5px]">chart-1..6</code> supaya ikut berganti tema.</p>
            </div>
        </div>
    </x-docs.section>

    {{-- ────────────────────────────────────────── Pengungkapan & status --}}
    <x-docs.section id="pengungkapan" number="18" eyebrow="Pengungkapan &amp; Status" title="Sembunyikan detail, bukan sembunyikan orientasi"
                    lead="Accordion, timeline, wizard: tiga cara berbeda menunjukkan di mana pengguna berada — dalam sebuah FAQ, sebuah riwayat, atau sebuah alur bertahap.">

        <x-docs.example title="Accordion" :code="$code['accordion']">
            <div class="w-full max-w-md">
                <x-ui.accordion>
                    <x-ui.accordion-item title="Kenapa faktur saya belum terkirim?">
                        Periksa alamat email penagihan di halaman klien — email yang salah menahan pengiriman otomatis.
                    </x-ui.accordion-item>
                    <x-ui.accordion-item title="Bagaimana cara mengubah siklus tagihan?">
                        Buka Pengaturan → Penagihan, lalu pilih siklus baru. Perubahan berlaku mulai periode berikutnya.
                    </x-ui.accordion-item>
                </x-ui.accordion>
            </div>
        </x-docs.example>

        <x-docs.example title="Timeline & wizard" class="mt-4" :code="$code['timeline-wizard']">
            <div class="grid w-full gap-6 sm:grid-cols-2">
                <x-ui.timeline :items="[
                    ['text' => 'INV-2048 dibayar Rp 24.850.000', 'time' => '2 jam lalu'],
                    ['text' => 'INV-2048 dikirim ke Sinar Abadi', 'time' => 'Kemarin, 14.20'],
                    ['text' => 'Faktur dibuat', 'time' => '3 hari lalu'],
                ]" />

                <x-ui.wizard :steps="['Data akun', 'Hak akses', 'Konfirmasi']" :current="2" />
            </div>
        </x-docs.example>

        <x-docs.example title="Kbd & code block" class="mt-4" sunken :code="$code['kbd-code']">
            <div class="flex w-full flex-col gap-3">
                <div class="flex items-center gap-2">
                    <x-ui.kbd>⌘K</x-ui.kbd>
                    <x-ui.kbd variant="well">Esc</x-ui.kbd>
                </div>

                <x-ui.code-block language="php">Route::get('/faktur/{invoice}', ShowInvoice::class);</x-ui.code-block>
            </div>
        </x-docs.example>

        <x-docs.example title="Avatar stack & presence" class="mt-4" :code="$code['avatar-stack']">
            <div class="flex w-full flex-wrap items-center gap-8">
                <x-ui.avatar-stack :users="$avatarTeam" :limit="4" />

                <div class="relative inline-flex">
                    <x-ui.avatar :user="$avatarTeam->first()" size="md" />
                    <x-ui.presence-dot status="online" />
                </div>
            </div>
        </x-docs.example>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary">Item pertama <code class="font-mono text-[12.5px]">x-ui.timeline</code> dianggap yang terbaru — urutkan datanya dari baru ke lama.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">Dua <code class="font-mono text-[12.5px]">x-ui.accordion</code> terpisah untuk satu daftar — panel yang seharusnya saling eksklusif jadi bisa terbuka bersamaan.</p>
            </div>
        </div>
    </x-docs.section>

    {{-- ───────────────────────────────── Filter, unggah & jarak jauh --}}
    <x-docs.section id="lanjutan" number="19" eyebrow="Filter, Unggah &amp; Jarak Jauh" title="Untuk toolbar tabel dan panel detail"
                    lead="Tiga komponen ini biasanya berdampingan di halaman index: filter chip menyaring, drawer jarak jauh menampilkan detail baris tanpa berpindah halaman.">

        <x-docs.example title="Filter chips" :code="$code['filter-chips']">
            <x-ui.filter-chips param="ds_status"
                :options="['lunas' => 'Lunas', 'menunggu' => 'Menunggu', 'jatuh-tempo' => 'Jatuh tempo']" />
        </x-docs.example>

        <x-docs.example title="Dropzone" class="mt-4" :code="$code['dropzone']">
            <div class="w-full max-w-md">
                <x-ui.dropzone name="ds_dropzone" label="Unggah lampiran" hint="PDF atau gambar, maksimal 10 MB." />
            </div>
        </x-docs.example>

        <x-docs.example title="Notification menu" class="mt-4" sunken :code="$code['notification-menu']">
            <x-ui.notification-menu url="#" :items="[
                ['icon' => 'receipt', 'tone' => 'success', 'text' => 'INV-2048 dibayar', 'time' => '2 jam lalu'],
                ['icon' => 'triangle-alert', 'tone' => 'warning', 'text' => 'Kartu akan kedaluwarsa', 'time' => 'Kemarin'],
            ]" />
        </x-docs.example>

        <x-docs.example title="Drawer jarak jauh" class="mt-4" :code="$code['drawer-remote']">
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('drawer-remote-open', '/tidak-ada')">
                Buka panel jarak jauh
            </x-ui.button>

            <x-ui.drawer-remote title="Detail entitas" />
        </x-docs.example>

        <p class="mt-4 max-w-[64ch] text-[13.5px] text-ink-secondary">
            Tombol di atas sungguhan — klik untuk melihat bagaimana <code class="font-mono text-[12.5px]">x-ui.drawer-remote</code> menangani URL yang gagal
            (404) langsung di dalam panel, bukan membiarkan halaman berpindah diam-diam.
        </p>

        <div class="mt-4 grid gap-3.5 sm:grid-cols-2">
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                    <x-ui.icon name="check" class="size-3.5" />LAKUKAN
                </div>
                <p class="text-sm text-ink-secondary"><code class="font-mono text-[12.5px]">x-ui.filter-chips</code> untuk filter dengan ≤4 pilihan yang harus terbaca sekaligus. Pilihan lebih banyak tetap pakai <code class="font-mono text-[12.5px]">x-ui.select</code>.</p>
            </div>
            <div class="rounded-md border border-line bg-surface-raised p-4">
                <div class="mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-danger">
                    <x-ui.icon name="x" class="size-3.5" />HINDARI
                </div>
                <p class="text-sm text-ink-secondary">Menanam satu <code class="font-mono text-[12.5px]">x-ui.drawer-remote</code> per baris tabel — satu instans per halaman sudah cukup, baris hanya mengirim URL lewat event.</p>
            </div>
        </div>
    </x-docs.section>
</x-layouts.docs>
