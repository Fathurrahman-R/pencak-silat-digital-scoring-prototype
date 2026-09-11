# Boilerplate — Auth + RBAC Resource Key + lapisan komponen si/*

> Dokumen teknis fondasi kode yang dipakai aplikasi ini (`Fathurrahman-R/boilerplate`), dipindah dari README utama supaya README bisa fokus menjelaskan aplikasi digital scoring pencak silat itu sendiri.
>
> Lapisan resource key dan RBAC-nya tidak berubah dari boilerplate aslinya — kode di seluruh domain silat (turnamen, pendaftaran, scoring, VAR, Jurus, rekap) memakai polanya apa adanya. **Lapisan komponennya berubah total:** design system boilerplate diganti lapisan `si/*` yang dirancang untuk panitia gelanggang, dan komponen lamanya sudah dihapus dari repo. Bagian "Lapisan komponen" di bawah menjelaskan yang berlaku sekarang; alasan tiap keputusannya ada di [`docs/BRIEF-DESAIN.md`](BRIEF-DESAIN.md).

Titik awal untuk project Laravel baru: autentikasi lengkap, kontrol akses berbasis peran yang bisa diatur dari UI, dan design system sendiri yang dokumentasinya hidup di dalam aplikasi.

Yang membedakannya dari boilerplate RBAC biasa: kode tidak pernah menyebut nama permission. Kode memakai **resource key**, dan permission di baliknya ditentukan lewat tabel pemetaan di database yang bisa diubah dari panel admin.

**Stack:** Laravel 13 · PHP 8.3+ · MySQL 8 · Fortify · spatie/laravel-permission 8 · Tailwind CSS 4 · Alpine 3 · Lucide · Pest 5

---

## Resource key

Satu resource key berbentuk `{resource}.{aksi}` — misalnya `posts.update`. Key inilah yang dipakai kode.

Permission Spatie adalah entitas terpisah. Hubungan keduanya disimpan di tabel `resource_permissions`:

```
resource key            pemetaan (DB)          permission
"posts.update"    ─────────────────────►      "posts.update"     (dibuat otomatis)
"laporan.export"  ─────────────────────►      "akses-laporan"    (diarahkan ulang lewat UI)
"posts.publish"   ─────────────────────►      "content-manage"   (banyak key, satu permission)
```

Konsekuensinya: menggabungkan dua permission, mengganti namanya, atau memindahkan sebuah key ke permission lain sama sekali tidak menyentuh kode. Cukup ubah pemetaannya di menu **Pemetaan Key**.

Domain silat memakai pola yang sama persis -- lihat `database/seeders/SilatResourceSeeder.php` dan `SilatRoleSeeder.php` untuk daftar lengkap resource key (`turnamen`, `partai`, `var`, `penampilan-jurus`, `rekap`, dst.) dan peran (Pasal 13: Ketua Pertandingan, Wasit, Juri, dst.).

### Empat cara memakainya

Keempatnya memakai key yang sama dan memberi jawaban yang sama.

```php
// 1. Menjaga route
Route::get('/laporan', ...)->middleware('resource:laporan.view');

Route::get('/laporan/ekspor', ...)->middleware('resource:laporan.export|laporan.print'); // salah satu (ATAU)
Route::post('/laporan', ...)->middleware('resource:laporan.view,laporan.create');        // keduanya (DAN)
```

```blade
{{-- 2. Menyembunyikan bagian tampilan --}}
@resource('laporan.export')
    <x-si.tombol>Ekspor</x-si.tombol>
@endresource

{{-- 3. Komponen, untuk potongan UI kecil --}}
<x-can resource="laporan.export">
    <x-si.tombol>Ekspor</x-si.tombol>
</x-can>
```

```php
// 4. Policy — $this->authorize() dan @can tetap idiomatis
class LaporanPolicy extends BaseResourcePolicy
{
    protected function resource(): string
    {
        return 'laporan';
    }
}
```

Menu sidebar menyaring dirinya sendiri: cukup cantumkan `'resource' => rk('laporan', ResourceAction::View)` di `config/navigation.php`.

### Aksi hanya boleh dari enum

`app/Enums/ResourceAction.php` adalah satu-satunya sumber nama aksi. Admin memilihnya lewat centang di UI, developer memakai case enum-nya:

```php
rk('laporan', ResourceAction::Export);   // "laporan.export"
rk('laporan', 'ekspor');                 // InvalidResourceKey — gagal saat itu juga
```

Aksi yang tersedia: `view` `create` `update` `delete` `restore` `force_delete` `export` `import` `approve` `reject` `publish` `assign` `print` `manage`. Tambah case baru di enum kalau butuh.

