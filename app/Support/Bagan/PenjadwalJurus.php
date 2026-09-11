<?php

namespace App\Support\Bagan;

use App\Models\Arena;
use App\Models\JurusBattle;
use App\Models\JurusPerformance;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menempatkan penampilan Jurus ke gelanggang dan menjaga urutan tayangnya.
 *
 * Cermin PenjadwalPartai, dan sengaja bukan generalisasinya. Keduanya memang
 * mengerjakan aritmetika urutan yang sama, tapi penjagaannya berbeda di setiap
 * titik: partai Tanding dijaga `status` dan babak berjalan, penampilan Jurus
 * dijaga `ratified_at` -- ia tidak punya babak, dan "sudah dimulai" untuknya
 * berarti timernya sudah ditekan, bukan babak pertama sudah bergulir. Satu
 * kelas yang melayani keduanya akan berisi percabangan jenis di tiap metode,
 * dan percabangan itulah yang kemudian dilupakan saat salah satu aturannya
 * bergeser.
 *
 * Yang dijadwalkan adalah PENAMPILAN, bukan battle. Pointer gelanggang menunjuk
 * `jurus_performances` (lihat ArenaTayang::JURUS), dan satu battle tampil
 * sebagai dua penampilan berurutan -- biru lebih dulu, Pasal 12.1.d.7.
 * Menjadwalkan sebuah battle karena itu menetapkan kedua penampilannya
 * sekaligus, berurutan, dan menyalin gelanggangnya ke baris battle supaya
 * penampilan ronde berikutnya mewarisinya lewat SusunBaganJurus.
 *
 * Jadwal di sini URUTAN TAYANG, bukan jam -- alasannya sama persis dengan yang
 * ditulis di PenjadwalPartai.
 */
class PenjadwalJurus
{
    public function __construct(private readonly SusunBaganJurus $susun) {}

    public function tetapkan(JurusPerformance $performance, Arena $arena): JurusPerformance
    {
        $this->pastikanBelumSelesai($performance, 'dijadwalkan ulang');

        $urutanTerakhir = JurusPerformance::where('arena_id', $arena->id)->max('order_in_arena');

        $performance->update([
            'arena_id' => $arena->id,
            'order_in_arena' => ((int) $urutanTerakhir) + 1,
        ]);

        return $performance->refresh();
    }

    /**
     * Menjadwalkan satu battle: kedua sudutnya, berurutan, di gelanggang yang sama.
     *
     * Penampilannya dibuat di sini kalau belum ada. Menyuruh panitia menekan
     * "Siapkan penampilan" lebih dulu di layar lain berarti satu langkah yang
     * hanya ketahuan wajib setelah antrean gelanggang ternyata kosong --
     * padahal battle-nya sudah tampak terjadwal.
     *
     * @throws RuntimeException
     */
    public function tetapkanBattle(JurusBattle $battle, Arena $arena): JurusBattle
    {
        if ($battle->red_registration_id === null || $battle->blue_registration_id === null) {
            throw new RuntimeException('Battle ini belum punya dua sudut — belum bisa dijadwalkan.');
        }

        if ($battle->selesai()) {
            throw new RuntimeException('Battle yang sudah selesai tidak bisa dijadwalkan ulang.');
        }

        return DB::transaction(function () use ($battle, $arena) {
            /*
             * Gelanggang battle ditulis LEBIH DULU: siapkanPenampilan menyalin
             * `arena_id` battle ke penampilan yang baru dibuatnya, dan urutan
             * terbalik akan melahirkan penampilan tanpa gelanggang yang lalu
             * ditambal sebaris di bawahnya.
             */
            $battle->update(['arena_id' => $arena->id]);

            $penampilan = $this->susun->siapkanPenampilan($battle->refresh());

            foreach ($penampilan as $satu) {
                $this->tetapkan($satu, $arena);
            }

            $battle->update([
                'order_in_arena' => $penampilan->first()?->refresh()->order_in_arena,
            ]);

            return $battle->refresh();
        });
    }

    public function lepas(JurusPerformance $performance): JurusPerformance
    {
        $this->pastikanBelumSelesai($performance, 'dilepas dari gelanggangnya');
        $this->pastikanTidakSedangDitayangkan($performance);

        $performance->update(['arena_id' => null, 'order_in_arena' => null]);

        return $performance->refresh();
    }

