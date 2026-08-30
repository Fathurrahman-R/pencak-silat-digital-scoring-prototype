// Penyapu prop yang menaungi prop sungguhan komponennya.
//
// Jalankan: node scripts/sapu-prop.mjs
// Keluar dengan kode 1 kalau ada yang tersangkut.
//
// Kenapa ini ada:
//
// Blade TIDAK mengeluh soal prop yang tidak dikenal komponennya. Prop asing
// lolos jadi atribut HTML biasa, menempel diam-diam di elemen, dan komponennya
// memakai nilai bawaan. Tidak ada galat, tidak ada peringatan, tidak ada uji
// yang gagal.
//
// Empat cacat sungguhan lahir dari situ selama pemindahan `x-ui.*` ke `x-si.*`:
//
//   - Subjudul layar siaran mengoper `subjudul=` ke komponen yang propnya
//     `keterangan` -- kedua kalimatnya tidak pernah tampil sama sekali.
//   - Penyaring status bendahara mengoper `:variant` dinamis, jadi seluruh
//     tombolnya tampil serupa dan panitia tidak bisa tahu penyaring mana yang
//     sedang berlaku.
//   - Badge super admin mengoper `variant="purple"` -- rona yang tidak ada di
//     palet mana pun, jadi badge-nya abu-abu sama seperti role biasa.
//   - Tombol "Batal" di dialog konfirmasi mengoper `type="button"` padahal
//     propnya `tipe`, sehingga ia sesungguhnya tombol kirim.
//
// CARA KERJANYA, dan ini yang berubah dari versi pertama:
//
// Versi pertama memakai bloklist -- daftar nama yang diketahui berasal dari
// lapisan lama. Itu menangkap keempat cacat di atas, tapi nama baru yang belum
// pernah terlihat tetap lolos, dan daftarnya harus dirawat tangan.
//
// Versi ini membaca `@props` tiap komponen dan menurunkan daftarnya sendiri.
// Yang dicari bukan "nama asing" melainkan NAMA YANG MENAUNGI PROP YANG SUDAH
// ADA: kalau sebuah tag mengoper `type=` ke komponen yang punya prop `tipe`,
// itu hampir pasti maksudnya `tipe`, dan Blade diam-diam mengabaikannya.
//
// Bedanya penting untuk `type`, `size`, dan `name` -- sah sebagai atribut HTML
// di sebagian komponen, berbahaya di sebagian lain:
//
//   - `size` pada <x-si.pilihan> adalah atribut <select> yang sungguhan
//     (jumlah baris yang terlihat), dan si/pilihan tidak punya prop `ukuran`.
//     Dibiarkan.
//   - `size` pada <x-si.tombol> menaungi prop `ukuran`. Dilaporkan.
//
// Tidak ada satu pun nama yang ditulis di sini sebagai "berbahaya". Yang
// ditulis hanya PASANGAN NAMA -- `type` sepadan `tipe` -- dan komponennya
// sendiri yang menentukan pasangan mana yang berlaku baginya.

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, sep } from 'node:path';

const AKAR_VIEW = 'resources/views';
const AKAR_KOMPONEN = 'resources/views/components';

// Pasangan nama Inggris → Indonesia yang dipakai lapisan ini. Sebuah nama
// hanya dianggap menaungi kalau komponen bersangkutan MEMANG mendeklarasikan
// pasangannya.
const SEPADAN = {
    type: 'tipe',
    size: 'ukuran',
    title: 'judul',
    subtitle: 'keterangan',
    description: 'keterangan',
    variant: 'varian',
    required: 'wajib',
    checked: 'dicentang',
    hint: 'bantuan',
    disabled: 'nonaktif',
    href: 'tautan',
    icon: 'ikon',
    items: 'daftar',
    options: 'pilihan',
    open: 'terbuka',
    rows: 'baris',
    all: 'semua',
    current: 'sekarang',
    status: 'keadaan',
    block: 'penuh',
    placement: 'letak',
    width: 'lebar',
    shortcut: 'pintasan',
    danger: 'bahaya',
    'errors-for': 'galatUntuk',
};

/* ------------------------------------------------------------------------ */

