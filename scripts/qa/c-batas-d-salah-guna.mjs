// C. Skala nilai -- Boundary Value Analysis.
// D. Error guessing: pelepasan jadwal dan penetapan pemenang ganda.

import { ASAL, bukaPeramban, hasil, jalankan, kirim, laporkan, masuk, pastikan, GELANGGANG, TURNAMEN } from './bantu.mjs';

const T = TURNAMEN;
const ARENA = GELANGGANG;

const browser = await bukaPeramban();
const ketua = await masuk(browser, 'ketua@silat.test');
const pengendali = await masuk(browser, 'pengendali2@silat.test');
const operator = await masuk(browser, 'operator2@silat.test');
const juri = [];

for (const n of [1, 2, 3, 4]) {
    juri.push(await masuk(browser, `juri${n}@silat.test`));
}

const panel = (peran) => `${ASAL}/admin/turnamen/${T}/gelanggang/${ARENA}/panel/${peran}`;
const jadwalJurus = `${ASAL}/admin/turnamen/${T}/jadwal/jurus`;

const baca = (h, pilih, jalur) => h.evaluate(([p, j]) => {
    const el = document.querySelector(p);
    if (! el) return null;
    return j.split('.').reduce((o, k) => (o == null ? null : o[k]), window.Alpine.$data(el));
}, [pilih, jalur]);

async function jadwalkanBattle(label = 'Gelanggang B') {
    await ketua.halaman.goto(jadwalJurus, { waitUntil: 'domcontentloaded' });

    const form = ketua.halaman.locator('form[action*="/jadwal/jurus/battle/"][action$="/tetapkan"]').first();
    const id = (await form.getAttribute('action')).match(/battle\/(\d+)\/tetapkan/)[1];

    await form.locator('select[name="arena_id"]').selectOption({ label });
    await Promise.all([
        ketua.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        form.locator('button[type="submit"]').click(),
    ]);

    return id;
}

