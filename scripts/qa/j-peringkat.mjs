// J. Nomor Jurus berformat PERINGKAT, ujung ke ujung.
//
// Seluruh blok sebelumnya battle: dua sudut, satu pemenang, satu bagan. Format
// yang satunya belum pernah dijalankan sekali pun di peramban -- padahal
// Tunggal, Ganda, dan Regu di banyak kejuaraan dinilai begitu: semua tampil,
// median menentukan peringkat, tidak ada lawan.
//
// Yang diuji karena itu bukan cuma "bisa dinilai", melainkan yang HANYA ada di
// jalur ini: kartu Buat penampilan, penampilan lepas di tab Jadwal, pengurangan
// Pengawas dan pembatalannya, ketiadaan komparasi battle, dan pemecah seri yang
// berjalan SENDIRI -- kebalikan dari battle, yang seri-nya justru berhenti dan
// menunggu keputusan Ketua (blok B).
//
// Prasyarat: satu nomor Jurus dengan >= 2 pendaftaran sah yang BELUM punya
// penampilan (formatnya diubah di J-00, dan ubah format ditolak kalau
// penampilannya sudah ada). Gelanggang sasaran pointernya kosong.

import {
    ASAL, bukaPeramban, jalankan, laporkan, masuk, pastikan, GELANGGANG, TURNAMEN,
} from './bantu.mjs';

const T = TURNAMEN;
const ARENA = GELANGGANG;
const NOMOR = Number(process.env.QA_NOMOR_PERINGKAT ?? 1);

const browser = await bukaPeramban();

/*
 * Akun `sekretariat@` kini berperan Operator IT: satu meja untuk seluruh
 * administrasi kejuaraan, sesudah peran Sekretariat lebur ke sana (September
 * 2026). Alamat surelnya sengaja tidak diganti -- yang duduk di meja itu
 * mengenalinya.
 */
const sekretariat = await masuk(browser, 'sekretariat@silat.test');
const ketua = await masuk(browser, 'ketua@silat.test');
const pengendali = await masuk(browser, 'pengendali2@silat.test');
const operator = await masuk(browser, 'operator2@silat.test');

// Pengurangan Pasal 12.1.e dijatuhkan PENGAWAS, bukan Operator IT: yang
// terakhir menjalankan timernya, yang pertama menilai jalannya penampilan.
const pengawas = await masuk(browser, 'pengawas@silat.test');
const juri = [];

for (const n of [1, 2, 3, 4]) {
    juri.push(await masuk(browser, `juri${n}@silat.test`));
}

const daftarNomor = `${ASAL}/admin/turnamen/${T}/jurus`;
const halamanNomor = `${ASAL}/admin/turnamen/${T}/jurus/${NOMOR}`;
const jadwalJurus = `${ASAL}/admin/turnamen/${T}/jadwal/jurus`;
const panel = (peran) => `${ASAL}/admin/turnamen/${T}/gelanggang/${ARENA}/panel/${peran}`;
const OPERATOR_JURUS = panel('jurus-operator');

// Lingkup daftar antrean Jurus di panel kendali: `ancestor::div[...]` menaiki
// pohon sampai pembungkus seluruh panel, dan menjaring tombol milik antrean
// Tanding.
const DAFTAR_JURUS = '//p[normalize-space()="Antrean Jurus"]'
    + '/following::div[contains(@class,"max-h-64")][1]';

const baca = (h, pilih, jalur) => h.evaluate(([p, j]) => {
    const el = document.querySelector(p);
    if (! el) return null;

    return j.split('.').reduce((o, k) => (o == null ? null : o[k]), window.Alpine.$data(el));
}, [pilih, jalur]);

/**
 * Baris "Peringkat sementara" halaman nomor, urut dari atas.
 *
 * Dikenali dari tautan Operator tiap baris, bukan dari kartu yang dicari lewat
 * innerText: yang terakhir mendarat di pembungkus judulnya, dan pembungkus itu
 * tidak berisi satu baris pun. Tautannya sekalian membawa id penampilan, jadi
 * urutan bisa dibandingkan tanpa mencocokkan nama.
 */
async function peringkat(sesi) {
    await sesi.halaman.goto(halamanNomor, { waitUntil: 'domcontentloaded' });

    return sesi.halaman.evaluate(() => [...document.querySelectorAll('a[href*="/penampilan/"][href$="/operator"]')]
        .map((tautan) => {
            const teks = (tautan.closest('div')?.innerText ?? '').split('\n')
                .map((s) => s.trim()).filter(Boolean);

            return {
                id: /penampilan\/(\d+)\/operator/.exec(tautan.getAttribute('href'))?.[1] ?? '',
                peserta: teks[1] ?? '',
                skor: teks.find((s) => /^\d+\.\d\d$/.test(s)) ?? '',
            };
        }));
}

