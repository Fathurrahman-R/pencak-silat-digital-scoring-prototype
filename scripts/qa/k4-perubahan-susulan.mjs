// K4. Perubahan susulan sesudah pemasangan: yang ditulis node global sesudah
// laptop gelanggang terpasang harus ikut menyusul, dan yang sudah dimainkan di
// gelanggang tidak boleh ditimpa salinan basi.
//
// Perubahan di node global disiapkan lewat `php artisan tinker` -- satu model
// Eloquent disimpan, jalan yang sama dengan formulir admin mana pun sampai ke
// observer sinkronnya. Yang diuji di sini perjalanannya, bukan formulirnya.
//
// Prasyarat: k3-hari-pertandingan.mjs sudah dijalankan (partai QA_PARTAI_B
// selesai dan sah di node gelanggang), dan skrip pembantu
// scripts/qa/k4-siapkan.php sudah menulis perubahan di node global.

import { execSync } from 'node:child_process';

import { bukaPeramban, jalankan, laporkan, masuk, pastikan } from './bantu.mjs';

const B = process.env.QA_NODE_GELANGGANG ?? 'http://127.0.0.4:8010';
const HARAP = JSON.parse(process.env.QA_HARAPAN ?? '{}');

pastikan(HARAP.atlet && HARAP.namaBaru && HARAP.akunBaru, 'QA_HARAPAN wajib diisi oleh k4-siapkan.php');

const browser = await bukaPeramban();
const ketuaB = await masuk(browser, 'ketua@silat.test', { asal: B });

// Operator IT, bukan Ketua: halaman kontingen dijaga `kontingen.view`, dan
// sesudah peleburan peran hanya meja administrasi yang memegangnya.
const operatorB = await masuk(browser, 'operator2@silat.test', { asal: B });

const kirim = (sesi, url, muatan = {}) => sesi.halaman.evaluate(async ([u, m]) => {
    const res = await fetch(u, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        },
        body: JSON.stringify(m),
    });

    return { status: res.status, badan: (await res.text()).slice(0, 400) };
}, [url, muatan]);

async function tarik() {
    await ketuaB.halaman.goto(`${B}/admin/sinkron`, { waitUntil: 'domcontentloaded' });

    const jumlah = { diterapkan: 0, dihapus: 0, ditolak: 0 };

    for (let i = 0; i < 30; i++) {
        const b = await kirim(ketuaB, `${B}/admin/sinkron/tarik`, { peer: 'global' });
        pastikan(b.status === 200, `tarik dijawab ${b.status}: ${b.badan.slice(0, 200)}`);

        const h = JSON.parse(b.badan);
        jumlah.diterapkan += h.diterapkan;
        jumlah.dihapus += h.dihapus;
        jumlah.ditolak += h.ditolak;

        if (h.selesai) {
            return jumlah;
        }
    }

    throw new Error('penarikan tidak selesai dalam 30 potongan');
}

await jalankan('K-22', 'Node gelanggang menarik perubahan susulan tanpa galat', async () => {
    const j = await tarik();

    return `${j.diterapkan} diterapkan, ${j.dihapus} dihapus, ${j.ditolak} ditolak`;
}, { halaman: ketuaB.halaman });

/*
 * Pemeriksaan isi dilakukan lewat halaman yang menampilkannya -- nama atlet di
 * daftar kontingen, akun di daftar pengguna -- bukan lewat basis data: yang
 * dilihat panitia di layar itulah yang harus benar.
 */
