# Brief Desain — Digiscoring Pencak Silat

> Dokumen ini adalah sumber tunggal untuk menggambar ulang seluruh antarmuka aplikasi.
> Ia menggantikan design system warisan boilerplate (**RizzxxUI**) sepenuhnya.
>
> Seluruh nilai warna di sini **sudah diukur**, bukan diperkirakan. Rasio di tiap baris
> dihasilkan `scripts/kontras.mjs` — jalankan `node scripts/kontras.mjs` untuk memeriksa
> ulang kapan saja. Saat ini: **43 pasangan diuji, 43 lolos.**

## 0. Cara memakai brief ini

1. Baca §1–§3 dulu. Itu yang menentukan kenapa sesuatu boleh atau tidak boleh digambar.
2. §4 (token) adalah nilai mentah — salin apa adanya, jangan diperhalus "sedikit saja".
3. §6 (komponen) dan §7 (layar) adalah daftar kerja menggambar.
4. §10 adalah daftar hal yang **tidak boleh diubah** desainer. Kalau sebuah gagasan desain
   bertabrakan dengan §10, gagasannya yang gugur.

Kalau satu keputusan di sini terasa salah setelah digambar, ubah **brief-nya dulu**,
baru gambarnya. Brief yang tertinggal dari gambar adalah cara paling cepat kehilangan konsistensi.

---

## 1. Siapa yang memakai aplikasi ini

Empat kelompok, dengan kemampuan dan tekanan yang sangat berbeda. Desain yang benar untuk satu
kelompok bisa berbahaya untuk kelompok lain.

| Pengguna | Perangkat & keadaan | Yang menentukan desain |
|---|---|---|
| **Juri (3–5 orang)** | HP **orientasi landscape (844×390)**, berdiri di tepi matras, menekan cepat saat serangan terjadi | Tombol besar, jarak antar sudut lebar, nol elemen yang bisa tertekan tak sengaja. Selip 8px ke samping = nilai jatuh ke lawan |
| **Wasit, operator, Dewan Wasit Juri** | Wasit memakai HP **landscape (844×390)**; operator dan Dewan Wasit Juri di layar lebar. Semua di bawah tekanan waktu | Istilah peraturan sebagai label utama, aksi berdampak selalu dikonfirmasi, keadaan partai selalu terbaca sekilas |
| **Panitia & official kontingen** | Laptop, sesi panjang, sering hanya sekali setahun memakainya | Alur berurutan yang menyatakan langkah berikutnya, nol jargon komputer, prasyarat dinyatakan bukan disembunyikan |
| **Penonton & operator siaran** | HP di tribun, layar besar, kanvas vMix | Angka dominan, hasil tidak bisa disalahbaca, overlay transparan tanpa satu piksel pun yang bukan informasi |

Yang mengikat semuanya: **tidak seorang pun dari mereka dilatih memakai aplikasi ini.**
Mereka membukanya di hari kejuaraan dan harus langsung bisa.

---

## 2. Lima prinsip

Tiap prinsip harus bisa diperiksa, bukan sekadar disepakati.

### 2.1 Kontras dan konsisten
Setiap pasangan teks/latar ≥ **4.5:1**; teks besar (≥24px, atau ≥19px tebal) dan tepi kendali
≥ **3:1**. Satu arti = satu warna di seluruh aplikasi. **Warna tidak pernah menjadi satu-satunya
pembawa makna** — selalu berpasangan dengan label, ikon, atau bentuk.

### 2.2 Nol jargon komputer
Kata yang tidak dipakai panitia kejuaraan tidak muncul di layar. Kamus penggantinya di §8.
Pesan galat menyebut **apa yang harus dilakukan**, bukan apa yang gagal.

### 2.3 Aman dari salah tekan
Aksi berdampak minta konfirmasi yang menyebut akibatnya dengan kalimat lengkap. Aksi yang bisa
dibatalkan menampilkan pembatalannya secara terlihat. Mode simulasi ditandai jelas supaya panitia
berani berlatih. Pola lengkapnya di §9.

### 2.4 Satu layar, satu pertanyaan
Tiap layar menjawab satu pertanyaan ("siapa yang belum diverifikasi?", "partai mana berikutnya?").
Layar yang menjawab tiga pertanyaan sekaligus dipecah.

### 2.5 Prasyarat dinyatakan, bukan disembunyikan
Tombol yang belum boleh ditekan benar-benar nonaktif **dan** disertai kalimat alasannya di dekatnya.
Tolok ukur yang sudah benar di aplikasi ini: halaman **Setelan peraturan** — rujukan pasal per isian,
pengelompokan bertajuk, pemisahan tegas antara ketentuan naskah dan keputusan penyelenggara.
Layar lain harus mencapai standar itu.

---

## 3. Arah rupa: "Matras"

**Dua suasana, satu keluarga.** Admin terang seperti kertas kerja panitia; gelanggang, live publik, dan overlay gelap. Yang menyatukan keduanya: satu palet aksi, satu tipografi, satu geometri sudut.

> **Lapisan "upacara" yang sempat direncanakan dibatalkan** — lihat §4.4. Tidak ada palet hangat, tidak ada huruf serif, tidak ada tekstur latar, tidak ada garis rangkap kop di permukaan mana pun.

### Dua dialek, satu sistem

Token, istilah, dan disiplin warna **sama di seluruh aplikasi**. Yang berbeda hanya kepadatan dan geometri, karena konteks bacanya berbeda:

| | Panel gelanggang & admin | Permukaan publik |
|---|---|---|
| Radius | `6px` | **`0`** |
| Kepadatan | Longgar — kontrol ≥64px, satu keputusan per blok | Padat — baris tabel 40px, banyak baris sekaligus |
| Lompatan skala | Sedang (11 → 52px) | Ekstrem (11 → 110px) |
| Ikon | Dipakai bila membawa arti: teknik, hukuman, status | **Nol ikon** — tipografi dan warna sudut sudah cukup |
| Kalimat penjelas | Boleh, untuk menyatakan prasyarat dan akibat | Tidak ada — hanya data dan label |

**Yang tidak boleh berbeda:** nilai warna, istilah naskah, arti tiap warna, urutan petak hukuman, dan merah kiri biru kanan.

