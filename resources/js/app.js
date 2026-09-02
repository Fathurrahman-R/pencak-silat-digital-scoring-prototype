import Alpine from 'alpinejs';

import './theme';

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

    tutupSidebar() {
        this.sidebarOpen = false;
    },

    toggleCollapsed() {
        this.collapsed = ! this.collapsed;
        document.documentElement.dataset.sidebar = this.collapsed ? 'collapsed' : 'expanded';
        localStorage.setItem('sidebar-collapsed', this.collapsed ? '1' : '0');
    },
});

/*
 * Halaman di belakang laci TIDAK ikut tergulir selama laci terbuka.
 *
 * Tanpa ini, jari yang menggeser di atas laci atau di latar gelapnya menggulir
 * halaman di baliknya: laci ditutup, dan pembacanya mendarat di tempat yang
 * bukan tempat ia tadi berada. Penandanya dipasang di <html> lewat kelas, dan
 * yang benar-benar mengunci adalah CSS di app.css -- dibatasi media query
 * lebar laci, supaya keadaan yang tertinggal dari layar sempit tidak ikut
 * mengunci layar lebar tempat sidebar bukan laci sama sekali.
 */
Alpine.effect(() => {
    document.documentElement.classList.toggle('sidebar-terbuka', Alpine.store('shell').sidebarOpen);
});

/**
 * Seleksi baris tabel.
 *
 * Dipasang komponen <x-si.tabel> begitu prop `selectable` diisi. Id halaman
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

window.Alpine = Alpine;
Alpine.start();

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
