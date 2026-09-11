// K (lanjutan). Aturan satu penulis dan perjalanan balik hasil pertandingan.
//
// Blok sebelumnya menguji pertukarannya. Yang ini menguji ATURANNYA: siapa
// boleh menulis apa, dan apakah yang ditulis gelanggang benar-benar sampai ke
// node global.
//
// Node G (global)      http://127.0.0.2:8000
// Node B (gelanggang)  http://127.0.0.4:8010  -- memegang Gelanggang B

import { bukaPeramban, jalankan, laporkan, masuk, pastikan } from './bantu.mjs';

const G = process.env.QA_NODE_GLOBAL ?? 'http://127.0.0.2:8000';
const B = process.env.QA_NODE_GELANGGANG ?? 'http://127.0.0.4:8010';
const TOKEN = process.env.QA_SINKRON_TOKEN ?? '';
const T = Number(process.env.QA_TURNAMEN ?? 1);

const browser = await bukaPeramban();

const pengendaliB = await masuk(browser, 'pengendali2@silat.test', { asal: B });
const adminB = await masuk(browser, 'operator2@silat.test', { asal: B });
/*
 * Ketua Pertandingan, bukan Operator IT: halaman Sinkron Gelanggang dijaga
 * `sinkron-gelanggang.view`, dan Operator IT -- peran yang memegang seluruh
 * administrasi kejuaraan sesudah peleburan -- tidak memilikinya. Lihat temuan
 * K-16 di laporan.
 */
const adminG = await masuk(browser, 'ketua@silat.test', { asal: G });

await jalankan('K-11', 'Node gelanggang menulis baris bergolongan GLOBAL', async () => {
    /*
     * Kontingen bergolongan GLOBAL: dokumen menyebut node global satu-satunya
     * penulisnya. Yang diuji di sini apakah aturan itu ditegakkan mesin, atau
     * cuma tertulis di dokumen -- aturan yang hanya hidup di dokumen adalah
     * aturan yang belum diuji.
     */
    await adminB.halaman.goto(`${B}/admin/turnamen/${T}/kontingen`, { waitUntil: 'domcontentloaded' });

    const balasan = await adminB.halaman.evaluate(async ([asal, t]) => {
        const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
        const res = await fetch(`${asal}/admin/turnamen/${t}/kontingen`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ name: 'Kontingen Uji Multinode', daerah: 'Uji', kontak: '08123456789' }),
        });

        return { status: res.status, badan: (await res.text()).slice(0, 300) };
    }, [B, T]);

    return `node gelanggang menjawab ${balasan.status} — ${balasan.badan.slice(0, 120).replace(/\s+/g, ' ')}`;
}, { halaman: adminB.halaman });

await jalankan('K-12', 'Perubahan yang lahir di gelanggang sampai ke node global', async () => {
    // Menayangkan partai Gelanggang B: menulis `arena_tayang` (LOKAL) dan
    // menyalin aparat gelanggang ke partainya.
    await pengendaliB.halaman.goto(`${B}/admin/turnamen/${T}/gelanggang/2/panel/kendali`, { waitUntil: 'domcontentloaded' });
    /*
     * Menunggu ANTREANNYA, bukan sekadar `panel != null`: partaiPanel menimpa
     * `panel` dengan kerangka kosong saat init, jadi syarat itu benar sebelum
     * tarikan `state` pertama dan yang membacanya melihat antrean kosong.
     * Jebakan yang sama pernah membuat blok I dilaporkan rusak.
     */
    await pengendaliB.halaman.waitForFunction(
        () => (window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.panel?.antrean?.length ?? 0) > 0,
        null, { timeout: 25_000 },
    );

    const tombol = pengendaliB.halaman.locator('button:text-is("Tayangkan")');
    pastikan(await tombol.count() > 0, 'antrean Gelanggang B tidak punya tombol Tayangkan');

    await tombol.first().click();
    await pengendaliB.halaman.waitForTimeout(3000);

    const tayang = await pengendaliB.halaman.evaluate(async ([asal, token]) => {
        const res = await fetch(`${asal}/sinkron/identitas`, { headers: { 'X-Sinkron-Token': token } });

        return (await res.json()).kursor;
    }, [B, TOKEN]);

    pastikan(tayang > 0, `kursor node B masih ${tayang} sesudah menayangkan partai`);

    // Node global menarik dari gelanggang-b.
    await adminG.halaman.goto(`${G}/admin/sinkron`, { waitUntil: 'domcontentloaded' });

    const tombolTarik = adminG.halaman.locator('button:has-text("Tarik")');
    pastikan(await tombolTarik.count() > 0, 'node global tidak menawarkan tombol tarik');

    await tombolTarik.first().click();
    await adminG.halaman.waitForTimeout(10_000);
    await adminG.halaman.reload({ waitUntil: 'domcontentloaded' });

    const teks = await adminG.halaman.innerText('body');
    pastikan(! /galat/i.test(teks), `halaman sinkron global menyebut galat: ${teks.slice(0, 200)}`);

    return `kursor gelanggang-b ${tayang}, node global menarik tanpa galat`;
}, { halaman: adminG.halaman });

await jalankan('K-13', 'Node global melihat tayangan gelanggang sesudah ditarik', async () => {
    await adminG.halaman.goto(`${G}/admin/turnamen/${T}/gelanggang/2/panel/papan`, { waitUntil: 'domcontentloaded' });
    await adminG.halaman.waitForTimeout(2500);

    const teks = await adminG.halaman.innerText('body');

    pastikan(
        ! /menunggu pengendali memilih partai/i.test(teks),
        'papan node global masih menunggu partai — tayangan gelanggang B tidak ikut tertarik',
    );

    return 'papan node global menampilkan partai yang ditayangkan gelanggang B';
}, { halaman: adminG.halaman });

await browser.close();
process.exit(laporkan('K lanjutan. Satu penulis dan perjalanan balik') === 0 ? 0 : 1);
