# Satu Gelanggang Satu Laptop

Sampai sekarang seluruh gelanggang dilayani satu mesin Windows
(`docs/INSTALASI-LAN.md`). Dokumen ini menggantikan asumsi itu: tiap
gelanggang berdiri sendiri di laptopnya masing-masing, dan pertukaran datanya
dilakukan sadar lewat tombol.

## Lima mesin, bukan empat

| Peran | Jumlah | Isi |
|---|---|---|
| Node gelanggang | 4 | Laravel + MySQL + Reverb + vMix. Menilai partai gelanggangnya sendiri, berdiri sendiri penuh |
| Node global | 1 | Laravel + MySQL. **Tidak** melayani gelanggang. Satu-satunya penulis data kejuaraan, sekaligus penampung arsip bukti |

Node gelanggang tetap bisa menilai walau seluruh jaringan mati. Yang berhenti
saat LAN putus cuma pertukaran data — bukan pertandingannya.

## Aturan satu penulis

Sinkron peer-to-peer tanpa aturan kepemilikan menuntut jawaban atas pertanyaan
yang tidak punya jawaban benar: kalau dua node mengubah baris yang sama, mana
yang menang? Semua jawabannya membuang pekerjaan seseorang, dan yang paling
sering dipakai — stempel waktu terbaru — membuangnya berdasarkan jam laptop
yang tidak pernah benar-benar sama.

Sistem ini tidak menjawabnya. Ia membuat keadaannya tidak bisa terjadi.

| Golongan | Ditulis oleh | Contoh |
|---|---|---|
| Global | Node global saja | atlet, kontingen, pendaftaran, bagan, jadwal, pengguna, peran |
| Penghubung | Disisipkan node global, diperbarui gelanggang pemiliknya | `matches`, `jurus_performances`, `jurus_battles` |
| Lokal | Gelanggang tempat partainya berjalan | nilai, hukuman, timer, verifikasi, VAR, protes |

Kepemilikan dibandingkan lewat **kode** gelanggang (`A`, `B`), bukan id.
Id auto-increment berbeda antar basis data; membandingkan id berarti node A
mengklaim baris milik B begitu urutan penyisipan di dua basis data kebetulan
berbeda.

## Kunci ULID

Empat belas tabel yang lahir di gelanggang memakai ULID, bukan auto-increment.
Alasannya satu kalimat: penghitung auto-increment tiap basis data mulai dari
satu, jadi gelanggang A dan B akan menerbitkan baris bernomor sama, dan saat
datanya digabungkan tidak ada cara memilih di antara keduanya yang tidak
menghapus catatan sungguhan.

ULID, bukan UUID acak: kunci utama menentukan urutan fisik baris di InnoDB,
dan ULID yang urut mengikuti waktu membuat sisipan tetap jatuh di ujung —
persis seperti auto-increment.

`matches` **tidak** ikut pindah. Hanya node global yang menyisipkannya; node
gelanggang cuma memperbaruinya.

> **Sebelum hari-H.** Konversi 100.500 baris `judge_inputs` memakan **9 menit
> 49 detik**. Pemasangan baru di basis data kosong tidak terpengaruh — yang
> lama hanya basis data yang sudah berisi riwayat. Jangan menjalankan migrasi
> ini di sela pertandingan.

## Menyetel tiap laptop

```dotenv
# Node gelanggang A
SINKRON_PERAN=gelanggang
SINKRON_NODE=gelanggang-a
SINKRON_ARENA=A
SINKRON_TOKEN=<token yang sama di semua laptop>
SINKRON_PEER="global|http://192.168.1.10:8000|<token>,gelanggang-b|http://192.168.1.12:8000|<token>"

# Node global
SINKRON_PERAN=global
SINKRON_NODE=global
SINKRON_ARENA=
SINKRON_TOKEN=<token yang sama>
SINKRON_PEER="gelanggang-a|http://192.168.1.11:8000|<token>,..."
```

**Token kosong berarti endpoint sinkron MATI, bukan terbuka.** Laptop yang
belum dikonfigurasi tidak menyajikan isi basis datanya ke jaringan gelanggang
— jaringan yang sama dengan perangkat penonton.

Setelah mengubah `.env`, jalankan `php artisan config:clear`.

## Menarik data

Buka **Sinkron Gelanggang** di menu admin, tekan **Tarik dari peer ini**.
Halaman memanggil peer satu potongan pada satu waktu sampai peer bilang
selesai; bilah kemajuannya bergerak di antara potongan.

Perulangannya sengaja di browser. Satu permintaan yang menarik sampai habis
akan menahan satu dari delapan proses php-cgi selama seluruh penarikan —
proses yang juga melayani tekanan tombol juri.

Kursor disimpan **setelah** penerapan berhasil. Kursor yang maju lebih dulu
berarti perubahan yang gagal diterapkan dianggap sudah masuk dan tidak akan
pernah ditarik lagi — hilang tanpa galat, baru ketahuan saat rekap medali
tidak cocok antar laptop.

## Bagan lintas gelanggang

Pemenang perdelapan final di gelanggang A naik ke perempat final yang bisa
dijadwalkan di gelanggang B. Sampai hasilnya ditarik, laptop B belum tahu
siapa pemenangnya.

Sistem menahannya. Pengendali yang mencoba menayangkan partai seperti itu
mendapat pesan yang **menyebut gelanggang mana** yang ditunggu. Kalau hasilnya
sudah pasti dan jaringan tidak bisa ditunggu, pemindahan paksa tetap tersedia
— jalan yang sama dengan meninggalkan partai yang belum diakhiri.

## Yang berubah sifatnya

- **Panel Ketua Pertandingan** melihat seluruh gelanggang, tapi kini sejauh
  sinkron terakhir — bukan lagi keadaan langsung.
- **Konflik penugasan aparat lintas gelanggang** baru terdeteksi setelah
  sinkron. Kunci penugasannya di node global sebelum hari-H.
- **Rekap medali** hanya lengkap setelah semua node ditarik.

## Yang belum ditangani

- **Tabel pivot murni** (`registration_athlete`, `model_has_roles`,
  `role_has_permissions`) tidak punya model Eloquent, jadi perubahannya tidak
  tertangkap observer. Ia ikut terbawa pada penarikan penuh pertama; yang
  belum tertangani adalah perubahan pivot **sesudah** itu. Praktisnya: kunci
  pendaftaran dan peran di node global sebelum hari pertama.
- **Sinkron lewat berkas** (flashdisk) belum ada. Format paketnya sudah
  serialisable, jadi jalur itu bisa ditambah tanpa merombak.
