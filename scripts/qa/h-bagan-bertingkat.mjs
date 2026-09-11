// H. Bagan Jurus bertingkat: bye, promosi pemenang, pohon lebih dari satu kolom.
//
// Kode yang belum pernah dijalankan di peramban: generalisasi PohonBagan lewat
// SumberBagan, dan PromosiPemenang yang dipakai ulang untuk JurusBattle.
//
// Prasyarat: satu nomor Jurus berformat battle dengan LIMA peserta sah, supaya
// bagannya berukuran 8 dengan tiga bye dan tiga kolom.

import {
    ASAL, bukaPeramban, GELANGGANG, hasil, jalankan, laporkan, masuk, pastikan, TURNAMEN,
} from './bantu.mjs';

const T = TURNAMEN;
const ARENA = GELANGGANG;
const NOMOR = Number(process.env.QA_NOMOR_JURUS ?? 1);

const browser = await bukaPeramban();

const ketua = await masuk(browser, 'ketua@silat.test');

// Menyusun bagan adalah kewenangan Operator IT, bukan Ketua.
const penyusun = await masuk(browser, 'operator@silat.test');
const pengendali = await masuk(browser, 'pengendali2@silat.test');
const operator = await masuk(browser, 'operator2@silat.test');
const juri = [];

for (const n of [1, 2, 3, 4]) {
    juri.push(await masuk(browser, `juri${n}@silat.test`));
}

const panel = (peran) => `${ASAL}/admin/turnamen/${T}/gelanggang/${ARENA}/panel/${peran}`;
const bagan = `${ASAL}/admin/turnamen/${T}/jurus/${NOMOR}/bagan`;

const baca = (h, pilih, jalur) => h.evaluate(([p, j]) => {
    const el = document.querySelector(p);
    if (! el) return null;
    return j.split('.').reduce((o, k) => (o == null ? null : o[k]), window.Alpine.$data(el));
}, [pilih, jalur]);

await jalankan('H-01', 'Susun bagan lima peserta: tiga kolom, dari perempat final ke final', async () => {
    await penyusun.halaman.goto(`${ASAL}/admin/turnamen/${T}/jurus/${NOMOR}`, { waitUntil: 'domcontentloaded' });

    const form = penyusun.halaman.locator('form[action$="/susun-bagan"]');
    pastikan(await form.count() > 0, 'form susun bagan tidak tergambar untuk Operator IT');

    await form.locator('select[name="acak"]').selectOption('0');
    await Promise.all([
        penyusun.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        form.locator('button[type="submit"]').click(),
    ]);

    await ketua.halaman.goto(bagan, { waitUntil: 'domcontentloaded' });

    const judul = await ketua.halaman.locator('.text-ink-muted.uppercase, [class*="uppercase"]').allInnerTexts();
    const kolom = judul.map((t) => t.trim()).filter((t) => /final|penyisihan/i.test(t));

    pastikan(kolom.length >= 3, `kolom babak ${kolom.length}: ${kolom.join(' | ')}`);
    pastikan(/final/i.test(kolom.at(-1)), `kolom terakhir "${kolom.at(-1)}", diharap Final`);

    return kolom.join(' -> ');
}, { halaman: ketua.halaman });

await jalankan('H-02', 'Pasangan bye tidak digambar di kolom pertama', async () => {
    // Lima peserta di bagan delapan: tiga pasangan bye. Kolom pertama karena
    // itu hanya memuat SATU pasangan yang benar-benar dipertandingkan.
    const slot = await ketua.halaman.evaluate(() => {
        const kotak = [...document.querySelectorAll('[class*="absolute"][style*="left: 0px"]')];
        return kotak.length;
    });

    const bidang = await ketua.halaman.locator('.bg-corner-red, .bg-corner-blue').count();

    pastikan(bidang >= 2, `slot bersudut hanya ${bidang}`);
    pastikan(slot <= 4, `kolom pertama memuat ${slot} slot; pasangan bye tampaknya ikut digambar`);

    return `${slot} slot di kolom pertama, ${bidang} slot bersudut`;
}, { halaman: ketua.halaman });

await jalankan('H-03', 'Cetak PDF bagan bertingkat', async () => {
    const balasan = await ketua.halaman.request.get(`${bagan}/cetak`);

    pastikan(balasan.status() === 200, `status ${balasan.status()}`);
    pastikan(
        (balasan.headers()['content-type'] ?? '').includes('application/pdf'),
        `content-type ${balasan.headers()['content-type']}`,
    );

    return `PDF ${Math.round((await balasan.body()).length / 1024)} KB`;
});