**Bidang sudut penuh** (`#7a1418` / `#0c2a63`) dipakai di **semua papan skor**, termasuk halaman publik — identitas sudut harus terbaca sama di mana pun. Di **daftar** (jadwal, bagan, riwayat) sudut ditandai batang tepi 3–5px, karena bidang penuh pada setiap baris akan menutupi seluruh halaman.

### Batas yang mengikat

| Permukaan | Perlakuan |
|---|---|
| Panel gelanggang — juri, wasit, operator, Dewan Wasit Juri, keberatan, Jurus, Ketua Pertandingan | **Gelap.** Rata, nol ornamen, nol tekstur, nol gradien |
| Overlay siaran vMix | **Gelap, transparan.** Tidak menambah satu piksel pun yang bukan informasi |
| Admin/panitia | **Terang.** Kertas kerja, kontras tinggi, tanpa kaca dan tanpa noise |
| Landing, beranda, live publik, bagan publik, medali, halaman masuk | **Gelap, palet yang sama dengan panel.** Yang membedakannya dari panel adalah kepadatan dan skala — lihat §7 Rombongan 2 |
| Berita acara, rekap medali PDF | **Terang.** Hitam di atas putih, tanpa hiasan apa pun |

## 4. Token

Tiga kelompok, dipisah tegas di berkas CSS supaya tidak saling bocor:

- **Inti terang** (`--k-*`, "kertas") — admin/panitia dan dokumen cetak
- **Inti gelap** (`--g-*`, "gelanggang") — panel gelanggang, live publik, overlay
- **Upacara** (`--u-*`) — **hanya** permukaan publik dan dokumen cetak. Tidak pernah dimuat oleh bundel panel gelanggang maupun overlay

### 4.1 Inti terang — permukaan

| Token | Nilai | Dipakai untuk |
|---|---|---|
| `--k-kertas` | `#f4f2ee` | Latar halaman. Putih tulang hangat, bukan abu biru |
| `--k-kertas-naik` | `#ffffff` | Kartu, panel, baris tabel |
| `--k-kertas-turun` | `#e9e6e0` | Baris selang-seling, kepala tabel |
| `--k-kertas-dalam` | `#dcd8d0` | Isian yang tenggelam, bidang nonaktif |
| `--k-garis` | `#d4cfc6` | Pembatas dekoratif. Tidak pernah jadi satu-satunya pembawa makna |
| `--k-tepi-kendali` | `#7d7668` | **Tepi isian, tombol, kotak centang.** Wajib ≥3:1 |

### 4.2 Inti terang — teks, aksi, status

| Token | Nilai | Di atas | Rasio | Peran |
|---|---|---|---|---|
| `--k-tinta` | `#17161a` | `#f4f2ee` | **16.11** | Teks utama, judul |
| `--k-tinta` | `#17161a` | `#ffffff` | **18.01** | Teks utama di kartu |
| `--k-tinta-kedua` | `#45434a` | `#f4f2ee` | **8.72** | Keterangan, label sekunder |
| `--k-tinta-redup` | `#5f5c66` | `#f4f2ee` | **5.85** | Teks paling redup yang masih boleh ada |
| `--k-tinta-redup` | `#5f5c66` | `#ffffff` | **6.54** | Idem, di kartu |
| `--k-aksi` / `--k-aksi-teks` | `#17161a` / `#ffffff` | — | **18.01** | Tombol utama: bidang tinta, teks putih |
| `--k-aksi-lembut` | `#e6e3dd` | teks `#17161a` | **14.06** | Tombol kedua |
| `--k-sukses` | `#14663f` | `#f4f2ee` | **6.26** | Lunas, terverifikasi, disahkan |
| `--k-sukses-lembut` | `#dcefe4` | teks `#14663f` | **5.83** | Badge sukses |
| `--k-perhatian` | `#7a4a00` | `#f4f2ee` | **6.69** | Menunggu, belum lengkap |
| `--k-perhatian-lembut` | `#f7ecd6` | teks `#7a4a00` | **6.39** | Badge perhatian |
| `--k-bahaya` | `#a3221c` | `#f4f2ee` | **6.70** | Ditolak, gagal, aksi merusak |
| `--k-bahaya-lembut` | `#f8e3e1` | teks `#a3221c` | **6.09** | Badge bahaya |
| `--k-bahaya` sebagai bidang | `#a3221c` | teks `#ffffff` | **7.49** | Tombol hapus yang sudah dikonfirmasi |

**Tidak ada token aksen berwarna.** Aksi utama berupa bidang tinta, bukan biru korporat. Alasannya di §5.

### 4.3 Inti gelap — gelanggang, live publik, overlay

| Token | Nilai | Di atas | Rasio | Peran |
|---|---|---|---|---|
| `--g-latar` | `#0b0b0c` | — | — | Latar papan. Gelap netral, bukan hitam pekat |
| `--g-panel` | `#131316` | — | — | Blok informasi di atas latar |
| `--g-garis` | `#2a2a2c` | — | — | Pembatas dekoratif |
| `--g-tepi-kendali` | `#6a6a70` | `#131316` | **3.45** | Tepi tombol dan isian. Wajib ≥3:1 |
| `--g-teks` | `#ffffff` | `#0b0b0c` | **19.67** | Teks utama |
| `--g-teks` | `#ffffff` | `#131316` | **18.54** | Teks utama di panel |
| `--g-teks-redup` | `#8a8a90` | `#0b0b0c` | **5.73** | Label sekunder |
| `--g-teks-redup` | `#8a8a90` | `#131316` | **5.40** | Idem, di panel |
| `--g-merah` | `#d42027` | teks `#ffffff` | **5.20** | **Sudut merah.** Ditetapkan peraturan |
| `--g-biru` | `#12439e` | teks `#ffffff` | **9.04** | **Sudut biru.** Ditetapkan peraturan |
| `--g-merah-dalam` | `#7a1418` | teks `#fff0f0` | **9.78** | Blok informasi sisi merah |
| `--g-merah-dalam` | `#7a1418` | teks `#fff5f5` | **10.12** | Label sekunder sisi merah |
| `--g-biru-dalam` | `#0c2a63` | teks `#f2f6ff` | **12.71** | Blok informasi sisi biru |
| `--g-aksi` / `--g-aksi-teks` | `#e8e8ea` / `#111114` | — | **15.40** | Tombol aksi: bidang terang, teks gelap |
| `--g-emas` | `#c9a227` | `#0b0b0c` | **8.13** | **Hanya juara dan medali** |
| `--g-emas` | `#c9a227` | `#131316` | **7.67** | Idem, di panel |
| `--g-pembinaan` | `#6b6b73` | teks `#ffffff` | **5.28** | Hukuman tanpa pengurangan nilai |
| `--g-teguran` | `#d98324` | teks `#1a1207` | **6.37** | Hukuman −1 / −2 |
| `--g-peringatan` | `#d42027` | teks `#ffffff` | **5.20** | Hukuman −5 / −10 |
| `--g-hidup` | `#4ade80` | `#0b0b0c` | **11.29** | Indikator sistem hidup / tersambung |
| `--g-hidup` | `#4ade80` | `#131316` | **10.64** | Idem, di panel |
| `--g-tepi-petak` | `#8a8a90` | `#0b0b0c` | **5.73** | **Tepi petak dan bidang "belum terisi".** Sama nilainya dengan teks redup |
| `--g-tepi-petak` | `#8a8a90` | `#7a1418` | **3.15** | Idem, di dalam bidang sudut merah |
| `--g-tepi-petak` | `#8a8a90` | `#0c2a63` | **4.01** | Idem, di dalam bidang sudut biru |
| `--g-teks-mati` | `#b0b0b6` | `#0b0b0c` | **13.24** | Teks pada kontrol nonaktif |
| `--g-teks-merah-samar` | `#e8b4b6` | `#7a1418` | **6.00** | Tingkat ketiga di dalam blok merah |
| `--g-teks-biru-samar` | `#a8b8e0` | `#0c2a63` | **6.94** | Tingkat ketiga di dalam blok biru |

