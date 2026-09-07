# Panduan Instalasi — Satu Mesin Windows per Gelanggang

> NFR-08: seluruh sistem harus bisa dipasang dari nol di satu mesin Windows, dan setelah dependensi terunduh, tidak butuh akses internet lagi untuk berjalan.

> **Dokumen ini memasang SATU mesin.** Untuk kejuaraan lebih dari satu gelanggang, ulangi seluruh langkah di tiap laptop gelanggang, ditambah satu laptop lagi sebagai node global. Penyetelan yang membedakannya — `SINKRON_PERAN`, `SINKRON_NODE`, `SINKRON_ARENA`, `SINKRON_TOKEN`, `SINKRON_PEER` — ada di [MULTI-GELANGGANG.md](MULTI-GELANGGANG.md), dan arsip buktinya di [ARSIP-BUKTI.md](ARSIP-BUKTI.md).
>
> **Sebelum memigrasikan basis data yang sudah berisi riwayat:** konversi kunci ke ULID memakan sekitar sepuluh menit per seratus ribu baris `judge_inputs`. Jangan dijalankan di sela pertandingan.

## 1. Prasyarat (butuh internet, sekali saja)

| Perangkat lunak | Versi | Catatan |
|---|---|---|
| PHP | 8.3+ | Aktifkan ekstensi `pdo_mysql`, `mbstring`, `intl`, `gd`, `fileinfo`. Paket ZIP resmi PHP untuk Windows sudah membawa `php-cgi.exe` yang dibutuhkan Nginx |
| Nginx | 1.24+ | Paket ZIP dari nginx.org, cukup diekstrak. Dipakai untuk hari-H |
| Composer | 2.x | |
| Node.js | 20+ | Untuk `npm install` dan build aset Vite |
| MySQL | 8.0+ | XAMPP, Laragon, atau instalasi mandiri |
| Git | terbaru | Untuk clone repo |

**PHP dan `php-cgi.exe` harus dari instalasi yang SAMA.** Dua instalasi berbeda berarti dua `php.ini` berbeda: ekstensi yang aktif di baris perintah belum tentu aktif di yang melayani web, dan galatnya baru muncul sebagai halaman kosong di tengah kejuaraan. `scripts\server\jalankan-server.ps1` mengambil `php-cgi.exe` dari folder yang sama dengan `php` di PATH, jadi ini terjaga sendiri selama tidak dipaksa lain.

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

# Reverb -- alamat cadangan; peramban tidak lagi memakainya (lihat
# VITE_REVERB_HOST di bawah), jadi nilainya cuma dipakai kalau
# REVERB_PUBLISH_HOST dikosongkan.
REVERB_HOST="127.0.0.1"
REVERB_PORT=8080

# Alamat yang dipakai APLIKASI untuk mendorong siaran ke Reverb. Selalu
# loopback selama Reverb berjalan di mesin yang sama -- lewat IP LAN mesin
# sendiri satu siaran diukur 15,7 ms, lewat loopback 1,8 ms.
REVERB_PUBLISH_HOST=127.0.0.1

# SENGAJA KOSONG. Peramban menyambung ke alamat yang sedang dibukanya
# sendiri. Isi hanya kalau Reverb dijalankan di mesin yang BERBEDA dari
# yang melayani HTTP.
VITE_REVERB_HOST=
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME=http

# Overlay vMix -- default sudah mencakup RFC 1918, biasanya tidak perlu diisi
# OVERLAY_ALLOWED_CIDRS=127.0.0.1/32,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16

