# Pembatalan Nilai Per Juri

> Status: **rancangan, belum dikerjakan.** Ditulis 7 September 2026.
> Klasifikasi: arsitektural — ia menyentuh invarian `judge_inputs` yang tidak
> pernah diubah, semantik konsensus, peta sinkron, dan paket arsip bukti.

## 1. Masalah

Pembatalan hari ini bersifat **semua-atau-tidak sama sekali**. Dewan Wasit Juri
membatalkan satu `score_event` — nilai yang sudah terbit — lewat kolom
`voided_at`/`voided_by`/`void_reason` pada barisnya
([`PartaiScoringController::batalkanNilai`](../../../app/Http/Controllers/Admin/PartaiScoringController.php#L734)).
Tidak ada jalan untuk menarik **satu tekanan juri** dari nilai itu.

Dua keadaan yang tidak punya jawaban benar sekarang:

**Satu juri keliru, dua juri benar.** Nilai terbit karena dua dari tiga juri
sepakat. Salah satunya kemudian mengakui menekan sudut atau teknik yang keliru.
Pilihan yang tersedia cuma dua, dan keduanya salah: membatalkan seluruh nilai —
membuang tekanan juri kedua yang sah — atau membiarkan nilai berdiri di atas
kesepakatan yang sudah tidak ada.

**Tekanan yang belum terbit.** Juri menekan sudut yang keliru; tekanannya
tersimpan tapi belum membentuk nilai. Tidak ada cara menariknya, dan ia tetap
hidup di dalam jendela konsensus: tekanan sah berikutnya dari juri lain bisa
berpasangan dengannya dan menerbitkan nilai yang tidak pernah dimaksudkan
siapa pun.

Keduanya bukan kasus langka. Justru inilah yang ditanyakan saat hasil digugat,
dan berita acara sudah mencantumkan **siapa** yang menekan tiap nilai
([`PartaiScoringController`](../../../app/Http/Controllers/Admin/PartaiScoringController.php#L186))
— jadi dokumen resminya sudah menyebut per juri, sementara koreksinya tidak
bisa.

## 2. Invarian yang membentuk seluruh rancangan

> `judge_inputs` tidak pernah diubah atau dihapus.

Ini invarian nomor satu repo ini. Menambahkan `voided_at` ke `judge_inputs`
berarti menyunting baris yang seluruh nilai buktinya berasal dari fakta bahwa ia
tidak pernah disunting.

Karena itu pembatalan per juri **tidak menyentuh `judge_inputs` sama sekali**.
Ia dicatat sebagai baris di tabel lain, dan pembacaan konsensus menyaringnya.

Perhatikan bedanya dengan `score_events`, yang memang memakai kolom pembatal
pada barisnya sendiri: `score_events` adalah **kesimpulan**, dan kesimpulan
boleh dinyatakan batal. `judge_inputs` adalah **pengamatan**, dan pengamatan
tidak pernah dibatalkan — yang dibatalkan penggunaannya.

## 3. Skema

### `pembatalan_input_juri` — golongan LOKAL

```
id                ULID          lahir di gelanggang
match_id          FK matches    penentu kepemilikan sinkron DAN kunci arsip
judge_input_id    FK judge_inputs  UNIQUE
dibatalkan_pada   timestamp
dibatalkan_oleh   FK users
alasan            string        wajib, tidak boleh kosong
```

**`match_id` disimpan di baris ini, tidak ditelusuri lewat `judge_inputs`**, dan
itu bukan denormalisasi malas. `judge_inputs` **tidak ikut sinkron sama sekali**
— ia mengalir satu arah lewat paket arsip
([`PetaSinkron`](../../../app/Support/Sinkron/PetaSinkron.php)). Rantai
kepemilikan yang melewatinya tidak akan pernah bertemu gelanggang di node
penerima. Rantainya karena itu `[['matches', 'match_id']]`, sama seperti
`score_events`.

`alasan` wajib, mengikuti `batalkanNilai` yang sudah ada: koreksi tanpa alasan
adalah koreksi yang tidak bisa dipertanggungjawabkan saat digugat.

## 4. Semantik

### 4.1 Tekanan yang belum terbit

Tekanan tanpa `score_event_id` dibatalkan → ia berhenti ikut dihitung di jendela
konsensus mana pun sesudah itu. Tidak ada akibat lain.

### 4.2 Tekanan yang sudah membentuk nilai

Ini bagian yang menentukan. Membatalkan satu tekanan **memicu evaluasi ulang
satu `score_event`**, bukan seluruh partai:

```
batalkan tekanan juri X pada score_event S
      │
      ▼
hitung ulang juri BERBEDA yang tekanannya masih sah pada S
      │
      ├─ masih ≥ ambang_sepakat  →  S tetap berdiri
      │                             (kesepakatan masih ada tanpa X)
      │
      └─ kurang dari ambang      →  S dibatalkan, dengan void_reason yang
                                    MENYEBUT pembatalan tekanan itu
```

Tiga aturan yang tidak boleh dilanggar:

- **Tidak pernah menerbitkan nilai baru.** Evaluasi ulang hanya bisa
  menjatuhkan, tidak pernah menaikkan. Nilai yang lahir dari evaluasi ulang
  berjam-jam sesudah kejadiannya adalah nilai yang tidak pernah dilihat siapa
  pun di matras.
- **Tidak pernah menghidupkan kembali** `score_event` yang sudah dibatalkan.
- **Tidak menyentuh `score_event` lain.** Jendela konsensus adalah peristiwa
  saat itu; menghitung ulang seluruh partai berarti menyusun ulang pertandingan
  yang sudah selesai dari data yang tidak lagi punya urutan waktu yang sama.

`judge_inputs.score_event_id` **dibiarkan apa adanya**. Ia mencatat bahwa
tekanan itu dulu ikut membentuk nilai — dan itu benar, terjadi, dan bagian dari
riwayat.

### 4.3 Siapa yang berwenang

**Dewan Wasit Juri**, lewat `hasil-partai` — peran yang sama yang sudah
membatalkan nilai dan hukuman.

**Bukan juri yang bersangkutan.** Juri yang bisa menarik tekanannya sendiri bisa
menulis ulang apa yang terjadi, dan Pasal 13 menempatkan peninjauan penilaian
pada Dewan Wasit Juri, bukan pada juri itu sendiri.

Setelah hasil disahkan, pembatalan mengikuti aturan yang sudah berlaku untuk
`batalkanNilai`: jalurnya protes manajer, bukan tombol di panel.

## 5. Yang ikut berubah

| Berkas | Perubahan |
|---|---|
| `ConsensusEvaluator` | Query juri-berbeda menyaring tekanan yang dibatalkan |
| `TandingScoreCalculator` | Tidak berubah — ia membaca `score_events`, dan yang jatuh sudah bertanda `voided_at` |
| `SnapshotSkor` | Tidak berubah — observernya sudah membuang snapshot saat `score_events` berubah |
| `PetaSinkron::LOKAL` | `'pembatalan_input_juri' => [['matches', 'match_id']]` |
| `PaketArsip` | Tabel baru masuk daftar, berkunci `match_id` |
| `PemangkasRiwayatJuri` | **Tidak** ikut memangkas — lihat §6 |
| Panel Dewan Wasit Juri | Tombol per juri pada tiap nilai yang punya tekanan |

## 6. Pemangkasan riwayat: pembatalan TIDAK ikut dipangkas

`PemangkasRiwayatJuri` menghapus `judge_inputs` setelah arsipnya dikonfirmasi
node global. Baris pembatalan **tidak ikut**, dan ini keputusan sadar.

Sesudah pemangkasan, yang tersisa di basis data adalah `score_events` — termasuk
yang dibatalkan, beserta `void_reason`-nya. Kalau baris pembatalan ikut terhapus,
alasan itu menunjuk sesuatu yang sudah tidak ada, dan pertanyaan "kenapa nilai
ini batal" hanya bisa dijawab dengan membongkar berkas arsip beku.

Volumenya tidak jadi soal: pembatalan per juri adalah peristiwa langka, sementara
`judge_inputs` menyumbang sembilan puluh persen volume basis data. Yang dipangkas
tetap yang besar.

Konsekuensinya satu baris pembatalan bisa menunjuk `judge_input_id` yang sudah
dihapus. Foreign key-nya karena itu `nullOnDelete`, dan `judge_input_id` boleh
kosong — barisnya tetap bermakna lewat `match_id` dan alasannya.

## 7. Rupa panelnya

Panel Dewan Wasit Juri sudah menampilkan riwayat nilai. Tiap nilai yang lahir
dari tekanan juri mendapat daftar penekannya — data itu sudah ada, dipakai
berita acara — dan tiap nama mendapat satu tindakan **"Batalkan tekanan ini"**
dengan alasan wajib.

Yang harus terlihat sebelum ditekan, bukan sesudah: **apakah nilai ini ikut
jatuh**. Dua tombol yang bentuknya sama tapi akibatnya berbeda jauh — satu
menyisakan nilai, satu menjatuhkannya — adalah dua tombol yang suatu saat
tertukar di tengah kejuaraan. Labelnya karena itu menyebut akibatnya:

- *"Batalkan tekanan Juri 2 — nilai tetap berdiri (masih 2 juri sepakat)"*
- *"Batalkan tekanan Juri 2 — nilai ikut batal (tinggal 1 juri)"*

Nilai mutlak jatuhan tidak punya tekanan juri sama sekali; barisnya tidak
menawarkan tindakan ini.

## 8. Pengujian yang menentukan

1. Membatalkan satu tekanan dari tiga yang sepakat: nilai **tetap** berdiri.
2. Membatalkan satu dari dua yang sepakat: nilai **ikut batal**, dan
   `void_reason`-nya menyebut pembatalan itu.
3. `judge_inputs` **tidak berubah satu kolom pun** sesudah pembatalan —
   dibandingkan baris demi baris sebelum dan sesudah.
4. Tekanan yang dibatalkan **tidak ikut** membentuk nilai baru di jendela
   konsensus berikutnya.
5. Evaluasi ulang **tidak pernah** menerbitkan nilai baru, dan tidak pernah
   menghidupkan nilai yang sudah batal.
6. Nilai yang sudah dibatalkan lalu salah satu penekannya dibatalkan: tidak ada
   perubahan, tidak ada galat.
7. Skor partai dan snapshot mengikuti — nilai yang jatuh hilang dari total.
8. Kepemilikan sinkron: baris pembatalan milik gelanggang tempat partainya
   berjalan, diuji dari dua konfigurasi `SINKRON_ARENA`.
9. Paket arsip memuat tabel baru; berita acara menyebut pembatalannya.
10. Sesudah `PemangkasRiwayatJuri` berjalan, baris pembatalan **masih ada** dan
    `void_reason` nilainya masih bisa dibaca.

Uji nomor 3 yang paling menentukan: ia yang menjaga invarian yang membuat
seluruh sistem ini bisa dipercaya saat hasilnya digugat.

## 9. Yang sengaja TIDAK dikerjakan

- **Membatalkan tekanan yang belum terbit lewat UI.** Jendelanya dua detik;
  tidak ada manusia yang sempat menekan tombol di dalamnya. Mekanismenya tetap
  dibangun (§4.1) karena evaluasi ulang membutuhkannya, tapi tanpa layar.
- **Menghitung ulang seluruh partai.** Lihat §4.2.
- **Membatalkan tekanan pada kategori Jurus.** Nilai Jurus bukan konsensus — tiap
  juri menyumbang angkanya sendiri, dan mengoreksinya sudah punya jalur sendiri
  lewat pengurangan dan pembatalan pengurangan.

## 10. Urutan pengerjaan

1. Tabel + model + `ConsensusEvaluator` menyaring yang dibatalkan (uji 1–4).
2. Evaluasi ulang `score_event` (uji 5–7).
3. Sinkron dan arsip (uji 8–10).
4. Rupa panel Dewan Wasit Juri.

Tiga langkah pertama berdiri sendiri dan bisa dilepas tanpa langkah keempat:
sampai panelnya ada, pembatalan hanya bisa lewat perintah — cukup untuk
memperbaiki kejuaraan yang sedang berjalan, dan tidak menambah tombol yang belum
diuji ke layar gelanggang.
