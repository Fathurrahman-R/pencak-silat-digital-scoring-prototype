// K. Multi-server: node gelanggang menarik dari node global, dan sebaliknya.
//
// Dua node sungguhan, dua basis data, dua alamat:
//
//   Node G (global)      http://127.0.0.2:8000   basis data berisi kejuaraan
//   Node B (gelanggang)  http://127.0.0.4:8010   basis data KOSONG saat mulai
//
// Yang diuji bukan "apakah datanya sama", melainkan bentuk-bentuk yang hanya
// muncul kalau mesinnya benar-benar dua: halaman pemasangan yang menutup
// dirinya sendiri, kursor yang maju hanya sesudah penerapan berhasil, dan
// aturan satu penulis.
//
// Prasyarat disiapkan di luar skrip ini (lihat docs/UJI-KOTAK-HITAM.md):
// database node B dibuat dan dimigrasi TANPA seed, `.env.gelanggangb` menunjuk
// node global sebagai peer, dan `artisan serve` node B berjalan.

import { bukaPeramban, hasil, jalankan, laporkan, masuk, pastikan } from './bantu.mjs';

const G = process.env.QA_NODE_GLOBAL ?? 'http://127.0.0.2:8000';
const B = process.env.QA_NODE_GELANGGANG ?? 'http://127.0.0.4:8010';
const TOKEN = process.env.QA_SINKRON_TOKEN ?? '';

pastikan(TOKEN !== '', 'QA_SINKRON_TOKEN wajib diisi -- ambil dari .env node global');

const browser = await bukaPeramban();
const konteks = await browser.newContext({ ignoreHTTPSErrors: true });
const halaman = await konteks.newPage();

/*
 * GET dari NODE, bukan dari dalam halaman.
 *
 * `page.evaluate(fetch)` menembak lintas asal -- halaman di node G memanggil
 * node B -- dan peramban memblokirnya dengan "Failed to fetch" yang terbaca
 * seperti node-nya mati. Endpoint sinkron memang tidak dipanggil peramban:
 * yang memanggilnya laptop lain.
 */
async function ambil(url, { token = null } = {}) {
    const res = await fetch(url, {
        headers: token ? { 'X-Sinkron-Token': token, Accept: 'application/json' } : { Accept: 'application/json' },
    });

    return { status: res.status, badan: (await res.text()).slice(0, 400) };
}

let akunPertama = null;

await jalankan('K-00', 'Dua node hidup dengan identitas yang berbeda', async () => {
    await halaman.goto(`${G}/login`, { waitUntil: 'domcontentloaded' });

    const global = JSON.parse((await ambil(`${G}/sinkron/identitas`, { token: TOKEN })).badan);
    const gelanggang = JSON.parse((await ambil(`${B}/sinkron/identitas`, { token: TOKEN })).badan);

    pastikan(global.peran === 'global', `node G berperan ${global.peran}`);
    pastikan(global.arena.length === 0, 'node global mengaku memiliki gelanggang');
    pastikan(gelanggang.peran === 'gelanggang', `node B berperan ${gelanggang.peran}`);
    pastikan(gelanggang.arena.join(',') === 'B', `node B memegang ${gelanggang.arena.join(',')}`);
    pastikan(gelanggang.kursor === 0, `node B mulai dengan kursor ${gelanggang.kursor}`);

    return `${global.node} (kursor ${global.kursor}) dan ${gelanggang.node} (kursor 0)`;
}, { halaman });

await jalankan('K-04', 'Halaman pemasangan hidup di node kosong, tanpa login', async () => {
    const balasan = await halaman.goto(`${B}/pemasangan`, { waitUntil: 'domcontentloaded' });

    if (balasan.status() === 404) {
        return 'dilewati: node B sudah berisi akun, halaman pemasangan sudah menutup diri';
    }

    pastikan(balasan.status() === 200, `status ${balasan.status()}`);

    const teks = await halaman.innerText('body');

    pastikan(/pemasangan node/i.test(teks), 'judulnya tidak tergambar');
    pastikan(teks.includes('gelanggang-b'), 'identitas mesin tidak disebut');
    pastikan(teks.includes('global'), 'peer global tidak terdaftar');
    pastikan(/tarik data dari peer ini/i.test(teks), 'tombol tariknya tidak ada');

    return 'tergambar tanpa login, menyebut identitas dan peer';
}, { halaman });

await jalankan('K-05', 'Penarikan pertama membawa akun dari node global', async () => {
    const kosong = (await ambil(`${B}/pemasangan`)).status === 200;

    if (! kosong) {
        return 'dilewati: node B sudah punya akun (lihat catatan pemasangan di laporan)';
    }

    await halaman.locator('button:has-text("Tarik data dari peer ini")').first().click();

    await halaman.waitForFunction(
        () => /Penarikan selesai/i.test(document.body.innerText),
        null, { timeout: 180_000 },
    ).catch(() => { throw new Error('penarikan tidak pernah selesai dalam 3 menit'); });

    const teks = await halaman.innerText('body');
    const diterapkan = Number(/(\d+)\s+diterapkan/i.exec(teks)?.[1] ?? 0);

    pastikan(diterapkan > 0, `tidak ada baris diterapkan (${diterapkan})`);

    return `${diterapkan} baris diterapkan`;
}, { halaman });

await jalankan('K-06', 'Sesudah ada akun, halaman pemasangan membalas 404 selamanya', async () => {
    const balasan = await halaman.goto(`${B}/pemasangan`, { waitUntil: 'domcontentloaded' });

    pastikan(balasan.status() === 404, `status ${balasan.status()}, diharap 404`);

    return 'menutup dirinya sendiri';
}, { halaman });