Untuk autocomplete IDE, jalankan `php artisan resource:keys`. Perintah itu membaca database lalu menulis ulang `app/Support/Resources/ResourceKeys.php` berisi konstanta seperti `ResourceKeys::LAPORAN_EXPORT`.

### Aturan yang berlaku

- Key tidak dikenal atau belum dipetakan **selalu ditolak** — tidak ada celah diam-diam. Key tak dikenal juga dicatat di log.
- Super admin (`config/resources.php`) melewati semuanya lewat `Gate::before`, tanpa perlu satu centangan pun.
- Menghapus permission **tidak** menghapus resource key-nya; key-nya berubah jadi "tak terpetakan" dan aksesnya tertutup sampai dipetakan ulang.
- Menghapus resource **tidak** menghapus permission-nya — bisa jadi masih dipakai key lain.
- Resource dan permission inti ditandai terkunci dan tidak bisa dihapus dari UI.

---

## Menambah modul baru

Contohnya modul Laporan. Modul `posts` di repo boilerplate asal adalah cetakan lengkapnya (sudah dibersihkan dari aplikasi ini karena hanya contoh) — modul-modul domain silat (`app/Http/Controllers/Admin/JurusScoringController.php`, dst.) adalah contoh nyatanya di sini.

**1. Buat resource lewat panel.** Menu Resource → Tambah. Isi nama `laporan`, centang aksi yang dibutuhkan. Permission-nya terbuat dan terpetakan otomatis.

**2. Buat model, migration, dan controller.**

```bash
php artisan make:model Laporan -mfc
php artisan make:request Admin/StoreLaporanRequest
```

**3. Buat policy** di `app/Policies/LaporanPolicy.php`, turunkan dari `BaseResourcePolicy`, sebutkan `return 'laporan';`.

**4. Daftarkan route** di `routes/web.php`, di dalam grup admin, dengan `->middleware('resource:'.rk('laporan', ResourceAction::View))` — atau panggil `$this->authorize()` di controller kalau memakai policy.

**5. Tambahkan menu** di `config/navigation.php` dengan `'resource' => rk('laporan', ResourceAction::View)`.

Lalu bagikan permission-nya ke role lewat menu Role.

---

## Tabel: pencarian, urutan, filter, ekspor

`TableBuilder` mengurus query-nya, komponen `<x-si.tabel>` mengurus tampilannya.

```php
$table = TableBuilder::for(Laporan::query()->with('penulis'))
    ->searchable(['judul', 'penulis.name'])                                  // titik = lewat relasi
    ->sortable(['judul', 'created_at'], default: 'created_at', direction: 'desc')
    ->filter('status', fn ($query, $value) => $query->where('status', $value))
    ->perPage(15);

return view('admin.laporan.index', ['laporan' => $table->paginate(), 'table' => $table]);
```

```blade
<x-si.tabel.toolbar :table="$table" placeholder="Cari laporan…" />

<x-si.tabel :table="$table" :headers="['judul' => 'Judul', 'created_at' => 'Dibuat', 0 => '']">
    @foreach ($laporan as $item)
        <x-si.tabel.baris>
            <x-si.tabel.sel header>{{ $item->judul }}</x-si.tabel.sel>
            ...
        </x-si.tabel.baris>
    @endforeach
</x-si.tabel>
```

Kolom yang boleh diurutkan wajib didaftarkan di `sortable()`. Nilai `?sort=` di luar daftar itu diabaikan, bukan diteruskan ke query.

Ekspor CSV:

```php
return $table->download(fn (Laporan $item): array => [
    'Judul' => $item->judul,
    'Dibuat' => $item->created_at->format('Y-m-d'),
], 'laporan.csv');
```

Modul rekap silat (`app/Http/Controllers/Admin/RekapController.php`) memakai pola CSV manual (`response()->streamDownload()`) alih-alih `TableBuilder::download()` karena datanya hasil agregasi lintas tabel (medali per kontingen), bukan satu query tabel tunggal.

---

## Lapisan komponen

Lapisannya bernama `si/*` dan tinggal di `resources/views/components/si/`.
Rujukan lengkapnya — token, alasan tiap keputusan, dan angka kontrasnya — ada
di [`docs/BRIEF-DESAIN.md`](BRIEF-DESAIN.md); yang di bawah ini hanya cukup
untuk mulai bekerja.

Peraganya bukan berkas terpisah yang bisa basi, melainkan halaman di dalam
aplikasi ini yang dirender dari komponen yang sama:

