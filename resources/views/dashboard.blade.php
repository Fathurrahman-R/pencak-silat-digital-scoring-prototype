@php
    /*
     * Judulnya sama untuk semua orang, mengikuti kata yang dipakai menu
     * samping. Panitia yang menekan "Beranda" lalu mendarat di halaman
     * berjudul "Dashboard" akan mengira ia salah klik -- dan "Dashboard"
     * juga satu-satunya kata Inggris yang tersisa di navigasi.
     *
     * Yang membedakan siapa yang membuka tetap ada, tapi di keterangannya.
     * Seorang juri yang login dari HP di pinggir gelanggang tidak sedang
     * mencari ringkasan isi aplikasi; ia mencari partainya.
     */
    $judul = 'Beranda';
    $keterangan = $tampilkanRingkasan
        ? ($turnamen?->name ?? 'Belum ada kejuaraan yang dibuka.')
        : 'Partai tempat Anda ditugaskan hari ini.';
@endphp

<x-layouts.admin :heading="$judul" :description="$keterangan">
    {{-- Paling atas, sebelum apa pun: wasit dan juri membuka halaman ini di HP
         di pinggir gelanggang, dan satu-satunya hal yang mereka butuhkan
         adalah pintu masuk ke partainya. --}}
    {{--
        Ketua Pertandingan tidak ditugaskan ke satu partai, jadi kartu "Partai
        saya" di bawah selalu kosong untuknya. Tanpa pintu ini panelnya hanya
        bisa dicapai dengan mengetik alamatnya sendiri — dan panel yang tidak
        punya pintu masuk sama saja tidak ada.
    --}}
    @if ($turnamen && resource_allows(rk('partai', App\Enums\ResourceAction::Manage)))
        <x-si.kartu judul="Panel Ketua Pertandingan"
                    keterangan="Seluruh gelanggang sekaligus, dan perkara yang menunggu keputusanmu."
                    class="mb-4">
            <x-si.tombol tautan="{{ route('admin.turnamen.ketua-pertandingan.index', $turnamen) }}" varian="utama">
                Buka panel
            </x-si.tombol>
        </x-si.kartu>
    @endif

    @if ($penugasan !== [])
        <x-si.kartu judul="Partai saya" keterangan="Partai tempat Anda ditugaskan" class="mb-4">
            <div class="divide-y divide-line">
                @foreach ($penugasan as $tugas)
                    <a href="{{ $tugas['url'] }}"
                       class="-mx-2 flex items-center gap-3 rounded-md px-2 py-3 transition-colors hover:bg-surface-inset">
                        <div class="min-w-0 flex-1">
                            {{--
                                Sudut ditandai warnanya, tidak hanya diurutkan. Aparat
                                perlu tahu siapa merah dan siapa biru SEBELUM membuka
                                panel — begitu panel terbuka dan babak berjalan, tidak
                                ada waktu lagi untuk mencocokkan nama.

                                Titik warna didampingi teks "Merah"/"Biru" di
                                aria-label, jadi maknanya tidak bergantung warna saja.
                            --}}
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="size-2 shrink-0 rounded-full bg-[#d42027]"
                                          role="img" aria-label="Sudut merah"></span>
                                    {{ $tugas['merah'] ?: 'Sudut merah' }}
                                </span>

                                <span class="text-ink-muted">vs</span>

                                <span class="inline-flex items-center gap-1.5">
                                    <span class="size-2 shrink-0 rounded-full bg-[#12439e]"
                                          role="img" aria-label="Sudut biru"></span>
                                    {{ $tugas['biru'] ?: 'Sudut biru' }}
                                </span>
                            </p>
                            <p class="truncate text-xs text-ink-muted">
                                {{ $tugas['sebutan'] }} · {{ $tugas['kelas'] }}
                                @if ($tugas['gelanggang']) · {{ $tugas['gelanggang'] }} @endif
                                @if ($tugas['waktu']) · {{ $tugas['waktu'] }} @endif
                            </p>
                        </div>

                        {{-- Status selalu tampil, bukan hanya saat berlangsung: tanpanya
                             juri tidak bisa membedakan partai yang sudah dimulai dari
                             yang masih menunggu, dan harus membuka panel untuk tahu. --}}
                        @if ($tugas['berlangsung'])
                            <x-si.badge varian="sukses">Berlangsung</x-si.badge>
                        @else
                            <x-si.badge varian="netral">Menunggu</x-si.badge>
                        @endif

                        <x-si.ikon nama="chevron-right" class="size-4 shrink-0 text-ink-muted" />
                    </a>
                @endforeach
            </div>
        </x-si.kartu>
    @endif

    @if (! $tampilkanRingkasan && $penugasan === [])
        <x-si.kartu class="mb-4">
            <x-si.kosong judul="Belum ada partai untuk Anda"
                         syarat="Kartu partai muncul di sini setelah panitia menugaskan Anda sebagai wasit atau juri. Untuk kategori Jurus, buka menu Pertandingan → Kategori Jurus." />
        </x-si.kartu>
    @endif

    @if ($tampilkanRingkasan)
        @if ($turnamen === null)
            <x-si.kartu>
                <x-si.kosong judul="Belum ada kejuaraan"
                             syarat="Buat kejuaraan lebih dulu lewat menu Kejuaraan. Angka dan jadwal di halaman ini mengikuti kejuaraan yang sedang dibuka." />
            </x-si.kartu>
        @else
            {{--
                YANG MENUNGGU DIKERJAKAN.

                Menggantikan empat ubin angka — Kontingen, Pendaftaran
                terverifikasi, Partai hari ini, Menunggu verifikasi. Ubin angka
                menjawab "berapa", padahal pertanyaan yang dibawa panitia ke
                layar depan adalah "apa yang harus saya kerjakan sekarang".

                Angka 4 di ubin "Menunggu verifikasi" tidak memberi tahu bahwa
                empat pendaftaran itu menahan penyusunan bagan, dan tidak
                mengantarkan siapa pun ke layarnya. Tiap baris di sini menyebut
                ketiganya: berapa, kenapa mendesak, dan ke mana pergi.

                Barisnya sendiri yang jadi tautan, bukan tombol kecil di
                ujungnya — sasaran setinggi baris penuh jauh lebih sulit
                meleset, terutama di layar sentuh.
            --}}
            @if ($pekerjaan !== [])
                <x-si.kartu judul="Yang menunggu dikerjakan"
                            keterangan="Hanya yang jumlahnya belum nol. Baris hilang sendiri begitu pekerjaannya selesai."
                            padat>
                    <div class="divide-y divide-line">
                        @foreach ($pekerjaan as $baris)
                            <a href="{{ $baris['tautan'] }}"
                               class="-mx-2 flex items-start gap-3 rounded-[var(--radius-kecil)] px-2 py-3 hover:bg-surface-inset focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                                <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-[var(--radius-kecil)] bg-surface-inset text-ink-secondary">
                                    <x-si.ikon :nama="$baris['ikon']" class="size-[18px]" />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-[15px] text-ink">
                                        <span class="font-mono text-[17px] font-semibold tabular-nums">{{ $baris['jumlah'] }}</span>
                                        {{ $baris['benda'] }}
                                    </span>
                                    <span class="mt-0.5 block text-[14px] leading-relaxed text-ink-muted">{{ $baris['sebab'] }}</span>
                                </span>

                                <x-si.ikon nama="chevron-right" class="mt-2 size-4 shrink-0 text-ink-muted" />
                            </a>
                        @endforeach
                    </div>
                </x-si.kartu>
            @else
                <x-si.kartu padat>
                    <div class="flex items-center gap-3 py-2">
                        <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-kecil)] bg-success-soft text-success">
                            <x-si.ikon nama="check" class="size-[18px]" />
                        </span>
                        <div>
                            <p class="text-[15px] font-semibold text-ink">Tidak ada yang menunggu</p>
                            <p class="text-[14px] leading-relaxed text-ink-muted">
                                Seluruh pekerjaan yang bisa dilihat akun ini sudah selesai untuk kejuaraan yang sedang dibuka.
                            </p>
                        </div>
                    </div>
                </x-si.kartu>
            @endif

            <div class="mt-4 grid items-start gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <x-si.kartu judul="Partai hari ini" :keterangan="now()->translatedFormat('l, d F Y')">
                    @forelse ($partaiHariIni as $gelanggang => $daftar)
                        <div class="mb-4 last:mb-0">
                            <p class="eyebrow mb-2">{{ $gelanggang }}</p>

                            <div class="divide-y divide-line">
                                @foreach ($daftar as $partai)
                                    <div class="flex items-center gap-3 py-2">
                                        <span class="num w-[46px] shrink-0 text-xs text-ink-muted">{{ $partai['waktu'] ?? '—' }}</span>

                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm text-ink">
                                                {{ $partai['merah'] ?: 'Sudut merah' }}
                                                <span class="text-ink-muted">vs</span>
                                                {{ $partai['biru'] ?: 'Sudut biru' }}
                                            </p>
                                            <p class="truncate text-xs2 text-ink-muted">{{ $partai['kelas'] }}</p>
                                        </div>

                                        @if ($partai['berlangsung'])
                                            <x-si.badge varian="sukses">Berlangsung</x-si.badge>
                                        @elseif ($partai['selesai'])
                                            <x-si.badge varian="netral">Selesai</x-si.badge>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <x-si.kosong judul="Tidak ada partai terjadwal hari ini"
                                     syarat="Partai muncul di sini setelah bagan dikunci dan jadwal ditetapkan lewat menu Pertandingan → Jadwal." />
                    @endforelse
                </x-si.kartu>

                <x-si.kartu judul="Hasil terakhir" keterangan="Sudah disahkan Dewan Wasit Juri">
                    @if ($hasilTerakhir === [])
                        <p class="text-sm text-ink-muted">Belum ada hasil yang disahkan.</p>
                    @else
                        <x-si.linimasa :daftar="$hasilTerakhir" />
                    @endif
                </x-si.kartu>
            </div>

            <x-si.kartu judul="Urutan kerja kejuaraan" class="mt-4">
                {{-- Bernomor karena urutannya memang mengikat: tiap tahap punya
                     prasyarat yang membuat tombol tahap berikutnya mati kalau
                     dilangkahi. Rinciannya ada di docs/PANDUAN-WORKFLOW.md. --}}
                <ol class="space-y-4">
                    @foreach ([
                        ['Daftarkan kontingen dan pesilatnya', 'Berkas pesilat wajib lengkap sebelum pendaftaran bisa diverifikasi.'],
                        ['Terbitkan tagihan dan tunggu pelunasan', 'Pendaftaran terkunci sampai tagihan kontingen lunas.'],
                        ['Verifikasi pendaftaran dan timbang badan', 'Hasil timbang di venue yang menentukan kelas, bukan berat yang diakui saat mendaftar.'],
                        ['Susun dan kunci bagan, lalu tetapkan jadwal', 'Setelah dikunci, penyusunan ulang wajib beralasan dan tercatat di jejak audit.'],
                        ['Tugaskan aparat, lalu jalankan partai', 'Wasit dan juri menemukan partai yang ditugaskan langsung dari halaman depan mereka.'],
                    ] as $i => [$langkah, $catatan])
                        <li class="flex items-start gap-3.5">
                            <span class="num flex size-6 shrink-0 items-center justify-center rounded-sm bg-surface-inset text-[11px] text-ink-muted">
                                {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <p class="text-sm text-ink-secondary">
                                <strong class="font-semibold text-ink">{{ $langkah }}</strong> — {{ $catatan }}
                            </p>
                        </li>
                    @endforeach
                </ol>
            </x-si.kartu>
        @endif
    @endif
</x-layouts.admin>
