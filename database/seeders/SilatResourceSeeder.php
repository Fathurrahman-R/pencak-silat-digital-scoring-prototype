<?php

namespace Database\Seeders;

use App\Enums\ResourceAction;
use App\Models\Resource;
use App\Support\Resources\ResourceManager;
use Illuminate\Database\Seeder;

/**
 * Resource domain pertandingan pencak silat.
 *
 * Semuanya memakai lapisan resource key bawaan boilerplate, jadi panitia bisa
 * memindahkan permission di balik tiap key lewat panel tanpa menyentuh kode.
 *
 * Resource yang menyentuh jalannya pertandingan ditandai terkunci: sekali
 * terhapus dari panel, gelanggang kehilangan jalur otorisasinya di tengah
 * partai dan tidak ada cara memulihkannya selain lewat database.
 */
class SilatResourceSeeder extends Seeder
{
    public function run(): void
    {
        $manager = app(ResourceManager::class);

        foreach ($this->definitions() as $definition) {
            if (Resource::where('key', $definition['key'])->exists()) {
                continue;
            }

            $resource = $manager->createResource([
                'key' => $definition['key'],
                'label' => $definition['label'],
                'group' => $definition['group'],
                'description' => $definition['description'],
                'is_locked' => $definition['locked'] ?? false,
            ], $definition['actions']);

            if ($definition['locked'] ?? false) {
                $resource->mappings()->with('permission')->get()
                    ->each(fn ($mapping) => $mapping->permission?->update(['is_locked' => true]));
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        $crud = [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete];

        return [
            // ── Penyelenggaraan ──────────────────────────────────────────────
            [
                'key' => 'turnamen',
                'label' => 'Turnamen',
                'group' => 'Penyelenggaraan',
                'description' => 'Kejuaraan beserta tanggal, tempat, dan statusnya.',
                'actions' => [...$crud, ResourceAction::Manage],
                'locked' => true,
            ],
            [
                'key' => 'peraturan-turnamen',
                'label' => 'Setelan Peraturan',
                'group' => 'Penyelenggaraan',
                'description' => 'Nilai, hukuman, durasi babak, formasi juri, dan ambang konsensus per turnamen.',
                'actions' => [ResourceAction::View, ResourceAction::Update],
                'locked' => true,
            ],
            [
                'key' => 'gelanggang',
                'label' => 'Gelanggang',
                'group' => 'Penyelenggaraan',
                'description' => 'Arena pertandingan. Satu turnamen dapat menjalankan beberapa gelanggang sekaligus.',
                'actions' => $crud,
                'locked' => true,
            ],
            [
                'key' => 'kelas-tanding',
                'label' => 'Kelas Tanding',
                'group' => 'Penyelenggaraan',
                'description' => 'Kelas menurut golongan usia, jenis kelamin, dan rentang berat badan.',
                'actions' => $crud,
            ],
            [
                'key' => 'nomor-jurus',
                'label' => 'Nomor Jurus',
                'group' => 'Penyelenggaraan',
                'description' => 'Tunggal, Tunggal Bebas, Ganda, Regu, dan Solo Kreatif.',
                'actions' => $crud,
            ],

            // ── Peserta ──────────────────────────────────────────────────────
            [
                'key' => 'kontingen',
                'label' => 'Kontingen',
                'group' => 'Peserta',
                'description' => 'Daerah atau perguruan peserta beserta officialnya.',
                'actions' => [...$crud, ResourceAction::Export],
            ],
            [
                'key' => 'atlet',
                'label' => 'Atlet',
                'group' => 'Peserta',
                'description' => 'Data pesilat: identitas, tanggal lahir, foto, dan berkas persyaratan.',
                'actions' => [...$crud, ResourceAction::Export],
            ],
            [
                'key' => 'pendaftaran',
                'label' => 'Pendaftaran',
                'group' => 'Peserta',
                'description' => 'Pendaftaran atlet ke kelas tanding atau nomor jurus, beserta verifikasinya.',
                'actions' => [...$crud, ResourceAction::Approve, ResourceAction::Reject, ResourceAction::Export],
            ],
            [
                'key' => 'timbang-badan',
                'label' => 'Timbang Badan',
                'group' => 'Peserta',
                'description' => 'Pencatatan berat badan dan penentuan lolos atau gugur terhadap kelas.',
                'actions' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Export],
            ],

            // ── Keuangan ─────────────────────────────────────────────────────
            [
                'key' => 'tarif',
                'label' => 'Tarif Pendaftaran',
                'group' => 'Keuangan',
                'description' => 'Biaya per kategori dan golongan usia, plus biaya tetap per kontingen.',
                'actions' => $crud,
            ],
            [
                'key' => 'invoice',
                'label' => 'Invoice',
                'group' => 'Keuangan',
                'description' => 'Tagihan kontingen, pembayaran Midtrans, dan penandaan lunas manual.',
                'actions' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Export],
            ],

            // ── Pertandingan ─────────────────────────────────────────────────
            [
                'key' => 'bagan',
                'label' => 'Bagan',
                'group' => 'Pertandingan',
                'description' => 'Bagan gugur, undian, dan penguncian hasil drawing.',
                'actions' => [...$crud, ResourceAction::Assign],
                'locked' => true,
            ],
            [
                'key' => 'jadwal',
                'label' => 'Jadwal Partai',
                'group' => 'Pertandingan',
                'description' => 'Penempatan partai ke gelanggang dan urutan tayangnya.',
                'actions' => [...$crud, ResourceAction::Assign],
            ],
            [
                'key' => 'penugasan-aparat',
                'label' => 'Penugasan Aparat',
                'group' => 'Pertandingan',
                'description' => 'Penugasan wasit, juri, dan dewan wasit juri per partai.',
                'actions' => [ResourceAction::View, ResourceAction::Assign],
            ],
            [
                'key' => 'partai',
                'label' => 'Kendali Partai',
                'group' => 'Pertandingan',
                'description' => 'Memilih partai aktif dan mengendalikan timer babak.',
                'actions' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Manage],
                'locked' => true,
            ],
            [
                'key' => 'penilaian',
                'label' => 'Penilaian Juri',
                'group' => 'Pertandingan',
                'description' => 'Input nilai juri kategori Tanding maupun Jurus.',
                'actions' => [ResourceAction::View, ResourceAction::Create],
                'locked' => true,
            ],
            [
                'key' => 'hukuman',
                'label' => 'Hukuman',
                'group' => 'Pertandingan',
                'description' => 'Pembinaan, teguran, peringatan, dan hitungan teknik oleh wasit.',
                'actions' => [ResourceAction::View, ResourceAction::Create],
                'locked' => true,
            ],
            [
                'key' => 'hasil-partai',
                'label' => 'Hasil Partai',
                'group' => 'Pertandingan',
                'description' => 'Verifikasi, koreksi bernotulen, dan pengesahan hasil.',
                'actions' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Print],
                'locked' => true,
            ],

            // ── Keberatan ────────────────────────────────────────────────────
            [
                'key' => 'var',
                'label' => 'Protes VAR',
                'group' => 'Keberatan',
                'description' => 'Kartu protes pelatih dan keputusan Wasit Komisi Protes dalam tenggat 5 menit.',
                'actions' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                'locked' => true,
            ],
            [
                'key' => 'protes-manajer',
                'label' => 'Protes Manajer',
                'group' => 'Keberatan',
                'description' => 'Protes tingkat pertama ke Ketua Pertandingan dan banding ke Delegasi Teknik.',
                'actions' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Approve, ResourceAction::Reject],
                'locked' => true,
            ],

            // ── Publikasi ────────────────────────────────────────────────────
            [
                'key' => 'rekap',
                'label' => 'Rekap & Laporan',
                'group' => 'Publikasi',
                'description' => 'Rekap medali, daftar juara, berita acara partai, dan ekspor.',
                'actions' => [ResourceAction::View, ResourceAction::Export, ResourceAction::Print],
            ],
        ];
    }
}
