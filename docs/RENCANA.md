# Digital Scoring Pencak Silat — Rencana & Progres

> Dikonversi dari plan mode (`grill-me-apakah-kamu-memiliki-sprightly-kettle.md`) pada 2026-08-16 untuk ditrack progresnya. Checkbox mencerminkan status pengerjaan terkini, bukan lagi rencana murni.

## Status Ringkas

| Fase | Status |
|---|---|
| Fase 0 — Adaptasi Boilerplate | ✅ Selesai |
| Fase 0b — Design System Gelanggang | ✅ Selesai |
| Fase 1 — Manajemen Turnamen | ✅ Selesai |
| Fase 2 — Pendaftaran & Timbang Badan | ✅ Selesai |
| Fase 2b — Biaya, Invoice & Pembayaran | 🟡 Selesai kecuali integrasi Midtrans (menunggu kredensial sandbox) |
| Fase 3 — Bagan & Jadwal | ✅ Selesai |
| Fase 4 — Mesin Scoring Tanding | 🟡 Engine + broadcasting + HTTP + panel operator/wasit/dewan-juri selesai; PWA juri belum |
| Fase 4b — VAR & Keberatan | ⬜ Belum dimulai |
| Fase 5 — Live Score Publik & Tunneling | ⬜ Belum dimulai |
| Fase 6 — Overlay Siaran vMix | ⬜ Belum dimulai |
| Fase 7 — Kategori Jurus | ⬜ Belum dimulai |
| Fase 8 — Rekap, Laporan, Dokumen | ⬜ Belum dimulai |

Selain itu, ada pekerjaan **di luar urutan fase asli** yang sudah dikerjakan atas permintaan langsung: refactor navigasi ke sidebar, perbaikan komponen UI (design system compliance), dan pembersihan modul contoh boilerplate (`Post`).

---

## Context

Folder `D:\digiscoring-prototype` awalnya kosong — pembangunan dari nol di atas boilerplate.

**Masalah.** Pencatatan skor pertandingan pencak silat masih manual: papan skor tulis tangan, rekap kertas, penjumlahan di kepala panitia. Rawan salah hitung, lambat berpindah partai, sulit dipertanggungjawabkan saat ada protes, penonton di luar gelanggang tidak punya cara memantau.

**Hasil yang dituju.** Satu sistem yang menjalankan alur turnamen dari pendaftaran kontingen sampai rekap medali, dengan mesin scoring realtime yang meniru mekanisme konsensus juri resmi, halaman publik lewat internet, dan overlay siaran untuk vMix Pro.

**Keputusan yang sudah dikonfirmasi:**

| Dimensi | Keputusan |
|---|---|
| Cakupan | Full: Tanding + Seni + manajemen turnamen lengkap |
| Konteks | Prototype pengembangan, wajib punya halaman publik live score |
| Fondasi kode | Bukan dari nol. Memakai boilerplate `Fathurrahman-R/boilerplate` (branch `main`) |
| Stack | Laravel 13, PHP 8.3+, MySQL 8, Blade + Alpine 3, Tailwind 4, Pest 5 |
| Design system | Admin memakai RizzxxUI apa adanya. Panel gelanggang, halaman publik, dan overlay memakai lapisan baru bergaya papan skor konvensional |
| Jaringan | LAN lokal offline di gelanggang; live score publik diekspos lewat tunneling/port forwarding |
| Formasi juri | Konfigurabel per turnamen (jumlah juri, ambang sepakat, lebar window) |
| Input juri | HP/tablet lewat browser (PWA) |
| Manajemen turnamen | Lengkap — pendaftaran online kontingen, timbang badan, verifikasi, bagan, jadwal, rekap medali |
| Biaya & pembayaran | Penyelenggara mengatur tarif; kontingen menerima invoice; dibayar lewat Midtrans. Verifikasi pendaftaran terkunci sampai lunas |
| Siaran | vMix Pro. Overlay: scorebug, lower third atlet, rincian nilai & hukuman, papan hasil & bagan |
| Kendali overlay | Sepenuhnya di operator vMix — aplikasi tidak menyediakan panel kontrol siaran |
| Topologi vMix | vMix Pro dan server Laravel di mesin yang sama (localhost) |
| Deliverable | Kode + dokumen teknis (README, ERD, diagram arsitektur, panduan instalasi & operasional) |

Sumber kebenaran aturan pertandingan: naskah **Peraturan Pertandingan Pencak Silat Nasional Tahun 2025** (SK Ketua Umum PB IPSI Skep-70/III/2025), folder `document/`.

---
---

# BAGIAN 1 — PRD (Product Requirements Document)

## 1.1 Ringkasan Produk

Aplikasi web yang menjadi satu-satunya sumber kebenaran untuk penyelenggaraan turnamen pencak silat: data peserta, jadwal, bagan, penilaian pertandingan secara realtime, penayangan skor ke publik dan ke siaran.

Sistem berjalan di server lokal di dalam venue tanpa bergantung pada internet. Internet hanya dibutuhkan untuk satu hal: menerbitkan halaman live score ke publik lewat tunnel, dan itu pun tidak boleh mematikan pertandingan kalau putus.

## 1.2 Tujuan

| # | Tujuan | Ukuran keberhasilan |
|---|---|---|
| G-1 | Menghapus penjumlahan skor manual | Skor akhir partai dihitung sistem, panitia tidak menjumlah apa pun |
| G-2 | Meniru mekanisme konsensus juri resmi | Nilai hanya terbit bila ambang juri sepakat tercapai dalam window waktu |
| G-3 | Skor terlihat serentak di semua layar | Selisih tampil operator, publik, dan overlay siaran < 300 ms |
| G-4 | Setiap keputusan bisa ditelusuri | Setiap nilai bisa dilacak ke input juri mana, pada milidetik ke berapa |
| G-5 | Turnamen berjalan tanpa internet | Seluruh fungsi pertandingan jalan penuh saat WAN mati |
| G-6 | Siap siar | Operator vMix bisa menayangkan skor tanpa mengetik ulang apa pun |

## 1.3 Non-Tujuan (di luar cakupan)

- Bukan sistem multi-tenant/SaaS berlangganan.
- Tidak menangani penjualan tiket maupun akreditasi. (Biaya pendaftaran kontingen **termasuk cakupan** — lihat bagian K.)
- Tidak memproses pengembalian dana. Pembatalan hanya dicatat.
- Tidak menyimpan data kartu pembayaran dalam bentuk apa pun.
- Tidak menggantikan software siaran — aplikasi hanya memasok overlay, produksi tetap di vMix.
- Tidak ada aplikasi native Android/iOS. Juri memakai browser.
- Tidak ada integrasi perangkat keras tombol juri pada tahap ini (tapi lapisan input dirancang agar tidak menghalangi penambahannya nanti).

## 1.4 Persona & Peran

| Peran | Kebutuhan utama | Perangkat |
|---|---|---|
| Admin panitia | Menyiapkan turnamen, kelas, gelanggang, jadwal, bagan; kelola user | Laptop |
| Official kontingen | Mendaftarkan atlet, unggah berkas, pantau tagihan, bayar, pantau status verifikasi | Laptop/HP |
| Bendahara panitia | Atur tarif, pantau tagihan masuk, tandai pembayaran manual, rekonsiliasi | Laptop |
| Petugas timbang | Catat berat badan, validasi kelas, gugurkan yang tidak memenuhi | Laptop/tablet |
| Operator IT | Pilih partai aktif, jalankan/hentikan timer, catat hukuman atas perintah wasit | Laptop |
| Wasit | Menjatuhkan pembinaan/teguran/peringatan, hitungan teknik, menghentikan pertandingan | Tablet (atau lewat Operator IT) |
| Juri 1–3 (Tanding) | Menekan tombol nilai secepat mungkin, tahu inputnya masuk | HP/tablet |
| Juri Jurus (min. 4, genap) | Memberi nilai skala 9.00–10.00 dan mencatat pengurangan 0.01 | Tablet |
| Ketua Pertandingan | Mengatur jalannya pertandingan, verifikasi juri, mengumumkan hasil | Laptop |
| Pengawas/Dewan Wasit Juri | Evaluasi penilaian juri, pengurangan 0.50 pada kategori Jurus, ikut memutus VAR | Laptop |
| Wasit Komisi Protes | Memutus protes VAR dalam tenggat 5 menit | Laptop |
| Delegasi Teknik | Memutus banding, keputusannya final | Laptop |
| Operator vMix | Menayangkan overlay, toggle sendiri dari vMix | PC vMix (mesin server) |
| Publik | Lihat skor berjalan, jadwal, bagan, rekap medali | HP, lewat internet |

## 1.5 Aturan Domain Pencak Silat

**Sumber: Peraturan Pertandingan Pencak Silat Nasional Tahun 2025**, SK Ketua Umum PB IPSI Nomor Skep-70/III/2025, berkas `document/PERATURAN PERATNDINGAN PENCAK SILAT TERBARU 2025 FINAL.pdf` (61 halaman) di dalam repo.

### 1.5.1 Kategori Tanding — Pasal 11 (Remaja, Dewasa, Master)

**Babak dan waktu**

| Golongan | Babak | Durasi per babak |
|---|---|---|
| Remaja, Dewasa | 3 | 2 menit bersih |
| Master 1 | 2 | 1,5 menit bersih |
| Master 2 | 2 | 1 menit bersih |
| Pra Remaja | 3 | 2 menit bersih |
| Usia Dini 1, Usia Dini 2 | 2 | 1,5 menit bersih |

Istirahat antar babak 1 menit. Waktu berhenti saat wasit menghentikan pertandingan dan saat hitungan terhadap pesilat yang jatuh.

**Nilai prestasi teknik — hanya tiga**

- `1` serangan tangan yang masuk sasaran sah dan bernilai, bertenaga, tanpa terhalang tangkisan, berkaidah
- `2` serangan kaki dengan syarat yang sama
- `3` teknik jatuhan yang berhasil menjatuhkan lawan lewat tangkapan, sapuan, ungkitan, kaitan, guntingan, atau serangan balik jatuhan dalam 2 detik

**Tidak ada nilai 4, dan tidak ada nilai gabungan `1+1`, `1+2`, `1+3`.** Keduanya berasal dari edisi lama dan tidak muncul di naskah 2025.

Serangan tangan sejenis beruntun tanpa jeda dihitung `1`. Serangan kaki sejenis beruntun tanpa menyentuh matras dihitung `2`.

**Sasaran** — dada, perut dari pusar ke atas, rusuk kiri dan kanan, punggung. Tungkai dan lengan boleh jadi sasaran antara tetapi tidak bernilai.

**Pelanggaran — tiga tingkat, bukan dua:** Ringan, **Sedang**, Berat.

