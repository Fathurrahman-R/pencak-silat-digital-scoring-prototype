// Penyapu prop berbahasa lama yang tersangkut di tag `x-si.*`.
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
// Pemindainya sadar-kutip: regex non-greedy yang berhenti di '>' pertama akan
// berhenti di '>' milik `$arena->is_active`, dan prop di belakangnya luput.

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, sep } from 'node:path';

const AKAR = 'resources/views';

// Nama prop berbahasa lama. Kalau muncul di dalam tag si/*, hampir pasti sisa
// pemindahan yang belum diganti.
const LAMA = /\s:?\b(variant|subtitle|checked|hint|errors-for|delta|current|dot|pill|title|size|href|required|rows|options|icon|status|items|all)=/g;

// Nama yang memang atribut HTML sungguhan pada komponen bersangkutan, bukan
// prop yang kelewat.
const BOLEH = {
    'x-si.pilihan': new Set(['options', 'size']),
};

/** Semua berkas .blade.php di bawah `dir`. */
const berkas = (dir) =>
    readdirSync(dir).flatMap((nama) => {
        const jalur = join(dir, nama);
        if (statSync(jalur).isDirectory()) return berkas(jalur);
        return jalur.endsWith('.blade.php') ? [jalur] : [];
    });

/** Indeks '>' yang benar-benar menutup tag yang dibuka di `mulai`. */
const batasTag = (isi, mulai) => {
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
};

let temuan = 0;

for (const jalur of berkas(AKAR)) {
    const isi = readFileSync(jalur, 'utf8');

    for (const m of isi.matchAll(/<x-si\.[a-z.-]+/g)) {
        const tag = m[0].slice(1);
        const mulai = m.index + m[0].length;
        const atribut = isi.slice(mulai, batasTag(isi, mulai));
        const boleh = BOLEH[tag] ?? new Set();

        for (const g of atribut.matchAll(LAMA)) {
            if (boleh.has(g[1])) continue;

            const baris = isi.slice(0, m.index).split('\n').length;
            console.log(`${jalur.split(sep).join('/')}:${baris}  ${tag}  <-  ${g[0].trim()}`);
            temuan++;
        }
    }
}

console.log(temuan === 0 ? '\nBersih, nol prop tersangkut.' : `\n${temuan} prop tersangkut.`);
process.exit(temuan === 0 ? 0 : 1);
