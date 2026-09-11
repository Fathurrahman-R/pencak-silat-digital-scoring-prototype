// G. Modal hasil verifikasi juri di panel operator -- Pasal 13.
//
// Yang diuji bukan "apakah verifikasinya jalan" (itu sudah ditutup uji Pest),
// melainkan APA YANG TERLIHAT dan KAPAN: judul yang menyebut jenisnya, suara
// yang tampil selama polling, hasil yang TIDAK muncul sebelum Wasit
// menerapkannya, lalu modal yang mengambil layar sesudahnya.

import {
    ASAL, bukaPeramban, GELANGGANG, hasil, jalankan, laporkan, masuk, pastikan, TURNAMEN,
} from './bantu.mjs';

const T = TURNAMEN;
const ARENA = Number(process.env.QA_GELANGGANG_TANDING ?? 1);

const browser = await bukaPeramban();

const pengendali = await masuk(browser, 'pengendali1@silat.test');
const wasit = await masuk(browser, 'wasit1@silat.test');
const operator = await masuk(browser, 'operator@silat.test');
const juri = [];

for (const n of [1, 2, 3]) {
    juri.push(await masuk(browser, `juri${n}@silat.test`));
}

const panel = (peran) => `${ASAL}/admin/turnamen/${T}/gelanggang/${ARENA}/panel/${peran}`;

const baca = (h, jalur) => h.evaluate((j) => {
    const el = document.querySelector('[x-data^="partaiPanel"]');
    if (! el) return null;
    return j.split('.').reduce((o, k) => (o == null ? null : o[k]), window.Alpine.$data(el));
}, jalur);

/** Wasit membuka satu verifikasi. */
async function mintaVerifikasi(jenis, tingkat = null) {
    await wasit.halaman.goto(panel('wasit'), { waitUntil: 'domcontentloaded' });
    await wasit.halaman.waitForSelector('button:has-text("Minta verifikasi juri")', { timeout: 20_000 });
    await wasit.halaman.locator('button:has-text("Minta verifikasi juri")').click();

    /*
     * Dikenali dari KETERANGANNYA, bukan dari judulnya: panel wasit di
     * belakang dialog punya tombol skor "Jatuhan untuk Sudut merah +3", dan
     * has-text("Jatuhan") mengenainya juga -- lalu kliknya tertahan dialog.
     */
    const petunjuk = jenis === 'jatuhan' ? 'Sah atau tidak' : 'Terjadi atau tidak';
    await wasit.halaman.locator(`button:has-text("${petunjuk}")`).first().click();

    if (tingkat) {
        await wasit.halaman.locator(`button:text-is("${tingkat}")`).first().click();
    }

    await wasit.halaman.locator('button:has-text("Kirim ke")').click();
    await wasit.halaman.waitForFunction(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data^="partaiPanel"]'));
        return d.verifikasi?.berjalan === true;
    }, null, { timeout: 20_000 });
}

async function jawab(sesi, pilihan) {
    await sesi.halaman.goto(panel('juri'), { waitUntil: 'domcontentloaded' });
    await sesi.halaman.waitForFunction(() => {
        const d = window.Alpine.$data(document.querySelector('[x-data^="partaiPanel"]'));
        return d.verifikasi?.berjalan === true;
    }, null, { timeout: 20_000 });

    // Tombol jawaban dikenali dari label sudutnya yang huruf-besar, bukan dari
    // kata "Merah"/"Biru" yang juga muncul di banyak tempat lain.
    const label = { red: 'Sudut merah', blue: 'Sudut biru', tidak_ada: 'Tidak ada' }[pilihan];
    await sesi.halaman.locator(`button:has-text("${label}")`).first().click();
    await sesi.halaman.waitForTimeout(900);
}

async function terapkan() {
    await wasit.halaman.goto(panel('wasit'), { waitUntil: 'domcontentloaded' });
    await wasit.halaman.waitForSelector('button:has-text("Terapkan")', { timeout: 20_000 });
    await wasit.halaman.locator('button:has-text("Terapkan")').first().click();
    await wasit.halaman.waitForTimeout(1500);
}