**Hukuman — empat tahap berurutan (Pasal 11.6.d.4)**

1. **Pembinaan** — untuk pelanggaran ringan. Belum ada pemotongan nilai. **Berlaku akumulatif tanpa membedakan jenis pelanggaran.**
2. **Teguran** — diberikan bila pelanggaran ringan terjadi **setelah 2 kali Pembinaan**, atau **langsung** bila pelanggaran sedang. Teguran I `−1`, Teguran II `−2`.
3. **Peringatan** — **berlaku untuk seluruh babak**. Peringatan I `−5` diberikan bila pelanggaran berat, atau teguran ketiga, atau setelah teguran kedua akibat pelanggaran ringan. Peringatan II `−10`. **Peringatan III ada**, diberikan bila kembali mendapat peringatan setelah Peringatan II, dan langsung berarti diskualifikasi — harus dinyatakan wasit. Setelah Peringatan I maupun II, pembinaan atas pelanggaran ringan masih boleh diberikan.
4. **Diskualifikasi** — juga berlaku untuk pelanggaran berat yang disengaja, pelanggaran yang membuat lawan cedera dan tidak bisa melanjutkan, serta menancapkan kepala lawan ke matras.

**Penentuan kemenangan (Pasal 11.6.g)**

- **Menang angka.** Bila seri, urutan pemecahnya: hukuman terendah → nilai prestasi teknik tertinggi terbanyak (urutan 3, 2, 1) → tambah satu babak → berat badan lebih ringan → undian oleh Ketua Pertandingan.
- **Menang teknik.** Termasuk bila pesilat mendapat hitungan tiga kali berturut-turut dalam satu babak. Hitungan yang mencapai 9 disusul Teguran I bagi yang dihitung. Dokter pertandingan punya 120 detik memutuskan fit atau tidak fit.
- **Menang mutlak.** Lawan tidak dapat berdiri dengan sikap pasang setelah hitungan ke-10.
- **Menang WMP.** Pertandingan tidak seimbang, atau **selisih nilai 30** pada babak II atau III. Khusus Usia Dini 1 dan 2, selisih **20** poin.
- **Menang undur diri.** Lawan tidak muncul setelah tiga panggilan berinterval 30 detik.
- **Menang diskualifikasi.**

**Aparat (Pasal 16.1.a)** — kategori Tanding: **1 Wasit dan 3 Juri** per gelanggang.

#### 1.5.1.1 Yang tidak diatur naskah: mekanisme keabsahan nilai

Naskah 2025 **tidak mengatur** berapa juri harus sepakat maupun selebar apa window waktunya. Yang diatur hanya jumlah juri, yaitu tiga. Jadi ambang dan window adalah **keputusan implementasi, bukan angka peraturan**.

Dengan tiga juri, ambang wajar adalah **2 dari 3**. Keduanya tetap jadi setelan turnamen.

Naskah mengakui keberadaan sistem digital secara resmi. Susunan aparat mencantumkan **Ketua Tim Teknologi Informasi** dan **Operator IT**, dengan tugas memastikan "perangkat digital score berjalan dengan baik, termasuk jaringan, server, dan perangkat lainnya". Daftar perlengkapan gelanggang wajib memuat **Laptop Scoring Digital**, Layar dan Proyektor, Lampu Babak, Lampu Hasil, Gong manual atau otomatis, Kamera, dan **Meja VAR**.

### 1.5.2 Kategori Jurus — Pasal 12

Naskah 2025 memakai istilah **Jurus**, bukan Seni. Nomor yang dipertandingkan: Jurus Tunggal, Jurus Tunggal Bebas, Jurus Ganda, Jurus Regu (Regu A jurus 1–6, Regu B jurus 7–12), dan Solo Kreatif.

**Cara penilaian — median, bukan buang tertinggi-terendah**

- Skala nilai **9.00 hingga 10.00**.
- Sistem menghitung **nilai median dari semua juri** — naskah menyebut median dari 6 juri.
- Juri kategori Jurus **minimal 4 orang dan harus genap** (Pasal 16.1.b).

**Hukuman**

- Pengurangan **0.01** oleh juri: kesalahan rincian gerak, kesalahan urutan, gerakan tertinggal, senjata terlepas tetapi tidak menyentuh matras.
- Pengurangan **0.50** oleh Pengawas/Dewan Wasit Juri: waktu melebihi atau kurang dari toleransi antara 5 sampai 10 detik, keluar gelanggang 10×10 m, senjata jatuh menyentuh lantai, pakaian tidak sesuai, menahan gerakan lebih dari 5 detik.
- **Diskualifikasi ditunjukkan dengan skor 0,00.**

**Pemecah seri** — nilai hukuman lebih rendah → waktu penampilan terdekat ke waktu acuan → **standar deviasi lebih rendah** → undian.

**Waktu penampilan Jurus Tunggal** — penyisihan 1 menit 20 detik, semifinal senjata 1 menit 40 detik, final lengkap 3 menit. Toleransi 5 detik untuk Remaja dan Dewasa, 10 detik untuk Usia Dini dan Pra Remaja.

### 1.5.3 VAR dan pengajuan keberatan — Pasal 15

- **Kategori Tanding:** pelatih menerima **2 Kartu Protes** per pertandingan, berlaku sepanjang tiga babak. Protes diajukan dengan mengangkat kartu untuk meminta tinjauan video atas keputusan wasit soal pelanggaran atau jatuhan.
- **Kategori Jurus:** pelatih menerima **1 Kartu Protes** per penampilan, diajukan setelah penampilan selesai dan sebelum pemenang diumumkan, terbatas pada dua hal: penampilan tidak sesuai deskripsi, atau pesilat/senjata keluar gelanggang.
- Keputusan VAR diambil Wasit Komisi Protes dibantu Pengawas/Dewan Wasit Juri dan Wasit, **maksimal 5 menit**. Lewat dari itu, prosesnya dilanjutkan dengan verifikasi juri yang dipimpin Ketua Pertandingan.
- Hasil ditetapkan dengan mengangkat kartu **Sah** atau **Tidak Sah**.
- **Protes Manajer** berjenjang: tingkat pertama ke Ketua Pertandingan (formulir dalam 10 menit, dikembalikan dalam 20 menit, diputus dalam 2 jam), lalu banding ke Delegasi Teknik dengan tenggat serupa. Keputusan banding bersifat final.

### 1.5.4 Aparat pertandingan resmi — Pasal 13

**Tenaga Teknis** — Delegasi Teknik, Asisten Delegasi Teknik, Dewan Hakim, Ketua Pertandingan, Pengawas/Dewan Wasit Juri, Wasit Juri, Wasit Komisi Protes, Dokter Pertandingan, **Ketua Tim Teknologi Informasi**.

**Petugas Teknis** — Sekretaris Pertandingan, Pengamat Waktu, **Operator IT**, Announcer, Petugas Medis, Petugas Lapangan, Petugas Timbang Badan.

## 1.6 Functional Requirements

### A. Autentikasi & Peran

| ID | Requirement |
|---|---|
| FR-A-01 | Login dengan email/username + password. Sesi kedaluwarsa dapat dikonfigurasi. |
| FR-A-02 | Sistem peran berbasis permission: admin, official kontingen, petugas timbang, operator gelanggang, wasit, juri, dewan juri. |
| FR-A-03 | Satu user dapat memegang lebih dari satu peran (lazim di turnamen kecil). |
| FR-A-04 | Akun juri dapat dibuat massal per turnamen dengan kredensial ringkas yang mudah diketik di HP. |
| FR-A-05 | Seluruh tindakan yang mengubah skor, hukuman, atau hasil tercatat di `audit_logs` beserta pelakunya. |

### B. Manajemen Turnamen

| ID | Requirement |
|---|---|
| FR-B-01 | CRUD turnamen: nama, penyelenggara, tanggal, tempat, status (draf/berjalan/selesai). |
| FR-B-02 | CRUD gelanggang (arena) per turnamen. Mendukung banyak gelanggang berjalan bersamaan. |
| FR-B-03 | Konfigurasi peraturan per turnamen: jumlah juri, ambang sepakat, lebar window konsensus (ms), jumlah babak, durasi babak, durasi istirahat, jenis nilai yang aktif beserta besarannya, besaran tiap hukuman, cakupan tiap sanksi (per babak / per partai), dan tingkat peringatan yang berujung diskualifikasi. |
| FR-B-03a | Bawaan mengikuti naskah 2025: kategori Tanding 1 wasit dan **3 juri**, kategori Jurus minimal 4 juri dan wajib genap. Ambang sepakat dan lebar window tetap dapat diatur; bawaannya 2 dari 3 juri. |
| FR-B-04 | CRUD golongan usia (usia dini, pra-remaja, remaja, dewasa) dan kelas tanding (A–J, putra/putri) dengan rentang berat badan. |
| FR-B-05 | CRUD nomor seni: tunggal/ganda/regu, putra/putri, per golongan usia. |

### C. Pendaftaran Peserta

| ID | Requirement |
|---|---|
| FR-C-01 | Official kontingen mendaftar/dibuatkan akun, lalu mengelola data kontingennya sendiri. |
| FR-C-02 | Official mendaftarkan atlet: nama, jenis kelamin, tanggal lahir, berat badan klaim, foto. |
| FR-C-03 | Official mendaftarkan atlet ke kelas tanding atau nomor seni tertentu. Sistem menolak bila jenis kelamin/golongan usia tidak cocok. |
| FR-C-04 | Unggah berkas persyaratan (akta, surat sehat, kartu pelajar) dengan batas ukuran dan tipe file. |
| FR-C-05 | Panitia memverifikasi/menolak pendaftaran dengan alasan yang tercatat. |
| FR-C-06 | Official melihat status verifikasi tiap atletnya. |
| FR-C-07 | Verifikasi pendaftaran **tidak dapat diproses** sebelum invoice kontingen berstatus lunas (lihat bagian K). |

### D. Timbang Badan

| ID | Requirement |
|---|---|
| FR-D-01 | Petugas timbang mencari atlet, mencatat berat badan aktual dan waktu penimbangan. |
| FR-D-02 | Sistem otomatis menandai lolos/tidak lolos terhadap rentang berat kelas yang didaftarkan. |
| FR-D-03 | Atlet yang tidak lolos ditandai gugur dan otomatis dikeluarkan dari bagan (lawannya menang WO). |
| FR-D-04 | Riwayat penimbangan tersimpan, termasuk penimbangan ulang. |

### E. Bagan & Jadwal

