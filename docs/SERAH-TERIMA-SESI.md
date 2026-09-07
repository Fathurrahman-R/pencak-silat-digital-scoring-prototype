# Serah terima sesi — 7 September 2026, 06:25

Berkas ini ditulis untuk agen berikutnya yang melanjutkan di komputer yang sama
dengan akun berbeda. Isinya: apa yang sudah selesai, apa yang menggantung, dan
hal-hal yang akan membuang waktu kalau ditemukan sendiri dari nol.

Cabang: `qa/perbaikan-uji-lapangan`.

> **Diperbarui 7 September 2026, 07:45 oleh sesi berikutnya.** Kalimat di sini
> semula berbunyi "belum ada satu commit pun". Itu sudah tidak berlaku: cabang
> ini punya empat commit (`88f2190`, `f8e1a75`, `3d31b8c`, `23af76b`) dan tiga
> di antaranya sudah di-push ke `origin/qa/perbaikan-uji-lapangan`. Keempatnya
> menyentuh dokumentasi, `.env.example`, integrasi graphify, dan seeder
> simulasi — bukan pekerjaan QA yang dijelaskan di bawah, yang memang masih di
> working tree.

## Keadaan mesin

- Aplikasi dilayani nginx + php-cgi di port 8000, Reverb di 8080. Keduanya
  hidup; jangan dinyalakan ulang tanpa perlu.
- **Alamat lapangan berpindah-pindah.** Hari ini sudah dua kali: 10.10.11.24 →
  192.168.1.18. Ambil dari `ipconfig` (adapter Wi-Fi), JANGAN dari `APP_URL` di
  `.env` — nilai di sana tertinggal dan tidak dipakai peramban.
- Basis data lapangan `digiscoring`. Uji memakai `digiscoring_test` (diatur
  `phpunit.xml`); sudah diverifikasi tidak saling menyentuh.
- Pengguna me-reset `digiscoring` pukul 06:00 hari ini. Tiga dataset sekarang:
  kejuaraan **1** kecil, **4** sedang, **5** besar; gelanggang 1–2, 8–9, 10–12.

## Cara menguji di peramban

Playwright MCP mati (CONNECT_TIMEOUT) dan ekstensi Chrome menolak (token OAuth
beda akun). Jalan yang dipakai sepanjang sesi ini: `playwright-core` + Chrome
sistem, dengan pustaka kecil di
`C:\Users\LENOVO~1\AppData\Local\Temp\claude\D--digiscoring-prototype\8fa2fd04-03cb-4d3d-8483-8c9fd33ad69e\scratchpad\lib.mjs`
(`launch()`, `sesi(browser, email)`, `ambil(page)`, `overflow(page)`). Kalau
scratchpad itu sudah hilang, buat ulang: `npm i playwright-core`, lalu
`chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true })`,
login lewat `/login`, akun `<peran>@silat.test`, kata sandi `password`.

Akun yang sering dipakai: `pengendali1@silat.test` (panel kendali),
`operator@silat.test` (papan), `juri1@silat.test`, `wasit1@silat.test`,
`ketua@silat.test`, `pengawas@silat.test`, `komisi@silat.test`,
`sekretariat@silat.test`, `super@example.com` (bagan).

## Menjalankan uji

```bash
php -d memory_limit=1G vendor/bin/pest              # ±48 menit, 1000+ uji
php -d memory_limit=1G vendor/bin/pest tests/Feature/Bagan/ModeBaganTest.php
```

Tiga hal yang akan menggigit:

1. **Jangan menjalankan dua `pest` sekaligus** — keduanya memakai
   `digiscoring_test` dan saling merusak.
2. `memory_limit=1G` wajib; pada 128M suite mati di BladeCompiler.
3. Kalau uji menolak jalan dengan "konfigurasi sedang di-cache", jalankan
   `php artisan optimize:clear`. Penyebabnya `scripts/server/siapkan-gelanggang.ps1`,
   yang menutup dengan `php artisan optimize`.

## Yang sudah selesai sesi ini (semua sudah diverifikasi di peramban)

