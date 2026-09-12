// K5. Pemenang yang naik melintasi batas node, di dua mesin sungguhan.
//
// Blok K3 memainkan satu partai di node gelanggang, tapi partai itu final
// bagan berukuran dua: tidak ada babak berikutnya, jadi promosi tidak pernah
// benar-benar diuji di dua mesin. Blok ini menyusun bagan berukuran empat,
// menjadwalkan KEDUA semifinal ke Gelanggang B, dan sengaja MEMBIARKAN
// finalnya tanpa gelanggang -- partai seperti itu dimiliki node global, jadi
// node globallah yang harus menurunkan siapa yang naik dari hasil yang
// ditariknya dari Gelanggang B.
//
// Tiga cacat yang dijaga blok ini, ketiganya diam dan tidak pernah muncul
// sebagai galat:
//
//   promosi      sudut final tidak pernah terisi, sementara panel menyatakan
//                partainya siap ditayangkan -- pesilat dipanggil tanpa nama.
//   penerusan    node global menyimpan yang ia terima tanpa meneruskannya,
//                jadi gelanggang lain tidak pernah tahu hasil tetangganya.
//   pivot atlet  pendaftaran yang lahir sesudah pemasangan tiba di gelanggang
//                tanpa satu atlet pun.
//
// Prasyarat: node B sudah terpasang dari nol (k-multinode.mjs), kelas QA
// berisi empat pendaftaran sah, dan bagannya BELUM tersusun.

import { execSync } from 'node:child_process';

import { bukaPeramban, jalankan, laporkan, masuk, pastikan } from './bantu.mjs';

const G = process.env.QA_NODE_GLOBAL ?? 'http://127.0.0.2:8000';
const B = process.env.QA_NODE_GELANGGANG ?? 'http://127.0.0.4:8010';
const T = Number(process.env.QA_TURNAMEN ?? 1);
const KELAS = Number(process.env.QA_KELAS ?? 0);
const KONTINGEN = Number(process.env.QA_KONTINGEN ?? 0);
const ARENA_B = Number(process.env.QA_ARENA_B ?? 2);
const AKUN = process.env.QA_AKUN_ADMIN ?? 'operator@silat.test';
const ID = JSON.parse(process.env.QA_ID_AKUN ?? '{"wasit":10,"juri":[14,15,16]}');

pastikan(KELAS > 0 && KONTINGEN > 0, 'QA_KELAS dan QA_KONTINGEN wajib diisi');

const browser = await bukaPeramban();

const adminG = await masuk(browser, AKUN, { asal: G });

// Menarik dari peer dijaga `sinkron-gelanggang`, yang dipegang Ketua
// Pertandingan -- bukan meja administrasi yang menyusun bagan.
const ketuaG = await masuk(browser, 'ketua@silat.test', { asal: G });
const ketuaB = await masuk(browser, 'ketua@silat.test', { asal: B });
const operatorB = await masuk(browser, 'operator2@silat.test', { asal: B });
const pengendaliB = await masuk(browser, 'pengendali2@silat.test', { asal: B });
const juriB = [];

for (const n of [4, 5, 6]) {
    juriB.push(await masuk(browser, `juri${n}@silat.test`, { asal: B }));
}

/*
 * Data disiapkan dan dibaca lewat scripts/qa/k5-data.php, bukan lewat
 * `tinker --execute`: perintah panjang berisi tanda kutip melewati cmd.exe
 * milik Node di Windows dan luruh di tengah jalan, lalu gagal dengan pesan
 * yang menyebut perintahnya sendiri, bukan sebabnya.
 */
function data(aksi, tambahan = {}) {
    const keluaran = execSync('php artisan tinker scripts/qa/k5-data.php', {
        cwd: process.cwd(),
        stdio: 'pipe',
        env: { ...process.env, QA_AKSI: aksi, ...tambahan },
    }).toString();

    const baris = keluaran.split('\n').map((s) => s.trim()).find((s) => s.startsWith('HASIL='));

    pastikan(baris !== undefined, `pembantu data tidak menjawab: ${keluaran.slice(-200)}`);

    return JSON.parse(baris.slice('HASIL='.length));
}

const kirim = (sesi, url, muatan = {}) => sesi.halaman.evaluate(async ([u, m]) => {
    const res = await fetch(u, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        },
        body: JSON.stringify(m),
    });

    return { status: res.status, badan: (await res.text()).slice(0, 400) };
}, [url, muatan]);

/** Menarik satu peer sampai habis; mengembalikan jumlah seluruh potongan. */
async function tarik(sesi, asal, peer) {
    await sesi.halaman.goto(`${asal}/admin/sinkron`, { waitUntil: 'domcontentloaded' });

    const jumlah = { diterapkan: 0, dihapus: 0, ditolak: 0, potongan: 0 };

    for (let i = 0; i < 60; i++) {
        const b = await kirim(sesi, `${asal}/admin/sinkron/tarik`, { peer });
        pastikan(b.status === 200, `tarik ${peer} dijawab ${b.status}: ${b.badan.slice(0, 200)}`);

        const h = JSON.parse(b.badan);
        jumlah.diterapkan += h.diterapkan;
        jumlah.dihapus += h.dihapus;
        jumlah.ditolak += h.ditolak;
        jumlah.potongan++;

        if (h.selesai) {
            return jumlah;
        }
    }

    throw new Error(`penarikan ${peer} tidak selesai dalam 60 potongan`);
}