| ID | Requirement |
|---|---|
| FR-E-01 | Generator bagan gugur tunggal per kelas, menangani jumlah peserta bukan pangkat dua dengan bye. |
| FR-E-02 | Undian/drawing dapat diacak atau diatur manual (drag antar slot) sebelum bagan dikunci. |
| FR-E-03 | Bagan dikunci setelah disahkan; perubahan setelah itu wajib beralasan dan tercatat. |
| FR-E-04 | Pemenang partai otomatis naik ke slot berikutnya. |
| FR-E-05 | Penjadwalan partai ke gelanggang dan urutan tayang; partai dapat digeser antar gelanggang. |
| FR-E-06 | Penugasan petugas per partai: juri 1..N, wasit, dewan juri. |

### F. Mesin Scoring Tanding — inti sistem

| ID | Requirement |
|---|---|
| FR-F-01 | Operator memilih partai aktif untuk gelanggangnya; hanya satu partai aktif per gelanggang. |
| FR-F-02 | Timer babak dikendalikan server (mulai/jeda/lanjut/reset/babak berikutnya). Waktu resmi adalah waktu server, bukan jam perangkat mana pun. |
| FR-F-03 | Juri membuka papan tombol PWA: tombol besar per sudut dan per jenis nilai, dirancang untuk ditekan cepat tanpa melihat lama. |
| FR-F-04 | Setiap penekanan tombol dikirim ke server, diberi stempel waktu server, dan disimpan mentah ke `judge_inputs`. Baris ini tidak pernah diubah atau dihapus. |
| FR-F-05 | Nilai terbit bila jumlah **juri berbeda** yang menekan kombinasi (sudut, jenis nilai) yang sama mencapai ambang, dalam rentang window. Evaluasi dilakukan saat input tiba, bukan oleh timer latar. |
| FR-F-06 | Satu input juri hanya boleh ikut membentuk satu nilai (anti hitung ganda). |
| FR-F-07 | Input yang tiba saat timer tidak berjalan ditolak dan dicatat alasannya. |
| FR-F-08 | Wasit/operator menjatuhkan pembinaan, teguran, dan peringatan. Tiap sanksi dicatat beserta tingkat pelanggaran penyebabnya: ringan, sedang, atau berat. |
| FR-F-08a | **Pembinaan** tidak mengurangi nilai dan bersifat akumulatif tanpa membedakan jenis pelanggaran. Setelah dua kali pembinaan, pelanggaran ringan berikutnya wajib naik menjadi Teguran. |
| FR-F-08b | Pelanggaran **sedang** menghasilkan Teguran secara langsung tanpa melewati pembinaan. Pelanggaran **berat** menghasilkan Peringatan I secara langsung. |
| FR-F-08c | Teguran ketiga, atau teguran berikutnya setelah Teguran II dalam babak yang sama, tidak dapat dijatuhkan sebagai teguran — sistem menaikkannya menjadi Peringatan I. Aturan ini dipaksakan di sisi server. |
| FR-F-08d | Peringatan **berlaku untuk seluruh babak** dan tidak pernah mereset. Setelah Peringatan I maupun II, pembinaan atas pelanggaran ringan tetap boleh diberikan. |
| FR-F-09 | Peringatan III berarti diskualifikasi dan otomatis mengakhiri partai. Diskualifikasi juga berlaku untuk pelanggaran berat yang disengaja, pelanggaran yang membuat lawan cedera dan tidak dapat melanjutkan, serta menancapkan kepala lawan ke matras. |
| FR-F-09a | Hitungan teknik: hitungan yang mencapai 9 disusul Teguran I bagi pesilat yang dihitung. Tiga kali hitungan berturut-turut dalam satu babak membuat lawan menang teknik. Hitungan sampai 10 tanpa bangkit berarti menang mutlak. |
| FR-F-09b | Menang WMP otomatis ditawarkan ke operator bila selisih nilai mencapai 30 pada babak II atau III, atau 20 untuk golongan Usia Dini. Ambangnya konfigurabel. |
| FR-F-11a | Pemecah seri pada menang angka dijalankan berurutan: hukuman terendah, lalu nilai prestasi teknik tertinggi terbanyak dengan urutan 3-2-1, lalu tambah satu babak, lalu berat badan lebih ringan, lalu undian. |
| FR-F-10 | Operator dapat mengakhiri partai dengan sebab khusus: KO, TKO, WMP, mutlak, undur diri, cedera, WO. |
| FR-F-11 | Sistem menghitung skor per babak dan pemenang; hasil belum sah sebelum disahkan dewan juri. |
| FR-F-12 | Dewan juri dapat mengoreksi nilai/hukuman. Koreksi dilakukan dengan membuat baris pembatal, **bukan** menyunting riwayat, dan wajib disertai alasan. |
| FR-F-13 | Juri melihat indikator status koneksi. Saat terputus, tombol dinonaktifkan dan indikator merah besar muncul. |
| FR-F-14 | Setiap panel (juri, wasit, operator, dewan juri) memuat ulang state penuh saat tersambung kembali. |

### G. Kategori Jurus

| ID | Requirement |
|---|---|
| FR-G-01 | Nomor yang didukung: Jurus Tunggal, Jurus Tunggal Bebas, Jurus Ganda, Jurus Regu (A dan B), Solo Kreatif. |
| FR-G-02 | Operator menjalankan timer penampilan dengan waktu acuan dan toleransi sesuai nomor dan golongan usia. |
| FR-G-03 | Tiap juri memberi nilai pada **skala 9.00–10.00**. Jumlah juri minimal 4 dan **wajib genap**. |
| FR-G-04 | Nilai akhir adalah **median dari nilai seluruh juri**, bukan penjumlahan setelah membuang nilai tertinggi dan terendah. |
| FR-G-05 | Pengurangan **0.01** dicatat oleh juri untuk kesalahan rincian gerak, kesalahan urutan, gerakan tertinggal, dan senjata terlepas tanpa menyentuh matras. |
| FR-G-06 | Pengurangan **0.50** dicatat oleh Pengawas/Dewan Wasit Juri untuk pelanggaran waktu 5–10 detik, keluar gelanggang, senjata jatuh menyentuh lantai, pakaian tidak sesuai, dan menahan gerakan lebih dari 5 detik. |
| FR-G-07 | Diskualifikasi ditampilkan sebagai skor `0,00`. |
| FR-G-08 | Pemecah seri berurutan: nilai hukuman lebih rendah, lalu waktu penampilan terdekat ke waktu acuan, lalu **standar deviasi lebih rendah**, lalu undian. |
| FR-G-09 | Peringkat nomor jurus tersusun otomatis dari hasil median dan hukuman. |

### M. VAR dan Pengajuan Keberatan

| ID | Requirement |
|---|---|
| FR-M-01 | Pelatih kategori Tanding memiliki **2 kartu protes** per pertandingan, berlaku sepanjang tiga babak. Sisa kartu terlihat di panel operator dan papan skor. |
| FR-M-02 | Pelatih kategori Jurus memiliki **1 kartu protes** per penampilan, hanya untuk dua alasan: penampilan tidak sesuai deskripsi, atau pesilat/senjata keluar gelanggang. |
| FR-M-03 | Saat protes diajukan, sistem menandai kejadian yang disengketakan beserta stempel waktu pertandingan, sehingga rekaman video mudah ditemukan. |
| FR-M-04 | Panel Wasit Komisi Protes menampilkan hitung mundur **5 menit**. Lewat tenggat, sistem mengarahkan proses ke verifikasi juri yang dipimpin Ketua Pertandingan. |
| FR-M-05 | Hasil VAR dicatat sebagai **Sah** atau **Tidak Sah** beserta pengambil keputusannya, dan berdampak langsung ke `score_events` atau `penalties` lewat baris pembatal, bukan penyuntingan riwayat. |
| FR-M-06 | Protes Manajer dicatat berjenjang dengan tenggatnya: tingkat pertama ke Ketua Pertandingan, banding ke Delegasi Teknik. Keputusan banding bersifat final dan mengunci hasil partai. |
| FR-M-07 | Sistem tidak memutar video. Ia hanya menandai momen, mencatat keputusan, dan menegakkan tenggat waktu — pemutaran tetap di perangkat VAR terpisah. |

### H. Live Score Publik

| ID | Requirement |
|---|---|
| FR-H-01 | Halaman publik per gelanggang: skor berjalan, timer, babak, nama atlet dan kontingen. Read-only, tanpa login. |
| FR-H-02 | Halaman publik per turnamen: jadwal, bagan, hasil, rekap medali. |
| FR-H-03 | Halaman publik menerima pembaruan realtime dan pulih sendiri saat koneksi terputus. |
| FR-H-04 | Halaman publik tidak menampilkan identitas juri maupun input mentah per juri. |
| FR-H-05 | Halaman publik diberi rate limit dan dilayani lewat cache agar lonjakan penonton tidak membebani mesin scoring. |

### I. Overlay Siaran vMix

| ID | Requirement |
|---|---|
| FR-I-01 | Lima halaman overlay terpisah, masing-masing bisa dipasang ke Overlay Channel vMix yang berbeda: scorebug, lower third atlet, rincian nilai & hukuman, papan hasil, bagan/rekap medali. |
| FR-I-02 | Latar transparan, kanvas terkunci 1920×1080, nol elemen interaktif. |
| FR-I-03 | Halaman terhubung sendiri saat dimuat, reconnect otomatis dengan backoff, dan **menarik ulang seluruh state** setiap kali tersambung kembali. |
| FR-I-04 | Overlay mengikuti partai aktif gelanggang yang bersangkutan; tidak perlu dipilih manual dari vMix. |
| FR-I-05 | Font, ikon, dan gambar dilayani lokal — tidak ada permintaan ke internet. |
| FR-I-06 | Rincian nilai memberi kilatan visual singkat saat nilai baru terbit. |
| FR-I-07 | Rute overlay dibatasi ke localhost/LAN lewat middleware IP dan tidak diteruskan lewat tunnel publik. |

### J. Rekap & Laporan

| ID | Requirement |
|---|---|
| FR-J-01 | Rekap medali per kontingen (emas/perak/perunggu) dan peringkat umum. |
| FR-J-02 | Daftar juara per kelas dan per nomor seni. |
| FR-J-03 | Berita acara partai: skor per babak, daftar nilai, daftar hukuman, tanda tangan pejabat. |
| FR-J-04 | Ekspor PDF dan Excel untuk rekap medali, jadwal, dan daftar peserta. |

### K. Biaya Pendaftaran, Invoice & Pembayaran

