// Pengukur rasio kontras untuk token di docs/BRIEF-DESAIN.md.
//
// Jalankan: node scripts/kontras.mjs
//
// Aturannya: tidak ada warna masuk desain sebelum angkanya ada di sini. Setiap
// pasangan teks/latar baru ditambahkan ke daftar PASANGAN lebih dulu, lalu
// nilainya disalin ke tabel token di brief.
//
// Perhitungan latar efektif ikut memperhitungkan alpha lewat `atas()`. Ini
// bukan kerumitan yang dibuat-buat: temuan T2 di docs/AUDIT-UIUX.md sempat
// dilaporkan berkontras 1.54 padahal sesungguhnya 10.6, semata karena alat
// ukur waktu itu membaca rgba(...) sebagai warna solid dan mengabaikan alpha.

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

/** Warna `depan` beropasitas `alpha` yang dilapiskan di atas `belakang`. */
const atas = (depan, alpha, belakang) => {
    const d = hex(depan);
    const b = hex(belakang);
    return '#' + d.map((c, i) => Math.round(c * alpha + b[i] * (1 - alpha)).toString(16).padStart(2, '0')).join('');
};

const rasio = (a, b) => {
    const la = luminans(a);
    const lb = luminans(b);
    const [tinggi, rendah] = la > lb ? [la, lb] : [lb, la];
    return (tinggi + 0.05) / (rendah + 0.05);
};

// ---------------------------------------------------------------------------
// Token — nama mengikuti docs/BRIEF-DESAIN.md §4
// ---------------------------------------------------------------------------

const T = {
    // Inti terang (--k-*): admin/panitia dan dokumen cetak
    kKertas: '#f4f2ee',
    kKertasNaik: '#ffffff',
    kKertasTurun: '#e9e6e0',
    kKertasDalam: '#dcd8d0',
    kGaris: '#d4cfc6',
    kTepiKendali: '#7d7668',
    kTinta: '#17161a',
    kTintaKedua: '#45434a',
    kTintaRedup: '#5f5c66',
    kAksi: '#17161a',
    kAksiTeks: '#ffffff',
    kAksiLembut: '#e6e3dd',
    kSukses: '#14663f',
    kSuksesLembut: '#dcefe4',
    kPerhatian: '#7a4a00',
    kPerhatianLembut: '#f7ecd6',
    kBahaya: '#a3221c',
    kBahayaLembut: '#f8e3e1',
    kBahayaTeks: '#ffffff',

    /*
     * Sudut pesilat di suasana terang -- bagan panitia menggambarnya sebagai
     * bidang penuh, sama seperti papan skor gelanggang. Nilainya berbeda dari
     * palet gelanggang karena latarnya kertas: #7a1418 dan #0c2a63 terlalu
     * gelap berdampingan dengan kertas dan terbaca seperti lubang.
     */
    kSudutMerah: '#a3221c',
    kSudutBiru: '#12439e',
    kSudutMerahTeks: '#ffe6e4',
    kSudutBiruTeks: '#ccdcf8',

    // Mode gelap admin (:root[data-theme='dark']) memakai palet gelanggang.
    // Tidak ada set token tersendiri -- panitia yang berpindah antara layar
    // admin dan panel gelanggang melihat satu keluarga warna.
    kdKertas: '#0b0b0c',
    kdKertasNaik: '#131316',
    kdKertasTurun: '#17171a',
    kdAksiLembut: '#26262b',
    kdTinta: '#ffffff',
    kdTintaKedua: '#b0b0b6',
    kdTintaRedup: '#8a8a90',
    kdTepiKendali: '#6a6a70',
    kdSukses: '#4ade80',
    kdSuksesLembut: '#10241a',
    kdPerhatian: '#d98324',
    kdPerhatianLembut: '#2a1d0c',
    kdBahaya: '#ff7b74',
    kdBahayaLembut: '#2c1412',
    kdBahayaTeks: '#111114',

    // Inti gelap (--g-*): panel gelanggang, live publik, overlay
    gLatar: '#0b0b0c',
    gPanel: '#131316',
    gGaris: '#2a2a2c',
    gTepiKendali: '#6a6a70',   // tepi tombol dan isian
    gTepiPetak: '#8a8a90',     // tepi petak informasi -- sama dengan teks redup
    gTeks: '#ffffff',
    gTeksRedup: '#8a8a90',
    gMerah: '#d42027',
    gMerahDalam: '#7a1418',
    gBiru: '#12439e',
    gBiruDalam: '#0c2a63',
    gTeksMerah: '#fff0f0',
    gTeksMerahRedup: '#fff5f5',
    gTeksBiru: '#f2f6ff',
    gTeksMerahSamar: '#e8b4b6',  // tingkat ketiga di dalam blok merah

    /*
     * Rona sudut yang dipakai langsung di Blade, bukan lewat token CSS.
     * Sudah lama ada di blok sudut, papan skor, dan overlay, tapi tidak
     * pernah masuk berkas ini -- artinya bidang sudutnya bisa digelapkan
     * atau diterangkan tanpa satu pun uji gagal, dan teks di atasnya baru
     * ketahuan hilang di gelanggang.
     */
    gRedupDiBiru: '#9ebbea',
    gSamarDiBiru: '#cbdbf7',
    gTeksBiruSamar: '#a8b8e0',   // tingkat ketiga di dalam blok biru
    gAksi: '#e8e8ea',
    gAksiTeks: '#111114',
    gEmas: '#c9a227',
    gPembinaan: '#6b6b73',
    gTeguran: '#d98324',
    gTeguranTeks: '#1a1207',
    gHidup: '#4ade80',         // indikator sistem hidup / tersambung
    gTeksMati: '#b0b0b6',      // teks pada kontrol nonaktif
    gPeringatan: '#d42027',

};