    /** Melepas kedua sudut satu battle sekaligus, beserta baris battle-nya. */
    public function lepasBattle(JurusBattle $battle): JurusBattle
    {
        return DB::transaction(function () use ($battle) {
            foreach ($battle->performances as $satu) {
                $this->lepas($satu);
            }

            $battle->update(['arena_id' => null, 'order_in_arena' => null]);

            return $battle->refresh();
        });
    }

    /**
     * Memindahkan penampilan ke urutan tertentu, menggeser yang lain sekali jalan.
     *
     * Bedanya dengan urutkan(): yang itu menukar dengan tetangga sebelah, satu
     * langkah per panggilan.
     */
    public function pindahkan(JurusPerformance $performance, int $tujuan): JurusPerformance
    {
        if ($performance->arena_id === null) {
            throw new RuntimeException('Penampilan ini belum dijadwalkan ke gelanggang mana pun.');
        }

        $this->pastikanBelumSelesai($performance, 'digeser urutannya');

        return DB::transaction(function () use ($performance, $tujuan) {
            $urut = JurusPerformance::where('arena_id', $performance->arena_id)
                ->orderBy('order_in_arena')
                ->lockForUpdate()
                ->get();

            $tujuan = max(1, min($tujuan, $urut->count()));

            $tanpa = $urut->reject(fn (JurusPerformance $p) => $p->id === $performance->id)->values();
            $baru = $tanpa->splice(0, $tujuan - 1)
                ->push($performance)
                ->concat($tanpa)
                ->values();

            /*
             * Ditulis ulang seluruhnya, bukan cuma yang bergeser -- alasannya
             * sama dengan PenjadwalPartai::pindahkan(): urutan yang sudah tidak
             * rapat sesudah satu baris dilepas akan melahirkan nomor ganda.
             */
            foreach ($baru as $i => $satu) {
                if ($satu->order_in_arena !== $i + 1) {
                    $satu->update(['order_in_arena' => $i + 1]);
                }
            }

            return $performance->refresh();
        });
    }

    public function urutkan(JurusPerformance $performance, int $langkah): JurusPerformance
    {
        if ($performance->arena_id === null) {
            throw new RuntimeException('Penampilan ini belum dijadwalkan ke gelanggang mana pun.');
        }

        $this->pastikanBelumSelesai($performance, 'digeser urutannya');

        $tetangga = JurusPerformance::where('arena_id', $performance->arena_id)
            ->where('order_in_arena', $performance->order_in_arena + $langkah)
            ->first();

        if ($tetangga === null) {
            return $performance;
        }

        DB::transaction(function () use ($performance, $tetangga) {
            [$urutanIni, $urutanTetangga] = [$performance->order_in_arena, $tetangga->order_in_arena];

            $performance->update(['order_in_arena' => $urutanTetangga]);
            $tetangga->update(['order_in_arena' => $urutanIni]);
        });

        return $performance->refresh();
    }

    /**
     * Jadwal hanya boleh disentuh selama penampilannya belum berjalan atau disahkan.
     *
     * Yang mengikat di sini `ratified_at`, bukan `status` semata: nilai yang
     * sudah disahkan masuk peringkat, medali, dan berita acara, dan gelanggang
     * tempat penampilan itu terjadi bagian dari catatan tersebut.
     */
    private function pastikanBelumSelesai(JurusPerformance $performance, string $aksi): void
    {
        if ($performance->status === JurusPerformance::STATUS_BERLANGSUNG) {
            throw new RuntimeException("Penampilan ini sedang berlangsung — tidak bisa {$aksi}. Hentikan dulu timernya.");
        }

        if ($performance->disahkan()) {
            throw new RuntimeException("Penampilan ini sudah disahkan — tidak bisa {$aksi}.");
        }
    }

    /**
     * Penampilan yang sedang ditunjuk gelanggang tidak boleh dilepas.
     *
     * Statusnya boleh saja masih `terjadwal` -- pengendali sudah memilihnya dan
     * papan gelanggang sudah menyebut namanya, tapi timernya belum ditekan.
     * Melepasnya di detik itu membuat pointer menunjuk penampilan tanpa
     * gelanggang, dan panel juri yang mengikuti gelanggang kehilangan sasaran
     * tanpa satu pun pesan yang menjelaskan kenapa.
     */
    private function pastikanTidakSedangDitayangkan(JurusPerformance $performance): void
    {
        $ditayangkan = Arena::whereKey($performance->arena_id)
            ->menayangkanPenampilan($performance->id)
            ->exists();

        if ($ditayangkan) {
            throw new RuntimeException(
                'Penampilan ini sedang ditayangkan di gelanggangnya — pindahkan dulu tayangan gelanggang itu.',
            );
        }
    }
}