| ID | Requirement |
|---|---|
| FR-K-01 | Penyelenggara menyusun daftar tarif per turnamen dengan empat komponen: biaya per atlet per nomor, tarif berbeda per kategori (tanding/tunggal/ganda/regu), tarif berbeda per golongan usia, dan biaya tetap per kontingen. |
| FR-K-02 | Nomor beregu (ganda/regu) dikenakan biaya per tim, bukan per orang. |
| FR-K-03 | Tarif dapat diubah selama turnamen berstatus draf. Setelah pendaftaran dibuka, perubahan tarif hanya berlaku untuk invoice yang belum dikunci, dan perubahannya tercatat. |
| FR-K-04 | Invoice dibuat otomatis satu per kontingen per turnamen, berisi rincian per atlet per nomor plus komponen biaya tetap. |
| FR-K-05 | Selama berstatus `draft`, invoice ikut berubah setiap kali pendaftaran atlet ditambah atau dihapus. |
| FR-K-06 | Saat official menekan bayar, invoice berpindah ke `awaiting_payment`: nilainya dikunci dan **pendaftaran atlet kontingen tersebut dibekukan**. |
| FR-K-07 | Bila sesi pembayaran kedaluwarsa atau dibatalkan, invoice kembali ke `draft` dan pendaftaran cair kembali. |
| FR-K-08 | Pembayaran memakai Midtrans Snap (hosted checkout). Aplikasi tidak pernah menerima, mengirim, atau menyimpan data kartu. |
| FR-K-09 | Status lunas hanya berubah lewat **webhook Midtrans yang tanda tangannya terverifikasi**. Parameter redirect browser tidak pernah dipercaya untuk mengubah status. |
| FR-K-10 | Penanganan webhook bersifat idempoten — notifikasi berulang untuk `order_id` yang sama tidak menggandakan efek. |
| FR-K-11 | Setiap upaya pembayaran tercatat sebagai baris `payment_attempts` dengan `order_id` unik berformat `INV-{nomor}-{upaya}`, karena Midtrans menolak `order_id` yang berulang. |
| FR-K-12 | Seluruh notifikasi mentah dari Midtrans disimpan apa adanya di `payment_events` untuk keperluan rekonsiliasi dan sengketa. |
| FR-K-13 | Bendahara panitia dapat menandai invoice lunas secara manual untuk pembayaran di luar sistem, dengan keterangan dan bukti wajib diisi. Tercatat di `audit_logs` dan dibedakan tegas dari pembayaran gateway. |
| FR-K-14 | Official kontingen melihat status tagihan, rincian, riwayat pembayaran, dan dapat mengunduh invoice PDF. |
| FR-K-15 | Panel bendahara: daftar invoice per status, total masuk, tunggakan, dan ekspor rekap keuangan. |
| FR-K-16 | Pembatalan pendaftaran atau atlet yang gugur setelah lunas **tidak mengembalikan dana**; sistem hanya mencatat pembatalannya. |
| FR-K-17 | Seluruh modul ini hidup di fase pra-acara. Node di venue tidak pernah memanggil Midtrans dan tidak terpengaruh statusnya. |

### L. Ikonografi Aksi & Indikator Hukuman

| ID | Requirement |
|---|---|
| FR-L-01 | Enam piktogram siluet padat digambar sebagai SVG: pukulan, tendangan, jatuhan, pembinaan, teguran, peringatan. Kuncian tidak termasuk — naskah 2025 tidak mengenal nilai 4. |
| FR-L-02 | Ikon jatuhan mewakili seluruh teknik bernilai 3: tangkapan, sapuan, ungkitan, kaitan, guntingan, dan serangan balik jatuhan. Tidak ada ikon gabungan, karena naskah 2025 tidak mengenal nilai `1+1`, `1+2`, maupun `1+3`. |
| FR-L-03 | Ikon berupa SVG inline dalam satu sprite, mewarisi warna dari induknya sehingga bisa mengambil warna sudut merah/biru tanpa berkas terpisah. Tidak ada raster, tidak ada permintaan berkas tambahan. |
| FR-L-04 | Ikon dipakai di empat permukaan dengan bentuk yang sama: tombol juri PWA, papan skor gelanggang, halaman publik, dan overlay vMix. |
| FR-L-05 | Teguran ditampilkan sebagai **2 kolom**. Teguran ketiga tidak pernah muncul di kolom karena naik menjadi Peringatan I. |
| FR-L-06 | Peringatan ditampilkan sebagai **3 kolom** dan tidak pernah mereset selama partai berjalan. Kolom ketiga menyala berarti pesilat terdiskualifikasi. |
| FR-L-07 | Pembinaan ditampilkan sebagai **2 kolom** yang dibuat lebih redup secara visual, karena pembinaan tidak mengurangi nilai. Kolom penuh menjadi isyarat bahwa pelanggaran ringan berikutnya akan naik menjadi teguran. |
| FR-L-08 | Jumlah kolom ditetapkan di `config/scoring.php`, tidak ditulis berserakan sebagai angka ajaib di dalam kode. |
| FR-L-09 | Tiap sanksi menyimpan atribut sebab: jenis pelanggaran (ringan/berat) dan keterangan wasit. Ditampilkan di panel dewan juri dan berita acara, tidak di papan skor. |
| FR-L-10 | Tombol juri untuk tiap jenis nilai memuat ikon beserta angka nilainya, agar bisa ditekan tanpa membaca teks. |
| FR-L-11 | Setiap ikon memiliki label teks alternatif untuk pembaca layar dan untuk berita acara berbasis teks. |

## 1.7 Non-Functional Requirements

| ID | Requirement |
|---|---|
| NFR-01 | Latensi input juri sampai tampil di panel operator, halaman publik, dan overlay < 300 ms di LAN. |
| NFR-02 | Seluruh fungsi pertandingan berjalan penuh tanpa internet. Putusnya tunnel publik tidak boleh berdampak apa pun ke gelanggang. |
| NFR-03 | Mendukung minimal 4 gelanggang berjalan bersamaan, masing-masing dengan juri aktif sesuai konfigurasi. |
| NFR-04 | Semua panel operasional pulih otomatis setelah koneksi putus, tanpa perlu dimuat ulang manual. |
| NFR-05 | `judge_inputs` bersifat immutable; riwayat penilaian tidak pernah hilang atau berubah. |
| NFR-06 | Panel juri terbaca dan tertekan dengan nyaman di layar HP 5 inci, satu tangan, tanpa zoom. |
| NFR-07 | Overlay vMix tetap stabil setelah menyala 3 jam tanpa dimuat ulang. |
| NFR-08 | Seluruh sistem dapat dipasang dari nol di satu mesin Windows dengan mengikuti panduan instalasi, tanpa akses internet setelah dependensi terunduh. |
| NFR-09 | Alamat host server, port Reverb, dan port aplikasi dapat diubah lewat `.env` tanpa mengubah kode. |
| NFR-10 | Modul pembayaran terisolasi penuh dari jalur pertandingan. Midtrans tidak dapat dijangkau, lambat, atau mati — pertandingan di gelanggang tetap berjalan tanpa terpengaruh. |
| NFR-11 | Kredensial Midtrans hanya berada di `.env`, tidak pernah masuk repo maupun terkirim ke browser. Server key tidak pernah muncul di sisi klien. |
| NFR-12 | Endpoint webhook dapat menerima notifikasi berulang dan notifikasi yang datang tidak berurutan tanpa merusak status invoice. |

## 1.8 Arsitektur & Keputusan Teknis

### 1.8.1 Realtime: Laravel Reverb, bukan Pusher

Reverb berjalan sebagai proses PHP lokal (`php artisan reverb:start`). Gelanggang offline, sedangkan Pusher/Ably butuh internet dan akan mati total saat sinyal venue hilang. Klien memakai Laravel Echo dengan broadcaster `reverb`. Polling HTTP tidak dipakai di jalur juri — window konsensus 2 detik menuntut latensi sub-200 ms.

### 1.8.2 Konsensus juri dievaluasi saat kedatangan input

1. Juri menekan tombol → event dikirim ke server.
2. Server membubuhkan `server_ts` sendiri (**jangan pernah percaya jam perangkat juri**) dan menyimpan baris mentah ke `judge_inputs`.
3. Segera setelah insert, evaluator melihat mundur sejauh `consensus_window_ms` untuk kombinasi `(match_id, round, corner, point_type)` yang sama, menghitung **juri berbeda** yang inputnya belum ter-consume.
4. Bila jumlahnya mencapai `consensus_threshold` → buat satu baris `score_events`, tandai input pembentuknya sebagai consumed (`score_event_id` terisi), broadcast.

Dedup dijamin kolom `score_event_id`. Konkurensi ditangani `SELECT ... FOR UPDATE` pada baris `matches` di dalam transaksi.

### 1.8.3 Timer server-authoritative

State babak disimpan di server (`started_at`, `paused_at`, `accumulated_ms`), bukan dihitung di browser. Server broadcast tick tiap ~250 ms; klien menginterpolasi.

### 1.8.4 Koneksi juri putus: tolak, jangan buffer lama

Bila WebSocket juri terputus, PWA menonaktifkan tombol dan menampilkan indikator merah besar. Buffer hanya ditoleransi untuk gap sangat pendek (< 1 detik).

### 1.8.5 Integrasi vMix: Web Browser Input

Dipilih **Web Browser Input** dibanding Data Source XML/JSON (polling, jeda) dan vMix API push ke GT Title (desain ganda). Overlay dipecah per elemen, bukan monolitik:

| Halaman | Dipasang ke | Isi |
|---|---|---|
| `/overlay/scorebug/{arena}` | Overlay 1 | Skor merah/biru, timer, babak, nama atlet, kontingen |
| `/overlay/athlete/{arena}/{corner}` | Overlay 2 | Lower third: nama, kontingen, foto |
| `/overlay/breakdown/{arena}` | Overlay 3 | Rincian nilai per babak, hukuman, kilatan saat nilai baru |
| `/overlay/result/{arena}` | Overlay 4 | Hasil akhir partai |
| `/overlay/bracket/{tournament}` | Input terpisah | Bagan gugur & rekap medali untuk tayangan antar partai |

### 1.8.6 Keamanan tunneling — hanya rute publik yang boleh keluar

- Rute publik diberi prefix `/live/*`, route group terpisah, read-only, tanpa auth, rate limit.
- Tunnel diarahkan ke reverse proxy (Caddy/Nginx) yang **hanya** meneruskan `/live/*`, aset statis, dan channel WebSocket publik. Path lain dibalas 404.
- Channel Echo dipisah tegas: `presence-arena.{id}` (private) versus `public-live.{arena}` (public, tanpa identitas juri).
- `/overlay/*` dikunci middleware IP lokal dan tidak pernah diteruskan lewat tunnel.

### 1.8.7 Pembayaran: Midtrans Snap

**Status lunas hanya boleh berubah lewat webhook yang tanda tangannya diverifikasi.** Redirect browser hanya menampilkan pesan "sedang diproses", tidak pernah mengubah data.

Verifikasi webhook: `signature_key` = SHA512 dari `order_id + status_code + gross_amount + ServerKey`.

State machine invoice:

