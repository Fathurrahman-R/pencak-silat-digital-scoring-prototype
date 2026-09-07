# Repowiki — Peta Kode untuk Developer

> Dokumen orientasi bagi developer yang baru memegang repo ini, atau yang kembali setelah lama.
> Ia menjawab "di mana X" dan "kenapa begini", bukan "cara memasang" — itu ada di
> [`PANDUAN-SISTEM.md`](PANDUAN-SISTEM.md).
>
> Peta mesin dari graf pengetahuan (`graphify-out/`) melengkapi dokumen ini; lihat
> [§18](#18-graf-pengetahuan-graphify).

---

## 1. Orientasi enam puluh detik

Aplikasi Laravel 13 monolitik yang menjalankan satu kejuaraan pencak silat dari pendaftaran
sampai berita acara. Tidak ada API terpisah, tidak ada SPA: Blade + Alpine 3, dan satu channel
WebSocket (Laravel Reverb) untuk realtime gelanggang.

| | |
|---|---|
| Bahasa | PHP 8.3+, JavaScript (tanpa build framework selain Vite) |
| Framework | Laravel 13, Fortify (auth), spatie/laravel-permission (RBAC), Reverb (WebSocket), dompdf (cetak) |
| Basis data | MySQL 8 — 73 migrasi, 44 model |
| Frontend | Blade (166 view), Alpine 3, Tailwind CSS 4, komponen `<x-si.*>` |
| Uji | Pest 5 — 100 berkas uji, mayoritas Feature |
| Bahasa domain | **Indonesia.** Nama kelas, method, variabel, dan komentar berbahasa Indonesia kecuali istilah Laravel |

Sumber kebenaran aturan pertandingan: **Peraturan Pertandingan Pencak Silat Nasional 2025**,
SK PB IPSI Skep-70/III/2025, PDF-nya ada di `document/`. Setiap penyimpangan sadar dari naskah
itu dicatat di `config/scoring.php` beserta alasannya.

---

## 2. Lima invarian yang tidak boleh dilanggar

Kalau sebuah perubahan melanggar salah satu dari ini, perubahannya yang salah — bukan invariannya.

### 2.1 `judge_inputs` tidak pernah diubah atau dihapus

Tiap tekanan tombol juri Tanding tersimpan mentah, selamanya. Koreksi dewan juri memakai
**baris pembatal** (`voided_at` / `voided_by` / `void_reason`), bukan `UPDATE` atau `DELETE`.
Pola yang sama dipakai ulang di VAR dan pengurangan nilai Jurus.

Konsekuensi: query skor selalu memfilter lewat scope `berlaku()`, tidak pernah membaca baris
mentah tanpa filter itu.

### 2.2 Waktu resmi selalu milik server

Timer partai dan penampilan Jurus dihitung dari `started_at` / `accumulated_ms` di basis data.
Jam perangkat juri atau operator tidak pernah dipercaya. Lihat
[`app/Support/Scoring/MatchTimer.php`](../app/Support/Scoring/MatchTimer.php) — "Panel operator
hanya menampilkan hasil hitungan ini; ia tidak diberi wewenang menghitung sendiri."

Input juri diberi `server_ts` oleh server saat tiba, bukan oleh HP juri.

### 2.3 Yang bisa dihitung tidak disimpan

Skor, golongan usia dari kelas, posisi bagan berikutnya — semuanya dihitung ulang dari data
mentah. Tidak ada dua salinan angka yang bisa diam-diam bergeser.

Pengecualian terkendali: [`SnapshotSkor`](../app/Support/Scoring/SnapshotSkor.php) menyimpan
hasil hitungan kalkulator apa adanya dan **membuangnya** begitu ada nilai/hukuman berubah
(`App\Observers\SnapshotSkorObserver`). Ia cache, bukan sumber kebenaran. Kalau isinya pernah
berbeda dari hitungan segar, yang salah snapshot-nya: `php artisan silat:snapshot-skor --bangun-ulang`.

### 2.4 Event siaran hanya pemicu, bukan muatan

Setiap event Reverb dianggap "sesuatu berubah, ambil ulang". Panel yang menerima event **selalu**
memanggil endpoint resync (`GET .../state`), tidak pernah menambal payload event ke state lokal.
Ini menghapus seluruh kelas bug drift dua salinan state. Pola sama dipakai di overlay dan live
score publik.

### 2.5 Aturan tinggal di `App\Support\*`, bukan di controller

`App\Support\*` berisi aturan pertandingan, diuji lewat TDD, dan **tidak tahu apa-apa soal HTTP**.
Controller hanya menerjemahkan permintaan jadi pemanggilan `App\Support\*` lalu menyiarkan
hasilnya. Konsisten di `PartaiScoringController`, `VarController`, `JurusScoringController`.

Kalau ada `if` aturan pertandingan di controller, ia salah tempat.

---

## 3. Peta lapisan

```
app/
  Support/                 ATURAN — tanpa HTTP, diuji langsung
    Scoring/    ConsensusEvaluator, MatchTimer, TanggaHukuman, TandingScoreCalculator,
                HitunganTeknik, SnapshotSkor, BabakSusulan, PollingVerifikasi,
                CatatInputJuri, HasilPertandingan, AlasanMenang
    Jurus/      JurusTimer, JurusScoreCalculator, PerbandinganBattle, PutuskanBattle
    Var/        PengajuanProtes, KeputusanVar, PengajuanProtesManajer, KeputusanProtesManajer
    Live/       StatePartaiPublik — satu sumber kebenaran payload overlay DAN live publik
    Sinkron/    PetaSinkron, Kepemilikan, PembungkusPaket, PenarikPeer, PenerapPaket,
                CatatanKeluar, KonversiKunciUlid
    Bagan/      PohonBagan, PromosiPemenang
    Gelanggang/ PointerPartaiAktif
    Rekap/ Ekspor/ Arsip/ Keuangan/ Pendaftaran/ Peraturan/ Pemantauan/ Panel/ …
  Http/
    Controllers/Admin/     Panel gelanggang & admin — butuh auth + resource key
    Controllers/Public/    LiveScoreController — tanpa auth, throttle:live
    Controllers/OverlayController.php  — tanpa auth, AllowLocalNetworkOnly
    Middleware/            8 middleware, lihat §10
  Events/Scoring/          ShouldBroadcastNow (5 event) + Events/Gelanggang
  Models/                  44 model Eloquent
  Observers/ Policies/ Enums/ (18 enum) Broadcasting/ Console/Commands/ (10 command)
routes/
  web.php      594 baris — admin + panel gelanggang, seluruhnya di grup 'web' + auth
  live.php     Live score publik            → didaftarkan lewat then: di bootstrap/app.php
  overlay.php  Overlay vMix                 → idem
  sinkron.php  Endpoint antar-laptop        → idem
  channels.php Otorisasi channel Reverb
resources/
  js/silat.js              Bundel gelanggang: partaiPanel, jurusPanel, silatTimer, store koneksi
  js/echo.js               Penyambung Reverb — alamatnya mengikuti alamat yang dibuka peramban
  js/overlay/connection.js overlayLive — dipakai overlay DAN live score publik
  views/components/si/     Design system (33 komponen: tombol, kartu, tabel, modal, …)
  views/silat/             Panel gelanggang (juri, wasit, operator, dewan juri, papan)
```

---

## 4. Alur inti: satu nilai Tanding, dari tombol sampai layar

Ini jalur terpanas di seluruh sistem. Hafalkan yang ini dan sisanya menyusul.

```
PWA Juri  ──POST──▶  PartaiScoringController
                          │
                          ▼
                     CatatInputJuri ──▶ INSERT judge_inputs  (server_ts dibubuhi server)
                          │
                          ▼
                     ConsensusEvaluator
                          │  DB::transaction + SilatMatch::lockForUpdate()
                          │  hitung juri BERBEDA yang menekan teknik sama dalam window_ms
                          │
              ┌───────────┴───────────┐
     ambang tercapai            belum cukup
              │                        │
              ▼                        ▼
   INSERT score_events        umpan balik lokal saja
   tandai judge_inputs.score_event_id   (titik juri menyala)
              │
              ▼
   broadcast ScoreAwarded (ShouldBroadcastNow)
              │
      ┌───────┴────────┐
      ▼                ▼
 presence-arena.*  public-live.*   ← hanya jika OVERLAY_ENABLED atau LIVE_SCORE_ENABLED
      │                │
      ▼                ▼
 panel gelanggang   overlay/live  ── keduanya memanggil GET …/state (resync penuh)
```

Berkas yang disentuh, berurutan:

1. [`app/Http/Controllers/Admin/PartaiScoringController.php`](../app/Http/Controllers/Admin/PartaiScoringController.php)
2. [`app/Support/Scoring/CatatInputJuri.php`](../app/Support/Scoring/CatatInputJuri.php)
3. [`app/Support/Scoring/ConsensusEvaluator.php`](../app/Support/Scoring/ConsensusEvaluator.php)
4. [`app/Events/Scoring/ScoreAwarded.php`](../app/Events/Scoring/ScoreAwarded.php)
5. [`app/Support/Live/SaluranArena.php`](../app/Support/Live/SaluranArena.php) — memutuskan satu atau dua channel
6. [`app/Support/Live/StatePartaiPublik.php`](../app/Support/Live/StatePartaiPublik.php) — payload resync

**Ambang dan window dibaca dari `TournamentRuleSetting`,** bukan angka tetap — naskah 2025 tidak
mengaturnya. Bawaannya di `config/scoring.php`: 3 juri, ambang sepakat 2, window 2000 ms.

**Dua jebakan presisi di `ConsensusEvaluator`** yang sudah pernah menggigit:

- Batas window diformat eksplisit `'Y-m-d H:i:s.v'`. Format bawaan query builder memangkas
  milidetik saat mem-bind Carbon, dan window 2 detik kehilangan artinya kalau presisinya jatuh
  ke detik penuh.
- `lockForUpdate()` pada baris partai wajib. Tanpa itu, dua input yang tiba nyaris bersamaan
  sama-sama lolos evaluasi dan melahirkan dua nilai kembar untuk momen yang sama.

---

## 5. Skor, hukuman, dan hasil

| Kelas | Tanggung jawab | Pasal |
|---|---|---|
| [`TandingScoreCalculator`](../app/Support/Scoring/TandingScoreCalculator.php) | Skor kumulatif dan per babak, kedua sudut dalam dua query | 11.6.g.1 |
| [`TanggaHukuman`](../app/Support/Scoring/TanggaHukuman.php) | Pembinaan → Teguran → Peringatan → Diskualifikasi | 11.6.d.4 |
| [`HitunganTeknik`](../app/Support/Scoring/HitunganTeknik.php) | Hitungan teknik (jatuhan yang tidak dilanjutkan) | |
| [`HasilPertandingan`](../app/Support/Scoring/HasilPertandingan.php) + [`AlasanMenang`](../app/Support/Scoring/AlasanMenang.php) | Penentuan pemenang dan alasannya | |
| [`BabakSusulan`](../app/Support/Scoring/BabakSusulan.php) | Mencatat nilai/hukuman yang terlewat di babak lalu | |

Nilai teknik (`config/scoring.php`): pukulan 1, tendangan 2, jatuhan 3.

**Cakupan tangga hukuman berbeda per tingkat, dan ini sering salah dibaca:**

- **Pembinaan** — dihitung **per babak**, kembali nol tiap babak baru, tidak pernah tersetel
  ulang oleh eskalasi. Setelah dua pembinaan dalam satu babak, pelanggaran ringan berikutnya di
  babak itu naik jadi Teguran. *Ini penyimpangan sadar dari naskah; alasannya di `config/scoring.php`.*
- **Teguran** — bertingkat **sepanjang partai** (Teguran I lalu II), tidak mengulang tiap babak.
  Naik ke Peringatan I lewat **dua** pemicu: teguran ketiga sepanjang partai, atau pelanggaran
  berikutnya setelah dua teguran dalam babak yang sama. Mana pun yang lebih dulu.
- **Peringatan** — sepanjang partai, tidak pernah reset.

Semua tingkat dihitung dari baris `penalties` yang tercatat, bukan dari kolom penghitung
(invarian [§2.3](#23-yang-bisa-dihitung-tidak-disimpan)).

---

## 6. Kategori Jurus

Jalur terpisah dari Tanding, model dan controller sendiri:
`JurusPerformance`, `JurusScore`, `JurusDeduction`, `JurusBracket`, `JurusBracketSlot`,
`JurusBattle`, `JurusEvent`.

| Kelas | Tanggung jawab |
|---|---|
| [`JurusTimer`](../app/Support/Jurus/JurusTimer.php) | Timer penampilan, server-authoritative sama seperti Tanding |
| [`JurusScoreCalculator`](../app/Support/Jurus/JurusScoreCalculator.php) | Nilai 9.00–10.00, pengurangan 0.01 (juri) dan 0.50 (dewan wasit juri) |
| [`PerbandinganBattle`](../app/Support/Jurus/PerbandinganBattle.php) / [`PutuskanBattle`](../app/Support/Jurus/PutuskanBattle.php) | Format battle (adu langsung antar dua penampilan) |

Pengesahan **ditolak sistem** kalau jumlah juri yang menilai kurang dari setelan turnamen atau
jumlahnya ganjil (Pasal 16.1.b), kecuali penampilan itu didiskualifikasi. Bawaan
`config/scoring.php`: minimal 4 juri, harus genap.

---

## 7. VAR, protes manajer, dan verifikasi juri

| Kelas | Alur |
|---|---|
| [`PengajuanProtes`](../app/Support/Var/PengajuanProtes.php) → [`KeputusanVar`](../app/Support/Var/KeputusanVar.php) | Protes VAR saat partai berjalan, tenggat 5 menit |
| [`PengajuanProtesManajer`](../app/Support/Var/PengajuanProtesManajer.php) → [`KeputusanProtesManajer`](../app/Support/Var/KeputusanProtesManajer.php) | Protes setelah hasil diumumkan |
| [`PollingVerifikasi`](../app/Support/Scoring/PollingVerifikasi.php) | Verifikasi juri yang dipimpin Ketua Pertandingan |

Protes yang **diterima wajib memilih akibatnya** (enum `AkibatProtes`) sebelum tombol Terima
berhasil: mengubah hasil, menambah satu babak (Tanding), atau penampilan kembali (Jurus).
Pengesahan hasil tertahan sampai akibatnya dijalankan.

Koreksi hasil protes memakai baris pembatal, bukan penyuntingan — invarian
[§2.1](#21-judge_inputs-tidak-pernah-diubah-atau-dihapus).

---

## 8. Siaran realtime

Lima event, semuanya `ShouldBroadcastNow` (didorong di dalam permintaan, bukan lewat pekerja
antrean — itu sebabnya `queue:listen` **tidak dibutuhkan** di hari-H):

| Event | Kelas |
|---|---|
| `timer.berubah` | `App\Events\Scoring\TimerTicked` |
| `skor.terbit` | `App\Events\Scoring\ScoreAwarded` |
| `hukuman.terbit` | `App\Events\Scoring\PenaltyIssued` |
| `partai.berubah` | `App\Events\Scoring\MatchStateChanged` |
| `juri.input` | `App\Events\Scoring\JudgeInputReceived` |

Ditambah tiga event di luar kelima itu: `gelanggang.partai` (`Events\Gelanggang\PartaiAktifBerubah`),
`babak-susulan.berubah`, dan `verifikasi.berubah`.

**Dua channel, dan channel kedua bersyarat:**

| Channel | Siapa | Otorisasi |
|---|---|---|
| `presence-arena.{arenaId}` | Panel juri, wasit, operator, dewan wasit juri | `App\Broadcasting\ArenaChannelAuthorizer` lewat `Broadcast::channel('arena.{arenaId}', …)` |
| `public-live.{arena}` | Overlay vMix, live score publik | **Tanpa** otorisasi — namanya sengaja tidak berawalan `private-`/`presence-` |

`public-live.*` hanya disertakan selama `OVERLAY_ENABLED` **atau** `LIVE_SCORE_ENABLED` menyala
(lihat `App\Support\Live\SaluranArena`). Saat keduanya mati, tiap event mendorong satu kali, bukan
dua — penghematan yang menempel di setiap tekanan tombol juri. Panel gelanggang tidak terpengaruh
sama sekali.

**Middleware `SiaranAktif` (alias `siaran`)** menjaga halaman dan endpoint yang memang realtime.
Ia dipasang **paling depan**, sebelum `SubstituteBindings` — itulah yang membuat balasan 503 saat
siaran dimatikan tidak menyentuh database sama sekali. Urutan itu dijaga uji yang menghitung
query; jangan ditukar.

**Alamat WebSocket tidak tertanam di aset.** `resources/js/echo.js` menyambung ke host yang sedang
dibuka peramban. Ini disengaja: `VITE_REVERB_HOST` dibaca Vite saat kompilasi dan ikut terpanggang
ke `public/build/assets/echo-*.js`, sehingga IP yang berubah memutus seluruh HP juri sekaligus dan
memperbaikinya menuntut `npm run build` di tengah kejuaraan. Isi `VITE_REVERB_HOST` **hanya** kalau
Reverb dijalankan di mesin yang berbeda dari yang melayani HTTP — dan sesudahnya `npm run build`
wajib.

---

## 9. RBAC berbasis resource key

Bukan `can('edit-post')` biasa. Izin berbentuk **resource key**: `{resource}.{action}`.

```php
// app/Support/helpers.php
rk('bagan', ResourceAction::View)        // → "bagan.view"
resource_allows('bagan.view')            // → bool untuk pengguna yang sedang login
```

Dipasang sebagai middleware per rute:

```php
Route::get('/', 'index')->name('index')->middleware('resource:'.rk('bagan', ResourceAction::View));
```

`ResourceAction` (enum): `View`, `Create`, `Update`, `Delete`, `Approve`, `Reject`, `Assign`,
`Manage`, `Export`, `Print` — daftar penuh di [`app/Enums/ResourceAction.php`](../app/Enums/ResourceAction.php).

**Sembilan peran silat** (di luar `super-admin`, `admin`, `user` bawaan boilerplate), didefinisikan
di [`database/seeders/SilatRoleSeeder.php`](../database/seeders/SilatRoleSeeder.php):

| `name` | Label |
|---|---|
| `ketua-pertandingan` | Ketua Pertandingan (melebur Delegasi Teknik) |
| `pengawas-wasit-juri` | Pengawas / Dewan Wasit Juri |
| `wasit-komisi-protes` | Wasit Komisi Protes |
| `wasit` | Wasit |
| `juri` | Juri |
| `pengendali-gelanggang` | Pengendali Gelanggang |
| `operator-it` | Operator IT |
| `sekretariat` | Sekretariat Pertandingan (melebur sekretaris, bendahara, petugas timbang) |
| `official-kontingen` | Official Kontingen |

Empat perintah artisan menjaga daftar key tetap sinkron dengan rute:

```bash
php artisan resource:keys --check   # gagal kalau berkas key tidak mutakhir — pakai ini di CI
php artisan resource:sync           # tulis ulang
php artisan resource:list
php artisan resource:doctor         # cari key yang dipakai rute tapi tidak terdaftar, dan sebaliknya
```

Rincian konvensinya: [`BOILERPLATE-RESOURCE-KEYS.md`](BOILERPLATE-RESOURCE-KEYS.md).

---

## 10. Empat kelompok rute, dan kenapa dipisah

Ini bagian yang paling mudah salah diubah. Ketiga kelompok non-`web` didaftarkan di closure
`then:` pada [`bootstrap/app.php`](../bootstrap/app.php).

| Kelompok | Grup `web`? | Pengaman | Arah |
|---|---|---|---|
| `routes/web.php` | ya | `auth` + `resource:` | LAN, orang yang login |
| `routes/overlay.php` | ya (tanpa `auth`) | `AllowLocalNetworkOnly` | **Hanya LAN** — vMix tidak bisa login |
| `routes/live.php` | ya | `throttle:live` | **Sengaja untuk internet** lewat tunnel |
| `routes/sinkron.php` | **tidak** | `TokenSinkron` + `AllowLocalNetworkOnly` | Laptop lain, bukan orang |

Plus dua endpoint JSON yang dikeluarkan dari grup `web` secara khusus karena ditarik paling sering
di seluruh sistem:

- `overlay/state/{arena}` — `siaran:overlay,json` + `SubstituteBindings` + `AllowLocalNetworkOnly`
- `live/gelanggang/{arena}/state` — `siaran:live,json` + `SubstituteBindings` + `throttle:live`

Yang dilepas bukan pengamannya melainkan **sesi**. `StartSession` membaca dan menulis satu baris
sesi tiap permintaan (dua perjalanan ke MySQL dengan `SESSION_DRIVER=database`), lalu
`EnsureUserIsActive` dan `IngatTurnamenAktif` ikut jalan di belakangnya. Tidak satu pun berguna
untuk vMix Browser Input yang tidak bisa login, atau untuk penonton live score yang tidak punya
turnamen aktif untuk diingat.

`SubstituteBindings` disebut manual di situ. Tanpa itu `{arena}` tidak pernah berubah jadi model,
dan controller membalas "tidak ada partai" untuk gelanggang yang sedang bertanding.

> **`/overlay/*` dan `/live/*` berlawanan arah, dan mencampurnya adalah risiko terbesar desain ini.**
> Keduanya membaca channel publik yang sama dan payload yang sama, tapi overlay dikunci ke LAN
> sementara live justru dirancang keluar. Meneruskan `/overlay/*` lewat tunnel berarti panel juri,
> wasit, dan operator ikut terekspos ke internet. Reverse proxy di [`TUNNELING.md`](TUNNELING.md)
> membalas 404 untuk apa pun selain `/live/*`, `/build/*`, `/app/*`.

### Middleware

| Alias / kelas | Fungsi |
|---|---|
| `resource` → `EnsureResourceAccess` | Cek resource key |
| `active` → `EnsureUserIsActive` | Tolak akun nonaktif |
| `siaran` → `SiaranAktif` | Gerbang `OVERLAY_ENABLED` / `LIVE_SCORE_ENABLED`, membalas 503 tanpa menyentuh DB |
| `HeaderKeamanan` | Global (bukan cuma `web`) — halaman galat, overlay, dan live ikut dijaga dari pembingkaian |
| `IngatTurnamenAktif` | Menyimpan turnamen aktif di sesi |
| `AllowLocalNetworkOnly` | Cek CIDR dari `config('overlay.allowed_cidrs')`, balas **403** (bukan 404 — pesannya untuk operator IT yang sedang men-debug vMix) |
| `TokenSinkron` | Token bersama antar laptop |
| `CatatWaktuState` | Instrumentasi waktu endpoint state |

---

## 11. Multi-node: satu gelanggang satu laptop

Lima mesin untuk kejuaraan empat gelanggang: empat node gelanggang + satu **node global**.

**Aturan satu penulis menggantikan resolusi konflik.** Sistem tidak menjawab "kalau dua node
mengubah baris yang sama, mana yang menang" — ia membuat keadaan itu tidak bisa terjadi.

| Golongan data | Ditulis oleh | Contoh |
|---|---|---|
| Global | Node global saja | atlet, kontingen, pendaftaran, bagan, jadwal, pengguna, peran |
| Penghubung | Disisipkan node global, diperbarui gelanggang pemiliknya | `matches`, `jurus_performances`, `jurus_battles` |
| Lokal | Gelanggang tempat partainya berjalan | nilai, hukuman, timer, verifikasi, VAR, protes |

Kepemilikan dibandingkan lewat **kode** gelanggang (`A`, `B`), bukan id — id auto-increment
berbeda antar basis data.

**Empat belas tabel yang lahir di gelanggang memakai ULID**, bukan auto-increment: penghitung tiap
basis data mulai dari satu, jadi gelanggang A dan B akan menerbitkan baris bernomor sama. ULID
(bukan UUID acak) karena kunci utama menentukan urutan fisik baris di InnoDB, dan ULID yang urut
mengikuti waktu membuat sisipan tetap jatuh di ujung. `matches` **tidak** ikut pindah — hanya node
global yang menyisipkannya.

> Konversi 100.500 baris `judge_inputs` ke ULID memakan **9 menit 49 detik**. Jangan menjalankan
> migrasi ini di sela pertandingan. Pemasangan baru di basis data kosong tidak terpengaruh.

Kode: `app/Support/Sinkron/` — `PetaSinkron` (tabel mana milik siapa), `Kepemilikan`,
`PembungkusPaket`/`PenerapPaket` (serialisasi), `PenarikPeer`, `CatatanKeluar`, `KonversiKunciUlid`.

**Perulangan penarikan sengaja di browser**, satu potongan per permintaan (bawaan 500 baris). Satu
permintaan yang menarik sampai habis akan menahan satu dari delapan proses `php-cgi` selama seluruh
penarikan — proses yang juga melayani tekanan tombol juri.

**Kursor disimpan setelah penerapan berhasil**, bukan sebelum. Kursor yang maju lebih dulu berarti
perubahan yang gagal diterapkan dianggap sudah masuk dan tidak pernah ditarik lagi — hilang tanpa
galat, baru ketahuan saat rekap medali tidak cocok antar laptop.

Selengkapnya: [`MULTI-GELANGGANG.md`](MULTI-GELANGGANG.md), [`ARSIP-BUKTI.md`](ARSIP-BUKTI.md).

---

## 12. Basis data

73 migrasi, 44 model. Skema penuh beserta relasinya: [`ERD.md`](ERD.md).

Kelompok tabel:

| Kelompok | Tabel utama |
|---|---|
| Kejuaraan | `tournaments`, `tournament_rule_settings`, `weight_classes`, `arenas` |
| Peserta | `contingents`, `athletes`, `registrations`, `registration_documents`, `weight_ins` |
| Keuangan | `fee_schedules`, `invoices`, `invoice_items`, `manual_payments` |
| Bagan & jadwal | `brackets`, `bracket_slots`, `matches`, `match_officials`, `match_rounds`, `match_round_reopens` |
| Scoring Tanding | `judge_inputs`, `score_events`, `penalties`, `technical_counts` |
| Verifikasi & protes | `judge_verifications`, `judge_verification_answers`, `var_reviews`, `manager_protests`, `protest_cards` |
| Jurus | `jurus_performances`, `jurus_scores`, `jurus_deductions`, `jurus_brackets`, `jurus_bracket_slots`, `jurus_battles`, `jurus_events` |
| Akses | `users`, `roles`, `permissions`, `resources`, `resource_permissions` |
| Operasional | `audit_logs`, tabel sinkron, tabel arsip |

Indeks jalur panas ditambahkan di `2026_09_05_110000_tambah_index_jalur_panas.php` (peningkatan
terukur ~70× pada `judge_inputs`).

---

## 13. Perintah artisan

| Perintah | Fungsi |
|---|---|
| `silat:simulasi` | Bangun kejuaraan simulasi lengkap: akun tiap peran, bagan, jadwal. **Buang sebelum hari-H.** |
| `silat:kesehatan` | Tiga metrik kesehatan node (ambang di `config/pemantauan.php`) |
| `silat:beban` | Uji beban internal |
| `silat:arsip [--dorong]` | Status/dorong arsip bukti partai ke node global |
| `silat:snapshot-skor [--bangun-ulang]` | Kelola cache skor |
| `silat:pindah-pengendali` | Beri peran Pengendali Gelanggang ke Operator IT yang sudah memegang gelanggang; laporkan yang masih kosong |
| `resource:keys --check` / `resource:sync` / `resource:list` / `resource:doctor` | Jaga resource key sinkron dengan rute |

Penjadwal (`bootstrap/app.php`) hanya berjalan kalau `schedule:work` dijalankan, dan **di mesin
gelanggang saat hari-H ia tidak dijalankan**. Isinya sengaja hal yang boleh tertunda:
`silat:arsip --dorong` tiap sepuluh menit, `model:prune`, `queue:prune-failed`,
`cache:prune-stale-tags`.

**Pemangkasan riwayat juri tidak dijadwalkan, dan itu disengaja** — ia menghapus bukti; yang
menekan tombolnya harus manusia yang tahu kejuaraannya sedang di titik mana.

---

## 14. Menjalankan uji — baca ini sebelum menyalahkan kode

Empat perangkap yang sudah pernah memakan waktu, semuanya bergejala seperti bug nyata.

### 14.1 Batas memori

Suite penuh mati di tengah jalan dengan `memory_limit` 128M:

```
Allowed memory size of 134217728 bytes exhausted
in vendor/laravel/framework/.../BladeCompiler.php on line 803
```

Yang menyesatkan: Pest tetap keluar dengan **exit code 0** dan tidak menulis ringkasan.
**Laporan "selesai" tanpa baris `Tests: N passed` berarti GAGAL, bukan lulus.**

`php -d memory_limit=1G artisan test` **tidak menolong** — `artisan test` men-spawn pest sebagai
anak dengan `php` polos, dan `-d` hilang. Jalankan binarinya langsung:

```bash
php -d memory_limit=1G vendor/pestphp/pest/bin/pest
```

### 14.2 Satu invokasi pada satu waktu

Dua `php artisan test` bersamaan — termasuk dari dua jendela terminal atau dua sesi asisten —
berebut database `digiscoring_test` yang sama. Keduanya memakai `RefreshDatabase`, jadi yang satu
menghapus tabel di tengah uji milik yang lain, dan yang muncul `SQLSTATE[42S02] Base table not
found` yang terbaca persis seperti regresi.

Periksa dulu:

```powershell
Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -match 'pest' }
```

Kalau memang harus paralel, beri masing-masing database sendiri:

```bash
DB_DATABASE=digiscoring_ce_test php -d memory_limit=1G artisan test
```

**Nama database uji wajib berakhiran `_test`** — `tests/TestCase.php` menolak berjalan kalau tidak,
dan penjagaan itulah yang mencegah `migrate:fresh` menghapus kejuaraan sungguhan.

### 14.3 Cache konfigurasi

Selama `php artisan optimize` aktif, Laravel tidak lagi membaca variabel lingkungan mana pun —
termasuk `DB_DATABASE=digiscoring_test` di `phpunit.xml`. `tests/TestCase.php` menolak berjalan
dalam keadaan itu, jadi yang muncul galat, bukan data yang hilang.

Urutannya: `php artisan optimize:clear` → jalankan uji → `php artisan optimize` lagi.
**`optimize:clear`, bukan `config:clear`** — yang kedua meninggalkan `bootstrap/cache/routes-v7.php`
yang menghabiskan batas memori PHP di tengah suite. Gejalanya identik dengan §14.1, jadi periksa
`bootstrap/cache/` dulu: kalau di sana hanya ada `packages.php` dan `services.php`, sebabnya batas
memori.

### 14.4 Uji hijau bukan jaminan hari-H

`phpunit.xml` memaksa saklar siaran menyala. Mesin produksi yang mematikannya bisa lolos uji tanpa
suara. Kalau menyentuh jalur `SiaranAktif` / `SaluranArena`, uji manual dengan saklar mati juga.

---

## 15. Frontend

Tanpa framework SPA. Blade merender, Alpine memberi interaktivitas, Echo membawa pemicu resync.

| Berkas | Isi |
|---|---|
| `resources/js/silat.js` | `partaiPanel`, `jurusPanel`, `silatTimer`, store koneksi — bundel gelanggang |
| `resources/js/echo.js` | Penyambung Reverb; host mengikuti alamat yang dibuka peramban |
| `resources/js/overlay/connection.js` | `overlayLive` — dipakai overlay **dan** live score publik |
| `resources/js/theme.js` | Tema terang/gelap |

**Design system `si`** — 33 komponen Blade di `resources/views/components/si/`, semuanya berbahasa
Indonesia: `tombol`, `kartu`, `tabel/`, `modal`, `isian`, `pilihan`, `saklar`, `callout`, `badge`,
`linimasa`, `pohon-bagan`, `titik-hadir`, dan seterusnya. Jangan menulis markup Tailwind mentah
untuk hal yang sudah punya komponen — konsistensi kontras WCAG AA dijaga lewat komponen ini.

Empat pemeriksa rupa, jalankan sebelum menyerahkan perubahan UI:

```bash
npm run periksa-rupa   # kelas-hilang + kontras + kontras-kelas + sapu-prop
```

**Sumber kebenaran arah rupa adalah [`BRIEF-DESAIN.md`](BRIEF-DESAIN.md)** (arah "Digital Scoring",
shadcn/zinc), bukan `docs/kanvas/`. Folder kanvas menggambar arah lama "Matras" dan sudah
dinyatakan **arsip** oleh `docs/kanvas/README.md` — dipertahankan sebagai riwayat keputusan, bukan
acuan kerja. Jangan menggambar layar baru dari sana. Audit kepatuhannya:
[`AUDIT-UIUX.md`](AUDIT-UIUX.md).

---

## 16. Cookbook: di mana menambahkan X

| Yang ingin ditambahkan | Mulai dari |
|---|---|
| Aturan pertandingan baru | Kelas baru di `app/Support/Scoring/` + uji Pest lebih dulu (TDD). Controller belakangan. |
| Endpoint panel baru | `routes/web.php` di grup yang sesuai + `middleware('resource:'.rk(…))`, lalu `resource:sync` |
| Event siaran baru | `app/Events/…` implement `ShouldBroadcastNow`, dan lewat `SaluranArena` untuk memutuskan channelnya |
| Kolom baru pada tabel yang disinkronkan | Migrasi **plus** daftarkan di `app/Support/Sinkron/PetaSinkron.php`, tentukan golongan kepemilikannya |
| Tabel baru yang lahir di gelanggang | Pakai ULID, bukan auto-increment ([§11](#11-multi-node-satu-gelanggang-satu-laptop)) |
| Halaman publik baru | `routes/live.php` kalau boleh keluar LAN; `routes/overlay.php` kalau tidak. Jangan tertukar. |
| Peran atau izin baru | `database/seeders/SilatRoleSeeder.php`, lalu `resource:sync` dan `resource:doctor` |
| Komponen UI baru | `resources/views/components/si/`, lalu `npm run periksa-rupa` |
| Perintah operasional baru | `app/Console/Commands/`, awalan `silat:` |

---

## 17. Konvensi

- **Bahasa Indonesia** untuk nama kelas domain, method, variabel, dan komentar. Istilah Laravel
  (`Controller`, `Middleware`, `Observer`) tetap Inggris. Nama tabel dan kolom Inggris, mengikuti
  konvensi Eloquent.
- **Komentar menjelaskan KENAPA, bukan APA.** Repo ini penuh komentar panjang yang mencatat
  keputusan dan trade-off — itu disengaja, dan komentar semacam itu jangan dihapus saat refactor.
  Kalau alasannya sudah tidak berlaku, ubah komentarnya, jangan buang.
- **Pesan commit** memakai awalan tipe berbahasa Inggris dengan ruang lingkup Indonesia:
  `fix(bendahara): …`, `feat(juri): …`.
- **Pint** untuk gaya kode: `vendor/bin/pint`.
- Penyimpangan sadar dari naskah peraturan **selalu** dicatat di `config/scoring.php` beserta
  alasannya, bukan di kelas yang menerapkannya.

---

## 18. Graf pengetahuan (graphify)

`graphify-out/` berisi graf pengetahuan repo yang bisa ditanyai:

```bash
graphify query "bagaimana konsensus juri menerbitkan nilai?"
graphify path "ConsensusEvaluator" "StatePartaiPublik"
graphify explain "TanggaHukuman"
graphify . --update          # setelah perubahan besar
```

| Berkas | Isi |
|---|---|
| `graphify-out/graph.html` | Graf interaktif, buka di peramban |
| `graphify-out/GRAPH_REPORT.md` | Laporan audit: god node, komunitas, koneksi tak terduga |
| `graphify-out/graph.json` | Data graf mentah |
| `graphify-out/wiki/index.md` | Pintu masuk wiki hasil crawl, 434 artikel — pelengkap mesin untuk dokumen ini |

`graphify-out/` **tidak ikut versi** (ada di `.gitignore`) — ia turunan, dan 434 berkas wikinya
membanjiri diff. Bangun sendiri sekali dengan `graphify . --wiki`.

Isi graf: **3.228 simpul, 8.232 sisi, 424 komunitas**, dari 636 berkas kode (AST, deterministik),
52 dokumen, dan 4 PDF peraturan. Lima puluh berkas gambar (tangkapan layar audit UI dan ikon kartu
hukuman) sengaja **tidak** diekstraksi — tidak menambah simpul yang berguna.

> **Batas ketelitian yang perlu diketahui sebelum memercayai jawabannya.** Diagnostik integritas
> melaporkan **1.770 sisi menggantung** dari 8.873 sisi mentah (~20%). Sebabnya: simpul dokumen
> menyebut simbol kode dengan nama pendek (`ConsensusEvaluator`), sementara ekstraktor AST
> menerbitkannya sebagai `app_support_scoring_consensusevaluator_consensusevaluator` — kedua sisi
> tidak bertemu. Akibatnya jembatan **dokumen → kode** lebih lemah daripada jembatan kode → kode.
> Untuk pertanyaan struktur kode graf ini akurat; untuk "dokumen mana yang menjelaskan kelas ini",
> periksa ulang lewat `Grep`.

---

## Peta dokumen lain

| Dokumen | Untuk |
|---|---|
| [`PANDUAN-SISTEM.md`](PANDUAN-SISTEM.md) | **Panduan final menyiapkan sistem** — prasyarat sampai hari-H |
| [`ARSITEKTUR.md`](ARSITEKTUR.md) | Topologi jaringan dan alur data (diagram Mermaid) |
| [`INSTALASI-LAN.md`](INSTALASI-LAN.md) | Instalasi satu mesin, rinci |
| [`MULTI-GELANGGANG.md`](MULTI-GELANGGANG.md) | Sinkron antar laptop |
| [`ARSIP-BUKTI.md`](ARSIP-BUKTI.md) | Arsip bukti partai di node global |
| [`TUNNELING.md`](TUNNELING.md) | Live score publik lewat tunnel |
| [`PANDUAN-OPERASIONAL.md`](PANDUAN-OPERASIONAL.md) | Alur hari-H untuk panitia |
| [`PANDUAN-WORKFLOW.md`](PANDUAN-WORKFLOW.md) | Tahap pra-acara: kejuaraan, tarif, pendaftaran, bagan |
| [`SIMULASI-LAPANGAN.md`](SIMULASI-LAPANGAN.md) | Latihan dengan orang sungguhan |
| [`ERD.md`](ERD.md) | Skema basis data |
| [`PARAMETER-PERATURAN.md`](PARAMETER-PERATURAN.md) | Setelan peraturan per turnamen |
| [`BOILERPLATE-RESOURCE-KEYS.md`](BOILERPLATE-RESOURCE-KEYS.md) | Konvensi RBAC |
| [`BRIEF-DESAIN.md`](BRIEF-DESAIN.md) · [`AUDIT-UIUX.md`](AUDIT-UIUX.md) | Design system |
| [`RENCANA.md`](RENCANA.md) | Rencana dan progres per fase |