await jalankan('H-06', 'Battle yang sudutnya belum lengkap tidak ditawari penjadwalan', async () => {
    await ketua.halaman.goto(`${ASAL}/admin/turnamen/${T}/jadwal/jurus`, { waitUntil: 'domcontentloaded' });

    const siap = await ketua.halaman.locator('form[action*="/jadwal/jurus/battle/"][action$="/tetapkan"]').count();
    const semua = await ketua.halaman.evaluate(() => document.body.innerText.match(/lawan/g)?.length ?? 0);

    pastikan(siap > 0, 'tidak ada satu pun battle siap dijadwalkan');
    pastikan(siap <= semua, 'lebih banyak form daripada baris battle');

    return `${siap} battle siap dijadwalkan`;
}, { halaman: ketua.halaman });

// ---- H-04/H-05: jalankan satu battle sampai pemenangnya naik ke ronde 2.

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

    pastikan(await tombol.count() > 0, 'tidak ada tombol Tayangkan');
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

async function nilai(sesi, angka) {
    await sesi.halaman.goto(panel('juri'), { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForSelector('button:has-text("Kirim nilai")', { timeout: 20_000 });
    await sesi.halaman.locator('button:has-text("Ulangi")').click();

    for (const d of String(angka).replace('.', '')) {
        await sesi.halaman.locator(`button:text-is("${d}")`).first().click();
    }

    await sesi.halaman.locator('button:has-text("Kirim nilai")').click();
    await sesi.halaman.waitForFunction(() => document.body.innerText.includes('Terkirim:'), null, { timeout: 20_000 });
}

async function sahkan(sesi) {
    await sesi.halaman.goto(panel('papan'), { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForSelector('button:has-text("Sahkan skor akhir")', { timeout: 20_000 });
    await sesi.halaman.locator('button:has-text("Sahkan skor akhir")').click();
    await sesi.halaman.waitForFunction(
        () => document.body.innerText.includes('Sudah disahkan'), null, { timeout: 20_000 },
    );
}

await jalankan('H-04', 'Pemenang ronde 1 naik ke ronde berikutnya', async () => {
    await ketua.halaman.goto(`${ASAL}/admin/turnamen/${T}/jadwal/jurus`, { waitUntil: 'domcontentloaded' });

    const form = ketua.halaman.locator('form[action*="/jadwal/jurus/battle/"][action$="/tetapkan"]').first();
    await form.locator('select[name="arena_id"]').selectOption({ label: 'Gelanggang B' });
    await Promise.all([
        ketua.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        form.locator('button[type="submit"]').click(),
    ]);

    for (const putaran of [0, 1]) {
        await tayangkanJurus(pengendali);
        await jalankanTimer(operator);

        for (const sesi of juri) {
            await nilai(sesi, putaran === 0 ? '9.80' : '9.50');
        }

        await sahkan(ketua);
    }

    await ketua.halaman.goto(panel('ketua'), { waitUntil: 'domcontentloaded' });
    await ketua.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.komparasi?.siap === true,
        null, { timeout: 20_000 },
    );

    await ketua.halaman.locator('button:has-text("Tetapkan pemenang")').click();
    await ketua.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.komparasi?.battle?.selesai === true,
        null, { timeout: 20_000 },
    );

    const pemenang = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.battle.winner_registration_id');

    // Pemenangnya harus tampil di kolom BERIKUTNYA pada pohon.
    await ketua.halaman.goto(bagan, { waitUntil: 'domcontentloaded' });
    const menunggu = await ketua.halaman.evaluate(
        () => document.body.innerText.match(/Menunggu babak sebelumnya/g)?.length ?? 0,
    );

    pastikan(pemenang != null, 'pemenang tidak tercatat');

    return `pemenang ${pemenang}; slot "menunggu" tersisa ${menunggu}`;
}, { halaman: ketua.halaman });

await jalankan('H-05', 'Battle ronde berikutnya bisa dijadwalkan dan penampilannya lahir di gelanggangnya', async () => {
    await ketua.halaman.goto(`${ASAL}/admin/turnamen/${T}/jadwal/jurus`, { waitUntil: 'domcontentloaded' });

    const form = ketua.halaman.locator('form[action*="/jadwal/jurus/battle/"][action$="/tetapkan"]').first();
    pastikan(await form.count() > 0, 'tidak ada battle ronde lanjut yang siap dijadwalkan');

    await form.locator('select[name="arena_id"]').selectOption({ label: 'Gelanggang B' });
    await Promise.all([
        ketua.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        form.locator('button[type="submit"]').click(),
    ]);

    await pengendali.halaman.goto(panel('kendali'), { waitUntil: 'domcontentloaded' });
    await pengendali.halaman.waitForFunction(
        () => document.body.innerText.includes('Antrean Jurus'), null, { timeout: 20_000 },
    );

    const antrean = await baca(pengendali.halaman, '[x-data^="partaiPanel"]', 'panel.jurus.antrean.length');
    pastikan(antrean >= 2, `antrean ${antrean}, diharap bertambah dua penampilan`);

    return `antrean gelanggang berisi ${antrean} penampilan`;
}, { halaman: pengendali.halaman });

await browser.close();
process.exit(laporkan('H. Bagan Jurus bertingkat') === 0 ? 0 : 1);
