// Proksi https lokal yang meniru Caddyfile di docs/TUNNELING.md.
//
// Tujuannya satu: mereproduksi jalur yang rusak TANPA tunnel sungguhan --
// halaman disajikan lewat https, dan /app/* (endpoint WebSocket Reverb) terbit
// di ORIGIN YANG SAMA, bukan di port 8080.
//
// Itulah bentuk yang membuat aset lama gagal: ia membawa ws://host:8080, dan
// peramban memblokirnya dari halaman https sebagai konten campuran.

import { createServer } from 'node:https';
import { request as httpRequest } from 'node:http';
import { connect } from 'node:net';
import { readFileSync } from 'node:fs';

const PORT = 8443;
/*
 * 127.0.0.2, BUKAN 127.0.0.1.
 *
 * Di mesin ini ada dua server di port 8000: `php -S 127.0.0.1:8000` milik
 * proyek lain, dan nginx yang mengikat 0.0.0.0:8000 untuk proyek ini. Ikatan
 * ke alamat spesifik menang atas 0.0.0.0, jadi 127.0.0.1:8000 mendarat di
 * proyek yang salah. Alamat loopback lain melewatinya dan sampai ke nginx --
 * dan tetap loopback, jadi Laravel mempercayainya sebagai proxy.
 */
const LARAVEL = { host: '127.0.0.2', port: 8000 };
const REVERB = { host: '127.0.0.1', port: 8080 };

const server = createServer({
    key: readFileSync(new URL('./key.pem', import.meta.url)),
    cert: readFileSync(new URL('./cert.pem', import.meta.url)),
});

server.on('request', (req, res) => {
    const hulu = httpRequest(
        {
            ...LARAVEL,
            method: req.method,
            path: req.url,
            /*
             * Host DIPERTAHANKAN, dan X-Forwarded-* ditambahkan -- persis yang
             * dilakukan Caddy. Menimpa Host dengan 127.0.0.1:8000 membuat
             * Laravel membangun seluruh alamat asetnya ke alamat itu, dan
             * peramban menolaknya sebagai lintas-origin.
             */
            headers: {
                ...req.headers,
                'x-forwarded-proto': 'https',
                'x-forwarded-host': req.headers.host,
                'x-forwarded-port': String(PORT),
                'x-forwarded-for': '127.0.0.1',
            },
        },
        (balasan) => {
            res.writeHead(balasan.statusCode ?? 502, balasan.headers);
            balasan.pipe(res);
        },
    );

    hulu.on('error', (e) => {
        res.writeHead(502);
        res.end(`proksi gagal: ${e.message}`);
    });

    req.pipe(hulu);
});

/*
 * Upgrade WebSocket diteruskan mentah ke Reverb. Inilah yang dilakukan Caddy
 * pada `handle /app/*` di docs/TUNNELING.md, dan inilah sebabnya halaman https
 * TIDAK boleh menyambung ke port 8080: dari luar, port itu memang tidak dibuka.
 */
server.on('upgrade', (req, socket, head) => {
    const tujuan = req.url?.startsWith('/app/') ? REVERB : LARAVEL;

    const hulu = connect(tujuan.port, tujuan.host, () => {
        const kepala = Object.entries(req.headers)
            .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`)
            .join('\r\n');

        hulu.write(`${req.method} ${req.url} HTTP/1.1\r\n${kepala}\r\n\r\n`);

        if (head?.length) {
            hulu.write(head);
        }

        hulu.pipe(socket);
        socket.pipe(hulu);
    });

    hulu.on('error', () => socket.destroy());
    socket.on('error', () => hulu.destroy());
});

server.listen(PORT, '127.0.0.1', () => {
    console.log(`proksi https siap di https://localhost:${PORT} (app -> ${REVERB.port}, sisanya -> ${LARAVEL.port})`);
});
