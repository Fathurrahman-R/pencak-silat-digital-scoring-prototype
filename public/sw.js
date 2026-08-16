/**
 * Service worker panel juri.
 *
 * Cakupannya sengaja sempit: hanya menyimpan cache untuk berkas statis
 * (bundel Vite, ikon) supaya panel tetap tampil instan sewaktu wifi
 * gelanggang sedang lemah. Tidak menyentuh permintaan lain sama sekali --
 * terutama POST ke /nilai. Skor hanya sah kalau benar-benar sampai ke
 * server; menyimpannya di sini dan mengirim belakangan berarti server_ts-nya
 * salah dan bisa jatuh di luar window konsensus.
 */

const CACHE_NAME = 'silat-juri-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) => Promise.all(
            names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name)),
        )),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Hanya GET ke berkas statis yang dicache. Semua permintaan lain
    // (halaman, POST aksi juri, resync state) selalu langsung ke jaringan.
    const statis = request.method === 'GET'
        && (request.url.includes('/build/') || request.url.includes('/icons/'));

    if (!statis) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const tersimpan = await cache.match(request);

            const jaringan = fetch(request).then((respons) => {
                cache.put(request, respons.clone());

                return respons;
            }).catch(() => tersimpan);

            return tersimpan ?? jaringan;
        }),
    );
});
