// A. Alur utama Jurus, ujung ke ujung -- State Transition.
//
// Gelanggang B (arena 2), yang pointernya kosong: alur utama tidak boleh
// bergantung pada pemaksaan, dan tidak boleh mengubah partai Tanding yang
// sedang berjalan di Gelanggang A.

import { ASAL, bukaPeramban, hasil, jalankan, laporkan, masuk, pastikan, GELANGGANG, TURNAMEN } from './bantu.mjs';


const T = TURNAMEN;
const ARENA = GELANGGANG;

const browser = await bukaPeramban();

// Penjadwalan menuntut `jadwal.assign`; di seeder peran hanya Ketua
// Pertandingan yang memilikinya, bukan Sekretariat.
const penjadwal = await masuk(browser, 'ketua@silat.test');
const pengendali = await masuk(browser, 'pengendali2@silat.test');
const operator = await masuk(browser, 'operator2@silat.test');
const ketua = penjadwal;
const juri = [];

for (const n of [1, 2, 3, 4]) {
    juri.push(await masuk(browser, `juri${n}@silat.test`));
}

const jadwalJurus = `${ASAL}/admin/turnamen/${T}/jadwal/jurus`;
const panel = (peran) => `${ASAL}/admin/turnamen/${T}/gelanggang/${ARENA}/panel/${peran}`;

// Papan Jurus hanya menampilkan; kendali timer dan pengesahan ada di alamat
// terpisah `jurus-operator` -- berbeda dari Tanding, yang keduanya di `papan`.
const OPERATOR_JURUS = panel('jurus-operator');

// Membaca SATU jalur properti, bukan menyerialkan seluruh komponen: getter
// Alpine di komponen ini melempar saat partai kosong (`current_round` dari
// null), dan JSON.stringify menyentuh semuanya.
const baca = (h, pilih, jalur) => h.evaluate(([p, j]) => {
    const el = document.querySelector(p);
    if (! el) return null;
    return j.split('.').reduce((o, k) => (o == null ? null : o[k]), window.Alpine.$data(el));
}, [pilih, jalur]);

let battleId = null;

/**
 * Menekan Tayangkan DI DALAM blok "Antrean Jurus".
 *
 * Antrean Tanding memakai label tombol yang sama persis, dan menekan yang itu
 * menayangkan partai Tanding sungguhan di gelanggang ini.
 */
async function tayangkanJurus(sesi, indeks) {
    const tombol = sesi.halaman.locator(
        'xpath=//p[normalize-space()="Antrean Jurus"]'
        + '/ancestor::div[.//button[normalize-space()="Tayangkan"]][1]'
        + '//button[normalize-space()="Tayangkan"]',
    );

    const n = await tombol.count();
    pastikan(n > 0, 'blok Antrean Jurus tidak punya tombol Tayangkan');

    // Baris yang SEDANG tayang tidak lagi menampilkan tombolnya, jadi jumlah
    // tombol menyusut seiring antrean berjalan -- indeksnya dijepit.
    await tombol.nth(Math.min(indeks, n - 1)).click();
}

await jalankan('A-01', 'Tab Jurus terbuka dan menautkan tab Tanding', async () => {
    await penjadwal.halaman.goto(jadwalJurus, { waitUntil: 'domcontentloaded' });

    const teks = await penjadwal.halaman.innerText('body');
    pastikan(teks.includes('Battle belum dijadwalkan'), 'kartu "Battle belum dijadwalkan" tidak ada');

    const tautan = await penjadwal.halaman.locator(`a[href="${ASAL}/admin/turnamen/${T}/jadwal"]`).count();
    pastikan(tautan > 0, 'tab Tanding tidak tertaut');

    return 'dua tab hadir';
}, { halaman: penjadwal.halaman });

