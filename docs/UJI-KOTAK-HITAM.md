# Uji Kotak Hitam di Peramban

Rangkaian uji yang menjalankan **panel sungguhan, lintas peran, di peramban
sungguhan** — bukan permintaan HTTP yang dirakit sendiri. Ia masuk lewat `/login`
sebagai Ketua Pertandingan, Pengendali, Operator IT, dan empat juri sekaligus,
lalu menekan tombol yang sama dengan yang ditekan petugas di pinggir matras.

## Kenapa ia ada, padahal sudah ada 1199 uji Pest

Uji Pest memanggil controller dan memeriksa balasannya. Ia tidak pernah
menanyakan pertanyaan yang jawabannya menentukan di hari-H: **apakah tombolnya
kelihatan oleh orang yang bertugas menekannya, di alamat yang memang ia buka?**

Ronde pertama rangkaian ini (9 September 2026) menemukan tiga cacat yang lolos
dari seluruh suite:

1. `bagan.print` dan `jadwal.print` tidak dimiliki **satu peran pun**. Tombol
   "Cetak PDF" ada di kedua halaman, tapi dibungkus `@resource` — jadi tidak
   pernah tergambar untuk siapa pun, dan alamatnya membalas 403. Uji cetak yang
   sudah ada selalu hijau karena memakai akun super-admin, yang lolos lewat
   `Gate::before`.
2. Kendali timer Jurus berada di alamat yang berbeda dari kendali Tanding,
   sementara peran yang memegangnya (Operator IT) membuka alamat papan.
3. Dokumentasi menyebut Sekretariat sebagai penjadwal Jurus, padahal hanya
   Ketua Pertandingan yang punya `jadwal.assign`.

Ronde kedua (10 September 2026) menemukan dua lagi, keduanya sekeluarga:

4. `bagan.create`, `bagan.update`, `bagan.delete`, dan `nomor-jurus.update`
   juga tidak dimiliki peran mana pun -- **seluruh tahap pra-acara** mustahil
   dijalankan siapa pun kecuali super-admin. Ditemukan saat Ketua membuka
   halaman bagan dan tidak menemukan form menyusunnya.
5. Antrean Jurus di panel kendali tidak punya "Pindahkan…", sementara antrean
   Tanding tepat di atasnya punya. Seluruh sisi servernya sudah ada sejak
   rancangan serah-terima; yang tidak ada cuma pintunya.
6. `nomor-jurus.update` -- pemilih format battle/peringkat -- dimiliki
   Sekretariat, dan HANYA Sekretariat. Pemilih itu berdiri di halaman daftar
   nomor Jurus, yang dijaga `penampilan-jurus.view`: kewenangan yang justru
   tidak dimiliki Sekretariat. Jadi satu-satunya peran yang boleh menetapkan
   format nomor mendapat 403 di layar yang menetapkannya, dan menunya pun
   tidak tergambar untuknya. Lapisan ketiga dari keluarga yang sama:
   kewenangan berpemilik, bertombol, tapi layarnya menolak pemiliknya.
   (Peran Sekretariat sendiri sudah lebur ke Operator IT sesudah audit
   izin; temuannya tetap ditulis apa adanya karena bentuk cacatnya yang
   penting, bukan nama perannya. `silat:audit-izin` sekarang menangkapnya
   tanpa peramban.)
7. Reverb mati membuat aksi yang BERHASIL terbaca gagal. Seluruh event
   gelanggang `ShouldBroadcastNow` -- tanpa worker antrean, demi latensi --
   jadi siarannya berjalan di dalam permintaan yang menulis perubahannya.
   Pointer gelanggang berpindah, commit selesai, lalu cURL kehabisan waktu dan
   panel membalas `422 "Pusher error: cURL error 28"`. Yang menekan tombolnya
   menekannya lagi. Ditemukan justru karena blok J dijalankan tanpa
   `reverb:start`.
