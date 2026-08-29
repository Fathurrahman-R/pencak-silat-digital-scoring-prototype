{{--
    Dokumentasi hidup lapisan komponen baru (`x-si.*`).

    Halaman ini bukan hiasan: ia satu-satunya tempat setiap komponen tampil
    dalam SELURUH keadaannya sekaligus, jadi kalau sebuah keadaan rusak ia
    ketahuan di sini alih-alih di gelanggang. Ia juga rujukan saat memindahkan
    layar dari `x-ui.*` ke `x-si.*`.

    Rujukan nilai dan alasannya: docs/BRIEF-DESAIN.md.
--}}
<x-layouts.docs title="Komponen si/*">
    <div class="mx-auto flex max-w-[900px] flex-col gap-8 px-6 py-10">

        <div class="flex flex-col gap-2 border-b border-line pb-6">
            <h1 class="text-[32px] leading-tight font-bold text-ink">Lapisan komponen si/*</h1>
            <p class="max-w-[70ch] text-[16px] leading-relaxed text-ink-secondary">
                Pengganti RizzxxUI. Tiap komponen tampil dalam seluruh keadaannya — kalau satu keadaan rusak, ia ketahuan di sini alih-alih di gelanggang.
            </p>
        </div>

        {{-- TOMBOL --}}
        <x-si.kartu judul="Tombol" keterangan="Aksi utama tidak memakai warna: merah, biru, dan emas sudah punya arti yang ditetapkan peraturan.">
            <div class="flex flex-col gap-6">
                @foreach ([
                    'utama' => 'Utama — satu per layar',
                    'kedua' => 'Kedua',
                    'polos' => 'Polos',
                    'bahaya' => 'Berbahaya',
                    'bahaya-tegas' => 'Berbahaya, sudah dikonfirmasi',
                ] as $varian => $keterangan)
                    <div class="flex flex-col gap-2">
                        <div class="text-[13px] font-semibold text-ink-muted">{{ $keterangan }}</div>
                        <div class="flex flex-wrap items-center gap-3">
                            <x-si.tombol :varian="$varian" type="button">Simpan</x-si.tombol>
                            <x-si.tombol :varian="$varian" type="button" ikon="check">Dengan ikon</x-si.tombol>
                            <x-si.tombol :varian="$varian" type="button" nonaktif>Nonaktif</x-si.tombol>
                        </div>
                    </div>
                @endforeach

                <div class="flex flex-col gap-2">
                    <div class="text-[13px] font-semibold text-ink-muted">Ukuran</div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-si.tombol ukuran="kecil" varian="kedua" type="button">Kecil</x-si.tombol>
                        <x-si.tombol ukuran="sedang" varian="kedua" type="button">Sedang · 44px</x-si.tombol>
                        <x-si.tombol ukuran="besar" varian="kedua" type="button">Besar</x-si.tombol>
                        <x-si.tombol ukuran="gelanggang" varian="utama" type="button">Gelanggang · 64px</x-si.tombol>
                    </div>
                </div>
            </div>
        </x-si.kartu>

        {{-- ISIAN --}}
        <x-si.kartu judul="Isian" keterangan="Tanda wajib berupa kata, bukan tanda bintang — bintang hanya dipahami orang yang sudah terbiasa mengisi formulir web.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-si.isian name="nama_kontingen" label="Nama kontingen" wajib
                            bantuan="Nama resmi yang tercetak di berita acara." />
                <x-si.isian name="berat" label="Berat badan" awalan="kg"
                            bantuan="Diisi petugas timbang, bukan official kontingen." />
                <x-si.isian name="nonaktif_contoh" label="Terkunci setelah disahkan" value="52.4" nonaktif />
                <x-si.isian name="contoh_galat" label="Isian yang ditolak"
                            bantuan="Keterangan bantu tetap tampil di bawah pesan galat." />
            </div>
        </x-si.kartu>

        {{-- CENTANG --}}
        <x-si.kartu judul="Kotak centang" keterangan="Yang bisa ditekan adalah seluruh baris, bukan kotak 22px-nya saja — kotak sekecil itu meleset terus di layar sentuh.">
            <div class="flex flex-col gap-1">
                <x-si.centang name="contoh_centang" label="Kirim salinan berita acara ke email official" />
                <x-si.centang name="contoh_centang_terisi" label="Sudah menerima jadwal" dicentang
                              bantuan="Keterangan bantu ikut jadi bagian sasaran sentuh." />
                <x-si.centang name="contoh_centang_mati" label="Terkunci setelah hasil disahkan" nonaktif />
            </div>
        </x-si.kartu>

        {{-- BADGE --}}
        <x-si.kartu judul="Badge status" keterangan="Selalu membawa kata dan ikon. Warna tidak pernah jadi satu-satunya pembawa makna.">
            <div class="flex flex-wrap items-center gap-3">
                <x-si.badge varian="sukses">Terverifikasi</x-si.badge>
                <x-si.badge varian="perhatian">Menunggu verifikasi</x-si.badge>
                <x-si.badge varian="bahaya">Ditolak</x-si.badge>
                <x-si.badge varian="netral">Belum diajukan</x-si.badge>
            </div>
        </x-si.kartu>

        {{-- CALLOUT --}}
        <x-si.kartu judul="Callout" keterangan="Dipakai menyatakan prasyarat dan akibat — kenapa sebuah tombol belum bisa ditekan.">
            <div class="flex flex-col gap-3">
                <x-si.callout judul="Bagan belum bisa disusun">
                    Tiga pesilat belum ditimbang. Selesaikan timbang badan lebih dulu, lalu tombol Susun bagan terbuka sendiri.
                </x-si.callout>
                <x-si.callout varian="perhatian" judul="Protes VAR lewat tenggat">
                    Wasit Komisi Protes belum memutus dalam 5 menit. Pasal 15 menyerahkan kelanjutannya ke verifikasi juri yang dipimpin Ketua Pertandingan.
                </x-si.callout>
                <x-si.callout varian="bahaya" judul="Hasil sudah disahkan">
                    Nilai dan hukuman partai ini tidak bisa diubah lagi. Koreksi hanya lewat protes manajer.
                </x-si.callout>
            </div>
        </x-si.kartu>

        {{-- TABEL --}}
        <x-si.kartu judul="Tabel"
                    keterangan="Padanan x-ui.table dengan API yang sepadan — memindahkan layar admin cukup dengan mengganti nama komponennya.">
            <x-si.tabel :headers="['Atlet', 'Kontingen', 'Kelas', 'Berat', '']">
                <x-slot:toolbar>
                    <x-si.tabel.toolbar placeholder="Cari nama atlet…" :tampil="3" :total="312" />
                </x-slot:toolbar>

                <x-si.tabel.baris>
                    <x-si.tabel.sel header>Bayu Pratama</x-si.tabel.sel>
                    <x-si.tabel.sel>Padepokan Harimau Putih</x-si.tabel.sel>
                    <x-si.tabel.sel>Putra Dewasa — Kelas B</x-si.tabel.sel>
                    <x-si.tabel.sel numeric>57,3</x-si.tabel.sel>
                    <x-si.tabel.sel align="right">
                        <x-si.badge varian="sukses">Lolos</x-si.badge>
                    </x-si.tabel.sel>
                </x-si.tabel.baris>

                <x-si.tabel.baris>
                    <x-si.tabel.sel header>Candra Setiawan</x-si.tabel.sel>
                    <x-si.tabel.sel>PSHT Cabang Pontianak</x-si.tabel.sel>
                    <x-si.tabel.sel>Putra Dewasa — Kelas B</x-si.tabel.sel>
                    <x-si.tabel.sel numeric>58,4</x-si.tabel.sel>
                    <x-si.tabel.sel align="right">
                        <x-si.badge varian="netral">Belum ditimbang</x-si.badge>
                    </x-si.tabel.sel>
                </x-si.tabel.baris>

                <x-si.tabel.baris>
                    <x-si.tabel.sel header>Ilham Nugraha</x-si.tabel.sel>
                    <x-si.tabel.sel>Merpati Putih Singkawang</x-si.tabel.sel>
                    <x-si.tabel.sel>Putra Remaja — Kelas A</x-si.tabel.sel>
                    <x-si.tabel.sel numeric>51,2</x-si.tabel.sel>
                    <x-si.tabel.sel align="right">
                        <x-si.badge varian="bahaya">Tidak lolos</x-si.badge>
                    </x-si.tabel.sel>
                </x-si.tabel.baris>

                <x-slot:footer>Halaman 1 dari 13</x-slot:footer>
            </x-si.tabel>
        </x-si.kartu>

        {{-- PESAN KILAT --}}
        <x-si.kartu judul="Pesan hasil tindakan"
                    keterangan="Tidak hilang sendiri. Pendahulunya menutup diri setelah 6 detik — cukup buat orang yang sudah tahu pesan apa yang ditunggunya, tidak cukup buat orang yang baru pertama memakai aplikasi.">
            {{-- session()->now() menaruh flash untuk permintaan ini saja, jadi
                 yang tampil di bawah adalah komponen sungguhan membaca session
                 sungguhan -- bukan tiruan markup yang bisa melenceng dari
                 aslinya begitu komponennya berubah. --}}
            @php
                session()->now('success', 'Hasil partai 35 disahkan. Bagan lanjut ke semifinal.');
            @endphp

            <p class="text-[14px] leading-relaxed text-ink-secondary">
                Pesannya muncul melayang di pojok kanan bawah halaman ini. Tutup lewat tombol silangnya;
                ia tidak akan pergi sendiri.
            </p>

            <x-si.pesan-kilat />
        </x-si.kartu>

        {{-- KEADAAN KOSONG --}}
        <x-si.kartu judul="Keadaan kosong" keterangan="Wajib menyebutkan apa yang membuka isinya, bukan sekadar “tidak ada data”.">
            <x-si.kosong judul="Belum ada medali"
                         syarat="Medali muncul setelah hasil partai atau penampilan Jurus disahkan Dewan Wasit Juri." />
        </x-si.kartu>

        {{-- KONFIRMASI --}}
        <x-si.kartu judul="Dialog konfirmasi" keterangan="Komponen paling menentukan apakah panitia berani memakai aplikasi. Batal di kiri; tombol berbahaya tidak pernah di posisi refleks.">
            <div class="flex flex-wrap items-center gap-3">
                <x-si.tombol varian="bahaya" type="button"
                             x-on:click="$dispatch('buka-konfirmasi-contoh-sahkan')">
                    Sahkan hasil partai
                </x-si.tombol>

                <x-si.tombol varian="bahaya" type="button"
                             x-on:click="$dispatch('buka-konfirmasi-contoh-hapus')">
                    Hapus kontingen
                </x-si.tombol>
            </div>

            <x-si.konfirmasi
                nama="contoh-sahkan"
                judul="Sahkan hasil Partai 14?"
                akibat="Setelah disahkan, nilai dan hukuman partai ini tidak bisa diubah lagi, dan hasilnya masuk ke bagan."
                label="Sahkan hasil"
                aksi="#" />

            <x-si.konfirmasi
                nama="contoh-hapus"
                judul="Hapus kontingen Padepokan Harimau Putih?"
                akibat="Seluruh 24 pesilat, pendaftaran, dan hasil timbang badannya ikut terhapus. Tindakan ini tidak bisa dibatalkan."
                ketik="Padepokan Harimau Putih"
                label="Hapus kontingen"
                metode="DELETE"
                aksi="#" />
        </x-si.kartu>

    </div>
</x-layouts.docs>
