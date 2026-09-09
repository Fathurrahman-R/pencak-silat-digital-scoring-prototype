# Panduan Operasional — Hari-H

> Untuk panitia. Langkah teknis instalasi ada di `docs/INSTALASI-LAN.md`; konfigurasi tunnel ada di `docs/TUNNELING.md`. Dokumen ini adalah urutan langkah dan siapa mengerjakan apa.
>
> Seluruh tahap pra-acara — membuat kejuaraan, tarif, pendaftaran, tagihan, verifikasi, timbang badan, bagan, jadwal — ada di [`PANDUAN-WORKFLOW.md`](PANDUAN-WORKFLOW.md), lengkap dengan syarat tiap tahap dan apa yang memblokir kalau tombolnya tidak jalan.

## H-1: Persiapan

1. **Ketua Tim Teknologi Informasi** memastikan server sudah terpasang (`docs/INSTALASI-LAN.md`) dan diuji dari HP di jaringan venue, bukan cuma dari mesin server.
2. **Operator IT** membuat akun juri massal per gelanggang lewat panel **Manajemen Akses → Pengguna**, kredensial pendek dan mudah diketik di HP (FR-A-04).

   Pastikan tiap gelanggang punya **Pengendali Gelanggang** (menu Pertandingan → Gelanggang → Pengendali). Gelanggang tanpa pengendali tidak bisa memulai babak sama sekali. Kalau baru memasang pembaruan ini, jalankan `php artisan silat:pindah-pengendali` — ia memberi peran itu kepada setiap Operator IT yang sudah memegang gelanggang, lalu melaporkan gelanggang yang masih kosong.
3. Pastikan bagan sudah **dikunci** (menu Bagan → Kunci) untuk setiap kelas yang akan bertanding -- setelah dikunci, penyusunan ulang wajib beralasan dan tercatat di jejak audit.
4. Pastikan jadwal partai sudah ditetapkan ke tiap gelanggang (menu Jadwal).
5. Cetak atau siapkan daftar kredensial juri per gelanggang untuk dibagikan pagi hari-H.

## Pagi hari-H: nyalakan sistem

1. Nyalakan **dua** proses server (lihat [`PANDUAN-SISTEM.md`](PANDUAN-SISTEM.md) §5): `.\scripts\server\jalankan-server.ps1` dan `php artisan reverb:start --host=0.0.0.0 --port=8080`, masing-masing di jendela PowerShell sendiri. Tambah proxy tunnel hanya kalau live score publik dipakai. **Bukan** `php artisan serve` — ia melayani satu permintaan pada satu waktu. **Bukan** `queue:listen` — tidak ada pekerjaan antrean di aplikasi ini.
2. **Operator IT** tiap gelanggang membuka panel Operator di laptop gelanggangnya masing-masing (`/admin/turnamen/{id}/partai/{match}/operator` untuk partai pertama).
3. Juri dan wasit login di HP masing-masing. **Yang bertugas di satu gelanggang mendarat langsung di panelnya** — tidak lewat dashboard sama sekali. Yang memegang dua gelanggang tetap melihat dashboard, karena sistem tidak punya dasar memilih salah satunya. Tambahkan panelnya ke layar utama (PWA): alamatnya per GELANGGANG, jadi ikonnya tidak pernah basi saat jadwal berganti.

   Pergantian partai dipegang **satu perangkat**: Pengendali Gelanggang. Begitu ia memindahkan jadwal, panel juri, wasit, dan dewan wasit juri ikut berpindah sendiri — tidak ada satu pun perangkat yang perlu disentuh.
4. Uji satu nilai percobaan sebelum partai pertama sungguhan dimulai -- indikator koneksi harus hijau di seluruh perangkat.

## Alur satu partai Tanding

| Langkah | Siapa | Di mana |
|---|---|---|
| Pilih partai aktif, mulai babak, pindah babak | **Pengendali Gelanggang** | Panel Kendali (`/gelanggang/{arena}/panel/kendali`) |
| Menjatuhkan pembinaan/teguran/peringatan, hitungan teknik | Wasit | Panel Wasit |
| Menilai serangan yang masuk | Juri 1–3 | PWA Juri |
| Mencatat nilai/hukuman yang terlewat di babak lalu | **Pengendali Gelanggang** | Panel Kendali → "Catat susulan babak N" |
| Mengakhiri partai (KO, WMP, mutlak, dst.) | **Pengendali Gelanggang** | Panel Kendali |
| Meninggalkan partai yang terlanjur dimulai dan tidak jadi dimainkan | **Pengendali Gelanggang** | Panel Kendali → pilih partai lain (atau "Kosongkan gelanggang"), lalu tombol **Pindah paksa** yang muncul di pesan penolakan |
| Menayangkan skor di gelanggang | Operator IT | Papan tampilan (`/gelanggang/{arena}/panel/papan`) |
| Meninjau riwayat, membatalkan nilai/hukuman keliru, **mengesahkan hasil** | Dewan Juri | Panel Dewan Juri |
| Mencetak berita acara | Ketua Pertandingan / Dewan Juri | Tombol "Berita acara (PDF)" di Panel Dewan Juri |

