<?php

use App\Enums\ResourceAction;

/**
 * Menu sidebar panel admin.
 *
 * Kunci yang dikenali per item:
 *   label            teks yang ditampilkan
 *   label_turnamen   pakai nama kejuaraan aktif sebagai label
 *   caption          baris kecil di bawah label; menjelaskan label yang isinya
 *                    berganti-ganti (mis. nama kejuaraan)
 *   icon             nama ikon di komponen <x-ui.icon>
 *   route            nama route tujuan (dilewati kalau route belum terdaftar)
 *   url              alternatif route untuk tautan luar
 *   butuh_turnamen   item disembunyikan bila belum ada kejuaraan yang dibuka;
 *                    kejuaraan aktif disisipkan sendiri sebagai parameter route
 *   resource         satu resource key atau array key; item disembunyikan kalau
 *                    pengguna tidak punya salah satunya
 *   active           pola path untuk menandai menu aktif (mis. 'admin/users*')
 *   children         submenu, aturannya sama
 *
 * Hampir seluruh pekerjaan panitia berlangsung di dalam satu kejuaraan. Karena
 * itu bagian-bagiannya berdiri sebagai submenu tersendiri, bukan sebagai
 * deretan tombol di halaman kejuaraan — berpindah dari timbang badan ke
 * verifikasi tidak perlu melewati dua halaman perantara.
 */
return [
    [
        'label' => 'Dashboard',
        'icon' => 'house',
        'route' => 'dashboard',
    ],

    [
        'label' => 'Kejuaraan',
        'icon' => 'trophy',
        'route' => 'admin.turnamen.index',
        'resource' => rk('turnamen', ResourceAction::View),

        // Hanya daftar dan formulir kejuaraan. Halaman di dalam satu kejuaraan
        // punya grupnya sendiri di bawah.
        'active' => 'admin/turnamen',
    ],

    [
        'label' => 'Kejuaraan aktif',
        'label_turnamen' => true,
        'caption' => 'Kejuaraan aktif',
        'icon' => 'flag',
        'butuh_turnamen' => true,
        'children' => [
            [
                'label' => 'Ringkasan',
                'route' => 'admin.turnamen.edit',
                'butuh_turnamen' => true,
                'resource' => rk('turnamen', ResourceAction::View),
                'active' => 'admin/turnamen/*/edit',
            ],
            [
                'label' => 'Setelan peraturan',
                'route' => 'admin.turnamen.peraturan.edit',
                'butuh_turnamen' => true,
                'resource' => rk('peraturan-turnamen', ResourceAction::View),
                'active' => 'admin/turnamen/*/peraturan*',
            ],
            [
                'label' => 'Gelanggang',
                'route' => 'admin.turnamen.gelanggang.index',
                'butuh_turnamen' => true,
                'resource' => rk('gelanggang', ResourceAction::View),
                'active' => 'admin/turnamen/*/gelanggang*',
            ],
            [
                'label' => 'Tarif',
                'route' => 'admin.turnamen.tarif.index',
                'butuh_turnamen' => true,
                'resource' => rk('tarif', ResourceAction::View),
                'active' => 'admin/turnamen/*/tarif*',
            ],
        ],
    ],

    [
        'label' => 'Peserta',
        'icon' => 'users',
        'butuh_turnamen' => true,
        'children' => [
            [
                'label' => 'Kontingen',
                'route' => 'admin.turnamen.kontingen.index',
                'butuh_turnamen' => true,
                'resource' => rk('kontingen', ResourceAction::View),
                'active' => 'admin/turnamen/*/kontingen*',
            ],
            [
                'label' => 'Verifikasi',
                'route' => 'admin.turnamen.verifikasi.index',
                'butuh_turnamen' => true,
                'resource' => rk('pendaftaran', ResourceAction::View),
                'active' => 'admin/turnamen/*/verifikasi*',
            ],
            [
                'label' => 'Timbang badan',
                'route' => 'admin.turnamen.timbang.index',
                'butuh_turnamen' => true,
                'resource' => rk('timbang-badan', ResourceAction::View),
                'active' => 'admin/turnamen/*/timbang*',
            ],
        ],
    ],

    [
        'label' => 'Keuangan',
        'icon' => 'wallet',
        'butuh_turnamen' => true,
        'children' => [
            [
                'label' => 'Bendahara',
                'route' => 'admin.turnamen.bendahara.index',
                'butuh_turnamen' => true,
                'resource' => rk('invoice', ResourceAction::View),
                'active' => 'admin/turnamen/*/bendahara*',
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
