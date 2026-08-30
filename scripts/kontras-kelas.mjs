// Pengukur kontras untuk pasangan warna yang DITULIS DI KELAS, bukan yang
// didaftarkan tangan.
//
// Jalankan: node scripts/kontras-kelas.mjs
// Keluar dengan kode 1 kalau ada pasangan yang gagal.
//
// Kenapa ini ada:
//
// `scripts/kontras.mjs` hanya menguji pasangan yang ditulis di dalamnya. Itu
// celahnya: pasangan yang dipakai view tapi tidak pernah didaftarkan tidak
// diukur siapa pun, dan yang mengetahuinya hanya orang yang kebetulan
// memperhatikan bahwa sesuatu tampak pudar.
//
// Berkas ini TIDAK lahir dari cacat sungguhan — ia lahir dari salah tuduh.
// Chip penyaring yang tampil putih-di-atas-putih sempat didiagnosis sebagai
// tabrakan token, dan `bg-ink text-surface` sempat dicatat berkontras 1.0.
// Angkanya ternyata 16.11 di terang dan 19.67 di gelap; yang salah adalah
// kelasnya belum dibangun (lihat `kelas-hilang.mjs`). Diagnosis itu keliru,
// tapi celah yang ditunjuknya nyata, dan berkas ini menutupnya.
//
// Berkas ini bekerja dari arah sebaliknya: ia membaca kelas yang benar-benar
// ditulis di view, meresolusi tiap tokennya ke nilai hex lewat rantai
// `bg-ink` → `--color-ink` → `--text-primary` → `--k-tinta` → `#17161a`, lalu
// mengukurnya di kedua suasana.
//
// BATASNYA, dan ini disengaja: hanya pasangan yang ditulis di SATU untaian
// kelas yang sama yang terbaca. Latar yang datang dari elemen leluhur tidak
// terlihat dari sini, dan menebaknya akan menghasilkan lebih banyak laporan
// palsu daripada temuan. Pasangan seperti itu tetap didaftarkan tangan di
// `kontras.mjs`. Kedua berkas saling melengkapi, bukan menggantikan.

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, sep } from 'node:path';

const BERKAS_CSS = [
    'resources/css/dasar.css',
    'resources/css/dasar-admin.css',
    'resources/css/app.css',
];

const AKAR_VIEW = 'resources/views';

/* ------------------------------------------------------------------------ */
/* Warna                                                                     */
/* ------------------------------------------------------------------------ */

const hex = (h) => {
    h = h.replace('#', '');
    if (h.length === 3) h = [...h].map((c) => c + c).join('');
    return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
};

const linier = (c) => {
    c /= 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
};

const luminans = (h) => {
    const [r, g, b] = hex(h);
    return 0.2126 * linier(r) + 0.7152 * linier(g) + 0.0722 * linier(b);
};

const rasio = (a, b) => {
    const la = luminans(a);
    const lb = luminans(b);
    const [tinggi, rendah] = la > lb ? [la, lb] : [lb, la];
    return (tinggi + 0.05) / (rendah + 0.05);
};

/* ------------------------------------------------------------------------ */
/* Membaca token dari CSS                                                    */
/* ------------------------------------------------------------------------ */

/**
 * Kumpulkan deklarasi `--nama: nilai` per suasana.
 *
 * Blok `:root[data-theme='dark']` menimpa `:root`; selektor lain (mis.
 * `.silat`) diabaikan karena bundel gelanggang punya pengukurnya sendiri.
 */
