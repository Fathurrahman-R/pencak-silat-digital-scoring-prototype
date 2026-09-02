# Brief Desain — Digital Scoring (dulu "Matras")

> **Sumber kebenaran ada di project Claude Design `digiscoring`**
> (`bd068c94-a5e9-4c40-9ab3-a30f50088252`, https://claude.ai/design/p/bd068c94-a5e9-4c40-9ab3-a30f50088252).
> Berkas ini adalah **cermin** dari dua dokumen di sana — `BRIEF-DIGITAL-SCORING.md`
> dan `DESIGN-SYSTEM.md` — disalin verbatim, plus satu bagian tambahan lokal
> (§0 di bawah) yang menutup satu tabrakan antar keduanya. Kalau sebuah nilai
> di sini terasa salah, yang diubah **project Claude Design-nya dulu**, baru
> salinan lokal ini.
>
> Arah "Matras" yang sebelumnya berlaku di berkas ini sudah **digantikan**.
> Riwayatnya diarsipkan di `docs/kanvas/README.md`.

---

## 0. Batas lokal: semantik warna admin vs. gelanggang

`BRIEF-DIGITAL-SCORING.md` §2.2 mengunci "merah dan biru hanya berarti sudut
pesilat" — nol merah/biru semantik, termasuk untuk galat atau tautan.
`DESIGN-SYSTEM.md` §2 sebaliknya mendefinisikan semantik penuh (hijau/oranye/
merah/biru) untuk badge dan kotak pesan.

**Keputusan:** `DESIGN-SYSTEM.md` menang **di admin saja**. Badge dan kotak
pesan di `resources/views/admin/**` boleh memakai keempat warna semantik
(sukses/perhatian/bahaya/info) apa adanya dari `DESIGN-SYSTEM.md` §2.

Aturan "merah & biru hanya berarti sudut pesilat" tetap berlaku penuh di:
panel gelanggang (juri, wasit, operator, Dewan Wasit Juri, keberatan, Jurus),
overlay siaran, bagan (admin maupun publik), dan blok sudut di mana pun ia
digambar. Di permukaan-permukaan itu, galat dan status tidak pernah memakai
`--k-bahaya`/`--k-info` — bidang netral (tinta/tepi) dan bentuk yang membawa
arti.

Nilai kontras gabungan (diukur dari nilai project apa adanya, dijaga
`scripts/kontras.mjs`):

| Pasangan | Rasio |
|---|---|
| Galat `#b91c1c` di `#fef2f2` | 5.91 |
| Info `#1d4ed8` di `#eff6ff` | 6.16 |
| Sukses `#15803d` di `#f0fdf4` | 4.79 |
| Peringatan `#b45309` di `#fffbeb` | 4.84 |
| Sudut merah/putih | 5.20 · Sudut biru/putih | 9.04 |
| Teguran `#1a1207` di `#d98324` | 6.37 |
| Peringatan (hukuman) putih di `#fafafa` teks `#09090b` | 19.06 |

Sistem ini **tidak** mengunci tepi kendali ke ambang 3:1 seperti sistem lama —
`DESIGN-SYSTEM.md` §12 hanya menuntut teks ≥4.5:1, sentuh 36/44px, cincin
fokus, dan "status tidak boleh hanya lewat warna". Tepi kendali zinc
(`#d4d4d8` di terang, `#3f3f46` di gelap) sengaja di bawah 3:1 dan itu
disengaja oleh sistem baru; `scripts/kontras.mjs` mencatat angkanya (`(catat)`)
tanpa menggagalkan. Tepi yang **membawa arti** — petak hukuman, slot bagan
kosong — tetap diambang 3:1.

---

## Bagian A — BRIEF-DIGITAL-SCORING.md (verbatim)

# Digital Scoring — Brief Desain

Pengganti `docs/BRIEF-DESAIN.md` dan sistem "Matras". Ditulis dari sepuluh putaran
keputusan, bukan dari selera. Tiap baris di sini punya asal-usulnya.

Berkas gambar acuan ada di project ini:

| Layar | Berkas |
|---|---|
| Pola daftar + pola formulir admin | `arah-b-shadcn-badge.dc.html` |
| Panel Operator gelanggang | `operator-b-eksperimen.dc.html` |
| Panel Juri (844×390) | `panel-juri.dc.html` |
| Panel Wasit (844×390) | `panel-wasit.dc.html` |
| Panel Dewan Wasit Juri | `panel-dewan-wasit-juri.dc.html` |
| Beranda publik + live score | `publik-beranda-live.dc.html` |
| Overlay siaran vMix | `overlay-siaran.dc.html` |

Jejak eksplorasi yang tidak dipakai: `arah-a-padat`, `arah-b-lega`, `arah-c-kartu`,
`arah-b-shadcn`, `operator-a-konvensional`.

---

### 1. Arah

**shadcn untuk admin dan komponen. Gelanggang dan overlay punya bahasa papan skor sendiri.**

Admin adalah aplikasi kerja: netral, padat informasi, pola yang sudah dikenal siapa pun
yang pernah memakai panel modern. Gelanggang bukan aplikasi kerja — ia papan skor yang
kebetulan berjalan di peramban, dan bahasanya angka raksasa di atas bidang gelap.

Yang menyatukan keduanya: satu keluarga huruf, satu skala jarak, satu geometri sudut,
satu disiplin warna.

### 2. Token

#### 2.1 Netral (shadcn zinc)

| Peran | Terang | Gelap |
|---|---|---|
| Latar halaman | `#ffffff` | `#09090b` |
| Permukaan naik | `#fafafa` | `#18181b` |
| Garis | `#e4e4e7` | `#27272a` |
| Tepi kendali | `#d4d4d8` | `#3f3f46` |
| Teks utama | `#09090b` | `#fafafa` |
| Teks kedua | `#3f3f46` | `#d4d4d8` |
| Teks redup | `#71717a` | `#a1a1aa` |
| Teks paling redup | `#a1a1aa` | `#71717a` |
| Aksi utama / teksnya | `#18181b` / `#fafafa` | `#e8e8ea` / `#111114` |

Suasana bawaan **terang**, dengan saklar manual. Papan gelanggang dan overlay
tidak ikut saklar — keduanya gelap permanen.

#### 2.2 Sudut pesilat — terkunci

| Token | Nilai | Dipakai |
|---|---|---|
| Merah | `#d42027` | Batang tepi, titik penanda, bidang tombol nilai juri |
| Biru | `#12439e` | Idem |
| Merah dalam | `#7a1418` | Bidang blok sudut merah |
| Biru dalam | `#0c2a63` | Bidang blok sudut biru |
| Teks di merah dalam | `#e8b4b6` · `#f0c9ca` | Label dan baris kedua |
| Teks di biru dalam | `#a8b8e0` · `#c3cfeb` | Label dan baris kedua |

**Merah dan biru hanya berarti sudut pesilat.** Tidak untuk tombol, tautan, aksen,
peringatan sistem, atau status. Ini keputusan terkunci dan punya tiga konsekuensi
yang harus diterima:

1. **Tidak ada tombol hapus berwarna merah.** Perlindungan aksi merusak dinyatakan
   lewat kalimat akibat dan konfirmasi terketik, bukan warna.
2. **Peringatan (hukuman terberat) tidak berbidang merah.** Tangga hukuman dinyatakan
   lewat kenaikan kontras dan bentuk: tepi abu → bidang oranye → bidang putih pekat.
3. **Tautan tidak berwarna biru.** Bertinta penuh dan bergaris bawah.

#### 2.3 Hukuman

| Tingkat | Bidang | Teks | Bentuk |
|---|---|---|---|
| Pembinaan | `#6b6b73` | `#ffffff` | Telapak terbuka |
| Teguran | `#d98324` | `#1a1207` | Segitiga |
| Peringatan | `#fafafa` | `#09090b` | Segi delapan |

Teks gelap di atas oranye, bukan putih: putih di atas `#d98324` hanya 2.75:1,
sedangkan `#1a1207` mencapai 6.37:1.

#### 2.4 Lain-lain

| Token | Nilai | Dipakai |
|---|---|---|
| Emas | `#8a6d10` (terang) · `#c9a227` (gelap) | **Hanya** juara dan medali |
| Hidup / tersambung | `#4ade80` | Indikator sistem |
| Petak & kontrol mati | `#8a8a90` | **Tepi**, tidak pernah bidang |

**Aturan "mati bukan bidang".** Apa pun yang berarti belum terisi, belum menyala, atau
tidak bisa ditekan digambar sebagai **tepi** `#8a8a90`, bukan bidang abu. Bidang abu
gelap di atas hitam berkontras 1.4–2.9 dan terbaca seperti kontrol hidup yang gagal.

### 3. Tipografi

| Peran | Huruf |
|---|---|
| Antarmuka | **Geist** |
| Angka, waktu, kode, cap waktu | **Geist Mono** |

Dua huruf saja. Keduanya di-bundle lewat npm — gelanggang sering tanpa internet.

Angka yang dibandingkan sebaris ke bawah selalu `font-variant-numeric: tabular-nums`.

**Skala.** Admin: 30 judul halaman · 20 judul bagian · 14 badan · 13.5 label ·
12.5 keterangan. Gelanggang: skor 148–156 (operator), 110 (live publik), 44 (overlay) ·
timer 84 (operator), 44 (overlay) · nama pesilat 22–32.

### 4. Geometri

Radius `8px` untuk tombol, isian, kartu, dan blok sudut; `12–14px` untuk kartu besar,
sheet, dan dialog; `6px` untuk badge dan petak hukuman; `999px` hanya untuk avatar,
titik indikator, dan saklar.

Jarak kelipatan 4: `4 · 8 · 12 · 16 · 20 · 24 · 32 · 40`.

**Ukuran sentuh.** Admin 36–40px. Panel gelanggang **64px minimum**, tanpa pengecualian.
Lorong antara kolom merah dan biru di panel juri **28px**; sela antar baris 8px —
selip ke samping memberi nilai kepada lawan, selip ke atas hanya menggeser jenis serangan.

Nol gradien, nol kaca, nol tekstur, nol noise. Kedalaman dinyatakan lewat warna
permukaan dan satu garis. Bayangan hanya untuk yang benar-benar mengambang.
Panel gelanggang tidak memakai bayangan sama sekali.

### 5. Arsitektur informasi — satu pintu per tujuan

Aturan yang membentuk pola daftar admin: **tidak boleh ada dua pintu masuk ke satu
fitur, aksi, atau alur.** Penerapannya:

- Aksi per baris hanya di **menu titik-tiga**. Tidak ada tombol berjajar, tidak ada
  panel samping yang isinya sama.
- Pengecualian tunggal: **badge Kelengkapan berkas ITU tombolnya**. Pekerjaan yang
  paling sering dilakukan tidak boleh dua klik dalam dan tak terlihat — dan karena
  badge-nya yang jadi pintu, "Berkas" dikeluarkan dari menu. Tetap satu pintu.
- Formulir dan rincian memakai **sheet** (panel samping), bukan modal. Satu bentuk
  untuk keduanya.
- Tab "Pendaftaran nomor" **dihapus**: pendaftaran selalu milik seseorang, jadi
  pintunya ada di atletnya. Akibatnya kolom **Nomor terdaftar** masuk ke tabel Atlet,
  dan nomor **Ganda/Regu** — yang butuh 2–3 pesilat — didaftarkan lewat mencentang
  beberapa baris, memunculkan toolbar aksi massal yang menyebut syaratnya.
- Tombol aksi halaman duduk **rata kanan di baris tab**, bukan sebaris dengan judul.
- Chip saring berdiri di **barisnya sendiri** di bawah kolom cari.

> **Status implementasi lokal:** belum dikerjakan — dicatat sebagai Fase 2 di
> `docs/RENCANA.md`. Rupa (token, komponen, tata letak) dikerjakan lebih dulu;
> perubahan alur ini menyentuh controller, route, dan tes Pest, jadi dipisah
> supaya suite tetap hijau lebih lama.

### 6. Pola aman dari salah tekan

| Tingkat | Untuk aksi | Bentuk |
|---|---|---|
| Tanpa halangan | Menyaring, menyortir, membuka rincian | Langsung jalan |
| Konfirmasi | Berdampak tapi bisa dipulihkan | Dialog yang menyebut akibatnya |
| Konfirmasi terketik | Tidak bisa dipulihkan | Dialog + mengetik nama objeknya |

Dialog konfirmasi wajib memuat, berurutan: judul berupa pertanyaan dengan objeknya
disebut · kalimat lengkap tentang akibatnya, termasuk apa yang jadi tidak bisa diubah ·
apa yang terjadi kalau batal · dua tombol, batal di kiri.

Kontrol nonaktif **selalu** berpasangan dengan kalimat alasannya.

### 7. Bahasa

Bahasa Indonesia. Istilah umum web boleh masuk apa adanya — Filter, Export, Dashboard —
karena panitia yang pernah memakai panel lain mengenalinya lebih cepat daripada
terjemahan yang dibuat khusus.

**Yang tidak boleh diterjemahkan atau disingkat**, karena ditetapkan naskah:

| Naskah | Aturan |
|---|---|
| Pembinaan · Teguran · Peringatan | Label utama hukuman di semua layar. Sebutan sehari-hari boleh jadi baris kedua, tidak boleh menggantikan |
| Sudut merah · Sudut biru | Tidak pernah tertukar |
| Pukulan (1) · Tendangan (2) · Jatuhan (3) | Lengkap dengan angka nilainya di tombol |
| Dewan Wasit Juri | Satu sebutan di seluruh teks pengguna |
| Tangga hukuman Pasal 11.6.d.4 | 2 Pembinaan + 2 Teguran + 3 Peringatan, posisi tetap, tanpa angka |
| Alasan menang | Selalu bentuk terbaca — "Menang angka", bukan kode mentah |

Pesan galat menyebut **apa yang harus dilakukan**, bukan apa yang gagal. Kalau aksi
gagal karena prasyarat, pesannya menyebutkan prasyaratnya dan tautan ke sana.

### 8. Keputusan terkunci

| Keputusan | Alasan |
|---|---|
| Merah & biru hanya berarti sudut pesilat | Ditetapkan peraturan sebagai identitas |
| Emas hanya juara dan medali | Begitu dipakai untuk hal lain, ia berhenti berarti juara |
| Istilah naskah sebagai label utama hukuman | Wasit tidak boleh menerjemahkan sendiri di bawah tekanan waktu |
| 7 petak hukuman 2+2+3, posisi tetap, tanpa angka | Jumlah dibaca dari berapa petak menyala; petak Peringatan ketiga bergaris putus karena mengisinya berarti diskualifikasi |
| Overlay transparan, kanvas 1920×1080 | Dikomposit vMix, bukan halaman web biasa |
| Panel juri & wasit muat 844×390 landscape tanpa gulir | Itu cara aparat memegang HP di tepi matras |
| Kontrol panel gelanggang ≥64px | Ditekan cepat sambil berdiri, mata pada pesilat |

### 9. Yang sengaja dibuka dari sistem lama

| Dulu terkunci | Sekarang |
|---|---|
| Merah kiri, biru kanan | **Dibuka.** Panel operator memakai susunan **merah atas, biru bawah** — angka, hukuman, dan indikator juri jadi tersusun sekolom dan bisa dibandingkan langsung, dan pertanyaan kiri-kanan hilang. Panel juri, wasit, live publik, dan overlay tetap kiri-kanan karena di sana kolomnya memang memetakan posisi pesilat di matras |
| Papan skor gelap saja | **Dibuka untuk live score publik** — dibuka di HP di bawah matahari. Panel gelanggang dan overlay tetap gelap |
| Aksi utama tanpa warna | Tetap tanpa warna, tapi sekarang karena pilihan shadcn (primary netral), bukan karena kehabisan warna |
| Dua huruf khusus (Space Grotesk / IBM Plex Mono) | Diganti Geist / Geist Mono |
| Radius 0 di permukaan publik | Dibuka — publik memakai radius yang sama dengan admin |

### 10. Cara memeriksa sebelum diserahkan

1. Ukur kontras di DOM terhadap latar **efektif**, di kedua suasana, dengan transisi
   dimatikan lebih dulu.
2. Panel juri dan wasit diperiksa pada **844×390 landscape** tanpa gulir.
3. Overlay diperiksa di atas **latar hijau** untuk memastikan yang transparan memang
   transparan — `overlay-siaran.dc.html` sudah menyediakan sakelarnya.
4. Tiap layar dibaca sekali dengan pertanyaan: "kalau saya belum pernah memakai
   aplikasi web, apakah saya tahu apa yang harus saya tekan berikutnya?"
5. Cari pintu ganda: satu tujuan, satu jalan masuk.

---

## Bagian B — DESIGN-SYSTEM.md (verbatim)

# Aturan Desain Sistem — Digital Scoring (Panel Admin & Operasional)

Dokumen ini adalah sumber kebenaran. Setiap halaman baru dirakit dari aturan di bawah, bukan didesain ulang.
Referensi visual: `Aturan Desain.dc.html`. Contoh implementasi: `Admin Kejuaraan.dc.html`, `dashboard-superadmin.dc.html`.

---

### 1. Prinsip

1. **Data dulu, dekorasi nol.** Tidak ada gradien, bayangan berat, ilustrasi, emoji. Kontur 1px + latar putih.
2. **Satu aksi utama per layar.** Sisanya sekunder/halus.
3. **Angka selalu mono + tabular-nums.** Skor, ID, waktu, kode partai.
4. **Hierarki lewat berat & warna teks**, bukan ukuran font berlebihan.
5. **Setiap layar wajib punya 6 keadaan** (§9). Kalau belum digambar, halaman belum selesai.

### 2. Token warna

#### Netral (zinc) — 95% tampilan

| Token | Hex | Pakai untuk |
|---|---|---|
| `latar` | `#ffffff` | latar halaman, kartu, tabel |
| `latar-halus` | `#fafafa` | zona kosong, toolbar massal, header tabel alternatif |
| `latar-mati` | `#f4f4f5` | header tabel, badge netral, hover tombol putih, avatar |
| `tepi` | `#e4e4e7` | semua kontur kartu/tabel/tombol (default) |
| `tepi-kuat` | `#d4d4d8` | kontur input, pemisah dalam, kontur putus-putus |
| `redup-3` | `#a1a1aa` | teks tersier, ikon nonaktif, garis bagan |
| `redup-2` | `#71717a` | teks sekunder, label, subjudul |
| `redup-1` | `#52525b` | ikon dalam kotak, tautan hover |
| `teks-3` | `#3f3f46` | ikon tombol ikon |
| `teks-2` | `#27272a` | hover tombol utama, teks badge |
| `teks-1` | `#18181b` | latar tombol utama, garis tab aktif |
| `teks-0` | `#09090b` | judul & teks utama, latar topbar dev |

#### Semantik — hanya untuk status, bukan hiasan

| Status | Teks | Ikon | Latar | Tepi |
|---|---|---|---|---|
| Sukses / lolos | `#15803d` | `#16a34a` | `#f0fdf4` | `#bbf7d0` |
| Peringatan / menunggu | `#b45309` | `#f59e0b` | `#fffbeb` | `#fde68a` |
| Galat / ditolak | `#b91c1c` | `#dc2626` | `#fef2f2` | `#fecaca` |
| Info / berlangsung | `#1d4ed8` | `#2563eb` | `#eff6ff` | `#bfdbfe` |

#### Domain pertandingan (tidak boleh dipakai di UI umum)

| Token | Hex | Pakai |
|---|---|---|
| `sudut-merah` | `#d42027` | aksen/teks sudut merah |
| `sudut-biru` | `#12439e` | aksen/teks sudut biru |
| `bidang-merah` | `#7a1418` | blok penuh peserta merah (bagan, overlay) |
| `bidang-biru` | `#0c2a63` | blok penuh peserta biru |
| `sudut-kosong` | `#8a8a90` | slot belum terisi / BYE |

Aturan: maksimal 2 warna latar non-putih per halaman. Semantik hanya muncul pada badge, kotak pesan, dan ikon status.

### 3. Tipografi

Font: **Geist** (400/500/600/700) untuk semua teks. **Geist Mono** (400/500) untuk angka, kode, ID, waktu, label topbar.

| Peran | Ukuran | Berat | Tracking | Warna |
|---|---|---|---|---|
| H1 judul halaman | 30px | 600 | −0.025em | `#09090b` |
| H2 judul seksi besar | 20px | 600 | −0.02em | `#09090b` |
| H3 judul kartu | 16px | 600 | — | `#09090b` |
| Judul baris/blok | 14.5px | 600 | — | `#09090b` |
| Teks utama | 14px | 400 | — | `#09090b` |
| Subjudul halaman | 15px | 400 | 1.5 lh | `#71717a` |
| Teks sekunder | 13.5px | 400 | — | `#71717a` |
| Label & meta | 12.5px | 500 | — | `#71717a` |
| Micro / topbar | 11px | 500 | 0.04em, uppercase | mono |
| Angka KPI | 26px | 500 mono | −0.02em, tabular | `#09090b` |
| Angka skor besar | 44–72px | 500 mono | −0.03em, tabular | konteks |

Aturan: `line-height` 1.5–1.6 untuk paragraf, 1.35 untuk badge/label. Selalu `text-wrap: pretty` pada paragraf. Maksimal 3 ukuran font dalam satu kartu.

### 4. Spasi

Skala: **2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 24, 28, 32, 40, 56, 64**. Angka di luar skala tidak dipakai.

| Konteks | Nilai |
|---|---|
| Padding halaman (`main`) | `28px 32px 56px` |
| Lebar maksimum konten | `main` penuh lebar layar (tanpa batas); form: `900px`, teks panjang: `680px` |
| Antar seksi besar | `32px` |
| Judul halaman → toolbar | `20px` |
| Toolbar → tab | `12px` |
| Tab → konten | `24px` |
| Antar kartu (grid gap) | `16px` |
| Padding kartu | `16px 18px` (padat) / `18px` / `20px 22px` (form) |
| Padding sel tabel | `11px 14px` |
| Gap ikon → label dalam tombol | `7px`…`10px` |
| Gap antar tombol sederet | `8px` |
| Gap antar tab | `4px` |

### 5. Radius, kontur, elevasi

| Elemen | Radius |
|---|---|
| Badge, chip, pil kecil | `6px` |
| Tombol, input, tautan nav, tombol ikon | `8px` |
| Toolbar massal, blok inline | `10px` |
| Kartu, panel, tabel, kotak pesan | `12px` |
| Sheet/dialog | `14px` |
| Avatar, pil status | `999px` |

Kontur: selalu `1px solid #e4e4e7`; input `#d4d4d8`; zona kosong `1px dashed #d4d4d8`.
Bayangan: hanya pada lapisan mengapung — dialog `0 24px 60px rgba(9,9,11,.22)`, dropdown `0 12px 28px rgba(9,9,11,.14)`. Kartu **tanpa** bayangan.

### 6. Anatomi halaman

```
┌ Topbar dev (opsional, 34px, #09090b, mono 11px) ─────────────────┐
├──────────────┬───────────────────────────────────────────────────┤
│ Sidebar      │ Header 60px — remah roti · aksi kanan (ikon 40px)  │
│ 264px        ├───────────────────────────────────────────────────┤
│ (ciut 68px)  │ main: padding 28/32/56, lebar penuh                │
│              │  H1 + subjudul (lebar penuh, tanpa tombol)        │
│ brand 60px   │  toolbar aksi — baris sendiri, rata kanan         │
│ + toggle     │  tab (border-bottom 1px)                          │
│ nav 12px pad │  ── konten ──                                     │
│              │  KPI 4 kolom → toolbar massal → tabel/kartu        │
└──────────────┴───────────────────────────────────────────────────┘
```

Aturan tetap:
- **Sidebar** `264px`, ciut `68px`, latar `#fafafa`, tepi kanan `#e4e4e7`. Tombol ciutkan **di baris brand** (32×32), bukan di footer. Tidak ada kolom pencarian di sidebar/header.
- Item nav: `height 38px`, `padding 0 12px`, `radius 8px`, `gap 10px`, ikon 16px. Aktif: latar `#ffffff` + tepi `#e4e4e7` + teks `#09090b` 500. Nonaktif: teks `#3f3f46`, hover latar `#f4f4f5`.
- Label seksi nav: 11px mono uppercase `#a1a1aa`, margin `18px 0 6px 12px`. Saat ciut, label diganti garis pemisah.
- **Header** `60px` sticky, isi kiri = remah roti (13.5px), isi kanan = tombol ikon 40px + avatar 36px.
- **Urutan wajib dalam `main`**: judul → toolbar aksi → tab → toolbar massal (kalau ada seleksi) → konten. Toolbar aksi selalu di baris sendiri di bawah judul dan keterangannya, rata kanan, di semua lebar layar — tidak pernah sebaris dengan judul.

### 7. Tombol

#### Varian

| Varian | Latar | Tepi | Teks | Hover | Pakai |
|---|---|---|---|---|---|
| Utama | `#18181b` | none | `#fafafa` | `#27272a` | satu aksi terpenting per layar |
| Sekunder | `#ffffff` | `#e4e4e7` | `#09090b` | latar `#f4f4f5` | aksi pendamping, Batal, Ekspor |
| Halus | transparan | none | `#3f3f46` | latar `#f4f4f5` | aksi dalam baris tabel, ikon header |
| Destruktif | `#ffffff` | `#fecaca` | `#b91c1c` | latar `#fef2f2` | hapus/tolak; versi solid `#b91c1c` hanya di dialog konfirmasi |
| Tautan | — | — | `#09090b` underline | `#52525b` | navigasi sekunder dalam teks |

#### Ukuran

| Ukuran | Tinggi | Padding-x | Font | Pakai |
|---|---|---|---|---|
| sm | 32px | 12px | 13px/500 | toolbar massal, dalam kartu padat |
| md | 36px | 13–14px | 13.5px/500 | default (toolbar halaman, zona kosong) |
| lg | 38px | 14–16px | 14px/500 | footer form, footer sheet |
| ikon-sm | 32×32 | — | ikon 16px | sidebar, baris tabel |
| ikon-md | 36×36 | — | ikon 16px | dalam kartu |
| ikon-lg | 40×40 | — | ikon 16px | header |

#### Peletakan

- Toolbar halaman: **rata kanan**, di bawah judul, di atas tab. Urutan kiri→kanan: halus → sekunder → utama (utama paling kanan).
- Maksimal 3 tombol di toolbar halaman; sisanya masuk menu "⋯".
- Footer form/dialog: rata kanan, Batal (sekunder) lalu Simpan (utama), gap 8px.
- Footer sheet mobile/panel: tombol utama `flex: 1`, sekunder lebar-isi di kiri.
- Aksi baris tabel: tombol halus ikon 32×32 di kolom terakhir, rata kanan, muncul permanen (bukan hanya hover).
- Ikon tombol: `lucide`, 16px, stroke 2, selalu di **kiri** label. Ikon kanan hanya untuk chevron/menu.
- Ikon aksi baku: tambah `plus`, ekspor `download`, impor `upload`, jadwal `calendar-plus`, bagan `list-plus`, duplikat `copy`, undang `user-plus`, simpan `check`, kirim `send`, hapus `trash-2`, muat ulang `refresh-cw`.

### 8. Komponen

**Kartu** — `border 1px #e4e4e7; radius 12px; background #ffffff; padding 18px`. Header kartu opsional: `padding 12px 14px; border-bottom 1px #e4e4e7; 13px/600`.

**KPI** — grid 4 kolom, gap 16px, padding `16px 18px`. Isi: label 12.5px/500 `#71717a` → nilai mono 26px → sub 12.5px `#a1a1aa`. Maksimal 4 KPI per baris; jangan tambahkan grafik di dalam KPI.

**Tabel** — bungkus kartu `radius 12px; overflow hidden`. `thead`: latar `#f4f4f5`, teks 12px/600 `#3f3f46`, padding `10px 14px`. `td`: `11px 14px`, tepi atas `1px #e4e4e7`, 14px. Kolom angka `text-align: right` + mono tabular. Baris hover `#fafafa`. Checkbox kolom pertama 16px hanya jika ada aksi massal. Zebra tidak dipakai.

**Badge** — `min-height 24px; padding 3px 9px; radius 6px; 12.5px/500; line-height 1.35`.
- netral: latar `#f4f4f5`, teks `#27272a`
- bertepi: latar `#ffffff`, tepi `#e4e4e7`
- putus-putus (belum ada data): latar `#fafafa`, tepi `1px dashed #a1a1aa`, teks `#71717a`
- semantik: pakai trio latar/tepi/teks dari §2
- badge titik status live: titik 6px + teks; berkedip pakai `@keyframes denyut` (opacity 1 → .45, 1.6s)

**Input** — `min-height 36px; padding 7px 12px; border 1px #d4d4d8; radius 8px; 13.5px`. Fokus: `border #18181b` + `box-shadow 0 0 0 3px rgba(9,9,11,.08)`. Label 13px/500 di atas, gap 6px; teks bantuan 12.5px `#71717a` di bawah; galat: tepi `#fecaca`, pesan `#b91c1c`.

**Saklar** — 40×22px, radius 999, knob 16px. Mati: latar `#ffffff`, tepi `#d4d4d8`. Hidup: latar `#18181b`, tepi `#18181b`, knob putih.

**Tab** — `height 38px; padding 0 12px; gap 4px`, garis bawah kontainer `1px #e4e4e7`. Aktif: `border-bottom 2px #18181b`, teks `#09090b`/600. Nonaktif: teks `#71717a`/400. Tab hanya untuk memfilter isi yang sama, bukan navigasi antar halaman.

**Toolbar massal** — `padding 10px 14px; radius 10px; tepi #d4d4d8; latar #fafafa`. Kiri: "N dipilih" 13.5px/600 + syarat 12.5px `#71717a`. Kanan: tombol sm (utama pertama). Muncul di atas tabel, gap 12px ke tabel.

**Baris form** — grid `260px 1fr`, gap 32px, padding `20px 22px`. Kiri: judul 14.5px/600 + deskripsi 13px `#71717a`. Kanan: kontrol, gap 16px.

**Sheet / dialog** — panel `radius 14px`, lebar 520px (dialog) / 440px (sheet kanan, full-height). Header `padding 18px 22px` + tepi bawah; isi `padding 18px 22px`; footer `padding 16px 22px` + tepi atas. Latar gelap `rgba(9,9,11,.45)`.

**Bagan pertandingan** — kolom per ronde, gap 28px, kartu partai lebar 200–220px, tepi `1px #a1a1aa`, radius 8. Kepala partai: latar `#f4f4f5`, mono 11px (kode + waktu). Slot peserta: **blok warna penuh** `#7a1418` / `#0c2a63`, teks putih; pemenang berat 600 + latar cerah, yang kalah opacity .55. Slot kosong `#8a8a90` dengan teks putus-putus. Garis penghubung `1px #a1a1aa`.

### 9. Enam keadaan wajib per halaman

1. **Terisi** — data normal.
2. **Kosong** — zona `padding 64px 24px; radius 12px; 1px dashed #d4d4d8; latar #fafafa`, ikon dalam kotak 44px, judul 16px/600, teks 14px `#71717a` maks 420px, satu tombol utama.
3. **Memuat** — kartu berisi rangka: blok `radius 8px; latar #f4f4f5` dengan animasi `denyut`; tinggi 12–16px, lebar bervariasi 40–100%. Tanpa spinner.
4. **Galat** — kotak `latar #fef2f2; tepi #fecaca; radius 12px; padding 18px`, ikon `circle-alert` `#dc2626` 18px, judul 14.5px/600 `#b91c1c`, penjelasan + tombol "Coba muat ulang" (sekunder).
5. **Sheet/dialog terbuka** — untuk aksi utama halaman.
6. **Aksi massal aktif** — baris terpilih + toolbar massal.

### 10. Pola per tipe halaman

| Tipe | Susunan baku |
|---|---|
| Daftar/tabel | judul → aksi (utama = tambah) → tab filter → KPI (opsional) → toolbar massal → tabel → paginasi |
| Detail entitas | judul + badge status → aksi → tab → grid `1fr 320px`: konten kiri, ringkasan/meta kanan |
| Form/pengaturan | judul → tab seksi → baris form maks 900px → footer aksi rata kanan (sticky bila > 2 layar) |
| Papan/monitor | judul → filter chip → grid kartu 2–4 kolom, refresh otomatis, badge live |
| Antrean verifikasi | grid `320px 1fr`: daftar antrean kiri (item 2 baris), pemeriksa kanan + aksi tolak/terima di footer kartu |
| Log/audit | tabel padat, kolom waktu mono, tanpa aksi massal, filter rentang tanggal di toolbar |

Paginasi: bar bawah tabel `padding 12px 14px`, tepi atas, kiri "Menampilkan x–y dari z" 12.5px `#71717a`, kanan tombol sekunder sm Sebelumnya/Berikutnya.

### 11. Copywriting

- Bahasa Indonesia formal-ringkas, kalimat pendek, tanpa tanda seru.
- Label tombol = kata kerja + objek: "Tambah kontingen", "Kirim tagihan", "Terbitkan bagan". Bukan "Submit"/"OK".
- Judul halaman = kata benda: "Verifikasi berkas". Subjudul = satu kalimat menjelaskan cakupan.
- Pesan galat: sebutkan apa yang gagal + langkah pemulihan.
- Angka selalu dengan satuan/konteks: "12 dari 48 atlet".
- Istilah baku: kontingen, atlet, ofisial, aparat (wasit/juri), partai, gelanggang, kelas, bagan, tagihan, berkas.

### 12. Aksesibilitas & teknis

- Kontras teks minimal 4.5:1; `#a1a1aa` hanya untuk teks ≥12.5px non-esensial.
- Target sentuh minimal 36px (desktop), 44px (mobile/panel lapangan).
- Fokus terlihat: `box-shadow 0 0 0 3px rgba(9,9,11,.08)` + tepi `#18181b`.
- Status tidak boleh hanya lewat warna — selalu ada label teks.
- Semua gaya **inline** (aturan Design Component); `<helmet><style>` hanya untuk font, reset, `@keyframes`.
- Ikon dari `lucide` UMD; `[data-lucide]` default 16px stroke 2.
- Panel lapangan (wasit/juri/overlay) memakai tema gelap: latar `#09090b`, kartu `#18181b`, tepi `#27272a` — token teks tetap dari ramp yang sama, dibalik.

---

## Status implementasi lokal

Dicatat di sini, bukan di pesan commit, supaya orang yang membuka brief tahu
bagian mana yang sudah punya wujud di kode.

Cabang kerja: `rombak-ui`.

| Bagian | Keadaan |
|---|---|
| Token (`dasar.css`, `dasar-admin.css`) | Selesai — nilai zinc/sudut/hukuman, huruf Geist, geometri 6/8/12/14, jarak kelipatan 4 |
| Panel gelanggang (juri, wasit, operator, Dewan Wasit Juri, keberatan) | Selesai — operator disusun ulang merah atas/biru bawah |
| Overlay siaran (scorebug, breakdown, result, athlete, bracket) | Selesai |
| Wajah publik (beranda, live gelanggang, medali, bagan) | Selesai — berpindah ke suasana terang, radius disamakan admin |
| Komponen `si/*` (tombol, badge, isian, tabel, kartu, kosong, modal, panel-rincian, callout, saklar, dll.) | Selesai — 47/51 view admin ikut berubah otomatis lewat token/komponen |
| `scripts/kontras.mjs` | Ditulis ulang penuh, 69/69 lolos |
| §5 "satu pintu per tujuan" (struktur: menu ⋯, sheet, penggabungan tab pendaftaran) | **Belum** — Fase 2, lihat `docs/RENCANA.md` |