async function tayangkanJurus(sesi) {
    await sesi.halaman.goto(panel('kendali'), { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForFunction(
        () => document.body.innerText.includes('Antrean Jurus'), null, { timeout: 20_000 },
    );

    const tombol = sesi.halaman.locator(
        'xpath=//p[normalize-space()="Antrean Jurus"]'
        + '/ancestor::div[.//button[normalize-space()="Tayangkan"]][1]'
        + '//button[normalize-space()="Tayangkan"]',
    );

    pastikan(await tombol.count() > 0, 'tidak ada tombol Tayangkan di Antrean Jurus');
    await tombol.first().click();
    await sesi.halaman.waitForTimeout(1800);
}

async function jalankanTimer(sesi) {
    await sesi.halaman.goto(panel('papan'), { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForSelector('button:text-is("Mulai")', { timeout: 20_000 });
    await sesi.halaman.locator('button:text-is("Mulai")').first().click();
    await sesi.halaman.waitForFunction(() => document.body.innerText.includes('Sedang tampil'), null, { timeout: 20_000 });
    await sesi.halaman.waitForTimeout(1100);
    await sesi.halaman.locator('button:text-is("Selesai")').first().click();
    await sesi.halaman.waitForFunction(() => document.body.innerText.includes('Selesai'), null, { timeout: 20_000 });
}

/** Menekan deret digit di papan tik juri, tanpa mengirim. */
async function ketik(sesi, digit) {
    await sesi.halaman.goto(panel('juri'), { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForSelector('button:has-text("Kirim nilai")', { timeout: 20_000 });
    await sesi.halaman.locator('button:has-text("Ulangi")').click();

    for (const d of digit) {
        await sesi.halaman.locator(`button:text-is("${d}")`).first().click();
    }
}

async function nilai(sesi, angka) {
    await ketik(sesi, String(angka).replace('.', ''));
    await sesi.halaman.locator('button:has-text("Kirim nilai")').click();
    await sesi.halaman.waitForFunction(() => document.body.innerText.includes('Terkirim:'), null, { timeout: 20_000 });
}

async function sahkan(sesi) {
    await sesi.halaman.goto(panel('papan'), { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForSelector('button:has-text("Sahkan skor akhir")', { timeout: 20_000 });
    await sesi.halaman.locator('button:has-text("Sahkan skor akhir")').click();
    await sesi.halaman.waitForTimeout(1500);
}

const battleUtama = await jadwalkanBattle();
await tayangkanJurus(pengendali);
await jalankanTimer(operator);

// ------------------------------------------------------------------ C
for (const [id, digit, tampil, sah] of [
    ['C-01', '899', '8.99', false],
    ['C-02', '900', '9.00', true],
    ['C-03', '1000', '10.00', true],
    ['C-04', '1001', '10.01', false],
]) {
    await jalankan(id, `Papan tik ${tampil}: ${sah ? 'diterima' : 'ditolak sebelum dikirim'}`, async () => {
        await ketik(juri[0], digit);

        const terbaca = await juri[0].halaman.locator('span[aria-live="polite"]').first().innerText();
        pastikan(terbaca.trim() === tampil, `layar menampilkan "${terbaca.trim()}", diharap "${tampil}"`);

        const mati = await juri[0].halaman.locator('button:has-text("Kirim nilai")').isDisabled();
        pastikan(mati === ! sah, `tombol Kirim ${mati ? 'mati' : 'hidup'}, diharap ${sah ? 'hidup' : 'mati'}`);

        if (! sah) {
            const teks = await juri[0].halaman.innerText('body');
            pastikan(/hanya antara 9\.00 dan 10\.00/i.test(teks), 'batas tidak disebutkan di layar');
        }

        return `${tampil} -> Kirim ${mati ? 'mati' : 'hidup'}`;
    }, { halaman: juri[0].halaman });
}

await jalankan('C-05', 'Nilai berdesimal tiga ditolak server, dan mustahil diketik', async () => {
    // Papan tik menampung maksimal empat digit, jadi 9.705 tidak bisa disusun
    // sama sekali -- "9705" terbaca 97.05 dan langsung di luar rentang.
    await ketik(juri[0], '9705');
    const terbaca = await juri[0].halaman.locator('span[aria-live="polite"]').first().innerText();
    pastikan(terbaca.trim() === '97.05', `papan tik menyusun "${terbaca.trim()}"`);

    // Sisi server tetap diuji, karena panel bukan satu-satunya pintu.
    const alamat = await baca(juri[0].halaman, '[x-data^="jurusPanel"]', 'cfg.nilai');
    const balasan = await kirim(juri[0].halaman, alamat, { value: 9.705 });

    pastikan(balasan.status >= 400, `server menerima 9.705 dengan status ${balasan.status}`);

    return `papan tik menolak; server ${balasan.status}`;
}, { halaman: juri[0].halaman });

await jalankan('C-06', 'Pengesahan ditolak selagi juri ganjil / kurang dari empat', async () => {
    for (const sesi of juri.slice(0, 3)) {
        await nilai(sesi, '9.70');
    }

    const jumlah = await baca(operator.halaman, '[x-data^="jurusPanel"]', 'nilaiJuri.length');

    await sahkan(ketua);

    const disahkan = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'performance.ratified');
    const galat = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'galat');

    pastikan(disahkan !== true, 'pengesahan lolos padahal juri baru tiga');
    pastikan(galat, 'penolakan tidak menyebutkan sebabnya');

    return `tiga juri (${jumlah}) ditolak: ${String(galat).slice(0, 90)}`;
}, { halaman: ketua.halaman });

// ------------------------------------------------------------------ D
await jalankan('D-03', 'Melepas battle yang salah satu sudutnya SEDANG ditayangkan ditolak', async () => {
    await ketua.halaman.goto(jadwalJurus, { waitUntil: 'domcontentloaded' });

    const form = ketua.halaman.locator(`form[action$="/jadwal/jurus/battle/${battleUtama}/lepas"]`).first();
    pastikan(await form.count() > 0, 'tombol Lepas battle tidak ada di antrean gelanggang');

    await Promise.all([
        ketua.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        form.locator('button[type="submit"]').click(),
    ]);

    const teks = await ketua.halaman.innerText('body');
    pastikan(/sedang ditayangkan/i.test(teks), `pesan penolakan tidak muncul: ${teks.slice(0, 200)}`);

    return 'ditolak: sedang ditayangkan';
}, { halaman: ketua.halaman });

// Selesaikan battle utama supaya D-04 dan D-08 punya battle yang sudah usai.
await jalankan('D-00', 'Selesaikan battle utama untuk menyiapkan D-04 dan D-08', async () => {
    await nilai(juri[3], '9.70');
    await sahkan(ketua);

    await tayangkanJurus(pengendali);
    await jalankanTimer(operator);

    for (const sesi of juri) {
        await nilai(sesi, '9.60');
    }

    await sahkan(ketua);

    await ketua.halaman.goto(panel('ketua'), { waitUntil: 'domcontentloaded' });
    await ketua.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.komparasi?.siap === true,
        null, { timeout: 20_000 },
    );

    return 'kedua sudut disahkan, komparasi siap';
}, { halaman: ketua.halaman });

await jalankan('D-08', 'Tetapkan pemenang ditekan dua kali tidak melahirkan keputusan ganda', async () => {
    const tombol = ketua.halaman.locator('button:has-text("Tetapkan pemenang")');
    await tombol.click();
    await tombol.click({ force: true }).catch(() => {});

    await ketua.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.komparasi?.battle?.selesai === true,
        null, { timeout: 20_000 },
    );

    // Tekanan ketiga lewat jalur HTTP: server harus menolaknya.
    const balasan = await kirim(ketua.halaman, `${ASAL}/admin/turnamen/${T}/jurus/battle/${battleUtama}/putuskan`, {});
    pastikan(balasan.status >= 400, `penetapan kedua diterima dengan status ${balasan.status}`);

    const sebab = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.battle.win_reason');

    return `sebab tetap "${sebab}"; penetapan ulang ditolak ${balasan.status}`;
}, { halaman: ketua.halaman });

await jalankan('D-04', 'Menjadwalkan battle yang sudah SELESAI ditolak', async () => {
    const balasan = await kirim(
        ketua.halaman,
        `${ASAL}/admin/turnamen/${T}/jadwal/jurus/battle/${battleUtama}/tetapkan`,
        { arena_id: ARENA },
    );

    pastikan(balasan.status >= 400, `status ${balasan.status}, diharap penolakan`);

    return `ditolak ${balasan.status}`;
}, { halaman: ketua.halaman });

await jalankan('D-02', 'Melepas battle mengeluarkan KEDUA sudut dari antrean', async () => {
    const kedua = await jadwalkanBattle();

    const sebelum = await ketua.halaman.evaluate(() => document.querySelectorAll('input[name="urutan"]').length);

    // Pointer masih menunjuk battle utama, jadi battle kedua bebas dilepas.
    const form = ketua.halaman.locator(`form[action$="/jadwal/jurus/battle/${kedua}/lepas"]`).first();
    pastikan(await form.count() > 0, 'tombol Lepas battle tidak ada');

    await Promise.all([
        ketua.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        form.locator('button[type="submit"]').click(),
    ]);

    const sesudah = await ketua.halaman.evaluate(() => document.querySelectorAll('input[name="urutan"]').length);

    pastikan(sesudah === sebelum - 2, `baris antrean ${sebelum} -> ${sesudah}, diharap berkurang 2`);

    return `antrean ${sebelum} -> ${sesudah}`;
}, { halaman: ketua.halaman });

await browser.close();
process.exit(laporkan('C. Skala nilai & D. Error guessing') === 0 ? 0 : 1);
