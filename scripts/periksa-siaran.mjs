// Pemeriksa alamat WebSocket yang TERTANAM di dalam aset terbangun.
//
// Jalankan: node scripts/periksa-siaran.mjs
// Keluar dengan kode 1 kalau skema WebSocket dibekukan saat build.
//
// Kenapa ini ada:
//
// `VITE_REVERB_*` dibaca Vite saat `npm run build` dan ikut tertanam di dalam
// berkas aset. Selama `VITE_REVERB_SCHEME` terisi, cabang
// `window.location.protocol` di resources/js/echo.js tidak pernah dijalankan --
// aset yang dibangun di LAN (`http`) memaksa `ws://` ke mana pun ia disajikan,
// termasuk ke halaman `https` yang lewat tunnel.
//
// Peramban memblokir `ws://` dari halaman `https://` sebagai konten campuran,
// dan Safari iOS memblokirnya TANPA pesan yang terlihat: panel diam, lalu
// menyalakan penanda "Terputus" setelah percobaan sambungnya kedaluwarsa.
// Terbaca persis seperti Reverb yang mati, padahal Reverb tidak pernah
// dihubungi. Satu sore terbuang mengejar server yang sebenarnya sehat.
//
// Yang membuatnya pantas dijaga skrip, bukan sekadar dikomentari: kegagalannya
// terjadi di perangkat yang tidak ada di meja pengembang, pada jalur yang tidak
// dipakai sehari-hari, dan tidak meninggalkan satu baris pun di log server.
// Yang mengisinya kembali suatu saat -- demi "kerapian", supaya seragam dengan
// VITE_REVERB_PORT -- tidak akan tahu apa yang baru saja ia matikan.

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const ENV = '.env';
const ENV_CONTOH = '.env.example';
const AKAR_ASET = 'public/build/assets';

const galat = [];

/** Nilai satu kunci di berkas .env, atau null kalau barisnya tidak ada. */
function nilaiEnv(berkas, kunci) {
    if (! existsSync(berkas)) {
        return null;
    }

    const baris = readFileSync(berkas, 'utf8')
        .split(/\r?\n/)
        .find((b) => b.trimStart().startsWith(`${kunci}=`));

    if (baris === undefined) {
        return null;
    }

    return baris.slice(baris.indexOf('=') + 1).trim().replace(/^["']|["']$/g, '');
}

for (const berkas of [ENV, ENV_CONTOH]) {
    const skema = nilaiEnv(berkas, 'VITE_REVERB_SCHEME');

    if (skema !== null && skema !== '') {
        galat.push(
            `${berkas}: VITE_REVERB_SCHEME="${skema}" — harus KOSONG.\n`
            + '  Diisi, skema WebSocket ikut tertanam di aset saat build, dan halaman\n'
            + '  https lewat tunnel akan tetap membuka ws:// — diblokir peramban sebagai\n'
            + '  konten campuran, Safari iOS tanpa satu pun pesan yang terlihat.\n'
            + '  Skemanya diturunkan dari location.protocol di resources/js/echo.js.',
        );
    }
}

/*
 * Bundel yang sudah terbangun ikut diperiksa: .env yang benar tidak menolong
 * kalau yang disajikan aset lama yang dibangun sebelum ia dibetulkan.
 *
 * Yang dicari `forceTLS` yang terikat ke LITERAL boolean (`!0`/`!1` sesudah
 * diminifikasi). Terikat ke variabel berarti ia dihitung saat halaman dibuka,
 * dan itulah yang benar.
 */
if (existsSync(AKAR_ASET)) {
    const bundel = readdirSync(AKAR_ASET).filter((n) => n.startsWith('silat-') && n.endsWith('.js'));

    for (const nama of bundel) {
        const isi = readFileSync(join(AKAR_ASET, nama), 'utf8');
        const reverb = isi.indexOf('broadcaster:');

        if (reverb === -1) {
            continue;
        }

        const potongan = isi.slice(reverb, reverb + 400);

        if (/forceTLS:\s*!\d/.test(potongan)) {
            galat.push(
                `${join(AKAR_ASET, nama)}: forceTLS terikat nilai tetap, bukan dihitung dari halaman.\n`
                + '  Bangun ulang asetnya sesudah mengosongkan VITE_REVERB_SCHEME: npm run build',
            );
        }
    }
}

if (galat.length > 0) {
    console.error('\nSkema WebSocket dibekukan saat build:\n');
    galat.forEach((g) => console.error(`- ${g}\n`));
    process.exit(1);
}

console.log('Siaran: skema WebSocket mengikuti halaman, tidak dibekukan saat build.');
