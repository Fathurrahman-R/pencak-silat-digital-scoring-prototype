// Uji Reverb di MESIN SAFARI, dengan emulasi perangkat iPhone.
//
// WebKit di sini adalah mesin yang sama yang menjalankan Safari; profil
// perangkat `iPhone 14` memberinya viewport, sentuh, dan user agent iOS.
// Ini sedekat mungkin dengan Safari iOS tanpa perangkat sungguhan -- dan yang
// paling penting, penegakan konten campurannya SAMA, karena itu bagian dari
// mesinnya, bukan dari kulit aplikasinya.
//
// Dua origin, satu bundel aset yang sama:
//   http  ke IP LAN     -> ws://<ip>:8080
//   https lewat proksi  -> wss://<host>:8443/app/*
//
// Yang kedua adalah jalur yang dilaporkan rusak.

import { ASAL, devices, GELANGGANG, SANDI, TURNAMEN, webkit } from './bantu.mjs';

const AKUN = { email: process.env.QA_AKUN ?? 'pengendali1@silat.test', sandi: SANDI };
const PANEL = `/admin/turnamen/${TURNAMEN}/gelanggang/${GELANGGANG}/panel/kendali`;

/*
 * Dua origin, satu bundel aset yang sama. Yang kedua menuntut proksi TLS
 * lokal berjalan lebih dulu -- lihat proksi-tunnel.mjs di folder ini.
 *
 * QA_ASAL_LAN diisi IP LAN mesin ini saat itu (`ipconfig`), bukan alamat di
 * .env yang sering tertinggal di jaringan venue sebelumnya.
 */
const ORIGIN = [
    {
        nama: 'LAN http',
        asal: process.env.QA_ASAL_LAN ?? ASAL,
        skema: 'ws:',
        port: process.env.QA_PORT_REVERB ?? '8080',
    },
    {
        nama: 'tunnel https',
        asal: process.env.QA_ASAL_TUNNEL ?? 'https://localhost:8443',
        skema: 'wss:',
        port: process.env.QA_PORT_TUNNEL ?? '8443',
    },
];

const browser = await webkit.launch({ headless: true });
let gagal = 0;

console.log(`WebKit ${browser.version()} · profil perangkat: iPhone 14\n`);

for (const { nama, asal, skema, port } of ORIGIN) {
    const konteks = await browser.newContext({
        ...devices['iPhone 14'],
        ignoreHTTPSErrors: true,
    });

    const halaman = await konteks.newPage();

    const konsol = [];
    halaman.on('console', (p) => konsol.push(`${p.type()}: ${p.text()}`));
    halaman.on('pageerror', (e) => konsol.push(`pageerror: ${e.message}`));

    const soket = [];
    halaman.on('websocket', (ws) => soket.push(ws.url()));

    try {
        await halaman.goto(`${asal}/login`, { waitUntil: 'domcontentloaded', timeout: 40_000 });
        await halaman.fill('input[name="email"]', AKUN.email);
        await halaman.fill('input[name="password"]', AKUN.sandi);
        await Promise.all([
            halaman.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 40_000 }),
            halaman.click('button[type="submit"]'),
        ]);

        await halaman.goto(asal + PANEL, { waitUntil: 'domcontentloaded', timeout: 40_000 });

        const status = await halaman.waitForFunction(() => {
            const keadaan = window.Echo?.connector?.pusher?.connection?.state;

            return (keadaan === undefined || keadaan === 'connecting' || keadaan === 'initialized')
                ? false
                : keadaan;
        }, null, { timeout: 40_000 }).then((h) => h.jsonValue());

        /*
         * Penanda koneksi di layar ikut diperiksa, bukan cuma keadaan di dalam
         * pustaka: itulah yang sungguh-sungguh dibaca petugas di pinggir
         * matras, dan itulah yang dilaporkan berbunyi "Terputus".
         */
        /*
         * Diberi waktu MENETAP dulu. Status di dalam pustaka berubah lebih
         * dulu daripada penanda di layar; membacanya di detik yang sama
         * menghasilkan "Terputus" yang sebenarnya sedang dalam perjalanan
         * menjadi "tersambung".
         */
        await halaman.waitForFunction(
            () => window.Alpine?.store('koneksi')?.tersambung === true,
            null,
            { timeout: 10_000 },
        ).catch(() => {});

        const penanda = await halaman.evaluate(() => ({
            store: window.Alpine?.store('koneksi')?.tersambung ?? null,
            teks: document.body.innerText.includes('Terputus'),
        }));

        const alamat = soket.find((u) => u.includes('/app/')) ?? '(tidak ada WebSocket dibuka)';
        const salah = [];

        if (! alamat.startsWith(skema)) {
            salah.push(`alamat "${alamat}" tidak berskema ${skema}`);
        }

        if (! alamat.includes(`:${port}/`)) {
            salah.push(`alamat "${alamat}" tidak berport ${port}`);
        }

        if (status !== 'connected') {
            salah.push(`status koneksi "${status}", diharap "connected"`);
        }

        if (penanda.store !== true) {
            salah.push(`penanda koneksi di layar bukan "tersambung" (store=${penanda.store}, teks Terputus=${penanda.teks})`);
        }

        const diblokir = konsol.filter((b) => /[Mm]ixed [Cc]ontent|insecure|blocked/i.test(b));

        if (diblokir.length > 0) {
            salah.push(`peramban memblokir sesuatu: ${diblokir[0]}`);
        }

        console.log(
            `${salah.length === 0 ? 'LULUS' : 'GAGAL'}  ${nama.padEnd(13)} `
            + `${alamat.split('?')[0]}  status=${status}  penanda=${penanda.store === true ? 'tersambung' : 'Terputus'}`,
        );

        salah.forEach((s) => console.log(`       ${s}`));

        if (salah.length > 0) {
            konsol.slice(-8).forEach((b) => console.log(`       konsol: ${b}`));
        }

        gagal += salah.length === 0 ? 0 : 1;
    } catch (e) {
        console.log(`GAGAL  ${nama.padEnd(13)} ${e.message.split('\n')[0]}`);
        konsol.slice(-8).forEach((b) => console.log(`       konsol: ${b}`));
        gagal += 1;
    } finally {
        await konteks.close();
    }
}

await browser.close();
process.exit(gagal === 0 ? 0 : 1);
