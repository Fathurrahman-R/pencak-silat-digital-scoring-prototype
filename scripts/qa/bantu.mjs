// Perkakas bersama untuk pengujian kotak hitam alur Jurus.

import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/*
 * Playwright SENGAJA bukan devDependency.
 *
 * Tiap pemasangan di laptop gelanggang (docs/INSTALASI-LAN.md) akan ikut
 * menariknya beserta unduhan perambannya -- ratusan megabita untuk alat yang
 * dipakai beberapa kali setahun, di mesin yang menyiapkannya lewat tethering
 * HP di venue. Jadi ia dipasang saat dibutuhkan saja.
 *
 * Yang tidak boleh: mati dengan "Cannot find package 'playwright'", yang tidak
 * memberi tahu siapa pun apa yang harus dilakukan.
 */
const pw = await import('playwright').catch(() => {
    console.error([
        '',
        'Uji kotak hitam butuh Playwright, dan ia sengaja tidak ikut dipasang.',
        '',
        '  npm i playwright',
        '  npx playwright install chromium webkit',
        '',
        'Selengkapnya di docs/UJI-KOTAK-HITAM.md.',
        '',
    ].join('\n'));

    process.exit(1);
});

export const { chromium, devices, webkit } = pw;

/*
 * Seluruhnya dari env, dengan bawaan yang masuk akal di mesin gelanggang.
 *
 * QA_ASAL sengaja BUKAN 127.0.0.1: di mesin pengembangan bisa ada server lain
 * yang mengikat alamat itu secara spesifik dan menang atas nginx yang mengikat
 * 0.0.0.0 -- permintaan lalu mendarat di aplikasi yang salah tanpa satu pun
 * tanda. Alamat loopback lain melewatinya.
 */
export const CHROME = process.env.QA_CHROME
    ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
export const ASAL = process.env.QA_ASAL ?? 'http://127.0.0.2:8000';
export const SANDI = process.env.QA_SANDI ?? 'password';

/** Kejuaraan dan gelanggang sasaran. Gelanggang harus KOSONG pointernya. */
export const TURNAMEN = Number(process.env.QA_TURNAMEN ?? 1);
export const GELANGGANG = Number(process.env.QA_GELANGGANG ?? 2);

// fileURLToPath, bukan .pathname: nama foldernya mengandung spasi, dan
// .pathname menyisakan %20 yang jadi nama folder harfiah lalu ditolak Windows.
// Di luar repo: tangkapan layar bukti tidak pantas ikut ter-commit.
export const BUKTI = process.env.QA_BUKTI
    ?? fileURLToPath(new URL('./bukti/', import.meta.url));

mkdirSync(BUKTI, { recursive: true });

export const hasil = [];

/** Menjalankan satu test case, mencatat status dan buktinya. */
export async function jalankan(id, deskripsi, fn, { halaman = null } = {}) {
    const mulai = Date.now();

    try {
        const catatan = await fn();
        hasil.push({ id, deskripsi, status: 'Pass', catatan: catatan ?? '', ms: Date.now() - mulai });
        console.log(`Pass   ${id}  ${deskripsi}${catatan ? `  — ${catatan}` : ''}`);
    } catch (e) {
        const pesan = String(e.message ?? e).split('\n')[0].slice(0, 260);
        hasil.push({ id, deskripsi, status: 'Fail', catatan: pesan, ms: Date.now() - mulai });
        console.log(`FAIL   ${id}  ${deskripsi}\n         ${pesan}`);

        if (halaman) {
            const berkas = `${BUKTI}${id}.png`;
            await halaman.screenshot({ path: berkas, fullPage: true }).catch(() => {});
            console.log(`         bukti: ${berkas}`);
        }
    }
}

export function pastikan(syarat, pesan) {
    if (! syarat) {
        throw new Error(pesan);
    }
}

/** Konteks peramban yang sudah login sebagai satu peran. */
export async function masuk(browser, email, { iphone = false, asal = ASAL } = {}) {
    const konteks = await browser.newContext({
        ...(iphone ? devices['iPhone 14'] : {}),
        ignoreHTTPSErrors: true,
    });

    const halaman = await konteks.newPage();

    // `asal` bisa ditimpa: uji multi-server masuk ke NODE LAIN, bukan ke
    // alamat bawaan rangkaian ini.
    await halaman.goto(`${asal}/login`, { waitUntil: 'domcontentloaded', timeout: 40_000 });
    await halaman.fill('input[name="email"]', email);
    await halaman.fill('input[name="password"]', SANDI);
    await Promise.all([
        halaman.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 40_000 }),
        halaman.click('button[type="submit"]'),
    ]);

    pastikan(! halaman.url().includes('/login'), `gagal login sebagai ${email}`);

    return { konteks, halaman };
}

export async function bukaPeramban({ iphone = false } = {}) {
    return iphone
        ? webkit.launch({ headless: true })
        : chromium.launch({ executablePath: CHROME, headless: true });
}

/** POST JSON memakai token CSRF halaman -- jalur yang sama dengan panelnya. */
export async function kirim(halaman, url, muatan = {}) {
    return halaman.evaluate(async ([u, m]) => {
        const res = await fetch(u, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
            },
            body: JSON.stringify(m),
        });

        let badan = null;
        try { badan = await res.json(); } catch { badan = null; }

        return { status: res.status, badan };
    }, [url, muatan]);
}

export function laporkan(judul) {
    const lulus = hasil.filter((h) => h.status === 'Pass').length;
    const gagal = hasil.filter((h) => h.status === 'Fail').length;

    console.log(`\n=== ${judul} === ${lulus} Pass, ${gagal} Fail dari ${hasil.length}\n`);

    hasil.filter((h) => h.status === 'Fail')
        .forEach((h) => console.log(`  FAIL ${h.id}  ${h.deskripsi}\n       ${h.catatan}`));

    return gagal;
}
