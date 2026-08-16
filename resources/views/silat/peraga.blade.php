<x-layouts.silat title="Peraga Gelanggang">
    <div class="mx-auto max-w-5xl px-6 py-10">

        <header class="mb-10 border-b border-silat-garis pb-6">
            <p class="silat-angka text-[12px] tracking-[.12em] text-silat-teks-samar">DESIGN SYSTEM GELANGGANG</p>
            <h1 class="mt-1 text-2xl font-medium text-silat-teks">Papan skor, tombol juri, dan ikon aksi</h1>
            <p class="mt-2 max-w-2xl text-[14px] leading-relaxed text-silat-teks-redup">
                Lapisan terpisah dari RizzxxUI. Dipakai panel gelanggang, live score publik, dan
                overlay siaran. Nilai, hukuman, jumlah kolom, dan formasi juri di halaman ini
                dibaca dari <span class="silat-angka text-silat-teks">config/scoring.php</span>,
                bukan ditulis ulang — jadi kalau setelannya berubah, halaman ini ikut berubah.
            </p>
        </header>

        {{-- ── Ikon ──────────────────────────────────────────────────────── --}}
        <section class="mb-12">
            <h2 class="mb-1 text-[15px] font-medium text-silat-teks">Ikon aksi dan sanksi</h2>
            <p class="mb-5 text-[13px] text-silat-teks-redup">
                Enam piktogram siluet padat. Tidak ada ikon kuncian dan tidak ada ikon gabungan:
                naskah 2025 hanya mengenal nilai 1, 2, dan 3.
            </p>

            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                @foreach (['pukulan', 'tendangan', 'jatuhan', 'pembinaan', 'teguran', 'peringatan'] as $ikon)
                    <div class="flex flex-col items-center gap-2 rounded-silat bg-silat-panel p-4">
                        <x-silat.ikon :nama="$ikon" :ukuran="36" class="text-silat-teks" />
                        <span class="text-[12px] text-silat-teks-redup">{{ ucfirst($ikon) }}</span>
                    </div>
                @endforeach
            </div>

            <p class="mt-4 text-[13px] text-silat-teks-redup">Terbaca pada ukuran overlay dan layar gelanggang:</p>
            <div class="mt-3 flex items-end gap-6 rounded-silat bg-silat-panel p-4">
                @foreach ([20, 34, 56, 96] as $px)
                    <div class="flex flex-col items-center gap-2">
                        <x-silat.ikon nama="tendangan" :ukuran="$px" class="text-silat-teks" />
                        <span class="silat-angka text-[11px] text-silat-teks-samar">{{ $px }}px</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Tombol juri ───────────────────────────────────────────────── --}}
        <section class="mb-12">
            <h2 class="mb-1 text-[15px] font-medium text-silat-teks">Tombol nilai juri</h2>
            <p class="mb-5 text-[13px] text-silat-teks-redup">
                Target sentuh minimal 64px. Angka nilainya diambil dari setelan, jadi tombol tidak
                pernah menjanjikan nilai yang berbeda dari yang dihitung server. Tombol mematikan
                dirinya sendiri begitu WebSocket terputus.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['merah', 'biru'] as $sudut)
                    <div class="rounded-silat bg-silat-panel p-4">
                        <p class="mb-3 text-[12px] tracking-wide text-silat-teks-redup">Sudut {{ $sudut }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (['pukulan', 'tendangan', 'jatuhan'] as $jenis)
                                <x-silat.tombol-nilai :jenis="$jenis" :sudut="$sudut" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Hukuman ───────────────────────────────────────────────────── --}}
        <section class="mb-12">
            <h2 class="mb-1 text-[15px] font-medium text-silat-teks">Deret hukuman</h2>
            <p class="mb-5 text-[13px] text-silat-teks-redup">
                Pembinaan dua kolom dan sengaja paling redup — ia tidak mengurangi nilai. Teguran dua
                kolom; teguran ketiga tidak pernah muncul di sini karena langsung menjadi Peringatan I.
                Peringatan tiga kolom; kolom ketiga menyala berarti diskualifikasi.
            </p>

            <div class="space-y-2 rounded-silat bg-silat-panel p-4">
                <x-silat.baris-hukuman jenis="pembinaan" :terisi="1" />
                <x-silat.baris-hukuman jenis="teguran" :terisi="2" />
                <x-silat.baris-hukuman jenis="peringatan" :terisi="1" />
            </div>
        </section>

        {{-- ── Indikator juri ────────────────────────────────────────────── --}}
        <section class="mb-12">
            <h2 class="mb-1 text-[15px] font-medium text-silat-teks">Indikator juri</h2>
            <p class="mb-5 text-[13px] text-silat-teks-redup">
                Memperlihatkan mengapa sebuah nilai terbit atau tidak. Saat pelatih protes, inilah
                bagian yang ditunjuk.
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-silat bg-silat-panel p-4">
                    <p class="mb-3 text-center text-[12px] text-silat-teks-redup">Belum ada yang menekan</p>
                    <x-silat.indikator-juri :menekan="[]" sudut="merah" />
                </div>
                <div class="rounded-silat bg-silat-panel p-4">
                    <p class="mb-3 text-center text-[12px] text-silat-teks-redup">Satu juri — belum sah</p>
                    <x-silat.indikator-juri :menekan="[2]" sudut="merah" />
                </div>
                <div class="rounded-silat bg-silat-panel p-4">
                    <p class="mb-3 text-center text-[12px] text-silat-teks-redup">Dua juri — nilai terbit</p>
                    <x-silat.indikator-juri :menekan="[1, 3]" sudut="merah" />
                </div>
            </div>
        </section>

        {{-- ── Papan skor ────────────────────────────────────────────────── --}}
        <section class="mb-12">
            <h2 class="mb-1 text-[15px] font-medium text-silat-teks">Papan skor gelanggang</h2>
            <p class="mb-5 text-[13px] text-silat-teks-redup">
                Susunan konvensional: merah kiri, biru kanan, timer dan indikator juri di tengah.
            </p>

            <div class="overflow-hidden rounded-silat bg-silat-latar ring-1 ring-silat-garis">
                <div class="flex items-center justify-between border-b border-silat-garis px-4 py-2">
                    <span class="silat-angka text-[12px] text-silat-teks-samar">Kejuaraan Daerah 2026</span>
                    <span class="silat-angka text-[12px] text-silat-teks-samar">Gelanggang A</span>
                </div>

                <div class="grid grid-cols-[1fr_210px_1fr]">
                    <x-silat.blok-sudut
                        sudut="merah"
                        atlet="Andi Pratama"
                        kontingen="Jawa Barat"
                        :nilai="12"
                        :pembinaan="1"
                        :teguran="1"
                        :peringatan="0"
                    />

                    <div class="flex flex-col items-center justify-center gap-4 bg-silat-panel px-2 py-4">
                        <x-silat.timer :sisa-ms="84000" :babak="2" />
                        <x-silat.indikator-juri :menekan="[1, 3]" sudut="merah" />
                    </div>

                    <x-silat.blok-sudut
                        sudut="biru"
                        atlet="Budi Santosa"
                        kontingen="Kalimantan Barat"
                        :nilai="9"
                        :pembinaan="0"
                        :teguran="0"
                        :peringatan="1"
                    />
                </div>
            </div>
        </section>

        {{-- ── Overlay ───────────────────────────────────────────────────── --}}
        <section class="mb-6">
            <h2 class="mb-1 text-[15px] font-medium text-silat-teks">Scorebug overlay</h2>
            <p class="mb-5 text-[13px] text-silat-teks-redup">
                Versi ringkas untuk vMix. Latar di balik contoh ini sengaja diberi warna supaya
                terlihat bahwa hanya bilahnya yang buram — sisanya transparan.
            </p>

            <div class="rounded-silat bg-[#4a4f45] p-6">
                <div class="flex overflow-hidden rounded-silat">
                    <div class="flex flex-1 items-center gap-3 bg-silat-merah px-3 py-2">
                        <span class="text-[14px] font-medium text-silat-teks">Andi Pratama</span>
                        <x-silat.angka-skor :nilai="12" ukuran="overlay" class="ml-auto text-silat-teks" />
                    </div>

                    <div class="bg-silat-panel px-4 py-2">
                        <x-silat.timer :sisa-ms="84000" :babak="2" ukuran="overlay" />
                    </div>

                    <div class="flex flex-1 items-center gap-3 bg-silat-biru px-3 py-2">
                        <x-silat.angka-skor :nilai="9" ukuran="overlay" class="text-silat-teks" />
                        <span class="ml-auto text-[14px] font-medium text-silat-teks">Budi Santosa</span>
                    </div>
                </div>
            </div>
        </section>

    </div>
</x-layouts.silat>
