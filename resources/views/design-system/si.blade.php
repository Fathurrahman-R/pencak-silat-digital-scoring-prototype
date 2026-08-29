{{--
    Dokumentasi hidup lapisan komponen baru (`x-si.*`).

    Halaman ini bukan hiasan: ia satu-satunya tempat setiap komponen tampil
    dalam SELURUH keadaannya sekaligus, jadi kalau sebuah keadaan rusak ia
    ketahuan di sini alih-alih di gelanggang.

    GaleriKomponenSiTest menjaga dua hal: halamannya merender tanpa galat, dan
    setiap komponen di `components/si/` benar-benar dipanggil dari sini.
    Komponen baru yang tidak dipasang di galeri memerahkan uji — kecuali kalau
    alasannya ditulis eksplisit di daftar pengecualian uji itu.

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
                            <x-si.tombol :varian="$varian" tipe="button">Simpan</x-si.tombol>
                            <x-si.tombol :varian="$varian" tipe="button" ikon="check">Dengan ikon</x-si.tombol>
                            <x-si.tombol :varian="$varian" tipe="button" nonaktif>Nonaktif</x-si.tombol>
                        </div>
                    </div>
                @endforeach

                <div class="flex flex-col gap-2">
                    <div class="text-[13px] font-semibold text-ink-muted">Ukuran</div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-si.tombol ukuran="kecil" varian="kedua" tipe="button">Kecil</x-si.tombol>
                        <x-si.tombol ukuran="sedang" varian="kedua" tipe="button">Sedang · 44px</x-si.tombol>
                        <x-si.tombol ukuran="besar" varian="kedua" tipe="button">Besar</x-si.tombol>
                        <x-si.tombol ukuran="gelanggang" varian="utama" tipe="button">Gelanggang · 64px</x-si.tombol>
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
                    keterangan="Bisa dipilih, bisa diurutkan, dan tindakan borongannya hanya hidup saat ada yang tercentang.">
            <x-si.tabel :headers="['Atlet', 'Kontingen', 'Kelas', 'Berat', '']"
                        :selectable="[1, 2, 3]">
                <x-slot:toolbar>
                    <x-si.tabel.toolbar placeholder="Cari nama atlet…" :tampil="3" :total="312">
                        {{-- Tindakan borongan hanya hidup di dalam cakupan tabel
                             yang bisa dipilih: `selected` datang dari sana. --}}
                        <x-slot:bulk>
                            <x-si.hapus-borongan aksi="#" benda="atlet"
                                                 akibat="Pendaftaran, hasil timbang badan, dan berkas tiap atlet ikut terhapus. Official kontingen harus mengunggahnya ulang." />
                        </x-slot:bulk>
                    </x-si.tabel.toolbar>
                </x-slot:toolbar>

                <x-si.tabel.baris :id="1">
                    <x-si.tabel.sel header>Bayu Pratama</x-si.tabel.sel>
                    <x-si.tabel.sel>Padepokan Harimau Putih</x-si.tabel.sel>
                    <x-si.tabel.sel>Putra Dewasa — Kelas B</x-si.tabel.sel>
                    <x-si.tabel.sel numeric>57,3</x-si.tabel.sel>
                    <x-si.tabel.sel align="right">
                        <x-si.badge varian="sukses">Lolos</x-si.badge>
                    </x-si.tabel.sel>
                </x-si.tabel.baris>

                <x-si.tabel.baris :id="2">
                    <x-si.tabel.sel header>Candra Setiawan</x-si.tabel.sel>
                    <x-si.tabel.sel>PSHT Cabang Pontianak</x-si.tabel.sel>
                    <x-si.tabel.sel>Putra Dewasa — Kelas B</x-si.tabel.sel>
                    <x-si.tabel.sel numeric>58,4</x-si.tabel.sel>
                    <x-si.tabel.sel align="right">
                        <x-si.badge varian="netral">Belum ditimbang</x-si.badge>
                    </x-si.tabel.sel>
                </x-si.tabel.baris>

                <x-si.tabel.baris :id="3">
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
                <x-si.tombol varian="bahaya" tipe="button"
                             x-on:click="$dispatch('buka-konfirmasi-contoh-sahkan')">
                    Sahkan hasil partai
                </x-si.tombol>

                <x-si.tombol varian="bahaya" tipe="button"
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

        {{-- PILIHAN, SAKLAR, UNGGAH, ISIAN PANJANG --}}
        <x-si.kartu judul="Isian lain"
                    keterangan="Select mempertahankan bentuk bawaan peramban: di HP ia membuka pemilih layar penuh milik sistem, yang sasarannya lebih besar dan sudah dikenal.">
            <div class="flex flex-col gap-6">
                <x-si.pilihan name="contoh-golongan" label="Golongan usia" wajib
                              placeholder="Pilih golongan…"
                              :options="['dewasa' => 'Dewasa', 'remaja' => 'Remaja', 'pra-remaja' => 'Pra Remaja']"
                              bantuan="Menentukan lama babak dan tangga kelas berat." />

                <x-si.pilihan name="contoh-galat" label="Kelas" wajib
                              placeholder="Pilih kelas…"
                              :options="['a' => 'Kelas A', 'b' => 'Kelas B']" />

                <x-si.isian-panjang name="contoh-alasan" label="Alasan pembatalan" wajib baris="3"
                                    bantuan="Dibaca delegasi teknik dan tercatat di berita acara." />

                <x-si.unggah name="contoh-berkas" label="Akta kelahiran" wajib
                             accept="application/pdf,image/*"
                             bantuan="PDF atau foto, maksimal 4 MB." />

                <div class="flex flex-col gap-3 rounded-[var(--radius)] border border-line p-4">
                    <x-si.saklar name="contoh-aktif" label="Gelanggang aktif" dicentang
                                 bantuan="Gelanggang yang tidak aktif tidak muncul di daftar partai." />
                    <x-si.saklar name="contoh-mati" label="Terima pendaftaran susulan" />
                </div>
            </div>
        </x-si.kartu>

        {{-- ANGKA --}}
        <x-si.kartu judul="Angka"
                    keterangan="Digit tabular supaya sebaris kartu berjajar lurus: di font biasa angka 1 lebih sempit dari 8, dan kolom rupiah jadi bergoyang.">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-si.angka label="Total masuk" nilai="Rp 48.400.000" ikon="wallet" />
                <x-si.angka label="Tunggakan" nilai="Rp 3.200.000" />
                <x-si.angka label="Kontingen lunas" nilai="18" keterangan="dari 22 kontingen" />
                <x-si.angka label="Belum lunas" nilai="4" />
            </div>
        </x-si.kartu>

        {{-- PENYARING --}}
        <x-si.kartu judul="Penyaring"
                    keterangan="Yang sedang berlaku dibedakan warna DAN tebal huruf DAN garis tepi. Warna sendirian menghilang di proyektor gelanggang.">
            <x-si.saring param="contoh-status" semua="Semua status"
                         sekarang="lunas"
                         :pilihan="['draf' => 'Draf', 'menunggu' => 'Menunggu pembayaran', 'lunas' => 'Lunas']" />
        </x-si.kartu>

        {{-- TAB HALAMAN --}}
        <x-si.kartu judul="Tab antar halaman"
                    keterangan="Berpindah halaman, bukan menyembunyikan isi: masing-masing punya alamatnya sendiri dan bisa dibagikan ke official.">
            <x-si.tab-halaman :daftar="[
                'Atlet' => request()->url(),
                'Pendaftaran' => '#pendaftaran',
                'Tagihan' => '#tagihan',
            ]" />
        </x-si.kartu>

        {{-- FOTO DAN TITIK HADIR --}}
        <x-si.kartu judul="Foto dan keadaan akun"
                    keterangan="Titik keadaan tidak pernah jadi satu-satunya pembawa arti: katanya selalu ada untuk pembaca layar, dan di daftar keadaannya diulang sebagai badge berkata.">
            <div class="flex flex-wrap items-center gap-6">
                @foreach ([
                    ['aktif', 'Sedang aktif', 'Bayu Pratama'],
                    ['pergi', 'Sedang pergi', 'Sari Wulandari'],
                    ['mati', 'Tidak aktif', 'Joko Prasetyo'],
                ] as [$keadaan, $kata, $nama])
                    @php($contoh = new App\Models\User(['name' => $nama]))

                    <div class="flex items-center gap-3">
                        <span class="relative inline-flex">
                            <x-si.foto :user="$contoh" ukuran="sedang" />
                            <x-si.titik-hadir :keadaan="$keadaan" />
                        </span>

                        <div class="flex flex-col">
                            <span class="text-[14px] font-medium text-ink">{{ $nama }}</span>
                            <span class="text-[13px] text-ink-muted">{{ $kata }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-si.kartu>

        {{-- MODAL DAN PANEL RINCIAN --}}
        <x-si.kartu judul="Modal dan panel rincian"
                    keterangan="Modal hanya untuk hal yang butuh jawaban. Panel rincian mengambil isinya saat dibuka — satu untuk seluruh tabel, bukan satu per baris.">
            <div class="flex flex-wrap gap-3">
                <x-si.tombol tipe="button" varian="kedua"
                             x-on:click="$dispatch('modal-open', 'contoh-modal')">
                    Buka modal
                </x-si.tombol>

                <x-si.tombol tipe="button" varian="kedua"
                             x-on:click="$dispatch('panel-rincian-buka', '/tidak-ada')">
                    Buka panel rincian yang gagal dimuat
                </x-si.tombol>
            </div>

            <x-si.modal id="contoh-modal" judul="Ubah gelanggang" ukuran="kecil">
                <x-si.isian name="contoh-nama-gelanggang" label="Nama gelanggang" wajib />

                <x-slot:footer>
                    <x-si.tombol varian="kedua" tipe="button"
                                 x-on:click="$dispatch('modal-close', 'contoh-modal')">Batal</x-si.tombol>
                    <x-si.tombol tipe="button">Simpan</x-si.tombol>
                </x-slot:footer>
            </x-si.modal>

            <x-si.panel-rincian judul="Detail contoh" />
        </x-si.kartu>

        {{-- HAPUS BARIS --}}
        <x-si.kartu judul="Hapus satu baris"
                    keterangan="Satu dialog untuk seluruh halaman. Barisnya mengirim muatannya lewat data-*, bukan lewat JSON di dalam atribut — tanda kutipnya menutup atribut lebih awal.">
            <x-si.tombol tipe="button" varian="bahaya"
                         data-aksi="#"
                         data-nama="Bambang Sutrisno"
                         data-rincian="Ia bertugas sebagai wasit di 3 partai yang sudah dijadwalkan."
                         x-on:click="$dispatch('hapus-contoh', $el.dataset)">
                Hapus pengguna
            </x-si.tombol>

            <x-si.hapus-baris benda="contoh"
                              akibat="Akun ini kehilangan seluruh akses seketika, termasuk kalau sedang membuka panel gelanggang." />
        </x-si.kartu>

        {{-- MENU, TUTS, LINIMASA --}}
        <x-si.kartu judul="Menu, tuts, dan linimasa"
                    keterangan="Menu bertingkat lebih dari satu tidak dipakai: kalau butuh submenu, yang dibutuhkan sebenarnya halaman tersendiri.">
            <div class="flex flex-col gap-6">
                <div class="flex flex-wrap items-center gap-4">
                    <x-si.menu label="Tindakan">
                        <x-si.menu-butir tautan="#">
                            <x-si.ikon nama="pencil" />
                            Ubah kejuaraan
                        </x-si.menu-butir>
                        <x-si.menu-butir tautan="#" pintasan="⌘E">
                            <x-si.ikon nama="download" />
                            Ekspor peserta
                        </x-si.menu-butir>
                        <x-si.menu-butir bahaya>
                            <x-si.ikon nama="trash-2" />
                            Hapus kejuaraan
                        </x-si.menu-butir>
                    </x-si.menu>

                    <p class="text-[14px] text-ink-secondary">
                        Tekan <x-si.tuts>⌘K</x-si.tuts> untuk mencari halaman, <x-si.tuts>Esc</x-si.tuts> untuk menutup.
                    </p>
                </div>

                <x-si.linimasa :daftar="[
                    ['teks' => 'Bayu Pratama — menang angka · Putra Dewasa Kelas B', 'waktu' => '4 menit lalu'],
                    ['teks' => 'Candra Setiawan — menang mutlak · Putra Dewasa Kelas B', 'waktu' => '31 menit lalu'],
                    ['teks' => 'Tagihan INV-202608-00003 lunas', 'waktu' => '2 jam lalu'],
                ]" />
            </div>
        </x-si.kartu>

    </div>
</x-layouts.docs>
