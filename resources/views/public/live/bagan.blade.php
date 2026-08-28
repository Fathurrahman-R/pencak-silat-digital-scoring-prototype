<x-layouts.silat :title="'Bagan — '.$weightClass->name">
    {{--
        Bagan publik memakai DAFTAR PER TAHAP, bukan pohon.

        Pohon gugur butuh lebar mendatar yang tumbuh seiring jumlah babak, dan
        penonton tribun membuka halaman ini dengan HP tegak selebar 390px. Di
        sana pohonnya hanya bisa digulir ke samping -- gerakan yang membuat orang
        kehilangan tempatnya sendiri di dalam bagan.

        Pertanyaan yang dibawa penonton ke halaman ini cuma satu: siapa lawan
        berikutnya, dan siapa yang sudah lolos. Daftar menjawabnya tanpa
        menuntut siapa pun menelusuri garis.

        Pohonnya tetap dipakai overlay siaran <x-silat.bagan-pohon>, di kanvas
        1920px tempat bentuk itu justru paling masuk akal.
    --}}
    <div class="mx-auto flex min-h-screen max-w-[720px] flex-col gap-6 p-4">
        <header>
            <a href="{{ route('live.turnamen', $tournament) }}" class="text-[12px] text-silat-teks-redup">
                &larr; {{ $tournament->name }}
            </a>
            <h1 class="mt-1 text-[22px] leading-tight font-medium text-silat-teks">
                {{ $weightClass->jenis_kelamin->label() }} {{ $weightClass->golongan_usia->label() }} — {{ $weightClass->name }}
            </h1>
        </header>

        @if (! $bracket)
            <p class="text-[14px] leading-relaxed text-silat-teks-redup">
                Bagan kelas ini belum tersusun. Ia terbit setelah verifikasi pendaftaran dan timbang badan selesai.
            </p>
        @else
            @php
                /*
                 * Tahap yang punya partai BERJALAN diletakkan paling atas --
                 * orang yang membuka halaman ini di tengah acara mencari yang
                 * sedang berlangsung. Sisanya urut mundur dari final, mengikuti
                 * cara panitia dan announcer menyebut tahap: yang dikenal orang
                 * adalah "semifinal", bukan "babak ketiga".
                 */
                $urut = $babak
                    ->sortKeysDesc()
                    ->sortByDesc(fn ($partaiSatuBabak) => $partaiSatuBabak
                        ->contains(fn ($p) => $p->status === App\Models\SilatMatch::STATUS_BERLANGSUNG) ? 1 : 0);
            @endphp

            @foreach ($urut as $round => $partaiSatuBabak)
                @php
                    $adaBerjalan = $partaiSatuBabak->contains(fn ($p) => $p->status === App\Models\SilatMatch::STATUS_BERLANGSUNG);
                    $semuaSelesai = $partaiSatuBabak->every(fn ($p) => $p->status === App\Models\SilatMatch::STATUS_SELESAI);
                    $namaTahap = $bracket->namaBabak($round);
                @endphp

                <div class="flex flex-col">
                    <div class="flex items-baseline justify-between border-b-2 border-silat-teks pb-2">
                        <span class="text-[11px] tracking-[.28em] text-silat-teks uppercase">{{ $namaTahap }}</span>
                        <span class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-redup uppercase">
                            {{ $adaBerjalan ? 'Berjalan' : ($semuaSelesai ? 'Selesai' : 'Menunggu') }}
                        </span>
                    </div>

                    @foreach ($partaiSatuBabak as $partai)
                        @php
                            $berlangsung = $partai->status === App\Models\SilatMatch::STATUS_BERLANGSUNG;
                            $selesai = $partai->status === App\Models\SilatMatch::STATUS_SELESAI;
                            $pemenangId = $partai->winner_registration_id;
                        @endphp

                        <div class="flex flex-col gap-1 border-b border-silat-garis py-3 {{ $berlangsung ? 'bg-silat-merah-dalam/20' : '' }}">
                            @foreach ([['red', 'silat-merah'], ['blue', 'silat-biru']] as [$sisi, $warna])
                                @php($peserta = $partai->{$sisi})
                                <div class="flex items-center gap-3">
                                    <div class="w-[3px] shrink-0 self-stretch bg-{{ $warna }}"></div>
                                    <div class="min-w-0 flex-1 truncate text-[16px] {{ $peserta && $pemenangId === $peserta->id ? 'font-bold text-silat-teks' : ($selesai ? 'text-silat-teks-redup' : 'text-silat-teks') }}">
                                        {{ $peserta?->athletes->pluck('name')->implode(', ') ?? '—' }}
                                    </div>
                                    @if ($peserta && $pemenangId === $peserta->id)
                                        <span class="silat-angka shrink-0 text-[11px] tracking-[.1em] text-silat-emas uppercase">Lolos</span>
                                    @endif
                                </div>
                            @endforeach

                            <div class="mt-1 flex items-baseline justify-between gap-3 text-[12px] text-silat-teks-redup">
                                <span class="silat-angka">
                                    Partai {{ $partai->id }}
                                    @if ($partai->arena) · {{ $partai->arena->name }} @endif
                                    @if ($partai->scheduled_at) · {{ $partai->scheduled_at->format('H:i') }} @endif
                                </span>

                                @if ($berlangsung)
                                    <a href="{{ route('live.gelanggang', $partai->arena) }}" class="border-b border-silat-tepi-kendali text-silat-teks">Lihat skor</a>
                                @elseif ($selesai && $partai->win_reason)
                                    <span>{{ App\Support\Scoring\AlasanMenang::peta()[$partai->win_reason] ?? $partai->win_reason }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</x-layouts.silat>