> **Aturan "mati bukan bidang".** Apa pun yang berarti *belum terisi*, *belum menyala*, atau *tidak bisa ditekan* digambar sebagai **tepi `#8a8a90`**, bukan bidang abu. Bidang abu gelap yang sempat dipakai di kanvas — `#2e2e33`, `#3a3a3d`, `#5a5a5e`, dan tepi `rgba(255,255,255,0.26–0.45)` — semuanya diukur ulang dan berkontras **1.41–2.99**: petak hukuman kosong dan pip babak praktis tidak terlihat, hampir seburuk K3 dulu.

> **Tidak ada rgba untuk teks, titik, atau tepi.** Nilai beralpha tidak bisa dibaca dari berkas dan mudah lolos dari pemeriksaan. Yang tersisa hanya untuk bayangan, latar catatan, dan transparansi vMix.

> **Token yang dihapus:** `--silat-teks-samar` (`#6e6e76`). Inilah penyebab temuan K3 di audit — istilah resmi hukuman ditulis dengan warna berkontras 1.03–1.74 dan praktis tidak terbaca oleh wasit. Tidak ada penggantinya. Kalau sebuah teks terlalu tidak penting untuk `--g-teks-redup`, teks itu tidak perlu ada.

### 4.4 Lapisan upacara — DIBATALKAN

Palet krem–kunyit–coklat dan huruf serif dibuang dari seluruh permukaan publik.

**Kenapa.** Warna hangat tanah adalah tebakan default untuk "nuansa Nusantara" — ia tidak datang dari sumber mana pun. Ditambah Petrona dan garis rangkap kop, hasilnya tiruan piagam: pastiche formalitas, bukan formalitas sungguhan. Itulah yang membuat halaman publik terbaca sebagai keluaran mesin, bukan sebagai kejuaraan.

**Penggantinya datang dari sumber nyata:** seragam pertandingan pencak silat IPSI berwarna **hitam polos**. Permukaan publik memakai palet yang sama dengan panel gelanggang — hitam, putih, merah/biru sudut. Yang membedakan halaman publik dari panel bukan warna tempelan, melainkan **kepadatan dan skala**.

Konsekuensi: bundel ketiga `upacara.*` tidak jadi dibuat, dan tidak ada huruf ketiga yang perlu di-bundle.

### 4.5 Tipografi

| Peran | Huruf | Dipakai di |
|---|---|---|
| Antarmuka | **Space Grotesk** | Semua permukaan. Satu-satunya huruf antarmuka |
| Angka | **IBM Plex Mono** | Skor, timer, nomor partai, berat badan, nominal — apa pun yang dibandingkan sebaris ke bawah |

**Dua huruf saja di seluruh aplikasi.** Tidak ada huruf display. Keduanya di-bundle lewat npm, tidak pernah dari CDN — gelanggang sering tanpa internet.

**Skala** (satuan px, tinggi baris dalam kurung):

| Nama | Ukuran | Dipakai untuk |
|---|---|---|
| `angka-raksasa` | 96 (1.0) | Skor di panel operator dan papan gelanggang |
| `angka-besar` | 56 (1.0) | Skor di papan skor panel, timer gelanggang |
| `angka-overlay` | 44 (1.0) | Skor di scorebug siaran |
| `judul-1` | 32 (1.2) | Judul halaman |
| `judul-2` | 24 (1.25) | Judul bagian |
| `judul-3` | 19 (1.3) | Judul kartu |
| `badan` | 16 (1.55) | Teks isi. **Batas bawah untuk teks yang harus dibaca** |
| `kecil` | 14 (1.45) | Label, keterangan tabel |
| `mikro` | 12 (1.4) | Badge dan cap waktu saja. Tidak pernah untuk kalimat |

### 4.6 Jarak, radius, ukuran sentuh

**Jarak** kelipatan 4: `4 · 8 · 12 · 16 · 24 · 32 · 48 · 64`.

**Radius:** `--r-kecil 4px` (badge, isian) · `--r-sedang 8px` (tombol, kartu) · `--r-besar 12px` (modal, panel besar). Tidak ada bentuk pil, tidak ada lingkaran kecuali avatar dan indikator juri.

**Ukuran sentuh minimum:**

| Konteks | Minimum | Alasan |
|---|---|---|
| Panel gelanggang | **64px** | Ditekan cepat sambil berdiri, tanpa sempat melihat lama |
| Tombol nilai juri | **≥390×100px** | Enam tombol memenuhi layar landscape 844×390 tanpa gulir — dua kolom sudut, tiga teknik ke bawah |
| Lorong antara kolom merah dan biru | **≥24px** | Selip ke samping memberikan nilai kepada lawan. Sela antar baris boleh tetap 8px |
| Admin di laptop | **44px** | Sasaran tetikus, sesi panjang |

