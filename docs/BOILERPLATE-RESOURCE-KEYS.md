# Boilerplate — Auth + RBAC Resource Key + RizzxxUI

> Dokumen teknis fondasi kode yang dipakai aplikasi ini (`Fathurrahman-R/boilerplate`), dipindah dari README utama supaya README bisa fokus menjelaskan aplikasi digital scoring pencak silat itu sendiri. Isinya tidak berubah dari boilerplate aslinya -- kode di seluruh domain silat (turnamen, pendaftaran, scoring, VAR, Jurus, rekap) memakai pola resource key ini apa adanya.

Titik awal untuk project Laravel baru: autentikasi lengkap, kontrol akses berbasis peran yang bisa diatur dari UI, dan design system sendiri yang dokumentasinya hidup di dalam aplikasi.

Yang membedakannya dari boilerplate RBAC biasa: kode tidak pernah menyebut nama permission. Kode memakai **resource key**, dan permission di baliknya ditentukan lewat tabel pemetaan di database yang bisa diubah dari panel admin.

**Stack:** Laravel 13 · PHP 8.3+ · MySQL 8 · Fortify · spatie/laravel-permission 8 · Tailwind CSS 4 · Alpine 3 · ApexCharts · Lucide · Pest 5

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
    <x-ui.button>Ekspor</x-ui.button>
@endresource

{{-- 3. Komponen, untuk potongan UI kecil --}}
<x-can resource="laporan.export">
    <x-ui.button>Ekspor</x-ui.button>
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

`TableBuilder` mengurus query-nya, komponen `<x-ui.table>` mengurus tampilannya.

```php
$table = TableBuilder::for(Laporan::query()->with('penulis'))
    ->searchable(['judul', 'penulis.name'])                                  // titik = lewat relasi
    ->sortable(['judul', 'created_at'], default: 'created_at', direction: 'desc')
    ->filter('status', fn ($query, $value) => $query->where('status', $value))
    ->perPage(15);

return view('admin.laporan.index', ['laporan' => $table->paginate(), 'table' => $table]);
```