**Perbaikan bug (15).** Pendaratan panel yang beku; 4 Alpine error di gelanggang
kosong; jalan "pindah paksa" tanpa tombol; overflow 375px di panel dewan-juri
dan ketua-pertandingan; error `verifikasi.terjawab`; 405 pada alamat kejuaraan
telanjang; tombol nilai juri hidup saat babak mati; **siaran Jurus** (modul ini
sebelumnya tanpa siaran sama sekali); tombol "Tarik dari peer ini" yang tidak
pernah jalan; protes VAR/manajer yang diajukan tidak disiarkan; diskualifikasi
Jurus satu klik tanpa konfirmasi; judul bagan ter-escape ganda; seeder skala
tanpa Pengendali Gelanggang.

**Fitur.** `ModeBagan` gugur/pemasalan (kolom `brackets.mode`, pemilih di panel
bagan, generator berjenjang, perambatan peserta yang melenggang, pohon bagan
sadar mode). Seeder skala sedang/besar (`silat:simulasi --skala=sedang|besar
[--tanpa-bagan] [--reset]`) yang mengisi seluruh 174 kelas tanding dan 64 nomor
Jurus.

**Uji baru.** `tests/Feature/Bagan/ModeBaganTest.php` (19), 
`tests/Feature/Turnamen/SeederSkalaTest.php` (8),
`tests/Feature/Jurus/SiaranJurusTest.php` (8), tambahan di
`PanelPerGelanggangTest`, `PointerPartaiAktifTest`, `VarControllerTest`,
`JurusScoringControllerTest`, `BulkDestroyAndPanelTest`.

## Yang menggantung — kerjakan ini lebih dulu

1. **Jalankan suite penuh sampai hijau.** Suite terakhir dihentikan di tengah
   karena ada perubahan menyusul. Perubahan yang BELUM pernah ikut suite penuh:
   - papan hasil yang menimpa blok skor (blade saja),
   - pengalihan panel papan per-partai ke alamat gelanggang
     (`PartaiScoringController::operator`) beserta ujinya di
     `PanelPerGelanggangTest`,
   - uji pengendali gelanggang di `SeederSkalaTest`,
   - uji konfirmasi diskualifikasi di `JurusScoringControllerTest`.
2. **Putuskan commit.** 37 berkas berubah + 9 berkas baru, belum ada commit.
   Pengguna belum memberi izin commit; tanyakan sebelum menulis sejarah.
3. Verifikasi rupa papan hasil di lebar sempit (panel Dewan Wasit Juri dan
   panel Ketua Pertandingan memakai komponen yang sama, kolomnya jauh lebih
   sempit daripada panel papan). Ukuran hurufnya `clamp()`, tapi belum dilihat
   dengan mata di kedua tempat itu.

## Temuan yang dilaporkan, sengaja BELUM diperbaiki

- **Penilaian Jurus tanpa penjagaan penugasan.** `JurusScoringController::nilai`
  hanya memeriksa izin peran, bukan apakah juri itu ditugaskan pada penampilan
  tersebut — berbeda dari Tanding, yang menjaganya lewat `MatchOfficial`.
  Siapa pun berperan `juri` bisa mengirim nilai ke penampilan mana pun di
  kejuaraan itu. Tidak diperbaiki karena `jurus_performances.arena_id` boleh
  kosong; memasang penjagaan yang salah beberapa jam sebelum uji lapangan
  berisiko memblokir penilaian yang sah. Perlu keputusan model penugasannya.
- **`.env` menyimpan alamat lama** (`APP_URL`, `REVERB_HOST`). Tidak memutus apa
  pun — `resources/js/echo.js` mengikuti alamat halaman — tapi menyesatkan.
- **`vendor/bin/pint --test app/` gagal di ~30 berkas** yang tidak disentuh sesi
  ini. Sudah begitu sebelum sesi dimulai; berkas yang disentuh sesi ini bersih.
- **Pemasalan pada jumlah peserta ganjil**: tempat terakhir bisa melenggang
  lebih dari sekali (9 peserta → melenggang 3 kali, bertanding sekali di final).
  Ini konsekuensi aturan yang dipilih pengguna secara sadar, sudah ditulis di
  README, `docs/PANDUAN-WORKFLOW.md`, dan komentar `ModeBagan`.

## Uji lapangan sore ini

Data siap pakai. Sebelum mulai, jalankan `php artisan silat:simulasi --reset`
kalau data kecil sudah kotor oleh percobaan, dan `php artisan optimize:clear`
kalau setup script baru saja dijalankan.
