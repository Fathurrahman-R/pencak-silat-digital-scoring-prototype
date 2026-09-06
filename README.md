# Digital Scoring Pencak Silat

Aplikasi web yang menjalankan penyelenggaraan turnamen pencak silat dari ujung ke ujung: pendaftaran kontingen, tarif dan pembayaran, timbang badan, bagan gugur tunggal, mesin scoring realtime kategori Tanding dan Jurus, VAR dan protes manajer, live score publik, overlay siaran vMix, sampai rekap medali dan berita acara.

Sumber kebenaran seluruh aturan pertandingan: **Peraturan Pertandingan Pencak Silat Nasional Tahun 2025**, SK Ketua Umum PB IPSI Nomor Skep-70/III/2025 (`document/`).

**Stack:** Laravel 13 · PHP 8.3+ · MySQL 8 · Blade + Alpine 3 · Tailwind CSS 4 · Laravel Reverb (WebSocket) · Pest 5

---

## Prinsip rancangan

- **Gelanggang tidak pernah butuh internet.** Seluruh jalur pertandingan — panel juri/wasit/operator/dewan juri, timer, mesin konsensus, overlay siaran — berjalan penuh di LAN lokal lewat Laravel Reverb. Internet hanya dipakai dua hal yang keduanya boleh mati tanpa mengganggu gelanggang: menerbitkan live score publik lewat tunnel, dan pembayaran pendaftaran pra-acara lewat Midtrans.
- **`judge_inputs` tidak pernah diubah atau dihapus.** Setiap tekanan tombol juri Tanding tersimpan mentah, selamanya. Koreksi dewan juri memakai baris pembatal (`voided_at`/`voided_by`/`void_reason`), bukan menyunting riwayat — pola yang sama dipakai ulang di VAR dan pengurangan nilai Jurus.
- **Waktu resmi selalu milik server.** Timer partai dan penampilan Jurus dihitung dari `started_at`/`accumulated_ms` di database; jam perangkat juri atau operator tidak pernah dipercaya.
- **Yang bisa dihitung tidak disimpan.** Skor, golongan usia dari kelas, posisi bagan berikutnya — semuanya dihitung on-the-fly dari data mentah, supaya tidak ada dua salinan angka yang bisa diam-diam bergeser satu sama lain.

Rincian tiap keputusan arsitektur ada di [`docs/RENCANA.md`](docs/RENCANA.md) (rencana + progres per fase) dan [`docs/ARSITEKTUR.md`](docs/ARSITEKTUR.md).

---

## Menjalankan pertama kali

Prasyarat: **PHP 8.3+** (ekstensi `pdo_mysql`, `mbstring`, `intl`, `gd`, `fileinfo`), **Composer 2**, **Node.js 20+**, **MySQL 8**.

**1. Dependensi**

```bash
composer install
npm install
```

**2. Database.** Buat dua database — satu aplikasi, satu test:

```sql
CREATE DATABASE digiscoring      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE digiscoring_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Nama `digiscoring_test` dipakai langsung oleh `phpunit.xml`; kalau diganti, ganti juga di sana.

**3. Berkas `.env`**

```bash
cp .env.example .env        # PowerShell: copy .env.example .env
php artisan key:generate
```

Sesuaikan `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` kalau berbeda dari bawaan, lalu isi tiga kunci Reverb yang sengaja dibiarkan kosong — tanpa itu WebSocket tidak hidup dan seluruh panel gelanggang berhenti di "Terputus". Cetak nilainya, tempel ke `.env`:

```bash
php -r "printf('REVERB_APP_ID=%d%sREVERB_APP_KEY=%s%sREVERB_APP_SECRET=%s%s', random_int(100000,999999), PHP_EOL, bin2hex(random_bytes(10)), PHP_EOL, bin2hex(random_bytes(10)), PHP_EOL);"
```

Jangan memakai `php artisan reverb:install` untuk ini: perintah itu menambahkan blok baru di akhir `.env` tanpa membuang baris lama, dan karena nilai terakhirlah yang menang, `REVERB_HOST` hasil suntingan sendiri ikut tertimpa `localhost`.

**4. Migrasi, data awal, aset**

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

**5. Jalankan**

```bash
composer run dev      # server + queue + Vite + Reverb sekaligus
```

Jalan pintas: `composer run setup` mengerjakan langkah 1, 3 (salin `.env` + `key:generate`), dan 4 sekaligus. Dua hal tetap manual karena tidak bisa ditebak mesin: membuat database dan mengisi tiga kunci Reverb.

Buka `http://127.0.0.1:8000`. Akun bawaan seeder (kata sandi semuanya `password`):

