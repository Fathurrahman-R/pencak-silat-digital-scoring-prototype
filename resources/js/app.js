import Alpine from 'alpinejs';

import './theme';

/**
 * Warna deret data dibaca dari token CSS saat render (bukan ditulis literal),
 * jadi grafik tetap benar begitu tema atau warna aksen berganti — sama dengan
 * bg-{tone} yang dipakai <x-ui.progress>/<x-ui.legend>.
 *
 * Dibaca dari variabel MENTAH (--c1, --text-muted, …), bukan alias
 * --color-chart-1 dkk. yang dipakai kelas Tailwind: @theme inline hanya
 * memakai alias itu untuk membangun utility class, tidak pernah menerbitkannya
 * sebagai custom property sungguhan di :root — getComputedStyle atasnya
 * selalu balik string kosong.
 */
const CHART_VARS = {
    'chart-1': '--c1',
    'chart-2': '--c2',
    'chart-3': '--c3',
    'chart-4': '--c4',
    'chart-5': '--c5',
    'chart-6': '--c6',
    accent: '--accent',
    'ink-muted': '--text-muted',
};

function chartColor(token) {
    return getComputedStyle(document.documentElement).getPropertyValue(CHART_VARS[token] ?? `--${token}`).trim();
}

/**
 * Keadaan shell aplikasi dipakai bersama oleh sidebar dan topbar, jadi
 * disimpan sekali di store — bukan dua x-data terpisah yang harus dijaga
 * tetap sinkron lewat event.
 *
 * Lebar sidebar dan penyembunyian label dikendalikan CSS lewat atribut
 * `data-sidebar` di <html>, yang sudah dipasang skrip inline sebelum halaman
 * digambar (lihat layouts/partials/theme-script.blade.php). Store hanya
 * membalik nilainya, jadi tidak ada lompatan tata letak saat Alpine termuat.
 *
 * `sidebarOpen` hanya berlaku di layar sempit, tempat sidebar berperilaku
 * sebagai drawer, dan sengaja tidak disimpan antar-halaman.
 */
Alpine.store('shell', {
    sidebarOpen: false,
    collapsed: document.documentElement.dataset.sidebar === 'collapsed',

    toggleSidebar() {
        this.sidebarOpen = ! this.sidebarOpen;
    },

    toggleCollapsed() {
        this.collapsed = ! this.collapsed;
        document.documentElement.dataset.sidebar = this.collapsed ? 'collapsed' : 'expanded';
        localStorage.setItem('sidebar-collapsed', this.collapsed ? '1' : '0');
    },
});

/**
 * Seleksi baris tabel.
 *
 * Dipasang komponen <x-ui.table> begitu prop `selectable` diisi. Id halaman
 * berjalan disimpan supaya "pilih semua" berarti semua yang terlihat — bukan
 * semua yang ada di database, yang tidak pernah bisa dijanjikan satu halaman.
 */
Alpine.data('tableSelection', (ids = []) => ({
    ids,
    selected: [],

    has(id) {
        return this.selected.includes(id);
    },

    toggle(id) {
        this.selected = this.has(id)
            ? this.selected.filter((value) => value !== id)
            : [...this.selected, id];
    },

    get allChecked() {
        return this.ids.length > 0 && this.selected.length === this.ids.length;
    },

    toggleAll() {
        this.selected = this.allChecked ? [] : [...this.ids];
    },

    clear() {
        this.selected = [];
    },
}));

/**
 * Dibalik <x-ui.bar-chart>. ApexCharts sendiri yang menggambar SVG-nya;
 * komponen Blade hanya menyiapkan bentuk data dan memanggil mount($el).
 *
 * ApexCharts (~250 KB gzip) diimpor dinamis, bukan di bagian atas berkas ini
 * — halaman yang tidak pernah menampilkan grafik (login, 2FA, dst.) tidak
 * ikut menanggung beratnya di bundle utama.
 *
 * Warna dan tema tooltip dibaca ulang tiap `theme:changed` (lihat theme.js)
 * supaya grafik ikut berganti tanpa reload — tanpa listener ini grafiknya
 * membeku di warna tema saat pertama kali dirender.
 */
Alpine.data('apexBarChart', ({ categories, data, tones, stacked, max }) => ({
    chart: null,

    async mount(el) {
        const { default: ApexCharts } = await import('apexcharts');

        this.chart = new ApexCharts(el, this.options());
        this.chart.render();

        document.addEventListener('theme:changed', this.refresh = () => {
            this.chart.updateOptions(this.options());
        });
    },

    colors() {
        return tones.map((tone) => chartColor(tone));
    },

    options() {
        const dark = document.documentElement.dataset.theme === 'dark';

        return {
            chart: {
                type: 'bar',
                height: '100%',
                stacked,
                toolbar: { show: false },
                fontFamily: 'inherit',
                foreColor: chartColor('ink-muted'),
            },
            series: data,
            colors: this.colors(),
            xaxis: {
                categories,
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                max: max ?? undefined,
                labels: { show: false },
            },
            grid: { show: false },
            legend: { show: stacked && data.length > 1, fontFamily: 'inherit' },
            dataLabels: { enabled: false },
            plotOptions: {
                bar: { borderRadius: 5, borderRadiusApplication: 'end', columnWidth: '55%' },
            },
            tooltip: { theme: dark ? 'dark' : 'light' },
        };
    },
}));

window.Alpine = Alpine;
Alpine.start();

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
