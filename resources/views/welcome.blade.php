{{--
    Halaman depan publik: PAPAN PENGUMUMAN GOR, bukan halaman jualan.

    Orang membukanya untuk satu hal — skor sekarang, jadwal, lawan berikutnya —
    bukan untuk dibujuk. Karena itu tidak ada kartu berikon sejajar, tidak ada
    hero mengambang, tidak ada kalimat yang bisa ditempel ke aplikasi mana pun.
    Informasi mendahului segalanya, dan yang membedakan bagian adalah tipografi,
    bukan kotak.

    Radius nol di seluruh permukaan publik — lihat docs/BRIEF-DESAIN.md §3.
    Sudut membulat di mana-mana adalah tanda tangan keluaran yang disusun mesin,
    dan papan pengumuman memang tidak punya sudut membulat.
--}}
<x-layouts.silat title="Kejuaraan Pencak Silat">
    <div class="min-h-screen bg-silat-latar text-silat-teks">

        {{-- Bar identitas sekecil mungkin: halaman ini bukan tentang aplikasinya --}}
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-silat-garis px-6 py-4 sm:px-10">
            <div class="text-[11px] tracking-[.28em] text-silat-teks-redup uppercase">
                {{ $berjalan?->name ?? 'Digital Scoring Pencak Silat' }}
            </div>
            <div class="flex items-center gap-6">
                @if ($berjalan)
                    <span class="silat-angka text-[13px] text-silat-teks-redup">
                        {{ $berjalan->venue ?? 'Gelanggang' }} · {{ now()->translatedFormat('d M Y') }}
                    </span>
                @endif
                <a href="{{ route('login') }}" class="border-b border-silat-tepi-kendali text-[13px] font-medium text-silat-teks">Masuk</a>
            </div>
        </div>

        @if ($papanGelanggang->isNotEmpty())
            {{-- Skor langsung: informasi paling dicari, jadi paling atas --}}
            <div class="grid gap-px bg-silat-garis sm:grid-cols-2">
                @foreach ($papanGelanggang as $papan)
                    @php($partai = $papan['partai'])
                    <a href="{{ route('live.gelanggang', $papan['arena']) }}"
                       class="flex flex-col gap-5 bg-silat-latar px-6 py-6 sm:px-8">
                        <div class="flex items-baseline justify-between gap-4">
                            <span class="text-[11px] tracking-[.28em] uppercase">{{ $papan['arena']->name }}</span>
                            <span class="silat-angka text-[12px] text-silat-teks-redup">
                                @if ($partai)
                                    PARTAI {{ $partai->id }} · {{ strtoupper($partai->bracket->weightClass->name) }}
                                @else
                                    TIDAK ADA PARTAI BERJALAN
                                @endif
                            </span>
                        </div>

                        @if ($partai)
                            @foreach ([['red', 'merah', 'bg-silat-merah'], ['blue', 'biru', 'bg-silat-biru']] as [$sisi, $nama, $warna])
                                <div class="flex items-stretch gap-5">
                                    {{-- Sudut ditandai batang tepi, bukan bidang penuh: di daftar
                                         yang barisnya banyak, bidang penuh menutupi halaman. --}}
                                    <div class="w-[5px] shrink-0 {{ $warna }}"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-[22px] leading-tight font-medium">
                                            {{ $partai->{$sisi}?->athletes->pluck('name')->implode(', ') ?? '—' }}
                                        </div>
                                        <div class="mt-1 text-[13px] text-silat-teks-redup">Sudut {{ $nama }}</div>
                                    </div>
                                    <div class="silat-angka text-[64px] leading-none font-medium tabular-nums">
                                        {{ $papan['skor'][$nama] }}
                                    </div>
                                </div>
                            @endforeach

                            <div class="flex items-center justify-between border-t border-silat-garis pt-4">
                                <span class="silat-angka text-[14px]">BABAK {{ $partai->current_round ?? '–' }}</span>
                                <span class="text-[13px] text-silat-teks-redup">Lihat papan skor penuh</span>
                            </div>
                        @else
                            <p class="text-[14px] text-silat-teks-redup">
                                Partai berikutnya tampil di sini begitu operator memulainya.
                            </p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

        @if ($jadwalHariIni->isNotEmpty())
            {{-- Jadwal hari ini: yang sebenarnya dicari orang yang berdiri di GOR --}}
            <div class="px-6 pt-10 sm:px-10">
                <div class="flex items-baseline justify-between border-b-2 border-silat-teks pb-2">
                    <span class="text-[11px] tracking-[.28em] uppercase">Jadwal hari ini</span>
                    <span class="text-[12px] text-silat-teks-redup">{{ $jadwalHariIni->count() }} partai</span>
                </div>

                @foreach ($jadwalHariIni as $partai)
                    @php($berlangsung = $partai->status === App\Models\SilatMatch::STATUS_BERLANGSUNG)
                    <div class="flex items-center gap-4 border-b border-silat-garis py-3 {{ $berlangsung ? 'bg-silat-merah-dalam/25' : '' }}">
                        <div class="w-[3px] shrink-0 self-stretch {{ $berlangsung ? 'bg-silat-merah' : '' }}"></div>
                        <div class="silat-angka w-16 shrink-0 text-[15px] font-medium tabular-nums">
                            {{ $partai->scheduled_at?->format('H:i') ?? '—' }}
                        </div>
                        <div class="silat-angka w-10 shrink-0 text-[13px] text-silat-teks-redup tabular-nums">{{ $partai->id }}</div>
                        <div class="min-w-0 flex-1 truncate text-[15px]">
                            {{ $partai->red?->athletes->pluck('name')->implode(', ') ?? '—' }}
                            <span class="text-silat-teks-redup">lawan</span>
                            {{ $partai->blue?->athletes->pluck('name')->implode(', ') ?? '—' }}
                        </div>
                        <div class="hidden w-40 shrink-0 truncate text-[13px] text-silat-teks-redup sm:block">
                            {{ $partai->bracket->weightClass->name }}
                        </div>
                        <div class="w-28 shrink-0 truncate text-right text-[13px] {{ $berlangsung ? 'text-silat-teks' : 'text-silat-teks-redup' }}">
                            {{ $berlangsung ? $partai->arena?->name.' · kini' : ($partai->arena?->name ?? 'Belum dijadwal') }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid gap-10 px-6 pt-10 pb-12 sm:grid-cols-2 sm:px-10">
            @if ($medali->isNotEmpty())
                {{-- Emas hanya di sini dan di halaman medali: begitu dipakai untuk
                     hal lain, ia berhenti berarti "juara". --}}
                <div>
                    <div class="flex items-baseline justify-between border-b-2 border-silat-teks pb-2">
                        <span class="text-[11px] tracking-[.28em] uppercase">Perolehan medali</span>
                        <a href="{{ route('live.medali', $berjalan) }}" class="text-[12px] text-silat-teks-redup">Seluruh kontingen</a>
                    </div>
                    @foreach ($medali as $i => $baris)
                        <div class="flex items-baseline gap-4 border-b border-silat-garis py-3">
                            <span class="silat-angka w-4 text-[13px] tabular-nums {{ $i === 0 ? 'text-silat-emas' : 'text-silat-teks-redup' }}">{{ $i + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate text-[16px] font-medium">{{ $baris['kontingen'] }}</span>
                            <span class="silat-angka w-8 text-right text-[16px] font-medium tabular-nums {{ $i === 0 ? 'text-silat-emas' : '' }}">{{ $baris['emas'] }}</span>
                            <span class="silat-angka w-8 text-right text-[16px] text-silat-teks-redup tabular-nums">{{ $baris['perak'] }}</span>
                            <span class="silat-angka w-8 text-right text-[16px] text-silat-teks-redup tabular-nums">{{ $baris['perunggu'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div>
                <div class="flex items-baseline justify-between border-b-2 border-silat-teks pb-2">
                    <span class="text-[11px] tracking-[.28em] uppercase">Kejuaraan</span>
                    <span class="text-[12px] text-silat-teks-redup">{{ $kejuaraan->count() }} terdaftar</span>
                </div>

                @foreach ($kejuaraan as $t)
                    <div class="flex items-baseline justify-between gap-4 border-b border-silat-garis py-3">
                        <div class="min-w-0">
                            <div class="truncate text-[16px] font-medium">{{ $t->name }}</div>
                            <div class="mt-0.5 flex flex-wrap gap-x-3 text-[13px] text-silat-teks-redup">
                                @foreach ($t->arenas as $arena)
                                    <a href="{{ route('live.gelanggang', $arena) }}" class="border-b border-silat-tepi-kendali">{{ $arena->name }}</a>
                                @endforeach
                                @if ($t->arenas->isEmpty())
                                    <span>Gelanggang belum ditetapkan</span>
                                @endif
                            </div>
                        </div>
                        <div class="silat-angka shrink-0 text-right text-[13px] {{ $t->status === App\Enums\StatusTurnamen::Berjalan ? 'text-silat-teks' : 'text-silat-teks-redup' }}">
                            {{ $t->status === App\Enums\StatusTurnamen::Berjalan ? 'BERJALAN' : $t->starts_on?->translatedFormat('d M Y') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="border-t border-silat-garis px-6 py-6 text-[13px] leading-relaxed text-silat-teks-redup sm:px-10">
            Halaman ini terbuka untuk umum dan tidak memerlukan akun. Penilaian mengikuti Peraturan
            Pertandingan Pencak Silat 2025; skor berubah begitu Dewan Wasit Juri mengesahkan hasil.
        </div>
    </div>
</x-layouts.silat>
