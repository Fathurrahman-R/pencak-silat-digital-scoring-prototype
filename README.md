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

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate --seed
php artisan storage:link

composer run dev      # server + queue + Vite + Reverb sekaligus
```

Buka `http://127.0.0.1:8000`. Akun bawaan seeder (kata sandi semuanya `password`):

| Email | Role | Bisa apa |
|---|---|---|
| `super@example.com` | super-admin | Semuanya, melewati seluruh pengecekan |
| `admin@example.com` | admin | Kelola pengguna |

Peran domain silat (Ketua Pertandingan, Wasit, Juri, Operator IT, dst. — lihat Pasal 13) didaftarkan `SilatRoleSeeder`, dibuatkan lewat panel **Manajemen Akses → Pengguna** setelah turnamen dibuat.

**Database.** MySQL 8. Buat dua database sebelum migrasi — satu aplikasi, satu test:

```sql
CREATE DATABASE boilerplate      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE boilerplate_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Untuk instalasi LAN Windows tanpa internet setelah dependensi terunduh (NFR-08), ikuti [`docs/INSTALASI-LAN.md`](docs/INSTALASI-LAN.md).

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
- `public-live.{arena}` — public, dipakai live score publik DAN overlay vMix. Payloadnya sengaja tipis: tanpa identitas juri, tanpa input mentah.
- `/overlay/*` dan `/live/*` adalah dua kelompok rute terpisah yang **sama-sama membaca channel publik yang sama**, tapi dijaga arah berlawanan: `/overlay/*` dikunci `AllowLocalNetworkOnly` (harus dari LAN, tidak boleh lewat tunnel), `/live/*` justru dirancang untuk diteruskan tunnel ke internet (lihat `docs/TUNNELING.md`).

## Kategori Jurus: penyederhanaan yang disengaja

Nilai juri Jurus (`jurus_scores`) memakai upsert per juri, **bukan** log immutable seperti `judge_inputs` milik Tanding. Ini beda perlakuan yang disengaja, bukan inkonsistensi: juri Jurus menulis satu angka akhir setelah menonton penampilan selesai, bukan menekan tombol cepat berkali-kali dalam window konsensus 2 detik yang butuh jejak tiap perubahan untuk anti-kecurangan. Dicatat di sini supaya keputusan ini terlihat jelas, bukan tersembunyi di commit history.

---

## Dokumen lain

- [`docs/RENCANA.md`](docs/RENCANA.md) — PRD lengkap, task list per epic, checklist per fase
- [`docs/ARSITEKTUR.md`](docs/ARSITEKTUR.md) — diagram arsitektur dan alur data
- [`docs/ERD.md`](docs/ERD.md) — diagram relasi entitas
- [`docs/TUNNELING.md`](docs/TUNNELING.md) — konfigurasi reverse proxy untuk live score publik
- [`docs/INSTALASI-LAN.md`](docs/INSTALASI-LAN.md) — pemasangan di satu mesin Windows untuk LAN gelanggang
- [`docs/PANDUAN-OPERASIONAL.md`](docs/PANDUAN-OPERASIONAL.md) — alur hari-H untuk panitia, setup vMix
- [`docs/PARAMETER-PERATURAN.md`](docs/PARAMETER-PERATURAN.md) — tiap parameter `config/scoring.php` dipetakan ke pasal naskah 2025
- [`docs/BOILERPLATE-RESOURCE-KEYS.md`](docs/BOILERPLATE-RESOURCE-KEYS.md) — dokumentasi teknis fondasi kode (resource key, RBAC, RizzxxUI)

---

## Test

```bash
php artisan test
./vendor/bin/pint          # format kode
```

Modul mesin scoring (Tanding, Jurus, VAR) ditulis dengan TDD — test lebih dulu, implementasi menyusul. Lihat `tests/Feature/Scoring/`, `tests/Feature/Jurus/`, `tests/Feature/Var/`.