**Hasil partai belum final sebelum disahkan dewan juri.** `winner_registration_id` yang muncul sebelum pengesahan bersifat sementara dan masih bisa dikoreksi.

## Alur satu penampilan Jurus

| Langkah | Siapa | Di mana |
|---|---|---|
| Menetapkan format nomor (`battle` atau peringkat) | Sekretariat | Menu Kategori Jurus → pilih nomor → Format |
| Menyusun bagan gugur (format `battle`) | Operator IT | Menu Kategori Jurus → pilih nomor → Susun bagan; pohonnya di **Lihat pohon bagan**, bisa dicetak PDF |
| Buat penampilan dari pendaftaran terverifikasi (format peringkat) | Operator IT | Menu Kategori Jurus → pilih nomor → Buat penampilan |
| **Menjadwalkan ke gelanggang** | Sekretariat | Menu Jadwal → tab **Jurus**. Battle dijadwalkan berpasangan: kedua sudutnya masuk antrean gelanggang berurutan, biru lebih dulu |
| Menayangkan penampilan berikutnya | Pengendali Gelanggang | Panel Kendali → **Antrean Jurus** → Tayangkan |
| Mulai/hentikan timer penampilan | Operator IT | Panel Operator Jurus |
| Memberi nilai 9.00–10.00, mencatat pengurangan 0.01 | Juri Jurus | Panel Juri Jurus |
| Mencatat pengurangan 0.50, menetapkan diskualifikasi | Pengawas/Dewan Wasit Juri | Panel Operator Jurus (bagian Pengurangan) |
| **Mengesahkan skor akhir** | Ketua Pertandingan | Panel Operator Jurus |
| **Menetapkan pemenang battle** | Ketua Pertandingan | Panel Ketua, di bawah perbandingan nilainya |

**Nomor Jurus yang belum dijadwalkan ke gelanggang tidak muncul di panel mana pun.** Kolom gelanggang penampilan diisi dari tab Jurus di menu Jadwal; sebelum itu antrean Jurus di Panel Kendali kosong dan tombol Tayangkan tidak tergambar. Alamat lama per penampilan tetap hidup untuk kejuaraan kecil yang menjalankan Jurus tanpa membaginya ke matras.

**Satu gelanggang menayangkan satu hal.** Begitu Pengendali menayangkan penampilan Jurus, panel papan, juri, dan ketua di gelanggang itu berganti sendiri ke mode Jurus — alamatnya tidak berubah. Wasit, Dewan Wasit Juri, dan Komisi Protes tidak bertugas di Jurus; panelnya menampilkan layar tunggu yang menyebutkan sebabnya sampai gelanggang kembali menayangkan Tanding.

Pengesahan **ditolak sistem** kalau jumlah juri yang sudah menilai kurang dari setelan turnamen atau jumlahnya ganjil (Pasal 16.1.b) -- kecuali penampilan itu didiskualifikasi. Pengesahan juga menuntut penampilannya sudah **selesai**: timernya dijalankan lalu dihentikan.

Panel Jurus mengikuti siaran, sama seperti panel Tanding: nilai juri, timer, pengurangan, dan pengesahan muncul di panel lain dalam hitungan detik tanpa memuat ulang halaman. Halaman perbandingan battle ikut bergerak, jadi yang menekan "Tetapkan pemenang" tidak pernah membaca satu sisi yang basi.

**Perbandingan nilai kedua sudut muncul sendiri** di papan gelanggang, panel juri, panel operator, dan panel ketua begitu **kedua penampilan disahkan** — bukan begitu keduanya selesai tampil. Sebelum pengesahan angkanya masih bisa berubah oleh pengurangan Pengawas, dan angka yang berganti di depan penonton lebih menyesatkan daripada angka yang belum muncul. Skor akhir yang lebih tinggi menang; kalau **sama persis**, sistem berhenti dan Ketua Pertandingan yang memilih sudut, dengan alasan wajib yang ikut tercetak di berita acara. Kalau penanda koneksi di pojok panel menyala **Terputus**, yang terlihat di layar sedang berhenti diperbarui -- periksa jaringan gelanggang sebelum mengambil keputusan dari angkanya.