await jalankan('A-02', 'Jadwalkan battle ke Gelanggang B; dua sudut berurutan, biru dulu', async () => {
    const form = penjadwal.halaman.locator('form[action*="/jadwal/jurus/battle/"][action$="/tetapkan"]').first();
    pastikan(await form.count() > 0, 'form jadwalkan battle tidak ada');

    battleId = (await form.getAttribute('action')).match(/battle\/(\d+)\/tetapkan/)[1];

    await form.locator('select[name="arena_id"]').selectOption({ label: 'Gelanggang B' });
    await Promise.all([
        penjadwal.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        form.locator('button[type="submit"]').click(),
    ]);

    // Chip sudut dikenali dari KELASNYA, bukan dari kata di teks halaman.
    const antrean = await penjadwal.halaman.evaluate(() => {
        const kartu = [...document.querySelectorAll('div')]
            .filter((d) => d.innerText?.startsWith('Gelanggang B') && d.querySelector('input[name="urutan"]'))
            .pop();

        // Chip diambil dalam URUTAN DOKUMEN sekartu, bukan lewat walk dari
        // tiap input -- pembungkus barisnya sama untuk kedua baris.
        const urut = [...(kartu?.querySelectorAll('input[name="urutan"]') ?? [])].map((i) => i.value);
        const chip = [...(kartu?.querySelectorAll('.bg-corner-red, .bg-corner-blue') ?? [])]
            .map((c) => (c.className.includes('bg-corner-red') ? 'merah' : 'biru'));

        return urut.map((u, n) => ({ urut: u, sudut: chip[n] ?? '?' }));
    });

    pastikan(antrean.length === 2, `antrean Gelanggang B berisi ${antrean.length} baris, diharap 2`);
    pastikan(antrean[0].sudut === 'biru', `urutan 1 bersudut ${antrean[0].sudut}, diharap biru`);
    pastikan(antrean[1].sudut === 'merah', `urutan 2 bersudut ${antrean[1].sudut}, diharap merah`);

    return `battle ${battleId}, ${antrean.map((a) => `${a.urut}:${a.sudut}`).join(' ')}`;
}, { halaman: penjadwal.halaman });

await jalankan('A-03', 'Antrean Jurus tergambar di panel kendali', async () => {
    await pengendali.halaman.goto(panel('kendali'), { waitUntil: 'domcontentloaded' });
    await pengendali.halaman.waitForFunction(
        () => document.body.innerText.includes('Antrean Jurus'), null, { timeout: 20_000 },
    );

    const jumlah = await baca(pengendali.halaman, '[x-data^="partaiPanel"]', 'panel.jurus.antrean.length');
    pastikan(jumlah === 2, `antrean payload ${jumlah}`);

    return '2 penampilan di antrean';
}, { halaman: pengendali.halaman });

await jalankan('A-04', 'Tayangkan penampilan sudut biru', async () => {
    await tayangkanJurus(pengendali, 0);

    await pengendali.halaman.waitForFunction(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data^="partaiPanel"]'));
        return d.panel?.tayang === 'jurus';
    }, null, { timeout: 20_000 });

    return 'pointer gelanggang = jurus';
}, { halaman: pengendali.halaman });

await jalankan('A-05', 'Panel papan berganti mode Jurus di alamat yang sama', async () => {
    await operator.halaman.goto(panel('papan'), { waitUntil: 'domcontentloaded' });

    const teks = await operator.halaman.innerText('body');
    pastikan(! teks.includes('Menunggu pengendali memilih partai'), 'masih layar tunggu partai');
    pastikan(/nilai juri/i.test(teks), 'papan Jurus tidak menggambar blok Nilai juri');

    return 'papan Jurus dirender';
}, { halaman: operator.halaman });

await jalankan('A-06', 'Panel juri berganti mode Jurus di alamat yang sama', async () => {
    await juri[0].halaman.goto(panel('juri'), { waitUntil: 'domcontentloaded' });

    const teks = await juri[0].halaman.innerText('body');
    pastikan(! teks.includes('Menunggu pengendali memilih partai'), 'masih layar tunggu partai');
    pastikan(/kirim nilai/i.test(teks), 'papan tik juri Jurus tidak ada');

    return 'panel juri Jurus dirender';
}, { halaman: juri[0].halaman });