// ---------------------------------------------------------------------------
// Pasangan yang diuji
//
// Ambang: 4.5 untuk teks; 3 untuk teks besar dan tepi kendali (ditandai
// "non-teks"); tekstur latar diuji TERBALIK — ia justru harus di bawah 1.3
// supaya tidak terbaca sebagai garis pembatas.
// ---------------------------------------------------------------------------

const PASANGAN = [
    ['TERANG · teks utama di kertas', T.kTinta, T.kKertas],
    ['TERANG · teks utama di kartu putih', T.kTinta, T.kKertasNaik],
    ['TERANG · teks kedua di kertas', T.kTintaKedua, T.kKertas],
    ['TERANG · teks redup di kertas', T.kTintaRedup, T.kKertas],
    ['TERANG · teks redup di kartu putih', T.kTintaRedup, T.kKertasNaik],
    ['TERANG · teks utama di baris selang-seling', T.kTinta, T.kKertasTurun],
    ['TERANG · tepi kendali di kertas (non-teks)', T.kTepiKendali, T.kKertas],
    ['TERANG · tepi kendali di kartu putih (non-teks)', T.kTepiKendali, T.kKertasNaik],
    ['TERANG · tombol utama: teks di bidang aksi', T.kAksiTeks, T.kAksi],
    ['TERANG · tombol kedua: teks di bidang lembut', T.kTinta, T.kAksiLembut],
    ['TERANG · sukses di lembutnya', T.kSukses, T.kSuksesLembut],
    // si/callout varian "berhasil" menaruh tinta biasa di atas bidang lembut,
    // bukan warna suksesnya -- kalimatnya panjang dan harus terbaca sebagai
    // teks biasa, bukan sebagai peringatan berwarna.
    ['TERANG · tinta di sukses lembut', T.kTinta, T.kSuksesLembut],

    // Bagan panitia: bidang sudut penuh di atas kertas.
    ['TERANG · putih di merah sudut', '#ffffff', T.kSudutMerah],
    ['TERANG · putih di biru sudut', '#ffffff', T.kSudutBiru],
    ['TERANG · kontingen di merah sudut', T.kSudutMerahTeks, T.kSudutMerah],
    ['TERANG · kontingen di biru sudut', T.kSudutBiruTeks, T.kSudutBiru],
    ['TERANG · bidang merah sudut di kertas (non-teks)', T.kSudutMerah, T.kKertas],
    ['TERANG · bidang biru sudut di kertas (non-teks)', T.kSudutBiru, T.kKertas],
    ['TERANG · sukses di kertas', T.kSukses, T.kKertas],
    ['TERANG · perhatian di lembutnya', T.kPerhatian, T.kPerhatianLembut],
    ['TERANG · perhatian di kertas', T.kPerhatian, T.kKertas],
    ['TERANG · bahaya di lembutnya', T.kBahaya, T.kBahayaLembut],
    ['TERANG · bahaya di kertas', T.kBahaya, T.kKertas],
    ['TERANG · teks di bidang bahaya', T.kBahayaTeks, T.kBahaya],

    ['GELAP-ADMIN · teks utama di kertas', T.kdTinta, T.kdKertas],
    ['GELAP-ADMIN · teks utama di kartu', T.kdTinta, T.kdKertasNaik],
    ['GELAP-ADMIN · teks utama di baris selang-seling', T.kdTinta, T.kdKertasTurun],
    ['GELAP-ADMIN · teks kedua di kertas', T.kdTintaKedua, T.kdKertas],
    ['GELAP-ADMIN · teks redup di kertas', T.kdTintaRedup, T.kdKertas],
    ['GELAP-ADMIN · tepi kendali di kertas (non-teks)', T.kdTepiKendali, T.kdKertas],
    ['GELAP-ADMIN · tombol kedua: teks di bidang lembut', T.kdTinta, T.kdAksiLembut],
    ['GELAP-ADMIN · sukses di lembutnya', T.kdSukses, T.kdSuksesLembut],
    ['GELAP-ADMIN · tinta di sukses lembut', T.kdTinta, T.kdSuksesLembut],
    ['GELAP-ADMIN · sukses di kertas', T.kdSukses, T.kdKertas],
    ['GELAP-ADMIN · perhatian di lembutnya', T.kdPerhatian, T.kdPerhatianLembut],
    ['GELAP-ADMIN · perhatian di kertas', T.kdPerhatian, T.kdKertas],
    ['GELAP-ADMIN · bahaya di lembutnya', T.kdBahaya, T.kdBahayaLembut],
    ['GELAP-ADMIN · bahaya di kertas', T.kdBahaya, T.kdKertas],
    ['GELAP-ADMIN · teks di bidang bahaya', T.kdBahayaTeks, T.kdBahaya],

    ['GELAP · teks di latar', T.gTeks, T.gLatar],
    ['GELAP · teks di panel', T.gTeks, T.gPanel],
    ['GELAP · teks redup di latar', T.gTeksRedup, T.gLatar],
    ['GELAP · teks redup di panel', T.gTeksRedup, T.gPanel],
    ['GELAP · tepi kendali di panel (non-teks)', T.gTepiKendali, T.gPanel],
    ['GELAP · teks putih di merah sudut', T.gTeks, T.gMerah],
    ['GELAP · teks putih di biru sudut', T.gTeks, T.gBiru],
    ['GELAP · nuansa merah di merah-dalam', T.gTeksMerah, T.gMerahDalam],
    ['GELAP · nuansa merah redup di merah-dalam', T.gTeksMerahRedup, T.gMerahDalam],
    ['GELAP · nuansa biru di biru-dalam', T.gTeksBiru, T.gBiruDalam],
    ['GELAP · tombol aksi: teks gelap di bidang terang', T.gAksiTeks, T.gAksi],
    ['GELAP · emas di latar', T.gEmas, T.gLatar],
    ['GELAP · emas di panel', T.gEmas, T.gPanel],
    ['GELAP · teks putih di pembinaan', T.gTeks, T.gPembinaan],
    ['GELAP · teks gelap di teguran', T.gTeguranTeks, T.gTeguran],
    ['GELAP · teks putih di peringatan', T.gTeks, T.gPeringatan],

    ['GELAP · teks samar di blok merah', T.gTeksMerahSamar, T.gMerahDalam],
    ['GELAP · teks samar di blok biru', T.gTeksBiruSamar, T.gBiruDalam],

    /*
     * Rona sudut yang dipakai langsung di Blade -- lihat catatan di palet.
     * Ketiganya hanya di sisi biru: padanan merahnya pernah #f5afb2 dan gagal
     * 2.89 di atas merah sudut, jadi sisi merah sekarang memakai putih
     * kemerahan yang lolos (dua baris terakhir di bawah).
     */
    ['GELAP · teks redup di biru sudut', T.gRedupDiBiru, T.gBiru],
    ['GELAP · teks redup di biru dalam', T.gRedupDiBiru, T.gBiruDalam],
    ['GELAP · teks samar di biru sudut', T.gSamarDiBiru, T.gBiru],
    ['GELAP · teks samar di biru dalam', T.gSamarDiBiru, T.gBiruDalam],
    ['GELAP · teks redup di merah sudut', T.gTeksMerah, T.gMerah],
    ['GELAP · teks samar di merah sudut', T.gTeksMerahRedup, T.gMerah],
    ['GELAP · teks kontrol nonaktif di latar', T.gTeksMati, T.gLatar],
    ['GELAP · hijau hidup di latar', T.gHidup, T.gLatar],
    ['GELAP · hijau hidup di panel', T.gHidup, T.gPanel],
    ['GELAP · tepi petak di latar (non-teks)', T.gTepiPetak, T.gLatar],
    ['GELAP · tepi petak di panel (non-teks)', T.gTepiPetak, T.gPanel],
    ['GELAP · tepi petak di bidang merah (non-teks)', T.gTepiPetak, T.gMerahDalam],
    ['GELAP · tepi petak di bidang biru (non-teks)', T.gTepiPetak, T.gBiruDalam],
];

// Tekstur harus SAMAR. Diuji terbalik: kalau ia melewati ambang ini, ia sudah
// terbaca sebagai garis pembatas dan bukan lagi tekstur.
const TEKSTUR = [];

let gagal = 0;

for (const [nama, depan, belakang] of PASANGAN) {
    const r = rasio(depan, belakang);
    const ambang = /non-teks/.test(nama) ? 3 : 4.5;
    const lolos = r >= ambang;
    if (!lolos) gagal++;
    console.log(`${lolos ? 'OK   ' : 'GAGAL'} ${r.toFixed(2).padStart(6)}  (min ${ambang})  ${nama}  ${depan} / ${belakang}`);
}

for (const [nama, depan, belakang, batas] of TEKSTUR) {
    const r = rasio(depan, belakang);
    const lolos = r <= batas;
    if (!lolos) gagal++;
    console.log(`${lolos ? 'OK   ' : 'GAGAL'} ${r.toFixed(2).padStart(6)}  (maks ${batas})  ${nama}  ${depan} / ${belakang}`);
}

const total = PASANGAN.length + TEKSTUR.length;
console.log(`\n${total - gagal}/${total} lolos, ${gagal} gagal`);

process.exit(gagal === 0 ? 0 : 1);