### 4.7 Elevasi

Tidak ada kaca, tidak ada butiran noise, tidak ada grid latar, tidak ada bevel. Semua itu warisan RizzxxUI dan dibuang. Kedalaman dinyatakan lewat **warna permukaan dan satu garis**; bayangan hanya untuk lapisan yang benar-benar mengambang:

| Token | Nilai | Dipakai |
|---|---|---|
| `--bayang-angkat` | `0 1px 2px rgb(20 18 14 / 0.08)` | Kartu, baris yang bisa diklik |
| `--bayang-apung` | `0 8px 24px rgb(20 18 14 / 0.14)` | Dropdown, drawer |
| `--bayang-modal` | `0 24px 64px rgb(20 18 14 / 0.28)` | Modal dan dialog konfirmasi |

Panel gelanggang **tidak memakai bayangan sama sekali** — dibaca dari jarak jauh, dan bayangan hanya mengaburkan tepi.

### 4.8 Fokus keyboard

Cincin ganda, bukan warna tunggal: **2px bidang latar** di dalam, **2px tinta** (terang) atau **2px putih** (gelap) di luar. Pola ini terbaca di atas permukaan apa pun tanpa menambah warna baru, dan tidak bergantung pada aksen. Fokus **tidak pernah** dihilangkan.

---

## 5. Aturan pemakaian warna

Warna di aplikasi ini punya arti tetap. Begitu satu warna dipakai untuk dua hal, ia berhenti berarti apa pun.

| Warna | Artinya | Tidak pernah dipakai untuk |
|---|---|---|
| **Merah `#d42027`** | Sudut merah pesilat | Tombol aksi, peringatan sistem, tautan |
| **Biru `#12439e`** | Sudut biru pesilat | Tombol utama, tautan, aksen merek |
| **Emas `#c9a227`** | Juara, medali, baris pemenang | Tombol, eyebrow, hiasan, badge biasa |
| **Tinta / terang (aksi)** | Aksi utama yang bisa ditekan | Bidang informasi pasif |
| **Hijau, kuning-tanah, merah-bata (status)** | Sukses, menunggu, gagal | Identitas sudut |
| **Palet upacara** | Suasana permukaan publik dan cetak | Panel gelanggang, overlay, admin |

### Kenapa aksi utama tidak berwarna

Boilerplate memberi aplikasi ini aksen biru `#3d5fe0` untuk tombol utama. Di layar yang sama, biru
adalah **identitas sudut pesilat**. Audit sudah menemukan akibatnya sekali: tombol "Akhiri partai"
berwarna merah terbaca seolah berhubungan dengan sudut merah.

Karena merah, biru, dan emas semuanya sudah punya arti yang ditetapkan peraturan, aksi utama tidak
punya warna tersisa yang aman. Jadi ia **tidak memakai warna sama sekali**: bidang tinta dengan
teks putih di suasana terang, bidang terang dengan teks gelap di suasana gelap. Kontrasnya tertinggi
di seluruh sistem (18.01 dan 15.40) dan tidak mungkin tertukar dengan apa pun.

Konsekuensi yang diterima: **tautan tidak berwarna** — ia bergaris bawah dan bertinta penuh.
Ini disengaja, bukan kelalaian.

### Merah bahaya vs merah sudut

Keduanya merah, dan itu memang risiko. Yang memisahkan bukan rona melainkan **peran bentuk**:

- **Sudut** selalu berupa **bidang besar** berlabel "Sudut Merah" / "Sudut Biru", memakai `--g-merah`.
- **Bahaya** selalu berupa **tombol kecil berikon dengan kata kerja** ("Hapus", "Batalkan", "Tolak"),
  memakai `--k-bahaya` yang lebih gelap, dan **hanya ada di permukaan terang**.
- **Di panel gelanggang dan overlay tidak ada tombol bahaya berwarna sama sekali.** Aksi merusak di sana
  memakai `--g-aksi` dan dilindungi dialog konfirmasi.

---

## 6. Komponen yang perlu digambar

Bukan 60 komponen RizzxxUI. Daftar ini diturunkan dari pemakaian nyata di 113 berkas view.

### 6.1 Inti — wajib digambar dalam semua keadaannya

Tiap komponen digambar dalam keadaan: **normal · tertunjuk · ditekan · fokus keyboard · nonaktif · galat** (untuk isian).

| Komponen | Varian yang perlu ada |
|---|---|
| **Tombol** | Utama · Kedua · Berbahaya · Polos · Besar-gelanggang (64px) |
| **Ikon** | Satu set garis 1.5px, sudut tumpul. Wajib: tambah, ubah, hapus, cari, saring, unduh, cetak, kembali, lanjut, centang, silang, peringatan, kunci, jam, orang, kontingen, gelanggang, bagan, medali |
| **Isian teks** | Dengan label, keterangan bantu, dan pesan galat yang menyebut cara memperbaikinya |
| **Pilihan & tanggal** | Sama seperti isian, plus keadaan terbuka |
| **Kartu** | Polos · Berjudul · Bisa diklik |
| **Tabel** | Kepala, baris selang-seling, baris terpilih, kolom aksi, keadaan kosong, toolbar (cari + saring + jumlah hasil) |
| **Badge status** | Sukses · Menunggu · Gagal · Netral. **Selalu berikon**, tidak pernah warna saja |
| **Callout** | Keterangan · Perhatian · Bahaya. Berjudul dan berisi kalimat lengkap |
| **Keadaan kosong** | Menyebutkan **apa yang membuka isinya**, bukan sekadar "tidak ada data" |
| **Modal** | Modal isian biasa |
| **Dialog konfirmasi** | Lihat §9. Ini komponen paling penting di daftar ini |
| **Drawer** | Panel samping untuk rincian |
| **Remah jejak** | Penuh di layar lebar; ringkas (naik satu tingkat + halaman sekarang) di HP |
| **Navigasi samping** | Penuh · Ciut · Drawer di HP. Dikelompokkan menurut urutan kerja kejuaraan |
| **Kepala halaman** | Judul, satu kalimat penjelas, tombol aksi |
| **Chip saring** | Dengan jumlah per chip dan keadaan terpilih |
| **Langkah / stepper** | Menyatakan langkah keberapa dari berapa, dan apa yang menghambat langkah berikutnya |
| **Toast** | Berhasil · Gagal. Hilang sendiri hanya untuk yang berhasil |

