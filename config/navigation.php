<?php

use App\Enums\ResourceAction;

/**
 * Menu sidebar panel panitia.
 *
 * Susunannya DATAR: tidak ada grup yang bisa dilipat. Nama grup dipakai
 * sebagai judul seksi, dan itemnya berdiri langsung di bawahnya.
 *
 * Grup lipat dibuang karena harganya tidak sepadan. Sebagian besar role hanya
 * memegang satu atau dua izin — Bendahara satu, Petugas Timbang Badan satu,
 * Official Kontingen satu — jadi yang mereka lihat adalah grup terlipat berisi
 * satu item, dan satu klik hanya untuk membukanya. Sisanya, yang izinnya luas,
 * membuka semua grup di kunjungan pertama lalu tidak pernah menutupnya lagi.
 *
 * Bentuk entri di berkas ini ada dua:
 *
 *   1. Item tunggal di pucuk, tanpa seksi   ['label' => …, 'route' => …]
 *   2. Seksi berisi item                    ['seksi' => …, 'items' => [ … ]]
 *
 * Kunci yang dikenali per ITEM:
 *   label            teks yang ditampilkan
 *   icon             nama ikon Lucide. WAJIB -- di lebar rail hanya ikon yang
 *                    tersisa, dan item tanpa ikon jadi baris kosong
 *   route            nama route tujuan (dilewati kalau route belum terdaftar)
 *   url              alternatif route untuk tautan luar
 *   butuh_turnamen   item disembunyikan bila belum ada kejuaraan yang dibuka;
 *                    kejuaraan aktif disisipkan sendiri sebagai parameter route
 *   resource         satu resource key atau array key; item disembunyikan kalau
 *                    pengguna tidak punya salah satunya
 *   active           pola path untuk menandai menu yang sedang dibuka
 *   badge            angka kecil di ujung kanan
 *
 * Seksi yang seluruh itemnya tersembunyi ikut hilang — judul seksi tanpa isi
 * hanya membuat orang mengira ada yang gagal dimuat.
 *
 * URUTAN SEKSI mengikuti kapan pekerjaannya dilakukan: kejuaraan disiapkan,
 * peserta didaftarkan dan diverifikasi, tagihannya ditagih, pertandingannya
 * disusun, siarannya dipasang, hasilnya direkap. Nama seksinya sendiri tetap
 * kata benda yang lazim di panel admin — bukan nama tahapan, yang akan terbaca
 * asing bagi orang yang terbiasa dengan panel lain.
 *
 * Dua item pindah kamar dari susunan sebelumnya, dan keduanya memang salah
 * tempat: "Overlay Siaran" dan "Rekap & Laporan" duduk di bawah "Pertandingan"
 * — siaran bukan partai, dan rekap datang sesudah seluruh pertandingan
 * selesai. "Tarif" ikut pindah ke Keuangan supaya ia sekamar dengan tagihan
 * yang dihitung darinya.
 */