# Saklar siaran. Bawaannya MATI; nyalakan hanya yang benar-benar dipakai
# (penjelasannya di 4c).
OVERLAY_ENABLED=false
LIVE_SCORE_ENABLED=false
```

Mencetak tiga kunci Reverb:

```powershell
php -r "printf('REVERB_APP_ID=%d%sREVERB_APP_KEY=%s%sREVERB_APP_SECRET=%s%s', random_int(100000,999999), PHP_EOL, bin2hex(random_bytes(10)), PHP_EOL, bin2hex(random_bytes(10)), PHP_EOL);"
```

**Jangan memakai `php artisan reverb:install`.** Perintah itu menempelkan blok Reverb baru di akhir `.env` tanpa membuang baris yang sudah ada, dan nilai terakhirlah yang dipakai Laravel -- artinya `VITE_REVERB_HOST` yang sengaja dikosongkan akan tertimpa `localhost` di baris bawahnya, dan seluruh HP juri kehilangan WebSocket tanpa pesan galat yang jelas. (`localhost` di HP menunjuk ke HP itu sendiri, bukan ke server.)

**Alamat WebSocket tidak disetel, ia mengikuti alamat yang dibuka.** HP juri membuka `http://<ip-server>:8000`, jadi nama host di bilah alamatnya memang alamat server di jaringan itu -- dan itulah yang dipakai `resources/js/echo.js` untuk menyambung ke Reverb.

Sebelumnya alamat itu diambil dari `VITE_REVERB_HOST`, yang dibaca Vite saat aset dikompilasi dan ikut tertanam di dalam `public/build/assets/echo-*.js`. Akibatnya dua hal yang sama-sama baru ketahuan di lapangan: IP yang berubah (router venue membagikan alamat lain, mesin pindah jaringan) memutus WebSocket seluruh HP juri sekaligus, dan memperbaikinya menuntut `npm run build` ulang di tengah kejuaraan. Sekarang tidak ada IP yang tertanam di aset sama sekali.

Yang masih menyebut alamat: yang diketik orang di HP-nya. **Reservasi DHCP di router venue tetap dianjurkan** supaya alamat itu tidak berubah di tengah acara dan tidak ada yang perlu mengetik ulang.

Isi `VITE_REVERB_HOST` hanya kalau Reverb sengaja dijalankan di mesin yang **berbeda** dari yang melayani HTTP; sesudah mengisinya, `npm run build` wajib.

**`REVERB_PUBLISH_HOST` arah yang lain lagi:** ia dipakai aplikasi di server untuk mendorong siarannya ke Reverb. Aplikasi tidak perlu keluar ke jaringan untuk bicara dengan proses di mesinnya sendiri -- diukur di satu mesin, satu dorongan siaran memakan 15,7 ms lewat IP LAN dan 1,8 ms lewat loopback. Selisihnya menempel di tiap penekanan tombol juri sepanjang pertandingan. Isi dengan alamat lain hanya kalau Reverb dijalankan di mesin terpisah.

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
; Paket ZIP PHP untuk Windows membawa DLL-nya tapi tidak menyalakannya.
zend_extension = opcache
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000

; Di hari-H berkasnya tidak berubah, jadi PHP tidak perlu memeriksa cap
; waktu tiap berkas tiap permintaan. Kembalikan ke 1 kalau kodenya sedang
; disunting -- dengan 0, perubahan kode tidak berlaku sampai kolam php-cgi
; dijalankan ulang.
opcache.validate_timestamps = 0

