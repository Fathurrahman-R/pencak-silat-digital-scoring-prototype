<?php

namespace App\Support\Bagan;

use App\Enums\StatusPendaftaran;
use App\Models\Bracket;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\WeightClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyusun bagan gugur tunggal satu kelas tanding.
 *
 * Hanya peserta yang sudah disahkan panitia yang masuk — berkasnya lengkap dan
 * tagihan kontingennya lunas. Peserta yang gugur di timbang badan tidak ikut,
 * dan itu memang perilaku yang dimaui: bagan disusun setelah penimbangan.
 *
 * Bagan yang sudah dikunci tidak bisa disusun ulang. Susunan yang bergeser
 * setelah diumumkan berarti kontingen menyiapkan lawan yang keliru, dan itu
 * jenis kesalahan yang tidak bisa diperbaiki di hari-H.
 */
class BracketGenerator
{
    public function untukKelas(WeightClass $kelas, bool $acak = true): Bracket
    {
        $peserta = $this->pesertaSah($kelas);

        if ($peserta->count() < 2) {
            throw new RuntimeException(
                "{$kelas->name} hanya punya {$peserta->count()} peserta sah; bagan butuh sekurang-kurangnya dua.",
            );
        }

        $lama = Bracket::firstWhere('weight_class_id', $kelas->id);

        if ($lama?->terkunci()) {
            throw new RuntimeException(
                "Bagan {$kelas->name} sudah dikunci dan tidak bisa disusun ulang.",
            );
        }

        $ukuran = UrutanUnggulan::ukuranBagan($peserta->count());
        $urutan = $acak ? $peserta->shuffle() : $peserta;

        return DB::transaction(function () use ($kelas, $lama, $ukuran, $urutan) {
            $lama?->delete();

            $bracket = Bracket::create([
                'weight_class_id' => $kelas->id,
                'size' => $ukuran,
            ]);

            $this->isiTempat($bracket, $urutan);
            $this->susunPartai($bracket);

            return $bracket->refresh();
        });
    }

    /**
     * Peserta yang berhak masuk bagan.
     *
     * @return Collection<int, Registration>
     */
    public function pesertaSah(WeightClass $kelas): Collection
    {
        return Registration::query()
            ->where('weight_class_id', $kelas->id)
            ->where('status', StatusPendaftaran::Terverifikasi)
            ->with(['athletes', 'contingent'])
            ->get();
    }

    /**
     * Menempatkan peserta pada tempat babak pertama.
     *
     * Peserta ke-n menempati tempat yang bernomor unggulan n, dan tempat yang
     * tersisa dibiarkan kosong sebagai bye. Karena susunan tempat baku
     * memasangkan nomor kecil dengan nomor besar, byenya tersebar ke partai
     * yang berbeda-beda — bukan menumpuk di satu sisi bagan.
     *
     * @param  Collection<int, Registration>  $peserta
     */
    private function isiTempat(Bracket $bracket, Collection $peserta): void
    {
        $urutanTempat = UrutanUnggulan::untuk($bracket->size);
        $baris = [];

        foreach ($urutanTempat as $tempat => $nomorUnggulan) {
            $baris[] = [
                'bracket_id' => $bracket->id,
                'position' => $tempat + 1,
                'registration_id' => $peserta->get($nomorUnggulan - 1)?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $bracket->slots()->insert($baris);
    }

    /**
     * Membuat seluruh partai dari babak pertama sampai final, lalu mengisi
     * babak pertama dan meluluskan bye.
     */
    private function susunPartai(Bracket $bracket): void
    {
        $jumlahBabak = (int) log($bracket->size, 2);
        $baris = [];

        for ($babak = 1; $babak <= $jumlahBabak; $babak++) {
            $jumlahPartai = $bracket->size / (2 ** $babak);

            for ($nomor = 1; $nomor <= $jumlahPartai; $nomor++) {
                $baris[] = [
                    'bracket_id' => $bracket->id,
                    'round' => $babak,
                    'position' => $nomor,
                    'status' => SilatMatch::STATUS_TERJADWAL,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        SilatMatch::insert($baris);

        $tempat = $bracket->slots()->orderBy('position')->get();

        foreach ($tempat->chunk(2) as $indeks => $pasangan) {
            $partai = $bracket->matches()
                ->where('round', 1)
                ->where('position', $indeks + 1)
                ->firstOrFail();

            $partai->update([
                'red_registration_id' => $pasangan->first()->registration_id,
                'blue_registration_id' => $pasangan->last()->registration_id,
            ]);
        }

        $this->luluskanBye($bracket);
    }

    /**
     * Meluluskan peserta yang lawannya bye.
     *
     * Dilakukan saat bagan disusun, bukan menunggu hari-H. Partai bye tidak
     * pernah benar-benar dipertandingkan, dan menyisakannya sebagai partai
     * "terjadwal" berarti operator gelanggang menunggu sesuatu yang tidak akan
     * datang.
     */
    private function luluskanBye(Bracket $bracket): void
    {
        foreach ($bracket->matches()->where('round', 1)->get() as $partai) {
            if (! $partai->bye()) {
                continue;
            }

            $pemenang = $partai->red_registration_id ?? $partai->blue_registration_id;

            $partai->update([
                'winner_registration_id' => $pemenang,
                'win_reason' => 'bye',
                'status' => SilatMatch::STATUS_SELESAI,
            ]);

            (new PromosiPemenang)($partai->refresh());
        }
    }
}