```blade
<x-ui.table.toolbar :table="$table" placeholder="Cari laporan…" />

<x-ui.table :table="$table" :headers="['judul' => 'Judul', 'created_at' => 'Dibuat', 0 => '']">
    @foreach ($laporan as $item)
        <x-ui.table.row>
            <x-ui.table.cell header>{{ $item->judul }}</x-ui.table.cell>
            ...
        </x-ui.table.row>
    @endforeach
</x-ui.table>
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

## Design system

Lapisan visualnya bernama **RizzxxUI**. Dokumentasinya bukan berkas terpisah yang bisa basi — ia halaman di dalam aplikasi ini, dirender dari komponen yang sama dengan yang dipakai panel admin:

| URL | Isi |
|---|---|
| `/design-system` | Prinsip, warna, tipografi, spacing, permukaan & kaca, material, motion, ikon |
| `/design-system/komponen` | Seluruh komponen beserta varian dan potongan kode pemakaiannya |
| `/design-system/pola` | Pola layout, voice & tone, dan panduan token untuk developer |
| `/design-system/layar/…` | Lima layar bukti: dashboard, landing, internal tool, settings, auth |

Aktif di semua environment kecuali produksi. Kalau dimatikan, route-nya tidak didaftarkan sama sekali:

```
DESIGN_SYSTEM_ENABLED=false
```

**Ini design system PANEL ADMIN saja.** Panel gelanggang (operator/wasit/juri/dewan-juri/keberatan), halaman live score publik, dan overlay vMix memakai lapisan visual terpisah total ("papan skor") -- lihat `resources/css/silat.css` dan halaman peraga komponennya sendiri. Keduanya sengaja tidak pernah saling memuat berkas satu sama lain.

### Token

Semua warna hidup sebagai CSS variable di `resources/css/app.css`, lalu dipetakan ke nama Tailwind di blok `@theme`. Tema berganti lewat atribut `data-theme` di `<html>` — **tidak ada satu pun kelas `dark:`** di seluruh view.

```css
:root            { --surface-raised: #FFFFFF; --accent: #3D5FE0; }
:root[data-theme='dark'] { --surface-raised: #131923; --accent: #4F7CFF; }

@theme inline {
    --color-surface-raised: var(--surface-raised);
    --color-accent: var(--accent);
}
```

Menyesuaikan tema untuk satu klien biasanya cukup mengganti `--accent`, `--accent-hover`, `--accent-soft`, `--accent-on`, dan `--mat-accent` di kedua blok.

Utility yang perlu diingat: `glass` · `mat-raised` `mat-base` `mat-panel` `mat-well` `mat-press` · `bg-shell` `bg-grid` `bg-grid-tight` `bg-noise` `bg-glow` · `num` · `eyebrow` · `form-check` `form-select` · `skeleton-line`.

**Aturan kaca.** Blur hanya untuk lapisan yang mengambang di atas latar bertekstur — sidebar, topbar, hero, kartu metrik. Tabel, form, dan teks panjang selalu di permukaan solid. Kaca di atas warna rata cuma jadi kotak abu-abu, jadi latar bertekstur di `layouts/base` bukan hiasan melainkan syarat.

Latarnya dipilih lewat prop `backdrop` di `<x-layouts.base>`:

| Nilai | Dipakai | Isi |
|---|---|---|
| `page` (bawaan) | landing, auth, dokumentasi | `bg-surface` + grid 32px + noise |
| `shell` | seluruh halaman aplikasi | `bg-shell` (bidang `mat-base` + semburat aksen) + grid 24px + noise |

**Aturan material.** Yang menonjol bisa ditekan (`mat-raised`, bayangan `bevel` + `lift`), yang cekung bisa diisi (`mat-well`), dan konten selalu datar. Satu sumber cahaya, selalu dari atas. Bidang yang menaungi sekelompok kontrol memakai `mat-panel` — bayangannya `lift-lg`, satu tingkat di atas tombol yang ada di dalamnya. Tidak ada kedalaman di baris tabel dan tidak ada emboss di teks; keduanya menggagalkan kontras AA.

**Sidebar yang diciutkan.** Lebarnya dipegang `--shell-sidebar` di `<html>`, bukan Alpine, supaya sudah benar sebelum halaman digambar. Nilainya adalah ruang yang dipesan di tepi kiri: panel kaca mengambang dengan jarak 12px di kiri dan kanan, jadi rail 68px yang diminta design system tercatat sebagai `calc(var(--rail-w) + 1.5rem)`. Ubah `--rail-w` kalau ikonnya perlu ruang lebih.

### Komponen

Semua di `resources/views/components/ui/`, semuanya bekerja di terang maupun gelap tanpa varian tambahan.

`button` `input` `textarea` `select` `datepicker` `file-upload` `dropzone` `checkbox` `radio` `toggle` `slider` `label` `field-note` · `card` `badge` `alert` `stat` `skeleton` `empty-state` `spinner` `avatar` `avatar-stack` `presence-dot` `breadcrumb` `icon` `kbd` `code-block` · `table` `table.row` `table.cell` `table.toolbar` · `modal` `drawer` `drawer-remote` `dropdown` `dropdown-item` `tooltip` `tabs` `toast` `command-palette` `notification-menu` · `combobox` `tag-input` `stepper` `segmented` `filter-chips` · `progress` `ratio-bar` `legend` `bar-chart` · `accordion` `accordion-item` `timeline` `wizard`

`bar-chart` dirender [ApexCharts](https://apexcharts.com), diimpor dinamis lewat `Alpine.data('apexBarChart', …)` di `resources/js/app.js` — halaman yang tidak menampilkan grafik tidak ikut menanggung beratnya di bundle.

Pencarian, filter, dan pagination tinggal di dalam kartu tabel yang sama lewat slot `toolbar` dan `footer`, bukan sebagai tiga potong yang kebetulan bertumpuk:

```blade
<x-ui.table :table="$table" :headers="['name' => 'Nama']">
    <x-slot:toolbar>
        <x-ui.table.toolbar :table="$table" placeholder="Cari nama…" />
    </x-slot:toolbar>

    {{-- baris --}}

    <x-slot:footer>{{ $users->links() }}</x-slot:footer>
</x-ui.table>
```

View pagination-nya ada di `resources/views/vendor/pagination/`. Bawaan Laravel sengaja diganti: kelas `gray-*` dan `dark:*` di dalamnya tidak ikut berganti saat `data-theme` berubah.

Komponen form membaca `$errors` sendiri — cukup sebut `name`, pesan validasinya muncul otomatis:

```blade
<x-ui.input name="judul" label="Judul" required />
```

Layout: `<x-layouts.admin>` (sidebar kaca + topbar + breadcrumb), `<x-layouts.guest>` (kartu terpusat untuk flow auth pendek/sensitif — 2FA, reset kata sandi), `<x-layouts.guest-split>` (form + panel kepercayaan kaca, dipakai Masuk/Daftar), `<x-layouts.docs>` (halaman dokumentasi).

**Perilaku dinamis memakai Alpine, bukan pustaka UI.** Tidak ada langkah re-init setelah DOM berubah. Modal dan drawer dibuka dengan event:

```blade
<x-ui.button type="button" x-on:click="$dispatch('modal-open', 'hapus-user')">Hapus</x-ui.button>

<x-ui.modal id="hapus-user" title="Hapus pengguna?" size="sm">…</x-ui.modal>
```

Ikon memakai Lucide lewat `mallardduck/blade-lucide-icons` — SVG inline, tanpa JavaScript. Nama ikonnya apa adanya dari [lucide.dev/icons](https://lucide.dev/icons):

```blade
<x-ui.icon name="trash-2" class="size-4" />
```

Font Sora, Space Grotesk, dan IBM Plex Mono di-bundle Vite lewat paket `@fontsource`, bukan diambil dari CDN — aplikasi tetap tampil benar di jaringan tertutup. Panel silat memakai Space Grotesk dan IBM Plex Mono dari bundel yang sama.

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