| URL | Isi |
|---|---|
| `/design-system` | Seluruh komponen `si/*` dalam semua keadaannya |
| `/design-system/gelanggang` | Papan skor, tombol juri, dan ikon aksi — bundel terpisah |

Aktif di semua environment kecuali produksi. Kalau dimatikan, route-nya tidak
didaftarkan sama sekali:

```
DESIGN_SYSTEM_ENABLED=false
```

Keduanya sengaja terpisah: yang pertama memakai bundel admin (terang), yang
kedua memakai bundel silat (gelap) dan tidak memuat `app.css` sama sekali.
Token yang bocor antar-bundel langsung kelihatan.

### Token

Semua warna hidup sebagai CSS variable. Nilai mentahnya di
`resources/css/dasar.css` (bersama + inti gelap) dan `dasar-admin.css` (inti
terang); `app.css` hanya memetakannya ke nama Tailwind lewat blok `@theme`.
Tema berganti lewat atribut `data-theme` di `<html>` — **tidak ada satu pun
kelas `dark:`** di seluruh view.

```css
@theme inline {
    --color-surface-raised: var(--surface-raised);
    --color-accent: var(--accent);
}
```

Menyesuaikan tema untuk satu klien biasanya cukup mengganti `--k-aksi`,
`--k-aksi-lembut`, dan `--k-aksi-teks` di `dasar-admin.css`.

**Tidak ada kaca, bevel, grid latar, maupun butiran noise.** Semua itu warisan
boilerplate dan sudah dibuang. Kedalaman dinyatakan lewat **warna permukaan dan
satu garis**; bayangan hanya untuk lapisan yang benar-benar mengambang —
dropdown, modal, panel rincian.

Halaman aplikasi memanggil `<x-layouts.base shell>` untuk mendapat latar shell;
sisanya duduk langsung di atas permukaan kertas.

**Tidak ada warna masuk desain sebelum angkanya diukur.** `npm run
periksa-rupa` menjalankan lima pemeriksa berurutan; masing-masing keluar
dengan kode 1 kalau gagal:

| Pemeriksa | Menangkap |
|---|---|
| `kelas-hilang.mjs` | Bundel CSS yang basi, dan kelas token yang dipakai view tapi tidak punya aturan CSS |
| `kontras.mjs` | 72 pasangan warna yang didaftarkan tangan, termasuk yang latar efektifnya butuh perhitungan alpha |
| `kontras-kelas.mjs` | Pasangan `bg-*`/`text-*` yang ditulis di kelas, diresolusi dari token — menutup celah pemeriksa di atasnya |
| `sapu-prop.mjs` | Prop yang menaungi prop sungguhan komponennya — daftarnya diturunkan dari `@props` tiap komponen, bukan dirawat tangan |
| `periksa-siaran.mjs` | `VITE_REVERB_SCHEME` yang terisi, dan bundel yang membekukan `forceTLS` — keduanya membuat halaman `https` tetap membuka `ws://`, yang diblokir peramban sebagai konten campuran tanpa pesan yang terlihat di Safari iOS |

Yang pertama dijalankan lebih dulu dan menghentikan sisanya, karena bundel
basi membuat setiap laporan lain jadi tidak berarti.

Semuanya menjaga mode kegagalan yang sama: **gagal tanpa bersuara.** Prop yang
tidak dikenal komponennya lolos jadi atribut HTML; kelas yang belum dibangun
tidak punya aturan sama sekali. Tidak ada yang menerbitkan galat, tidak ada
yang memerahkan uji Pest, dan di layar keduanya terbaca sebagai "propnya
salah" — kesimpulan yang salah, dan yang menyesatkan berjam-jam.

### Komponen

Semua di `resources/views/components/si/`, semuanya bekerja di terang maupun
gelap tanpa varian tambahan. **Propnya berbahasa Indonesia** — `varian`,
`ukuran`, `tipe`, `wajib`, `bantuan`, `judul` — sama seperti sisa kode ini.

Isian: `tombol` `isian` `isian-panjang` `pilihan` `centang` `saklar` `unggah` ·
Wadah: `kartu` `modal` `panel-rincian` `tabel` (+`tabel.baris` `tabel.sel`
`tabel.toolbar`) · Penanda: `badge` `ikon` `foto` `titik-hadir` `angka`
`callout` `kosong` `pesan-kilat` `tuts` `linimasa` `pohon-bagan` · Navigasi:
`menu` `menu-butir` `jejak` `tab-halaman` `saring` `lonceng` `cari-menu` ·
Bahaya: `konfirmasi` `hapus-baris` `hapus-borongan`

