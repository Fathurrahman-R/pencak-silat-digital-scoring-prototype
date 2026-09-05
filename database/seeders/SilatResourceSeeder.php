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
                // Export dipakai tombol ekspor CSV di daftar kejuaraan. Tanpa
                // aksi ini, key `turnamen.export` tidak punya pemetaan dan
                // rutenya menolak semua orang kecuali super admin.
                'actions' => [...$crud, ResourceAction::Manage, ResourceAction::Export],
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
                'actions' => [...$crud, ResourceAction::Assign, ResourceAction::Print],
                'locked' => true,
            ],
            [
                'key' => 'jadwal',
                'label' => 'Jadwal Partai',
                'group' => 'Pertandingan',
                'description' => 'Penempatan partai ke gelanggang dan urutan tayangnya.',
                'actions' => [...$crud, ResourceAction::Assign, ResourceAction::Print],
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
                'key' => 'kendali-gelanggang',
                'label' => 'Kendali Gelanggang',
                'group' => 'Pertandingan',
                'description' => 'Menentukan partai yang sedang dimainkan gelanggang, mengendalikan timer dan perpindahan babak, dan membuka babak lama untuk input susulan.',
                /*
                 * Assign = menetapkan partai aktif gelanggang.
                 * Manage = membuka dan menutup babak lama untuk susulan, wewenang
                 *          terberat di gelanggang karena ia melonggarkan penjagaan
                 *          babak yang sudah ditutup.
                 */
                'actions' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Assign, ResourceAction::Manage],
                'locked' => true,
            ],
            [
                'key' => 'sinkron-gelanggang',
                'label' => 'Sinkron Gelanggang',
                'group' => 'Pertandingan',
                'description' => 'Menarik data dari laptop gelanggang lain dan melihat sudah sampai mana pertukarannya.',
                /*
                 * Update, bukan Manage: menarik data BUKAN wewenang terberat --
                 * ia tidak bisa mengubah hasil pertandingan, hanya membawa
                 * masuk apa yang sudah diputuskan di gelanggang lain. Baris
                 * yang datang pun disaring aturan kepemilikan, jadi penarikan
                 * tidak bisa menimpa catatan gelanggang ini sendiri.
                 *
                 * Terkunci karena tanpa jalur ini bagan lintas gelanggang
                 * berhenti di tengah hari: partai lanjutan tidak pernah tahu
                 * siapa pemenang babak sebelumnya.
                 */
                'actions' => [ResourceAction::View, ResourceAction::Update],
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

            // ── Kategori Jurus ───────────────────────────────────────────────
            [
                'key' => 'penampilan-jurus',
                'label' => 'Kendali Penampilan Jurus',
                'group' => 'Pertandingan',
                'description' => 'Membuat penampilan dari pendaftaran terverifikasi, mengendalikan timer.',
                'actions' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Manage],
                'locked' => true,
            ],
            [
                'key' => 'pengurangan-jurus',
                'label' => 'Pengurangan Nilai Jurus',
                'group' => 'Pertandingan',
                'description' => 'Pengurangan 0.50 oleh Pengawas/Dewan Wasit Juri dan penetapan diskualifikasi.',
                'actions' => [ResourceAction::View, ResourceAction::Create],
                'locked' => true,
            ],
            [
                'key' => 'hasil-jurus',
                'label' => 'Hasil Jurus',
                'group' => 'Pertandingan',
                'description' => 'Koreksi bernotulen dan pengesahan skor akhir penampilan Jurus.',
                'actions' => [ResourceAction::View, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Print],
                'locked' => true,
            ],

            // ── Siaran ───────────────────────────────────────────────────────
            [
                'key' => 'overlay',
                'label' => 'Alamat Overlay Siaran',
                'group' => 'Pertandingan',
                'description' => 'Daftar alamat halaman overlay vMix per gelanggang. Halaman overlaynya sendiri tidak berautentikasi -- yang dijaga key ini hanya daftar alamatnya.',
                'actions' => [ResourceAction::View],
            ],

            // ── Keberatan ────────────────────────────────────────────────────
            [
                'key' => 'verifikasi-juri',
                'label' => 'Verifikasi Juri',
                'group' => 'Keberatan',
                'description' => 'Pertanyaan Wasit atau Ketua Pertandingan ke tiga juri saat ragu sudut mana yang menjatuhkan atau melanggar (Pasal 13).',
                /*
                 * Empat aksi dengan pembagian yang tegas, karena empat peran
                 * berbeda menyentuhnya:
                 *
                 * - Create  membuka pertanyaan (Wasit, Ketua Pertandingan)
                 * - Update  menjawab (Juri, dan hanya juri partai itu)
                 * - Approve menerapkan hasil jadi nilai atau sanksi
                 * - Reject  membatalkan tanpa menerapkan apa pun
                 *
                 * Juri sengaja tidak diberi Create: juri yang bisa membuka
                 * pertanyaan sendiri bisa memaksa polling atas kejadian yang
                 * menguntungkan sudut yang dinilainya.
                 */
                'actions' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Approve, ResourceAction::Reject],
                'locked' => true,
            ],
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
