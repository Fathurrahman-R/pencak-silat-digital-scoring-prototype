<?php

use App\Enums\ResourceAction;

/**
 * Menu sidebar panel admin.
 *
 * Kunci yang dikenali per item:
 *   label        teks yang ditampilkan
 *   icon         nama ikon di komponen <x-ui.icon>
 *   route        nama route tujuan (dilewati kalau route belum terdaftar)
 *   url          alternatif route untuk tautan luar
 *   resource     satu resource key atau array key; item disembunyikan kalau
 *                pengguna tidak punya salah satunya
 *   active       pola path untuk menandai menu aktif (mis. 'admin/users*')
 *   children     submenu, aturannya sama
 *
 * Menambah modul baru cukup menambah satu entri di sini — penyaringan hak
 * akses berjalan sendiri lewat NavigationBuilder.
 */
return [
    [
        'label' => 'Dashboard',
        'icon' => 'house',
        'route' => 'dashboard',
    ],

    [
        'label' => 'Penyelenggaraan',
        'icon' => 'trophy',
        'children' => [
            [
                'label' => 'Kejuaraan',
                'route' => 'admin.turnamen.index',
                'resource' => rk('turnamen', ResourceAction::View),
                'active' => 'admin/turnamen*',
            ],
        ],
    ],

    [
        'label' => 'Konten',
        'icon' => 'file-text',
        'children' => [
            [
                'label' => 'Artikel',
                'route' => 'admin.posts.index',
                'resource' => rk('posts', ResourceAction::View),
                'active' => 'admin/posts*',
            ],
        ],
    ],

    [
        'label' => 'Manajemen Akses',
        'icon' => 'shield-check',
        'children' => [
            [
                'label' => 'Pengguna',
                'route' => 'admin.users.index',
                'resource' => rk('users', ResourceAction::View),
                'active' => 'admin/users*',
            ],
            [
                'label' => 'Role',
                'route' => 'admin.roles.index',
                'resource' => rk('roles', ResourceAction::View),
                'active' => 'admin/roles*',
            ],
            [
                'label' => 'Permission',
                'route' => 'admin.permissions.index',
                'resource' => rk('permissions', ResourceAction::View),
                'active' => 'admin/permissions*',
            ],
            [
                'label' => 'Resource',
                'route' => 'admin.resources.index',
                'resource' => rk('resources', ResourceAction::View),
                'active' => 'admin/resources*',
            ],
            [
                'label' => 'Pemetaan Key',
                'route' => 'admin.mappings.index',
                'resource' => rk('mappings', ResourceAction::View),
                'active' => 'admin/mappings*',
            ],
        ],
    ],
];