### 6.2 Khas silat — ini jantung sistemnya

Digambar terpisah dan lebih teliti. Semuanya di suasana gelap.

| Komponen | Yang harus benar |
|---|---|
| **Papan skor sudut** | Merah kiri, biru kanan. Angka dominan (`angka-besar`), nama pesilat jauh lebih kecil, nama kontingen di bawahnya. **Sisi biru bercermin** — nilai, nama, dan kontingen rata kanan, menjauh dari tengah |
| **Tombol nilai juri** | Enam tombol memenuhi layar **landscape 844×390** tanpa gulir: dua kolom sudut, tiga teknik ke bawah. Tiap tombol memuat ikon, nama teknik, dan angka nilai dalam satu baris mendatar. Lorong merah–biru ≥24px |
| **Timer** | IBM Plex Mono, di tengah, dengan penanda babak "BABAK 2/3" di dekatnya |
| **Indikator juri per teknik** | Tiga titik juri untuk **tiap teknik di tiap sudut** — enam kelompok. Kelompok yang jendelanya sedang berjalan diberi tepi menyala dan sisa detik. Ambang "2 dari 3" ditulis sebagai kalimat, bukan disimpulkan pembaca. Tampil di panel Operator; di overlay siaran hanya sebagai kilasan selama jendela 2 detik lalu hilang. **Tidak pernah tampil di panel juri** sebelum jendela tutup — juri yang melihat rekannya sudah menekan akan ikut menekan |
| **Petak hukuman** | **Tujuh petak berposisi tetap**, dikelompokkan `2 Pembinaan · 2 Teguran · 3 Peringatan` mengikuti tangga Pasal 11.6.d.4. Jumlah dibaca dari berapa petak menyala — **tidak ada angka**. **Setiap petak selalu memuat ikon jenisnya** — lingkaran, segitiga, oktagon — supaya petaknya terbaca untuk apa bahkan sebelum ada hukuman; yang membedakan terisi dari kosong adalah bidang warnanya, bukan ada-tidaknya ikon. Petak Peringatan ketiga bergaris putus karena mengisinya berarti diskualifikasi. Urutan sama di kedua sudut |
| **Blok sudut** | Identitas pesilat: nama, kontingen, sudut. Sisi merah dan biru **wajib terbaca setara** |
| **Pohon bagan** | Terbaca di HP maupun layar lebar; jalur pemenang jelas |
| **Kartu partai** | Nomor partai, kelas, gelanggang, jam, kedua pesilat, status |
| **Kartu "Partai saya"** | Untuk aparat yang login di HP: peranku, partai apa, di gelanggang mana, jam berapa, titik warna sudut, badge status |
| **Scorebug siaran** | 1920×1080, latar transparan, bawah-tengah. Skor 44px, nama 22px, timer 30px, babak, deret hukuman |
| **Papan hasil** | Pemenang dan **alasan menang yang sudah diterjemahkan** — "Menang Diskualifikasi", bukan `diskualifikasi` mentah |
| **Papan medali** | Emas boleh dipakai di sini |

### 6.3 Dibuang — tidak digambar ulang

Sisa boilerplate yang dipakai ≤5 kali dan tidak dimengerti pengguna awam:
`avatar-stack` · `presence-dot` · `command-palette` · `tag-input` · `dropzone` · `slider` ·
`ratio-bar` · `skeleton` · `code-block` · `kbd` · `accordion` · `wizard` · `timeline` · `segmented`.

Command palette layak disebut khusus: ia satu-satunya isian pencarian di halaman Bagan yang memuat
174 baris, tersembunyi di balik pintasan papan tik, dan tidak akan pernah ditemukan panitia yang
belum pernah memakai aplikasi web. Penggantinya adalah pencarian yang terlihat di toolbar tabel.

---

## 7. Layar yang perlu digambar

Tidak semua 68 layar digambar. Yang digambar adalah **pola**; sisanya mengikuti pola itu.
Urutan di bawah adalah urutan menggambar — rombongan 1 dan 2 menentukan seluruh bahasa visual.

### Rombongan 1 — Hari-H gelanggang (gelap murni)

| Layar | Pertanyaan yang dijawab | Wajib ada |
|---|---|---|
| **Juri** (HP landscape 844×390) | "Nilai apa yang saya berikan sekarang?" | Enam tombol tanpa gulir, dua kolom sudut · lorong merah–biru ≥24px · kepala layar setipis mungkin · nama kedua pesilat di kepala, merah kiri biru kanan |
| **Wasit** (HP landscape 844×390) | "Hukuman apa yang saya jatuhkan, dan ke siapa?" | Pembinaan / Teguran / Peringatan sebagai **label utama** (sebutan sehari-hari boleh di baris kedua) · pilih sudut lebih dulu · seluruh kontrol ≥64px · hitungan jatuh |
| **Operator** | "Di mana partai ini sekarang?" | Papan skor mengisi tinggi layar · timer + babak · **kendali duduk di kolom tengah bersama timer, tidak pernah di dalam atau di bawah blok sudut** — tombol selebar layar di bawah blok biru terbaca sebagai milik sudut biru · lebar tombol boleh menyempit, tingginya tetap 64px |
| **Dewan Wasit Juri** | "Nilai mana yang keliru dan siapa yang memberi?" | Riwayat berjam:menit:detik **dan** identitas juri per baris · tombol batalkan per baris · keadaan terkunci setelah disahkan beserta alasannya |
| **Keberatan** | "Apa yang diprotes, dan apa konteks angkanya?" | Papan skor kedua sudut ikut tampil · rincian protes · putusan |
| **Jurus — juri** (HP landscape 844×390) | "Berapa nilai penampilan ini?" | Papan tik angka sendiri, bukan keyboard perangkat · alasan pengurangan dipilih dari empat sebab Pasal 12.1.e, tidak diketik |
| **Jurus — operator** | "Penampilan siapa sekarang?" | Timer · daftar penampil · sahkan |

### Rombongan 1b — Verifikasi juri (perluasan cakupan yang disetujui)

