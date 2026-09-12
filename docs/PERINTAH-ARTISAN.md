# Perintah Artisan

Seluruh perintah baris perintah yang dipasang proyek ini, lengkap dengan
opsinya, kapan dipakai, dan apa yang terjadi kalau salah pakai.

Dijalankan dari akar proyek, di mesin yang basis datanya ingin disentuh:

```bash
php artisan <perintah> [opsi]
```

Dua kelompok. `silat:*` mengurus jalannya kejuaraan; `resource:*` mengurus
peta izin. Perintah bawaan Laravel (`migrate`, `db:seed`, `queue:work`,
`reverb:start`, `config:clear`) tidak diulang di sini — pemakaiannya ada di
[INSTALASI-LAN.md](INSTALASI-LAN.md) dan [PANDUAN-SISTEM.md](PANDUAN-SISTEM.md).

| Perintah | Untuk apa | Aman di hari-H? |
|---|---|---|
| [`silat:simulasi`](#silatsimulasi) | Menyiapkan kejuaraan siap-uji | **Tidak** — menulis data kejuaraan |
| [`silat:kesehatan`](#silatkesehatan) | Memeriksa kesehatan gelanggang | Ya, hanya membaca |
| [`silat:arsip`](#silatarsip) | Mengirim arsip bukti, memangkas riwayat juri | Ya, `--pangkas` di jeda |
| [`silat:snapshot-skor`](#silatsnapshot-skor) | Menyusun ulang snapshot skor | Ya, tapi lihat catatannya |
| [`silat:pindah-pengendali`](#silatpindah-pengendali) | Memberi peran Pengendali Gelanggang | Ya, idempoten |
| [`silat:beban`](#silatbeban) | Menumpuk riwayat palsu untuk mengukur query | **Tidak pernah** |
| [`resource:sync`](#resourcesync) | Membuat permission untuk resource key baru | Ya |
| [`resource:keys`](#resourcekeys) | Membuat ulang berkas konstanta resource key | Ya (waktu ngoding) |
| [`resource:list`](#resourcelist) | Menampilkan peta resource key → permission | Ya, hanya membaca |
| [`resource:doctor`](#resourcedoctor) | Mencari resource key dan permission yang bermasalah | Ya, hanya membaca |

---

## `silat:simulasi`

Menyiapkan satu kejuaraan yang seluruh tahapannya sudah selesai — akun tiap
peran, tarif, peserta, tagihan lunas, bagan terkunci, jadwal, dan aparat —
supaya pengujian manual bisa langsung masuk ke panel gelanggang.

```bash
php artisan silat:simulasi [--skala=kecil|sedang|besar] [--tanpa-bagan] [--reset]
```

| Opsi | Bawaan | Artinya |
|---|---|---|
| `--skala=` | `kecil` | Ukuran kejuaraan yang disusun |
| `--tanpa-bagan` | mati | Berhenti sesudah pendaftaran lunas dan sah — bagan dan jadwal **tidak** disusun |
| `--reset` | mati | Hapus permanen kejuaraan berskala sama lebih dulu |

### Ketiga skala

| Skala | Kontingen | Peserta | Gelanggang | Untuk apa |
|---|---|---|---|---|
| `kecil` | 10 | ±100 pesilat, 5 kelas | 2 (A, B) | Menelusuri satu partai dari awal sampai pengesahan |
| `sedang` | 6 | 2 per kelas tanding, 2 per nomor jurus — seluruh kelas dan seluruh nomor | 2 | Menguji sistem seukuran kejuaraan kabupaten |
| `besar` | 12 | 12 per kelas tanding, 6 per nomor jurus (±3.000 pesilat) | 3 | Menguji beban, penjadwalan, dan bagan seukuran kejuaraan provinsi |

Tiap skala punya slug sendiri (`simulasi`, `simulasi-sedang`,
`simulasi-besar`), jadi **ketiganya boleh berdiri bersamaan** di satu basis
data: panitia bisa berlatih di data besar tanpa membuang kejuaraan kecil yang
sedang dipakai menelusuri satu partai.

### Akun yang dibuat

Seluruhnya berdomain `@silat.test`, kata sandi **`password`**:

| Email | Peran |
|---|---|
| `ketua@silat.test` | Ketua Pertandingan |
| `pengawas@silat.test` | Ketua Pertandingan (kursi Pengawas/Dewan Wasit Juri) |
| `komisi@silat.test` | Ketua Pertandingan (kursi Komisi Protes) |
| `sekretariat@silat.test` | Operator IT |
| `operator@silat.test`, `operator2@silat.test`, … | Operator IT (satu per gelanggang) |
| `pengendali1@silat.test`, `pengendali2@silat.test`, … | Pengendali Gelanggang (satu per gelanggang) |
| `wasit1@silat.test`, `wasit2@silat.test`, … | Ketua Pertandingan (kursi Wasit, satu per gelanggang) |
| `juri1@silat.test` … `juri{N}@silat.test` | Juri (tiga per gelanggang) |
| `official1@silat.test` … | Official Kontingen (satu per kontingen) |

Operator IT dan Pengendali Gelanggang sengaja **kursi terpisah**, bukan satu
orang merangkap: timer dan pergantian jadwal sudah pindah dari Operator IT ke
Pengendali Gelanggang, dan simulasi yang menggabungkan keduanya melatih
pembagian tugas yang tidak akan dipakai di hari-H.

### Yang perlu diketahui

- **Tanpa `--reset`, perintah menolak berjalan** kalau kejuaraan berskala sama
  sudah ada. Ia tidak menimpa; ia berhenti dan memberi tahu.
- **`--reset` menghapus permanen** kejuaraan itu beserta seluruh peserta,
  tagihan, bagan, dan hasilnya (`forceDelete`). Di terminal interaktif ia
  bertanya dulu; di skrip non-interaktif ia langsung jalan.
- **`--tanpa-bagan` hanya untuk `sedang` dan `besar`.** Skala kecil memang
  disiapkan untuk menelusuri partai yang sudah terjadwal, jadi kombinasinya
  ditolak. Pakai saklar ini kalau yang mau diuji justru **penyusunan bagan**
  dari nol — termasuk memilih mode gugur atau pemasalan.

---

## `silat:kesehatan`

Ketiga metrik kesehatan gelanggang, dibaca tanpa membuka peramban. Ada supaya
pemeriksaan pra-hari-H tidak menuntut login lima kali di lima laptop.

```bash
php artisan silat:kesehatan
```

| Metrik | Kuning | Merah | Artinya kalau menyala |
|---|---|---|---|
| Waktu tarikan state panel (p95) | 500 ms | 900 ms | Panel mulai tertinggal dari gelanggang |
| Baris `judge_inputs` | 150.000 | 300.000 | Riwayat menumpuk — jalankan `silat:arsip --pangkas` |
| Partai belum terarsip | 5 | 15 | Node global tidak terjangkau — periksa jaringan, lalu `silat:arsip --dorong` |

Ambangnya bisa digeser lewat `.env`: `PANTAU_STATE_KUNING`,
`PANTAU_STATE_MERAH`, `PANTAU_BARIS_KUNING`, `PANTAU_BARIS_MERAH`,
`PANTAU_ARSIP_KUNING`, `PANTAU_ARSIP_MERAH`. Angka bawaannya **diukur**, bukan
ditebak — dari basis data 100.500 baris `judge_inputs` yang dilayani nginx
dengan delapan proses php-cgi.

**Exit code ikut warnanya**: gagal (`1`) saat merah, berhasil (`0`) saat kuning
maupun hijau. Itu yang membuatnya bisa dipakai sebagai penjaga di skrip
penyalaan server, bukan cuma sebagai tampilan —
`scripts/server/siapkan-gelanggang.ps1` memakainya begitu.

---

## `silat:arsip`

Dua pekerjaan yang berbeda sifatnya, sengaja dipisah jadi dua opsi: yang
pertama **menambah** salinan bukti, yang kedua **menghapus** bukti.
Menyatukannya berarti satu perintah salah ketik bisa membuang riwayat yang
baru saja gagal dikirim.

```bash
php artisan silat:arsip                     # laporan keadaan, tidak mengubah apa pun
php artisan silat:arsip --dorong            # kirim ulang arsip yang belum sampai
php artisan silat:arsip --pangkas           # buang riwayat juri yang arsipnya sudah dikonfirmasi
php artisan silat:arsip --dorong --batas=200
```

| Opsi | Bawaan | Artinya |
|---|---|---|
| *(tanpa opsi)* | — | Cetak keadaan: berapa arsip tertunda, berapa diterima, berapa siap dipangkas |
| `--dorong` | mati | Kirim ulang arsip partai yang belum sampai ke node global |
| `--pangkas` | mati | Buang riwayat juri partai yang arsipnya **sudah dikonfirmasi** node global |
| `--batas=` | `50` | Berapa partai yang diproses sekali jalan |

`--pangkas` tidak pernah menyentuh partai yang arsipnya belum diterima. Partai
yang dilewati disebutkan **satu per satu beserta alasannya**, bukan diringkas
jadi angka — node global tidak terjangkau menuntut tindakan yang berbeda dari
checksum yang tidak cocok.

Rinciannya di [ARSIP-BUKTI.md](ARSIP-BUKTI.md).

---

## `silat:snapshot-skor`

Menyusun ulang snapshot skor partai dari `score_events` dan `penalties`.

```bash
php artisan silat:snapshot-skor
php artisan silat:snapshot-skor --bangun-ulang
php artisan silat:snapshot-skor --turnamen=5 --bangun-ulang
```

| Opsi | Bawaan | Artinya |
|---|---|---|
| `--bangun-ulang` | mati | Hitung ulang **seluruhnya**, bukan cuma partai yang snapshot-nya kosong |
| `--turnamen=` | semua | Batasi ke satu kejuaraan (id-nya) |

Snapshot membatalkan dirinya sendiri tiap nilai berubah, jadi **dalam keadaan
normal perintah ini tidak dibutuhkan**. Ia ada untuk keadaan yang tidak normal:
baris nilai yang disunting langsung di basis data, pemulihan dari cadangan,
atau kecurigaan bahwa angka di layar tidak lagi cocok dengan bahannya.

Membuang lebih dulu, baru menghitung. Kalau prosesnya terputus di tengah, yang
tertinggal adalah partai **tanpa** snapshot — dan partai tanpa snapshot dihitung
ulang saat dibaca, jadi selalu benar. Menghitung lebih dulu akan meninggalkan
campuran snapshot lama dan baru yang tidak bisa dibedakan.

---

## `silat:pindah-pengendali`

Memindahkan kendali gelanggang dari Operator IT ke Pengendali Gelanggang.
**Dijalankan sekali sesudah memasang pembaruan yang memperkenalkan peran itu.**

```bash
php artisan silat:pindah-pengendali
```

Tanpa perintah ini, di hari kode baru terpasang **tidak ada satu akun pun yang
boleh menjalankan timer**: `operator-it` baru saja kehilangan wewenang itu dan
`pengendali-gelanggang` belum dipegang siapa pun. Seluruh gelanggang berhenti,
dan yang muncul di panel cuma 403 tanpa penjelasan.

Yang dikerjakannya, berurutan:

1. Menyegarkan resource key dan peran (`SilatResourceSeeder`, `SilatRoleSeeder`).
2. Memberi peran Pengendali Gelanggang kepada **setiap** Operator IT. Peran
   lamanya tidak dicabut — orang yang sama masih menjalankan papan tampilan dan
   perangkat siaran.
3. Menyalin penugasan gelanggang yang sudah ada.
4. Membuang cache izin Spatie. Tanpa langkah ini perintahnya meninggalkan
   persis gejala yang dijanjikannya sembuh: peran sudah tertulis di basis data,
   tapi gate masih membaca peta izin lama.
5. Melaporkan gelanggang yang **masih** belum punya pengendali.

Idempoten — aman dijalankan berkali-kali.

---

## `silat:beban`

Menumpuk riwayat penilaian sebanyak satu hari pertandingan sungguhan, untuk
mengukur perilaku query.

```bash
php artisan silat:beban --partai=200 --input=500
php artisan silat:beban --bersihkan
```

| Opsi | Bawaan | Artinya |
|---|---|---|
| `--partai=` | `200` | Berapa partai yang diberi riwayat |
| `--input=` | `500` | Rata-rata tekanan tombol juri per partai |
| `--bersihkan` | mati | Buang riwayat buatan lebih dulu |

> **Bukan untuk mesin gelanggang.** Ia menulis ratusan ribu baris riwayat palsu
> dan menolak berjalan di `production`.

Alasan keberadaannya: basis data pengembangan berisi delapan baris
`judge_inputs`, dan di atas angka itu `EXPLAIN` selalu menjawab hal yang sama
dan selalu terlihat baik — MySQL memindai seluruh tabel karena memang lebih
murah. Keputusan index apa pun yang diambil dari data sekecil itu diambil dari
data yang tidak pernah membantah apa pun.

Baris buatannya ditandai `rejected_reason = 'beban-uji'`, jadi `--bersihkan`
menyasar tepat baris yang perintah ini buat — bukan mengosongkan tabel dan ikut
membawa riwayat sungguhan yang kebetulan ada di mesin yang sama.

Butuh minimal tiga user dan minimal satu partai; jalankan `silat:simulasi`
lebih dulu.

---

## `resource:sync`

Membuatkan permission untuk setiap resource key yang belum dipetakan.

```bash
php artisan resource:sync
```

Dijalankan setelah menambah resource key baru di seeder. Aman diulang.

---

## `resource:keys`

Membuat ulang berkas konstanta resource key dari basis data — yang dipakai
`rk()` di kode supaya key-nya tidak diketik sebagai string bebas.

```bash
php artisan resource:keys
php artisan resource:keys --check    # hanya periksa, tidak menulis
```

| Opsi | Artinya |
|---|---|
| `--check` | Hanya periksa apakah berkasnya sudah mutakhir. Cocok dipakai di CI |

---

## `resource:list`

Menampilkan seluruh resource key beserta permission yang terpasang dan berapa
role memakainya.

```bash
php artisan resource:list
php artisan resource:list --group=silat
php artisan resource:list --unmapped
```

| Opsi | Artinya |
|---|---|
| `--group=` | Saring berdasarkan grup |
| `--unmapped` | Hanya key yang **belum** punya permission |

---

## `resource:doctor`

Memeriksa resource key, permission, dan pemetaan yang bermasalah — key tanpa
permission, permission yatim, pemetaan yang menunjuk ke tempat yang tidak ada.

```bash
php artisan resource:doctor
```

Latar belakang peta izin ini ada di
[BOILERPLATE-RESOURCE-KEYS.md](BOILERPLATE-RESOURCE-KEYS.md).

---

## Urutan yang biasa dipakai

**Menyiapkan laptop untuk uji coba:**

```bash
php artisan migrate --seed
php artisan silat:simulasi --skala=kecil
php artisan silat:kesehatan
```

**Menyiapkan node gelanggang baru** (jangan diseed — lihat
[MULTI-GELANGGANG.md](MULTI-GELANGGANG.md)):

```bash
php artisan migrate
.\scripts\server\siapkan-gelanggang.ps1 -Peran gelanggang -Arena A -Token RAHASIA -Peer 'global|http://<ip-global>:8000'
# lalu buka /pemasangan di peramban
```

**Sesudah memasang pembaruan di mesin yang sudah berisi data:**

```bash
php artisan migrate
php artisan silat:pindah-pengendali
php artisan resource:doctor
php artisan config:clear
php artisan silat:kesehatan
```

**Di jeda antar sesi, saat lencana kesehatan menguning:**

```bash
php artisan silat:arsip                 # lihat keadaannya dulu
php artisan silat:arsip --dorong
php artisan silat:arsip --pangkas
```