return [
    /*
     * Beranda berdiri sendiri di pucuk, tanpa judul seksi. Ia bukan bagian
     * dari kategori mana pun, dan judul untuk satu item hanya menambah baris
     * yang harus dilewati mata setiap kali.
     */
    [
        'label' => 'Beranda',
        'icon' => 'house',
        'route' => 'dashboard',
    ],

    [
        'seksi' => 'Kejuaraan',
        'items' => [
            [
                'label' => 'Daftar kejuaraan',
                'icon' => 'trophy',
                'route' => 'admin.turnamen.index',
                'resource' => rk('turnamen', ResourceAction::View),

                // Pola tanpa bintang: halaman DI DALAM satu kejuaraan punya
                // itemnya sendiri, jadi item ini tidak ikut menyala di sana.
                'active' => 'admin/turnamen',
            ],
            [
                'label' => 'Setelan peraturan',
                'icon' => 'scroll-text',
                'route' => 'admin.turnamen.peraturan.edit',
                'butuh_turnamen' => true,
                'resource' => rk('peraturan-turnamen', ResourceAction::View),
                'active' => 'admin/turnamen/*/peraturan*',
            ],
            [
                'label' => 'Gelanggang',
                'icon' => 'layout-grid',
                'route' => 'admin.turnamen.gelanggang.index',
                'butuh_turnamen' => true,
                'resource' => rk('gelanggang', ResourceAction::View),
                'active' => 'admin/turnamen/*/gelanggang*',
            ],
        ],
    ],

    [
        'seksi' => 'Peserta',
        'items' => [
            [
                'label' => 'Kontingen',
                'icon' => 'users',
                'route' => 'admin.turnamen.kontingen.index',
                'butuh_turnamen' => true,
                'resource' => rk('kontingen', ResourceAction::View),
                'active' => 'admin/turnamen/*/kontingen*',
            ],
            [
                'label' => 'Verifikasi',
                'icon' => 'user-check',
                'route' => 'admin.turnamen.verifikasi.index',
                'butuh_turnamen' => true,
                'resource' => rk('pendaftaran', ResourceAction::View),
                'active' => 'admin/turnamen/*/verifikasi*',
            ],
            [
                'label' => 'Timbang badan',
                'icon' => 'scale',
                'route' => 'admin.turnamen.timbang.index',
                'butuh_turnamen' => true,
                'resource' => rk('timbang-badan', ResourceAction::View),
                'active' => 'admin/turnamen/*/timbang*',
            ],
        ],
    ],

    [
        'seksi' => 'Keuangan',
        'items' => [
            [
                'label' => 'Tarif',
                'icon' => 'receipt',
                'route' => 'admin.turnamen.tarif.index',
                'butuh_turnamen' => true,
                'resource' => rk('tarif', ResourceAction::View),
                'active' => 'admin/turnamen/*/tarif*',
            ],
            [
                'label' => 'Bendahara',
                'icon' => 'wallet',
                'route' => 'admin.turnamen.bendahara.index',
                'butuh_turnamen' => true,
                'resource' => rk('invoice', ResourceAction::View),
                'active' => 'admin/turnamen/*/bendahara*',
            ],
        ],
    ],

    [
        'seksi' => 'Pertandingan',
        'items' => [
            [
                'label' => 'Bagan',
                'icon' => 'network',
                'route' => 'admin.turnamen.bagan.index',
                'butuh_turnamen' => true,
                'resource' => rk('bagan', ResourceAction::View),
                'active' => 'admin/turnamen/*/bagan*',
            ],
            [
                'label' => 'Jadwal',
                'icon' => 'calendar-days',
                'route' => 'admin.turnamen.jadwal.index',
                'butuh_turnamen' => true,
                'resource' => rk('jadwal', ResourceAction::View),
                'active' => 'admin/turnamen/*/jadwal*',
            ],
            [
                'label' => 'Kategori Jurus',
                'icon' => 'list-ordered',
                'route' => 'admin.turnamen.jurus.nomor',
                'butuh_turnamen' => true,
                'resource' => rk('penampilan-jurus', ResourceAction::View),
                'active' => 'admin/turnamen/*/jurus*',
            ],
        ],
    ],

    [
        'seksi' => 'Publikasi',
        'items' => [
            [
                'label' => 'Overlay Siaran',
                'icon' => 'tv-minimal',
                'route' => 'admin.turnamen.siaran.index',
                'butuh_turnamen' => true,
                'resource' => rk('overlay', ResourceAction::View),
                'active' => 'admin/turnamen/*/siaran*',
            ],
        ],
    ],

    [
        'seksi' => 'Laporan',
        'items' => [
            [
                'label' => 'Rekap & Laporan',
                'icon' => 'file-text',
                'route' => 'admin.turnamen.rekap.index',
                'butuh_turnamen' => true,
                'resource' => rk('rekap', ResourceAction::View),
                'active' => 'admin/turnamen/*/rekap*',
            ],
        ],
    ],

    [
        'seksi' => 'Sistem',
        'items' => [
            [
                'label' => 'Pengguna',
                'icon' => 'user',
                'route' => 'admin.users.index',
                'resource' => rk('users', ResourceAction::View),
                'active' => 'admin/users*',
            ],
            [
                'label' => 'Role',
                'icon' => 'shield-check',
                'route' => 'admin.roles.index',
                'resource' => rk('roles', ResourceAction::View),
                'active' => 'admin/roles*',
            ],
            [
                'label' => 'Permission',
                'icon' => 'key',
                'route' => 'admin.permissions.index',
                'resource' => rk('permissions', ResourceAction::View),
                'active' => 'admin/permissions*',
            ],
            [
                'label' => 'Resource',
                'icon' => 'box',
                'route' => 'admin.resources.index',
                'resource' => rk('resources', ResourceAction::View),
                'active' => 'admin/resources*',
            ],
            [
                'label' => 'Pemetaan Key',
                'icon' => 'link',
                'route' => 'admin.mappings.index',
                'resource' => rk('mappings', ResourceAction::View),
                'active' => 'admin/mappings*',
            ],
        ],
    ],
];