const penampilan = [];

await jalankan('J-00', 'Sekretariat mengubah nomor ke format peringkat', async () => {
    await sekretariat.halaman.goto(daftarNomor, { waitUntil: 'domcontentloaded' });

    const pilih = sekretariat.halaman.locator(`form[action$="/jurus/${NOMOR}/format"] select[name="format"]`);
    pastikan(await pilih.count() > 0, 'Sekretariat tidak menemukan pilihan format — nomor-jurus.update hilang lagi?');

    await Promise.all([
        sekretariat.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        pilih.selectOption('penampilan'),
    ]);

    const nilai = await sekretariat.halaman
        .locator(`form[action$="/jurus/${NOMOR}/format"] select[name="format"]`).inputValue();
    pastikan(nilai === 'penampilan', `format tersimpan ${nilai}`);

    return 'format = peringkat';
}, { halaman: sekretariat.halaman });

await jalankan('J-01', 'Kartu "Buat penampilan" menggantikan kartu bagan', async () => {
    await ketua.halaman.goto(halamanNomor, { waitUntil: 'domcontentloaded' });

    const teks = await ketua.halaman.innerText('body');
    pastikan(/buat penampilan/i.test(teks), 'kartu "Buat penampilan" tidak tergambar');
    pastikan(! /susun bagan|susun ulang bagan/i.test(teks), 'kartu bagan masih tergambar untuk nomor tanpa bagan');

    const form = ketua.halaman.locator('form[action$="/buat-penampilan"]');
    await form.locator('select[name="tahap"]').selectOption('penyisihan');
    await Promise.all([
        ketua.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        form.locator('button[type="submit"]').click(),
    ]);

    const baris = await peringkat(ketua);
    pastikan(baris.length >= 2, `peringkat berisi ${baris.length} baris, diharap >= 2`);

    return `${baris.length} penampilan dibuat`;
}, { halaman: ketua.halaman });

await jalankan('J-02', 'Penampilan lepas berdiri di tab Jadwal tanpa sudut, dijadwalkan satu per satu', async () => {
    await ketua.halaman.goto(jadwalJurus, { waitUntil: 'domcontentloaded' });

    const pemilih = 'form[action*="/jadwal/jurus/penampilan/"][action$="/tetapkan"]';
    const jumlah = await ketua.halaman.locator(pemilih).count();
    pastikan(jumlah >= 2, `form jadwalkan penampilan ada ${jumlah}, diharap >= 2`);

    // Dua kali pada form PERTAMA: tiap penampilan berdiri sendiri, dan yang
    // sudah dijadwalkan lenyap dari daftar sesudah halaman dimuat ulang. Bukan
    // satu tombol yang memindahkan sepasang, seperti battle.
    for (let i = 0; i < 2; i++) {
        const satu = ketua.halaman.locator(pemilih).first();
        penampilan.push((await satu.getAttribute('action')).match(/penampilan\/(\d+)\/tetapkan/)[1]);

        await satu.locator('select[name="arena_id"]').selectOption({ label: 'Gelanggang B' });
        await Promise.all([
            ketua.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
            satu.locator('button[type="submit"]').click(),
        ]);
    }

    // Chip sudut kosong: penampilan tanpa battle tidak punya sudut, dan
    // mewarnainya merah atau biru membuat orang mencari lawan yang tidak ada.
    const sudut = await ketua.halaman.evaluate(() => {
        const kartu = [...document.querySelectorAll('div')]
            .filter((d) => d.innerText?.startsWith('Gelanggang B') && d.querySelector('input[name="urutan"]')).pop();

        return [...(kartu?.querySelectorAll('.bg-corner-red, .bg-corner-blue') ?? [])].length;
    });

    pastikan(sudut === 0, `${sudut} chip sudut tergambar untuk penampilan tanpa battle`);

    return `penampilan ${penampilan.join(', ')} ke Gelanggang B, tanpa sudut`;
}, { halaman: ketua.halaman });

