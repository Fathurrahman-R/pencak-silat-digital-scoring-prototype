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

Penampilan Jurus dijadwalkan ke gelanggang lewat tab **Jurus** di menu Jadwal
(`App\Support\Bagan\PenjadwalJurus`). Untuk nomor berformat `battle`, yang
dijadwalkan adalah **battle**, bukan satu sudut: keduanya dimainkan berurutan
di matras yang sama, biru lebih dulu (Pasal 12.1.d.7). Karena itu serah-terima
jadwal antar gelanggang **menolak** satu sudut battle — memindahkannya sendiri
akan meninggalkan lawannya di gelanggang asal, dan yang membacanya di panel
kendali tidak punya cara menebak ke mana pasangannya pergi.

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

## Token sinkron

Token di sini adalah **rahasia yang kamu buat sendiri** — bukan dari layanan
mana pun, bukan API key, bukan token bawaan Laravel. Ia berfungsi seperti kata
sandi antar-laptop: satu-satunya cara endpoint sinkron tahu bahwa yang mengetuk
memang salah satu mesin kejuaraan, bukan perangkat lain di LAN yang sama.

Buat sekali, sebelum memasang laptop pertama:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Jangan memakai kata yang mudah ditebak. LAN gelanggang juga dipakai perangkat
penonton dan panitia, dan endpoint ini menyajikan seluruh riwayat pertandingan.

Token dipakai dua arah, dan keduanya harus cocok:

| Setelan | Artinya |
|---|---|
| `SINKRON_TOKEN` di laptop X | Token yang **harus dibawa** siapa pun yang menarik data **dari** X |
| Bagian ketiga tiap entri `SINKRON_PEER` | Token **milik peer itu**, dibawa laptop ini saat menarik **darinya** |

Artinya token pada entri peer `global` harus sama persis dengan
`SINKRON_TOKEN` yang dipasang di laptop global. Paling sederhana: pakai **satu
token yang sama di kelima laptop**, sehingga tidak ada yang perlu dicocokkan
satu per satu. Contoh di bawah memakai cara itu.

## Menyetel tiap laptop

Ganti `RAHASIA` dengan token yang barusan dibuat — nilai yang **sama** di semua
laptop — dan sesuaikan alamat IP dengan yang dicetak `jalankan-server.ps1` di
tiap mesin.

```dotenv
# Node gelanggang A  (192.168.1.11)
SINKRON_PERAN=gelanggang
SINKRON_NODE=gelanggang-a
SINKRON_ARENA=A
SINKRON_TOKEN=RAHASIA
SINKRON_PEER="global|http://192.168.1.10:8000|RAHASIA,gelanggang-b|http://192.168.1.12:8000|RAHASIA"

# Node global  (192.168.1.10)
SINKRON_PERAN=global
SINKRON_NODE=global
SINKRON_ARENA=
SINKRON_TOKEN=RAHASIA
SINKRON_PEER="gelanggang-a|http://192.168.1.11:8000|RAHASIA,gelanggang-b|http://192.168.1.12:8000|RAHASIA"
```

Tiap laptop gelanggang cukup mengenal **node global** sebagai peer; mengenal
gelanggang lain hanya perlu kalau baganmu memang lintas gelanggang dan kamu
ingin menarik hasilnya langsung tanpa lewat node global.

Nama peer `global` bukan sekadar label: pendorong arsip mencarinya dengan nama
itu (atau entri yang `peran`-nya `global`) untuk tahu ke mana bukti partai
dikirim.

**Token kosong berarti endpoint sinkron MATI, bukan terbuka.** Laptop yang
belum dikonfigurasi tidak menyajikan isi basis datanya ke jaringan gelanggang
— jaringan yang sama dengan perangkat penonton.

Setelah mengubah `.env`, jalankan `php artisan config:clear`.

## Sebelum memasang laptop pertama: semai catatan sinkron

Catatan sinkron lahir dari observer, jadi ia hanya berisi baris yang BERUBAH
sesudah observernya terpasang. Kejuaraan yang datanya sudah tersusun --
peserta diimpor, bagan disusun, jadwal ditetapkan -- karena itu punya catatan
yang nyaris kosong, dan node baru yang menariknya menerima anak tanpa induk.

```bash
php artisan silat:sinkron-semai      # di node global, sekali
```

Node global menyemainya sendiri saat peer pertama menarik dari nol, jadi
perintah ini jaring pengaman, bukan keharusan. Yang berguna dari
menjalankannya lebih dulu: waktunya bisa dipilih, bukan jatuh di tengah
antrean panitia yang sedang memasang laptop.

