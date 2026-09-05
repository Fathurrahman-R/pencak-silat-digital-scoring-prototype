<?php

namespace App\Support\Bagan;

use App\Enums\StatusPendaftaran;
use App\Models\JurusBattle;
use App\Models\JurusBracket;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyusun bagan gugur satu nomor Jurus berformat battle -- Pasal 12.1.b.1.
 *
 * Cermin BracketGenerator, dan sengaja tidak menumpang tabelnya: alasan
 * lengkapnya ada di migrasi `2026_09_05_100600_buat_bagan_jurus`.
 *
 * Yang TIDAK digandakan, dan itu yang membuat kelas ini pendek:
 *
 *   UrutanUnggulan   ukuran bagan dan penyebaran unggulan -- murni aritmetika
 *   PohonBagan       geometri gambar pohon -- bekerja atas koordinat, bukan model
 *   PromosiPemenang  menaikkan pemenang -- lewat kontrak Terbagankan
 *
 * Cara MENILAI juga tidak berubah: tetap median seluruh juri atas dua
 * penampilan terpisah, dikurangi pengurangan. Yang ditambahkan bagan ini cuma
 * pertanyaan siapa bertemu siapa.
 */
class SusunBaganJurus
{
    /**
     * @throws RuntimeException
     */
    public function untukNomor(JurusEvent $nomor, bool $acak = true): JurusBracket
    {
        if (! $nomor->format->pakaiBagan()) {
            throw new RuntimeException(
                "Nomor {$nomor->jenis->label()} berformat penampilan, bukan battle — bagannya tidak disusun.",
            );
        }

        $peserta = $this->pesertaSah($nomor);

        if ($peserta->count() < 2) {
            throw new RuntimeException(
                "{$nomor->jenis->label()} hanya punya {$peserta->count()} peserta sah; bagan butuh sekurang-kurangnya dua.",
            );
        }

        $lama = JurusBracket::firstWhere('jurus_event_id', $nomor->id);

        if ($lama?->terkunci()) {
            throw new RuntimeException("Bagan {$nomor->jenis->label()} sudah dikunci dan tidak bisa disusun ulang.");
        }

        $ukuran = UrutanUnggulan::ukuranBagan($peserta->count());
        $urutan = $acak ? $peserta->shuffle() : $peserta;

        return DB::transaction(function () use ($nomor, $lama, $ukuran, $urutan) {
            $lama?->delete();

            $bracket = JurusBracket::create([
                'jurus_event_id' => $nomor->id,
                'size' => $ukuran,
            ]);

            $this->isiTempat($bracket, $urutan);
            $this->susunBattle($bracket);

            return $bracket->refresh();
        });
    }

    public function kunci(JurusBracket $bracket, User $user): JurusBracket
    {
        if ($bracket->terkunci()) {
            throw new RuntimeException('Bagan ini sudah dikunci.');
        }

        $bracket->update(['locked_at' => now(), 'locked_by' => $user->id]);

        return $bracket->refresh();
    }

    /**
     * Peserta yang berhak masuk bagan.
     *
     * Syaratnya sama dengan Tanding: hanya pendaftaran yang sudah disahkan.
     *
     * @return Collection<int, Registration>
     */
    public function pesertaSah(JurusEvent $nomor): Collection
    {
        return Registration::query()
            ->where('jurus_event_id', $nomor->id)
            ->where('status', StatusPendaftaran::Terverifikasi)
            ->with(['athletes', 'contingent'])
            ->orderBy('id')
            ->get();
    }

    /** @param  Collection<int, Registration>  $peserta */
    private function isiTempat(JurusBracket $bracket, Collection $peserta): void
    {
        $baris = [];

        foreach (UrutanUnggulan::untuk($bracket->size) as $tempat => $nomorUnggulan) {
            $baris[] = [
                'jurus_bracket_id' => $bracket->id,
                'position' => $tempat + 1,
                'registration_id' => $peserta->get($nomorUnggulan - 1)?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $bracket->slots()->insert($baris);
    }

    private function susunBattle(JurusBracket $bracket): void
    {
        $jumlahRonde = (int) log($bracket->size, 2);
        $baris = [];

        for ($ronde = 1; $ronde <= $jumlahRonde; $ronde++) {
            $jumlah = $bracket->size / (2 ** $ronde);

            for ($nomor = 1; $nomor <= $jumlah; $nomor++) {
                $baris[] = [
                    'jurus_bracket_id' => $bracket->id,
                    'round' => $ronde,
                    'position' => $nomor,
                    'status' => JurusBattle::STATUS_TERJADWAL,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        JurusBattle::insert($baris);

        foreach ($bracket->slots()->orderBy('position')->get()->chunk(2) as $indeks => $pasangan) {
            $bracket->battles()
                ->where('round', 1)
                ->where('position', $indeks + 1)
                ->firstOrFail()
                ->update([
                    'red_registration_id' => $pasangan->first()->registration_id,
                    'blue_registration_id' => $pasangan->last()->registration_id,
                ]);
        }

        $this->luluskanBye($bracket->refresh());
    }

    /**
     * Meluluskan peserta yang lawannya bye.
     *
     * Dilakukan saat bagan disusun, bukan menunggu hari-H: battle bye tidak
     * pernah benar-benar ditampilkan, dan menyisakannya sebagai "terjadwal"
     * berarti pengendali gelanggang menunggu sesuatu yang tidak akan datang.
     */
    private function luluskanBye(JurusBracket $bracket): void
    {
        $promosi = new PromosiPemenang;

        foreach ($bracket->battles()->where('round', 1)->get() as $battle) {
            $terisi = array_filter([$battle->red_registration_id, $battle->blue_registration_id]);

            if (count($terisi) !== 1) {
                continue;
            }

            $battle->update([
                'winner_registration_id' => reset($terisi),
                'win_reason' => 'bye',
                'status' => JurusBattle::STATUS_SELESAI,
            ]);

            $promosi($battle->refresh());
        }
    }

    /**
     * Membuat dua penampilan untuk satu battle -- satu per sudut.
     *
     * Tahapnya diturunkan dari ronde bagan, bukan diketik ulang: naskah
     * menetapkan jurus dan durasi yang berbeda tiap tahap (Pasal 12.1.b.2-5),
     * dan tahap yang salah berarti waktu acuan yang salah dipakai pemecah seri.
     *
     * @return Collection<int, JurusPerformance>
     */
    public function siapkanPenampilan(JurusBattle $battle): Collection
    {
        $bracket = $battle->bracket;
        $tahap = TahapBaganJurus::untuk($battle->round, $bracket->size);

        return collect(['biru' => $battle->blue_registration_id, 'merah' => $battle->red_registration_id])
            ->filter()
            ->map(fn (int $registrationId, string $sudut) => JurusPerformance::firstOrCreate(
                [
                    'jurus_event_id' => $bracket->jurus_event_id,
                    'registration_id' => $registrationId,
                    'tahap' => $tahap,
                ],
                [
                    'jurus_battle_id' => $battle->id,
                    'sudut' => $sudut,
                    'arena_id' => $battle->arena_id,
                ],
            ))
            ->values();
    }
}