| Email | Role | Bisa apa |
|---|---|---|
| `super@example.com` | super-admin | Semuanya, melewati seluruh pengecekan |
| `admin@example.com` | admin | Kelola pengguna |
| `user@example.com` | user | Akun tanpa hak kelola, untuk menguji batas akses |

Peran domain silat (Ketua Pertandingan, Wasit, Juri, Operator IT, dst. — lihat Pasal 13) didaftarkan `SilatRoleSeeder`, dibuatkan lewat panel **Manajemen Akses → Pengguna** setelah turnamen dibuat. Tapi untuk uji coba, seluruh akun itu sudah disiapkan seeder simulasi di bawah.

Untuk instalasi LAN Windows tanpa internet setelah dependensi terunduh (NFR-08) — arsitektur jaringan, `php.ini`, firewall, dan dua proses hari-H — ikuti [`docs/PANDUAN-SISTEM.md`](docs/PANDUAN-SISTEM.md); rinciannya di [`docs/INSTALASI-LAN.md`](docs/INSTALASI-LAN.md).

### Kejuaraan siap-uji untuk simulasi manual

```bash
php artisan silat:simulasi
```

Menyusun satu kejuaraan yang seluruh tahap pra-acaranya sudah selesai — akun tiap peran, tarif, sepuluh kontingen beserta atlet dan berkasnya, tagihan lunas, pendaftaran terverifikasi, timbang badan, bagan terkunci, jadwal, dan penugasan aparat. Tinggal masuk sebagai Operator IT dan menekan Mulai babak.

Yang sengaja **tidak** dikerjakan: menjalankan partai, memasukkan nilai juri, dan mengesahkan hasil — justru itu yang mau diuji manual.

Isi datanya — **100 pesilat, seluruhnya kategori Tanding**:

| Nomor | Peserta | Yang diuji |
|---|---|---|
| Tanding Dewasa kelas A–E putra | 10 atlet per kelas | Bagan 16 tanpa bye di babak pertama, jadwal panjang lintas gelanggang |
| Tanding Dewasa kelas A–E putri | 10 atlet per kelas | Idem, plus berkas surat tidak hamil |

Sepuluh kontingen masing-masing mengirim satu putra dan satu putri per kelas, jadi tidak ada kontingen yang bertemu dirinya sendiri di babak pertama. Bagannya diundi acak. Sepuluh bagan menghasilkan 50 partai perdelapan yang seluruhnya sudah punya dua peserta; semuanya terjadwal mulai pukul 08.00 hari pertama, berselang-seling di dua gelanggang dengan jarak 20 menit.

Sepuluh peserta jatuh ke bagan 16, dan bagan di aplikasi ini mengisi tempat rapat dari nomor satu — jadi **babak pertama tidak punya bye sama sekali**: kesepuluhnya bertanding. Kekurangan empat tempat itu muncul di belakang: pemenang partai perdelapan terakhir tidak punya lawan di perempat maupun semifinal, sehingga ia sampai final dengan satu kali bertanding sementara lawannya sudah tiga kali. Itu konsekuensi yang melekat pada aturan ini, bukan cacat — bye di babak belakang hanya mungkin bila satu cabang bagan kosong seluruhnya, dan cabang kosong selalu menguntungkan orang yang sama. Bye hilang sepenuhnya hanya bila jumlah peserta tiap kelas berupa pangkat dua; ubah `JUMLAH_KONTINGEN` di `database/seeders/SimulasiTurnamenSeeder.php` menjadi `8` untuk kejuaraan 80 pesilat berbagan penuh.