```
draft ──(official menekan bayar)──▶ awaiting_payment ──(webhook settlement)──▶ paid
  ▲                                        │
  └──────(kedaluwarsa / dibatalkan)────────┘
```

### 1.8.8 Fondasi kode: boilerplate

**Sudah tersedia** — Laravel 13, PHP 8.3+, MySQL 8, Blade + Alpine 3, Tailwind 4, Vite; Fortify; spatie/laravel-permission 8 + lapisan resource key `{resource}.{action}`; RizzxxUI (50+ komponen); token CSS `data-theme`; Sora/Space Grotesk/IBM Plex Mono; ApexCharts, blade-lucide-icons, Pest 5.

**Ditambahkan** — `laravel/reverb`, seluruh domain pencak silat. **Dibersihkan** — modul contoh `Post` (selesai, lihat bagian /goal di bawah).

### 1.8.9 Design system gelanggang

Token warna khusus (`--silat-merah`, `--silat-biru`, dst), tipografi IBM Plex Mono (angka) + Space Grotesk (nama), target sentuh ≥64px, ikonografi SVG inline sprite, indikator hukuman berkolom. Terpisah total dari token admin (`resources/css/silat.css`, entri Vite sendiri).

## 1.9 Model Data

**Master & turnamen** — `tournaments`, `tournament_rule_settings`, `arenas`, `age_groups`, `match_categories`, `weight_classes`, `seni_events`

**Peserta** — `contingents`, `athletes`, `athlete_registrations`, `registration_documents`, `weight_ins`

**Keuangan** — `fee_schedules`, `invoices`, `invoice_items`, `payment_attempts`, `payment_events`, `manual_payments`

**Pertandingan** — `brackets`, `bracket_slots`, `matches`, `match_officials`

**Scoring Tanding** — `judge_inputs` (mentah, immutable) → `score_events` → `penalties` → `technical_counts` → `match_rounds` → `match_results`

**Scoring Jurus** — `jurus_performances`, `jurus_scores`, `jurus_deductions`

**VAR & keberatan** — `protest_cards`, `var_reviews`, `manager_protests`

**Sistem** — `users`, `roles`, `permissions`, `role_user`, `audit_logs`

Prinsip yang dipegang: **`judge_inputs` tidak pernah diubah atau dihapus.** Koreksi memakai baris pembatal di `score_events`.

## 1.10 Kriteria Selesai

1. Turnamen dibuat dengan tarif lengkap, dua kontingen mendaftarkan atlet, invoice terbit otomatis dengan nominal benar, dibayar lewat Midtrans sandbox sampai webhook menandai lunas, panitia memverifikasi, timbang badan dijalankan, bagan tergenerate, jadwal tersusun.
2. Satu partai Tanding dijalankan penuh dengan 3 juri di 3 HP berbeda, timer server, hukuman wasit, sampai hasil disahkan dewan juri.
3. Skor muncul serentak di panel operator, halaman publik, dan overlay vMix — selisih < 300 ms.
4. Satu nomor Jurus dinilai enam juri pada skala 9.00–10.00, median menghasilkan angka yang benar, dan pengurangan 0.01 serta 0.50 terterapkan.
4b. Satu protes VAR diajukan pelatih, diputus dalam tenggat 5 menit, dan hasilnya mengubah skor lewat baris pembatal.
5. Rekap medali tercetak ke PDF.
6. Diakses dari jaringan seluler luar: `/live/*` tampil; `/admin`, `/juri`, `/overlay/*` semuanya 404.
7. Reverb direstart di tengah pertandingan; seluruh panel dan overlay pulih ke state benar tanpa dimuat ulang manual.
8. Dokumen teknis lengkap: README, ERD, diagram arsitektur, panduan instalasi, panduan operasional.

---
---

# BAGIAN 2 — TASK LIST (EPIC)

## EPIC 0 — Adaptasi Boilerplate ✅

| ID | Task | Status |
|---|---|---|
| T0.1 | Clone boilerplate, `composer run setup`, aplikasi hidup | ✅ |
| T0.2 | Audit kode: routes, `app/Http`, resource key, `/design-system` | ✅ |
| T0.3 | Pasang & konfigurasi `laravel/reverb` + Echo | ✅ |
| T0.4 | `config/scoring.php` untuk nilai default peraturan | ✅ |
| T0.5 | Daftarkan peran & resource key domain silat | ✅ |
| T0.6 | Bersihkan sisa contoh (`Post`) | ✅ |
| T0.7 | Seeder data contoh: turnamen, kontingen, atlet, user tiap peran | ✅ |

## EPIC 0B — Design System Gelanggang ✅

| ID | Task | Status |
|---|---|---|
| T0B.1 | `resources/css/silat.css` + entri Vite terpisah, token warna & skala ukuran | ✅ |
| T0B.2 | Layout `silat` (layar gelanggang, panel operasional, publik) | ✅ |
| T0B.3 | Komponen inti: blok sudut, angka skor, timer, indikator juri, baris hukuman | ✅ |
| T0B.4 | Komponen tombol juri berukuran besar (≥64px), ikon + angka nilai | ✅ |
| T0B.5 | Halaman peraga komponen silat, sejajar `/design-system` admin | ✅ |
| T0B.6 | Sprite ikon SVG: pukulan, tendangan, bantingan, binaan, teguran, peringatan | ✅ |
| T0B.7 | Komponen indikator hukuman: teguran 2 kolom, peringatan 3 kolom, binaan redup | ✅ |

## EPIC 1 — Manajemen Turnamen & Master Data ✅

| ID | Task | Status |
|---|---|---|
| T1.1 | Migrasi & model: tournaments, arenas, age_groups, weight_classes, match_categories, seni_events | ✅ |
| T1.2 | Migrasi & model `tournament_rule_settings` + form konfigurasi peraturan | ✅ |
| T1.3 | CRUD turnamen & gelanggang | ✅ |
| T1.4 | CRUD golongan usia, kelas tanding, nomor seni | ✅ |
| T1.5 | Manajemen user & penugasan peran per turnamen | ✅ |

## EPIC 2 — Pendaftaran, Verifikasi, Timbang Badan ✅

| ID | Task | Status |
|---|---|---|
| T2.1 | Migrasi & model: contingents, athletes, athlete_registrations, registration_documents | ✅ |
| T2.2 | Portal official kontingen: CRUD atlet + unggah berkas | ✅ |
| T2.3 | Pendaftaran atlet ke kelas/nomor + validasi gender & golongan usia | ✅ |
| T2.4 | Panel verifikasi panitia (setuju/tolak + alasan), terkunci sampai invoice lunas | ✅ |
| T2.5 | Migrasi & model `weight_ins` + panel petugas timbang | ✅ |
| T2.6 | Aturan lolos/gugur berat badan + dampaknya ke bagan | ✅ |

## EPIC 2B — Biaya, Invoice & Pembayaran 🟡

| ID | Task | Status |
|---|---|---|
| T2B.1 | Migrasi & model: fee_schedules, invoices, invoice_items, payment_attempts, payment_events, manual_payments | ✅ |
| T2B.2 | Panel tarif penyelenggara: per kategori × golongan usia + biaya tetap kontingen | ✅ |
| T2B.3 | `InvoiceBuilder` — susun rincian dari pendaftaran, nomor beregu per tim | ✅ |
| T2B.4 | State machine invoice: draft → awaiting_payment → paid, pembekuan & pengembalian saat kedaluwarsa | ✅ |
| T2B.5 | Integrasi Midtrans Snap: token, `order_id` per upaya, halaman redirect | ⬜ menunggu kredensial sandbox |
| T2B.6 | Webhook Midtrans: verifikasi `signature_key`, idempotensi, pemetaan status | ⬜ menunggu kredensial sandbox |
| T2B.7 | Halaman tagihan official: rincian, riwayat, unduh invoice PDF | ⬜ (invoice show ada, PDF belum) |
| T2B.8 | Panel bendahara: daftar invoice, total masuk, tunggakan, ekspor rekap keuangan | ✅ |
| T2B.9 | Penandaan lunas manual + bukti + audit log | ✅ |
| T2B.10 | Pencatatan pembatalan tanpa pengembalian dana | ✅ |

## EPIC 3 — Bagan & Jadwal ✅

| ID | Task | Status |
|---|---|---|
| T3.1 | Migrasi & model: brackets, bracket_slots, matches, match_officials | ✅ |
| T3.2 | `BracketGenerator` — gugur tunggal + penanganan bye | ✅ |
| T3.3 | Antarmuka drawing: acak / atur manual / kunci bagan | ✅ `BracketController`, panel bagan (susun/tukar/kunci/buka-kunci) |
| T3.4 | Promosi otomatis pemenang ke slot berikutnya | ✅ (logic `PromosiPemenang` ada; UI pemicu partai selesai menyusul Fase 4) |
| T3.5 | Penjadwalan partai ke gelanggang + urutan tayang | ✅ `JadwalController` + `PenjadwalPartai`, deteksi bentrok antar-gelanggang |
| T3.6 | Penugasan juri/wasit/dewan juri per partai | ✅ `AparatController`, jumlah juri mengikuti setelan peraturan |

## EPIC 4 — Mesin Scoring Tanding (inti, paling berisiko) 🟡

| ID | Task | Status |
|---|---|---|
| T4.1 | Migrasi & model: judge_inputs, score_events, penalties, match_rounds | ✅ Tanpa `match_results` terpisah — skor dihitung on-the-fly, `ratified_at`/`ratified_by` ditambah ke `matches` |
| T4.2 | `MatchTimer` — state babak server-authoritative + broadcast tick | 🟡 Timer selesai; broadcast per state-change (bukan tiap 250ms) — lihat catatan di Bagian 5 |
| T4.3 | `ConsensusEvaluator` — window, ambang, juri distinct, dedup, penguncian transaksi | ✅ TDD, 8 test, menemukan bug presisi milidetik Eloquent |
| T4.4 | Event & channel broadcast (private per gelanggang, public untuk live) | ✅ 5 event `ShouldBroadcastNow`, `ArenaChannelAuthorizer` |
| T4.5 | PWA juri: papan tombol, manifest, service worker, wake lock, indikator koneksi | ⬜ |
| T4.6 | Panel operator gelanggang: pilih partai, kendali timer, catat hukuman | ✅ `silat.operator`, verifikasi browser nyata |
| T4.7 | Panel wasit: binaan/teguran/peringatan, hentikan pertandingan | ✅ `silat.wasit` — "hentikan" berarti jeda timer (partai.update); mengakhiri partai tetap wewenang operator/ketua (partai.manage) |
| T4.8 | `TandingScoreCalculator` — skor per babak, hukuman, penentuan pemenang | ✅ |
| T4.9 | Panel dewan juri: verifikasi, koreksi bernotulen, pengesahan hasil | ✅ `silat.dewan-juri` — riwayat nilai/hukuman + pembatalan beralasan + sahkan |
| T4.10 | Resync state penuh saat reconnect untuk semua panel | ✅ `GET .../partai/{match}` |
| T4.11 | Tangga hukuman Pasal 11 | ✅ |
| T4.12 | Hitungan teknik | ✅ |
| T4.13 | Pemecah seri menang angka lima tingkat, tawaran WMP | ✅ |

