@php
    use App\Enums\StatusTurnamen;

    $registrationEnabled = Route::has('register');
    $docsEnabled = (bool) config('design-system.enabled');
@endphp

{{--
    Halaman depan publik.

    Isi sebelumnya adalah materi jualan bawaan boilerplate: hero "Hak akses yang
    berubah lewat panel, bukan lewat deploy", enam kartu fitur tentang resource
    key dan RBAC, tabel harga Rp 0 / Rp 490rb / Hubungi kami, dan footer
    "boilerplate Laravel". Halaman ini adalah alamat pertama yang dibuka penonton
    dan official kontingen lewat tunnel, jadi yang berdiri di sini sekarang
    adalah kejuaraannya sendiri.

    Tanpa `title`: layout dasar sudah memakai nama aplikasi apa adanya.
--}}
<x-layouts.base>

    <header class="bg-glow relative">
        <div class="mx-auto max-w-[1120px] px-6 pt-5">
            <nav class="glass flex h-14 items-center gap-5 rounded-full ps-5 pe-2.5">
                <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2.5">
                    <span class="flex size-[22px] items-center justify-center rounded-sm bg-accent font-display text-xs font-bold text-accent-on">
                        {{ mb_substr(config('app.name'), 0, 1) }}
                    </span>
                    <span class="font-display text-[15px] font-semibold tracking-tight text-ink">{{ config('app.name') }}</span>
                </a>

                <div class="hidden flex-1 gap-1 md:flex">
                    <a href="#kejuaraan" class="rounded-full px-3 py-1.5 text-[13.5px] text-ink-secondary transition hover:bg-surface-inset hover:text-ink">Kejuaraan</a>
                    <a href="#cara-kerja" class="rounded-full px-3 py-1.5 text-[13.5px] text-ink-secondary transition hover:bg-surface-inset hover:text-ink">Cara kerja</a>
                    @if ($docsEnabled)
                        <a href="{{ route('design-system.foundation') }}" class="rounded-full px-3 py-1.5 text-[13.5px] text-ink-secondary transition hover:bg-surface-inset hover:text-ink">Design system</a>
                    @endif
                </div>

                <div class="ms-auto flex items-center gap-2 md:ms-0">
                    <button type="button" data-theme-toggle
                            class="inline-flex size-9 items-center justify-center rounded-full text-ink-muted transition hover:bg-surface-inset hover:text-ink focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none">
                        <span class="sr-only">Ganti tema</span>
                        <x-ui.icon name="sun-moon" class="size-[18px]" />
                    </button>

                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="flex h-9 items-center rounded-full bg-[image:var(--mat-accent)] px-4 text-[13.5px] font-semibold text-accent-on shadow-[var(--bevel),var(--lift)] transition hover:brightness-95">
                            Buka dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-2 text-[13.5px] text-ink-secondary transition hover:text-ink">Masuk</a>

                        @if ($registrationEnabled)
                            <a href="{{ route('register') }}"
                               class="flex h-9 items-center rounded-full bg-[image:var(--mat-accent)] px-4 text-[13.5px] font-semibold text-accent-on shadow-[var(--bevel),var(--lift)] transition hover:brightness-95">
                                Daftar kontingen
                            </a>
                        @endif
                    @endauth
                </div>
            </nav>

            <div class="mx-auto max-w-[760px] pt-20 pb-16 text-center">
                @php($berjalan = $kejuaraan->firstWhere('status', StatusTurnamen::Berjalan))

                @if ($berjalan)
                    <span class="inline-flex items-center gap-2 rounded-full border border-line bg-surface-raised px-3.5 py-1.5 text-[12.5px] text-ink-secondary">
                        <span class="size-1.5 animate-pulse rounded-full bg-success"></span>
                        Sedang berlangsung — {{ $berjalan->name }}
                    </span>
                @endif

                <h1 class="mt-6 font-display text-[40px] leading-[1.05] font-semibold tracking-[-0.035em] text-ink sm:text-[56px]">
                    Skor pencak silat,<br>terbaca saat itu juga.
                </h1>

                <p class="mx-auto mt-5 max-w-[52ch] text-base text-ink-secondary">
                    Penilaian Tanding dan Jurus mengikuti Peraturan Pertandingan Pencak Silat Nasional 2025.
                    Ikuti jalannya pertandingan dari mana saja, atau masuk sebagai aparat gelanggang.
                </p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    @if ($berjalan)
                        <a href="{{ route('live.turnamen', $berjalan) }}"
                           class="flex h-11 items-center gap-2 rounded-full bg-[image:var(--mat-accent)] px-5 text-sm font-semibold text-accent-on shadow-[var(--bevel),var(--lift)] transition hover:brightness-95">
                            <x-ui.icon name="radio" class="size-4" />
                            Lihat skor langsung
                        </a>
                    @endif

                    <a href="{{ route('login') }}"
                       class="flex h-11 items-center gap-2 rounded-full border border-line-strong bg-[image:var(--mat-raised)] px-5 text-sm font-medium text-ink shadow-[var(--bevel),var(--lift)] transition hover:brightness-95">
                        Masuk sebagai aparat
                    </a>
                </div>
            </div>
        </div>
    </header>

    {{-- ──────────────────────────────────────────────────────── Kejuaraan --}}
    <section id="kejuaraan" class="border-t border-line bg-surface-raised px-6 py-20">
        <div class="mx-auto max-w-[1120px]">
            <h2 class="font-display text-[30px] leading-tight font-semibold tracking-[-0.03em] text-ink sm:text-[36px]">
                Kejuaraan
            </h2>
            <p class="mt-2 max-w-[60ch] text-sm text-ink-secondary">
                Skor tiap gelanggang terbit langsung dari meja operator, tanpa jeda penyalinan manual.
            </p>

            @if ($kejuaraan->isEmpty())
                <div class="mt-8 rounded-xl border border-line bg-surface px-6 py-14 text-center">
                    <p class="font-display text-lg font-semibold text-ink">Belum ada kejuaraan yang dibuka</p>
                    <p class="mx-auto mt-2 max-w-[46ch] text-sm text-ink-secondary">
                        Daftar ini terisi sendiri begitu panitia membuat kejuaraan dan menetapkan jadwalnya.
                    </p>
                </div>
            @else
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($kejuaraan as $t)
                        <article class="flex flex-col rounded-xl border border-line bg-surface p-5 shadow-lift">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-display text-base font-semibold text-ink">{{ $t->name }}</h3>
                                <x-ui.badge :variant="$t->status->variant()">{{ $t->status->label() }}</x-ui.badge>
                            </div>

                            <dl class="mt-3 space-y-1.5 text-[12.5px] text-ink-muted">
                                @if ($t->organizer)
                                    <div><dt class="sr-only">Penyelenggara</dt><dd>{{ $t->organizer }}</dd></div>
                                @endif
                                @if ($t->venue)
                                    <div><dt class="sr-only">Tempat</dt><dd>{{ $t->venue }}</dd></div>
                                @endif
                                @if ($t->starts_on)
                                    <div>
                                        <dt class="sr-only">Jadwal</dt>
                                        <dd>{{ $t->starts_on->translatedFormat('d M Y') }}
                                            @if ($t->ends_on) – {{ $t->ends_on->translatedFormat('d M Y') }} @endif
                                        </dd>
                                    </div>
                                @endif
                            </dl>

                            <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                                <a href="{{ route('live.turnamen', $t) }}"
                                   class="text-[13px] font-medium text-link underline-offset-2 hover:underline">
                                    Papan kejuaraan
                                </a>

                                {{-- Tautan langsung per gelanggang: penonton di venue biasanya
                                     hanya peduli pada satu gelanggang, yang tempat kontingennya
                                     bertanding. --}}
                                @foreach ($t->arenas as $arena)
                                    <a href="{{ route('live.gelanggang', $arena) }}"
                                       class="text-[13px] text-ink-secondary underline-offset-2 transition hover:text-ink hover:underline">
                                        {{ $arena->name }}
                                    </a>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ─────────────────────────────────────────────────────── Cara kerja --}}
    <section id="cara-kerja" class="border-t border-line px-6 py-20">
        <div class="mx-auto max-w-[1120px]">
            <h2 class="max-w-[22ch] font-display text-[30px] leading-tight font-semibold tracking-[-0.03em] text-ink sm:text-[36px]">
                Yang terjadi di gelanggang, tercatat apa adanya.
            </h2>

            <div class="mt-10 grid gap-px overflow-hidden rounded-xl border border-line bg-line sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['users', 'Tiga juri, satu nilai', 'Nilai hanya terbit ketika juri yang jumlahnya cukup menekan hal yang sama dalam satu jendela waktu. Tekanan yang tidak mencapai kesepakatan tetap tersimpan sebagai riwayat.'],
                    ['shield-check', 'Riwayat tidak pernah disunting', 'Koreksi Dewan Wasit Juri dicatat sebagai pembatalan beserta alasannya, bukan dengan mengubah baris aslinya.'],
                    ['timer', 'Waktu milik server', 'Timer dihitung dari jam server, bukan dari jam perangkat juri atau operator, sehingga seluruh layar menunjukkan detik yang sama.'],
                    ['wifi-off', 'Gelanggang tidak butuh internet', 'Panel juri, wasit, operator, dan overlay siaran berjalan penuh di jaringan lokal venue.'],
                    ['file-text', 'Berita acara siap tanda tangan', 'Tiap partai bisa dicetak lengkap dengan skor per babak, daftar nilai beserta juri penekannya, dan kolom pengesahan.'],
                    ['trophy', 'Rekap medali otomatis', 'Peringkat kontingen dan daftar juara tersusun sendiri dari hasil yang sudah disahkan.'],
                ] as [$ikon, $judul, $isi])
                    <div class="bg-surface-raised p-6">
                        <x-ui.icon :name="$ikon" class="size-5 text-accent" />
                        <h3 class="mt-3.5 font-display text-[15px] font-semibold text-ink">{{ $judul }}</h3>
                        <p class="mt-1.5 text-[13px] leading-relaxed text-ink-secondary">{{ $isi }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <footer class="border-t border-line px-6 py-8">
        <div class="mx-auto flex max-w-[1120px] flex-wrap items-center justify-between gap-3 text-[12.5px] text-ink-muted">
            <span>{{ config('app.name') }}</span>
            <span>Peraturan Pertandingan Pencak Silat Nasional 2025 · SK Ketua Umum PB IPSI Skep-70/III/2025</span>
        </div>
    </footer>
</x-layouts.base>
