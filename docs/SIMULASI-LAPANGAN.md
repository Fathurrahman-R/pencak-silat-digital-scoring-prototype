# Simulasi Lapangan — Lembar Kerja Hari Uji

Untuk latihan dengan orang sungguhan di gelanggang sungguhan: satu laptop jadi server, HP juri dan wasit menyambung lewat WiFi, vMix dan penonton menonton keluarannya. Mesin ini **sudah disetel ke mode hari-H** — yang tersisa satu langkah yang butuh hak administrator, dan sisanya urutan menyalakan.

Cara mengembalikannya ke mode pengembangan ada di bagian terakhir.

---

## 1. Satu-satunya langkah yang belum bisa dijalankan otomatis

Buka **PowerShell sebagai Administrator**, sekali saja seumur mesin:

```powershell
New-NetFirewallRule -DisplayName "Digiscoring HTTP"   -Direction Inbound -LocalPort 8000 -Protocol TCP -Action Allow
New-NetFirewallRule -DisplayName "Digiscoring Reverb" -Direction Inbound -LocalPort 8080 -Protocol TCP -Action Allow
```

Tanpa ini, **tidak satu pun HP bisa membuka aplikasinya** — dari mesin server sendiri semuanya terlihat normal, jadi kegagalannya baru ketahuan saat orang sudah berkumpul. Memeriksanya:

```powershell
Get-NetFirewallRule -DisplayName "Digiscoring*" | Select-Object DisplayName, Enabled
```

Dua baris, keduanya `True`.

---

## 2. Di venue: urutan menyalakan

**a. Sambungkan laptop ke WiFi venue lebih dulu**, baru jalankan servernya. Alamat yang dicetak skrip diambil dari adapter yang sedang punya gerbang bawaan.

**b. Satu perintah, satu jendela PowerShell:**

```powershell
cd D:\digiscoring-prototype
.\scripts\server\jalankan-server.ps1 -DenganReverb
```

Yang dicetaknya, catat baris terakhir:

```
php-cgi: 8 proses di port 9001-9008
nginx:   port 8000
Dari HP di LAN : http://192.168.1.18:8000      <-- INI yang dibagikan
reverb:  jendela terpisah, port 8080
```

**c. Uji dari satu HP dulu, sebelum orang lain dipanggil.** Buka alamat itu, login sebagai juri, pastikan penanda koneksi hijau bertuliskan **Tersambung**. Kalau itu jalan, semuanya jalan.

Mematikan setelah selesai:

```powershell
.\scripts\server\hentikan-server.ps1
```

Jendela Reverb ditutup sendiri (Ctrl+C di jendelanya).

---

## 3. Alamat yang dibagikan

Ganti `<ip-server>` dengan alamat yang dicetak skrip.

| Siapa | Alamat | Catatan |
|---|---|---|
| Juri, wasit, dewan juri, ketua | `http://<ip-server>:8000` | Login, lalu **beranda langsung menampilkan kartu partai tugasnya** — tinggal ditekan |
| Pengendali gelanggang | `http://<ip-server>:8000` | Login, lalu menu **Pertandingan → Gelanggang → Panel Kendali**. Dari sana ia menayangkan partai, menjalankan timer, memindahkan babak, dan mengakhiri partai |
| vMix (Web Browser Input) | `.../overlay/scorebug/1`<br>`.../overlay/breakdown/1`<br>`.../overlay/athlete/1/red`<br>`.../overlay/athlete/1/blue`<br>`.../overlay/result/1` | Angka terakhir = nomor gelanggang (1 = A, 2 = B). Tanpa login; dibatasi jaringan lokal |
| Penonton | `.../live/turnamen/1`<br>`.../live/gelanggang/1` | Tanpa login |

**Alamat WebSocket tidak lagi perlu disetel.** Peramban menyambung ke alamat yang sedang dibukanya sendiri, jadi IP laptop yang berubah tidak lagi memutus HP juri dan tidak menuntut `npm run build` ulang. Yang masih perlu diketik ulang cuma alamat di HP-nya, jadi **reservasi DHCP di router venue tetap dianjurkan** supaya tidak ada yang perlu mengetik ulang di tengah acara.

Ini sudah terbukti sendiri, bukan dugaan: di tengah penyiapan, DHCP memindahkan alamat mesin dari `192.168.1.100` ke `192.168.1.18`. Tanpa satu pun perintah dijalankan, panel yang dibuka di alamat baru itu menyambung ke `192.168.1.18:8080` dan langsung tersambung. Dengan cara lama, seluruh HP juri kehilangan WebSocket di titik itu.

---

## 4. Akun

Kata sandi seluruhnya `password`.