Batas "tanpa fitur baru" **dibatalkan khusus untuk verifikasi juri**, atas keputusan eksplisit. Ini satu-satunya pengecualian; sisa pekerjaan tetap rupa + alur.

**Dasar naskah.** Pasal 13 menugaskan Juri *"memberi jawaban tentang verifikasi dari Ketua Pertandingan maupun Wasit"*. Pasal 15 menyebut keputusan verifikasi termasuk yang bisa diprotes pelatih lewat Kartu Protes. Naskah **tidak** mengatur bentuk jawaban juri — sama seperti `ambang_sepakat` dan `window_ms`, bentuknya jadi keputusan implementasi.

**Bentuk yang dipilih:** juri memilih **Merah / Biru / Tidak ada**. Ambang sama dengan penilaian biasa, 2 dari 3.

| Layar | Pertanyaan yang dijawab | Wajib ada |
|---|---|---|
| **Wasit meminta verifikasi** (844×390) | "Apa yang saya tanyakan ke juri?" | Jenis pertanyaan (jatuhan / pelanggaran) · kejadian yang mana, boleh dilewati · peringatan bahwa panel juri berhenti menerima nilai |
| **Juri menjawab** (844×390) | "Sudut mana?" | **Mengambil alih panel juri sepenuhnya** — tombol nilai tidak boleh tersisa di belakangnya · merah kiri biru kanan seperti biasa · jawaban tidak terlihat juri lain sampai ketiganya selesai |
| **Hasil polling** (1440×900) | "Apa jawaban juri, dan apa akibatnya?" | Jawaban per juri berikut cap waktu · hitungan per pilihan · pernyataan ambang sudah tercapai atau belum · akibatnya dinyatakan **sebelum** diterapkan |

**Panel Ketua Pertandingan** kini ikut digambar. Role `ketua-pertandingan` sudah ada di `SilatRoleSeeder` dengan izin lengkap — deskripsinya bahkan sudah berbunyi "memimpin verifikasi juri" — tapi belum punya satu pun layar.

| Layar | Pertanyaan yang dijawab | Wajib ada |
|---|---|---|
| **Ketua Pertandingan** (1440×900) | "Apa yang menghambat kelancaran gelanggang sekarang?" | Pandangan **seluruh gelanggang sekaligus**, bukan satu partai · antrean "butuh keputusanmu" dengan tenggat tiap perkara · pemicu verifikasi juri · hentikan pertandingan · waktu penampilan Jurus, yang menurut Pasal 13.4.d.9 adalah tanggung jawabnya |

Tugas naskah yang **belum punya padanan di sistem** ditandai badge `BELUM ADA` di dalam artboard itu sendiri, bukan hanya di catatan: isyarat keluar garis Jurus (13.4.d.7), teruskan masalah ke Delegasi Teknik (13.4.d.6), keluarkan pendamping pesilat lewat Dewan Wasit Juri (13.4.d.5).

**Konsekuensi implementasi:** rute, controller, event realtime (panel juri harus beralih serentak), dan kemungkinan tabel baru untuk menyimpan pertanyaan beserta jawaban tiap juri. Hasilnya masuk riwayat Dewan Wasit Juri dan berita acara.

### Rombongan 2 — Wajah publik (gelap + upacara)

> **Halaman publik adalah papan pengumuman GOR, bukan halaman jualan produk.** Orang membukanya untuk satu hal — skor sekarang, jadwal, lawan berikutnya — bukan untuk dibujuk. Informasi mendahului ajakan; tipografi yang membedakan, bukan kotak dan ikon.
>
> **Yang dilarang di permukaan publik**, karena semuanya menandai halaman sebagai template dan bukan sebagai kejuaraan:
>
> - Baris kartu berikon sejajar ("Bagan / Medali / Jadwal") dengan judul dan satu kalimat deskripsi
> - Hero mengambang: eyebrow mono uppercase berwarna, judul raksasa, baris metadata bertitik pemisah
> - Tekstur latar diagonal beropasitas rendah
> - Ikon generik yang tidak membawa arti — perisai bercentang, piala, lingkaran-i
> - Kalimat deskripsi yang bisa ditempel ke aplikasi mana pun
>
> Ikon dipakai **hanya** kalau tanpa ikon jadi ambigu. Di seluruh Beranda, Medali, dan Masuk hasilnya nol ikon — warna sudut dan tipografi sudah cukup.



| Layar | Pertanyaan yang dijawab | Wajib ada |
|---|---|---|
| **Beranda kejuaraan** | "Kejuaraan apa yang sedang berjalan, dan di mana saya menontonnya?" | Kejuaraan berjalan & mendatang · tautan papan skor per gelanggang · kop berhuruf display · **nol harga, nol bahasa jualan** |
| **Live gelanggang** | "Skor sekarang berapa?" | Papan skor gelap murni · babak · hukuman tertulis "Pembinaan/Teguran/Peringatan" · hasil dengan alasan menang yang sudah diterjemahkan |
| **Bagan publik** | "Lawan berikutnya siapa?" | Pohon bagan terbaca di HP |
| **Medali** | "Kontingen mana yang unggul?" | Peringkat medali · emas boleh dipakai |
| **Masuk** | "Bagaimana saya masuk?" | Isian sesedikit mungkin · **nol testimoni, nol angka karangan** · menyebutkan siapa saja yang masuk lewat sini |

### Rombongan 3 — Siaran (kanvas 1920×1080, latar transparan)

Scorebug · Hasil · Rincian · Profil pesilat · Bagan. Digambar di atas **latar hijau** saat menggambar,
supaya jelas mana yang transparan. Tidak ada satu piksel pun yang bukan informasi.

### Rombongan 4 — Alur kejuaraan panitia (terang murni)

Navigasi disusun ulang mengikuti **urutan kerja kejuaraan**, bukan daftar tabel database:

> Persiapan → Pendaftaran → Verifikasi → Timbang badan → Bagan → Jadwal → Penugasan aparat → Hari-H → Rekap