; Nginx sudah mengirimkan SCRIPT_FILENAME yang lengkap (lihat
; scripts\server\nginx-digiscoring.conf), jadi PHP tidak perlu menebak
; berkas mana yang dimaksud dari potongan jalur. Dibiarkan 1, tebakan itu
; bisa menjalankan berkas yang bukan diminta.
cgi.fix_pathinfo = 0
```

Satu `php.ini` itu dipakai baris perintah maupun kolam `php-cgi` yang melayani web, selama keduanya dari instalasi PHP yang sama. Setelah menyuntingnya, jalankan ulang servernya: `.\scripts\server\hentikan-server.ps1` lalu `.\scripts\server\jalankan-server.ps1`.

## 4c. Mematikan siaran yang tidak dipakai

Dua saklar, dan keduanya bawaannya **mati**:

| Kunci | Nyalakan kalau |
|---|---|
| `OVERLAY_ENABLED` | Kejuaraan memakai vMix/OBS dan memasang grafis overlay |
| `LIVE_SCORE_ENABLED` | Skor gelanggang benar-benar ditonton dari luar matras -- lewat tunnel, atau lewat layar yang dipasang di lobi |

Yang dihemat bukan halamannya, melainkan siarannya. Selama salah satu saklar menyala, kelima event siaran (`timer.berubah`, `skor.terbit`, `hukuman.terbit`, `partai.berubah`, `juri.input`) mendorong muatannya ke **dua** channel Reverb sekaligus: satu untuk panel juri/wasit/operator, satu lagi `public-live.*` untuk overlay dan live score. Saat kedua saklar mati, channel kedua itu tidak lagi disertakan sama sekali -- satu dorongan per event, bukan dua, pada tiap penekanan tombol juri.

Panel juri, wasit, operator, dan Dewan Wasit Juri **tidak terpengaruh sama sekali**. Yang dicabut hanya channel publiknya.

Yang ikut mati saat saklarnya dimatikan hanya halaman yang memang realtime:

- `OVERLAY_ENABLED=false` -- `/overlay/scorebug`, `/overlay/athlete`, `/overlay/breakdown`, `/overlay/result`
- `LIVE_SCORE_ENABLED=false` -- `/live/gelanggang/{arena}`

Halaman bagan overlay, halaman turnamen, medali, dan bagan publik **tetap hidup apa pun saklarnya**: tidak satu pun memakai WebSocket, jadi mematikannya tidak menghemat apa-apa sementara penonton tetap butuh melihat hasil.

Halamannya tidak berubah jadi 404. Yang muncul adalah halaman yang menjelaskan keadaannya sendiri -- pada overlay, lengkap dengan nama kunci `.env` yang perlu diubah, karena yang membacanya operator IT yang sedang berdiri di depan vMix. Endpoint state-nya (`/overlay/state/{arena}`, `/live/gelanggang/{arena}/state`) membalas `503` tanpa menyentuh database.

**Sesudah mengubah salah satu saklar, jalankan `php artisan config:clear`.** Kalau `php artisan optimize` sudah pernah dijalankan (lihat bagian 5), konfigurasinya sedang di-cache dan perubahan `.env` tidak berlaku sampai cache itu dibuang.

## 4d. Melewati verifikasi dan pembayaran

Dua saklar lagi, keduanya juga bawaannya **mati**, untuk kejuaraan yang mengurus berkas dan uangnya manual di meja sekretariat:

```
PENDAFTARAN_LEWATI_VERIFIKASI=false
PENDAFTARAN_LEWATI_PEMBAYARAN=false
```

| Kunci | Yang berubah saat dinyalakan |
|---|---|
| `PENDAFTARAN_LEWATI_VERIFIKASI` | Pendaftaran yang diajukan **langsung berstatus Terverifikasi**; pemeriksaan kelengkapan berkas dilewati; menu Verifikasi hilang dari sidebar |
| `PENDAFTARAN_LEWATI_PEMBAYARAN` | Verifikasi tidak lagi menuntut tagihan lunas, dan pendaftaran tidak dibekukan saat sesi pembayaran dibuka |

Yang perlu diketahui sebelum menyalakannya:

- **`verified_by` dibiarkan kosong.** Tidak ada manusia yang memutuskan, dan mengisinya dengan id sekretaris akan memalsukan jejak audit.
- **Rutenya tetap terdaftar.** Pendaftaran lama yang sudah telanjur berstatus Diajukan masih bisa diputuskan lewat alamat Verifikasi, hanya menunya yang disembunyikan. Alamat yang tiba-tiba 404 lebih sulit didiagnosa panitia daripada halaman yang menjelaskan dirinya.
- **Tagihan tetap dibuat dan tetap bisa dibayar.** Yang dimatikan adalah paksaannya, bukan fiturnya.
- Setelan ini **per instalasi**, bukan per kejuaraan.

**Sesudah mengubahnya, jalankan `php artisan config:clear`** — alasan yang sama dengan 4c.

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

> **Sesudah ini, rangkaian uji menolak berjalan sampai cachenya dibuang.** Itu disengaja. Konfigurasi yang di-cache membuat `phpunit.xml` mati total: Laravel membaca `bootstrap/cache/config.php` dan tidak pernah lagi melihat `<env>` mana pun -- termasuk nama database uji. Uji lalu menunjuk database yang terpanggang ke dalam cache, yaitu database SUNGGUHAN, dan `RefreshDatabase` mengosongkannya tanpa satu pun peringatan. Mau menguji lagi: `php artisan optimize:clear` dulu (**bukan** `config:clear`, yang meninggalkan cache rute dan menghabiskan memori PHP di tengah rangkaian uji), lalu `php artisan optimize` lagi sesudah selesai.

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

Dua endpoint yang paling sering ditarik -- `overlay/state/{arena}` dan `live/gelanggang/{arena}/state` -- sudah tidak memakai sesi sama sekali (didaftarkan di luar grup `web` di `bootstrap/app.php`). Halaman overlay vMix tidak bisa login dan penonton live score tidak punya turnamen aktif untuk diingat, jadi tidak ada yang hilang, dan tarikan berulangnya jadi jauh lebih murah. Pembatasnya tetap terpasang: `AllowLocalNetworkOnly` untuk overlay, `throttle:live` untuk live score.

**Selama konfigurasi di-cache, jangan menjalankan `php artisan test`.** Konfigurasi yang beku berarti Laravel tidak pernah lagi membaca variabel lingkungan mana pun, termasuk `DB_DATABASE=digiscoring_test` di `phpunit.xml` -- seluruh uji menunjuk ke database sungguhan, dan `RefreshDatabase` menghapus isinya. `tests/TestCase.php` menolak berjalan dalam keadaan itu, jadi yang muncul galat, bukan data yang hilang. Urutannya: `php artisan optimize:clear`, jalankan uji, lalu `php artisan optimize` lagi. **`optimize:clear`, bukan `config:clear`** -- yang kedua meninggalkan cache rute, dan `bootstrap/cache/routes-v7.php` yang tertinggal menghabiskan batas memori PHP di tengah rangkaian uji.

**Suite penuh butuh batas memori lebih besar dari bawaan.** Dengan `memory_limit` 128M ia mati di tengah jalan:

```
Allowed memory size of 134217728 bytes exhausted
in vendor/laravel/framework/.../BladeCompiler.php on line 803
```

Yang menyesatkan: Pest tetap keluar dengan **exit code 0** dan tidak menulis ringkasan sama sekali. Laporan "selesai" tanpa baris `Tests: N passed` berarti GAGAL, bukan lulus.

`php -d memory_limit=1G artisan test` **tidak menolong**: `artisan test` men-spawn proses pest sebagai anak dengan `php` polos, sehingga `-d` hilang di tengah jalan. Jalankan binari pest langsung:

```bash
php -d memory_limit=1G vendor/pestphp/pest/bin/pest
```

Gejalanya sama persis dengan cache rute yang tertinggal (paragraf di atas), jadi periksa `bootstrap/cache/` dulu: kalau di sana hanya ada `packages.php` dan `services.php`, sebabnya batas memori.

**Satu invokasi pada satu waktu.** Dua `php artisan test` yang berjalan bersamaan -- termasuk dari dua jendela terminal atau dua sesi asisten di mesin yang sama -- berebut database `digiscoring_test` yang sama. Keduanya memakai `RefreshDatabase`, jadi yang satu menghapus tabel di tengah uji milik yang lain, dan yang muncul adalah `SQLSTATE[42S02] Base table not found` yang terbaca persis seperti regresi nyata. Periksa dulu sebelum menyalahkan kode:

```powershell
Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -match 'pest' }
```

Kalau memang harus menguji bersamaan -- dua orang, atau dua sesi asisten di mesin yang sama -- beri masing-masing database uji sendiri, jangan menunggu giliran:

```bash
DB_DATABASE=digiscoring_ce_test php -d memory_limit=1G artisan test
```

Buat databasenya sekali dengan `CREATE DATABASE digiscoring_ce_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`. **Namanya wajib berakhiran `_test`** -- `tests/TestCase.php` menolak berjalan di database yang tidak berakhiran itu, dan penjagaan itulah yang mencegah `migrate:fresh` menghapus kejuaraan sungguhan. Jangan dilonggarkan; cukup pilih nama yang lolos.

**Sesudah menyunting `.env` atau kode apa pun, jalankan ulang `php artisan optimize`** (atau `php artisan optimize:clear` untuk kembali ke mode pengembangan). Konfigurasi yang sudah di-cache tidak lagi membaca `.env`, jadi suntingan yang tidak diikuti perintah ini tidak berlaku sama sekali -- termasuk `REVERB_HOST` yang baru.

## 6. Jalankan server (produksi/hari-H, bukan `composer run dev`)

Dua proses harus hidup bersamaan.

```powershell
.\scripts\server\jalankan-server.ps1
```

```powershell
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Skrip pertama menyalakan Nginx beserta kolam delapan proses `php-cgi` di belakangnya, lalu mencetak alamat yang harus dibuka HP juri. Yang kedua dijalankan di jendela PowerShell sendiri supaya lognya terlihat sepanjang acara. `--host=0.0.0.0` wajib untuk Reverb -- tanpa itu, ia hanya menerima koneksi dari mesin itu sendiri dan HP di LAN tidak akan bisa menyambung sama sekali.