await jalankan('J-03', 'Antrean kendali menawarkan Pindahkan, bukan kalimat "satu sudut battle"', async () => {
    await pengendali.halaman.goto(panel('kendali'), { waitUntil: 'domcontentloaded' });
    await pengendali.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.panel?.jurus !== undefined,
        null, { timeout: 20_000 },
    );

    const antrean = await baca(pengendali.halaman, '[x-data^="partaiPanel"]', 'panel.jurus.antrean');
    pastikan((antrean ?? []).length === 2, `antrean ${(antrean ?? []).length}, diharap 2`);
    pastikan(antrean.every((a) => ! a.battle), 'baris antrean mengaku punya battle induk');

    const pilih = await pengendali.halaman.locator(
        `xpath=${DAFTAR_JURUS}//select[@aria-label="Pindahkan penampilan ini ke gelanggang lain"]`,
    ).count();

    pastikan(pilih === 2, `${pilih} baris menawarkan Pindahkan, diharap 2`);

    const teks = await pengendali.halaman.innerText('body');
    pastikan(! /satu sudut battle/i.test(teks), 'kalimat penahan battle tergambar di nomor tanpa battle');

    return 'dua baris lepas, keduanya boleh pindah';
}, { halaman: pengendali.halaman });

async function tayangkanJurus(sesi, indeks) {
    const tombol = sesi.halaman.locator(`xpath=${DAFTAR_JURUS}//button[normalize-space()="Tayangkan"]`);

    const n = await tombol.count();
    pastikan(n > 0, 'blok Antrean Jurus tidak punya tombol Tayangkan');
    await tombol.nth(Math.min(indeks, n - 1)).click();
}

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

await jalankan('J-04', 'Penampilan pertama: empat nilai berbeda, median rata-rata dua tengah', async () => {
    await tayangkanJurus(pengendali, 0);
    await pengendali.halaman.waitForTimeout(2000);

    /*
     * Blok `jurus`, `serah`, dan `tayang` ikut saat HALAMAN dirender, bukan di
     * endpoint `state` yang ditarik tiap tekanan tombol juri -- menaruhnya di
     * sana berarti dua query tambahan di jalur terpanas. Jadi yang membaca
     * pointer sesudah menekan Tayangkan harus memuat ulang, bukan menunggu.
     */
    await pengendali.halaman.reload({ waitUntil: 'domcontentloaded' });
    await pengendali.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="partaiPanel"]'))?.panel?.tayang === 'jurus',
        null, { timeout: 20_000 },
    );

    await jalankanTimer(operator);

    // 9.60 9.70 9.70 9.80 -> dua nilai tengah 9.70 dan 9.70 -> median 9.70,
    // dengan sebaran yang TIDAK nol. Sebaran itu yang memutus seri di J-08.
    for (const [i, sesi] of juri.entries()) {
        await nilai(sesi, ['9.60', '9.70', '9.70', '9.80'][i]);
    }

    await operator.halaman.goto(OPERATOR_JURUS, { waitUntil: 'domcontentloaded' });
    await operator.halaman.waitForFunction(
        () => window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.nilaiJuri?.length === 4,
        null, { timeout: 20_000 },
    );

    const median = await baca(operator.halaman, '[x-data^="jurusPanel"]', 'skor.median');
    pastikan(Number(median).toFixed(2) === '9.70', `median ${median}, diharap 9.70`);

    return `median ${Number(median).toFixed(2)} dari 9.60/9.70/9.70/9.80`;
}, { halaman: operator.halaman });

await jalankan('J-05', 'Pengurangan Pengawas memotong skor akhir, dan pembatalan mengembalikannya', async () => {
    await pengawas.halaman.goto(OPERATOR_JURUS, { waitUntil: 'domcontentloaded' });
    await pengawas.halaman.waitForSelector('button:has-text("Keluar gelanggang")', { timeout: 20_000 });

    await pengawas.halaman.locator('button:has-text("Keluar gelanggang")').first().click();
    await pengawas.halaman.waitForFunction(
        () => (window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.pengurangan?.length ?? 0) > 0,
        null, { timeout: 20_000 },
    );

    const dipotong = await baca(pengawas.halaman, '[x-data^="jurusPanel"]', 'skor.akhir');
    pastikan(Number(dipotong).toFixed(2) === '9.20', `skor akhir ${dipotong}, diharap 9.20`);

    // Pembatalan MENUNTUT alasan: tombolnya mati sampai isiannya terisi.
    const batal = pengawas.halaman.locator('button:text-is("Batal")').first();
    pastikan(await batal.isDisabled(), 'tombol Batal hidup padahal alasannya kosong');

    await pengawas.halaman.locator('input[placeholder="Alasan batal"]').first().fill('Salah tekan');
    await batal.click();

    await pengawas.halaman.waitForFunction(
        () => (window.Alpine?.$data(document.querySelector('[x-data^="jurusPanel"]'))?.pengurangan?.length ?? 0) === 0,
        null, { timeout: 20_000 },
    );

    const pulih = await baca(pengawas.halaman, '[x-data^="jurusPanel"]', 'skor.akhir');
    pastikan(Number(pulih).toFixed(2) === '9.70', `skor akhir sesudah pembatalan ${pulih}, diharap 9.70`);

    return '9.70 lalu 9.20 lalu 9.70';
}, { halaman: pengawas.halaman });

