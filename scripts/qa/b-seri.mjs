// B-03..B-05: jalur SERI di peramban.
//
// Memakai perkakas yang sama dengan blok C/D, yang sudah terbukti menuntaskan
// dua sudut sampai disahkan. Yang berbeda cuma angkanya: kedua sudut 9.70.

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

const baca = (h, pilih, jalur) => h.evaluate(([p, j]) => {
    const el = document.querySelector(p);
    if (! el) return null;
    return j.split('.').reduce((o, k) => (o == null ? null : o[k]), window.Alpine.$data(el));
}, [pilih, jalur]);

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

let battleId = null;

await jalankan('B-00', 'Siapkan battle dengan skor SAMA di kedua sudut (9.70 lawan 9.70)', async () => {
    await ketua.halaman.goto(`${ASAL}/admin/turnamen/${T}/jadwal/jurus`, { waitUntil: 'domcontentloaded' });

    const form = ketua.halaman.locator('form[action*="/jadwal/jurus/battle/"][action$="/tetapkan"]').first();
    battleId = (await form.getAttribute('action')).match(/battle\/(\d+)\/tetapkan/)[1];

    await form.locator('select[name="arena_id"]').selectOption({ label: 'Gelanggang B' });
    await Promise.all([
        ketua.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        form.locator('button[type="submit"]').click(),
    ]);

    for (const sudut of ['biru', 'merah']) {
        await tayangkanJurus(pengendali);
        await jalankanTimer(operator);

        for (const sesi of juri) {
            await nilai(sesi, '9.70');
        }

        await sahkan(ketua);
    }

    return `battle ${battleId}, kedua sudut 9.70`;
}, { halaman: ketua.halaman });

await jalankan('B-03', 'Skor sama: komparasi menandai SERI dan menawarkan pilihan sudut', async () => {
    await ketua.halaman.goto(panel('ketua'), { waitUntil: 'domcontentloaded' });
    await ketua.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.komparasi?.siap === true,
        null, { timeout: 20_000 },
    );

    const seri = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.seri');
    const selisih = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.selisih');

    pastikan(seri === true, 'komparasi tidak menandai seri');
    pastikan(Number(selisih).toFixed(2) === '0.00', `selisih ${selisih}, diharap 0.00`);

    const teks = await ketua.halaman.innerText('body');
    pastikan(/skor akhir kedua sudut sama/i.test(teks), 'blok SERI tidak tergambar');
    pastikan(/menangkan sudut merah/i.test(teks) && /menangkan sudut biru/i.test(teks), 'kedua pilihan sudut tidak ditawarkan');
    pastikan(/alasannya wajib ditulis/i.test(teks), 'kewajiban alasan tidak dinyatakan');

    return 'SERI tergambar, dua pilihan sudut, alasan dinyatakan wajib';
}, { halaman: ketua.halaman });

await jalankan('B-04', 'Seri: tombol sudut MATI selama alasan kosong maupun spasi', async () => {
    const tombol = ketua.halaman.locator('button:has-text("Menangkan sudut merah")').first();

    pastikan(await tombol.isDisabled(), 'tombol hidup padahal alasan kosong');

    await ketua.halaman.fill('#alasan-seri', '    ');
    pastikan(await tombol.isDisabled(), 'spasi saja sudah menghidupkan tombol');

    await ketua.halaman.fill('#alasan-seri', 'x');
    pastikan(! await tombol.isDisabled(), 'tombol tetap mati padahal alasan terisi');

    await ketua.halaman.fill('#alasan-seri', '');

    return 'mati untuk kosong dan spasi, hidup begitu terisi';
}, { halaman: ketua.halaman });

await jalankan('B-04b', 'Seri: server menolak penetapan tanpa pilihan sudut, menyebut angkanya', async () => {
    const balasan = await kirim(ketua.halaman, `${ASAL}/admin/turnamen/${T}/jurus/battle/${battleId}/putuskan`, {});

    pastikan(balasan.status >= 400, `status ${balasan.status}, diharap penolakan`);

    const pesan = JSON.stringify(balasan.badan ?? {});
    pastikan(/Ketua Pertandingan yang menetapkan/i.test(pesan), `pesan tidak menyebut Ketua: ${pesan.slice(0, 200)}`);
    pastikan(/9[.,]70/.test(pesan), `pesan tidak menyebut angka serinya: ${pesan.slice(0, 200)}`);

    return `ditolak ${balasan.status}, pesannya menyebut 9.70`;
}, { halaman: ketua.halaman });

await jalankan('B-05', 'Seri: sudut + alasan menetapkan pemenang dengan sebab keputusan_ketua', async () => {
    await ketua.halaman.fill('#alasan-seri', 'Kemantapan gerak lebih baik menurut Dewan Wasit Juri.');
    await ketua.halaman.locator('button:has-text("Menangkan sudut merah")').first().click();

    await ketua.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.komparasi?.battle?.selesai === true,
        null, { timeout: 20_000 },
    );

    const sebab = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.battle.win_reason');
    const menang = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.battle.winner_registration_id');
    const merah = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.merah.registration_id');

    pastikan(sebab === 'keputusan_ketua', `win_reason "${sebab}", diharap keputusan_ketua`);
    pastikan(menang === merah, 'pemenang bukan sudut yang dipilih Ketua');

    return `pemenang sudut merah, sebab ${sebab}`;
}, { halaman: ketua.halaman });

console.log(`\nbattle yang diputus: ${battleId}`);
await browser.close();
process.exit(laporkan('B. Jalur SERI') === 0 ? 0 : 1);