8. Baris yang sedang ditawarkan ke gelanggang lain digambar seperti baris
   biasa -- lengkap dengan "Tayangkan" yang PASTI dijawab 422 -- sementara
   baris kembar dirinya berdiri di daftar "Dilepas, menunggu diambil" tepat di
   bawahnya. Berlaku untuk kedua antrean, karena melepas memang tidak
   memindahkan `arena_id`: yang memindahkannya node penerima, sesudah
   adopsinya tercatat.

Kelimanya tidak menuliskan apa pun di log. Cacat yang bentuknya "tombol tidak
tergambar" memang tidak bisa ditangkap uji yang tidak punya mata.

## Prasyarat

- Server berjalan (`scripts/server/jalankan-server.ps1`) **dan** `reverb:start`.
- Kejuaraan berisi nomor Jurus berformat `battle` yang bagannya sudah tersusun.
- Akun seeder (`ketua@`, `pengendali2@`, `operator2@`, `juri1..4@`,
  `sekretariat@`, `official1@`), kata sandi `password`.
- Sekali saja: `npm i playwright && npx playwright install webkit chromium`.

## Menjalankan

```bash
node scripts/qa/a-alur-jurus.mjs             # alur utama, 14 case
node scripts/qa/b-seri.mjs                   # jalur SERI, 5 case
node scripts/qa/c-batas-d-salah-guna.mjs     # batas nilai + salah guna, 11 case
node scripts/qa/e-cetak.mjs                  # izin cetak, 7 case
node scripts/qa/g-verifikasi.mjs             # modal hasil verifikasi, 9 case
node scripts/qa/h-bagan-bertingkat.mjs       # bye + promosi ronde, 6 case
node scripts/qa/i-serah-jurus.mjs            # serah-terima jadwal Jurus, 6 case
node scripts/qa/j-peringkat.mjs              # nomor berformat peringkat, 10 case
node scripts/qa/k-multinode.mjs              # dua node: token, pemasangan, kursor, 10 case
node scripts/qa/k2-satu-penulis.mjs          # aturan satu penulis dan arah balik, 3 case
node scripts/qa/k3-hari-pertandingan.mjs     # satu partai penuh di node gelanggang, 6 case
node scripts/qa/k4-perubahan-susulan.mjs     # perubahan global sesudah pemasangan, 4 case
node scripts/qa/f-safari-ios.mjs             # WebKit profil iPhone
```

`h-bagan-bertingkat.mjs` menuntut satu nomor Jurus berisi **lima** peserta sah,
supaya bagannya berukuran delapan dengan tiga bye dan tiga kolom. Dengan dua
peserta ia tetap hijau tapi tidak menguji apa pun yang dimaksudkannya -- bagan
berukuran dua tidak punya ronde kedua untuk dinaiki siapa pun. Itu pernah
terjadi dan sempat dilaporkan sebagai lulus.

Semua alamat dan id lewat env, dengan bawaan yang masuk akal:

| Env | Bawaan | Keterangan |
|---|---|---|
| `QA_ASAL` | `http://127.0.0.2:8000` | **Bukan** `127.0.0.1` — lihat catatan di bawah |
| `QA_TURNAMEN` | `1` | Kejuaraan sasaran |
| `QA_GELANGGANG` | `2` | Harus **kosong** pointernya |
| `QA_NOMOR_JURUS` | `1` | Nomor yang bagannya sudah tersusun |
| `QA_ASAL_LAN` | `QA_ASAL` | Untuk uji Safari: IP LAN mesin saat itu |
| `QA_ASAL_TUNNEL` | `https://localhost:8443` | Butuh `proksi-tunnel.mjs` berjalan |
| `QA_ARENA_ASAL` / `QA_ARENA_TUJUAN` | `1` / `2` | Blok I; keduanya perlu pengendali sendiri |
| `QA_NOMOR_PERINGKAT` | `1` | Blok J; nomor yang formatnya boleh diubah |