| Layar | Pertanyaan yang dijawab | Wajib ada |
|---|---|---|
| **Beranda panitia** | "Apa yang perlu saya kerjakan hari ini?" | Partai hari ini per gelanggang · jumlah menunggu verifikasi · langkah berikutnya · **bukan KPI generik** |
| **Panel kejuaraan** | "Kejuaraan ini sudah sampai mana?" | Stepper tahapan dengan penghambat tiap tahap dinyatakan |
| **Pola daftar** (kontingen, atlet, gelanggang, pengguna) | "Siapa/apa yang ada, dan mana yang bermasalah?" | Pencarian terlihat · chip saring berjumlah · keadaan kosong menjelaskan · aksi massal aman |
| **Pola formulir** (tambah/ubah apa pun) | "Apa yang harus saya isi?" | Satu kolom · label di atas isian · keterangan bantu · galat menyebut cara memperbaiki |
| **Pendaftaran** | "Siapa yang sudah didaftarkan kontingen saya?" | Tanding & Jurus terpisah · status per pendaftaran |
| **Verifikasi** | "Siapa yang belum saya periksa?" | Antrean menunggu di atas · setujui/tolak dengan alasan · berkas terlihat |
| **Timbang badan** | "Siapa yang belum ditimbang, dan siapa yang gagal?" | Isian berat besar · batas kelas tertera · hasil lolos/gagal langsung terbaca |
| **Bagan** | "Kelas mana yang siap disusun?" | Saringan bawaan menyembunyikan kelas kosong · prasyarat dinyatakan · kunci/buka kunci dikonfirmasi |
| **Jadwal** | "Partai mana di gelanggang mana, urutan berapa?" | Per gelanggang · urutan bisa diubah · bentrok terlihat |
| **Penugasan aparat** | "Siapa wasit dan juri partai ini?" | Peran per orang · bentrok penugasan terlihat |
| **Rekap** | "Apa hasil akhirnya?" | Medali · peserta · unduhan · keadaan kosong menjelaskan syaratnya |
| **Berita acara** | "Apa bukti resmi partai ini?" | **Terang + upacara.** Kop berhuruf display, bingkai; isi data hitam-putih tanpa tekstur. Kolom Pesilat, Juri, Waktu, Dicatat oleh |

### Rombongan 5 — Sistem

Satu layar pengaturan generik. Pengguna, hak akses, dan pemetaan mengikuti **pola daftar** dan
**pola formulir** dari rombongan 4 — tidak perlu digambar sendiri.

---

## 8. Bahasa

### 8.1 Kamus pengganti

| Jangan | Pakai |
|---|---|
| Dashboard | Beranda |
| Resource, entity, record | Data, berkas, isian |
| Role, permission | Peran, hak akses |
| Filter | Saring |
| Export, download | Unduh |
| Import | Unggah |
| Submit | Kirim / Simpan |
| Bulk action | Pilih beberapa |
| Sync, refresh | Perbarui |
| Workspace | (dihapus, sebut nama kejuaraannya) |
| Panel | (dihapus dari teks pengguna; sebut nama layarnya) |
| Log, history | Riwayat |
| Settings, config | Setelan |
| Valid / invalid | Sesuai / belum sesuai |
| Error | (sebut masalahnya, jangan sebut kata ini) |

### 8.2 Istilah pencak silat yang mengikat

| Naskah | Yang wajib tampil |
|---|---|
| Pembinaan · Teguran · Peringatan | **Label utama** hukuman di semua layar. Sebutan sehari-hari boleh mendampingi, tidak boleh menggantikan |
| Pesilat | Dokumen resmi dan layar pertandingan. "Atlet" hanya untuk entitas data pra-acara |
| Dewan Wasit Juri | Satu sebutan di seluruh teks pengguna |
| Pukulan (1) · Tendangan (2) · Jatuhan (3) | Lengkap dengan angka nilainya di tombol |
| Sudut merah · Sudut biru | Tidak pernah tertukar, tidak pernah disingkat |
| Tangga hukuman (Pasal 11.6.d.4) | Pembinaan ketiga naik jadi Teguran I · Teguran ketiga naik jadi Peringatan I · Peringatan III berarti diskualifikasi. Papan skor menampilkan 2 + 2 + 3 petak mengikuti tangga ini |

### 8.3 Pesan galat

Menyebut **apa yang harus dilakukan**, bukan apa yang gagal.

> Buruk: "Validasi gagal." · "Field required." · "Terjadi kesalahan."
> Baik: "Berat badan belum diisi. Isi angka dalam kilogram, misalnya 52.4."
> Baik: "Bagan belum bisa disusun karena 3 pesilat belum ditimbang. Selesaikan timbang badan dulu."

Ketika sebuah aksi gagal karena prasyarat, pesannya **menyebutkan prasyaratnya dan tautan ke sana**.

---

## 9. Pola aman dari salah tekan

Ini prinsip yang paling menentukan bentuk komponen, jadi ditulis rinci.

### 9.1 Tiga tingkat perlindungan

| Tingkat | Untuk aksi | Bentuk |
|---|---|---|
| **Tanpa halangan** | Bisa dibatalkan sendiri, tidak terlihat orang lain (menyaring, menyortir, membuka rincian) | Langsung jalan |
| **Konfirmasi** | Berdampak tapi bisa dipulihkan (batalkan nilai, buka kunci bagan, tolak pendaftaran) | Dialog konfirmasi yang menyebut akibatnya |
| **Konfirmasi terketik** | Tidak bisa dipulihkan (hapus kontingen berisi atlet, sahkan hasil partai, akhiri partai) | Dialog konfirmasi + pengguna mengetik nama objeknya |

### 9.2 Bentuk dialog konfirmasi

Wajib memuat, berurutan:

1. **Judul berupa pertanyaan** dengan objeknya disebut: "Sahkan hasil Partai 14?"
2. **Kalimat lengkap tentang akibatnya**, termasuk apa yang jadi tidak bisa diubah lagi:
   "Setelah disahkan, nilai dan hukuman partai ini tidak bisa diubah lagi, dan hasilnya masuk ke bagan."
3. **Apa yang terjadi kalau batal** — kalau tidak jelas: "Kalau batal, tidak ada yang berubah."
4. Dua tombol: **batal** di kiri (polos), **aksi** di kanan (utama atau berbahaya).
   Tombol berbahaya **tidak boleh** berada di posisi yang sama dengan tombol lanjut di dialog biasa —
   supaya urutan tekan yang dihafal tidak berubah jadi bencana.

Dialog konfirmasi **tidak pernah** tertutup karena klik di luar atau tombol Esc untuk tingkat
"konfirmasi terketik".