## EPIC 4B — VAR & Pengajuan Keberatan ⬜

| ID | Task | Status |
|---|---|---|
| T4B.1 | Migrasi & model: protest_cards, var_reviews, manager_protests | ⬜ |
| T4B.2 | Jatah kartu protes pelatih dan pencatatan pemakaiannya | ⬜ |
| T4B.3 | Penandaan kejadian disengketakan beserta stempel waktu pertandingan | ⬜ |
| T4B.4 | Panel Wasit Komisi Protes dengan hitung mundur 5 menit dan hasil Sah/Tidak Sah | ⬜ |
| T4B.5 | Dampak hasil VAR ke skor lewat baris pembatal | ⬜ |
| T4B.6 | Protes Manajer berjenjang beserta tenggatnya sampai banding final | ⬜ |

## EPIC 5 — Live Score Publik & Tunneling ⬜

| ID | Task | Status |
|---|---|---|
| T5.1 | Route group `/live/*` read-only + rate limit + cache | ⬜ |
| T5.2 | Halaman live per gelanggang | ⬜ |
| T5.3 | Halaman jadwal, bagan, hasil, rekap medali publik | ⬜ |
| T5.4 | Channel publik terpisah | ⬜ |
| T5.5 | Konfigurasi reverse proxy + tunnel, allowlist path | ⬜ |
| T5.6 | Uji penetrasi ringan dari jaringan luar | ⬜ |

## EPIC 6 — Overlay Siaran vMix ⬜

| ID | Task | Status |
|---|---|---|
| T6.1 | Route group `/overlay/*` + middleware `AllowLocalNetworkOnly` | ⬜ |
| T6.2 | `overlay/connection.js` — Echo, reconnect backoff, resync | ⬜ |
| T6.3 | Halaman scorebug (Overlay 1) | ⬜ |
| T6.4 | Halaman lower third profil atlet (Overlay 2) | ⬜ |
| T6.5 | Halaman rincian nilai & hukuman + kilatan (Overlay 3) | ⬜ |
| T6.6 | Halaman papan hasil (Overlay 4) | ⬜ |
| T6.7 | Halaman bagan & rekap medali antar partai | ⬜ |
| T6.8 | Uji nyata di vMix Pro | ⬜ |

## EPIC 7 — Kategori Jurus ⬜

| ID | Task | Status |
|---|---|---|
| T7.1 | Migrasi & model: jurus_performances, jurus_scores, jurus_deductions | ⬜ |
| T7.2 | Timer penampilan waktu acuan & toleransi per nomor & golongan usia | ⬜ |
| T7.3 | Panel juri Jurus: nilai skala 9.00–10.00 + pengurangan 0.01 | ⬜ |
| T7.4 | Panel Pengawas/Dewan Wasit Juri: pengurangan 0.50 | ⬜ |
| T7.5 | `JurusScoreCalculator` — median seluruh juri, kurangi hukuman, DQ jadi 0,00 | ⬜ |
| T7.6 | Pemecah seri: hukuman, waktu terdekat, standar deviasi, undian | ⬜ |
| T7.7 | Validasi jumlah juri Jurus minimal 4 dan wajib genap | ⬜ |

## EPIC 8 — Rekap, Laporan, Dokumen ⬜

| ID | Task | Status |
|---|---|---|
| T8.1 | Rekap medali per kontingen + peringkat umum | ⬜ |
| T8.2 | Daftar juara per kelas dan nomor seni | ⬜ |
| T8.3 | Berita acara partai (PDF) | ⬜ |
| T8.4 | Ekspor Excel: peserta, jadwal, rekap medali | ⬜ |
| T8.5 | README + panduan instalasi LAN (Windows) | ⬜ |
| T8.6 | ERD + diagram arsitektur | ⬜ |
| T8.7 | Panduan operasional panitia | ⬜ |
| T8.8 | Daftar parameter peraturan wajib diverifikasi ke naskah resmi | ⬜ |

---
---

# BAGIAN 3 — TO-DO LIST RINCI

## Fase 0 — Adaptasi Boilerplate ✅ Selesai

- [x] Clone boilerplate ke `D:\digiscoring-prototype`
- [x] MySQL tersedia
- [x] Dua database (aplikasi + test)
- [x] `composer install`, `npm install`, `.env`, `php artisan key:generate`
- [x] `composer run setup`, `composer run dev` — aplikasi hidup, login akun contoh
- [x] Buka `/design-system`, catat komponen
- [x] Audit kode: routes, `app/Http`, `Resource`, `ResourcePermission`, enums, helpers
- [x] Pahami & daftarkan resource key domain silat: admin, official kontingen, petugas timbang, bendahara, operator gelanggang, wasit, juri, dewan juri
- [x] Periksa pemakaian model `Post`, buang setelah dipastikan tidak dipakai
- [x] Tambahkan `audit_logs`
- [x] `composer require laravel/reverb`, `reverb:install`
- [x] Set `BROADCAST_CONNECTION=reverb`, `REVERB_HOST`, `REVERB_PORT`
- [x] Pasang Laravel Echo, verifikasi handshake WebSocket
- [x] Tambahkan Reverb ke skrip `composer run dev`
- [x] `config/scoring.php` dari naskah 2025
- [x] Baca dokumen naskah untuk tabel kelas dan rentang berat badan → seeder
- [x] Baca dokumen kategori Jurus untuk rincian nomor dan penilaian
- [x] `DemoTournamentSeeder`: turnamen, kontingen, atlet, user per peran
- [x] Test Pest smoke: login tiap peran

## Fase 0b — Design System Gelanggang ✅ Selesai

- [x] `resources/css/silat.css`: token warna, skala ukuran, numeral tabular
- [x] Entri Vite terpisah
- [x] IBM Plex Mono & Space Grotesk dilayani lokal
- [x] Layout `layouts/silat.blade.php`
- [x] Layout `layouts/overlay.blade.php` (kanvas 1920×1080, transparan) — *dasar layout ada; halaman overlay aktual di Fase 6*
- [x] Sprite ikon SVG siluet padat
- [x] Ikon `fill="currentColor"`
- [x] Label teks alternatif per ikon
- [x] Uji keterbacaan ikon 20px & 96px
- [x] Komponen `components/silat/`: blok-sudut, angka-skor, timer, indikator-juri, baris-hukuman, tombol-nilai, ikon-aksi
- [x] Baris-hukuman: teguran 2 kolom, peringatan 3 kolom, binaan redup
- [x] Jumlah kolom dibaca dari `config/scoring.php`
- [x] Tombol nilai juri ≥64px
- [x] Halaman peraga komponen silat
- [x] Uji keterbacaan papan skor jarak jauh
- [x] Uji tombol juri di HP 5 inci

## Fase 1 — Manajemen Turnamen ✅ Selesai

- [x] Migrasi tournaments, arenas, age_groups, weight_classes, match_categories, seni_events
- [x] Migrasi `tournament_rule_settings`
- [x] Model + relasi + factory
- [x] CRUD turnamen (draf/berjalan/selesai) + validasi transisi status
- [x] CRUD gelanggang per turnamen
- [x] Form konfigurasi peraturan dengan teks bantuan
- [x] CRUD golongan usia dan kelas tanding
- [x] CRUD nomor seni (Jurus)
- [x] Manajemen user + pembuatan akun juri massal
- [x] Test: konfigurasi peraturan tersimpan & terbaca kembali

## Fase 2 — Pendaftaran & Timbang Badan ✅ Selesai

- [x] Migrasi contingents, athletes, athlete_registrations, registration_documents, weight_ins
- [x] Portal official: CRUD atlet
- [x] Unggah berkas dengan validasi tipe & ukuran, disk lokal
- [x] Pendaftaran atlet ke kelas tanding / nomor seni
- [x] Validasi otomatis gender/umur/berat
- [x] Cegah atlet terdaftar ganda di kelas yang sama
- [x] Panel verifikasi panitia: setujui/tolak + alasan wajib
- [x] Halaman status verifikasi untuk official
- [x] Panel timbang badan
- [x] Aturan lolos/gugur otomatis
- [x] Penimbangan ulang sebagai baris baru
- [x] Atlet gugur otomatis ditandai
- [x] Test: atlet di luar rentang berat tidak lolos verifikasi timbang

## Fase 2b — Biaya, Invoice & Pembayaran 🟡 Sebagian besar selesai

- [ ] Pasang SDK Midtrans, isi `MIDTRANS_SERVER_KEY`/`CLIENT_KEY`/`IS_PRODUCTION` di `.env` — **menunggu kredensial sandbox dari user**
- [x] Migrasi `fee_schedules`
- [x] Migrasi `invoices`
- [x] Migrasi `invoice_items`
- [ ] Migrasi `payment_attempts` — tabel belum ada, menyusul integrasi Midtrans
- [ ] Migrasi `payment_events` — idem
- [x] Migrasi `manual_payments`
- [x] Panel tarif: matriks kategori × golongan usia, biaya tetap kontingen
- [x] `InvoiceBuilder`
- [x] Invoice `draft` disusun ulang otomatis
- [x] Transisi ke `awaiting_payment`: kunci nominal, bekukan pendaftaran
- [ ] Pekerjaan terjadwal: invoice kedaluwarsa kembali ke `draft`
- [ ] `MidtransGateway`
- [ ] Halaman checkout official + halaman redirect balik
- [ ] Endpoint webhook `POST /webhooks/midtrans`
- [ ] Verifikasi `signature_key`
- [ ] Pemetaan status Midtrans lengkap
- [ ] Idempotensi webhook
- [ ] Proteksi notifikasi tidak berurutan
- [x] Halaman tagihan official: rincian per atlet, riwayat *(unduh PDF belum)*
- [x] Panel bendahara: daftar invoice, total masuk, tunggakan, ekspor CSV/Excel
- [x] Penandaan lunas manual + bukti wajib + audit log
- [x] Kunci panel verifikasi sampai lunas
- [x] Pencatatan pembatalan/atlet gugur tanpa pengembalian dana
- [x] Test: perhitungan invoice benar untuk kombinasi kategori × golongan usia + biaya tetap
- [x] Test: nomor ganda/regu dihitung per tim
- [ ] Test: webhook tanda tangan palsu ditolak
- [ ] Test: webhook dikirim tiga kali hanya berefek sekali
- [ ] Test: redirect browser palsu tidak mengubah status
- [x] Test: tambah atlet saat invoice `awaiting_payment` ditolak
- [ ] Test: invoice kedaluwarsa kembali ke `draft`
- [x] Test: verifikasi ditolak selama invoice belum lunas
- [ ] Uji end-to-end di Midtrans sandbox