await jalankan('A-07', 'Wasit dapat layar tunggu bersebab, bukan 404', async () => {
    const wasit = await masuk(browser, 'wasit2@silat.test');
    const balasan = await wasit.halaman.goto(panel('wasit'), { waitUntil: 'domcontentloaded' });

    pastikan(balasan.status() === 200, `status ${balasan.status()}, diharap 200`);

    const teks = await wasit.halaman.innerText('body');
    pastikan(/menayangkan jurus/i.test(teks), `layar tunggu tidak menyebut sebabnya: ${teks.slice(0, 200)}`);

    await wasit.konteks.close();

    return 'layar tunggu menyebut Jurus';
});

// `text-is`, bukan `has-text`: "Mulai babak" milik panel Tanding juga cocok
// dengan has-text("Mulai"), dan menekannya memulai partai Tanding sungguhan.
async function jalankanTimer(sesi) {
    await sesi.halaman.goto(OPERATOR_JURUS, { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForSelector('button:text-is("Mulai")', { timeout: 20_000 });
    await sesi.halaman.locator('button:text-is("Mulai")').first().click();
    await sesi.halaman.waitForFunction(
        () => document.body.innerText.includes('Sedang tampil'), null, { timeout: 20_000 },
    );
    await sesi.halaman.waitForTimeout(1200);
    await sesi.halaman.locator('button:text-is("Selesai")').first().click();
    await sesi.halaman.waitForFunction(
        () => document.body.innerText.includes('Selesai'), null, { timeout: 20_000 },
    );
}

await jalankan('A-08', 'Timer penampilan: mulai lalu selesai', async () => {
    await jalankanTimer(operator);

    return 'berlangsung -> selesai';
}, { halaman: operator.halaman });

async function nilai(sesi, angka) {
    await sesi.halaman.goto(panel('juri'), { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForSelector('button:has-text("Kirim nilai")', { timeout: 20_000 });

    for (const d of String(angka).replace('.', '')) {
        await sesi.halaman.locator(`button:text-is("${d}")`).first().click();
    }

    await sesi.halaman.locator('button:has-text("Kirim nilai")').click();
    await sesi.halaman.waitForFunction(
        () => document.body.innerText.includes('Terkirim:'), null, { timeout: 20_000 },
    );
}

async function sahkan(sesi) {
    await sesi.halaman.goto(OPERATOR_JURUS, { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForSelector('button:has-text("Sahkan skor akhir")', { timeout: 20_000 });
    await sesi.halaman.locator('button:has-text("Sahkan skor akhir")').click();
    await sesi.halaman.waitForFunction(
        () => document.body.innerText.includes('Sudah disahkan'), null, { timeout: 20_000 },
    );
}

await jalankan('A-09', 'Empat juri menilai sudut biru 9.70', async () => {
    for (const sesi of juri) {
        await nilai(sesi, '9.70');
    }

    await operator.halaman.goto(OPERATOR_JURUS, { waitUntil: 'domcontentloaded' });
    await operator.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.nilaiJuri?.length === 4,
        null, { timeout: 20_000 },
    );

    const median = await baca(operator.halaman, '[x-data^="jurusPanel"]', 'skor.median');
    pastikan(Number(median).toFixed(2) === '9.70', `median ${median}, diharap 9.70`);

    return `4 nilai, median ${Number(median).toFixed(2)}`;
}, { halaman: operator.halaman });

await jalankan('A-10', 'Ketua mengesahkan sudut biru', async () => {
    await sahkan(ketua);

    return 'sudut biru disahkan';
}, { halaman: ketua.halaman });

await jalankan('A-11', 'Komparasi BELUM muncul saat baru satu sudut disahkan', async () => {
    for (const [nama, sesi, peran] of [['papan', operator, 'papan'], ['juri', juri[0], 'juri']]) {
        await sesi.halaman.goto(panel(peran), { waitUntil: 'domcontentloaded' });
        await sesi.halaman.waitForTimeout(1500);

        const terlihat = await sesi.halaman.locator('text=Perbandingan nilai').first()
            .isVisible().catch(() => false);

        pastikan(! terlihat, `komparasi tergambar di panel ${nama} padahal sudut merah belum disahkan`);
    }

    return 'tidak tergambar di papan maupun panel juri';
}, { halaman: operator.halaman });

await jalankan('A-12', 'Tayangkan sudut merah, nilai 9.60, sahkan', async () => {
    await pengendali.halaman.goto(panel('kendali'), { waitUntil: 'domcontentloaded' });
    await pengendali.halaman.waitForFunction(
        () => document.body.innerText.includes('Antrean Jurus'), null, { timeout: 20_000 },
    );

    await tayangkanJurus(pengendali, 1);
    await pengendali.halaman.waitForTimeout(2000);

    await jalankanTimer(operator);

    for (const sesi of juri) {
        await nilai(sesi, '9.60');
    }

    await sahkan(ketua);

    return 'sudut merah dinilai 9.60 dan disahkan';
}, { halaman: operator.halaman });

await jalankan('A-13', 'Komparasi muncul otomatis di papan, panel juri, dan panel ketua', async () => {
    for (const [nama, sesi, peran] of [['papan', operator, 'papan'], ['juri', juri[0], 'juri'], ['ketua', ketua, 'ketua']]) {
        await sesi.halaman.goto(panel(peran), { waitUntil: 'domcontentloaded' });

        await sesi.halaman.waitForFunction(
            () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.komparasi?.siap === true,
            null, { timeout: 20_000 },
        ).catch(() => { throw new Error(`komparasi tidak siap di panel ${nama}`); });

        const terlihat = await sesi.halaman.locator('text=Perbandingan nilai').first().isVisible();
        pastikan(terlihat, `blok komparasi tidak terlihat di panel ${nama}`);
    }

    const selisih = await baca(operator.halaman, '[x-data^="jurusPanel"]', 'komparasi.selisih');
    const seri = await baca(operator.halaman, '[x-data^="jurusPanel"]', 'komparasi.seri');
    pastikan(Number(selisih).toFixed(2) === '0.10', `selisih ${selisih}, diharap 0.10`);
    pastikan(seri === false, 'ditandai seri padahal skornya berbeda');

    return `siap di tiga panel, selisih ${Number(selisih).toFixed(2)}`;
}, { halaman: operator.halaman });

await jalankan('A-14', 'Ketua menetapkan pemenang; skor berbeda diputus angka', async () => {
    await ketua.halaman.goto(panel('ketua'), { waitUntil: 'domcontentloaded' });
    await ketua.halaman.waitForSelector('button:has-text("Tetapkan pemenang")', { timeout: 20_000 });
    await ketua.halaman.locator('button:has-text("Tetapkan pemenang")').click();

    await ketua.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.komparasi?.battle?.selesai === true,
        null, { timeout: 20_000 },
    );

    const sebab = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.battle.win_reason');
    const menang = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.battle.winner_registration_id');
    const biru = await baca(ketua.halaman, '[x-data^="jurusPanel"]', 'komparasi.biru.registration_id');

    pastikan(sebab === 'angka', `win_reason ${sebab}`);
    pastikan(menang === biru, 'pemenang bukan sudut biru yang skornya lebih tinggi');

    return `pemenang sudut biru, sebab ${sebab}`;
}, { halaman: ketua.halaman });


await browser.close();
process.exit(laporkan('A. Alur utama Jurus') === 0 ? 0 : 1);
