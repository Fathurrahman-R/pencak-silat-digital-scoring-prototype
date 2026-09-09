// Ulang E-02/E-03 yang tadi 403, kali ini dengan izin cetak yang sudah ada.
// Murni baca: tidak ada satu pun baris yang ditulis.

import { ASAL, bukaPeramban, hasil, jalankan, laporkan, masuk, pastikan, GELANGGANG, TURNAMEN } from './bantu.mjs';

const T = TURNAMEN;

// Nomor Jurus yang bagannya sudah tersusun.
const NOMOR = Number(process.env.QA_NOMOR_JURUS ?? 1);
const browser = await bukaPeramban();

for (const email of ['ketua@silat.test', 'sekretariat@silat.test']) {
    const sesi = await masuk(browser, email);
    const peran = email.split('@')[0];

    await jalankan(`E-02/${peran}`, 'Cetak PDF bagan Jurus', async () => {
        const balasan = await sesi.halaman.request.get(`${ASAL}/admin/turnamen/${T}/jurus/${NOMOR}/bagan/cetak`);

        pastikan(balasan.status() === 200, `status ${balasan.status()}`);
        pastikan(
            (balasan.headers()['content-type'] ?? '').includes('application/pdf'),
            `content-type ${balasan.headers()['content-type']}`,
        );

        return `PDF ${Math.round((await balasan.body()).length / 1024)} KB`;
    });

    await jalankan(`E-03/${peran}`, 'Cetak PDF jadwal tab Jurus', async () => {
        const balasan = await sesi.halaman.request.get(`${ASAL}/admin/turnamen/${T}/jadwal/jurus/cetak`);

        pastikan(balasan.status() === 200, `status ${balasan.status()}`);

        return `PDF ${Math.round((await balasan.body()).length / 1024)} KB`;
    });

    await jalankan(`E-05/${peran}`, 'Tombol Cetak PDF TERGAMBAR di halaman bagan', async () => {
        await sesi.halaman.goto(`${ASAL}/admin/turnamen/${T}/jurus/${NOMOR}/bagan`, { waitUntil: 'domcontentloaded' });

        const tombol = await sesi.halaman.locator('a:has-text("Cetak PDF")').count();
        pastikan(tombol > 0, 'tombol Cetak PDF masih tersembunyi');

        return 'tombol terlihat';
    }, { halaman: sesi.halaman });

    await sesi.konteks.close();
}

// Official kontingen boleh melihat, tidak boleh menerbitkan cetakan.
const official = await masuk(browser, 'official1@silat.test');

await jalankan('E-06', 'Official kontingen tetap ditolak mencetak bagan', async () => {
    const balasan = await official.halaman.request.get(`${ASAL}/admin/turnamen/${T}/jurus/${NOMOR}/bagan/cetak`);

    pastikan(balasan.status() === 403, `status ${balasan.status()}, diharap 403`);

    return '403';
});

await browser.close();
process.exit(laporkan('E. Cetak sesudah perbaikan BUG-01') === 0 ? 0 : 1);
