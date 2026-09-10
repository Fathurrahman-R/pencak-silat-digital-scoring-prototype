// I. Serah-terima jadwal Jurus antar gelanggang, dari panel kendali.
//
// Prasyarat: satu penampilan Jurus TANPA battle terjadwal di gelanggang asal.
// Hanya penampilan lepas yang boleh berpindah sendirian -- kedua sudut battle
// dimainkan berurutan di matras yang sama (Pasal 12.1.d.7).

import {
    ASAL, bukaPeramban, hasil, jalankan, laporkan, masuk, pastikan, TURNAMEN,
} from './bantu.mjs';

/*
 * Lingkup daftar antrean Jurus.
 *
 * `ancestor::div[.//button][1]` pernah dipakai di sini dan MENAIKI seluruh
 * panel: ia cocok dengan dua puluh tombol "Tayangkan" milik antrean Tanding
 * plus kendali babak. Yang dituju wadah daftarnya sendiri -- satu-satunya div
 * bergulir sesudah judulnya.
 */
const DAFTAR_JURUS = '//p[normalize-space()="Antrean Jurus"]'
    + '/following::div[contains(@class,"max-h-64")][1]';

const T = TURNAMEN;
const ASAL_ARENA = Number(process.env.QA_ARENA_ASAL ?? 1);
const TUJUAN_ARENA = Number(process.env.QA_ARENA_TUJUAN ?? 2);

const browser = await bukaPeramban();

const pelepas = await masuk(browser, process.env.QA_PENGENDALI_ASAL ?? 'pengendali1@silat.test');
const penerima = await masuk(browser, process.env.QA_PENGENDALI_TUJUAN ?? 'pengendali2@silat.test');

const kendali = (arena) => `${ASAL}/admin/turnamen/${T}/gelanggang/${arena}/panel/kendali`;

const baca = (h, jalur) => h.evaluate((j) => {
    const el = document.querySelector('[x-data^="partaiPanel"]');
    if (! el) return null;
    return j.split('.').reduce((o, k) => (o == null ? null : o[k]), window.Alpine.$data(el));
}, jalur);

async function bukaKendali(sesi, arena) {
    await sesi.halaman.goto(kendali(arena), { waitUntil: 'domcontentloaded' });
    /*
     * Menunggu tarikan `state` PERTAMA, bukan sekadar `panel != null`.
     *
     * partaiPanel menimpa `panel` dengan kerangka kosong saat init -- ia sudah
     * bukan null sejak milidetik pertama, tapi belum punya blok `jurus`. Yang
     * membacanya terlalu cepat melihat antrean kosong dan menyimpulkan
     * fiturnya rusak.
     */
    await sesi.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.panel?.jurus !== undefined,
        null, { timeout: 20_000 },
    );
}

/** Memilih gelanggang tujuan pada baris antrean Jurus. */
async function pindahkan(sesi, keArena) {
    const pilih = sesi.halaman.locator(
        `xpath=${DAFTAR_JURUS}//select[@aria-label="Pindahkan penampilan ini ke gelanggang lain"]`,
    );

    pastikan(await pilih.count() > 0, 'pilihan "Pindahkan…" tidak ada di antrean Jurus');

    await pilih.first().selectOption(String(keArena));
    await sesi.halaman.waitForTimeout(2000);
}

let penampilanId = null;

await jalankan('I-00', 'Antrean Jurus gelanggang asal berisi penampilan lepas', async () => {
    await bukaKendali(pelepas, ASAL_ARENA);

    const antrean = await baca(pelepas.halaman, 'panel.jurus.antrean');
    pastikan(Array.isArray(antrean) && antrean.length > 0, 'antrean Jurus kosong');

    const lepas = antrean.find((a) => ! a.battle);
    pastikan(lepas, 'tidak ada penampilan tanpa battle di antrean');

    penampilanId = lepas.id;

    return `penampilan ${penampilanId} siap dipindahkan`;
}, { halaman: pelepas.halaman });

