# Pointer Penampilan Jurus dan Serah-Terima Jadwal Antar Gelanggang

> Status: **disetujui, belum dikerjakan.** Ditulis 7 September 2026.
> Sengaja tidak dipasang menjelang uji lapangan hari itu — lihat §9.

## 1. Masalah

Dua hal, dan keduanya berakar pada satu asumsi yang sudah tidak berlaku.

**Panel Jurus masih beralamat per penampilan.** `admin/turnamen/{t}/jurus/penampilan/{performance}/juri` menyebut satu penampilan di dalam alamatnya. Tanding sudah meninggalkan bentuk ini: alamatnya per gelanggang, dan Pengendali Gelanggang yang memindahkan pointer. Alasannya ditulis di [`PointerPartaiAktif`](../../../app/Support/Gelanggang/PointerPartaiAktif.php) — alamat per partai basi begitu jadwal bergeser, dan yang menanggungnya juri yang harus mengetik ulang alamat di HP-nya di pinggir matras.

Kategori Jurus tidak ikut pindah. Konsekuensinya sama persis: tiap pergantian nomor menuntut setiap juri membuka alamat baru sendiri-sendiri.

**Pertukaran jadwal antar gelanggang belum ada sama sekali.** Panitia yang harus memindahkan satu partai dari Gelanggang A ke B saat ini tidak punya jalan selain menyunting jadwal di node global, lalu menunggu kedua gelanggang menarik. Di tengah acara, itu berarti keluar dari panel kendali ke layar lain — dan pada pemasangan multi-node, mustahil dilakukan kalau node global sedang tidak terjangkau.

**Satu lubang yang ditemukan saat merancang ini.** `arenas` terdaftar `GLOBAL` di [`PetaSinkron`](../../../app/Support/Sinkron/PetaSinkron.php) — hanya node global yang boleh menulisnya. Tapi `arenas.active_match_id` justru ditulis node gelanggang, dan [`PenerapPaket`](../../../app/Support/Sinkron/PenerapPaket.php) menerapkan tiap baris sebagai `upsert(..., array_keys($data))`: seluruh kolom ditimpa, tanpa penyaringan. Begitu node global menyunting satu gelanggang apa pun — ganti nama, nonaktifkan — penarikan berikutnya membawa `active_match_id` versi global, yang kosong, dan **pointer gelanggang yang sedang bertanding ikut terhapus.**

Lubang itu ada sekarang, di mekanisme yang hendak diperluas. Rancangan ini menutupnya sebagai bagian dari pekerjaan, bukan sebagai catatan menyusul.

## 2. Keputusan yang sudah diambil

| Pertanyaan | Keputusan |
|---|---|
| Satu pointer per gelanggang atau dua? | **Satu.** Gelanggang menayangkan partai Tanding *atau* penampilan Jurus, tidak pernah keduanya |
| Siapa berwenang memindahkan antar gelanggang? | **Pemilik melepas, penerima mengadopsi.** Bekerja tanpa node global |
| Perlu persetujuan penerima? | **Ya, dua tangan.** Pengendali penerima menekan "Ambil" sebelum baris itu masuk jadwalnya |
| Di mana pointer disimpan? | **Tabel sendiri**, keluar dari `arenas` |

Alasan "dua tangan" bukan kesopanan melainkan bentuk kegagalannya: tanpa persetujuan, kekeliruan baru ketahuan setelah partai dipanggil dan pesilatnya berdiri di matras yang salah. Dengan persetujuan, satu-satunya kegagalan yang mungkin adalah baris yang terlihat menggantung di layar — dan itu terbaca sebelum ada yang bergerak.

## 3. Skema

### 3.1 `arena_tayang` — pointer, golongan LOKAL

```
id                ULID          lahir di gelanggang
arena_id          FK arenas     UNIQUE, satu baris per gelanggang
tayang_type       enum          'tanding' | 'jurus'   nullable
tayang_id         unsignedBig   id matches / jurus_performances, nullable
disetel_pada      timestamp     nullable
disetel_oleh      FK users      nullable
```

Pointer kosong ditulis sebagai `tayang_type`/`tayang_id` `NULL`, **bukan** dengan menghapus barisnya: "Kosongkan gelanggang" adalah tindakan sadar yang jejaknya perlu tersimpan di `disetel_oleh`.

`tayang_id` tanpa foreign key — ia menunjuk dua tabel. Keutuhannya dijaga aplikasi, dengan pola yang sama seperti `auditable_id` di `audit_logs`.

`matches` dan `jurus_performances` sama-sama auto-increment (keduanya disisipkan node global; hanya empat belas tabel yang lahir di gelanggang yang memakai ULID), jadi satu kolom integer cukup untuk keduanya.