Mematikan semuanya:

```powershell
.\scripts\server\hentikan-server.ps1
```

**`php artisan queue:listen` tidak dibutuhkan.** Tidak ada satu pun pekerjaan antrean di aplikasi ini: seluruh event gelanggang memakai `ShouldBroadcastNow`, artinya siarannya didorong di dalam permintaan itu juga, bukan lewat pekerja latar. Menjalankannya tidak salah, hanya tidak ada yang dikerjakannya.

### Kenapa bukan `php artisan serve`

Server bawaan PHP tidak punya banyak pekerja di Windows: permintaan kedua menunggu yang pertama selesai.

Akibatnya baru terasa saat gelanggang ramai: tiga juri menekan beruntun, empat panel dan lima halaman overlay masing-masing menarik keadaan terbaru, dan antreannya tumbuh lebih cepat daripada terurai. Layar tertinggal beberapa detik dari matras, dan aksi seperti membatalkan nilai ikut terasa lambat karena permintaannya mengantre di belakang tarikan-tarikan itu.

Diukur lewat `scripts\ukur-beban.ps1` di satu mesin (6 inti fisik), endpoint `overlay/state` yang sama, 8 putaran per tingkat, p50:

| | 1 permintaan | 5 bersamaan | 10 bersamaan |
|---|---|---|---|
| `php artisan serve` | 69 ms | 180 ms | 337 ms |
| Nginx + 8 php-cgi | 71 ms | 91 ms | 108 ms |

