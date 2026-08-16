<?php

namespace App\Support\Pendaftaran;

use App\Enums\StatusPendaftaran;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusEvent;
use App\Models\Registration;
use App\Models\WeightClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Membuat satu pendaftaran nomor setelah kelayakannya diperiksa.
 *
 * Berdiri sendiri karena pendaftaran lahir dari dua jalur: formulir
 * pendaftaran nomor, dan formulir atlet baru yang sekalian mendaftarkan
 * nomornya. Keduanya harus tunduk pada pemeriksaan yang sama — kalau
 * disalin, salah satunya cepat atau lambat akan tertinggal.
 */
class DaftarkanPeserta
{
    public function __construct(private readonly PeriksaKelayakan $periksa) {}

    /**
     * @throws PendaftaranDitolak
     */
    public function tanding(Contingent $contingent, WeightClass $kelas, Athlete $athlete): Registration
    {
        $hasil = $this->periksa->untukKelasTanding($kelas, [$athlete]);

        if ($hasil->ditolakSemua()) {
            throw new PendaftaranDitolak($hasil->alasan);
        }

        return DB::transaction(function () use ($contingent, $kelas, $athlete): Registration {
            $pendaftaran = $contingent->registrations()->create([
                'weight_class_id' => $kelas->id,
                'status' => StatusPendaftaran::Draf,
            ]);

            $pendaftaran->athletes()->attach($athlete, ['position' => 1]);

            return $pendaftaran;
        });
    }

    /**
     * @param  Collection<int, Athlete>  $athletes
     *
     * @throws PendaftaranDitolak
     */
    public function jurus(Contingent $contingent, JurusEvent $nomor, Collection $athletes): Registration
    {
        $hasil = $this->periksa->untukNomorJurus($nomor, $athletes);

        if ($hasil->ditolakSemua()) {
            throw new PendaftaranDitolak($hasil->alasan);
        }

        return DB::transaction(function () use ($contingent, $nomor, $athletes): Registration {
            $pendaftaran = $contingent->registrations()->create([
                'jurus_event_id' => $nomor->id,
                'status' => StatusPendaftaran::Draf,
            ]);

            foreach ($athletes->values() as $urutan => $athlete) {
                $pendaftaran->athletes()->attach($athlete, ['position' => $urutan + 1]);
            }

            return $pendaftaran;
        });
    }
}
