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
// Token — nilainya diambil apa adanya dari project design `digiscoring`
// (BRIEF-DIGITAL-SCORING.md §2 dan DESIGN-SYSTEM.md §2). Kalau salah satu perlu
// berubah, yang diubah brief-nya lebih dulu, baru berkas ini.
// ---------------------------------------------------------------------------

const T = {
    // Netral zinc — suasana terang (admin dan wajah publik)
    kKertas: '#ffffff',
    kKertasNaik: '#ffffff',
    kKertasTurun: '#fafafa',
    kKertasDalam: '#f4f4f5',
    kGaris: '#e4e4e7',
    kTepiKendali: '#d4d4d8',
    kTinta: '#09090b',
    kTintaKedua: '#3f3f46',
    kTintaRedup: '#71717a',
    kTintaSamar: '#a1a1aa',
    kAksi: '#18181b',
    kAksiTeks: '#fafafa',
    kAksiHover: '#27272a',
    kAksiLembut: '#f4f4f5',

    // Semantik — HANYA admin. Gelanggang, overlay, bagan, dan blok sudut
    // tunduk aturan "merah dan biru hanya berarti sudut pesilat" (BRIEF §2.2).
    kSukses: '#15803d',
    kSuksesIkon: '#16a34a',
    kSuksesLembut: '#f0fdf4',
    kSuksesTepi: '#bbf7d0',
    kPerhatian: '#b45309',
    kPerhatianLembut: '#fffbeb',
    kPerhatianTepi: '#fde68a',
    kBahaya: '#b91c1c',
    kBahayaLembut: '#fef2f2',
    kBahayaTepi: '#fecaca',
    kBahayaTeks: '#ffffff',
    kInfo: '#1d4ed8',
    kInfoLembut: '#eff6ff',
    kInfoTepi: '#bfdbfe',

    // Mode gelap admin memakai ramp gelap yang sama dengan panel gelanggang.
    kdKertas: '#09090b',
    kdKertasNaik: '#18181b',
    kdKertasDalam: '#27272a',
    kdAksiLembut: '#27272a',
    kdTinta: '#fafafa',
    kdTintaKedua: '#d4d4d8',
    kdTintaRedup: '#a1a1aa',
    kdTintaSamar: '#71717a',
    kdTepiKendali: '#3f3f46',
    kdSukses: '#4ade80',
    kdSuksesLembut: '#10241a',
    kdPerhatian: '#d98324',
    kdPerhatianLembut: '#2a1d0c',
    kdBahaya: '#ff7b74',
    kdBahayaLembut: '#2c1412',
    kdBahayaTeks: '#111114',
    kdInfo: '#93c5fd',
    kdInfoLembut: '#0f1c33',

    // Inti gelap (--g-*): panel gelanggang dan overlay siaran
    gLatar: '#09090b',
    gPanel: '#18181b',
    gGaris: '#27272a',
    gTepiKendali: '#3f3f46',
    gTepiPetak: '#8a8a90',
    gTeks: '#fafafa',
    gTeksKedua: '#d4d4d8',
    gTeksRedup: '#a1a1aa',
    gTeksSamar: '#71717a',
    gTeksMati: '#b0b0b6',
    gAksi: '#e8e8ea',
    gAksiTeks: '#111114',
    gHidup: '#4ade80',
    gAwas: '#fb7185',
    gEmas: '#c9a227',

    // Sudut pesilat — terkunci, sama di seluruh permukaan
    gMerah: '#d42027',
    gMerahDalam: '#7a1418',
    gBiru: '#12439e',
    gBiruDalam: '#0c2a63',
    gTeksMerah: '#e8b4b6',
    gTeksMerahKedua: '#f0c9ca',
    gTeksBiru: '#a8b8e0',
    gTeksBiruKedua: '#c3cfeb',

    // Tangga hukuman — BRIEF §2.3
    gPembinaan: '#6b6b73',
    gPembinaanTeks: '#ffffff',
    gTeguran: '#d98324',
    gTeguranTeks: '#1a1207',
    gPeringatan: '#fafafa',
    gPeringatanTeks: '#09090b',

    // Emas di atas kertas butuh nilai yang lebih gelap daripada di panggung.
    kEmas: '#8a6d10',
    kHidup: '#16a34a',
};