Satu permintaan sendirian sama cepatnya di keduanya -- itu sebabnya masalahnya tidak pernah terlihat saat mencoba sendirian. Yang berbeda apa yang terjadi begitu ada yang lain menunggu: di `php artisan serve` waktunya naik hampir sebanding jumlah yang mengantre, di Nginx hampir tidak naik.

**Jumlah pekerja itulah batasnya, dan ia terlihat di angka.** Diukur lewat LAN ke endpoint `panel/state` -- muatan terberat di sistem, dipakai tiap panel gelanggang -- pada Nginx dengan 8 pekerja:

| Bersamaan | p50 lewat LAN | p50 lewat loopback |
|---|---|---|
| 1 | 242 ms | 180 ms |
| 4 | 237 ms | 230 ms |
| 8 | 276 ms | 254 ms |
| 16 | 496 ms | 484 ms |

Datar sampai jumlah pekerjanya, lalu naik dua kali lipat di 16 -- yang ke-9 sampai ke-16 memang menunggu putaran kedua. Kalau gelanggang punya lebih dari delapan layar yang menarik keadaan berbarengan, naikkan `-Pekerja`.

Selisih LAN dan loopback (~60 ms) hanya terasa pada permintaan yang sendirian; begitu ada beban, ia tertelan waktu PHP-nya sendiri. Artinya jaringan venue bukan tempat pertama yang perlu dicurigai kalau panel terasa lambat.

