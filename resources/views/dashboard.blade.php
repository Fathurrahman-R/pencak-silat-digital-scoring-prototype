<x-layouts.admin heading="Dashboard" description="Ringkasan singkat isi aplikasi.">
    @if ($unmappedCount > 0)
        <x-ui.alert variant="warning" title="Ada resource key yang belum dipetakan" class="mb-6">
            {{ $unmappedCount }} key belum menunjuk permission mana pun, jadi aksesnya tertutup untuk semua orang
            kecuali super admin.
            <a href="{{ route('admin.mappings.index', ['status' => 'unmapped']) }}" class="font-medium text-link underline-offset-2 hover:underline">
                Lihat daftarnya
            </a>
        </x-ui.alert>
    @endif

    {{-- Baris metrik duduk di permukaan solid seperti sisi halaman lainnya —
         kaca dipakai hanya sidebar dan topbar. --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <x-ui.stat :label="$stat['label']"
                       :value="number_format($stat['value'], 0, ',', '.')"
                       :icon="$stat['icon']" />
        @endforeach
    </div>

    {{-- Dua kolom: grafik yang lebih lebar di kiri, aktivitas ringkas di
         kanan. Rasio 1.6fr/1fr yang sama dipakai di seluruh pola dashboard. --}}
    <div class="mt-4 grid items-start gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <x-ui.card title="Pengguna baru" subtitle="6 bulan terakhir">
            <x-ui.bar-chart :series="$signups" :tones="['chart-1']" :height="184" />
        </x-ui.card>

        <x-ui.card title="Aktivitas terbaru">
            @if ($activity === [])
                <p class="text-sm text-ink-muted">Belum ada aktivitas.</p>
            @else
                <x-ui.timeline :items="$activity" />
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Mulai dari mana" class="mt-4">
        {{-- Bernomor karena urutannya memang berarti: resource dulu, baru
             pemetaannya, baru pembagiannya ke role. --}}
        <ol class="space-y-4">
            <li class="flex items-start gap-3.5">
                <span class="num flex size-6 shrink-0 items-center justify-center rounded-sm bg-surface-inset text-[11px] text-ink-muted">01</span>
                <p class="text-sm text-ink-secondary">
                    Buat <strong class="font-semibold text-ink">Resource</strong> baru untuk tiap modul aplikasi.
                    Sistem otomatis membuatkan permission untuk tiap aksi yang dicentang.
                </p>
            </li>
            <li class="flex items-start gap-3.5">
                <span class="num flex size-6 shrink-0 items-center justify-center rounded-sm bg-surface-inset text-[11px] text-ink-muted">02</span>
                <p class="text-sm text-ink-secondary">
                    Ubah permission di balik sebuah key lewat <strong class="font-semibold text-ink">Pemetaan Key</strong>
                    — tanpa menyentuh kode.
                </p>
            </li>
            <li class="flex items-start gap-3.5">
                <span class="num flex size-6 shrink-0 items-center justify-center rounded-sm bg-surface-inset text-[11px] text-ink-muted">03</span>
                <p class="text-sm text-ink-secondary">
                    Bagikan permission ke <strong class="font-semibold text-ink">Role</strong>, lalu tugaskan role itu
                    ke pengguna.
                </p>
            </li>
        </ol>
    </x-ui.card>
</x-layouts.admin>
