// K3. Hari pertandingan di dua node: satu partai penuh di laptop gelanggang,
// lalu hasilnya pulang ke node global, dan perubahan global menyusul ke
// gelanggang.
//
// Blok K dan K2 membuktikan pemasangannya. Yang belum pernah dijalankan:
// seluruh alur pertandingan DI NODE GELANGGANG dengan penjaga penulis
// menyala. Penjaga itu menolak node gelanggang menulis data kejuaraan, dan
// satu penulisan sah yang kebetulan menyentuh tabel global -- saat partai
// diakhiri, saat pemenang naik -- akan membuat partai tidak bisa ditutup di
// depan penonton.
//
// Prasyarat: node B sudah terpasang dari nol (k-multinode.mjs), node global
// menyala di QA_NODE_GLOBAL, node B di QA_NODE_GELANGGANG.

import { bukaPeramban, jalankan, laporkan, masuk, pastikan } from './bantu.mjs';

const G = process.env.QA_NODE_GLOBAL ?? 'http://127.0.0.2:8000';
const B = process.env.QA_NODE_GELANGGANG ?? 'http://127.0.0.4:8010';
const T = Number(process.env.QA_TURNAMEN ?? 1);
const ARENA_B = Number(process.env.QA_ARENA_B ?? 2);
const PARTAI = Number(process.env.QA_PARTAI_B ?? 2);
const ID = JSON.parse(process.env.QA_ID_AKUN ?? '{}');

pastikan(ID.wasit && ID.juri?.length === 3, 'QA_ID_AKUN wajib: {"wasit":10,"juri":[14,15,16]}');

const browser = await bukaPeramban();

const ketuaG = await masuk(browser, 'ketua@silat.test', { asal: G });
const ketuaB = await masuk(browser, 'ketua@silat.test', { asal: B });
const pengendaliB = await masuk(browser, 'pengendali2@silat.test', { asal: B });
const wasitB = await masuk(browser, 'wasit2@silat.test', { asal: B });
const juriB = [];

for (const n of [4, 5, 6]) {
    juriB.push(await masuk(browser, `juri${n}@silat.test`, { asal: B }));
}

/** POST JSON dari halaman yang sudah login, lewat token CSRF-nya sendiri. */
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

/** Menarik satu peer sampai habis, lewat endpoint yang ditekan tombol Tarik. */
async function tarikSampaiHabis(sesi, asal, peer) {
    await sesi.halaman.goto(`${asal}/admin/sinkron`, { waitUntil: 'domcontentloaded' });

    for (let i = 0; i < 60; i++) {
        const b = await kirim(sesi, `${asal}/admin/sinkron/tarik`, { peer });
        pastikan(b.status === 200, `tarik ${peer} dijawab ${b.status}: ${b.badan.slice(0, 200)}`);

        const hasil = JSON.parse(b.badan);

        if (hasil.selesai) {
            return hasil;
        }
    }

    throw new Error(`penarikan ${peer} tidak selesai dalam 60 potongan`);
}

/*
 * Semua penulisan di node gelanggang diperiksa untuk satu kalimat: kalau
 * penjaga penulis salah menolak aksi pertandingan yang sah, jawabannya 422
 * dengan kata "data kejuaraan".
 */
function bukanPenolakanPenjaga(balasan, aksi) {
    pastikan(
        ! /data kejuaraan/i.test(balasan.badan),
        `${aksi} ditolak penjaga penulis: ${balasan.badan.slice(0, 200)}`,
    );
}

await jalankan('K-16', 'Node global menugaskan aparat Gelanggang B', async () => {
    await ketuaG.halaman.goto(`${G}/admin/turnamen/${T}/gelanggang`, { waitUntil: 'domcontentloaded' });

    const b = await kirim(ketuaG, `${G}/admin/turnamen/${T}/gelanggang/${ARENA_B}/aparat`, {
        wasit_id: ID.wasit,
        juri_id: ID.juri,
    });

    pastikan(b.status < 400, `penugasan aparat dijawab ${b.status}: ${b.badan.slice(0, 200)}`);

    return 'wasit dan tiga juri ditugaskan di node global';
}, { halaman: ketuaG.halaman });