Mengukurnya sendiri, sebelum dan sesudah:

```powershell
.\scripts\ukur-beban.ps1
.\scripts\ukur-beban.ps1 -Bersamaan 1,5,10,20 -Ulang 10
```

Yang dibaca: p50 pada 5 dan 10 bersamaan dibandingkan p50 pada 1 permintaan.

**Pastikan tidak ada `php artisan serve` lama yang masih hidup di port yang sama.** Windows mengizinkan dua proses mengikat port yang sama, dan yang lebih dulu mengikat itulah yang melayani -- Nginx terlihat hidup, halamannya terbuka, tapi seluruh permintaan dilayani server lama yang berbaris. Memeriksanya:

```powershell
Get-NetTCPConnection -LocalPort 8000 -State Listen |
    ForEach-Object { (Get-Process -Id $_.OwningProcess).ProcessName }
```

Kalau muncul `php` di situ, matikan dulu prosesnya sebelum menilai apa pun tentang kecepatannya.

### Yang dilakukan skrip itu, kalau mau dijalankan sendiri

Tiga hal yang mudah terlewat kalau Nginx disetel manual:

1. **Windows tidak punya PHP-FPM.** Yang ada `php-cgi.exe`, dan ia tidak bisa fork: satu proses melayani satu permintaan pada satu waktu. Jumlah proses yang dinyalakan itulah batas permintaan yang benar-benar dikerjakan berbarengan. Nginx tidak bisa menyalakannya sendiri -- skripnya yang melakukannya, lalu Nginx membagi permintaan lewat blok `upstream`.

2. **`PHP_FCGI_MAX_REQUESTS` wajib `0`.** Bawaannya 500: tanpa ini tiap proses `php-cgi` berhenti sendiri setelah 500 permintaan, dan Nginx membalas 502 sampai prosesnya dijalankan ulang -- yang tidak akan terjadi, karena tidak ada manajer proses yang melakukannya di Windows. Di gelanggang, 500 permintaan habis dalam hitungan menit.

3. **Nginx tidak membaca variabel lingkungan di konfigurasinya.** `scripts\server\nginx-digiscoring.conf` adalah templat; skripnya menyalinnya ke `.nginx-jalan.conf` sambil mengisi jalur proyek dan daftar port. Sunting templatnya, bukan salinannya.

Jumlah pekerja dan portnya bisa diubah:

```powershell
.\scripts\server\jalankan-server.ps1 -Pekerja 12 -Port 8000
```

Nginx juga melayani `public\build\*` langsung tanpa menyentuh PHP sama sekali. Di `php artisan serve`, tiap berkas JS dan CSS ikut mengantre di belakang permintaan juri.

`php artisan serve` tetap memadai untuk memasang, menguji, dan latihan satu-dua orang -- **asalkan diberi `--host=0.0.0.0`**:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

Tanpa itu ia hanya mengikat `127.0.0.1`, dan yang terjadi menyesatkan: halamannya terbuka sempurna di mesin server, sementara HP di jaringan yang sama tidak mendapat apa-apa selain waktu tunggu yang habis. Tidak ada pesan galat yang menyebut penyebabnya, dan gejalanya sama persis dengan firewall yang memblokir atau kabel yang lepas. Aturan `--host=0.0.0.0` yang sudah wajib untuk Reverb (lihat perintah di atas) berlaku sama untuk server HTTP-nya.

Memastikannya benar-benar terbuka ke LAN, dari mesin server sendiri:

```powershell
netstat -ano | Select-String ":8000" | Select-String "LISTENING"
```