function bacaToken() {
    const terang = new Map();
    const gelap = new Map();

    for (const jalur of BERKAS_CSS) {
        const isi = readFileSync(jalur, 'utf8');

        /*
         * Tiga blok yang menyimpan token: `:root`, `:root[data-theme='dark']`,
         * dan `@theme inline`.
         *
         * Yang terakhir gampang terlewat, dan justru di sanalah alias
         * `--color-*` tinggal — nama yang dipakai kelas Tailwind. Tanpa
         * membacanya, pemeriksa ini meresolusi NOL token dan melaporkan
         * "bersih" untuk halaman yang penuh cacat.
         */
        const blok = /(:root(?:\[data-theme='dark'\])?|@theme inline)\s*\{([^}]*)\}/g;

        for (const m of isi.matchAll(blok)) {
            const sasaran = m[1].includes('dark') ? gelap : terang;

            for (const d of m[2].matchAll(/(--[a-z0-9-]+)\s*:\s*([^;]+);/g)) {
                sasaran.set(d[1], d[2].trim());
            }
        }
    }

    // Suasana gelap mewarisi apa pun yang tidak ditimpanya.
    const gelapPenuh = new Map(terang);
    for (const [k, v] of gelap) gelapPenuh.set(k, v);

    return { terang, gelap: gelapPenuh };
}