await jalankan('K-23', 'Nama atlet yang disunting di node global tampil di node gelanggang', async () => {
    /*
     * Lewat kotak CARI, bukan halaman pertama: daftarnya dipaginasi 25 per
     * halaman urut abjad, dan membaca halaman pertama saja pernah membuat
     * nama yang sudah tiba terbaca "tidak tampil" -- atlet itu sedang duduk
     * di halaman dua.
     */
    const cari = encodeURIComponent(HARAP.namaLama);
    await operatorB.halaman.goto(`${B}/admin/turnamen/${HARAP.turnamen}/kontingen/${HARAP.kontingen}/atlet?q=${cari}`, { waitUntil: 'domcontentloaded' });

    const teks = await operatorB.halaman.innerText('body');

    pastikan(teks.includes(HARAP.namaBaru), `nama baru "${HARAP.namaBaru}" tidak tampil di node gelanggang`);

    // Nama lama adalah awalan nama baru, jadi yang diperiksa baris yang
    // PERSIS bernama lama -- bukan sekadar teks yang memuatnya.
    const barisPersisLama = teks.split('\n').map((s) => s.trim()).filter((s) => s === HARAP.namaLama);
    pastikan(barisPersisLama.length === 0, `nama lama "${HARAP.namaLama}" masih tampil sebagai baris sendiri`);

    return `"${HARAP.namaLama}" -> "${HARAP.namaBaru}"`;
}, { halaman: operatorB.halaman });

await jalankan('K-24', 'Akun dan perannya yang lahir sesudah pemasangan bisa dipakai masuk di node gelanggang', async () => {
    const sesi = await masuk(browser, HARAP.akunBaru, { asal: B });

    const balasan = await sesi.halaman.goto(`${B}/admin/turnamen/${HARAP.turnamen}/gelanggang/${HARAP.arena}/panel/juri`, { waitUntil: 'domcontentloaded' });

    // Peran juri membuka panel juri. Tanpa perannya, akun ini akan
    // mendapat 403 -- bentuk cacat pivot peran yang tidak ikut berpindah.
    pastikan(balasan.status() !== 403, 'akun baru masuk tapi panel juri 403 -- perannya tidak ikut tersinkron');

    await sesi.konteks.close();

    return `${HARAP.akunBaru} masuk dan membuka panel juri`;
}, { halaman: ketuaB.halaman });

/*
 * Penghapusan diuji terhadap baris yang SUDAH dimiliki node gelanggang --
 * kontingen yang tiba pada penarikan K-22. Menghapus baris yang tidak pernah
 * sampai lulus dengan sendirinya dan tidak membuktikan apa pun.
 */
await jalankan('K-25', 'Kontingen yang dihapus di node global hilang dari node gelanggang', async () => {
    const edit = `${B}/admin/turnamen/${HARAP.turnamen}/kontingen/${HARAP.kontingenDihapus}/edit`;

    const sebelum = await operatorB.halaman.goto(edit, { waitUntil: 'domcontentloaded' });
    pastikan(sebelum.status() === 200, `kontingen uji belum tiba di node gelanggang (${sebelum.status()})`);

    /*
     * Backslash digandakan: di dalam template literal JS, `\A` luruh jadi `A`
     * tanpa galat, dan perintahnya diam-diam berubah jadi
     * `AppModelsContingent` -- penghapusan yang tidak pernah terjadi, lalu uji
     * yang gagal karena alasan yang salah.
     */
    const keluaran = execSync(
        `php artisan tinker --execute="\\App\\Models\\Contingent::whereKey(${HARAP.kontingenDihapus})->first()?->delete(); echo 'terhapus';"`,
        { cwd: process.cwd(), stdio: 'pipe' },
    ).toString();

    pastikan(keluaran.includes('terhapus'), `penghapusan di node global tidak terjadi: ${keluaran.slice(-200)}`);

    const j = await tarik();

    const sesudah = await operatorB.halaman.goto(edit, { waitUntil: 'domcontentloaded' });
    pastikan(sesudah.status() === 404, `kontingen yang dihapus masih terbuka (${sesudah.status()})`);

    /*
     * Kontingen memakai soft delete: menghapusnya di node global mengisi
     * `deleted_at`, jadi yang berjalan ke node gelanggang adalah PEMBARUAN
     * berisi `deleted_at`, bukan penghapusan. Ringkasan penarikan karena itu
     * jujur menulis "diterapkan", bukan "dihapus" -- dan pernah dibaca keliru
     * sebagai penghapusan yang tidak sampai.
     */
    return `hadir lalu hilang (soft delete, ${j.diterapkan} pembaruan diterapkan)`;
}, { halaman: operatorB.halaman });

await browser.close();
process.exit(laporkan('K4. Perubahan susulan') === 0 ? 0 : 1);