`i-serah-jurus.mjs` menuntut gelanggang asal berisi **satu penampilan Jurus
tanpa battle**, dan sebaiknya juga satu sudut battle supaya I-05 benar-benar
diuji, bukan dilewati.

`k-multinode.mjs` dan `k2-satu-penulis.mjs` menuntut **dua node sungguhan**.
Node kedua disiapkan di mesin yang sama:

```bash
# 1. basis data kosong untuk node gelanggang
php -r "(new PDO('mysql:host=127.0.0.1','root',''))->exec('CREATE DATABASE digiscoring_gelanggang_b');"

# 2. .env.gelanggangb: DB_DATABASE=digiscoring_gelanggang_b, SINKRON_PERAN=gelanggang,
#    SINKRON_NODE=gelanggang-b, SINKRON_ARENA=B, SINKRON_PEER="global|http://127.0.0.2:8000|<token>"
# 3. .env node ini: SINKRON_PERAN=global, SINKRON_NODE=global, SINKRON_ARENA= (kosong),
#    SINKRON_PEER="gelanggang-b|http://127.0.0.4:8010|<token>"

APP_ENV=gelanggangb php artisan migrate --force      # skema saja, TANPA --seed
APP_ENV=gelanggangb php artisan serve --host=127.0.0.4 --port=8010

QA_SINKRON_TOKEN=<token> node scripts/qa/k-multinode.mjs
```

`k3-hari-pertandingan.mjs` dijalankan sesudah `k-multinode.mjs` (node B sudah
terpasang). Ia butuh id akun aparat di node global, misalnya
`QA_ID_AKUN='{"wasit":10,"juri":[14,15,16]}'`, plus `QA_ARENA_B` (bawaan 2)
dan `QA_PARTAI_B` (bawaan 2), yaitu partai **terjadwal** di Gelanggang B.
Partai itu dimainkan sampai sah, jadi kembalikan keadaannya sesudah selesai.

`k4-perubahan-susulan.mjs` menuntut perubahan di node global sudah ditulis
lebih dulu (atlet diganti nama, akun juri baru, kontingen uji yang sudah
tiba di node B), lalu diberi tahu lewat `QA_HARAPAN` (JSON: `turnamen`,
`kontingen`, `atlet`, `namaLama`, `namaBaru`, `akunBaru`, `arena`,
`kontingenDihapus`). K-25 menghapus kontingen itu sendiri lewat tinker.
Kontingen memakai soft delete, jadi ringkasan penarikan menulis
"diterapkan", bukan "dihapus".

`APP_ENV=gelanggangb` itulah yang membuat Laravel membaca `.env.gelanggangb`,
jadi dua node berjalan dari satu salinan kode. Cadangkan `.env` sebelum
mengubah perannya, dan kembalikan sesudah selesai: node yang tertinggal
berperan `global` tidak memiliki gelanggang mana pun.

`j-peringkat.mjs` menuntut nomor sasaran punya **>= 2 pendaftaran sah dan nol
penampilan** -- ia mengubah formatnya sendiri di J-00, dan ubah format memang
ditolak begitu penampilannya ada (J-09 menguji penolakan itu). Ia juga
menuntut **Reverb hidup**: tanpa itu tiap penekanan tombol menunggu satu detik
untuk cURL yang kehabisan waktu, dan rangkaiannya melambat sampai timeout,
walau aksinya sendiri sekarang tetap berhasil.

**`127.0.0.1` sengaja dihindari.** Di mesin pengembangan bisa ada server lain
yang mengikat alamat itu secara spesifik, dan ikatan spesifik menang atas nginx
yang mengikat `0.0.0.0` — permintaan lalu mendarat di aplikasi yang salah tanpa
satu pun tanda. Itu pernah terjadi dan menghabiskan waktu panjang: yang
terbaca "nginx mengabaikan X-Forwarded-Proto", padahal yang menjawab proyek
lain. Kalau balasannya aneh, **baca `<title>`-nya lebih dulu.**

