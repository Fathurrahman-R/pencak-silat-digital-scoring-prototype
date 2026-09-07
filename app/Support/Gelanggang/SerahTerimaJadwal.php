<?php

namespace App\Support\Gelanggang;

use App\Models\AdopsiJadwal;
use App\Models\Arena;
use App\Models\JurusPerformance;
use App\Models\SerahJadwal;
use App\Models\SilatMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Memindahkan partai atau penampilan antar gelanggang, tanpa keluar dari panel
 * kendali dan tanpa menuntut node global terjangkau.
 *
 * # Kenapa lepas-adopsi, bukan satu tombol
 *
 * `arena_id` adalah kolom yang menentukan kepemilikan di sinkron. Kalau A
 * melepas lalu B langsung mengubahnya, B menulis baris yang belum jadi
 * miliknya. Jalan keluarnya dua setengah-catatan yang masing-masing ditulis
 * pemiliknya sendiri -- lihat migrasi `buat_tabel_serah_jadwal`.
 *
 * # Kenapa dua tangan
 *
 * Penerima harus mengambil. Tanpa itu, partai mendarat di gelanggang yang
 * pengendalinya tidak sedang menunggunya, dan ia baru sadar saat jadwalnya
 * bertambah sendiri. Dengan itu, satu-satunya kegagalan yang mungkin adalah
 * baris yang terlihat menggantung -- keadaan yang terbaca sebelum ada yang
 * bergerak.
 *
 * # Yang TIDAK dilakukan di sini
 *
 * Kelas ini tidak pernah mengubah `arena_id` atas nama pelepas. Pelepas hanya
 * menulis penawarannya lalu berhenti menayangkan; yang memindahkan barisnya
 * adalah node penerima, sesudah adopsinya tercatat.
 */
class SerahTerimaJadwal
{
    public function __construct(private readonly PointerTayang $pointer) {}

    /**
     * Gelanggang pemilik melepas satu baris ke gelanggang lain.
     *
     * @throws RuntimeException
     */
    public function lepas(
        Arena $dari,
        Arena $ke,
        SilatMatch|JurusPerformance $baris,
        User $oleh,
        ?string $alasan = null,
    ): SerahJadwal {
        if ($dari->id === $ke->id) {
            throw new RuntimeException('Gelanggang tujuan sama dengan gelanggang asal.');
        }

        if ($baris->arena_id !== $dari->id) {
            throw new RuntimeException('Baris ini bukan milik gelanggang yang melepasnya.');
        }

        if (! $ke->is_active) {
            throw new RuntimeException('Gelanggang tujuan sedang tidak aktif.');
        }

        $jenis = $this->jenis($baris);

        if ($this->penawaranMenggantung($jenis, $baris->id) !== null) {
            throw new RuntimeException('Baris ini sudah dilepas dan sedang menunggu diambil.');
        }

        /*
         * Partai yang masih berjalan tidak boleh dilepas begitu saja. Yang
         * memutuskannya penjagaan yang sama dengan pemindahan pointer, jadi
         * pengendali tidak menghadapi dua aturan berbeda untuk satu keadaan
         * yang sama.
         */
        return DB::transaction(function () use ($dari, $ke, $baris, $oleh, $alasan, $jenis) {
            $serah = SerahJadwal::create([
                'arena_id' => $dari->id,
                'ke_arena_id' => $ke->id,
                'baris_type' => $jenis,
                'baris_id' => $baris->id,
                'dilepas_pada' => now(),
                'dilepas_oleh' => $oleh->id,
                'alasan' => $alasan,
            ]);

            /*
             * Berhenti menayangkannya SEKARANG, bukan menunggu diambil.
             * Selama penawaran menggantung, baris itu tidak boleh ada di layar
             * mana pun: yang menontonnya akan mengira ia masih dimainkan di
             * sini.
             */
            if ($this->sedangDitayangkan($dari, $jenis, $baris->id)) {
                $this->pointer->kosongkan($dari, $oleh, paksa: true);
            }

            return $serah;
        });
    }