## Fase 3 — Bagan & Jadwal ✅ Selesai

- [x] Migrasi `brackets`, `bracket_slots`, `matches`, `match_officials`
- [x] `BracketGenerator`: ukuran bagan pangkat dua terdekat, sebar bye merata
- [x] Uji generator untuk 2, 3, 5, 8, 9, 16, 17, 32 peserta
- [x] Antarmuka drawing: tombol acak (susun/susun ulang) + tukar tempat manual sebelum dikunci
- [x] Kunci bagan; membuka kunci wajib beralasan + tercatat `audit_logs` (`bagan.kunci`, `bagan.buka_kunci`)
- [x] Promosi otomatis pemenang ke `bracket_slot` berikutnya *(logic `PromosiPemenang`; pemicunya baru otomatis untuk bye — pemicu dari hasil partai sungguhan menyusul mesin scoring Fase 4)*
- [x] Penjadwalan partai ke gelanggang + pengurutan tayang (`PenjadwalPartai`, tombol naik/turun — bukan drag, tapi fungsinya sama)
- [x] Deteksi bentrok: satu atlet tidak boleh dijadwalkan di dua gelanggang dalam jeda 30 menit
- [x] Penugasan juri 1..N, wasit per partai (`AparatController`, `match_officials`)
- [x] Validasi jumlah juri ditugaskan = `jumlah_juri_tanding` dari setelan peraturan turnamen
- [x] Test: bagan 5 peserta menghasilkan bye yang benar dan promosi berjalan sampai final
- [x] Test: tukar tempat menyusun ulang partai babak pertama termasuk bye yang berpindah
- [x] Test: deteksi bentrok jadwal (beda gelanggang berdekatan ditolak, gelanggang sama/jarak jauh diterima)
- [x] Test: jumlah juri yang tidak sesuai setelan peraturan ditolak, wasit tidak boleh merangkap juri

## Fase 4 — Mesin Scoring Tanding 🟡 Engine + HTTP + broadcasting selesai; panel & PWA belum

> Dikerjakan dengan test lebih dulu (TDD), sesuai rencana. Engine (commit `ee0a37c`) dan lapisan HTTP/broadcasting (commit `d01710f`) sudah jalan dan teruji end-to-end lewat HTTP; yang belum cuma antarmukanya.

- [x] Migrasi `judge_inputs` (`match_id`, `round`, `judge_user_id`, `corner`, `point_type`, `server_ts`, `client_ts`, `score_event_id`, `rejected_reason`)
- [x] Index gabungan `(match_id, round, corner, point_type, server_ts)`
- [x] Migrasi `score_events`, `penalties`, `match_rounds` — **tanpa** `match_results` terpisah; `ratified_at`/`ratified_by` langsung di `matches`, skor dihitung on-the-fly
- [x] Migrasi tambahan `technical_counts` (tidak ada di rencana awal, ternyata perlu untuk hitungan teknik Pasal 11.6.g.2/3)
- [x] Test `ConsensusEvaluator` sebelum implementasi (9 kasus + 1 tambahan bug regresi)
- [x] Implementasi `ConsensusEvaluator` — menemukan & memperbaiki bug nyata: format tanggal bawaan Eloquent memangkas milidetik saat menyimpan, bikin window 2 detik tidak berarti apa-apa untuk input dalam detik yang sama
- [x] `MatchTimer`: mulai/jeda/lanjut/reset/babak berikutnya/akhiri (`akhiriPartai` juga memanggil `PromosiPemenang`, menutup promosi bagan otomatis untuk partai sungguhan, bukan cuma bye)
- [ ] ~~Broadcast tick ~250 ms~~ — **diganti sengaja**: broadcast per perubahan state timer (mulai/jeda/lanjut/selesai), klien hitung mundur sendiri dari `started_at`/`accumulated_ms`. Tick 250ms terus-menerus butuh proses latar yang hidup abadi, di luar cakupan siklus request/response. Didiskusikan dan disetujui user sebelum dikerjakan.
- [x] Event broadcast: `JudgeInputReceived` (privat saja — identitas juri), `ScoreAwarded`, `PenaltyIssued`, `TimerTicked`, `MatchStateChanged` (privat + publik)
- [x] Channel `arena.{id}` (private, klien minta `presence-arena.{id}`) dan `public-live.{arena}` (public, tanpa entri di channels.php — otomatis publik karena tidak berawalan private-/presence-)
- [ ] PWA juri: manifest, service worker, ikon, fullscreen
- [ ] Papan tombol juri: target sentuh nyaman satu tangan
- [ ] Umpan balik instan saat tombol ditekan
- [ ] Wake lock
- [x] Indikator koneksi; putus → tombol nonaktif + indikator merah *(store Alpine `koneksi` + badge "Tersambung"/"Terputus" di ketiga panel; tombol juri sendiri menyusul PWA)*
- [x] Panel operator: pilih partai aktif, kendali timer, papan skor besar, daftar nilai masuk — `silat.operator`, verifikasi klik nyata di browser (mulai/jeda/reset/selesaikan babak/akhiri)
- [x] Panel wasit: pembinaan, Teguran I/II, Peringatan I/II/III, hitungan teknik, hentikan pertandingan — `silat.wasit`, verifikasi klik nyata (hukuman ringan/sedang/berat, hitungan)
- [x] Sanksi menyimpan sebab: tingkat pelanggaran + keterangan wasit (`violation_level`, `note`)
- [x] Peringatan (`penalties.tier=peringatan`) berlaku sepanjang partai, tidak pernah reset antar babak
- [x] Pembinaan akumulatif tanpa bedakan jenis pelanggaran (reset hanya saat memicu eskalasi ke Teguran)
- [x] 2 pembinaan → pelanggaran ringan berikutnya naik jadi Teguran
- [x] Pelanggaran sedang → Teguran langsung; berat → Peringatan I langsung
- [x] Teguran ketiga → dinaikkan jadi Peringatan I (dipaksa server)
- [x] Peringatan III → otomatis diskualifikasi & akhiri partai (`TanggaHukuman::jatuhkanPeringatan` memanggil `MatchTimer::akhiriPartai`)
- [x] Hitungan teknik: 9 → Teguran I; 3 hitungan berturut → menang teknik; 10 → menang mutlak (`HitunganTeknik`, tiga akibat bisa bertumpuk pada hitungan yang sama)
- [x] Tawaran menang WMP saat selisih 30 (babak II/III) atau 20 (Usia Dini) — `TandingScoreCalculator::cekTawaranWmp()`, tawaran bukan otomatis mengakhiri
- [x] Pemecah seri menang angka lima tingkat (Pasal 11.6.g) — `babak_tambahan` bukan hasil komputasi, ia menunggu babak ekstra benar-benar dimainkan (dicek dari jumlah `match_rounds` melebihi normal golongan) baru lanjut ke `berat_badan_teringan`
- [x] Test: 2 pembinaan + pelanggaran ringan → Teguran I `−1`
- [x] Test: pelanggaran berat langsung → Peringatan I `−5`
- [x] Test: teguran ketiga → Peringatan I, bukan teguran
- [x] Test: peringatan tidak reset antar babak
- [x] Test: Peringatan III → diskualifikasi & akhiri partai
- [x] Penyelesaian partai dengan sebab khusus lewat endpoint `akhiri` (angka/teknik/mutlak/wmp/undur_diri/cedera/wo); diskualifikasi otomatis lewat tangga hukuman/hitungan teknik
- [x] `TandingScoreCalculator`
- [x] Panel dewan juri: tinjau, koreksi via baris pembatal + alasan, sahkan hasil — `silat.dewan-juri`, riwayat gabungan nilai+hukuman, verifikasi klik nyata (batalkan sanksi, dot padam)
- [x] Endpoint `GET .../partai/{match}` untuk resync (bukan `/api/match/{id}/state` — mengikuti pola route admin yang sudah ada, bukan API terpisah)
- [x] Semua panel resync penuh tiap aksi sukses (bukan menunggu Echo) dan tiap event Reverb diterima — lihat catatan bug #1 dan #3 di ringkasan Fase 4 di bawah
- [x] Test: partai penuh 1 babak dari mulai sampai diakhiri dan disahkan → status dan skor benar (lewat `PartaiScoringControllerTest`, 21 test end-to-end HTTP + 6 test halaman panel)

## Fase 4b — VAR & Pengajuan Keberatan ⬜ Belum dimulai

- [ ] Migrasi `protest_cards`, `var_reviews`, `manager_protests`
- [ ] Jatah kartu pelatih: 2 Tanding, 1 Jurus; sisa kartu tampil di panel & papan skor
- [ ] Pengajuan protes menandai kejadian + stempel waktu pertandingan
- [ ] Panel Wasit Komisi Protes: countdown 5 menit, hasil Sah/Tidak Sah, pencatat keputusan
- [ ] Lewat tenggat → verifikasi juri dipimpin Ketua Pertandingan
- [ ] Hasil VAR mengubah skor via baris pembatal
- [ ] Protes Manajer berjenjang + tenggat, banding ke Delegasi Teknik final
- [ ] Test: kartu protes habis, pengajuan berikutnya ditolak
- [ ] Test: hasil VAR "Tidak Sah" membatalkan nilai tanpa hapus `judge_inputs`

## Fase 5 — Live Score Publik & Tunneling ⬜ Belum dimulai

- [ ] `routes/public.php` prefix `/live`, tanpa auth, read-only
- [ ] Rate limit + cache respons pendek
- [ ] Halaman `/live/{arena}`
- [ ] Halaman `/live/tournament/{id}`
- [ ] Payload channel publik dibersihkan
- [ ] Pemulihan otomatis saat koneksi terputus
- [ ] Konfigurasi Caddy/Nginx: teruskan hanya `/live/*`, aset, WebSocket publik
- [ ] Sambungkan cloudflared/ngrok ke reverse proxy
- [ ] Uji dari jaringan seluler luar
- [ ] Uji: matikan tunnel di tengah pertandingan

## Fase 6 — Overlay Siaran vMix ⬜ Belum dimulai

- [ ] `AllowLocalNetworkOnly` middleware
- [ ] `routes/overlay.php` prefix `/overlay`
- [ ] `resources/js/overlay/connection.js`
- [ ] Layout overlay dasar 1920×1080 transparan
- [ ] Bundle font & ikon lokal
- [ ] Halaman scorebug
- [ ] Halaman lower third atlet
- [ ] Halaman rincian nilai & hukuman + kilat
- [ ] Halaman papan hasil
- [ ] Halaman bagan & rekap medali
- [ ] Uji nyata di vMix Pro (transparansi, latensi, 3 jam stabil, FPS)