/** Satu partai dimainkan sampai disahkan, seluruhnya di node gelanggang. */
async function mainkan(partaiId) {
    const partai = `${B}/admin/turnamen/${T}/partai/${partaiId}`;

    await pengendaliB.halaman.goto(`${B}/admin/turnamen/${T}/gelanggang/${ARENA_B}/panel/kendali`, { waitUntil: 'domcontentloaded' });

    const tayang = await kirim(pengendaliB, `${B}/admin/turnamen/${T}/gelanggang/${ARENA_B}/panel/partai-aktif`, { match_id: partaiId });
    pastikan(tayang.status < 400, `tayang partai ${partaiId} dijawab ${tayang.status}: ${tayang.badan.slice(0, 200)}`);

    const mulai = await kirim(pengendaliB, `${partai}/timer/mulai`, { babak: 1 });
    pastikan(mulai.status < 400, `mulai babak partai ${partaiId} dijawab ${mulai.status}: ${mulai.badan.slice(0, 200)}`);

    for (const sesi of juriB) {
        await sesi.halaman.goto(`${B}/admin/turnamen/${T}/gelanggang/${ARENA_B}/panel/juri`, { waitUntil: 'domcontentloaded' });

        const nilai = await kirim(sesi, `${partai}/nilai`, { babak: 1, corner: 'red', jenis: 'pukulan' });
        pastikan(nilai.status < 400, `nilai partai ${partaiId} dijawab ${nilai.status}: ${nilai.badan.slice(0, 200)}`);
    }

    const akhiri = await kirim(pengendaliB, `${partai}/akhiri`, { corner: 'red', sebab: 'angka' });
    pastikan(akhiri.status < 400, `akhiri partai ${partaiId} dijawab ${akhiri.status}: ${akhiri.badan.slice(0, 200)}`);

    await ketuaB.halaman.goto(`${B}/admin/turnamen/${T}/gelanggang/${ARENA_B}/panel/ketua`, { waitUntil: 'domcontentloaded' });

    const sahkan = await kirim(ketuaB, `${partai}/sahkan`, {});
    pastikan(sahkan.status < 400, `sahkan partai ${partaiId} dijawab ${sahkan.status}: ${sahkan.badan.slice(0, 200)}`);
}

/** Isi halaman bagan kelas ini, dibaca dari node mana pun. */
async function halamanBagan(sesi, asal) {
    await sesi.halaman.goto(`${asal}/admin/turnamen/${T}/bagan/${KELAS}`, { waitUntil: 'domcontentloaded' });

    return sesi.halaman.innerText('body');
}

let partai = { satu: 0, dua: 0, final: 0 };
let pemenang = [];

await jalankan('K-27', 'Bagan berukuran empat tersusun di node global', async () => {
    await adminG.halaman.goto(`${G}/admin/turnamen/${T}/bagan`, { waitUntil: 'domcontentloaded' });

    const b = await kirim(adminG, `${G}/admin/turnamen/${T}/bagan/${KELAS}/susun`, { mode: 'gugur' });
    pastikan(b.status < 400, `susun bagan dijawab ${b.status}: ${b.badan.slice(0, 200)}`);

    const daftar = data('partai');

    pastikan(daftar.length === 3, `bagan empat peserta harusnya 3 partai, dapat ${daftar.length}`);

    partai = { satu: daftar[0], dua: daftar[1], final: daftar[2] };

    return `partai ${partai.satu} dan ${partai.dua} (semifinal), ${partai.final} (final)`;
}, { halaman: adminG.halaman });

await jalankan('K-28', 'Kedua semifinal dijadwalkan ke Gelanggang B, finalnya sengaja dibiarkan', async () => {
    await adminG.halaman.goto(`${G}/admin/turnamen/${T}/jadwal`, { waitUntil: 'domcontentloaded' });

    for (const id of [partai.satu, partai.dua]) {
        const b = await kirim(adminG, `${G}/admin/turnamen/${T}/jadwal/${id}/tetapkan`, { arena_id: ARENA_B });
        pastikan(b.status < 400, `tetapkan partai ${id} dijawab ${b.status}: ${b.badan.slice(0, 200)}`);
    }

    /*
     * Final dibiarkan tanpa gelanggang dengan sengaja: partai yang belum
     * dijadwalkan dimiliki node global, dan itulah keadaan yang membuat
     * promosi harus diturunkan di sana, bukan dikirim dari Gelanggang B.
     */
    const final = data('keadaan', { QA_PARTAI: String(partai.final) });

    pastikan(final.arena_id === null, `final seharusnya belum dijadwalkan, tapi ada di gelanggang ${final.arena_id}`);

    return 'dua semifinal di Gelanggang B, final tanpa gelanggang';
}, { halaman: adminG.halaman });