Panel gelanggang punya lapisannya sendiri (`x-silat.*`) yang tidak pernah
dimuat bersama yang ini.

Pencarian, penyaring, dan penomoran halaman tinggal di dalam kartu tabel yang
sama lewat slot `toolbar` dan `footer`, bukan sebagai tiga potong yang
kebetulan bertumpuk:

```blade
<x-si.tabel :table="$table" :headers="['name' => 'Nama']">
    <x-slot:toolbar>
        <x-si.tabel.toolbar :table="$table" placeholder="Cari nama…" />
    </x-slot:toolbar>

    {{-- baris --}}

    <x-slot:footer>{{ $users->links() }}</x-slot:footer>
</x-si.tabel>
```

View penomoran halamannya ada di `resources/views/vendor/pagination/`. Bawaan
Laravel sengaja diganti: kelas `gray-*` dan `dark:*` di dalamnya tidak ikut
berganti saat `data-theme` berubah.

Komponen isian membaca `$errors` sendiri — cukup sebut `name`, pesan
validasinya muncul otomatis. Tanda wajib berupa **kata**, bukan tanda bintang:

```blade
<x-si.isian name="judul" label="Judul" wajib />
```

Layout: `<x-layouts.admin>` (sidebar + topbar + jejak halaman),
`<x-layouts.guest>` (kartu terpusat untuk alur auth pendek — 2FA, reset kata
sandi), `<x-layouts.guest-split>` (formulir + panel kepercayaan, dipakai
Masuk/Daftar), `<x-layouts.docs>` (halaman peraga).

**Perilaku dinamis memakai Alpine, bukan pustaka UI.** Tidak ada langkah
re-init setelah DOM berubah. Modal dibuka dengan event:

```blade
<x-si.tombol tipe="button" x-on:click="$dispatch('modal-open', 'hapus-user')">Hapus</x-si.tombol>

<x-si.modal id="hapus-user" judul="Hapus pengguna" ukuran="kecil">…</x-si.modal>
```

**Tindakan yang menghapus TIDAK memakai modal biasa.** Ia memakai
`<x-si.konfirmasi>` (satu objek tertentu) atau `<x-si.hapus-baris>` (satu
dialog untuk seluruh halaman, barisnya mengirim muatannya lewat `data-*`).
Keduanya menuntut prop `akibat`: kalimat yang menyebut apa lagi yang ikut
hilang. "Yakin?" bukan informasi.

Ikon memakai Lucide lewat `mallardduck/blade-lucide-icons` — SVG inline, tanpa
JavaScript. Nama ikonnya apa adanya dari [lucide.dev/icons](https://lucide.dev/icons):

```blade
<x-si.ikon nama="trash-2" class="size-4" />
```

Ikon tidak pernah berdiri sendiri sebagai tombol. Ia selalu berdampingan
dengan kata: `title` tidak pernah muncul di layar sentuh, dan orang yang jarang
memakai aplikasi tidak menebak arti gambar.

Font Sora, Space Grotesk, dan IBM Plex Mono di-bundle Vite lewat paket
`@fontsource`, bukan diambil dari CDN — aplikasi tetap tampil benar di jaringan
tertutup. Panel silat memakai Space Grotesk dan IBM Plex Mono dari bundel yang
sama.

---

## Perintah artisan

| Perintah | Gunanya |
|---|---|
| `php artisan resource:list` | Daftar resource key beserta permission dan jumlah role pemakainya |
| `php artisan resource:list --unmapped` | Hanya key yang belum dipetakan |
| `php artisan resource:keys` | Menulis ulang `ResourceKeys.php` untuk autocomplete |
| `php artisan resource:keys --check` | Gagal kalau berkas itu tidak mutakhir — cocok untuk CI |
| `php artisan resource:sync` | Membuatkan permission untuk key yang masih kosong |
| `php artisan resource:doctor` | Audit: key tanpa permission, permission tanpa key, permission tanpa role |

---

## Auth

Ditangani Fortify; seluruh tampilannya ada di `resources/views/auth/` dan bebas diubah.

Aktif: login, registrasi, reset password, verifikasi email, konfirmasi password, dan verifikasi dua langkah (TOTP + kode pemulihan). Passkey tersedia di Fortify tapi sengaja dimatikan — butuh alur JavaScript sendiri.

Matikan registrasi mandiri lewat `.env`:

```
REGISTRATION_ENABLED=false
```

Akun yang dinonaktifkan (`is_active = false`) ditolak saat login dan sesinya langsung diakhiri oleh middleware `EnsureUserIsActive`.