## Fase 7 — Kategori Jurus ⬜ Belum dimulai

- [ ] Migrasi `jurus_performances`, `jurus_scores`, `jurus_deductions`
- [ ] Nomor: Tunggal, Tunggal Bebas, Ganda, Regu A, Regu B, Solo Kreatif
- [ ] Timer penampilan waktu acuan & toleransi
- [ ] Panel juri Jurus: skala 9.00–10.00, langkah 0.01
- [ ] Pengurangan 0.01 oleh juri
- [ ] Pengurangan 0.50 oleh Pengawas/Dewan Wasit Juri
- [ ] `JurusScoreCalculator`: median, kurangi hukuman
- [ ] Validasi juri minimal 4 & genap; median genap = rata-rata dua nilai tengah
- [ ] Diskualifikasi → `0,00`
- [ ] Pemecah seri: hukuman, waktu terdekat, standar deviasi, undian
- [ ] Test: median 6 juri, median 4 juri, kasus nilai kembar
- [ ] Test: standar deviasi sebagai pemecah seri
- [ ] Papan hasil Jurus di panel operator dan halaman publik

## Fase 8 — Rekap, Laporan, Dokumen ⬜ Belum dimulai

- [ ] Pasang pustaka ekspor PDF dan Excel
- [ ] Rekap medali per kontingen + peringkat umum
- [ ] Daftar juara per kelas tanding dan per nomor seni
- [ ] Berita acara partai (PDF)
- [ ] Ekspor Excel: peserta, jadwal, rekap medali
- [ ] README
- [ ] Panduan instalasi LAN Windows
- [ ] ERD dan diagram arsitektur
- [ ] Panduan operasional panitia
- [ ] `docs/PARAMETER-PERATURAN.md`

---
---

# BAGIAN 4 — VERIFIKASI

**Unit test** — `ConsensusEvaluator`, `TandingScoreCalculator` (termasuk tangga hukuman Pasal 11), `JurusScoreCalculator` (median & standar deviasi), `BracketGenerator` ✅, `InvoiceBuilder` ✅.

**Uji keamanan pembayaran** — tanda tangan webhook palsu ditolak, webhook berulang hanya berefek sekali, redirect browser palsu tidak bisa menandai lunas. Seluruh pengujian memakai Midtrans sandbox. ⬜

**Feature test** — tiap peran hanya bisa akses rutenya; `/live/*` terbuka tanpa login; rute admin/juri/overlay tertutup. ⬜ (sebagian besar tertutup lewat resource key yang sudah ada, rute publik/overlay belum dibuat)

**Uji integrasi realtime** — Playwright: 3 tab juri + 1 tab operator + 1 tab publik. ⬜

**Uji lapangan LAN** — target latensi < 300 ms. ⬜

**Uji tunneling** — dari jaringan seluler luar. ⬜

**Uji overlay vMix** — lihat Fase 6. ⬜

**Uji beban** — 4 gelanggang berjalan bersamaan. ⬜

**Uji pemulihan** — restart Reverb, cabut WiFi HP juri, matikan tunnel di tengah pertandingan. ⬜

---
---

# BAGIAN 5 — PEKERJAAN DI LUAR URUTAN FASE (selesai)

Dikerjakan atas permintaan langsung sebelum lanjut ke Task #21 (Fase 3 lanjutan).

## /goal — Navigasi sidebar, komponen UI, bersih boilerplate (commit `8970b71`)

- [x] Pindahkan tombol navigasi bersarang dari halaman ke sidebar (`NavigationBuilder`, `config/navigation.php`, `IngatTurnamenAktif` middleware)
- [x] Komponen `x-ui.nav-tabs` untuk tab antar-halaman (beda dari `x-ui.tabs` antar-panel)
- [x] Perbaiki komponen UI menyimpang design system: bug kritis `x-ui.select` (SVG data-URI merusak parsing atribut `style`/`class`), `x-ui.file-upload` prop `required`
- [x] Bersihkan modul contoh boilerplate `Post` (model, controller, policy, view, route, seeder, test)
- [x] Audit sistematis: seluruh `rk(...)` di routes dicocokkan ke `ResourceMap` → temukan & perbaiki `turnamen.export` yang tidak terpetakan

## Refinement UI lanjutan (belum di-commit sepenuhnya / sedang berjalan)

- [x] Sidebar refinement (caption, spacing)
- [x] Hapus glass-variant dari `stat`/`card`
- [x] Komponen `table-row` dibuat
- [x] Update admin views: permissions, kontingen, atlet, timbang, turnamen (spacing, border)
- [x] Fix UI: `atlet/index`, `empty-state`, turnamen form/edit/peraturan
- [x] Fix UI: bendahara, verifikasi, gelanggang (border/spacing), guest-split layout
- [x] Fix 6 bug UI: menu fallback, cakupan glassmorphism, spacing, konsolidasi card, layout tabel, search
- [x] `MenuKejuaraanAktifTest.php` ditambahkan (4 test)
- [x] Commit & push seluruh perubahan UI refinement di atas (commit `5e13b1b`)
- [ ] Uji dark mode — disebutkan belum tuntas di catatan sesi, belum diverifikasi ulang

## Task #21 — Drawing, penguncian bagan, dan penjadwalan (selesai)

Bagian terakhir Fase 3 yang sempat tertunda oleh permintaan `/goal`. Tiga potongan:

- [x] **Panel bagan** (`BracketController`, `resources/views/admin/bagan/`) — daftar kelas tanding dengan status bagan, susun/susun ulang, tukar tempat manual sebelum dikunci, kunci dengan konfirmasi, buka kunci wajib alasan + audit log. `BracketGenerator` ditambah method `tukar()`, `kunci()`, `bukaKunci()`.
- [x] **Penjadwalan** (`JadwalController`, `PenjadwalPartai`, `resources/views/admin/jadwal/`) — tetapkan partai ke gelanggang + waktu tayang, urutan otomatis di akhir antrean, tombol naik/turun urutan, lepas jadwal. Deteksi bentrok: satu atlet tidak boleh dijadwalkan di gelanggang berbeda dalam jeda 30 menit (konstanta implementasi, bukan dari naskah — naskah tidak mengatur jadwal sama sekali).
- [x] **Penugasan aparat** (`AparatController`, `resources/views/admin/partai/aparat.blade.php`) — pilih wasit + N juri dari pengguna berperan `wasit`/`juri`, jumlah juri mengikuti `jumlah_juri_tanding` dari setelan peraturan turnamen (bukan angka tetap), wasit tidak boleh merangkap juri, menetapkan ulang menimpa penugasan lama.
- [x] Nav baru: grup "Pertandingan" (Bagan, Jadwal) di sidebar; halaman Aparat diakses lewat tautan dari baris partai di halaman Jadwal, tidak lewat nav sendiri.
- [x] 29 test baru (`BracketGeneratorTest` +8, `BracketControllerTest`, `PenjadwalPartaiTest`, `JadwalControllerTest`, `AparatControllerTest`), semuanya hijau bersama seluruh suite.

## Fase 4 — Mesin scoring Tanding, bagian yang sudah dikerjakan (ringkasan)

Dikerjakan atas permintaan langsung ("fase 4 dulu, mesin scoring Tanding"), dipecah jadi dua commit:

- **`ee0a37c`** — Engine murni: 6 kelas domain di `App\Support\Scoring` (ConsensusEvaluator, MatchTimer, TanggaHukuman, HitunganTeknik, TandingScoreCalculator, plus CatatInputJuri di commit berikutnya), 6 enum, 4 model baru, 5 migrasi. TDD penuh — test ditulis sebelum implementasi untuk ConsensusEvaluator, menemukan bug nyata (presisi milidetik Eloquent, lihat di atas).
- **`d01710f`** — Lapisan pengiriman: 5 event broadcast, `ArenaChannelAuthorizer`, `PartaiScoringController` dengan 11 endpoint (resync + timer + nilai + hukuman + hitungan + akhiri + sahkan + 2 pembatalan). Sempat berhenti sebentar untuk konfirmasi user soal keputusan TimerTicked (lihat catatan di atas) sebelum lanjut.
- **`d5e894e`** — Panel operator, wasit, dewan juri (Task #35). Satu factory Alpine (`partaiPanel`) dipakai ketiganya, tombol beda per panel diatur `@resource` di Bladenya, bukan tiga factory terpisah. Verifikasi lewat klik nyata di browser (bukan cuma baca kode) menemukan dan memperbaiki tiga bug yang test HTTP murni tidak akan pernah menangkap:
  1. `ShouldBroadcastNow` bikin aksi GAGAL TOTAL begitu Reverb tidak terjangkau, padahal perubahan sudah tersimpan ke database duluan. Diperbaiki dengan `siarkan()`: tiap dispatch event dibungkus try/catch, dilaporkan lewat `report()` tapi tidak pernah menggagalkan respons. Ditutup test regresi yang memaksa driver ke `reverb` dengan host tak terjangkau.
  2. Tombol "mulai babak" salah menghitung target setelah direset — selalu `current_round+1` padahal seharusnya mengulang babak yang sama kalau statusnya `belum_mulai`.
  3. `x-ref` di elemen yang sama dengan `x-data`-nya sendiri ternyata TIDAK tercatat di `$refs` milik komponen induk (asumsi awal dari kebiasaan Alpine keliru) — timer macet di 00:00 selamanya. Diperbaiki dengan memindahkan interpolasi rAF langsung ke komponen induk, tanpa `x-data` bersarang.

PWA juri (Task #36) belum dikerjakan — user memilih "Endpoint HTTP dulu saja" saat Fase 4 dimulai, lalu "lanjut panel operator, wasit, dewan-juri dulu" setelah HTTP selesai. PWA menyusul.

---

## Yang Perlu Diputuskan / Menunggu User

1. **Kredensial Midtrans sandbox** (`MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`) — untuk lanjut Fase 2b bagian pembayaran. Ini satu-satunya penghalang murni Fase 2b.
2. **PWA juri** (Task #36) — satu-satunya bagian Fase 4 yang tersisa: papan tombol juri (manifest, service worker, wake lock, indikator koneksi). Endpoint HTTP (`nilai`) sudah ada dan teruji.
3. **Arah setelah Fase 4 selesai total**: Fase 2b (Midtrans, menunggu kredensial), Fase 4b (VAR), Fase 5 (live score publik), Fase 7 (Jurus) — semuanya masih ⬜, belum ada urutan yang diputuskan user.