### 9.3 Pembatalan yang terlihat

Aksi yang bisa dibatalkan menampilkan pembatalannya **di tempat aksi itu terjadi**, bukan di menu
tersembunyi. Riwayat Dewan Wasit Juri sudah benar: tiap baris nilai punya tombol Batalkan sendiri,
dan tombol itu nonaktif dengan alasan tertulis begitu partai disahkan.

### 9.4 Mode simulasi

Kejuaraan simulasi ditandai **di seluruh layar**, bukan hanya di satu badge: pita di tepi atas
halaman bertuliskan "Kejuaraan simulasi — data ini bukan hasil resmi", dan pita itu ikut tampil di
panel gelanggang serta overlay. Panitia harus berani berlatih tanpa takut merusak data sungguhan.

### 9.5 Nonaktif selalu berpasangan dengan alasan

Tombol nonaktif tanpa penjelasan sama membingungkannya dengan tombol yang gagal diam-diam.
Setiap kontrol nonaktif punya kalimat alasan di dekatnya atau di callout bagian itu.

---

## 10. Keputusan terkunci

Hal-hal ini **tidak boleh diubah desainer**. Sebagian ditetapkan peraturan pertandingan, sebagian
sudah diuji di gelanggang dan terbukti benar. Kalau sebuah gagasan desain bertabrakan dengan daftar
ini, gagasannya yang gugur.

| Keputusan | Alasan |
|---|---|
| **Papan skor tidak punya mode terang** | Dibaca dari tribun dan dilapiskan di atas gambar kamera |
| **Merah di kiri, biru di kanan** | Konvensi papan skor pencak silat. Tidak pernah dibalik |
| **Merah dan biru hanya berarti sudut** | Ditetapkan peraturan sebagai identitas pesilat |
| **Emas hanya untuk juara dan medali** | Begitu dipakai untuk hal lain, ia berhenti berarti "juara" |
| **Tombol aksi tidak pernah bersudut merah/biru** | "Akhiri partai" berwarna merah terbaca seolah berhubungan dengan sudut merah |
| **Angka skor dominan atas nama pesilat** | Papan skor gelanggang konvensional |
| **Timer di tengah** | Konvensi |
| **Overlay transparan, kanvas 1920×1080 terkunci** | Overlay siaran bukan halaman web biasa; ia dikomposit vMix |
| **Font di-bundle, tidak pernah dari CDN** | Gelanggang sering tanpa internet |
| **Ornamen tidak pernah di belakang teks, angka, atau kontrol** | Syarat kontras berlaku tanpa pengecualian |
| **Kontrol panel gelanggang ≥64px** | Ditekan cepat sambil berdiri |
| **Panel juri dan wasit dirancang untuk landscape 844×390** | Itu cara aparat memegang HP di tepi matras. Tinggi layar sangat terbatas — kepala layar setipis mungkin, tombol mendapat sisanya |
| **Lorong merah–biru di panel juri ≥24px** | Selip ke samping memberikan nilai kepada lawan |
| **Istilah hukuman naskah sebagai label utama** | Wasit tidak boleh menerjemahkan sendiri di bawah tekanan waktu |

### Keputusan yang sengaja dipertahankan meski punya harga

| Keputusan | Harga yang diterima |
|---|---|
| `maximum-scale=1, user-scalable=no` di layout gelanggang | Melanggar WCAG 1.4.4. Ditukar dengan mencegah cubitan tak sengaja menggeser tombol saat ditekan cepat |
| Ekspor CSV, bukan `.xlsx` | Panitia perlu langkah tambahan membukanya rapi di Excel |
| Tautan tidak berwarna, hanya bergaris bawah | Konsekuensi dari merah/biru/emas yang sudah punya arti tetap |

---

## 11. Kendala teknis yang membentuk desain

Bukan bagian menggambar, tapi menentukan apa yang bisa diwujudkan.

### 11.1 Dua bundel

| Bundel | Melayani | Tidak boleh memuat |
|---|---|---|
| `app.*` | Admin/panitia | Token gelanggang, huruf display upacara |
| `silat.*` | Panel gelanggang, overlay | ApexCharts, komponen admin, huruf display upacara |

Bundel ketiga `upacara.*` **dibatalkan** bersama lapisan upacara (§4.4). Halaman publik memakai `silat.*` yang sama dengan panel gelanggang, jadi tidak ada huruf tambahan yang ikut terseret ke overlay vMix.

Token bersama tinggal di satu berkas `dasar.css` yang diimpor ketiganya, supaya nilai warna hanya
ditulis sekali.

### 11.2 Panel juri adalah PWA

Panel juri dipasang ke layar utama HP. Ia butuh ikon 192px dan 512px dalam format PNG **dan** SVG
yang valid. Ikon SVG pernah gagal dirender sama sekali karena komentar XML-nya memuat tanda hubung
ganda — kalau ikon digambar ulang, berkasnya wajib diuji buka di peramban, bukan hanya dilihat di
alat desain.

### 11.3 Yang dipakai ulang, jangan digambar ulang dari nol

Sudah ada dan sudah benar — jadikan titik mulai, bukan lahan kosong:

- **Tombol nilai juri** 176×247px dengan ikon, label, angka nilai, dan `aria-label` deskriptif
- **Kartu "Partai saya"** — aparat yang login di HP langsung melihat partainya
- **Halaman Setelan peraturan** — rujukan pasal per isian; ini tolok ukur yang harus dikejar layar lain
- **Keadaan kosong di Rekap** — menjelaskan apa yang membuka isinya
- **Peta alasan menang** — sudah dipusatkan; papan hasil tidak boleh kembali merender nilai mentah

### 11.4 Cara memeriksa desain sebelum diserahkan

1. `node scripts/kontras.mjs` — setiap pasangan warna baru ditambahkan ke berkas itu lebih dulu.
   Tidak ada warna masuk desain sebelum angkanya ada.
2. Panel juri dan wasit diperiksa pada **844×390 landscape** tanpa gulir.
3. Overlay diperiksa di atas **latar hijau** untuk memastikan yang transparan memang transparan.
4. Tiap layar dibaca sekali dengan pertanyaan: "kalau saya belum pernah memakai aplikasi web,
   apakah saya tahu apa yang harus saya tekan berikutnya?"
