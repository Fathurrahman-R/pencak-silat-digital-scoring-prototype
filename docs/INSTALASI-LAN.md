# Panduan Instalasi — Satu Mesin Windows untuk LAN Gelanggang

> NFR-08: seluruh sistem harus bisa dipasang dari nol di satu mesin Windows, dan setelah dependensi terunduh, tidak butuh akses internet lagi untuk berjalan.

## 1. Prasyarat (butuh internet, sekali saja)

| Perangkat lunak | Versi | Catatan |
|---|---|---|
| PHP | 8.3+ | Aktifkan ekstensi `pdo_mysql`, `mbstring`, `intl`, `gd`, `fileinfo` |
| Composer | 2.x | |
| Node.js | 20+ | Untuk `npm install` dan build aset Vite |
| MySQL | 8.0+ | Bisa lewat Laravel Herd, XAMPP, atau instalasi mandiri |
| Git | terbaru | Untuk clone repo |

Rekomendasi: **Laravel Herd untuk Windows** membundel PHP + Nginx + MySQL dalam satu installer, paling sedikit langkah manual.

## 2. Clone dan pasang dependensi

```powershell
git clone <url-repo> D:\digiscoring-prototype
cd D:\digiscoring-prototype

composer install
npm install
```

## 3. Database

Buat dua database (aplikasi + test):

```sql
CREATE DATABASE digiscoring      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE digiscoring_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 4. Konfigurasi `.env`

```powershell
copy .env.example .env
php artisan key:generate
```

Sunting `.env`:

```env
DB_DATABASE=digiscoring
DB_USERNAME=root
DB_PASSWORD=

# Kunci Reverb -- wajib diisi, bawaan .env.example sengaja kosong. Cetak nilai
# acaknya dengan perintah di bawah blok ini, lalu tempel ke sini.
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=

# Reverb -- alamat yang dijangkau HP juri/wasit di LAN, BUKAN localhost
# kalau HP menyambung lewat WiFi venue. Isi dengan IP mesin server di
# jaringan itu, misalnya 192.168.1.10.
REVERB_HOST="192.168.1.10"
REVERB_PORT=8080

# Alamat yang dipakai APLIKASI untuk mendorong siaran ke Reverb. Selalu
# loopback selama Reverb berjalan di mesin yang sama -- lewat IP LAN mesin
# sendiri satu siaran diukur 15,7 ms, lewat loopback 1,8 ms.
REVERB_PUBLISH_HOST=127.0.0.1

VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME=http

# Overlay vMix -- default sudah mencakup RFC 1918, biasanya tidak perlu diisi
# OVERLAY_ALLOWED_CIDRS=127.0.0.1/32,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
```

Mencetak tiga kunci Reverb:

```powershell
php -r "printf('REVERB_APP_ID=%d%sREVERB_APP_KEY=%s%sREVERB_APP_SECRET=%s%s', random_int(100000,999999), PHP_EOL, bin2hex(random_bytes(10)), PHP_EOL, bin2hex(random_bytes(10)), PHP_EOL);"
```

**Jangan memakai `php artisan reverb:install`.** Perintah itu menempelkan blok Reverb baru di akhir `.env` tanpa membuang baris yang sudah ada, dan nilai terakhirlah yang dipakai Laravel -- artinya `REVERB_HOST` berisi IP LAN yang baru saja disunting akan tertimpa `localhost` di baris bawahnya, dan seluruh HP juri kehilangan WebSocket tanpa pesan galat yang jelas.

**Kenapa `REVERB_HOST` bukan `localhost`.** HP juri menyambung ke server dari perangkat lain di jaringan yang sama. `localhost` di HP menunjuk ke HP itu sendiri, bukan ke server. Isi dengan alamat IP LAN mesin server (`ipconfig` di PowerShell untuk melihatnya), dan pastikan alamat itu **statis** (set IP statis di adapter jaringan Windows, atau reservasi DHCP di router) -- kalau berubah di tengah turnamen, seluruh HP juri kehilangan koneksi.

**`REVERB_HOST` dan `REVERB_PUBLISH_HOST` adalah dua arah yang berbeda.** Yang pertama dipakai peramban (HP juri, panel operator, vMix) untuk menyambung ke WebSocket; yang kedua dipakai aplikasi di server untuk mendorong siarannya ke Reverb. Aplikasi tidak perlu keluar ke jaringan untuk bicara dengan proses di mesinnya sendiri: diukur di satu mesin, satu dorongan siaran memakan 15,7 ms lewat IP LAN dan 1,8 ms lewat loopback. Selisihnya menempel di tiap penekanan tombol juri sepanjang pertandingan. Isi `REVERB_PUBLISH_HOST` dengan alamat lain hanya kalau Reverb sengaja dijalankan di mesin terpisah.

**Mengubah `REVERB_HOST` sesudahnya wajib diikuti `npm run build`.** `VITE_REVERB_HOST` dibaca saat aset dikompilasi, bukan saat aplikasi berjalan, jadi alamatnya ikut tertanam di dalam `public/build/assets/echo-*.js`. Menyunting `.env` lalu me-restart server **tidak** mengubah apa pun: panel tetap mencoba menyambung ke alamat lama sampai asetnya dibangun ulang. Panel memang menampilkan penanda "Terputus" yang menonjol saat ini terjadi, tapi baru sesudah percobaan sambungnya kedaluwarsa.

## 4b. Setelan `php.ini`

Dua setelan berikut tidak punya nilai bawaan yang cocok untuk kejuaraan, dan keduanya baru terasa akibatnya di hari-H.

```ini
; Foto struk transfer dan foto akta dari kamera HP rutin berukuran 2-5 MB.
; Aplikasi memvalidasi sampai 4 MB, tapi yang benar-benar berlaku adalah
; yang terkecil di antara setelan ini dan aturan aplikasi -- kalau ini
; dibiarkan 2M, bendahara akan ditolak terus untuk berkas yang menurut
; aplikasi masih boleh.
upload_max_filesize = 8M
post_max_size = 16M

