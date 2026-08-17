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
CREATE DATABASE boilerplate      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE boilerplate_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 4. Konfigurasi `.env`

```powershell
copy .env.example .env
php artisan key:generate
```

Sunting `.env`:

```env
DB_DATABASE=boilerplate
DB_USERNAME=root
DB_PASSWORD=

# Reverb -- alamat yang dijangkau HP juri/wasit di LAN, BUKAN localhost
# kalau HP menyambung lewat WiFi venue. Isi dengan IP mesin server di
# jaringan itu, misalnya 192.168.1.10.
REVERB_HOST="192.168.1.10"
REVERB_PORT=8080
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME=http

# Overlay vMix -- default sudah mencakup RFC 1918, biasanya tidak perlu diisi
# OVERLAY_ALLOWED_CIDRS=127.0.0.1/32,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
```

**Kenapa `REVERB_HOST` bukan `localhost`.** HP juri menyambung ke server dari perangkat lain di jaringan yang sama. `localhost` di HP menunjuk ke HP itu sendiri, bukan ke server. Isi dengan alamat IP LAN mesin server (`ipconfig` di PowerShell untuk melihatnya), dan pastikan alamat itu **statis** (set IP statis di adapter jaringan Windows, atau reservasi DHCP di router) -- kalau berubah di tengah turnamen, seluruh HP juri kehilangan koneksi.

## 5. Migrasi, seed, build aset

```powershell
php artisan migrate --seed
php artisan storage:link
npm run build
```

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

## 7. Firewall Windows

Izinkan port masuk untuk PHP dan Reverb:

```powershell
New-NetFirewallRule -DisplayName "Digiscoring HTTP" -Direction Inbound -LocalPort 8000 -Protocol TCP -Action Allow
New-NetFirewallRule -DisplayName "Digiscoring Reverb" -Direction Inbound -LocalPort 8080 -Protocol TCP -Action Allow
```

## 8. Uji sebelum hari-H

- [ ] Buka `http://<IP-server>:8000` dari HP yang tersambung ke WiFi venue (bukan dari mesin server sendiri)
- [ ] Login sebagai juri, buka panel juri, pastikan indikator koneksi hijau ("Tersambung")
- [ ] Kirim satu nilai percobaan dari 2 HP berbeda dalam window 2 detik, pastikan nilai terbit di panel operator
- [ ] Cabut WiFi satu HP juri di tengah percobaan, sambungkan lagi, pastikan panel resync sendiri tanpa reload manual
- [ ] Matikan dan nyalakan ulang `reverb:start`, pastikan seluruh panel pulih ke state benar

## Setelah ini

- Overlay vMix: lihat `docs/RENCANA.md` Fase 6 dan `resources/views/overlay/`
- Live score publik lewat tunnel: `docs/TUNNELING.md`
- Alur operasional hari-H: `docs/PANDUAN-OPERASIONAL.md`