## Ia MENULIS data

Rangkaian ini menjadwalkan battle, menjalankan timer, mengirim nilai juri,
mengesahkan hasil, dan menetapkan pemenang — semuanya baris sungguhan di
kejuaraan sasaran. **Jangan jalankan pada kejuaraan yang sedang dipakai.**

Mengembalikannya sesudah selesai:

```php
php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
\$t = 1; \$arena = 2;
DB::transaction(function () use (\$t, \$arena) {
    \$p = \App\Models\JurusPerformance::whereHas('jurusEvent', fn(\$q)=>\$q->where('tournament_id',\$t));
    \App\Models\JurusScore::whereIn('performance_id', (clone \$p)->pluck('id'))->delete();
    \App\Models\JurusDeduction::whereIn('performance_id', (clone \$p)->pluck('id'))->delete();
    \$p->update(['arena_id'=>null,'order_in_arena'=>null,'status'=>'terjadwal','started_at'=>null,
                 'duration_ms'=>null,'didiskualifikasi'=>false,'ratified_at'=>null,'ratified_by'=>null]);
    \App\Models\JurusBattle::whereHas('bracket.jurusEvent', fn(\$q)=>\$q->where('tournament_id',\$t))
        ->update(['arena_id'=>null,'order_in_arena'=>null,'status'=>'terjadwal','winner_registration_id'=>null,
                  'win_reason'=>null,'keputusan_alasan'=>null,'keputusan_oleh'=>null]);
    \App\Models\ArenaTayang::where('arena_id',\$arena)->delete();
});"
```

## Menulis test case baru

`bantu.mjs` menyediakan `jalankan()`, `pastikan()`, `masuk()`, dan `kirim()`.
Tiga hal yang sudah pernah membuat uji di sini berbohong, dan pantas dihindari:

**Selektor teks yang terlalu longgar.** `button:has-text("Mulai")` juga cocok
dengan **"Mulai babak"** milik panel Tanding — dan menekannya memulai partai
sungguhan. Sama untuk "Tayangkan", yang dimiliki antrean Tanding maupun
antrean Jurus. Pakai `text-is()`, dan lingkupi ke bloknya.

**Asersi peka huruf.** Banyak label dirender `uppercase` lewat CSS, dan
`innerText` mengembalikan hasil render — `includes('Nilai juri')` gagal pada
teks yang tampil "NILAI JURI". Pakai regex `/…/i`.

**Menunggu `panel != null` sebagai tanda muatan sudah datang.** `partaiPanel`
menimpa `panel` dengan kerangka kosong saat init, jadi syarat itu benar sejak
milidetik pertama -- sebelum tarikan `state` mana pun. Yang membacanya melihat
antrean kosong dan menyimpulkan fiturnya rusak. Tunggu bloknya sendiri
(`panel?.jurus !== undefined`).

**`ancestor::div[...]` untuk melingkupi satu blok.** Ia menaiki pohon sampai
menemukan yang cocok, dan `ancestor::div[.//button][1]` dari judul "Antrean
Jurus" mendarat di pembungkus seluruh panel -- dua puluh tombol "Tayangkan"
milik antrean Tanding ikut terjaring. Lingkupi ke wadah daftarnya
(`following::div[contains(@class,"max-h-64")][1]`), dan untuk asersi tentang
SATU baris, lingkupi lagi ke barisnya.

**Membaca state Alpine dengan menyerialkan seluruh komponen.**
`JSON.stringify` menyentuh setiap getter, dan sebagian melempar saat partai
kosong. Baca satu jalur properti saja.

Dan yang paling menentukan: **selalu pasangkan dengan kontrol negatif.**
Kembalikan bug-nya, pastikan ujinya GAGAL. Tanpa itu, "LULUS" bisa vakum —
satu uji di ronde ini lulus hanya karena asersinya tidak pernah bisa gagal.