await jalankan('I-01', 'Melepas ke gelanggang lain memindahkannya ke "menunggu diambil"', async () => {
    await pindahkan(pelepas, TUJUAN_ARENA);

    const menunggu = await baca(pelepas.halaman, 'panel.serah.menunggu');
    pastikan(Array.isArray(menunggu) && menunggu.length > 0, 'daftar "menunggu diambil" kosong');

    /*
     * Barisnya SENGAJA tetap berdiri di antrean pelepas: melepas tidak pernah
     * memindahkan `arena_id`: yang memindahkannya node penerima, sesudah
     * adopsinya tercatat. Yang diuji karena itu bukan hilangnya baris,
     * melainkan bahwa ia ditandai -- tanpa tombol yang pasti dijawab 422.
     */
    const antrean = await baca(pelepas.halaman, 'panel.jurus.antrean');
    pastikan((antrean ?? []).some((a) => a.id === penampilanId), 'baris hilang dari antrean pelepas');

    const teks = await pelepas.halaman.innerText('body');
    pastikan(/dilepas|menunggu diambil/i.test(teks), 'bagian "Dilepas, menunggu diambil" tidak tergambar');
    pastikan(/ditawarkan ke/i.test(teks), 'baris antrean tidak menyebut ia sedang ditawarkan');

    /*
     * Dilingkupi ke BARIS yang ditawarkan, bukan ke seluruh daftar: baris lain
     * di antrean yang sama tetap boleh -- dan harus -- menawarkan Tayangkan.
     */
    const barisDitawarkan = `${DAFTAR_JURUS}`
        + '//span[contains(normalize-space(),"Ditawarkan ke")]/ancestor::div[1]';

    const tombol = await pelepas.halaman
        .locator(`xpath=${barisDitawarkan}//button[normalize-space()="Tayangkan"]`).count();
    pastikan(tombol === 0, 'baris yang ditawarkan masih menawarkan tombol Tayangkan');

    const pindah = await pelepas.halaman
        .locator(`xpath=${barisDitawarkan}//select`).count();
    pastikan(pindah === 0, 'baris yang ditawarkan masih menawarkan menu Pindahkan…');

    return `${menunggu.length} baris menunggu diambil, barisnya ditandai`;
}, { halaman: pelepas.halaman });

await jalankan('I-02', 'Pelepas tidak bisa menayangkan baris yang sudah ditawarkan', async () => {
    const balasan = await pelepas.halaman.evaluate(async ([asal, t, arena, id]) => {
        const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
        const res = await fetch(`${asal}/admin/turnamen/${t}/gelanggang/${arena}/panel/penampilan-aktif`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ performance_id: id }),
        });

        let badan = null;
        try { badan = await res.json(); } catch { badan = null; }

        return { status: res.status, badan: JSON.stringify(badan ?? {}) };
    }, [ASAL, T, ASAL_ARENA, penampilanId]);

    pastikan(balasan.status >= 400, `status ${balasan.status}, diharap penolakan`);
    pastikan(
        /sedang ditawarkan/i.test(balasan.badan),
        `pesan tidak menyebut penawaran: ${balasan.badan.slice(0, 200)}`,
    );

    const menyebutTujuan = /Gelanggang/i.test(balasan.badan);
    pastikan(menyebutTujuan, 'pesan tidak menyebut gelanggang tujuannya');

    return `ditolak ${balasan.status}, pesannya menyebut gelanggang tujuan`;
}, { halaman: pelepas.halaman });

await jalankan('I-04', 'Pelepas bisa membatalkan selama belum diambil', async () => {
    await pelepas.halaman.locator('button:has-text("Batalkan")').first().click();
    await pelepas.halaman.waitForTimeout(2000);

    const menunggu = await baca(pelepas.halaman, 'panel.serah.menunggu');
    pastikan((menunggu ?? []).length === 0, 'penawaran masih menggantung sesudah dibatalkan');

    const antrean = await baca(pelepas.halaman, 'panel.jurus.antrean');
    pastikan((antrean ?? []).some((a) => a.id === penampilanId), 'baris tidak kembali ke antrean pelepas');

    return 'baris kembali ke gelanggang asal';
}, { halaman: pelepas.halaman });

await jalankan('I-03', 'Penerima mengambil, dan barisnya pindah ke antreannya', async () => {
    await pindahkan(pelepas, TUJUAN_ARENA);

    await bukaKendali(penerima, TUJUAN_ARENA);
    await penerima.halaman.waitForFunction(
        () => (window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.panel?.serah?.ditawarkan?.length ?? 0) > 0,
        null, { timeout: 20_000 },
    ).catch(() => { throw new Error('bagian "Ditawarkan dari gelanggang lain" tidak pernah berisi'); });

    await penerima.halaman.locator('button:has-text("Ambil")').first().click();
    await penerima.halaman.waitForTimeout(2500);

    const antrean = await baca(penerima.halaman, 'panel.jurus.antrean');
    pastikan(
        (antrean ?? []).some((a) => a.id === penampilanId),
        'baris tidak muncul di antrean penerima',
    );

    return 'baris masuk antrean gelanggang tujuan';
}, { halaman: penerima.halaman });

await jalankan('I-05', 'Satu sudut battle tidak ditawari pindah, dan kalimatnya menyebut jalan keluarnya', async () => {
    await bukaKendali(pelepas, ASAL_ARENA);

    const antrean = await baca(pelepas.halaman, 'panel.jurus.antrean');
    const sudutBattle = (antrean ?? []).find((a) => a.battle);

    if (! sudutBattle) {
        return 'dilewati: tidak ada sudut battle di antrean gelanggang asal';
    }

    const teks = await pelepas.halaman.innerText('body');
    pastikan(/pindahkan lewat menu jadwal/i.test(teks), 'kalimat penggantinya tidak tergambar');

    return 'ditahan, dengan kalimat yang menyebut menu Jadwal';
}, { halaman: pelepas.halaman });

await browser.close();
process.exit(laporkan('I. Serah-terima jadwal Jurus') === 0 ? 0 : 1);
