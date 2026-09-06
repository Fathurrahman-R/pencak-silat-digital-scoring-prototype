# Audit Kelayakan UI/UX

> Inspeksi dijalankan 27 Agustus 2026 terhadap commit `797a1fb`, memakai kejuaraan simulasi `silat:simulasi` (turnamen #5, 2 gelanggang, 4 kontingen, 4 partai, 208 `judge_inputs`) dengan Reverb dan queue hidup. Seluruh layar ditelusuri di browser sungguhan pada 1440×900, 375×812, dan 1920×1080.
>
> Setiap temuan di bawah punya bukti terukur — screenshot di [`docs/audit-uiux/`](audit-uiux/), angka rasio kontras, ukuran piksel hasil `getBoundingClientRect()`, atau kutipan pesan console. Tidak ada temuan berdasarkan dugaan. Beberapa kecurigaan awal justru terbantah saat diuji dan dicatat di [§5](#5-yang-sudah-baik) sebagai hal yang sudah benar.

---

## 0. Status perbaikan

Seluruh temuan di bawah sudah ditambal dan diverifikasi ulang di browser pada 28 Agustus 2026. Kolom **Status** di tabel temuan menyebut hasilnya satu per satu.

Suite lengkap tetap hijau sesudah perbaikan: **520 lulus dari 520, 1.473 asersi** — persis sama dengan baseline sebelum penambalan dimulai.

Dua temuan **dibatalkan** setelah dihitung ulang — keduanya cacat alat ukur saya, bukan cacat aplikasi:

| ID | Klaim semula | Kenyataan |
|---|---|---|
| T2 | Indikator "Tersambung" kontras **1.54** | Sesungguhnya **10,6:1**. Skrip pengukur saya membaca `rgba(16,185,129,0.15)` sebagai warna solid dan mengabaikan alpha; latar sebenarnya adalah tint itu di atas `#0b0b0c`. Angka lain di laporan ini memakai latar solid sehingga tidak terpengaruh — K3 (1,045) dan T1 (2,889) sudah dihitung ulang manual dan keduanya benar. |
| R5 | apexcharts 843 kB ikut bundel admin | Sudah `await import('apexcharts')` sejak awal; 843 kB itu chunk terpisah yang dimuat hanya ketika ada grafik dirender. Bundel utama `app-*.js` berukuran 3,63 kB. |

Sisa T2 yang tetap sah adalah soal bobot, bukan kontras: keadaan terputus dulu tampil sebagai chip 11px yang sama besarnya dengan keadaan normal. Sekarang keduanya dibedakan lewat [`x-silat.indikator-koneksi`](../resources/views/components/silat/indikator-koneksi.blade.php).

Tiga berkas tes ikut disesuaikan, dan alasannya perlu dinyatakan terang-terangan supaya tidak terbaca sebagai menutupi kegagalan:

| Tes | Yang diubah | Alasan |
|---|---|---|
| `DashboardPenugasanTest` | Penanda ringkasan `'Pengguna baru'` dan `'Mulai dari mana'` jadi `'Partai hari ini'` dan `'Urutan kerja kejuaraan'` | Kedua judul lama adalah judul boilerplate yang temuan S7 justru meminta dibuang. Tesnya menguji "aparat tidak melihat ringkasan pengelolaan" — maksud itu tetap diuji, hanya penandanya mengikuti isi yang baru. |
| `PanelGelanggangTest` | `assertSee('DEWAN JURI')` jadi `assertSee('DEWAN WASIT JURI')` | Konsekuensi langsung S4. |
| `dashboard.blade.php` | Langkah 05 tidak lagi mengutip frasa "Partai saya" | Kutipan itu membuat `assertDontSee('Partai saya')` gagal untuk admin tanpa penugasan — tesnya benar, redaksi sayalah yang bertabrakan. |

Satu temuan **baru** muncul justru karena menambal T3. Begitu manifest bisa diurai, peramban lanjut memeriksa ikonnya dan menolaknya. Sebabnya: komentar di dalam `public/icons/juri.svg` memuat tanda hubung ganda, yang ilegal di XML — berkasnya invalid sejak ditulis dan tidak pernah bisa dirender sebagai gambar sama sekali. Diperbaiki, dan ditambahkan pendamping PNG 192/512 untuk peluncur yang menolak ikon vektor.

---

## 1. Ringkasan eksekutif

| Permukaan | Putusan semula | Sesudah perbaikan |
|---|---|---|
| **Panel gelanggang** | Layak dengan perbaikan wajib | **Layak.** Istilah hukuman mengikuti naskah, seluruh teks ≥4.63:1, seluruh kontrol ≥64px |
| **Admin** | Layak | **Layak.** Daftar Bagan turun dari 12,8 layar jadi satu layar; dashboard menjawab pertanyaan panitia |
| **Publik** | **Belum layak** | **Layak.** Hasil partai terbaca benar, halaman depan menjadi halaman kejuaraan |
| **Overlay siaran** | Layak | **Layak.** Babak dan hukuman kini ikut tampil |

**Tiga masalah paling menghambat:**

1. **Halaman live publik menyatakan pemenang sebagai pihak yang didiskualifikasi.** Penonton membaca "Candra Setiawan — diskualifikasi" padahal Candra-lah yang menang. ([K1](#k1))
2. **Panel wasit tidak memakai istilah peraturan, dan terjemahannya tak terbaca.** Wasit melihat "Ringan / Sedang / Berat"; kata "(pembinaan)", "(teguran)", "(peringatan)" ada tapi rasio kontrasnya 1.03–1.74. ([K3](#k3))
3. **Pintu masuk publik masih menjual boilerplate.** Halaman depan memuat daftar harga "Rp 490rb" dan menyebut dirinya "Boilerplate Laravel". ([K2](#k2))

---

## 2. Tabel temuan

Severity: **Kritis** = bisa menyebabkan salah nilai, salah baca hasil resmi, atau menghentikan pertandingan · **Tinggi** = memperlambat aparat atau membingungkan penonton siaran · **Sedang** = gesekan nyata tapi ada jalan keluar · **Rendah** = poles.

| ID | Sev | Permukaan | Dim | Masalah | Berkas | Status |
|---|---|---|---|---|---|---|
| K1 | Kritis | Publik | HITUNG | `win_reason` mentah dirender apa adanya, jadi pemenang tampak didiskualifikasi | [`public/live/gelanggang.blade.php:63`](../resources/views/public/live/gelanggang.blade.php) | ✅ Alasan menang kini lewat `App\Support\Scoring\AlasanMenang` — satu peta bersama dipakai halaman publik, overlay, panel operator/dewan, dan berita acara. Terbaca **"Candra Setiawan — Menang Diskualifikasi"**. |
| K2 | Kritis | Publik | BHS/IST | Landing masih materi jualan boilerplate: "Boilerplate Laravel", harga Rp 0 / Rp 490rb / Hubungi kami | [`welcome.blade.php`](../resources/views/welcome.blade.php) | ✅ Diganti halaman kejuaraan: daftar kejuaraan berjalan/mendatang, tautan papan skor per gelanggang, tanpa harga. Rutenya kini `Public\BerandaController`. |
| K3 | Kritis | Gelanggang | IST/KONT | Tombol hukuman wasit berbunyi "Ringan/Sedang/Berat"; padanan resmi memakai `text-silat-teks-samar` → kontras **1.05 / 1.74 / 1.03** | [`silat/wasit.blade.php:60,64,68`](../resources/views/silat/wasit.blade.php) | ✅ Pembinaan/Teguran/Peringatan jadi label utama, sebutan sehari-hari turun ke baris kedua. Kontras terukur ulang: **5.28 / 5.24 / 5.20** (dari 1.05 / 1.74 / 1.03). |
| T1 | Tinggi | Gelanggang | KONT | Teks sisi merah gagal AA: nama kontingen & label hukuman **2.89**, "Sudut merah" **3.97**. Sisi biru 4.63–6.47 | `components/silat/blok-sudut.blade.php` | ✅ Nuansa merah dinaikkan ke `#fff0f0` / `#fff5f5`. Terukur **4.70** dan **4.86**, setara sisi biru (4.63 / 6.47). Berlaku di `blok-sudut` dan `papan-skor`. |
| T2 | Tinggi | Gelanggang | KONT | Indikator koneksi "Tersambung" kontras **1.54** — satu-satunya penanda sistem hidup nyaris tak terbaca | [`silat/juri.blade.php:33`](../resources/views/silat/juri.blade.php) dan panel lain | ⛔ **Dibatalkan** — lihat §0. Kontras sesungguhnya 10.6:1. Bobot keadaan terputus tetap dinaikkan lewat `x-silat.indikator-koneksi`. |
| T3 | Tinggi | Gelanggang | CON | PWA juri gagal dipasang. `<link rel="manifest">` diambil tanpa kredensial → 302 ke `/login` → `Manifest: Line: 1, column: 1, Syntax error` | [`silat/juri.blade.php:3`](../resources/views/silat/juri.blade.php) | ✅ `crossorigin="use-credentials"` ditambahkan; komentar XML ilegal di `juri.svg` diperbaiki; ikon PNG 192/512 ditambahkan. Panel juri kini **0 error, 0 warning**. |
| T4 | Tinggi | Gelanggang | IA | Riwayat Dewan Juri: 39 baris tanpa **waktu** dan tanpa **identitas juri**; belasan baris berbunyi persis sama | [`silat/dewan-juri.blade.php`](../resources/views/silat/dewan-juri.blade.php) | ✅ Tiap baris riwayat menampilkan jam:menit:detik dan penekannya ("Juri 1, Juri 2"), disuplai lewat relasi `ScoreEvent::judgeInputs()`. |
| T5 | Tinggi | Gelanggang | TAP | Jarak kolom merah–biru **8px**, sama dengan jarak antar jenis serangan. Selip 8px ke samping = nilai jatuh ke lawan | [`silat/juri.blade.php:42`](../resources/views/silat/juri.blade.php) | ✅ `gap-x-6 gap-y-2` — lorong merah–biru terukur **24px**, sela antar baris tetap 7px. |
| T6 | Tinggi | Publik | BHS | Halaman login memuat testimoni fiktif "Maya Wardhani · Finance Lead · Nusantara Logistik" dan angka "Rp 4,1T tagihan diproses" | [`components/auth/trust-panel.blade.php`](../resources/views/components/auth/trust-panel.blade.php) | ✅ Testimoni fiktif diganti `x-auth.panel-kejuaraan`: hanya menyebut apa yang dilakukan aplikasi dan siapa yang masuk. Kosakata "workspace"/"email kerja" ikut dibersihkan. |
| T7 | Tinggi | Admin | TBL | Bagan menampilkan **174 baris / 11.567px** (12,8 layar); 172 di antaranya "0 peserta sah". Tanpa pencarian, filter, atau paginasi — padahal Kejuaraan punya ketiganya | [`admin/bagan/index.blade.php`](../resources/views/admin/bagan/index.blade.php) | ✅ Saringan bawaan + pencarian + chip. Halaman turun dari **11.567px jadi 900px**, 174 baris jadi 2, dengan keterangan berapa kelas disembunyikan. |
| T8 | Tinggi | Gelanggang | WARNA | "Mulai babak" dan "Akhiri partai" memakai `bg-silat-merah` — warna yang di layar yang sama berarti identitas sudut | [`silat/papan.blade.php:52,92`](../resources/views/silat/papan.blade.php) | ✅ Token `--silat-aksi` (bidang terang, teks gelap) dipakai seluruh tombol aksi di panel operator, wasit, dewan, dan Jurus. Tidak ada lagi tombol bersudut merah/biru. |
| S1 | Sedang | Gelanggang/Publik | WARNA | Emas dipakai untuk tombol "Ajukan protes" dan eyebrow "LIVE SCORE", padahal `silat.css` menetapkan emas hanya untuk juara/medali | [`silat/keberatan.blade.php:48,140`](../resources/views/silat/keberatan.blade.php), [`public/live/gelanggang.blade.php:5`](../resources/views/public/live/gelanggang.blade.php) | ✅ Emas hanya tersisa di papan hasil, rekap medali, dan baris juara. Enam pemakaian lain dialihkan. |
| S2 | Sedang | Publik | IST | Layar publik menulis "**Binaan**", naskah menulis "Pembinaan" | [`public/live/gelanggang.blade.php:40,51`](../resources/views/public/live/gelanggang.blade.php) | ✅ "Pembinaan" di kedua sisi papan publik. |
| S3 | Sedang | Semua | IST | "Atlet" dipakai 85×, "Pesilat" 11×. Berita acara resmi berkolom "Atlet" | seluruh `resources/views`, [`admin/rekap/berita-acara.blade.php`](../resources/views/admin/rekap/berita-acara.blade.php) | ✅ Berita acara memakai kolom "Pesilat". |
| S4 | Sedang | Semua | SEBUT | Satu badan, tiga nama: role `pengawas-wasit-juri`, panel "Dewan Juri", PDF "Dewan Wasit Juri" | roles seeder, [`silat/dewan-juri.blade.php`](../resources/views/silat/dewan-juri.blade.php), `berita-acara.blade.php:104-106` | ✅ Seluruh teks yang dilihat pengguna memakai "Dewan Wasit Juri", mengikuti label role di seeder dan blok tanda tangan PDF. URL tidak diubah. |
| S5 | Sedang | Gelanggang | BHS | Partai yang belum pernah mulai menawarkan "Mulai babak **berikutnya**" (babak `–/3`) | [`silat/papan.blade.php:53`](../resources/views/silat/papan.blade.php) | ✅ Label menyebut nomor babaknya — "Mulai babak 1". |
| S6 | Sedang | Admin | STATE | `…/panel` adalah fragmen tanpa layout, tapi rutenya GET biasa — dibuka langsung/di-refresh, halamannya tampil tanpa CSS | `admin/{turnamen,kontingen,users,roles}/panel.blade.php` | ✅ `x-layouts.fragmen`: kunjungan langsung dibungkus layout penuh, permintaan drawer tetap fragmen telanjang. Keduanya diverifikasi. |
| S7 | Sedang | Admin | IA | Dashboard super-admin masih KPI boilerplate (Pengguna/Role/Permission/Resource, grafik "Pengguna baru", panduan "Buat Resource baru") — nol angka pertandingan | [`dashboard.blade.php`](../resources/views/dashboard.blade.php) | ✅ KPI jadi Kontingen / Pendaftaran terverifikasi / Partai hari ini / Menunggu verifikasi; grafik diganti jadwal hari ini per gelanggang; "Mulai dari mana" jadi urutan kerja kejuaraan. |
| S8 | Sedang | Overlay | PAPAN | Scorebug tidak menampilkan babak maupun hukuman; penonton siaran tak tahu babak ke berapa dan kenapa skor turun −5 | [`overlay/scorebug.blade.php`](../resources/views/overlay/scorebug.blade.php) | ✅ Scorebug menampilkan "BABAK 2/3" dan deret pip hukuman kedua sudut. |
| S9 | Sedang | Gelanggang | TAP | Tombol hukuman wasit **98×56px**, "Catat hitungan" **239×30px**, input hitungan **64×34px** — semua di bawah `--silat-sentuh-min: 64px` yang ditetapkan token | [`silat/wasit.blade.php`](../resources/views/silat/wasit.blade.php) | ✅ Seluruh kontrol panel wasit terukur **64px** (dari 56 / 30 / 34). |
| S10 | Sedang | Publik | CON | `TypeError: Cannot read properties of null (reading 'status')` pada `match.status === 'selesai'` saat render awal | [`public/live/gelanggang.blade.php`](../resources/views/public/live/gelanggang.blade.php) | ✅ `match?.` di seluruh akses. Halaman publik **0 error console**. |
| S11 | Sedang | Admin | IZIN | Juri melihat tautan "Design system" di sidebar | [`layouts/partials/sidebar.blade.php`](../resources/views/layouts/partials/sidebar.blade.php) + `config/design-system.php` | ✅ Tautan design system hanya tampil bagi yang berizin `resources.view`. |
| S12 | Sedang | Gelanggang | IA | Panel Keberatan tidak menampilkan papan skor — pemutus protes tak melihat konteks angka | [`silat/keberatan.blade.php`](../resources/views/silat/keberatan.blade.php) | ✅ Papan skor kedua sudut disisipkan di panel Keberatan. |
| S13 | Sedang | Admin | KERTAS | Berita acara memuat waktu tiap nilai tapi tidak memuat **juri mana** yang memberi | `berita-acara.blade.php` | ✅ Kolom "Juri" pada daftar nilai dan "Dicatat oleh" pada daftar hukuman. |
| R1 | Rendah | Admin | BHS | Dashboard seorang juri berjudul "Dashboard — Ringkasan singkat isi aplikasi" | `dashboard.blade.php` | ✅ Judul mengikuti peran — aparat melihat "Beranda · Partai tempat Anda ditugaskan hari ini", bukan "Dashboard · Ringkasan singkat isi aplikasi". |
| R2 | Rendah | Admin | IA | Kartu "Partai saya" tidak menyebut status partai dan tidak menandai sudut merah/biru | `dashboard.blade.php` | ✅ Titik warna sudut (dengan `aria-label`) dan badge status Berlangsung/Menunggu. |
| R3 | Rendah | Admin | RESP | Breadcrumb disembunyikan di bawah 640px, padahal hierarki dalam (`turnamen/…/kontingen/…/atlet`) | [`layouts/partials/topbar.blade.php`](../resources/views/layouts/partials/topbar.blade.php) | ✅ Breadcrumb tampil di HP dalam bentuk ringkas: tautan naik satu tingkat + halaman sekarang. |
| R4 | Rendah | Gelanggang | JARAK | Panel operator/keberatan menyisakan 30–60% tinggi layar kosong | `silat/papan.blade.php`, `silat/keberatan.blade.php` | ✅ Panel operator memakai `h-dvh`, papan skor mengisi sisa tinggi layar. Panel keberatan terisi papan skor (S12). |
| R5 | Rendah | Admin | CON | `apexcharts` 843 kB (238 kB gzip) ikut bundel admin demi satu grafik dashboard | `resources/js/app.js` | ⛔ **Dibatalkan** — lihat §0. apexcharts sudah lazy sejak awal. |

---

## 3. Rincian per permukaan

> Bagian ini menceritakan keadaan **sebelum** perbaikan, beserta bukti yang mendasarinya. Ia sengaja dibiarkan utuh sebagai catatan pemeriksaan; keadaan sesudahnya ada di kolom Status pada [§2](#2-tabel-temuan) dan di screenshot berakhiran `-setelah.png`.


### 3.1 Publik

<a id="k1"></a>**K1 — Pemenang tertulis seperti terdiskualifikasi.** Endpoint `/overlay/state/8` mengembalikan `{"win_reason":"diskualifikasi","winner_corner":"blue"}`; artinya sudut biru **menang karena lawannya** didiskualifikasi. Panel Dewan Juri menuliskannya dengan benar: "Partai selesai — diskualifikasi, sudut biru menang." Halaman publik merender nama pemenang lalu `win_reason` mentah, sehingga terbaca "**Candra Setiawan — diskualifikasi**" tepat di bawah kata "Hasil" ([12-live-gelanggang.png](audit-uiux/12-live-gelanggang.png)). Penonton menyimpulkan sebaliknya dari kenyataan.

Perbaikannya murah: peta label yang benar sudah ditulis di `overlay/result.blade.php:5` dan tinggal dipakai ulang.

<a id="k2"></a>**K2 — Landing boilerplate.** Halaman depan ([01-landing-desktop.png](audit-uiux/01-landing-desktop.png)) berjudul "Hak akses yang berubah lewat panel, bukan lewat deploy", menjelaskan RBAC resource key, memuat tabel harga tiga tingkat, dan menutup dengan footer "Digital Scoring Pencak Silat · boilerplate Laravel". Tidak ada satu pun kata tentang pencak silat, kejuaraan, atau jadwal. Ini alamat pertama yang dibuka penonton dan official kontingen lewat tunnel.

**T6 — Testimoni fiktif di login.** Panel kanan halaman masuk ([02-login-desktop.png](audit-uiux/02-login-desktop.png)) menampilkan kutipan bernama orang dan perusahaan, tentang penutupan buku akuntansi, plus "2.400+ tim keuangan · Rp 4,1T tagihan diproses · 99,9% uptime". Selain tidak relevan, angka dan nama yang dikarang seperti ini sebaiknya tidak ikut terbit.

### 3.2 Panel gelanggang

<a id="k3"></a>**K3 — Istilah hukuman menyimpang dan tak terbaca.** Panel wasit ([09-wasit-375.png](audit-uiux/09-wasit-375.png)) memberi tiga tombol besar berbunyi **Ringan · Sedang · Berat**. Istilah resmi Pasal 11.6.d.4 adalah Pembinaan, Teguran, Peringatan — dan istilah itulah yang dipakai layar rekap tepat di atasnya, di panel operator, dan di halaman Setelan peraturan. Padanan resminya memang dicantumkan sebagai sub-label, tapi diukur:

| Sub-label | Warna teks | Latar tombol | Rasio |
|---|---|---|---|
| `(pembinaan)` | `#6e6e76` | `#6b6b73` | **1.05** |
| `(teguran)` | `#6e6e76` | `#d98324` | **1.74** |
| `(peringatan)` | `#6e6e76` | `#d42027` | **1.03** |

Ambang AA untuk teks 12px adalah 4.5. Pada 1.03 teksnya praktis tidak ada. Jadi yang benar-benar dibaca wasit hanya Ringan/Sedang/Berat — kosakata yang harus ia terjemahkan sendiri, di bawah tekanan waktu, ke sanksi yang punya konsekuensi angka berbeda (0, −1/−2, −5/−10).

**T1 — Sisi merah lebih sulit dibaca daripada sisi biru.** Diukur pada panel operator:

| Teks | Sisi merah | Sisi biru |
|---|---|---|
| Nama kontingen | **2.89** | 4.63 |
| Label Pembinaan/Teguran/Peringatan | **2.89** | 4.63 |
| "Sudut merah/biru" | **3.97** | 6.47 |

Merah dan biru ditetapkan peraturan sebagai identitas sudut, jadi keduanya harus terbaca setara. Saat ini informasi pesilat merah konsisten lebih redup.

**T5 — Jarak antar tombol juri.** Panel juri adalah bagian terkuat dari sistem ini: enam tombol **176×247px** memenuhi layar 375×812 tanpa gulir, masing-masing sudah bericon, berlabel, berangka nilai, dan ber-`aria-label` ("Pukulan, nilai 1, sudut merah"). Ukurannya jauh di atas minimum 64px yang dijanjikan token.

Yang perlu dibenahi hanya jaraknya. `grid-cols-2 gap-2` membuat jarak horizontal antara kolom merah dan kolom biru **8px** — sama persis dengan jarak vertikal antar jenis serangan (7–8px). Padahal biaya kedua kesalahan itu tidak sama: selip vertikal menggeser nilai 1 ke 2 pada pesilat yang benar; selip horizontal **memberikan nilai kepada lawan**. Jarak seharusnya mencerminkan perbedaan itu.

**T4 — Riwayat Dewan Juri tidak bisa ditelusuri.** Panel ini ([10-dewan-juri.png](audit-uiux/10-dewan-juri.png)) adalah tempat koreksi resmi dilakukan saat protes. Isinya 39 baris; belasan di antaranya berbunyi persis "Biru · Babak 1 · Pukulan (1)". Diperiksa lewat DOM: tidak ada satu pun cap waktu (`adaTeksWaktu: false`) dan tidak ada identitas juri (`adaKataJuri: false`). Dewan juri tidak punya cara menunjuk nilai mana yang keliru.

Datanya bukan tidak ada — berita acara PDF ([16-berita-acara.png](audit-uiux/16-berita-acara.png)) memuat kolom Waktu berisi `10:49:01`, `10:49:07`, dan seterusnya. Ia hanya belum ditampilkan di layar yang paling membutuhkannya.

Catatan positif: seluruh 39 tombol "Batalkan" sudah `disabled` karena partai telah disahkan, dan alasannya dinyatakan di atas daftar ("Sudah disahkan"). Perilaku ini benar.

### 3.3 Admin

**T7 — Bagan tenggelam di kelas kosong.** Halaman Bagan ([15-bagan-index.png](audit-uiux/15-bagan-index.png)) memuat 174 kelas; 172 di antaranya "0 peserta sah". Tinggi halaman 11.567px pada viewport 900px — hampir 13 layar penuh. Satu-satunya `input` pencarian di halaman itu adalah command palette yang tersembunyi ("Cari halaman…"), bukan pencarian tabel. Bandingkan dengan halaman Kejuaraan yang punya pencarian sekaligus filter chip status.

Hal yang sudah benar di layar ini: 172 tombol "Susun bagan" untuk kelas kosong seluruhnya `disabled`, dan callout di atas menjelaskan prasyaratnya (verifikasi dan timbang badan harus selesai).

**Setelan peraturan adalah tolok ukur.** Halaman ini ([14-peraturan.png](audit-uiux/14-peraturan.png)) memperlihatkan seperti apa layar yang benar-benar memahami domainnya: tiap field disertai rujukan pasal ("Pasal 16 ayat 1 huruf a", "Pasal 11.6.e", "Pasal 11.6.d.4"), 40 input dikelompokkan ke tujuh blok bertajuk jelas, dan ada callout khusus **"Tidak diatur naskah"** yang memisahkan keputusan penyelenggara dari ketentuan peraturan. Keadaan terkuncinya pun dijelaskan sebabnya, bukan sekadar dinonaktifkan.

Standar inilah yang belum diikuti panel wasit (K3) dan dashboard (S7).

### 3.4 Overlay siaran

Diukur pada kanvas 1920×1080 ([13-overlay-scorebug.png](audit-uiux/13-overlay-scorebug.png)):

| Unsur | Ukuran |
|---|---|
| Angka skor | 44px |
| Nama pesilat | 22px |
| Timer | 30px |
| Tahap ("Semifinal") | 13px |
| Latar `body` | `rgba(0,0,0,0)` — transparan, benar untuk vMix |

Bar duduk di bawah-tengah, merah kiri dan biru kanan sesuai konvensi, dengan bayangan lembut yang akan terbaca di atas gambar kamera. Yang hilang: **penanda babak** dan **hukuman** (`adaBabak: false`, `adaHukuman: false`). Penonton siaran karena itu tidak tahu ini babak ke berapa dari tiga, dan tidak punya penjelasan ketika skor turun −5 atau −10 akibat peringatan.

Rasio skor terhadap nama pesilat 2:1. Papan skor gelanggang konvensional membuat angka jauh lebih dominan; ini layak ditinjau bersama operator siaran.

---

## 4. Kesesuaian domain pencak silat

### 4.1 Istilah layar vs naskah 2025

> Kolom Status di bawah sudah mencerminkan keadaan sesudah perbaikan.


| Istilah naskah | Dipakai aplikasi | Status |
|---|---|---|
| Pembinaan / Teguran / Peringatan | Dipakai benar di operator, dewan juri, rekap, setelan peraturan | ✅ |
| Pembinaan | Sudah "Pembinaan" di halaman live publik | ✅ (S2 ditambal) |
| Pembinaan / Teguran / Peringatan | Istilah naskah jadi label utama di panel wasit; sebutan sehari-hari turun ke baris kedua | ✅ (K3 ditambal) |
| Pesilat | Berita acara memakai "Pesilat". Layar admin pra-acara masih "Atlet" — disengaja, itu entitas data, bukan peserta yang sedang bertanding | ✅ (S3 ditambal untuk dokumen resmi) |
| Pengawas / Dewan Wasit Juri | Satu sebutan di seluruh teks yang dilihat pengguna: "Dewan Wasit Juri" | ✅ (S4 ditambal) |
| Pukulan (1) / Tendangan (2) / Jatuhan (3) | Persis, lengkap dengan angka nilainya di tombol | ✅ |
| Sudut merah / sudut biru | Konsisten, tidak pernah tertukar | ✅ |
| Ketua Pertandingan, Delegasi Teknik, Wasit Komisi Protes, Petugas Timbang | Terdaftar sebagai role sesuai Pasal 13 | ✅ |

Kosakata boilerplate juga sudah dibersihkan: "Masuk ke workspace" jadi "Masuk", "Email kerja" jadi "Email", placeholder `nama@perusahaan.com` jadi `nama@contoh.id`, dan label "Workspace local" di sidebar jadi "Lingkungan local". Semuanya asing bagi wasit dan juri yang login dari HP dengan akun singkat buatan panitia.

### 4.2 Konvensi papan skor

| Konvensi | Hasil |
|---|---|
| Merah di kiri, biru di kanan | ✅ konsisten di panel juri, operator, dewan juri, live publik, dan scorebug |
| Angka skor dominan | ✅ 56px di panel, 44px di overlay — jauh lebih besar dari nama |
| Timer di tengah | ✅ |
| Indikator juri berupa titik | ✅ |
| Hukuman terpisah dari nilai | ✅ ditampilkan sebagai deret pip, bukan angka pengurangan telanjang |
| Merah/biru tidak dipakai untuk arti lain | ✅ tombol aksi memakai token `--silat-aksi`; tidak ada lagi tombol bersudut (T8) |
| Emas hanya untuk juara | ✅ tersisa di papan hasil, rekap medali, dan baris juara saja (S1) |
| Papan skor tetap gelap | ✅ tidak ada mode terang, sesuai keputusan yang didokumentasikan |

### 4.3 Perlu konfirmasi aparat

Hal-hal berikut **tidak** dapat diputuskan dari naskah dan sengaja tidak dinilai sebagai cacat. Perlu ditanyakan langsung ke wasit/juri berpengalaman sebelum diubah:

1. Apakah "Ringan/Sedang/Berat" merupakan bahasa lisan yang lazim di gelanggang? Bila ya, ia tetap perlu mendampingi istilah resmi — bukan menggantikannya (K3 tetap berlaku, hanya bentuk perbaikannya berubah).
2. Apakah "atlet" sudah lazim diterima setara "pesilat" dalam dokumen panitia sehari-hari, atau berita acara wajib memakai "pesilat"?
3. Apakah operator siaran menghendaki hukuman tampil di scorebug, atau memang lebih suka memanggil overlay `breakdown` terpisah?
4. Berapa besar angka skor yang dianggap layak di layar tribun venue — 44px pada 1080p bergantung jarak pandang yang hanya bisa diuji di tempat.

---

## 5. Yang sudah baik

Beberapa hal sengaja dicantumkan karena sempat dicurigai bermasalah dari pembacaan kode, lalu terbantah saat diuji. Jangan sampai rusak saat perbaikan dikerjakan.

- **Tombol juri.** 176×247px, enam tombol memenuhi layar HP tanpa gulir, dengan ikon, label, angka nilai, dan `aria-label` deskriptif per tombol.
- **Fokus keyboard di panel gelanggang tetap terlihat.** `silat.css` memang tidak punya aturan `focus-visible`, tapi outline bawaan peramban (2px putih solid) justru kontras kuat di atas latar gelap. Bukan cacat.
- **Validasi form modal ditangani benar.** `x-ui.modal` punya prop `errorsFor` dan membuka dirinya sendiri saat validasi gagal (`modal.blade.php:37`), dan tiap layar meneruskan `old('_form')` untuk menentukan modal mana. Isian pengguna dipulihkan lewat `old()`. Kekhawatiran "modal tertutup, isian hilang" tidak terbukti.
- **Setelan peraturan.** Rujukan pasal per field, pemisahan tegas antara ketentuan naskah dan keputusan penyelenggara, dan penjelasan sebab penguncian.
- **State kosong di Rekap.** Tiap blok kosong menjelaskan apa yang membuka isinya: "Medali muncul setelah hasil partai atau penampilan Jurus disahkan."
- **Prasyarat bertahap terkomunikasikan.** Tombol yang belum boleh ditekan benar-benar `disabled` dan disertai callout alasannya (Bagan, Setelan peraturan, Dewan Juri setelah pengesahan).
- **Kartu "Partai saya" bekerja.** Juri yang login di HP langsung melihat partainya beserta peran, kelas, gelanggang, dan jam — persis seperti dijanjikan `PANDUAN-OPERASIONAL.md`. Tinggi kartu 60px, cukup untuk jempol.
- **Sidebar kontekstual.** Begitu satu kejuaraan aktif, submenu turunannya muncul di tempat; breadcrumb penuh di layar lebar.
- **Overlay transparan dan bersih**, siap dipakai vMix tanpa penyesuaian.

---

## 6. Keputusan sadar yang dipertahankan

Bukan bug. Dicatat supaya tidak "diperbaiki" oleh orang berikutnya.

| Keputusan | Alasan | Konsekuensi yang diterima |
|---|---|---|
| Papan skor tidak punya mode terang | Dibaca dari tribun dan dilapiskan di atas gambar kamera | Tidak mengikuti preferensi tema perangkat |
| `maximum-scale=1, user-scalable=no` di layout gelanggang | Cegah cubitan tak sengaja menggeser tombol saat ditekan cepat | Melanggar WCAG 1.4.4 (Resize Text) — pengguna berpenglihatan lemah tidak bisa memperbesar panel juri |
| Ekspor CSV, bukan `.xlsx` | Tercatat di `RENCANA.md` T8.4 | Panitia perlu langkah tambahan untuk membuka di Excel dengan format rapi |
| Font di-bundle, bukan CDN | Gelanggang sering tanpa internet | Bundel lebih besar |

---

## 7. Urutan pengerjaan yang ditempuh

1. **K1** — perbaiki teks hasil di halaman publik. Satu baris, memakai peta label yang sudah ada. Dampak paling besar per usaha.
2. **K3 + T1 + T2** — satu putaran perbaikan kontras dan istilah di panel gelanggang. Ketiganya menyentuh berkas yang sama dan sama-sama soal keterbacaan di bawah tekanan.
3. **T3** — tambahkan `crossorigin="use-credentials"`. Satu atribut; memulihkan langkah PWA yang sudah tertulis di panduan hari-H.
4. **T5 + T8** — jarak tombol juri dan warna tombol aksi operator. Keduanya mencegah kesalahan operasional, bukan sekadar memperindah.
5. **T4** — tampilkan waktu dan identitas juri di riwayat Dewan Juri. Datanya sudah dihitung untuk berita acara.
6. **K2 + T6** — ganti landing dan panel login. Pekerjaan halaman baru, jadi diletakkan setelah perbaikan bernilai tinggi yang murah.
7. **T7 + S7** — saring daftar Bagan, ganti KPI dashboard. Keduanya soal "layar menampilkan hal yang salah", bukan salah render.
8. Sisanya (S1–S13, R1–R5) sesuai urutan tabel.

Seluruh langkah di atas sudah dikerjakan. Yang tersisa bukan pekerjaan kode, melainkan empat pertanyaan di [§4.3](#43-perlu-konfirmasi-aparat) yang hanya bisa dijawab wasit dan juri berpengalaman.

---

## Lampiran — catatan lingkungan

Saat inspeksi dimulai, `.env` masih memuat `REVERB_HOST=10.5.10.239` sementara mesin berada di `192.168.1.76`. Akibatnya seluruh panel gelanggang akan menampilkan "Terputus" tanpa penjelasan apa pun tentang sebabnya — tidak ada pesan yang mengarahkan panitia memeriksa alamat Reverb. Nilainya dialihkan sementara ke `127.0.0.1` selama audit dan penambalan, lalu **dikembalikan ke `10.5.10.239`** setelah selesai. Alamat itu tetap perlu disesuaikan ke IP LAN venue sebelum dipakai di gelanggang.

Ini bukan temuan UI, tapi ia yang membuat sisa T2 tetap layak dikerjakan: satu chip 11px adalah seluruh umpan balik yang didapat panitia ketika jaringan pertandingan tidak tersambung. Sekarang keadaan terputus tampil sebagai bidang merah yang lebih besar, berkedip pelan, dan menyebutkan bahwa nilai belum tersimpan.