await jalankan('J-06', 'Tidak ada komparasi battle maupun "Tetapkan pemenang" di nomor tanpa battle', async () => {
    await sahkan(ketua);

    for (const [nama, sesi, peran] of [['papan', operator, 'papan'], ['juri', juri[0], 'juri'], ['ketua', ketua, 'ketua']]) {
        await sesi.halaman.goto(panel(peran), { waitUntil: 'domcontentloaded' });
        await sesi.halaman.waitForTimeout(1500);

        const komparasi = await baca(sesi.halaman, '[x-data^="jurusPanel"]', 'komparasi');
        pastikan(komparasi == null || komparasi.siap !== true, `komparasi siap di panel ${nama}`);

        const teks = await sesi.halaman.innerText('body');
        pastikan(! /perbandingan nilai/i.test(teks), `blok komparasi tergambar di panel ${nama}`);
        pastikan(! /tetapkan pemenang/i.test(teks), `tombol "Tetapkan pemenang" tergambar di panel ${nama}`);
    }

    return 'tiga panel bersih dari komparasi';
}, { halaman: ketua.halaman });

await jalankan('J-07', 'Penampilan kedua: empat nilai sama, median yang sama persis', async () => {
    await pengendali.halaman.goto(panel('kendali'), { waitUntil: 'domcontentloaded' });
    await pengendali.halaman.waitForFunction(
        () => document.body.innerText.includes('Antrean Jurus'), null, { timeout: 20_000 },
    );

    await tayangkanJurus(pengendali, 0);
    await pengendali.halaman.waitForTimeout(2000);

    await jalankanTimer(operator);

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

    await sahkan(ketua);

    return 'dua penampilan berskor akhir 9.70';
}, { halaman: operator.halaman });

await jalankan('J-08', 'Skor sama diurutkan sendiri oleh pemecah seri, tanpa menunggu Ketua', async () => {
    const baris = await peringkat(ketua);

    pastikan(baris.length >= 2, `peringkat berisi ${baris.length} baris`);
    pastikan(baris[0].skor === '9.70' && baris[1].skor === '9.70',
        `skor ${baris[0].skor} dan ${baris[1].skor}, diharap dua-duanya 9.70`);

    /*
     * Keduanya berskor sama, jadi yang memutuskan rantai pemecah seri: hukuman
     * sama-sama nol, waktu acuan tidak dipakai halaman ini, lalu STANDAR
     * DEVIASI terendah. Penampilan kedua dinilai 9.70 empat kali (sebaran nol)
     * dan harus berdiri di atas yang dinilai 9.60/9.70/9.70/9.80.
     *
     * Inilah bedanya dengan battle: di sana seri BERHENTI dan menunggu
     * keputusan Ketua (blok B); di sini tidak boleh ada yang menunggu siapa pun.
     */
    const sebaranNol = penampilan[1];

    pastikan(
        baris[0].id === String(sebaranNol),
        `peringkat 1 diisi penampilan ${baris[0].id}, diharap ${sebaranNol} yang sebarannya nol`,
    );

    const teks = await ketua.halaman.innerText('body');
    pastikan(! /menunggu keputusan/i.test(teks), 'halaman nomor meminta keputusan seri di format peringkat');

    return `penampilan ${sebaranNol} di atas, tanpa keputusan Ketua`;
}, { halaman: ketua.halaman });

await jalankan('J-09', 'Format tidak bisa diubah lagi sesudah penampilannya ada', async () => {
    await sekretariat.halaman.goto(daftarNomor, { waitUntil: 'domcontentloaded' });

    const pilih = sekretariat.halaman.locator(`form[action$="/jurus/${NOMOR}/format"] select[name="format"]`);
    await Promise.all([
        sekretariat.halaman.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        pilih.selectOption('battle'),
    ]);

    const teks = await sekretariat.halaman.innerText('body');
    pastikan(/hapus penampilannya lebih dulu/i.test(teks), `penolakan tidak menyebut jalan keluarnya: ${teks.slice(0, 200)}`);

    const nilai = await sekretariat.halaman
        .locator(`form[action$="/jurus/${NOMOR}/format"] select[name="format"]`).inputValue();
    pastikan(nilai === 'penampilan', `format berubah jadi ${nilai} padahal penampilannya sudah ada`);

    return 'ditolak, formatnya tetap peringkat';
}, { halaman: sekretariat.halaman });

await browser.close();
process.exit(laporkan('J. Nomor Jurus berformat peringkat') === 0 ? 0 : 1);
