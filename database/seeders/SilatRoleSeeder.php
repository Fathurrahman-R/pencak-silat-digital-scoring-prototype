<?php

namespace Database\Seeders;

use App\Enums\ResourceAction;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Peran mengikuti susunan aparat pertandingan pada Pasal 13 Peraturan
 * Pertandingan Pencak Silat Nasional Tahun 2025, bukan istilah karangan
 * sendiri. Ini penting supaya panitia mengenali namanya tanpa perlu
 * menerjemahkan, dan supaya pembagian wewenangnya bisa diadu langsung dengan
 * naskah.
 *
 * Aparat yang tidak menyentuh aplikasi — Announcer, Petugas Medis, Petugas
 * Lapangan — sengaja tidak dibuatkan peran. Bendahara bukan bagian Pasal 13;
 * ia fungsi penyelenggara yang dibutuhkan modul keuangan.
 */
class SilatRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $role = Role::firstOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                [
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                ],
            );

            $role->syncPermissions($this->permissionsFor($definition['grants']));
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        $lihat = [ResourceAction::View];
        $ubah = [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete];

        return [
            [
                'name' => 'delegasi-teknik',
                'label' => 'Delegasi Teknik',
                'description' => 'Memutus banding secara final dan berwenang menghentikan atau menunda pertandingan.',
                'grants' => [
                    'turnamen' => $lihat,
                    'jadwal' => $lihat,
                    'bagan' => $lihat,
                    'partai' => $lihat,
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve],
                    'var' => $lihat,
                    'protes-manajer' => [ResourceAction::View, ResourceAction::Approve, ResourceAction::Reject],
                    'rekap' => [ResourceAction::View, ResourceAction::Export, ResourceAction::Print],
                ],
            ],
            [
                'name' => 'ketua-pertandingan',
                'label' => 'Ketua Pertandingan',
                'description' => 'Mengatur kelancaran pertandingan, memimpin verifikasi juri, dan memutus protes tingkat pertama.',
                'grants' => [
                    'turnamen' => $lihat,
                    'gelanggang' => $lihat,
                    'jadwal' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Assign],
                    'bagan' => $lihat,
                    'penugasan-aparat' => [ResourceAction::View, ResourceAction::Assign],
                    'partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Manage],
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Print],
                    // Ketua Pertandingan menampung protes VAR maupun Protes
                    // Manajer atas nama pelatih (keduanya diajukan pelatih di
                    // gelanggang, bukan lewat akun sistem sendiri), lalu
                    // memutus tingkat pertama Protes Manajer.
                    'var' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                    'protes-manajer' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                    'penampilan-jurus' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Manage],
                    'hasil-jurus' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Print],
                    'rekap' => [ResourceAction::View, ResourceAction::Export, ResourceAction::Print],
                ],
            ],
            [
                'name' => 'pengawas-wasit-juri',
                'label' => 'Pengawas / Dewan Wasit Juri',
                'description' => 'Mengevaluasi penilaian juri, menyusun penugasan, dan mencatat pengurangan 0.50 pada kategori Jurus.',
                'grants' => [
                    'penugasan-aparat' => [ResourceAction::View, ResourceAction::Assign],
                    'partai' => $lihat,
                    'penilaian' => $lihat,
                    'hukuman' => [ResourceAction::View, ResourceAction::Create],
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Update],
                    'var' => [ResourceAction::View, ResourceAction::Approve, ResourceAction::Reject],
                    'penampilan-jurus' => $lihat,
                    'pengurangan-jurus' => [ResourceAction::View, ResourceAction::Create],
                    'hasil-jurus' => [ResourceAction::View, ResourceAction::Update],
                ],
            ],
            [
                'name' => 'wasit-komisi-protes',
                'label' => 'Wasit Komisi Protes',
                'description' => 'Menganalisis tayangan ulang dan menetapkan hasil protes VAR dalam tenggat 5 menit.',
                'grants' => [
                    'partai' => $lihat,
                    'penilaian' => $lihat,
                    'hukuman' => $lihat,
                    'var' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                ],
            ],
            [
                'name' => 'wasit',
                'label' => 'Wasit',
                'description' => 'Memimpin pertandingan, menjatuhkan pembinaan, teguran, dan peringatan, serta menghentikan pertandingan.',
                'grants' => [
                    'partai' => [ResourceAction::View, ResourceAction::Update],
                    'hukuman' => [ResourceAction::View, ResourceAction::Create],
                    'penilaian' => $lihat,
                ],
            ],
            [
                'name' => 'juri',
                'label' => 'Juri',
                'description' => 'Menilai serangan yang masuk. Hanya bisa menambah nilai, tidak pernah mengubah atau menghapusnya.',
                'grants' => [
                    'partai' => $lihat,
                    'penilaian' => [ResourceAction::View, ResourceAction::Create],
                    // Sama seperti Tanding: juri Jurus hanya melihat penampilan
                    // dan mengirim nilai, tidak pernah mengendalikan timer.
                    'penampilan-jurus' => $lihat,
                ],
            ],
            [
                'name' => 'operator-it',
                'label' => 'Operator IT',
                'description' => 'Menjalankan sistem digital score di gelanggang: memilih partai aktif dan mengendalikan timer.',
                'grants' => [
                    'jadwal' => $lihat,
                    'partai' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Manage],
                    'penilaian' => $lihat,
                    'hukuman' => $lihat,
                    'hasil-partai' => [ResourceAction::View, ResourceAction::Print],
                    // Operator paling dekat dengan meja pelatih -- bisa
                    // memasukkan protes VAR ke sistem, tapi tidak memutusnya.
                    'var' => [ResourceAction::View, ResourceAction::Create],
                    'penampilan-jurus' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Manage],
                    'hasil-jurus' => [ResourceAction::View, ResourceAction::Print],
                ],
            ],
            [
                'name' => 'sekretaris-pertandingan',
                'label' => 'Sekretaris Pertandingan',
                'description' => 'Menerima pendaftaran kontingen, memeriksa berkas peserta, dan mengesahkan keikutsertaannya. Petugas Teknis, Pasal 13.',
                'grants' => [
                    'turnamen' => $lihat,
                    'kontingen' => $ubah,
                    'atlet' => $ubah,

                    /*
                     * Approve dan Reject dipisahkan dari Update. Menyunting
                     * data pendaftaran dan mengesahkan keikutsertaan adalah dua
                     * kewenangan yang berbeda, dan yang kedua menentukan siapa
                     * berhak naik gelanggang.
                     */
                    'pendaftaran' => [
                        ResourceAction::View, ResourceAction::Update,
                        ResourceAction::Approve, ResourceAction::Reject,
                        ResourceAction::Export,
                    ],

                    'invoice' => $lihat,
                    'kelas-tanding' => $lihat,
                    'nomor-jurus' => $lihat,
                    'bagan' => $lihat,
                    'jadwal' => $lihat,
                    'rekap' => [ResourceAction::View, ResourceAction::Export, ResourceAction::Print],
                ],
            ],
            [
                'name' => 'petugas-timbang',
                'label' => 'Petugas Timbang Badan',
                'description' => 'Mencatat berat badan dan menentukan lolos atau gugurnya atlet terhadap kelas yang diikuti.',
                'grants' => [
                    'atlet' => $lihat,
                    'pendaftaran' => $lihat,
                    'timbang-badan' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Export],
                ],
            ],
            [
                'name' => 'bendahara',
                'label' => 'Bendahara Panitia',
                'description' => 'Mengatur tarif, memantau tagihan, dan menandai pembayaran manual. Di luar Pasal 13.',
                'grants' => [
                    'kontingen' => $lihat,
                    'tarif' => $ubah,
                    'invoice' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Export],
                    'pendaftaran' => $lihat,
                ],
            ],
            [
                'name' => 'official-kontingen',
                'label' => 'Official Kontingen',
                'description' => 'Mendaftarkan atlet kontingennya sendiri, mengunggah berkas, dan membayar tagihan.',
                'grants' => [
                    /*
                     * Hanya melihat kontingen, tidak mengubahnya. Nama dan
                     * kontak kontingen ditetapkan panitia saat pendaftaran
                     * kontingen diterima, dan ketiadaan hak ubah di sini pula
                     * yang membatasi official hanya pada kontingennya sendiri —
                     * lihat App\Http\Controllers\Concerns\ScopesContingents.
                     */
                    'kontingen' => $lihat,
                    'atlet' => $ubah,
                    'pendaftaran' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete],
                    'invoice' => $lihat,
                    'jadwal' => $lihat,
                    'bagan' => $lihat,
                    'rekap' => $lihat,
                ],
            ],
        ];
    }

    /**
     * Mengambil permission lewat resource key-nya, bukan lewat nama permission.
     * Kalau pemetaannya diubah lewat panel nanti, seeder ini tetap menunjuk hal
     * yang benar.
     *
     * @param  array<string, array<int, ResourceAction>>  $map
     * @return array<int, Permission>
     */
    private function permissionsFor(array $map): array
    {
        $permissions = [];

        foreach ($map as $resourceKey => $actions) {
            $resource = Resource::where('key', $resourceKey)->with('mappings.permission')->first();

            if ($resource === null) {
                continue;
            }

            foreach ($actions as $action) {
                $mapping = $resource->mappings->firstWhere('action', $action);

                if ($mapping?->permission) {
                    $permissions[] = $mapping->permission;
                }
            }
        }

        return $permissions;
    }
}
