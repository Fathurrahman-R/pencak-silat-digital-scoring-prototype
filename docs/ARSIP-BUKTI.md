# Arsip Bukti Partai

Riwayat penekanan tombol juri (`judge_inputs`) **tidak ikut sinkron
peer-to-peer**. Gelanggang tetangga tidak berkepentingan atas penekanan tombol
mentah gelanggang lain, dan tabel itu sendirian menyumbang sekitar sembilan
puluh persen volume basis data.

Padahal justru barisan itulah yang ditanyakan saat hasil digugat: siapa menekan
apa, pada milidetik keberapa, berapa juri yang sepakat.

Paket arsip adalah satu-satunya salinan bukti itu di luar laptop tempat ia
lahir. Seluruh rancangan di dokumen ini berangkat dari kalimat tersebut.

## Alurnya

```
partai disahkan
      │
      ├─ paket dibekukan  (12 tabel + baris partai, gzip + SHA-256)
      ├─ didorong ke node global        ── gagal? tetap di antrean, tidak
      │                                    membatalkan pengesahan
      ▼
node global menyimpan berkas beku
      │
      └─ membalas checksum yang IA hitung sendiri
             │
             ▼
      gelanggang menandai "diterima"
             │
      (operator menekan Pangkas Riwayat Juri)
             │
             ├─ tanya lagi ke node global, saat itu juga
             ▼
      judge_inputs partai itu dibuang, partai ditandai
```

## Kenapa berkas beku, bukan tabel

Node global juga menerima data yang sama lewat sinkron biasa. Kalau arsip ikut
ditulis ke tabel itu, keduanya saling menimpa dan tidak ada lagi salinan yang
bisa disebut "keadaan saat partai disahkan".

Tabel menjawab **bagaimana keadaannya sekarang**. Berkas menjawab **apa yang
tercatat saat gong terakhir dibunyikan**. Protes menanyakan yang kedua.

Berkas disimpan di `storage/app/arsip/{turnamen}/{partai}-v{versi}.json.gz`.

## Kenapa tidak pernah ditimpa

Partai yang sama bisa dikirim ulang — babak susulan membuka kembali babak yang
sudah ditutup, dan hasilnya berubah. Menimpa berkas lama berarti menghapus
bukti keadaan sebelum perubahan itu, padahal justru perubahan itulah yang
paling mungkin dipersoalkan. Kiriman berikutnya jadi versi baru bernomor, dan
keduanya disimpan.

## Tiga syarat pemangkasan

Pemangkasan **menghapus bukti**. Satu-satunya yang membenarkannya adalah
keyakinan bahwa salinannya benar-benar ada di tempat lain — bukan catatan bahwa
salinannya pernah dikirim.

1. Partai sudah disahkan.
2. Arsipnya tercatat diterima.
3. **Node global ditanya langsung, saat itu juga**, dan checksum yang ia
   pegang sama dengan yang dikirim dari sini.

Ditambah satu penjagaan lain: partai yang sedang ditayangkan gelanggang mana
pun tidak disentuh, walau ketiga syarat terpenuhi.

Syarat ketiga yang menentukan. Catatan lokal bisa menyebut "diterima" untuk
berkas yang sesudahnya terhapus, tertimpa, atau tidak pernah benar-benar
tersimpan.

## Hanya judge_inputs

`score_events`, hukuman, verifikasi, dan VAR tetap tinggal di gelanggang.
Papan hasil dan rekap membacanya, dan membuangnya berarti menukar ruang disk
dengan permintaan jaringan ke node global tiap kali panitia membuka hasil
partai lama.

Partai yang riwayatnya sudah dipangkas ditandai
`matches.judge_inputs_dipangkas_pada`, dan panel **menyatakannya**. Riwayat
tanpa penekan pada partai yang dipangkas terbaca sama persis dengan partai yang
nilainya terbit tanpa satu pun juri menekan — dan yang kedua itu keadaan yang
serius.

## Perintah

```bash
php artisan silat:arsip                 # keadaan sekarang: berapa tertunda, berapa siap dipangkas
php artisan silat:arsip --dorong        # kirim ulang yang belum sampai
php artisan silat:arsip --pangkas       # buang riwayat juri yang sudah dikonfirmasi
```

Sapuan `--dorong` juga terjadwal tiap sepuluh menit, tapi hanya kalau
`schedule:work` dijalankan — dan di mesin gelanggang saat hari-H ia tidak
dijalankan. Jalur utamanya tetap dorongan otomatis saat partai disahkan.

`--pangkas` **tidak pernah dijadwalkan**. Ia menghapus bukti; yang menekan
tombolnya harus manusia yang tahu kejuaraannya sedang di titik mana.

## Lencana kesehatan

Halaman **Sinkron Gelanggang** menampilkan tiga angka:

| Metrik | Artinya | Kuning | Merah |
|---|---|---|---|
| Waktu tarikan panel (p95) | Gejala yang dirasakan operator | 500 ms | 900 ms |
| Baris riwayat juri | Sebab yang bisa dipangkas | 150.000 | 300.000 |
| Partai belum terarsip | Dorongan arsip tidak bekerja | 5 | 15 |

Angka-angka ini **diukur, bukan ditebak**. Rancangan awal menetapkan kuning di
300 ms untuk waktu tarikan; pengukuran di atas 100.500 baris menunjukkan
baseline yang **sehat** sudah 182-317 ms. Ambang itu akan menyala kuning sejak
jam pertama hari pertama — dan peringatan yang selalu menyala melatih operator
mengabaikan lencana ini persis saat lencananya mulai benar.

Seluruhnya bisa disetel lewat `config/pemantauan.php` atau `.env`
(`PANTAU_STATE_KUNING`, `PANTAU_BARIS_MERAH`, dan seterusnya).

Metrik ketiga bukan soal kecepatan sama sekali. Tiap angkanya adalah satu
partai yang buktinya cuma ada di satu laptop.
