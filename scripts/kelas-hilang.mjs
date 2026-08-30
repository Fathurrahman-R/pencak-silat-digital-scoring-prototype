// Pemeriksa kelas token yang dipakai view tapi TIDAK ADA di CSS terbangun.
//
// Jalankan: node scripts/kelas-hilang.mjs
// Keluar dengan kode 1 kalau bundelnya basi atau ada kelas yang tidak dihasilkan.
//
// Kenapa ini ada:
//
// Tailwind hanya menghasilkan kelas yang DITEMUKANNYA saat build. Kelas yang
// ditulis di Blade setelah build terakhir tidak punya aturan CSS sama sekali —
// ia menempel di elemen tanpa berbuat apa pun, dan elemennya mewarisi nilai
// dari induknya.
//
// Ini menipu, dan pernah menipu saya: chip penyaring dengan `bg-ink
// text-surface` tampil sebagai kotak putih tanpa satu pun huruf. Terbaca
// persis seperti cacat kontras — dua token yang kebetulan sama-sama putih —
// padahal pasangan itu berkontras 16.11 di terang dan 19.67 di gelap. Yang
// terjadi: `si/saring` baru dibuat, `.text-surface` belum pernah ada di
// bundel, dan teksnya mewarisi putih dari induknya. Satu jam terbuang mengejar
// cacat warna yang tidak pernah ada.
//
// Pengukur kontras tidak bisa menangkap ini: menurut token, warnanya benar.
// Yang salah adalah aturannya tidak diterbitkan.

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, sep } from 'node:path';

const MANIFEST = 'public/build/manifest.json';
const AKAR_VIEW = 'resources/views';
const SUMBER = ['resources/views', 'resources/css', 'resources/js'];
const CSS_SUMBER = ['resources/css/app.css', 'resources/css/silat.css'];

// Awalan utility Tailwind yang mengambil nama warna dari `--color-*`.
const AWALAN = ['text', 'bg', 'border', 'ring', 'fill', 'stroke', 'from', 'via', 'to', 'divide', 'outline', 'decoration', 'shadow', 'accent', 'caret'];

/* ------------------------------------------------------------------------ */

const berkasRekursif = (dir, akhiran) =>
    readdirSync(dir).flatMap((nama) => {
        const jalur = join(dir, nama);
        if (statSync(jalur).isDirectory()) return berkasRekursif(jalur, akhiran);
        return akhiran.some((a) => jalur.endsWith(a)) ? [jalur] : [];
    });

let manifest;
try {
    manifest = JSON.parse(readFileSync(MANIFEST, 'utf8'));
} catch {
    console.log('public/build/manifest.json tidak ada — jalankan `npm run build` lebih dulu.');
    process.exit(1);
}

const cssTerbangun = Object.values(manifest)
    .flatMap((entri) => [entri.file, ...(entri.css ?? [])])
    .filter((f) => f?.endsWith('.css'))
    .map((f) => join('public/build', f));

if (cssTerbangun.length === 0) {
    console.log('Tidak ada berkas CSS di manifest — jalankan `npm run build` lebih dulu.');
    process.exit(1);
}

/* --- 1. Bundelnya basi? -------------------------------------------------- */
//
// Diperiksa lebih dulu, karena kalau basi maka SETIAP kelas baru akan
// dilaporkan hilang dan laporannya jadi tidak berguna. Yang perlu dibaca
// pemakainya cuma satu kalimat: bangun ulang.

const waktuBundel = Math.min(...cssTerbangun.map((f) => statSync(f).mtimeMs));

// Berkas `public/hot` berarti server Vite sedang berjalan dan menghasilkan
// kelas begitu berkasnya disimpan. Bundel di public/build memang tertinggal
// saat itu, dan itu bukan cacat — jadi pemeriksaan kebasian dilewati. Yang di
// bawahnya tetap jalan, karena bundel lama masih cukup untuk memeriksa kelas
// yang sudah lama ada.
let vitePanas = false;
try {
    statSync('public/hot');
    vitePanas = true;
} catch { /* tidak ada, berarti memakai bundel */ }

const sumberBaru = vitePanas ? [] : SUMBER
    .flatMap((dir) => berkasRekursif(dir, ['.blade.php', '.css', '.js']))
    .filter((f) => statSync(f).mtimeMs > waktuBundel);

if (sumberBaru.length > 0) {
    console.log(`Bundel CSS lebih tua daripada ${sumberBaru.length} berkas sumber.`);
    console.log('Kelas apa pun yang ditulis sesudahnya belum punya aturan CSS, dan di peramban');
    console.log('ia akan tampil seolah propnya salah. Jalankan `npm run build` lebih dulu.\n');

    for (const f of sumberBaru.slice(0, 8)) console.log(`  ${f.split(sep).join('/')}`);
    if (sumberBaru.length > 8) console.log(`  … dan ${sumberBaru.length - 8} lainnya`);

    process.exit(1);
}