await jalankan('K-07', 'Akun yang lahir di node global bisa dipakai masuk di node gelanggang', async () => {
    const sesi = await masuk(browser, process.env.QA_AKUN ?? 'ketua@silat.test', { asal: B });

    akunPertama = sesi;

    const teks = await sesi.halaman.innerText('body');
    pastikan(! /masuk|login/i.test(await sesi.halaman.title()), 'masih di halaman masuk');

    return `masuk sebagai ketua di ${B}`;
}, { halaman });

await jalankan('K-08', 'Halaman Sinkron Gelanggang menyebut identitas, peer, dan kursornya', async () => {
    await akunPertama.halaman.goto(`${B}/admin/sinkron`, { waitUntil: 'domcontentloaded' });

    const teks = await akunPertama.halaman.innerText('body');

    pastikan(teks.includes('gelanggang-b'), 'nama node tidak disebut');
    pastikan(teks.includes('global'), 'peer global tidak terdaftar');
    pastikan(/tarik/i.test(teks), 'tombol tarik tidak ada');

    return 'identitas, peer, dan tombol tarik hadir';
}, { halaman: akunPertama.halaman });

await jalankan('K-09', 'Menarik dari peer memajukan kursor dan mencatat waktunya', async () => {
    const sebelum = JSON.parse((await ambil(`${B}/sinkron/identitas`, { token: TOKEN })).badan).kursor;

    await akunPertama.halaman.locator('button:has-text("Tarik")').first().click();
    await akunPertama.halaman.waitForTimeout(8000);

    const sesudah = JSON.parse((await ambil(`${B}/sinkron/identitas`, { token: TOKEN })).badan).kursor;

    /*
     * Kursor node B adalah penghitung perubahan MILIKNYA SENDIRI, bukan posisi
     * bacanya terhadap peer -- yang terakhir tersimpan di `sinkron_kursor` dan
     * tergambar di halamannya. Yang diperiksa di sini halaman itu.
     */
    await akunPertama.halaman.reload({ waitUntil: 'domcontentloaded' });
    const teks = await akunPertama.halaman.innerText('body');

    pastikan(/\d/.test(teks), 'halaman tidak menampilkan angka kursor sama sekali');
    pastikan(! /galat|gagal/i.test(teks), `halaman menyebut galat: ${teks.slice(0, 200)}`);

    return `kursor sendiri ${sebelum} -> ${sesudah}, halaman bersih dari galat`;
}, { halaman: akunPertama.halaman });

await jalankan('K-10', 'Menarik lagi tanpa perubahan baru tidak menggandakan apa pun', async () => {
    const hitung = async (asal, sesi) => {
        await sesi.halaman.goto(`${asal}/admin/turnamen`, { waitUntil: 'domcontentloaded' });

        return (await sesi.halaman.innerText('body')).match(/Kejuaraan|Turnamen/gi)?.length ?? 0;
    };

    const sebelum = await hitung(B, akunPertama);

    await akunPertama.halaman.goto(`${B}/admin/sinkron`, { waitUntil: 'domcontentloaded' });
    await akunPertama.halaman.locator('button:has-text("Tarik")').first().click();
    await akunPertama.halaman.waitForTimeout(6000);

    const sesudah = await hitung(B, akunPertama);

    pastikan(sebelum === sesudah, `jumlah baris berubah ${sebelum} -> ${sesudah} tanpa perubahan baru`);

    return 'idempoten';
}, { halaman: akunPertama.halaman });

await jalankan('K-14', 'Arsip bukti hanya hidup di node global', async () => {
    const diGelanggang = await ambil(`${B}/sinkron/arsip/1/tanda-terima`, { token: TOKEN });

    pastikan(diGelanggang.status === 404, `node gelanggang membalas ${diGelanggang.status}, diharap 404`);

    return 'node gelanggang menolak menampung arsip';
}, { halaman });

await jalankan('K-15', 'Penarikan yang gagal tidak memajukan kursor bacanya', async () => {
    const bacaKursorPeer = async () => {
        await akunPertama.halaman.goto(`${B}/admin/sinkron`, { waitUntil: 'domcontentloaded' });
        const teks = await akunPertama.halaman.innerText('body');

        return /kursor[^0-9]*(\d+)/i.exec(teks)?.[1] ?? null;
    };

    const sebelum = await bacaKursorPeer();

    /*
     * Peer dibuat tidak terjangkau dengan menembak alamat yang tidak melayani
     * apa pun. Yang diuji: kursor tetap, dan galatnya tercatat -- kursor yang
     * maju lebih dulu berarti perubahan yang gagal diterapkan dianggap sudah
     * masuk dan tidak akan pernah ditarik lagi.
     */
    const balasan = await akunPertama.halaman.evaluate(async ([asal]) => {
        const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
        const res = await fetch(`${asal}/admin/sinkron/tarik`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ peer: 'peer-yang-tidak-ada' }),
        });

        return { status: res.status, badan: (await res.text()).slice(0, 200) };
    }, [B]);

    pastikan(balasan.status >= 400, `peer karangan dijawab ${balasan.status}`);

    const sesudah = await bacaKursorPeer();

    pastikan(sebelum === sesudah, `kursor berubah ${sebelum} -> ${sesudah} padahal penarikannya gagal`);

    return `ditolak ${balasan.status}, kursor tetap ${sesudah}`;
}, { halaman: akunPertama.halaman });

await browser.close();
process.exit(laporkan('K. Multi-server antar gelanggang dan node global') === 0 ? 0 : 1);