> Terukur pada kejuaraan 556 atlet: 4.247 baris disemai, dan penarikan
> pertama sebuah node gelanggang selesai dalam **24 detik**.

## Penarikan pertama: node yang belum punya akun

Node gelanggang yang baru dipasang **tidak boleh diseed**. Seluruh data
kejuaraan — termasuk `users`, `roles`, dan `resources` — bergolongan GLOBAL:
node global satu-satunya yang menulisnya, dan node gelanggang menerimanya lewat
sinkron. Menyeed di kedua mesin menghasilkan dua deret id auto-increment yang
berbeda, dan penerapan paket meng-*upsert* menurut id: baris global menimpa
baris lokal yang artinya berbeda. Bentuk terburuknya bukan kejuaraan ganda,
melainkan `model_has_roles` yang menunjuk peran bernomor sama tapi bukan peran
yang sama — petugas mendapat izin yang bukan miliknya, tanpa satu pun pesan
galat.

Yang dijalankan di node gelanggang cuma:

```bash
php artisan migrate            # skema saja, TANPA --seed
.\scripts\server\siapkan-gelanggang.ps1 -Peran gelanggang -Arena A -Token RAHASIA -Peer 'global|http://<ip-global>:8000'
```

Lalu buka **`/pemasangan`** di peramban. Halaman itu terbuka **tanpa login**,
menampilkan identitas mesin dan daftar peer, dan menarik potongan demi potongan
seperti halaman sinkron biasa.

Ia hidup hanya bila ketiganya terpenuhi: tabel `users` masih kosong, token
sinkron sudah terpasang, dan ada peer terdaftar. Penarikan pertama membawa akun
dari node global — dan sejak baris pertama itu masuk, `/pemasangan` membalas
**404** untuk selamanya, tanpa ada yang perlu ingat mematikannya. Sesudah itu
masuk memakai akun dari node global dan gunakan menu Sinkron Gelanggang seperti
biasa.

> Yang bisa dilakukan orang asing di jaringan gelanggang, seandainya ia
> menemukan alamat itu pada mesin yang memang masih kosong: memicu penarikan
> dari peer yang sudah tertulis di `.env` mesin itu sendiri, memakai token yang
> tertulis di situ juga. Ia tidak memilih sumbernya, tidak menyisipkan apa pun,
> dan tidak ada yang bisa dibaca dari basis data yang masih kosong.

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

Begitu hasil partai hulu tiba, **laptop pemilik partai berikutnya menaikkan
pemenangnya sendiri**, dengan aritmetika bagan yang sama. Laptop A memang
menaikkan pemenang di basis datanya sendiri, tapi partai babak berikutnya
milik B: A tidak mengirimnya, dan B menolaknya kalau pun terkirim. Yang sampai
ke B cuma hasil partai hulu, jadi B yang menurunkan sudutnya. Partai hilir
yang belum dijadwalkan dimiliki node global, dan node global yang
menurunkannya.

Di topologi yang dianjurkan (gelanggang hanya mengenal node global), hasil
partai dari A sampai ke B **lewat node global**: node global meneruskan
keadaan partai yang ia terima dari satu gelanggang ke gelanggang lainnya.
Urutannya: A ditarik node global, lalu B menarik dari node global. Laptop A
yang kemudian menarik dari node global akan melihat partainya sendiri
dikirim kembali dan menolaknya. Angka "ditolak" di ringkasannya itu wajar,
itulah pemutus lingkaran yang bekerja.

## Yang berubah sifatnya

- **Panel Ketua Pertandingan** melihat seluruh gelanggang, tapi kini sejauh
  sinkron terakhir — bukan lagi keadaan langsung.
- **Konflik penugasan aparat lintas gelanggang** baru terdeteksi setelah
  sinkron. Kunci penugasannya di node global sebelum hari-H.
- **Rekap medali** hanya lengkap setelah semua node ditarik.

## Yang belum ditangani

- **Atlet yang dilepas dari pendaftaran** (detach) belum tercatat. Tidak ada
  layar yang melakukannya hari ini; pendaftaran yang dihapus utuh tetap
  terbawa. Pivot yang ditempelkan (attach) dan perubahan peran sudah ikut
  tersinkron sesudah pemasangan.
- **Sinkron lewat berkas** (flashdisk) belum ada. Format paketnya sudah
  serialisable, jadi jalur itu bisa ditambah tanpa merombak.