| Akun | Peran | Gelanggang |
|---|---|---|
| `operator@silat.test` | Operator IT — timer, panel gelanggang | A |
| `operator2@silat.test` | Operator IT | B |
| `wasit1@silat.test` | Wasit | A |
| `wasit2@silat.test` | Wasit | B |
| `juri1@` `juri2@` `juri3@silat.test` | Juri 1–3 | A |
| `juri4@` `juri5@` `juri6@silat.test` | Juri 4–6 | B |
| `ketua@silat.test` | Ketua Pertandingan — pengesahan, VAR, protes | — |
| `pengawas@silat.test` | Dewan Wasit Juri | — |
| `komisi@silat.test` | Wasit Komisi Protes | — |

---

## 5. Partai yang dipakai

**Jangan mengandalkan nomor partai yang tertulis di dokumen.** Nomornya berubah setiap kali kejuaraan simulasi disemai ulang, dan lembar ini pernah menunjuk partai yang sudah tidak ada sama sekali — petugas yang mengikutinya pagi hari-H mencari partai yang tidak pernah ada.

Yang benar: buka **Panel Kendali** gelanggang masing-masing. Antreannya mencantumkan seluruh partai gelanggang itu lengkap dengan nomor, kelas, nama kedua pesilat, dan statusnya. Ambil partai pertama yang berstatus **terjadwal**, tekan **Tayangkan**, lalu **Mulai babak 1**.

Beberapa hal yang berlaku apa pun nomornya:

- Partai yang berstatus **berlangsung** tidak bisa ditinggalkan begitu saja. Panel menolak dengan "Partai yang sedang berjalan belum diakhiri." Akhiri dulu lewat **Akhiri partai**, atau pindah paksa kalau memang perlu.
- Partai yang sudah dipakai menguji berisi nilai dan hukuman lama. Kalau terlanjur dibuka, tekan **Reset babak** — tersedia di Panel Kendali maupun Panel Papan.
- Jendela konsensus juri **2 detik**, sama dengan setelan pertandingan sungguhan.
- Aparat ditugaskan per gelanggang lewat **Gelanggang → Aparat**. Petugas yang tercatat di dua gelanggang sekaligus sengaja berhenti di dashboard, bukan didaratkan di salah satunya — sistem tidak punya dasar memilih.

---

## 6. Yang diamati selama simulasi

Inti yang mau dibuktikan: gelanggang tetap terasa langsung saat atlet bergerak cepat dan juri menekan beruntun.

- [ ] **Indikator juri menyala segera** setelah tombol ditekan, di panel operator maupun overlay
- [ ] **Titik juri padam kira-kira 2 detik sejak tekanannya sendiri**, tidak diperpanjang oleh tekanan juri lain
- [ ] **Juri yang menekan dua kali beruntun** melihat titiknya berkedip ulang, bukan diam
- [ ] **Nilai terbit seketika** begitu juri kedua menekan teknik yang sama — angka berubah tanpa jeda terasa
- [ ] Papan tidak tertinggal dari matras saat serangan datang bertubi
- [ ] Membatalkan nilai lewat Dewan Wasit Juri tetap terasa ringan di tengah keramaian
- [ ] Cabut WiFi satu HP juri, sambungkan lagi — panelnya pulih sendiri tanpa reload manual
- [ ] Matikan lalu nyalakan lagi Reverb — seluruh panel kembali ke keadaan benar

Diukur di mesin ini sebelum berangkat, endpoint state yang sama, p50:

| Panel menarik bersamaan | 1 | 5 | 10 | 20 |
|---|---|---|---|---|
| `php artisan serve` (lama) | 69 ms | 180 ms | 337 ms | — |
| Nginx + 8 php-cgi, mode hari-H | ~120 ms* | 58 ms | 60 ms | 70 ms |

\* Angka pertama termasuk pemanasan OPcache delapan pekerja; sesudah hangat ia turun ke kisaran yang sama.

---

## 7. Kalau ada yang pecah

| Gejala | Penyebab yang paling sering | Tindakan |
|---|---|---|
| HP tidak bisa membuka halamannya sama sekali | Aturan firewall belum dibuat (§1), atau HP-nya di WiFi yang berbeda | Jalankan §1 sebagai Administrator; pastikan HP dan laptop satu WiFi |
| Halaman terbuka, panel bertuliskan **Terputus** | Reverb mati, atau port 8080 diblok | Lihat jendela Reverb; kalau tertutup, jalankan `php artisan reverb:start --host=0.0.0.0 --port=8080` |
| Semuanya terasa lambat padahal Nginx jalan | Ada `php artisan serve` lama yang masih memegang port 8000 — Windows mengizinkan keduanya mengikat port yang sama, dan yang lama yang melayani | `Get-NetTCPConnection -LocalPort 8000 -State Listen \| ForEach-Object { (Get-Process -Id $_.OwningProcess).ProcessName }` — kalau muncul `php`, matikan prosesnya |
| Halaman membalas 502 di tengah acara | Proses `php-cgi` mati | `.\scripts\server\hentikan-server.ps1` lalu jalankan lagi |
| Menyunting `.env` tapi tidak ada yang berubah | Konfigurasi sedang di-cache | `php artisan optimize` setiap kali sesudah menyunting |
| `php artisan test` menolak jalan | Memang begitu selama konfigurasi di-cache — lihat kotak di bawah | `php artisan optimize:clear`, jalankan uji, lalu `php artisan optimize` lagi |
| Overlay vMix membalas 403 | Mesin vMix di luar jaringan lokal | Overlay sengaja dibatasi RFC 1918; taruh vMix di jaringan yang sama |