// Partai harus berjalan sebelum verifikasi bisa diminta.
await jalankan('G-00', 'Siapkan partai Tanding yang sedang berjalan', async () => {
    await pengendali.halaman.goto(panel('kendali'), { waitUntil: 'domcontentloaded' });
    await pengendali.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.match?.id != null,
        null, { timeout: 20_000 },
    );

    const mulai = pengendali.halaman.locator('button:has-text("Mulai babak")');

    if (await mulai.count() > 0) {
        await mulai.first().click();
        await pengendali.halaman.waitForTimeout(1500);
    }

    const status = await baca(pengendali.halaman, 'match.status');
    pastikan(status === 'berlangsung', `status partai "${status}"`);

    return `partai ${await baca(pengendali.halaman, 'match.id')} berlangsung`;
}, { halaman: pengendali.halaman });

await jalankan('G-01', 'Judul blok menyebut "Verifikasi Jatuhan", bukan "Verifikasi juri"', async () => {
    await mintaVerifikasi('jatuhan');

    await operator.halaman.goto(panel('papan'), { waitUntil: 'domcontentloaded' });
    await operator.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.verifikasi?.berjalan === true,
        null, { timeout: 20_000 },
    );

    const label = await baca(operator.halaman, 'verifikasi.jenis_label');
    pastikan(label === 'Jatuhan', `jenis_label "${label}"`);

    const teks = await operator.halaman.innerText('body');
    pastikan(/verifikasi jatuhan/i.test(teks), 'judul tidak berbunyi "Verifikasi Jatuhan"');
    pastikan(! /verifikasi juri/i.test(teks), 'masih ada judul lama "Verifikasi juri"');

    return 'judul menyebut jenisnya';
}, { halaman: operator.halaman });

await jalankan('G-03', 'Selama polling: suara tampil, hasil TIDAK', async () => {
    await jawab(juri[0], 'red');

    await operator.halaman.reload({ waitUntil: 'domcontentloaded' });
    await operator.halaman.waitForTimeout(1200);

    const teks = await operator.halaman.innerText('body');
    pastikan(! /hasil verifikasi/i.test(teks), 'blok "Hasil verifikasi" masih tergambar');

    const hitungan = await baca(operator.halaman, 'verifikasi.hitungan.red');
    pastikan(hitungan === 1, `hitungan merah ${hitungan}, diharap 1`);

    return 'suara tampil, hasil tidak';
}, { halaman: operator.halaman });

await jalankan('G-04', 'Ambang tercapai tapi belum diterapkan: modal belum muncul', async () => {
    await jawab(juri[1], 'red');

    await operator.halaman.reload({ waitUntil: 'domcontentloaded' });
    await operator.halaman.waitForTimeout(1200);

    const adaHasil = await baca(operator.halaman, 'verifikasi.hasil');
    const diterapkan = await baca(operator.halaman, 'verifikasi.sudah_diterapkan');
    const modal = await baca(operator.halaman, 'hasilVerifikasiTampil');

    pastikan(adaHasil === 'red', `hasil "${adaHasil}", diharap red`);
    pastikan(diterapkan === false, 'sudah ditandai diterapkan padahal Wasit belum menekan');
    pastikan(modal !== true, 'modal muncul sebelum Wasit menerapkan');

    const teks = await operator.halaman.innerText('body');
    pastikan(/menunggu wasit menerapkannya/i.test(teks), 'baris "menunggu Wasit" tidak ada');

    return 'hasil terbit, modal menahan diri';
}, { halaman: operator.halaman });

