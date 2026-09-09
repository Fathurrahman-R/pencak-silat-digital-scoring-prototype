# Tunneling aman untuk live score publik

> Fase 5 (`docs/RENCANA.md`, T5.5). Konfigurasi teknis untuk mengekspos
> `/live/*` ke internet tanpa membuka apa pun yang lain. Panduan operasional
> lengkap untuk panitia (langkah hari-H, siapa menjalankan apa) menyusul di
> Fase 8 (T8.7) -- dokumen ini adalah rujukan teknisnya.

## Kenapa tidak tunnel langsung ke Laravel

Kalau seluruh aplikasi diekspos apa adanya lewat ngrok/cloudflared, panel
admin, panel juri, dan `/overlay/*` ikut terbuka ke internet -- siapa pun
yang menebak atau menemukan URL-nya bisa membuka panel juri dan memanipulasi
skor pertandingan yang sedang berjalan. Middleware `auth` dan
`AllowLocalNetworkOnly` melindungi rute-rute itu selama diakses dari LAN,
tapi keduanya tidak berarti apa-apa begitu ada reverse proxy yang meneruskan
SEMUA path ke Laravel tanpa pandang bulu.

Mitigasinya: tunnel tidak pernah menunjuk langsung ke `php artisan serve`
atau ke port aplikasi Laravel. Ia menunjuk ke reverse proxy (Caddy dipakai
di sini karena konfigurasi TLS-nya otomatis) yang **hanya** meneruskan tiga
hal:

1. `/live/*` -- halaman dan endpoint state live score publik (Fase 5)
2. `/build/*` -- aset Vite terkompilasi (CSS/JS/font) yang dipakai halaman
   `/live/*` supaya tampilannya tidak polos
3. `/app/*` -- endpoint WebSocket Reverb (protokol Pusher), supaya browser
   penonton bisa menyambung ke channel `public-live.{arena}` untuk
   pembaruan realtime

Path lain -- termasuk `/admin`, `/juri`, `/wasit`, `/operator`, `/overlay`,
dan `/broadcasting/auth` -- dibalas 404 oleh proxy itu sendiri, sebelum
permintaannya sempat menyentuh Laravel sama sekali.

**Setel `LIVE_SCORE_ENABLED=true` sebelum membuka tunnel.** Bawaannya mati,
dan selama mati `/live/gelanggang/{arena}` hanya merender halaman "live score
sedang tidak ditayangkan" sementara channel `public-live.{arena}` tidak
disiarkan sama sekali -- tunnelnya menyala, isinya tidak ada. Halaman
turnamen, medali, dan bagan tetap tampil normal apa pun saklarnya. Lihat
bagian 4c di `docs/INSTALASI-LAN.md`.

## Contoh Caddyfile

```
live.contoh-domain.test {
    # Aset Vite -- CSS, JS, dan font yang dipakai halaman /live/*
    handle /build/* {
        reverse_proxy localhost:8000
    }

    # Endpoint WebSocket Reverb (protokol Pusher). Channel publiknya
    # (public-live.{arena}) tidak butuh /broadcasting/auth sama sekali --
    # itu hanya dipakai channel private/presence yang memang tidak pernah
    # boleh keluar dari LAN, jadi path itu SENGAJA tidak diteruskan di sini.
    handle /app/* {
        reverse_proxy localhost:8080
    }

    # Live score publik -- satu-satunya kelompok halaman yang memang
    # dirancang untuk internet publik.
    handle /live/* {
        reverse_proxy localhost:8000
    }

    # Apa pun di luar tiga pola di atas dibalas 404 di sini, sebelum
    # sempat menyentuh Laravel.
    handle {
        respond 404
    }
}
```

Ganti `localhost:8000` dengan port `php artisan serve`/PHP-FPM yang
sesungguhnya, dan `localhost:8080` dengan `REVERB_PORT` dari `.env`.

### Kalau halaman terbuka tapi polos, tanpa CSS dan tanpa angka yang bergerak

Proxy meneruskan permintaan `https` ke Laravel sebagai `http`. Laravel hanya
tahu itu dari `X-Forwarded-Proto`, dan hanya mempercayainya kalau proxy-nya
terdaftar — `bootstrap/app.php` mempercayai **loopback saja**, karena Caddy di
atas memang berdiri di mesin yang sama. Tidak percaya, seluruh alamat aset
terbit berskema `http`, dan peramban memblokirnya dari halaman `https` sebagai
konten campuran: tidak ada CSS, tidak ada Alpine, tidak ada Echo.

Dua hal yang harus benar bersamaan, dan keduanya gejalanya sama persis —
"siarannya mati", tanpa satu pun pesan di Safari iOS:

1. Caddy meneruskan `X-Forwarded-Proto` (bawaannya sudah begitu) **dan**
   berjalan di mesin yang sama dengan Laravel. Kalau ia dipindah ke mesin lain,
   daftarkan alamat mesin itu di `trustProxies` pada `bootstrap/app.php`.
2. `VITE_REVERB_SCHEME` di `.env` **kosong**, lalu `npm run build` ulang —
   dijaga `npm run periksa-siaran`.

Periksa cepat dari mesin proxy: `curl -sk https://<domain>/live/turnamen/1 |
grep -o 'href="[^"]*build[^"]*"' | head -1`. Alamat yang keluar harus berskema
`https`. Kalau `http`, yang salah nomor 1.

**Sesudah menyunting `bootstrap/app.php`, jalankan ulang kolam PHP-nya**
(`.\scripts\server\hentikan-server.ps1` lalu `jalankan-server.ps1`). Perubahan
di berkas itu tidak berlaku pada proses yang sedang berjalan.

## Menyambungkan tunnel

Tunnel (cloudflared, ngrok, atau sejenisnya) diarahkan ke Caddy di atas,
**bukan** ke Laravel:

```bash
cloudflared tunnel --url http://localhost:2019   # port Caddy, bukan port Laravel
```

Putusnya tunnel tidak boleh berdampak apa pun ke gelanggang (NFR-02) --
`/live/*` memang tidak tersambung sementara, tapi panel operator, wasit,
juri, dan dewan juri semuanya berjalan di LAN lokal dan tidak pernah
memanggil URL tunnel sama sekali.

## Uji yang wajib dijalankan sebelum hari-H

Dari jaringan seluler di luar LAN venue (bukan WiFi yang sama):

- [ ] `/live/{arena}` tampil dan skornya diperbarui saat partai berjalan
- [ ] `/live/turnamen/{id}` tampil
- [ ] `/admin`, `/login` dibalas 404 oleh proxy
- [ ] `/juri`, `/wasit`, `/operator`, `/dewan-juri`, `/keberatan` (di bawah
      `/admin/turnamen/*/partai/*`) dibalas 404 oleh proxy
- [ ] `/overlay/*` dibalas 404 oleh proxy
- [ ] `/broadcasting/auth` dibalas 404 oleh proxy
- [ ] Matikan tunnel di tengah pertandingan -- panel operator/wasit/juri di
      LAN tetap berjalan tanpa terganggu

Butuh perangkat di luar jaringan venue dan domain/subdomain sungguhan untuk
TLS Caddy, jadi tidak bisa dijalankan di lingkungan pengembangan ini --
dicatat sebagai langkah verifikasi hari-H, sejalan dengan T6.8 (uji vMix
Pro) yang juga menunggu perangkat sungguhan.