; Wajib Off di mesin yang dipakai kejuaraan. Kalau On, unggahan yang
; melebihi post_max_size membalas peringatan PHP mentah beserta angka
; konfigurasi servernya, bukan halaman galat aplikasi.
display_errors = Off
log_errors = On

; OPcache menyimpan hasil kompilasi PHP di memori. Tanpa ini, tiap
; permintaan mengompilasi ulang seluruh berkas yang dipakainya -- puluhan
; milidetik yang menempel di setiap tekanan tombol dan setiap tarikan layar.
; Herd sudah menyalakannya; PHP yang dipasang sendiri belum tentu.
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000

; Di hari-H berkasnya tidak berubah, jadi PHP tidak perlu memeriksa cap
; waktu tiap berkas tiap permintaan. Kembalikan ke 1 kalau kodenya sedang
; disunting -- dengan 0, perubahan kode tidak berlaku sampai PHP di-restart.
opcache.validate_timestamps = 0
```

Setelah menyunting `php.ini`, restart PHP (tutup dan jalankan ulang `php artisan serve`, atau restart layanan web-nya).

## 5. Migrasi, seed, build aset

```powershell
php artisan migrate --seed
php artisan storage:link
npm run build
```

### Sebelum hari-H: `php artisan optimize`

```powershell
php artisan optimize
```

Satu perintah yang menyatukan cache konfigurasi, rute, tampilan, dan event. Tanpa itu, tiap permintaan membaca ulang `.env`, menyusun ulang seluruh daftar rute, dan memeriksa apakah tiap berkas Blade sudah dikompilasi.

Pasangannya di `.env`:

```env
APP_ENV=production
APP_DEBUG=false
```

`APP_DEBUG=true` membuat Laravel mengumpulkan jejak tiap query dan tiap pengecualian sepanjang permintaan -- berguna saat mengembangkan, pemborosan saat gelanggang berjalan, dan ia menayangkan isi `.env` ke siapa pun yang memicu galat.

Sesi dan cache boleh pindah dari basis data ke berkas kalau seluruh kejuaraan berjalan di satu mesin:

```env
SESSION_DRIVER=file
CACHE_STORE=file
```

Dengan bawaan `database`, tiap permintaan dari tiap panel membaca dan menulis satu baris sesi -- dua perjalanan ke MySQL sebelum permintaannya sendiri mulai dikerjakan. Tetap di `database` kalau suatu saat aplikasinya dijalankan di lebih dari satu mesin sekaligus.

**Sesudah menyunting `.env` atau kode apa pun, jalankan ulang `php artisan optimize`** (atau `php artisan optimize:clear` untuk kembali ke mode pengembangan). Konfigurasi yang sudah di-cache tidak lagi membaca `.env`, jadi suntingan yang tidak diikuti perintah ini tidak berlaku sama sekali -- termasuk `REVERB_HOST` yang baru.

## 6. Jalankan server (produksi/hari-H, bukan `composer run dev`)

Empat proses harus hidup bersamaan. Untuk hari-H, jalankan sebagai empat jendela PowerShell terpisah (atau daftarkan sebagai Windows Service lewat NSSM kalau butuh auto-restart):

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

```powershell
php artisan reverb:start --host=0.0.0.0 --port=8080
```

```powershell
php artisan queue:listen --tries=1
```

`--host=0.0.0.0` wajib untuk kedua perintah pertama -- tanpa itu, server hanya menerima koneksi dari mesin itu sendiri dan HP di LAN tidak akan bisa menyambung sama sekali.

### `php artisan serve` melayani SATU permintaan pada satu waktu

Server bawaan PHP tidak punya banyak pekerja di Windows: permintaan kedua menunggu yang pertama selesai.

Akibatnya baru terasa saat gelanggang ramai: tiga juri menekan beruntun, empat panel dan lima halaman overlay masing-masing menarik keadaan terbaru, dan antreannya tumbuh lebih cepat daripada terurai. Layar tertinggal beberapa detik dari matras, dan aksi seperti membatalkan nilai ikut terasa lambat karena permintaannya mengantre di belakang tarikan-tarikan itu.

Diukur di satu mesin pengembangan, endpoint state yang sama:

| | 1 permintaan | 5 bersamaan | 10 bersamaan |
|---|---|---|---|
| `php artisan serve` | ~200 ms | ~1150 ms | — |
| Herd (Nginx + PHP-FPM) | ~200 ms | ~440 ms | ~724 ms |

Lima permintaan yang berbaris memakan waktu lima kali lipat; yang berbarengan tidak.

Sisi aplikasi sudah menekan jumlah tarikan: letupan siaran digabung jadi satu tarikan, dan angka skor dipasang langsung dari muatan siarannya. Sisanya urusan server.

**Untuk hari-H, jangan pakai `php artisan serve`.** Pakai Herd (sudah direkomendasikan di §1) atau Nginx/Apache + PHP-FPM yang dipasang sendiri.

Mendaftarkan proyek ke Herd, sekali saja:

```powershell
cd D:\digiscoring-prototype
herd link digiscoring
```

Situsnya lalu hidup di `http://digiscoring.test` (Herd ikut memperbarui `APP_URL`). Reverb tetap dijalankan sendiri seperti di atas -- Herd hanya melayani HTTP, bukan WebSocket:

