@php
    use App\Enums\ResourceAction;
@endphp

<x-layouts.silat :title="'Kendali — '.$arena->name" :manifest="$manifestUrl ?? null">
    {{--
        Panel Pengendali Gelanggang.

        Satu perangkat memegang satu gelanggang: ia yang menentukan partai mana
        yang sedang dimainkan, menjalankan timer, dan memindahkan babak. Juri,
        wasit, dan dewan wasit juri mengikuti pilihannya tanpa menyentuh
        perangkat masing-masing.

        Tata letaknya sengaja dua kolom, bukan satu daftar panjang: antrean
        jadwal dibaca sambil melirik, sementara timer dan tombol babak ditekan
        sambil mengawasi matras. Yang kedua tidak boleh ikut tergulir saat yang
        pertama digulir.
    --}}
    <div x-data="partaiPanel(@js($config))" class="flex h-dvh flex-col overflow-hidden">

        <header class="flex shrink-0 items-center gap-5 border-b border-silat-garis px-6 py-4">
            <div class="min-w-0">
                <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">
                    PENGENDALI · {{ $arena->name }}
                </p>
                <p class="mt-1 truncate text-[18px] font-medium tracking-[-0.01em] text-silat-teks">
                    <template x-if="match?.id">
                        <span>
                            Partai <span x-text="match.id"></span>
                            · <span x-text="(match.red?.athletes ?? []).join(', ') || '—'"></span>
                            vs <span x-text="(match.blue?.athletes ?? []).join(', ') || '—'"></span>
                        </span>
                    </template>
                    <template x-if="! match?.id">
                        <span class="text-silat-teks-redup">Belum ada partai dipilih</span>
                    </template>
                </p>
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-3">
                <x-silat.indikator-koneksi />

                {{-- Pendaratan otomatis tidak boleh mengunci: petugas yang
                     juga memegang peran lain harus punya jalan keluar. --}}
                <a href="{{ route('dashboard', ['dashboard' => 1]) }}"
                   class="silat-angka text-[11px] tracking-[.08em] text-silat-teks-samar uppercase">Dashboard</a>
            </div>
        </header>

        <template x-if="galat || pesan">
            <p class="mx-6 mt-4 shrink-0 rounded-silat bg-silat-panel px-4 py-2.5 text-[13.5px] text-silat-teks-kedua"
               x-text="galat || pesan"></p>
        </template>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-5 px-6 pt-5 pb-6 lg:grid-cols-[1fr_340px]">

            {{--
                ANTREAN JADWAL.

                Digulir sendiri. Gelanggang berisi empat puluh partai bukan
                hal luar biasa, dan yang tidak boleh ikut terdorong keluar
                layar adalah kolom timer di sebelahnya.
            --}}
            <div class="flex min-h-0 min-w-0 flex-col">
                <div class="flex shrink-0 items-center gap-3.5 pb-3">
                    <p class="text-[17px] font-semibold tracking-[-0.01em] text-silat-teks">Antrean gelanggang</p>
                    <span class="silat-angka text-[11.5px] text-silat-teks-samar"
                          x-text="(panel?.antrean?.length ?? 0) + ' partai'"></span>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto rounded-silat-besar border border-silat-garis">
                    <template x-if="(panel?.antrean?.length ?? 0) === 0">
                        <p class="px-4 py-8 text-center text-[13.5px] text-silat-teks-redup">
                            Belum ada partai dijadwalkan ke gelanggang ini.
                        </p>
                    </template>

                    <template x-for="(partai, i) in (panel?.antrean ?? [])" :key="partai.id">
                        <div class="flex items-center gap-3.5 px-4 py-3.5"
                             x-bind:class="[
                                 i > 0 ? 'border-t border-silat-panel' : '',
                                 partai.aktif ? 'bg-silat-panel' : '',
                             ]">

                            <span class="silat-angka w-8 shrink-0 text-[12px] text-silat-teks-samar"
                                  x-text="partai.urutan ?? '—'"></span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[14px] text-silat-teks">
                                    <span x-text="partai.merah || '—'"></span>
                                    <span class="text-silat-teks-samar">vs</span>
                                    <span x-text="partai.biru || '—'"></span>
                                </p>
                                <p class="silat-angka mt-1 text-[11.5px] text-silat-teks-samar">
                                    Partai <span x-text="partai.id"></span>
                                    · <span x-text="partai.kelas ?? '—'"></span>
                                    · <span x-text="partai.status"></span>
                                </p>
                            </div>

                            {{-- Yang sedang tayang tidak menawarkan tombol
                                 pindah ke dirinya sendiri: tombol yang tidak
                                 mengubah apa pun tetap menuntut dibaca. --}}
                            <template x-if="partai.aktif">
                                <span class="silat-angka shrink-0 rounded-silat-kecil bg-silat-teks px-2.5 py-1.5 text-[11px] font-semibold text-silat-latar">
                                    TAYANG
                                </span>
                            </template>

                            <template x-if="! partai.aktif">
                                <button type="button"
                                        x-on:click="pilihPartai(partai.id)"
                                        class="h-11 w-24 shrink-0 rounded-silat border border-silat-tepi-kendali text-[13px] font-medium text-silat-teks-kedua">
                                    Tayangkan
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- TIMER DAN BABAK. Tidak pernah tergulir. --}}
            <aside class="flex min-h-0 flex-col gap-4">
                <div class="flex flex-col items-center justify-center gap-3.5 rounded-silat-besar border border-silat-garis p-5">
                    <div class="flex gap-1.5">
                        <template x-for="i in (peraturan?.jumlah_babak ?? 0)" :key="i">
                            <span class="silat-angka rounded-silat-kecil px-2.5 py-1.5 text-[11px] tracking-[.06em]"
                                  x-bind:class="i === match?.current_round
                                      ? 'bg-silat-teks font-semibold text-silat-latar'
                                      : (i < (match?.current_round ?? 1)
                                          ? 'bg-silat-garis text-silat-teks-redup'
                                          : 'border border-silat-tepi-kendali text-silat-teks-samar')"
                                  x-text="'B' + i"></span>
                        </template>
                    </div>

                    <p class="silat-angka text-[64px] leading-none font-medium"
                       x-bind:class="babakAktif?.status === 'berjalan' ? 'text-silat-teks' : 'text-silat-teks-redup'"
                       x-text="tampilWaktu" role="timer" aria-live="off">00:00</p>

                    <p class="silat-angka text-[11.5px] tracking-[.12em] text-silat-teks-samar uppercase"
                       x-text="sudahSelesai
                           ? 'Partai selesai'
                           : ({ berjalan: 'Berjalan', jeda: 'Dijeda', belum_mulai: 'Belum dimulai', selesai: 'Babak selesai' }[babakAktif?.status] ?? 'Belum dimulai')"></p>
                </div>

                @resource(rk('partai', ResourceAction::Update))
                    <div class="flex flex-col gap-2" x-show="match?.id">
                        {{-- Tinggi 64px mengikuti batas sentuh gelanggang:
                             ditekan sambil mengawasi matras, bukan layar. --}}
                        <button type="button" x-show="babakUntukDimulai !== null && ! susulanTerbuka" x-on:click="mulaiBabak()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat bg-silat-aksi text-[16px] font-semibold text-silat-aksi-teks">
                            <span x-text="(babakAktif?.status === 'belum_mulai' ? 'Mulai ulang babak ' : 'Mulai babak ') + babakUntukDimulai"></span>
                        </button>
                        <button type="button" x-show="babakAktif?.status === 'jeda' && ! susulanTerbuka" x-on:click="lanjutkan()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat bg-silat-aksi text-[16px] font-semibold text-silat-aksi-teks">Lanjutkan</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan'" x-on:click="jeda()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali text-[16px] font-semibold text-silat-teks-kedua">Jeda</button>
                        <button type="button" x-show="babakAktif?.status === 'berjalan' || babakAktif?.status === 'jeda'" x-on:click="selesaikanBabak()"
                                class="h-[var(--silat-sentuh-min)] rounded-silat border border-silat-tepi-kendali text-[16px] font-semibold text-silat-teks-kedua">Selesaikan babak</button>
                    </div>
                @endresource

                @resource(rk('kendali-gelanggang', ResourceAction::Manage))
                    {{--
                        BABAK SUSULAN.

                        Nilai atau hukuman yang terlewat di babak sebelumnya
                        dicatat dari sini. Babak berjalan dijeda selama itu, dan
                        seluruh panel di gelanggang beralih serentak.

                        Daftar babak yang boleh dibuka datang dari server
                        (`dapat_dibuka`), bukan disusun ulang di sini: panel yang
                        menyusun aturannya sendiri adalah panel yang suatu saat
                        menawarkan tombol untuk hal yang server tolak.
                    --}}
                    <div class="rounded-silat-besar border border-silat-garis p-4" x-show="match?.id">
                        <p class="silat-angka text-[10px] tracking-[.14em] text-silat-teks-redup uppercase">Input susulan</p>

                        <template x-if="susulanTerbuka">
                            <div class="mt-2.5">
                                <p class="text-[13.5px] leading-[1.6] text-amber-300">
                                    Babak <span x-text="susulan?.round"></span> sedang dibuka.
                                    Seluruh panel gelanggang mencatat ke babak itu.
                                </p>
                                <button type="button" x-on:click="tutupSusulan()"
                                        class="mt-3 h-12 w-full rounded-silat bg-silat-aksi text-[15px] font-semibold text-silat-aksi-teks">
                                    Tutup susulan
                                </button>
                            </div>
                        </template>

                        <template x-if="! susulanTerbuka">
                            <div class="mt-2.5 flex flex-wrap gap-2">
                                <template x-for="babak in (rounds ?? []).filter(r => r.dapat_dibuka)" :key="babak.round">
                                    <button type="button" x-on:click="bukaSusulan(babak.round)"
                                            class="h-11 rounded-silat border border-silat-tepi-kendali px-3.5 text-[13px] font-medium text-silat-teks-kedua">
                                        <span x-text="'Catat susulan babak ' + babak.round"></span>
                                    </button>
                                </template>

                                <template x-if="(rounds ?? []).filter(r => r.dapat_dibuka).length === 0">
                                    <p class="text-[13px] leading-[1.6] text-silat-teks-redup">
                                        Belum ada babak yang sudah selesai untuk dibuka kembali.
                                    </p>
                                </template>
                            </div>
                        </template>
                    </div>
                @endresource

                {{--
                    Mengosongkan gelanggang. Bukan tombol besar, dan bukan di
                    dekat tombol babak: ia jarang dipakai, dan yang jarang
                    dipakai tidak boleh duduk di tempat yang tangan sudah hafal.
                --}}
                <button type="button" x-show="match?.id" x-on:click="pilihPartai(null)"
                        class="mt-auto h-12 shrink-0 rounded-silat border border-silat-tepi-kendali text-[13.5px] font-medium text-silat-teks-redup">
                    Kosongkan gelanggang
                </button>
            </aside>
        </div>
    </div>
</x-layouts.silat>
