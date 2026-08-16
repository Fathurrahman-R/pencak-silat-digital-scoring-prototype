import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Font tidak diambil dari CDN mana pun. Sora, Space Grotesk, dan IBM Plex Mono
// diimpor sebagai paket npm di resources/js/app.js dan ikut masuk bundle.
//
// Dua bundel yang berdiri sendiri:
//   app.*   — panel admin, memakai design system RizzxxUI
//   silat.* — panel gelanggang, live score publik, dan overlay siaran vMix
//
// Pemisahannya bukan sekadar rapi-rapi. Halaman admin tidak pernah memuat token
// --silat-*, jadi RizzxxUI tidak mungkin ikut berubah saat papan skor
// dikerjakan. Sebaliknya overlay vMix tidak menyeret ApexCharts dan komponen
// admin yang tidak dipakainya, padahal ia berbagi CPU dengan encoder streaming.
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/silat.css',
                'resources/js/silat.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
