<?php

namespace App\Support\Pendaftaran;

use App\Enums\GolonganUsia;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusEvent;
use App\Models\WeightClass;
use Illuminate\Support\Collection;

/**
 * Memeriksa apakah sekumpulan atlet berhak mendaftar ke satu kelas tanding
 * atau satu nomor jurus.
 *
 * Seluruh syaratnya berasal dari naskah 2025: kesesuaian jenis kelamin,
 * golongan usia menurut umur pada bulan kejuaraan dimulai (Pasal 2), rentang
 * berat kelas (Pasal 3 sampai 7), dan jumlah pesilat per nomor (Pasal 12).
 *
 * Diperiksa di satu tempat, bukan disebar ke formulir, supaya jalur mana pun
 * yang dipakai — portal kontingen, impor massal, atau panitia yang
 * mendaftarkan atas nama kontingen — tunduk pada syarat yang sama.
 */
class PeriksaKelayakan
{
    /** @param  Collection<int, Athlete>|list<Athlete>  $atlet */
    public function untukKelasTanding(WeightClass $kelas, Collection|array $atlet, ?int $kecualikanPendaftaran = null): HasilKelayakan
    {
        $atlet = collect($atlet);
        $alasan = [];

        if ($atlet->count() !== 1) {
            $alasan[] = 'Kategori Tanding diisi tepat satu pesilat.';

            // Tanpa pesilat yang jelas, syarat selebihnya tidak bisa diperiksa.
            return HasilKelayakan::ditolak($alasan);
        }

        /** @var Athlete $pesilat */
        $pesilat = $atlet->first();
        $tournament = $kelas->tournament;

        $alasan = [
            ...$alasan,
            ...$this->periksaKontingen($pesilat, $kelas->tournament_id),
            ...$this->periksaGender($pesilat, $kelas->jenis_kelamin->value, $kelas->jenis_kelamin->label()),
            ...$this->periksaGolongan($pesilat, $kelas->golongan_usia, $tournament),
        ];

        /*
         * Berat klaim diperiksa hanya sebagai penyaring awal. Yang menentukan
         * pada akhirnya adalah timbang badan di venue — klaim yang meleset
         * sedikit lebih baik ditemukan sekarang daripada saat atlet sudah
         * berdiri di atas timbangan dengan bagan yang sudah tercetak.
         */
        if ($pesilat->weight_claim !== null && ! $kelas->memuatBerat((float) $pesilat->weight_claim)) {
            $alasan[] = "Berat klaim {$pesilat->weight_claim} kg berada di luar {$kelas->name} ({$kelas->rentang()}).";
        }

        if ($this->sudahTerdaftarDiKelas($pesilat, $kelas, $kecualikanPendaftaran)) {
            $alasan[] = "{$pesilat->name} sudah terdaftar di {$kelas->name}.";
        }

        return $alasan === [] ? HasilKelayakan::lolos() : HasilKelayakan::ditolak($alasan);
    }

    /** @param  Collection<int, Athlete>|list<Athlete>  $atlet */
    public function untukNomorJurus(JurusEvent $nomor, Collection|array $atlet, ?int $kecualikanPendaftaran = null): HasilKelayakan
    {
        $atlet = collect($atlet);
        $alasan = [];
        $tournament = $nomor->tournament;

        $seharusnya = $nomor->jenis->jumlahPesilat();

        if ($atlet->count() !== $seharusnya) {
            $alasan[] = "{$nomor->jenis->label()} diisi tepat {$seharusnya} pesilat, "
                ."saat ini {$atlet->count()}.";
        }

        if ($atlet->pluck('id')->duplicates()->isNotEmpty()) {
            $alasan[] = 'Satu pesilat tidak boleh mengisi dua tempat pada nomor yang sama.';
        }

        foreach ($atlet as $pesilat) {
            $alasan = [
                ...$alasan,
                ...$this->periksaKontingen($pesilat, $nomor->tournament_id),
                ...$this->periksaGender($pesilat, $nomor->jenis_kelamin->value, $nomor->jenis_kelamin->label()),
                ...$this->periksaGolongan($pesilat, $nomor->golongan_usia, $tournament),
            ];

            if ($this->sudahTerdaftarDiNomor($pesilat, $nomor, $kecualikanPendaftaran)) {
                $alasan[] = "{$pesilat->name} sudah terdaftar di {$nomor->nama()}.";
            }
        }

        // Nomor beregu diisi satu tim, jadi seluruh pesilatnya harus berasal
        // dari kontingen yang sama.
        if ($atlet->pluck('contingent_id')->unique()->count() > 1) {
            $alasan[] = 'Seluruh pesilat pada satu nomor harus berasal dari kontingen yang sama.';
        }

        return $alasan === [] ? HasilKelayakan::lolos() : HasilKelayakan::ditolak($alasan);
    }

    /** @return list<string> */
    private function periksaKontingen(Athlete $pesilat, int $tournamentId): array
    {
        return $pesilat->contingent->tournament_id === $tournamentId
            ? []
            : ["{$pesilat->name} terdaftar di kejuaraan lain."];
    }

    /** @return list<string> */
    private function periksaGender(Athlete $pesilat, string $nomorGender, string $labelGender): array
    {
        return $pesilat->jenis_kelamin->value === $nomorGender
            ? []
            : ["{$pesilat->name} berjenis kelamin {$pesilat->jenis_kelamin->label()}, tidak sesuai nomor {$labelGender}."];
    }

    /** @return list<string> */
    private function periksaGolongan(Athlete $pesilat, GolonganUsia $golongan, $tournament): array
    {
        $umur = $pesilat->umurSaatKejuaraan($tournament);

        if ($golongan->mencakupUmur($umur)) {
            return [];
        }

        $sebenarnya = GolonganUsia::untukUmur($umur);

        return [
            "{$pesilat->name} berumur {$umur} tahun saat kejuaraan dimulai, "
            .'yang termasuk golongan '.($sebenarnya?->label() ?? 'di luar semua golongan')
            .", bukan {$golongan->label()}.",
        ];
    }

    private function sudahTerdaftarDiKelas(Athlete $pesilat, WeightClass $kelas, ?int $kecualikan): bool
    {
        return $pesilat->registrations()
            ->where('weight_class_id', $kelas->id)
            ->when($kecualikan, fn ($query) => $query->where('registrations.id', '!=', $kecualikan))
            ->exists();
    }

    private function sudahTerdaftarDiNomor(Athlete $pesilat, JurusEvent $nomor, ?int $kecualikan): bool
    {
        return $pesilat->registrations()
            ->where('jurus_event_id', $nomor->id)
            ->when($kecualikan, fn ($query) => $query->where('registrations.id', '!=', $kecualikan))
            ->exists();
    }

    /**
     * Kelas tanding yang cocok untuk seorang atlet, untuk ditawarkan di
     * formulir alih-alih membiarkan official menebak lalu ditolak.
     *
     * @return Collection<int, WeightClass>
     */
    public function kelasYangCocok(Athlete $pesilat, Contingent $kontingen): Collection
    {
        $tournament = $kontingen->tournament;
        $golongan = $pesilat->golonganUsia($tournament);

        if ($golongan === null || ! $golongan->pakaiKelasBerat()) {
            return collect();
        }

        return $tournament->weightClasses()
            ->aktif()
            ->untuk($golongan, $pesilat->jenis_kelamin)
            ->get()
            ->filter(fn (WeightClass $kelas): bool => $pesilat->weight_claim === null
                || $kelas->memuatBerat((float) $pesilat->weight_claim))
            ->values();
    }
}
