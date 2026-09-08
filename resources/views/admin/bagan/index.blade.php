@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Bagan"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Bagan' => null,
                 ]">
    <div class="space-y-4">
        <x-si.callout varian="keterangan" judul="Bagan disusun dari peserta yang sudah disahkan">
            Peserta yang berkasnya belum lengkap atau tagihan kontingennya belum lunas tidak ikut
            masuk hitungan, meski sudah mendaftar. Susun bagan setelah verifikasi dan timbang badan
            selesai untuk kelas yang bersangkutan.
        </x-si.callout>

        <x-si.kartu judul="Kelas tanding">
            <x-slot:aksi>
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    @if ($tampil !== 'terpakai')
                        <input type="hidden" name="tampil" value="{{ $tampil }}">
                    @endif

                    <x-si.isian name="q" :value="$cari" placeholder="Cari kelas…" class="w-[200px]" />
                </form>
            </x-slot:aksi>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <x-si.saring param="tampil" semua="Semua kelas" :sekarang="$tampil === 'semua' ? null : $tampil" :pilihan="[
                             'terpakai' => 'Ada peserta atau bagan',
                             'tersusun' => 'Sudah disusun',
                             ]" />

                <p class="text-xs text-ink-muted">
                    Menampilkan {{ $kelas->count() }} dari {{ $jumlahSemua }} kelas.
                    @if ($tampil === 'terpakai' && $jumlahSemua > $jumlahTerpakai)
                        {{ $jumlahSemua - $jumlahTerpakai }} kelas tanpa peserta disembunyikan —
                        pilih <span class="text-ink">Semua kelas</span> untuk melihatnya.
                    @endif
                </p>
            </div>

            @forelse ($kelas as $k)
                @php
                    $bracket = $k->bracket;
                    $bisaSusun = $k->peserta_sah >= 2;
                @endphp

                <div class="flex flex-wrap items-center gap-4 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    {{-- 220px minimum di layar 360px menyisakan terlalu sedikit
                         untuk lajur aksi di sebelahnya; di bawah `sm` blok nama
                         kelas mengambil satu baris penuh sendiri. --}}
                    <div class="w-full min-w-0 flex-1 sm:min-w-[220px]">
                        <p class="font-medium text-ink">
                            {{ $k->jenis_kelamin->label() }} {{ $k->golongan_usia->label() }} — {{ $k->name }}
                        </p>
                        <p class="text-xs text-ink-muted">{{ $k->rentang() }}</p>
                    </div>

                    <span class="text-sm text-ink-muted">{{ $k->peserta_sah }} peserta sah</span>

                    @if ($bracket && $bracket->terkunci())
                        <x-si.badge varian="sukses">Terkunci · {{ $bracket->size }} tempat</x-si.badge>
                    @elseif ($bracket)
                        <x-si.badge varian="perhatian">Draf · {{ $bracket->size }} tempat</x-si.badge>
                    @else
                        <x-si.badge varian="netral">Belum disusun</x-si.badge>
                    @endif

                    <div class="flex w-full min-w-0 flex-wrap gap-1 sm:w-auto">
                        @if ($bracket)
                            <x-si.tombol :tautan="route('admin.turnamen.bagan.show', [$tournament, $k])" varian="kedua" ukuran="kecil">
                                Lihat
                            </x-si.tombol>
                        @else
                            {{--
                                Hanya penyusunan PERTAMA yang berdiri di sini.
                                Menyusun ulang berarti mengacak undian dari nol,
                                dan keputusan itu diambil setelah melihat
                                susunannya -- tombolnya ada di halaman bagan.
                            --}}
                            {{--
                                Mode dipilih DI SEBELAH tombolnya, bukan di
                                halaman setelan tersendiri.

                                Satu kejuaraan memakai keduanya di hari yang
                                sama -- pemasalan untuk usia dini, gugur untuk
                                dewasa -- jadi yang menyusun bagan memutuskan
                                per kelas, sambil melihat berapa peserta kelas
                                itu. Bawaannya gugur, bentuk yang dikenal
                                pembaca bagan.
                            --}}
                            @resource(rk('bagan', ResourceAction::Create))
                                {{-- Membungkus di layar sempit: label modenya
                                     panjang ("Gugur (bagan pangkat dua, sisanya
                                     bye)"), dan sebaris dengan tombol Susun ia
                                     menuntut 496px -- tombolnya terdorong ke
                                     luar layar ponsel. --}}
                                <form method="POST" action="{{ route('admin.turnamen.bagan.susun', [$tournament, $k]) }}"
                                      class="flex w-full min-w-0 flex-wrap items-center gap-2 sm:w-auto">
                                    @csrf
                                    <select name="mode" aria-label="Mode bagan {{ $k->name }}"
                                            class="h-9 min-w-0 flex-1 rounded-[var(--radius)] border border-line bg-surface px-2 text-[12.5px] text-ink sm:flex-none">
                                        {{-- Keterangan menempel di option-nya
                                             sendiri lewat title: bedanya kedua
                                             mode cuma terasa pada jumlah peserta
                                             tertentu, dan label sependek ini
                                             tidak muat menjelaskannya. --}}
                                        @foreach (App\Enums\ModeBagan::cases() as $mode)
                                            <option value="{{ $mode->value }}"
                                                    title="{{ $mode->keterangan() }}">{{ $mode->label() }}</option>
                                        @endforeach
                                    </select>

                                    <x-si.tombol tipe="submit" ukuran="kecil" :nonaktif="! $bisaSusun">
                                        Susun bagan
                                    </x-si.tombol>
                                </form>
                            @endresource
                        @endif
                    </div>
                </div>
            @empty
                @if ($cari !== '' || $tampil !== 'semua')
                    <x-si.kosong judul="Tidak ada kelas yang cocok"
                                 syarat="Belum ada kelas dengan peserta sah atau bagan. Ubah penyaring di atas untuk melihat seluruh kelas yang diturunkan dari naskah." />
                @else
                    <x-si.kosong judul="Belum ada kelas tanding"
                                 syarat="Kelas tanding diturunkan dari naskah peraturan saat kejuaraan dibuat." />
                @endif
            @endforelse
        </x-si.kartu>
    </div>
</x-layouts.admin>