    /**
     * Pelepas membatalkan penawarannya, selama belum diambil.
     *
     * @throws RuntimeException
     */
    public function batalkan(SerahJadwal $serah, User $oleh): SerahJadwal
    {
        if ($serah->dibatalkan()) {
            return $serah;
        }

        if ($serah->sudahDiambil()) {
            throw new RuntimeException(
                'Penawaran ini sudah diambil gelanggang tujuan. Jalan kembalinya adalah pemindahan baru ke arah sebaliknya.',
            );
        }

        $serah->forceFill([
            'dibatalkan_pada' => now(),
            'dibatalkan_oleh' => $oleh->id,
        ])->save();

        return $serah;
    }

    /**
     * Gelanggang tujuan mengambil baris yang ditawarkan kepadanya.
     *
     * Inilah satu-satunya tempat `arena_id` berpindah, dan ia berpindah
     * SESUDAH adopsinya tercatat -- urutan itu yang membuat penulisannya sah.
     *
     * @throws RuntimeException
     */
    public function ambil(SerahJadwal $serah, User $oleh): AdopsiJadwal
    {
        if ($serah->dibatalkan()) {
            throw new RuntimeException('Penawaran ini sudah dibatalkan gelanggang asal.');
        }

        if ($serah->sudahDiambil()) {
            throw new RuntimeException('Penawaran ini sudah diambil.');
        }

        return DB::transaction(function () use ($serah, $oleh) {
            $adopsi = AdopsiJadwal::create([
                'serah_id' => $serah->id,
                'arena_id' => $serah->ke_arena_id,
                'diambil_pada' => now(),
                'diambil_oleh' => $oleh->id,
            ]);

            $baris = $this->baris($serah);

            if ($baris === null) {
                throw new RuntimeException('Baris yang ditawarkan sudah tidak ada.');
            }

            /*
             * Urutan tayang dikosongkan, tidak dibawa serta: nomor urut milik
             * antrean gelanggang ASAL, dan menempelkannya di gelanggang tujuan
             * menyisipkan partai di tengah antrean orang lain tanpa ada yang
             * memutuskannya. Pengendali tujuan yang menempatkannya.
             */
            $baris->forceFill([
                'arena_id' => $serah->ke_arena_id,
                'order_in_arena' => null,
            ])->save();

            return $adopsi;
        });
    }

    /**
     * Yang dilepas gelanggang ini dan masih menunggu diambil.
     *
     * @return Collection<int, SerahJadwal>
     */
    public function menungguDiambil(Arena $arena): Collection
    {
        return SerahJadwal::query()
            ->menggantung()
            ->where('arena_id', $arena->id)
            ->with('tujuan')
            ->latest('dilepas_pada')
            ->get();
    }

    /**
     * Yang ditawarkan gelanggang lain KEPADA gelanggang ini.
     *
     * @return Collection<int, SerahJadwal>
     */
    public function ditawarkanKe(Arena $arena): Collection
    {
        return SerahJadwal::query()
            ->menggantung()
            ->where('ke_arena_id', $arena->id)
            ->with('arena')
            ->latest('dilepas_pada')
            ->get();
    }

    /**
     * Penawaran yang masih menggantung atas satu baris, kalau ada.
     *
     * Dipakai penjagaan penayangan: baris yang sedang ditawarkan tidak boleh
     * ditayangkan gelanggang mana pun -- pelepas sudah melepasnya, penerima
     * belum mengambilnya.
     */
    public function penawaranMenggantung(string $jenis, int $barisId): ?SerahJadwal
    {
        return SerahJadwal::query()
            ->menggantung()
            ->where('baris_type', $jenis)
            ->where('baris_id', $barisId)
            ->with(['arena', 'tujuan'])
            ->first();
    }

    private function jenis(Model $baris): string
    {
        return $baris instanceof SilatMatch ? SerahJadwal::TANDING : SerahJadwal::JURUS;
    }

    private function baris(SerahJadwal $serah): SilatMatch|JurusPerformance|null
    {
        return $serah->baris_type === SerahJadwal::TANDING
            ? SilatMatch::find($serah->baris_id)
            : JurusPerformance::find($serah->baris_id);
    }

    private function sedangDitayangkan(Arena $arena, string $jenis, int $barisId): bool
    {
        $tayang = $arena->fresh()?->tayang;

        return $tayang !== null
            && $tayang->tayang_type === $jenis
            && (int) $tayang->tayang_id === $barisId;
    }
}
