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

Ketiganya tidak menuliskan apa pun di log. Cacat yang bentuknya "tombol tidak
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
node scripts/qa/f-safari-ios.mjs             # WebKit profil iPhone
```

Semua alamat dan id lewat env, dengan bawaan yang masuk akal:

| Env | Bawaan | Keterangan |
|---|---|---|
| `QA_ASAL` | `http://127.0.0.2:8000` | **Bukan** `127.0.0.1` — lihat catatan di bawah |
| `QA_TURNAMEN` | `1` | Kejuaraan sasaran |
| `QA_GELANGGANG` | `2` | Harus **kosong** pointernya |
| `QA_NOMOR_JURUS` | `1` | Nomor yang bagannya sudah tersusun |
| `QA_ASAL_LAN` | `QA_ASAL` | Untuk uji Safari: IP LAN mesin saat itu |
| `QA_ASAL_TUNNEL` | `https://localhost:8443` | Butuh `proksi-tunnel.mjs` berjalan |

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

**Membaca state Alpine dengan menyerialkan seluruh komponen.**
`JSON.stringify` menyentuh setiap getter, dan sebagian melempar saat partai
kosong. Baca satu jalur properti saja.

Dan yang paling menentukan: **selalu pasangkan dengan kontrol negatif.**
Kembalikan bug-nya, pastikan ujinya GAGAL. Tanpa itu, "LULUS" bisa vakum —
satu uji di ronde ini lulus hanya karena asersinya tidak pernah bisa gagal.