**Migrasi memindahkan `arenas.active_match_id` ke tabel ini lalu membuang ketiga kolomnya** (`active_match_id`, `active_match_set_at`, `active_match_set_by`). Satu baris per gelanggang; ringan, dan aman dijalankan di basis data berisi.

### 3.2 `serah_jadwal` — setengah catatan milik pelepas, golongan LOKAL

```
id                ULID
baris_type        enum          'tanding' | 'jurus'
baris_id          unsignedBig
dari_arena_id     FK arenas     penentu kepemilikan baris INI
ke_arena_id       FK arenas
dilepas_pada      timestamp
dilepas_oleh      FK users
alasan            string        nullable
dibatalkan_pada   timestamp     nullable
dibatalkan_oleh   FK users      nullable
```

### 3.3 `adopsi_jadwal` — setengah catatan milik penerima, golongan LOKAL

```
id                ULID
serah_id          ULID          menunjuk serah_jadwal.id, tanpa FK (lintas node)
arena_id          FK arenas     penentu kepemilikan baris INI
diambil_pada      timestamp
diambil_oleh      FK users
```

Tanpa foreign key ke `serah_jadwal` dengan sengaja: kedua baris lahir di node berbeda dan tiba lewat penarikan yang urutannya tidak dijamin. Adopsi yang tiba lebih dulu daripada serahnya harus tetap tersimpan, bukan ditolak basis data.

## 4. Kenapa dua setengah-catatan

Serah-terima menyentuh `arena_id` pada `matches`/`jurus_performances` — kolom yang menentukan kepemilikan. Kalau A melepas lalu B mengubah `arena_id` jadi miliknya, B menulis baris yang **belum** jadi miliknya pada saat ia menulis, dan aturan satu penulis runtuh persis di titik yang dibuat untuk melindunginya.

Dua setengah-catatan menghindarinya: tiap node hanya pernah menulis barisnya sendiri.

```
Gelanggang A                          Gelanggang B
─────────────                         ─────────────
tulis serah_jadwal                    (menarik)
  dari=A ke=B                    →    lihat "menunggu diambil"
berhenti menayangkannya                     │
                                      tulis adopsi_jadwal
                                        serah_id=…, arena=B
                                            │
                                      tulis matches.arena_id = B
                                        ← sah: adopsinya yang
                                          menyerahkan hak tulis
(menarik)                        ←
lihat baris itu bukan lagi miliknya
```

Urutannya bisa direkonstruksi di node mana pun tanpa membandingkan jam laptop — hanya dengan membaca kedua catatan.

**Kepemilikan efektif** = `arena_id` baris itu, kecuali ada `adopsi_jadwal` yang menunjuk `serah_jadwal` yang belum dibatalkan dan yang `baris_id`-nya sama. Fungsi ini tinggal di `App\Support\Sinkron\Kepemilikan`, bersama pertanyaan kepemilikan lainnya.

## 5. Perubahan pada sinkron

**`PetaSinkron::LOKAL` menerima tiga tabel baru.** Rantai relasinya berhenti langsung di baris itu sendiri — ketiganya punya `arena_id`. `Kepemilikan::telusuriArena()` sekarang selalu melompat ke tabel lain lebih dulu; ia perlu satu cabang tambahan: rantai kosong berarti "`arena_id` ada di baris ini". Itu tiga baris kode dan menghapus keharusan menulis rantai palsu.

**`arenas` tetap GLOBAL, dan sekarang benar-benar hanya berisi kolom global.** Lubang §1 tertutup bukan dengan pengecualian melainkan dengan memindahkan kolomnya keluar — tidak ada daftar kolom-yang-dikecualikan yang harus diingat orang berikutnya.

**`matches` dan `jurus_performances` tetap PENGHUBUNG**, dengan satu tambahan pada kontraknya: `arena_id` boleh ditulis node gelanggang **hanya** sebagai akibat adopsi yang tercatat. Ditulis di komentar `PetaSinkron`, dan dijaga uji.

## 6. Alur kendali dan panel

### 6.1 Pointer Jurus

`PointerPartaiAktif` menjadi `PointerTayang`, satu-satunya penulis `arena_tayang`, dengan dua jalan masuk: `tunjukPartai()` dan `tunjukPenampilan()`. Penjagaan yang sudah ada — hulu bagan lewat `KesiapanHulu`, partai belum diakhiri, pindah paksa — berlaku sama untuk Jurus, kecuali `KesiapanHulu` yang untuk Jurus membaca `jurus_bracket_slots`.

Panel Jurus pindah dari alamat per penampilan ke alamat per gelanggang:

```
admin/turnamen/{t}/gelanggang/{arena}/panel/jurus-juri
admin/turnamen/{t}/gelanggang/{arena}/panel/jurus-operator
```