Yang muncul harus `0.0.0.0:8000`. Kalau tertulis `127.0.0.1:8000`, tidak satu pun HP juri akan bisa menyambung.

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
- [ ] **Kalau halamannya tidak terbuka sama sekali** dari HP: `jalankan-server.ps1` sudah mencetak alamat LAN yang benar saat dinyalakan -- cocokkan dulu dengan yang diketik di HP. Kalau alamatnya sudah benar, sebabnya firewall (bagian 7), atau HP-nya tersambung ke jaringan lain. Nginx mengikat seluruh antarmuka, jadi salah-bind bukan penyebabnya; itu hanya terjadi kalau yang dijalankan `php artisan serve` tanpa `--host=0.0.0.0` (bagian 6)
- [ ] Login sebagai juri, buka panel juri, pastikan indikator koneksi hijau ("Tersambung")
- [ ] **Kalau halamannya terbuka tapi indikatornya "Terputus"**: HTTP-nya sampai, WebSocket-nya tidak. Yang keliru ada di Reverb, bukan di aplikasinya -- `reverb:start` belum dijalankan, dijalankan tanpa `--host=0.0.0.0`, atau port 8080 belum diizinkan firewall. `REVERB_HOST` yang berisi alamat lain **bukan** penyebabnya: Reverb melayani permintaan dari alamat mana pun, dan peramban memakai alamat yang sedang dibukanya sendiri
- [ ] Kirim satu nilai percobaan dari 2 HP berbeda dalam window konsensus (bawaan 2 detik; kejuaraan simulasi memakai 5 detik), pastikan nilai terbit di panel operator
- [ ] **Tekanan beruntun.** Dua juri menekan teknik yang sama bergantian cepat, lalu berhenti. Yang harus terlihat di panel operator dan overlay: tiap titik juri padam kira-kira dua detik sejak tekanannya SENDIRI, tidak diperpanjang oleh tekanan juri lain; dan titik yang tekniknya baru saja terbit jadi nilai padam seketika, sementara teknik lain yang jendelanya masih terbuka tetap menyala
- [ ] Satu juri menekan teknik yang sama dua kali beruntun -- titiknya harus terlihat berkedip ulang, bukan diam
- [ ] Cabut WiFi satu HP juri di tengah percobaan, sambungkan lagi, pastikan panel resync sendiri tanpa reload manual
- [ ] Matikan dan nyalakan ulang `reverb:start`, pastikan seluruh panel pulih ke state benar. Selama Reverb mati, tombol nilai harus tetap membalas dalam hitungan detik (bukan menggantung) dan panel penekannya tetap memperbarui diri

### Kalau memakai lebih dari satu gelanggang

- [ ] `php artisan silat:kesehatan` di tiap laptop -- ketiga metriknya hijau
- [ ] Buka **Sinkron Gelanggang**, pastikan tiap peer terdaftar dan tidak ada peringatan token
- [ ] Tarik dari node global di tiap laptop gelanggang, pastikan bagan dan jadwalnya masuk
- [ ] Sahkan satu partai percobaan, lalu periksa `php artisan silat:arsip` di laptop itu: partainya harus tercatat **diterima**, bukan menunggu
- [ ] Coba tayangkan partai yang hulunya berjalan di gelanggang lain sebelum ditarik -- **harus ditolak**, dengan pesan yang menyebut nama gelanggangnya
- [ ] Tarik dari gelanggang itu, lalu tayangkan lagi -- kali ini harus lolos
- [ ] Matikan node global, sahkan satu partai lagi: pengesahannya **tetap berhasil**, dan partainya menumpuk di antrean arsip. Hidupkan lagi, tekan `silat:arsip --dorong`, antreannya harus habis

## Setelah ini

- **Latihan dengan orang sungguhan di gelanggang: [`SIMULASI-LAPANGAN.md`](SIMULASI-LAPANGAN.md)** — urutan menyalakan, alamat per peran, partai yang dipakai, dan daftar periksa hari uji
- Overlay vMix: lihat `docs/RENCANA.md` Fase 6 dan `resources/views/overlay/`
- Live score publik lewat tunnel: `docs/TUNNELING.md`
- Alur operasional hari-H: `docs/PANDUAN-OPERASIONAL.md`