await jalankan('K-29', 'Gelanggang B menerima kursi aparatnya dari node global', async () => {
    await ketuaG.halaman.goto(`${G}/admin/turnamen/${T}/gelanggang`, { waitUntil: 'domcontentloaded' });

    const b = await kirim(ketuaG, `${G}/admin/turnamen/${T}/gelanggang/${ARENA_B}/aparat`, {
        wasit_id: ID.wasit,
        juri_id: ID.juri,
    });

    pastikan(b.status < 400, `penugasan aparat dijawab ${b.status}: ${b.badan.slice(0, 200)}`);

    const j = await tarik(ketuaB, B, 'global');

    /*
     * Diperiksa lewat halaman bagan, bukan antrean panel kendali: antrean
     * memuat seluruh partai gelanggang dan partai baru duduk di ujungnya.
     * Membaca layar yang salah pernah membuat data yang sudah tiba terbaca
     * "tidak sampai".
     *
     * Nama pesilat, bukan sekadar nomor partai: nama datang lewat pivot
     * pendaftaran-atlet, dan pivot itulah yang dulu tidak pernah tercatat.
     */
    const teks = await halamanBagan(operatorB, B);
    const nama = [...teks.matchAll(/Pesilat QA \d/g)].map((m) => m[0]);

    pastikan(nama.length >= 4, `nama pesilat tidak lengkap di bagan node gelanggang: ${nama.join(', ')}`);

    return `${j.diterapkan} baris diterapkan, ${nama.length} nama tampil`;
}, { halaman: operatorB.halaman });

await jalankan('K-30', 'Kedua semifinal dimainkan sampai sah di node gelanggang', async () => {
    await mainkan(partai.satu);
    await mainkan(partai.dua);

    return 'dua partai selesai dan disahkan di Gelanggang B';
}, { halaman: pengendaliB.halaman });

await jalankan('K-31', 'Node global menurunkan kedua pemenang ke final yang belum dijadwalkan', async () => {
    const j = await tarik(ketuaG, G, 'gelanggang-b');

    const teks = await halamanBagan(adminG, G);

    /*
     * Yang diperiksa nama, di halaman bagan yang dibaca panitia -- bukan
     * kolom di basis data. Final berisi dua nama berarti kedua pemenang
     * benar-benar naik.
     */
    pemenang = [...teks.matchAll(/Pesilat QA \d/g)].map((m) => m[0]);

    const final = data('keadaan', { QA_PARTAI: String(partai.final) });

    pastikan(
        final.red !== null && final.blue !== null,
        `sudut final masih kosong sesudah hasil ditarik: merah=${final.red} biru=${final.blue}`,
    );

    pastikan(pemenang.length >= 4, `halaman bagan node global tidak menampilkan nama: ${teks.slice(0, 200)}`);

    return `${j.diterapkan} baris ditarik, final diisi ${final.red} lawan ${final.blue}`;
}, { halaman: adminG.halaman });

await jalankan('K-32', 'Final yang terisi di node global sampai ke node gelanggang', async () => {
    const j = await tarik(ketuaB, B, 'global');

    const teks = await halamanBagan(operatorB, B);

    const namaDiFinal = [...teks.matchAll(/Pesilat QA \d/g)].map((m) => m[0]);

    pastikan(namaDiFinal.length >= 4, `halaman bagan node gelanggang tidak lengkap: ${namaDiFinal.join(', ')}`);

    /*
     * Baris milik Gelanggang B yang dikirim balik node global ditolak di sini.
     * Angka itu bukti penerusan bekerja: node global memang menyiarkan ulang
     * apa yang ia terima, dan pemutus lingkaran menahan yang kembali ke
     * pemiliknya.
     */
    pastikan(j.ditolak > 0, 'tidak ada baris milik sendiri yang dipantulkan -- node global tampaknya tidak meneruskan apa pun');

    return `${j.diterapkan} diterapkan, ${j.ditolak} ditolak (pantulan milik sendiri)`;
}, { halaman: operatorB.halaman });

await jalankan('K-33', 'Pendaftaran yang lahir sesudah pemasangan tiba lengkap dengan atletnya', async () => {
    const susulan = data('susulan');

    pastikan(susulan.atlet > 0, `pendaftaran susulan gagal dibuat: ${JSON.stringify(susulan)}`);

    await tarik(ketuaB, B, 'global');

    await operatorB.halaman.goto(`${B}/admin/turnamen/${T}/kontingen/${KONTINGEN}/atlet?q=Susulan`, { waitUntil: 'domcontentloaded' });

    const teks = await operatorB.halaman.innerText('body');

    pastikan(/Pesilat QA Susulan/.test(teks), 'atlet susulan tidak tampil di node gelanggang');

    return 'atlet susulan tampil di node gelanggang';
}, { halaman: operatorB.halaman });

await browser.close();
process.exit(laporkan('K5. Promosi lintas node') === 0 ? 0 : 1);