```powershell
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Untuk HP juri dan wasit di LAN, `*.test` tidak akan terselesaikan dari perangkat lain. Pakai alamat IP mesin server (`http://192.168.1.10:8000`) lewat `php artisan serve` untuk latihan kecil, atau setel Herd/Nginx melayani IP mesin itu untuk kejuaraan sungguhan.

`php artisan serve` tetap memadai untuk memasang, menguji, dan latihan satu-dua orang.

## 7. Firewall Windows

Izinkan port masuk untuk PHP dan Reverb:

```powershell
New-NetFirewallRule -DisplayName "Digiscoring HTTP" -Direction Inbound -LocalPort 8000 -Protocol TCP -Action Allow
New-NetFirewallRule -DisplayName "Digiscoring Reverb" -Direction Inbound -LocalPort 8080 -Protocol TCP -Action Allow
```

## 8. Uji sebelum hari-H

Sediakan dulu data ujinya -- kejuaraan simulasi lengkap dengan akun tiap peran, bagan, dan jadwal:

```powershell
php artisan silat:simulasi
```

Rincian isinya ada di [README](../README.md#kejuaraan-siap-uji-untuk-simulasi-manual). **Buang kejuaraan simulasi ini sebelum hari-H** lewat menu Kejuaraan (hapus permanen), supaya tidak ikut terbaca di live score publik dan rekap medali bersama kejuaraan sungguhan.

- [ ] Buka `http://<IP-server>:8000` dari HP yang tersambung ke WiFi venue (bukan dari mesin server sendiri)
- [ ] Login sebagai juri, buka panel juri, pastikan indikator koneksi hijau ("Tersambung")
- [ ] Kirim satu nilai percobaan dari 2 HP berbeda dalam window konsensus (bawaan 2 detik; kejuaraan simulasi memakai 5 detik), pastikan nilai terbit di panel operator
- [ ] Cabut WiFi satu HP juri di tengah percobaan, sambungkan lagi, pastikan panel resync sendiri tanpa reload manual
- [ ] Matikan dan nyalakan ulang `reverb:start`, pastikan seluruh panel pulih ke state benar

## Setelah ini

- Overlay vMix: lihat `docs/RENCANA.md` Fase 6 dan `resources/views/overlay/`
- Live score publik lewat tunnel: `docs/TUNNELING.md`
- Alur operasional hari-H: `docs/PANDUAN-OPERASIONAL.md`