/* --- 2. Kelas token yang dipakai tapi tidak dihasilkan -------------------- */

/*
 * Backslash pelolos dibuang lebih dulu. Tailwind menuliskan varian dan
 * pengubah opasitas sebagai selektor berpelolos:
 *
 *     .bg-red-500\/20{…}
 *     .focus-visible\:outline-white:focus-visible{…}
 *
 * Mencari `.bg-red-500{` di dalamnya tidak akan ketemu, dan kelas yang
 * sebenarnya ADA dilaporkan hilang. Setelah pelolosnya dibuang, nama kelasnya
 * tinggal dicari sebagai potongan dengan batas di kedua ujungnya.
 */
const gabungan = cssTerbangun
    .map((f) => readFileSync(f, 'utf8'))
    .join('\n')
    .replace(/\\/g, '');

/*
 * Nama warna sistem dibaca dari CSS SUMBER, bukan dari bundel.
 *
 * `@theme inline` tidak pernah menerbitkan `--color-surface` dkk. sebagai
 * custom property — itulah arti `inline`: nilainya disisipkan langsung ke
 * utility-nya (`.text-surface{color:var(--surface)}`). Mencarinya di bundel
 * hanya menemukan palet bawaan Tailwind, dan seluruh token sistem ini lolos
 * dari pemeriksaan tanpa satu pun tanda.
 */
const namaWarna = new Set(
    CSS_SUMBER
        .map((f) => readFileSync(f, 'utf8'))
        .join('\n')
        .matchAll(/--color-([a-z0-9-]+)\s*:/g)
        .map((m) => m[1]),
);

if (namaWarna.size < 10) {
    console.log('Nama warna sistem hampir tidak ada — periksa blok @theme di resources/css/.');
    process.exit(1);
}

// Pengubah opasitas ikut ditangkap: `bg-red-500/20` adalah kelas tersendiri,
// dan `bg-red-500` tanpa pengubahnya belum tentu pernah dihasilkan.
const pola = new RegExp(`\\b(${AWALAN.join('|')})-([a-z][a-z0-9-]*)(/[0-9]+)?\\b`, 'g');

/**
 * Kelas itu punya aturan di CSS, dalam varian apa pun.
 *
 * Batas di kedua ujung wajib, supaya `bg-surface` tidak dianggap ada hanya
 * karena ada `bg-surface-raised`. Dan SELURUH kemunculan harus diperiksa,
 * bukan yang pertama saja: `bg-surface-raised` biasanya muncul lebih dulu di
 * bundel, dan pemeriksaan yang berhenti di situ melaporkan `bg-surface`
 * hilang padahal aturannya ada beberapa kilobyte di bawahnya.
 */
const ada = (kelas) => {
    const sasaran = kelas.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return new RegExp(`(?<=[.:\\s,>+~])${sasaran}(?=[{,:.\\s/>+~])`).test(gabungan);
};

const hilang = new Map();

for (const jalur of berkasRekursif(AKAR_VIEW, ['.blade.php'])) {
    // Komentar Blade dibuang: prosa di dalamnya sering menyebut nama kelas
    // sebagai contoh -- catatan di berkas pagination menerangkan kenapa kelas
    // `text-gray-700` bawaan Laravel diganti, dan itu bukan pemakaian.
    const isi = readFileSync(jalur, 'utf8').replace(/\{\{--[\s\S]*?--\}\}/g, '');

    for (const m of isi.matchAll(pola)) {
        // Hanya yang benar-benar menunjuk nama warna sistem. `text-[14px]`,
        // `border-2`, dan `gap-3` bukan urusan berkas ini.
        if (! namaWarna.has(m[2])) continue;

        const kelas = `${m[1]}-${m[2]}${m[3] ?? ''}`;
        if (ada(kelas)) continue;

        if (! hilang.has(kelas)) hilang.set(kelas, []);

        const baris = isi.slice(0, m.index).split('\n').length;
        hilang.get(kelas).push(`${jalur.split(sep).join('/')}:${baris}`);
    }
}

for (const [kelas, tempat] of hilang) {
    console.log(`${kelas} — dipakai ${tempat.length}×, tidak ada di CSS terbangun`);
    for (const t of tempat.slice(0, 3)) console.log(`        ${t}`);
}

// Kalimatnya menyebut apa yang BENAR-BENAR diperiksa. "Bundel mutakhir" saat
// pemeriksaan kebasiannya dilewati adalah kabar yang tidak pernah diuji.
console.log(
    hilang.size > 0
        ? `\n${hilang.size} kelas token tanpa aturan CSS.`
        : vitePanas
            ? 'Server Vite berjalan, jadi kebasian bundel tidak diperiksa. Tiap kelas token'
                + '\nyang dipakai view punya aturannya di bundel terakhir.'
            : 'Bundel mutakhir, dan tiap kelas token yang dipakai view punya aturannya.',
);

process.exit(hilang.size === 0 ? 0 : 1);