await jalankan('G-05', 'Wasit menerapkan: modal muncul, berbunyi "Jatuhan Valid"', async () => {
    await terapkan();

    /*
     * TANPA memuat ulang. Memuat ulang membuat panelnya jadi "panel yang baru
     * dibuka", dan panel baru memang sengaja TIDAK disambut hasil verifikasi
     * (lihat G-09) -- jadi memuat ulang di sini akan menguji kebalikan dari
     * yang dimaksud. Operator sungguhan panelnya sudah terbuka, dan modalnya
     * datang sendiri lewat siaran.
     */
    await operator.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.hasilVerifikasiTampil === true,
        null, { timeout: 20_000 },
    ).catch(() => { throw new Error('modal tidak pernah muncul sesudah diterapkan'); });

    const judul = await operator.halaman.locator('#judul-hasil-verifikasi').innerText();
    pastikan(/jatuhan valid/i.test(judul), `judul modal "${judul}"`);

    return `modal berbunyi "${judul.trim()}"`;
}, { halaman: operator.halaman });

await jalankan('G-06', 'Modal berbidang warna sudut pemenang, dan menyebut sudutnya dengan kata', async () => {
    const kelas = await operator.halaman.locator('#judul-hasil-verifikasi').evaluate(
        (el) => el.closest('div').className,
    );

    pastikan(/silat-merah/.test(kelas), `bidang modal tidak berwarna merah: ${kelas.slice(0, 120)}`);

    const teks = await operator.halaman.locator('#judul-hasil-verifikasi').evaluate(
        (el) => el.closest('div').innerText,
    );

    pastikan(/sudut merah/i.test(teks), 'sudut tidak ditulis dengan kata');

    return 'bidang merah, sudut tertulis';
}, { halaman: operator.halaman });

await jalankan('G-08', 'Modal bisa ditutup dengan diketuk', async () => {
    await operator.halaman.locator('#judul-hasil-verifikasi').click();
    await operator.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.hasilVerifikasiTampil === false,
        null, { timeout: 10_000 },
    );

    return 'tertutup';
}, { halaman: operator.halaman });

await jalankan('G-09', 'Panel yang BARU dibuka tidak disambut hasil verifikasi lama', async () => {
    const segar = await masuk(browser, 'operator@silat.test');

    await segar.halaman.goto(panel('papan'), { waitUntil: 'domcontentloaded' });
    await segar.halaman.waitForTimeout(2500);

    const modal = await segar.halaman.evaluate(() => {
        const el = document.querySelector('[x-data^="partaiPanel"]');
        return el ? window.Alpine.$data(el).hasilVerifikasiTampil : null;
    });

    pastikan(modal !== true, 'panel baru disambut hasil verifikasi yang sudah lewat');

    await segar.konteks.close();

    return 'tidak muncul';
});

await jalankan('G-07', 'Hasil "tidak ada": modal netral, berbunyi "Tidak Valid"', async () => {
    await mintaVerifikasi('jatuhan');

    // Panel operator dibuka SELAGI polling berjalan -- sesudah itu ia hanya
    // menunggu, sama seperti di matras.
    await operator.halaman.goto(panel('papan'), { waitUntil: 'domcontentloaded' });
    await operator.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.verifikasi?.berjalan === true,
        null, { timeout: 20_000 },
    );

    await jawab(juri[0], 'tidak_ada');
    await jawab(juri[1], 'tidak_ada');
    await terapkan();

    // Sama seperti G-05: halaman operator dibiarkan terbuka.
    await operator.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.hasilVerifikasiTampil === true,
        null, { timeout: 25_000 },
    );

    const judul = await operator.halaman.locator('#judul-hasil-verifikasi').innerText();
    pastikan(/tidak valid/i.test(judul), `judul modal "${judul}"`);

    const kelas = await operator.halaman.locator('#judul-hasil-verifikasi').evaluate((el) => el.closest('div').className);
    pastikan(! /silat-merah|silat-biru/.test(kelas), `bidang modal berwarna sudut: ${kelas.slice(0, 120)}`);

    return `"${judul.trim()}", bidang netral`;
}, { halaman: operator.halaman });

await browser.close();
process.exit(laporkan('G. Modal hasil verifikasi') === 0 ? 0 : 1);