## Protes VAR dan Protes Manajer

1. Pelatih mengangkat kartu protes VAR di pinggir gelanggang (fisik, di luar sistem).
2. **Operator IT atau Ketua Pertandingan** memasukkan protes ke sistem lewat Panel Keberatan (`/admin/turnamen/{id}/partai/{match}/keberatan`), memilih sudut dan menuliskan kejadian yang disengketakan.
3. **Wasit Komisi Protes** meninjau dalam tenggat 5 menit yang ditampilkan sistem, lalu menetapkan Sah/Tidak Sah dari panel yang sama.
4. Lewat tenggat, sistem hanya menampilkan peringatan -- prosesnya dilanjutkan secara manual lewat verifikasi juri yang dipimpin Ketua Pertandingan (di luar sistem).
5. Protes Manajer (setelah hasil diumumkan) diajukan dan diputus dari panel yang sama, tingkat pertama oleh Ketua Pertandingan. Naskah menyerahkan banding kepada Delegasi Teknik bersama Tim Medis dan satu anggota eksekutif PB IPSI (Pasal 15 ayat 4 huruf c); sistem ini melebur Delegasi Teknik ke peran Ketua Pertandingan, jadi bandingnya diputus dari akun itu dan bersifat final.
6. Protes yang **diterima wajib memilih akibatnya** sebelum tombol Terima bisa berhasil: mengubah hasil, menambah satu babak (Tanding), atau penampilan kembali (Jurus). Pengesahan hasil tertahan sampai akibatnya dijalankan.

## Setup vMix Pro

> Alamat lengkapnya tidak perlu diketik manual. Buka menu **Pertandingan → Overlay Siaran**: seluruh alamat per gelanggang tercetak di sana lengkap dengan tombol salin dan pratinjau. Tabel di bawah hanya menjelaskan isi tiap halaman dan ke Overlay Channel mana ia dipasang.

Lima halaman overlay dipasang sebagai **Web Browser Input** terpisah (bukan satu input untuk semuanya), supaya bisa ditoggle sendiri-sendiri dari vMix:

| Overlay Channel | URL | Isi |
|---|---|---|
| 1 | `http://localhost:8000/overlay/scorebug/{arena_id}` | Skor, timer, babak |
| 2 | `http://localhost:8000/overlay/athlete/{arena_id}/red` dan `/blue` | Lower third nama & kontingen |
| 3 | `http://localhost:8000/overlay/breakdown/{arena_id}` | Rincian nilai & hukuman, kilat saat nilai baru |
| 4 | `http://localhost:8000/overlay/result/{arena_id}` | Papan hasil akhir partai |
| Input terpisah | `http://localhost:8000/overlay/bracket/{tournament_id}?kelas={weight_class_id}` | Bagan untuk tayangan antar partai |

Langkah di vMix: **Add Input → Web Browser → masukkan URL di atas → centang "Transparent Background"**. Resolusi Browser Input diset 1920×1080 mengikuti kanvas overlay.

`{arena_id}` tidak perlu dicari sendiri — menu **Pertandingan → Overlay Siaran** sudah menyusunkan alamatnya per gelanggang. Overlay otomatis mengikuti partai aktif gelanggang itu, jadi tidak perlu dipilih ulang tiap partai.

**Sebelum siaran dimulai**, uji checklist T6.8 di `docs/RENCANA.md` (transparansi latar, latensi dengan panel operator, kestabilan 3 jam, FPS output vMix) -- ini butuh vMix Pro sungguhan dan tidak bisa diuji dari lingkungan pengembangan.

## Live score publik (opsional, kalau tunnel dipasang)

Bagikan URL `https://<domain-tunnel>/live/turnamen/{tournament_id}` ke penonton. Halaman ini otomatis menampilkan daftar gelanggang, kelas tanding beserta juara, nomor Jurus, dan tautan ke rekap medali. Lihat `docs/TUNNELING.md` untuk konfigurasi proxy-nya.

**Matikan tunnel di tengah pertandingan tidak masalah** -- panel operator/wasit/juri/dewan juri semuanya berjalan di LAN dan tidak pernah memanggil URL tunnel.

## Setelah turnamen selesai

1. **Sekretaris Pertandingan / Ketua Pertandingan** membuka menu **Rekap & Laporan**, memeriksa peringkat umum dan juara tiap kelas/nomor.
2. Ekspor CSV medali, peserta, dan jadwal untuk arsip panitia.
3. Cetak berita acara tiap partai yang belum dicetak (dari Panel Dewan Juri masing-masing partai).