/** Ikuti rantai `var(--x)` sampai ketemu nilai hex. */
function resolusi(nama, token, kedalaman = 0) {
    if (kedalaman > 12) return null;

    const nilai = token.get(nama);
    if (nilai === undefined) return null;

    const langsung = nilai.match(/^#[0-9a-fA-F]{3,8}$/);
    if (langsung) return langsung[0].slice(0, 7);

    const lanjut = nilai.match(/^var\((--[a-z0-9-]+)\)$/);
    if (lanjut) return resolusi(lanjut[1], token, kedalaman + 1);

    // rgb(...), gradien, `none`, dan kawan-kawan tidak diukur.
    return null;
}

/**
 * Nama utility Tailwind (`ink`, `surface-raised`, `danger-soft`) → hex,
 * lewat alias `--color-*` di blok `@theme inline`.
 */
function petaWarna(token) {
    const peta = new Map();

    for (const [nama] of token) {
        if (! nama.startsWith('--color-')) continue;

        const warna = resolusi(nama, token);
        if (warna) peta.set(nama.slice('--color-'.length), warna);
    }

    return peta;
}

/* ------------------------------------------------------------------------ */
/* Membaca kelas dari Blade                                                  */
/* ------------------------------------------------------------------------ */

const berkasBlade = (dir) =>
    readdirSync(dir).flatMap((nama) => {
        const jalur = join(dir, nama);
        if (statSync(jalur).isDirectory()) return berkasBlade(jalur);
        return jalur.endsWith('.blade.php') ? [jalur] : [];
    });

/**
 * Untaian yang mungkin berisi daftar kelas.
 *
 * Diambil dari `class="…"`, dari kunci `@class([… => syarat])`, dan dari
 * untaian PHP yang isinya memang daftar kelas (`$nyala = 'bg-ink text-surface'`).
 * Tiap untaian diperiksa sendiri-sendiri: dua kelas yang berada di untaian
 * berbeda belum tentu mendarat di elemen yang sama.
 */
function untaianKelas(isi) {
    const keluar = [];

    for (const m of isi.matchAll(/class="([^"]*)"/g)) {
        const nilai = m[1];

        /*
         * Cabang ternary dipisahkan, tidak digabung.
         *
         *     class="… {{ $lengkap ? 'bg-surface-raised text-ink' : 'bg-accent text-accent-on' }}"
         *
         * Kedua cabang itu saling meniadakan; memperlakukan seluruh atribut
         * sebagai satu untaian akan memasangkan latar cabang pertama dengan
         * tinta cabang kedua, dan melaporkan 1.00 untuk kombinasi yang tidak
         * pernah terjadi. Persis itu yang dilaporkannya sebelum ini.
         *
         * Jadi: bagian statis (di luar `{{ }}`) digabung dengan SATU cabang
         * pada satu waktu.
         */
        const statis = nilai.replace(/\{\{[\s\S]*?\}\}/g, ' ');
        const cabang = [...nilai.matchAll(/\{\{[\s\S]*?\}\}/g)]
            .flatMap((i) => [...i[0].matchAll(/'([^'\n]*)'/g)].map((c) => c[1]));

        if (cabang.length === 0) {
            keluar.push([statis, m.index]);
        } else {
            for (const c of cabang) keluar.push([`${statis} ${c}`, m.index]);
        }
    }

    // Untaian PHP yang berdiri sendiri: `$nyala = 'bg-accent text-accent-on'`.
    for (const m of isi.matchAll(/'([^'\n]*\b(?:bg|text)-[a-z][a-z0-9-]*[^'\n]*)'/g)) {
        keluar.push([m[1], m.index]);
    }

    return keluar;
}

/* ------------------------------------------------------------------------ */
/* Pemeriksaan                                                               */
/* ------------------------------------------------------------------------ */

// Teks biasa. Judul besar dan label kecil sama-sama diukur di sini karena
// ukurannya tidak terbaca dari daftar kelas dengan andal.
const MINIMUM = 4.5;

/*
 * Belum ada satu pun pasangan yang perlu dikecualikan, dan daftar kosong ini
 * ditinggalkan sengaja: kalau suatu saat ada pasangan yang memang bukan teks
 * di atas latarnya, alasannya ditulis di sini -- bukan diselesaikan dengan
 * menurunkan ambang.
 */
const DIKECUALIKAN = new Set([]);

const token = bacaToken();
const warna = {
    terang: petaWarna(token.terang),
    gelap: petaWarna(token.gelap),
};

const temuan = [];

for (const jalur of berkasBlade(AKAR_VIEW)) {
    const isi = readFileSync(jalur, 'utf8');

    for (const [untaian, posisi] of untaianKelas(isi)) {
        /*
         * Kelas berpengubah opasitas DILEWATI.
         *
         * `bg-accent-on/15` bukan `accent-on` -- ia lapisan 15% di atas apa pun
         * yang ada di belakangnya, dan warna hasilnya tidak bisa dihitung tanpa
         * tahu latar leluhurnya. Menganggapnya opak melaporkan
         * `text-accent-on di bg-accent-on` berkontras 1.00 untuk badge yang
         * sesungguhnya 9.9 di terang dan 11.9 di gelap.
         *
         * Ini batas yang sama dengan latar dari leluhur: pemeriksa ini hanya
         * mengukur yang bisa dipastikan dari untaian kelasnya sendiri.
         */
        const opak = (re) => [...untaian.matchAll(re)]
            .filter((m) => m[2] === undefined)
            .map((m) => m[1]);

        const latar = opak(/(?:^|\s)bg-([a-z][a-z0-9-]*)(\/[0-9]+)?/g);
        const tinta = opak(/(?:^|\s)text-([a-z][a-z0-9-]*)(\/[0-9]+)?/g);

        for (const b of latar) {
            for (const t of tinta) {
                if (DIKECUALIKAN.has(`${b}|${t}`)) continue;

                for (const suasana of ['terang', 'gelap']) {
                    const hb = warna[suasana].get(b);
                    const ht = warna[suasana].get(t);

                    // Salah satunya bukan token warna (mis. `text-[14px]`,
                    // `bg-black/50`) — tidak ada yang bisa diukur.
                    if (! hb || ! ht) continue;

                    const r = rasio(ht, hb);
                    if (r >= MINIMUM) continue;

                    temuan.push({
                        jalur: jalur.split(sep).join('/'),
                        baris: isi.slice(0, posisi).split('\n').length,
                        suasana,
                        pasangan: `text-${t} di bg-${b}`,
                        warna: `${ht} / ${hb}`,
                        rasio: r,
                    });
                }
            }
        }
    }
}

for (const t of temuan) {
    console.log(
        `${t.rasio.toFixed(2).padStart(6)}  (min ${MINIMUM})  ${t.suasana.toUpperCase()} · ${t.pasangan}`
        + `  ${t.warna}\n        ${t.jalur}:${t.baris}`,
    );
}

console.log(
    temuan.length === 0
        ? '\nBersih, nol pasangan kelas di bawah ambang.'
        : `\n${temuan.length} pasangan kelas di bawah ambang.`,
);

process.exit(temuan.length === 0 ? 0 : 1);