// ---------------------------------------------------------------------------
// Pasangan yang diuji
//
// Ambang mengikuti DESIGN-SYSTEM.md §12: teks 4.5. Tepi kendali TIDAK lagi
// diambang 3:1 — sistem baru menetapkan tepi zinc (#d4d4d8 di terang, #3f3f46
// di gelap) sebagai tepi kendali, dan keduanya memang di bawah 3. Barisnya
// tetap diukur dan dicetak, ditandai `catat`, supaya angkanya sadar dan tidak
// berubah diam-diam. Yang masih diambang 3 adalah tepi yang MEMBAWA ARTI:
// petak hukuman dan slot bagan yang belum terisi.
// ---------------------------------------------------------------------------

const PASANGAN = [
    ['TERANG · teks utama di kertas', T.kTinta, T.kKertas],
    ['TERANG · teks utama di zona halus', T.kTinta, T.kKertasTurun],
    ['TERANG · teks utama di header tabel', T.kTinta, T.kKertasDalam],
    ['TERANG · teks kedua di kertas', T.kTintaKedua, T.kKertas],
    ['TERANG · teks redup di kertas', T.kTintaRedup, T.kKertas],
    ['TERANG · teks redup di zona halus', T.kTintaRedup, T.kKertasTurun],
    ['TERANG · teks tersier di kertas (catat)', T.kTintaSamar, T.kKertas],
    ['TERANG · tepi kendali di kertas (catat)', T.kTepiKendali, T.kKertas],
    ['TERANG · garis kartu di kertas (catat)', T.kGaris, T.kKertas],
    ['TERANG · tombol utama: teks di bidang aksi', T.kAksiTeks, T.kAksi],
    ['TERANG · tombol utama hover', T.kAksiTeks, T.kAksiHover],
    ['TERANG · penyaring aktif: teks di bidangnya', T.kAksiTeks, T.kAksi],
    ['TERANG · tombol sekunder: tinta di kertas naik', T.kTinta, T.kKertasNaik],
    ['TERANG · tombol halus: teks kedua di hover', T.kTintaKedua, T.kAksiLembut],

    ['TERANG · sukses di lembutnya', T.kSukses, T.kSuksesLembut],
    ['TERANG · sukses di kertas', T.kSukses, T.kKertas],
    ['TERANG · tepi sukses di lembutnya (catat)', T.kSuksesTepi, T.kSuksesLembut],
    ['TERANG · perhatian di lembutnya', T.kPerhatian, T.kPerhatianLembut],
    ['TERANG · perhatian di kertas', T.kPerhatian, T.kKertas],
    ['TERANG · bahaya di lembutnya', T.kBahaya, T.kBahayaLembut],
    ['TERANG · bahaya di kertas', T.kBahaya, T.kKertas],
    ['TERANG · teks di bidang bahaya', T.kBahayaTeks, T.kBahaya],
    ['TERANG · info di lembutnya', T.kInfo, T.kInfoLembut],
    ['TERANG · info di kertas', T.kInfo, T.kKertas],
    ['TERANG · emas di kertas', T.kEmas, T.kKertas],
    ['TERANG · titik hidup di kertas (non-teks)', T.kHidup, T.kKertas],
    ['TERANG · titik awas di kertas (non-teks)', '#be123c', T.kKertas],

    // Bagan dan papan publik: bidang sudut penuh, teks putih.
    ['TERANG · putih di bidang merah', '#ffffff', T.gMerahDalam],
    ['TERANG · putih di bidang biru', '#ffffff', T.gBiruDalam],
    ['TERANG · bidang merah di kertas (non-teks)', T.gMerahDalam, T.kKertas],
    ['TERANG · bidang biru di kertas (non-teks)', T.gBiruDalam, T.kKertas],

    ['GELAP-ADMIN · teks utama di kertas', T.kdTinta, T.kdKertas],
    ['GELAP-ADMIN · teks utama di kartu', T.kdTinta, T.kdKertasNaik],
    ['GELAP-ADMIN · teks kedua di kertas', T.kdTintaKedua, T.kdKertas],
    ['GELAP-ADMIN · teks redup di kertas', T.kdTintaRedup, T.kdKertas],
    ['GELAP-ADMIN · teks tersier di kertas (catat)', T.kdTintaSamar, T.kdKertas],
    ['GELAP-ADMIN · tepi kendali di kertas (catat)', T.kdTepiKendali, T.kdKertas],
    ['GELAP-ADMIN · tombol kedua: teks di bidang lembut', T.kdTinta, T.kdAksiLembut],
    ['GELAP-ADMIN · sukses di lembutnya', T.kdSukses, T.kdSuksesLembut],
    ['GELAP-ADMIN · sukses di kertas', T.kdSukses, T.kdKertas],
    ['GELAP-ADMIN · perhatian di lembutnya', T.kdPerhatian, T.kdPerhatianLembut],
    ['GELAP-ADMIN · perhatian di kertas', T.kdPerhatian, T.kdKertas],
    ['GELAP-ADMIN · bahaya di lembutnya', T.kdBahaya, T.kdBahayaLembut],
    ['GELAP-ADMIN · bahaya di kertas', T.kdBahaya, T.kdKertas],
    ['GELAP-ADMIN · teks di bidang bahaya', T.kdBahayaTeks, T.kdBahaya],
    ['GELAP-ADMIN · info di lembutnya', T.kdInfo, T.kdInfoLembut],

    ['GELAP · teks di latar', T.gTeks, T.gLatar],
    ['GELAP · teks di panel', T.gTeks, T.gPanel],
    ['GELAP · teks kedua di latar', T.gTeksKedua, T.gLatar],
    ['GELAP · teks kedua di panel', T.gTeksKedua, T.gPanel],
    ['GELAP · teks redup di latar', T.gTeksRedup, T.gLatar],
    ['GELAP · teks redup di panel', T.gTeksRedup, T.gPanel],
    ['GELAP · teks paling redup di latar (catat)', T.gTeksSamar, T.gLatar],
    ['GELAP · tepi kendali di latar (catat)', T.gTepiKendali, T.gLatar],
    ['GELAP · garis di latar (catat)', T.gGaris, T.gLatar],
    ['GELAP · teks kontrol nonaktif di latar', T.gTeksMati, T.gLatar],
    ['GELAP · tombol aksi: teks gelap di bidang terang', T.gAksiTeks, T.gAksi],
    ['GELAP · penyaring aktif: teks di bidangnya', T.gAksiTeks, T.gAksi],
    ['GELAP · hijau hidup di latar', T.gHidup, T.gLatar],
    ['GELAP · hijau hidup di panel', T.gHidup, T.gPanel],

    // Titik mutu jaringan. Non-teks: yang dibaca warnanya, angkanya
    // sendiri memakai warna teks netral. Diukur juga di atas kedua bidang
    // sudut, karena penanda sambungan duduk di header panel yang bisa
    // berlatar merah maupun biru.
    ['GELAP · titik awas di latar (non-teks)', T.gAwas, T.gLatar],
    ['GELAP · titik awas di panel (non-teks)', T.gAwas, T.gPanel],
    ['GELAP · titik awas di bidang merah (non-teks)', T.gAwas, T.gMerahDalam],
    ['GELAP · titik awas di bidang biru (non-teks)', T.gAwas, T.gBiruDalam],
    ['GELAP · emas di latar', T.gEmas, T.gLatar],
    ['GELAP · emas di panel', T.gEmas, T.gPanel],

    // Sudut pesilat
    ['SUDUT · putih di merah', '#ffffff', T.gMerah],
    ['SUDUT · putih di biru', '#ffffff', T.gBiru],
    ['SUDUT · putih di merah dalam', '#ffffff', T.gMerahDalam],
    ['SUDUT · putih di biru dalam', '#ffffff', T.gBiruDalam],
    ['SUDUT · label di merah dalam', T.gTeksMerah, T.gMerahDalam],
    ['SUDUT · baris kedua di merah dalam', T.gTeksMerahKedua, T.gMerahDalam],
    ['SUDUT · label di biru dalam', T.gTeksBiru, T.gBiruDalam],
    ['SUDUT · baris kedua di biru dalam', T.gTeksBiruKedua, T.gBiruDalam],

    // Tangga hukuman
    ['HUKUMAN · teks di pembinaan', T.gPembinaanTeks, T.gPembinaan],
    ['HUKUMAN · teks di teguran', T.gTeguranTeks, T.gTeguran],
    ['HUKUMAN · teks di peringatan', T.gPeringatanTeks, T.gPeringatan],
    ['HUKUMAN · bidang peringatan di latar (non-teks)', T.gPeringatan, T.gLatar],

    // Tepi yang MEMBAWA ARTI: petak dan slot yang belum terisi. Ini yang tetap
    // diambang 3 — "mati bukan bidang" (BRIEF §2.4) hanya berlaku kalau tepinya
    // benar-benar terlihat.
    ['MATI · tepi petak di latar (non-teks)', T.gTepiPetak, T.gLatar],
    ['MATI · tepi petak di panel (non-teks)', T.gTepiPetak, T.gPanel],
    ['MATI · tepi petak di bidang merah (non-teks)', T.gTepiPetak, T.gMerahDalam],
    ['MATI · tepi petak di bidang biru (non-teks)', T.gTepiPetak, T.gBiruDalam],
    ['MATI · tepi petak di kertas (non-teks)', T.gTepiPetak, T.kKertas],

    // Petak hukuman pindah ke DALAM batang sudut yang terang, bukan lagi di
    // bidang dalam yang gelap. Tepi petak biasa (#8a8a90) cuma 1,53 di sana --
    // deretnya hilang. Yang dipakai token teks sudut, dan dua baris ini yang
    // menjaganya tetap begitu.
    ['MATI · tepi petak di batang merah (non-teks)', T.gTeksMerahKedua, T.gMerah],
    ['MATI · tepi petak di batang biru (non-teks)', T.gTeksBiruKedua, T.gBiru],
];

let gagal = 0;
let dicatat = 0;

for (const [nama, depan, belakang] of PASANGAN) {
    const r = rasio(depan, belakang);

    // Baris bertanda `(catat)` diukur dan dicetak, tapi tidak menggagalkan:
    // nilainya ditetapkan sistem desain, dan yang dijaga di sini adalah supaya
    // ia tidak berubah tanpa ada yang melihat angkanya.
    if (/\(catat\)/.test(nama)) {
        dicatat++;
        console.log(`CATAT ${r.toFixed(2).padStart(6)}  (—)         ${nama}  ${depan} / ${belakang}`);
        continue;
    }

    const ambang = /non-teks/.test(nama) ? 3 : 4.5;
    const lolos = r >= ambang;
    if (!lolos) gagal++;
    console.log(`${lolos ? 'OK   ' : 'GAGAL'} ${r.toFixed(2).padStart(6)}  (min ${ambang})  ${nama}  ${depan} / ${belakang}`);
}

const diuji = PASANGAN.length - dicatat;
console.log(`\n${diuji - gagal}/${diuji} lolos, ${gagal} gagal, ${dicatat} dicatat tanpa ambang`);

process.exit(gagal === 0 ? 0 : 1);