Log ada di `C:\nginx-1.31.0\logs\digiscoring-error.log`, `scripts\server\.log\`, dan `storage\logs\laravel.log`.

### Jangan menjalankan uji selagi konfigurasi di-cache

`php artisan optimize` membekukan konfigurasi ke `bootstrap/cache/config.php`, dan sejak itu Laravel **tidak pernah lagi membaca variabel lingkungan mana pun** — termasuk `DB_DATABASE=digiscoring_test` yang ditetapkan `phpunit.xml`. Seluruh uji lalu menunjuk ke database sungguhan, dan `RefreshDatabase` menjalankan `migrate:fresh` di sana: kejuaraan yang sedang dipakai simulasi hilang, tanpa satu pun isyarat bahwa itu terjadi.

`tests/TestCase.php` sekarang menolak berjalan kalau database yang tersambung bukan yang berakhiran `_test`, jadi kecelakaan itu berhenti sebagai galat. Urutan yang benar kalau memang mau menjalankan uji:

```powershell
php artisan optimize:clear
php artisan test
php artisan optimize      # jangan lupa -- tanpa ini servernya kembali lambat
```

**`optimize:clear`, bukan `config:clear`.** Yang kedua cuma membuang cache konfigurasi dan meninggalkan cache rute; `bootstrap/cache/routes-v7.php` yang tertinggal itu ikut dimuat tiap uji dan menghabiskan batas memori PHP di tengah rangkaian, dengan galat yang menunjuk ke berkas cache dan sama sekali tidak menyebut penyebabnya:

```
Allowed memory size of 134217728 bytes exhausted in bootstrap\cache\routes-v7.php
```

---

## 8. Setelan yang sedang berlaku

Mesin ini sudah berada di mode hari-H:

```env
APP_ENV=production
APP_DEBUG=false          # galat muncul sebagai halaman aplikasi, bukan jejak berisi .env
SESSION_DRIVER=file      # bukan database -- dua perjalanan MySQL per permintaan hilang
CACHE_STORE=file
DESIGN_SYSTEM_ENABLED=false
VITE_REVERB_HOST=        # sengaja kosong: peramban memakai alamat yang dibukanya sendiri
APP_URL=http://192.168.1.18:8000
OVERLAY_ENABLED=true     # tanpa ini seluruh overlay vMix membalas halaman "dimatikan"
LIVE_SCORE_ENABLED=true  # tanpa ini live score gelanggang untuk penonton mati
```

**Dua baris terakhir bawaannya MATI, dan diamnya total.** Overlay vMix hanya menampilkan halaman "Overlay siaran dimatikan" dan `overlay/state` membalas 503 — tidak ada satu pun isyarat di sisi server bahwa ada yang salah. Sekali lagi: baru ketahuan saat vMix sudah dipasang dan orang sudah berkumpul. Periksa keduanya dengan membuka satu alamat overlay dari peramban sebelum berangkat.

`APP_URL` hanya dipakai untuk tautan yang dibuat **di luar** permintaan (mis. surel reset sandi). Halaman yang dibuka peramban memakai alamat permintaannya sendiri, jadi IP yang tertinggal di sini tidak memutus siapa pun.

Aset sudah dibangun (`npm run build`) dan konfigurasi sudah di-cache (`php artisan optimize`).

### Kembali ke mode pengembangan sesudah simulasi

```powershell
cd D:\digiscoring-prototype
php artisan optimize:clear
```

lalu kembalikan di `.env`:

```env
APP_ENV=local
APP_DEBUG=true
DESIGN_SYSTEM_ENABLED=true
```

`SESSION_DRIVER=file` dan `CACHE_STORE=file` boleh dibiarkan — keduanya tidak mengganggu pengembangan. `VITE_REVERB_HOST` jangan diisi lagi kecuali Reverb sengaja dipindah ke mesin lain; mengisinya mengembalikan ketergantungan pada IP yang tertanam di aset.

---

Instalasi dari nol dan alasan tiap setelan: [`INSTALASI-LAN.md`](INSTALASI-LAN.md). Alur operasional per peran: [`PANDUAN-OPERASIONAL.md`](PANDUAN-OPERASIONAL.md).