await jalankan('K-17', 'Node gelanggang menerima kursi aparatnya lewat sinkron', async () => {
    const hasil = await tarikSampaiHabis(ketuaB, B, 'global');

    return `kursor ${hasil.kursor}, penarikan selesai`;
}, { halaman: ketuaB.halaman });

await jalankan('K-18', 'Menayangkan partai di node gelanggang menyalin aparat ke partainya', async () => {
    await pengendaliB.halaman.goto(`${B}/admin/turnamen/${T}/gelanggang/${ARENA_B}/panel/kendali`, { waitUntil: 'domcontentloaded' });

    const b = await kirim(pengendaliB, `${B}/admin/turnamen/${T}/gelanggang/${ARENA_B}/panel/partai-aktif`, { match_id: PARTAI });

    bukanPenolakanPenjaga(b, 'menayangkan partai');
    pastikan(b.status < 400, `tayang dijawab ${b.status}: ${b.badan.slice(0, 200)}`);

    return `partai ${PARTAI} tayang di Gelanggang B`;
}, { halaman: pengendaliB.halaman });

await jalankan('K-19', 'Babak berjalan: juri menilai dan wasit menghukum tanpa ditolak penjaga', async () => {
    const partai = `${B}/admin/turnamen/${T}/partai/${PARTAI}`;

    const mulai = await kirim(pengendaliB, `${partai}/timer/mulai`, { babak: 1 });
    bukanPenolakanPenjaga(mulai, 'memulai babak');
    pastikan(mulai.status < 400, `mulai babak dijawab ${mulai.status}: ${mulai.badan.slice(0, 200)}`);

    for (const [i, sesi] of juriB.entries()) {
        await sesi.halaman.goto(`${B}/admin/turnamen/${T}/gelanggang/${ARENA_B}/panel/juri`, { waitUntil: 'domcontentloaded' });

        const nilai = await kirim(sesi, `${partai}/nilai`, { babak: 1, corner: 'red', jenis: 'pukulan' });
        bukanPenolakanPenjaga(nilai, `nilai juri ${i + 4}`);
        pastikan(nilai.status < 400, `nilai juri ${i + 4} dijawab ${nilai.status}: ${nilai.badan.slice(0, 200)}`);
    }

    await wasitB.halaman.goto(`${B}/admin/turnamen/${T}/gelanggang/${ARENA_B}/panel/wasit`, { waitUntil: 'domcontentloaded' });

    const hukuman = await kirim(wasitB, `${partai}/hukuman`, { babak: 1, corner: 'blue', tingkat: 'ringan' });
    bukanPenolakanPenjaga(hukuman, 'hukuman wasit');
    pastikan(hukuman.status < 400, `hukuman dijawab ${hukuman.status}: ${hukuman.badan.slice(0, 200)}`);

    return 'tiga nilai dan satu hukuman diterima';
}, { halaman: pengendaliB.halaman });

await jalankan('K-20', 'Partai diakhiri dan disahkan di node gelanggang', async () => {
    const partai = `${B}/admin/turnamen/${T}/partai/${PARTAI}`;

    const akhiri = await kirim(pengendaliB, `${partai}/akhiri`, { corner: 'red', sebab: 'angka' });
    bukanPenolakanPenjaga(akhiri, 'mengakhiri partai');
    pastikan(akhiri.status < 400, `akhiri dijawab ${akhiri.status}: ${akhiri.badan.slice(0, 200)}`);

    await ketuaB.halaman.goto(`${B}/admin/turnamen/${T}/gelanggang/${ARENA_B}/panel/ketua`, { waitUntil: 'domcontentloaded' });

    const sahkan = await kirim(ketuaB, `${partai}/sahkan`, {});
    bukanPenolakanPenjaga(sahkan, 'mengesahkan hasil');
    pastikan(sahkan.status < 400, `sahkan dijawab ${sahkan.status}: ${sahkan.badan.slice(0, 200)}`);

    return 'partai selesai dan sah';
}, { halaman: ketuaB.halaman });

await jalankan('K-21', 'Hasil partai pulang ke node global', async () => {
    const hasil = await tarikSampaiHabis(ketuaG, G, 'gelanggang-b');

    return `node global menarik gelanggang-b sampai kursor ${hasil.kursor}`;
}, { halaman: ketuaG.halaman });

await browser.close();
process.exit(laporkan('K3. Hari pertandingan di dua node') === 0 ? 0 : 1);