Nomor Jurus tidak diikutkan supaya jumlah pesilatnya bulat 100 dan tiap kelas benar-benar berisi sepuluh — mesin penilaian Jurus dijaga test suite, bukan data simulasi ini.

Dua gelanggang (A dan B) masing-masing punya operatornya sendiri, sehingga dua partai bisa dijalankan bersamaan. Window konsensus juri dilebarkan jadi 5 detik (bawaan 2 detik) supaya satu penguji bisa berpindah antar tab atau antar HP tanpa kehabisan waktu.

| Akun | Peran |
|---|---|
| `operator@silat.test`, `operator2@silat.test` | Operator IT (Gelanggang A dan B: panel gelanggang, timer) |
| `wasit1@silat.test`, `wasit2@silat.test` | Wasit |
| `juri1@silat.test` … `juri6@silat.test` | Juri (1–3 Gelanggang A, 4–6 Gelanggang B) |
| `ketua@silat.test` | Ketua Pertandingan (pengesahan hasil, VAR, putusan protes) |
| `pengawas@silat.test`, `komisi@silat.test` | Pengawas / Dewan Wasit Juri, Wasit Komisi Protes |
| `sekretariat@silat.test` | Sekretariat Pertandingan (berkas, tagihan, timbang badan) |
| `official1@silat.test` … `official10@silat.test` | Official kontingen |

Kata sandi seluruhnya `password`. Ulangi dari bersih dengan `php artisan silat:simulasi --reset` — kejuaraan simulasi lama beserta seluruh peserta, tagihan, bagan, dan hasilnya dihapus permanen lebih dulu.

Langkah ujinya per tahap ada di [`docs/PANDUAN-WORKFLOW.md`](docs/PANDUAN-WORKFLOW.md).

---

## Peta modul

| Fase | Modul | Dokumen |
|---|---|---|
| 0–1 | Master data turnamen, gelanggang, kelas tanding, nomor Jurus | `app/Actions/Turnamen/SusunMasterDataTurnamen.php` |
| 2 | Pendaftaran, verifikasi, timbang badan | `app/Http/Controllers/Admin/{Registration,Verification,WeightIn}Controller.php` |
| 2b | Tarif, invoice, pembayaran manual (Midtrans belum tersambung — butuh kredensial sandbox) | `app/Http/Controllers/Admin/{FeeSchedule,Invoice,Treasury}Controller.php` |
| 3 | Bagan gugur tunggal, jadwal partai | `app/Http/Controllers/Admin/{Bracket,Jadwal,Aparat}Controller.php` |
| 4 | Mesin scoring Tanding: konsensus juri, timer, tangga hukuman, panel juri/wasit/operator/dewan juri | `app/Support/Scoring/`, `resources/views/silat/` |
| 4b | VAR dan Protes Manajer | `app/Support/Var/`, `resources/views/silat/keberatan.blade.php` |
| 5 | Live score publik + panduan tunneling | `app/Http/Controllers/Public/LiveScoreController.php`, `docs/TUNNELING.md` |
| 6 | Overlay siaran vMix | `app/Http/Controllers/OverlayController.php`, `resources/views/overlay/` |
| 7 | Mesin scoring Jurus: median, pengurangan, pemecah seri | `app/Support/Jurus/`, `resources/views/jurus/` |
| 8 | Rekap medali, berita acara PDF, ekspor | `app/Support/Rekap/RekapMedali.php`, `app/Http/Controllers/Admin/RekapController.php` |

Status detail dan checklist tiap fase: [`docs/RENCANA.md`](docs/RENCANA.md).

---

## Realtime: Laravel Reverb