Alamat lama **tetap hidup**, karena nomor Jurus yang belum dijadwalkan ke gelanggang mana pun tidak punya alamat gelanggang untuk diikuti — dan itu keadaan normal di kejuaraan kecil yang menjalankan Jurus tanpa membaginya ke matras.

### 6.2 Serah-terima, dari panel kendali

Di panel kendali, tiap baris jadwal mendapat satu tindakan **"Pindahkan ke gelanggang…"**. Yang muncul daftar gelanggang aktif; memilih salah satunya menulis `serah_jadwal` dan baris itu langsung berpindah ke bagian **"Dilepas, menunggu diambil"** di panel yang sama. Tidak ada perpindahan layar.

Pengendali penerima melihat bagian **"Ditawarkan dari gelanggang lain"** di panelnya, dengan tombol **Ambil**. Sebelum diambil, ia tidak bisa ditayangkan — ditolak dengan pesan yang menyebut gelanggang asalnya, pola yang sama dengan penjagaan hulu yang sudah ada.

Pelepas bisa **membatalkan** selama belum diambil. Setelah diambil, jalan kembalinya adalah serah-terima baru ke arah sebaliknya — bukan pembatalan, karena baris itu sudah bukan miliknya.

## 7. Latensi: nol query tambahan di jalur panas

Endpoint `panel/state` ditarik tiap panel yang terbuka, tiap ada siaran, ditambah sekali tiap dua puluh detik selama babak berjalan. Ia tidak boleh bertambah berat.

- **Pointer dibaca satu query, sama seperti sekarang.** `arenas.active_match_id` hari ini ikut terbaca bersama baris arena. Setelah pindah, `arena_tayang` diambil lewat `with('tayang')` pada baris arena yang memang sudah dimuat — satu query eager-load untuk seluruh gelanggang yang diminta, bukan satu per gelanggang.
- **Serah-terima TIDAK dibaca di `state` sama sekali.** Daftar "menunggu diambil" dan "ditawarkan" hanya dipakai panel kendali, dan diambil pada endpoint kendali yang ditarik jauh lebih jarang. Menaruhnya di `state` berarti dua query tambahan pada tiap tekanan tombol juri untuk data yang berubah beberapa kali sehari.
- **Indeks**: `arena_tayang(arena_id)` unik sudah cukup. `serah_jadwal` diberi indeks `(ke_arena_id, dibatalkan_pada)` dan `(baris_type, baris_id)` — keduanya melayani daftar pendek, bukan jalur panas.
- Uji penghitung query yang sudah ada di `PanelGelanggangTest` diperluas untuk mengunci jumlahnya: **`state` tidak boleh bertambah satu query pun.**

## 8. Pengujian

Ditulis lebih dulu, dan yang berikut ini yang menentukan rancangan ini benar:

1. Pointer Jurus berpindah dan panel juri Jurus mengikuti tanpa memuat ulang alamat.
2. Satu gelanggang tidak bisa memegang partai Tanding dan penampilan Jurus sekaligus.
3. `state` tidak bertambah query setelah pointer pindah tabel (penghitung query).
4. Melepas menghentikan penayangan di pelepas seketika.
5. Penerima menolak menayangkan sebelum mengambil, dengan pesan yang menyebut gelanggang asal.
6. Setelah adopsi, `Kepemilikan` menyatakan baris itu milik penerima — **di kedua node**, diuji dengan mensimulasikan dua konfigurasi `SINKRON_ARENA`.
7. Pembatalan sebelum adopsi mengembalikan baris ke pelepas; pembatalan sesudah adopsi ditolak.
8. Node global menyunting `arenas` lalu gelanggang menarik: **pointer tayang tidak berubah.** Inilah uji yang menutup lubang §1, dan ia harus gagal pada kode hari ini.

## 9. Kenapa tidak dikerjakan sekarang

Ditulis pada hari yang ada uji lapangannya. Rancangan ini menyentuh peta kepemilikan sinkron, membuang tiga kolom dari tabel yang dipakai tiap panel, dan mengubah alamat panel Jurus. Tidak satu pun dari itu bisa diverifikasi cukup dalam beberapa jam, dan kegagalannya muncul di tempat yang paling mahal: gelanggang yang tidak bisa menayangkan partai.

Urutan yang dianjurkan sesudah uji lapangan:

1. Migrasi `arena_tayang` + uji §8.3 dan §8.8 — menutup lubang yang sudah ada, tanpa fitur baru.
2. Pointer Jurus (§6.1).
3. Serah-terima (§6.2), yang bergantung pada keduanya.

Tiap langkah berdiri sendiri dan bisa dilepas terpisah.