const berkas = (dir) => readdirSync(dir).flatMap((nama) => {
    const jalur = join(dir, nama);
    if (statSync(jalur).isDirectory()) return berkas(jalur);
    return jalur.endsWith('.blade.php') ? [jalur] : [];
});

/**
 * Nama prop dibaca dari AWAL BARIS, bukan dari seluruh isi `@props`.
 *
 * `'varian' => 'netral'` punya dua untaian berkutip, dan hanya yang pertama
 * nama prop. Regex yang menyapu seluruh blok mengembalikan 'netral' juga, dan
 * daftar propnya jadi berisi nilai bawaan yang tidak pernah bisa dioper.
 */
function propKomponen(isi) {
    const m = isi.match(/@props\(\[([\s\S]*?)\n\]\)/);
    if (! m) return null;

    return new Set(
        m[1].split('\n')
            .map((baris) => baris.match(/^\s*'([a-zA-Z][a-zA-Z0-9]*)'\s*(?:=>|,)/))
            .filter(Boolean)
            .map((x) => x[1]),
    );
}

/** Peta `<x-si.tombol` → Set nama prop yang dideklarasikannya. */
function petaKomponen() {
    const peta = new Map();

    for (const jalur of berkas(AKAR_KOMPONEN)) {
        const relatif = jalur.split(sep).join('/').split('components/')[1];

        // `si/tabel/index.blade.php` dipanggil sebagai `<x-si.tabel`.
        const nama = '<x-' + relatif
            .replace('.blade.php', '')
            .replace(/\/index$/, '')
            .replace(/\//g, '.');

        const props = propKomponen(readFileSync(jalur, 'utf8'));
        if (props) peta.set(nama, props);
    }

    return peta;
}

/**
 * Indeks '>' yang benar-benar menutup tag yang dibuka di `mulai`.
 *
 * Sadar-kutip: regex non-greedy berhenti di '>' milik `$arena->is_active`, dan
 * prop di belakangnya luput.
 */
function batasTag(isi, mulai) {
    let kutip = null;

    for (let i = mulai; i < isi.length; i++) {
        const c = isi[i];

        if (kutip) {
            if (c === kutip) kutip = null;
        } else if (c === '"' || c === "'") {
            kutip = c;
        } else if (c === '>') {
            return i;
        }
    }

    return isi.length;
}

/* ------------------------------------------------------------------------ */

const komponen = petaKomponen();

if (komponen.size < 20) {
    console.log('Hampir tidak ada komponen terbaca — periksa resources/views/components/.');
    process.exit(1);
}

const temuan = [];

for (const jalur of berkas(AKAR_VIEW)) {
    const isi = readFileSync(jalur, 'utf8');

    for (const m of isi.matchAll(/<x-(?:si|silat)\.[a-z.-]+/g)) {
        const props = komponen.get(m[0]);

        // Komponen tanpa `@props` meneruskan segalanya sebagai atribut; tidak
        // ada prop yang bisa dinaungi di sana.
        if (! props) continue;

        const mulai = m.index + m[0].length;
        const atribut = isi.slice(mulai, batasTag(isi, mulai));

        for (const a of atribut.matchAll(/(?:^|\s)(:?)([a-zA-Z][a-zA-Z0-9-]*)\s*=/g)) {
            const sepadan = SEPADAN[a[2]];

            // Bukan nama berpasangan, atau komponennya memang tidak punya
            // pasangannya — berarti ia atribut HTML sungguhan.
            if (! sepadan || ! props.has(sepadan)) continue;

            temuan.push({
                jalur: jalur.split(sep).join('/'),
                baris: isi.slice(0, m.index).split('\n').length,
                tag: m[0].slice(1),
                dioper: `${a[1]}${a[2]}=`,
                seharusnya: sepadan,
            });
        }
    }
}

for (const t of temuan) {
    console.log(`${t.jalur}:${t.baris}  ${t.tag}  ${t.dioper}  menaungi prop \`${t.seharusnya}\``);
}

console.log(
    temuan.length === 0
        ? `Bersih. ${komponen.size} komponen dibaca, nol prop yang menaungi propnya sendiri.`
        : `\n${temuan.length} prop menaungi prop komponennya.`,
);

process.exit(temuan.length === 0 ? 0 : 1);