Broadcasting memakai `laravel/reverb`, bukan Pusher/Ably — gelanggang harus tetap berfungsi tanpa internet. Tiga kelompok channel:

- `presence-arena.{id}` — private, dipakai panel operator/wasit/juri/dewan juri. Butuh login.
- `public-live.{arena}` — public, dipakai live score publik DAN overlay vMix. Payloadnya sengaja tipis: tanpa identitas juri, tanpa input mentah. Tidak disiarkan sama sekali kalau `OVERLAY_ENABLED` dan `LIVE_SCORE_ENABLED` sama-sama mati (bawaannya memang mati).
- `/overlay/*` dan `/live/*` adalah dua kelompok rute terpisah yang **sama-sama membaca channel publik yang sama**, tapi dijaga arah berlawanan: `/overlay/*` dikunci `AllowLocalNetworkOnly` (harus dari LAN, tidak boleh lewat tunnel), `/live/*` justru dirancang untuk diteruskan tunnel ke internet (lihat `docs/TUNNELING.md`).

## Kategori Jurus: penyederhanaan yang disengaja

Nilai juri Jurus (`jurus_scores`) memakai upsert per juri, **bukan** log immutable seperti `judge_inputs` milik Tanding. Ini beda perlakuan yang disengaja, bukan inkonsistensi: juri Jurus menulis satu angka akhir setelah menonton penampilan selesai, bukan menekan tombol cepat berkali-kali dalam window konsensus 2 detik yang butuh jejak tiap perubahan untuk anti-kecurangan. Dicatat di sini supaya keputusan ini terlihat jelas, bukan tersembunyi di commit history.

---

## Dokumen lain

**Dua pintu masuk utama:**

- [`docs/PANDUAN-SISTEM.md`](docs/PANDUAN-SISTEM.md) — **panduan final menyiapkan sistem**, dari nol sampai gong pertama: arsitektur LAN, daftar kebutuhan, pemasangan, konfigurasi, multi-gelanggang, vMix, tunnel, daftar periksa, dan tabel gejala→tindakan
- [`docs/REPOWIKI.md`](docs/REPOWIKI.md) — **peta kode untuk developer**: invarian, lapisan, alur satu nilai Tanding, RBAC, rute, perangkap pengujian

**Rincian:**

- [`docs/RENCANA.md`](docs/RENCANA.md) — PRD lengkap, task list per epic, checklist per fase
- [`docs/ARSITEKTUR.md`](docs/ARSITEKTUR.md) — diagram arsitektur dan alur data
- [`docs/ERD.md`](docs/ERD.md) — diagram relasi entitas
- [`docs/TUNNELING.md`](docs/TUNNELING.md) — konfigurasi reverse proxy untuk live score publik
- [`docs/INSTALASI-LAN.md`](docs/INSTALASI-LAN.md) — pemasangan di satu mesin Windows untuk LAN gelanggang
- [`docs/PANDUAN-WORKFLOW.md`](docs/PANDUAN-WORKFLOW.md) — cara memakai aplikasi tahap demi tahap, dari kejuaraan kosong sampai rekap medali
- [`docs/PANDUAN-OPERASIONAL.md`](docs/PANDUAN-OPERASIONAL.md) — alur hari-H untuk panitia, setup vMix
- [`docs/PARAMETER-PERATURAN.md`](docs/PARAMETER-PERATURAN.md) — tiap parameter `config/scoring.php` dipetakan ke pasal naskah 2025
- [`docs/BOILERPLATE-RESOURCE-KEYS.md`](docs/BOILERPLATE-RESOURCE-KEYS.md) — dokumentasi teknis fondasi kode (resource key, RBAC, lapisan komponen si/*)

---

## Test

```bash
php artisan test
./vendor/bin/pint          # format kode
```

Modul mesin scoring (Tanding, Jurus, VAR) ditulis dengan TDD — test lebih dulu, implementasi menyusul. Lihat `tests/Feature/Scoring/`, `tests/Feature/Jurus/`, `tests/Feature/Var/`.
