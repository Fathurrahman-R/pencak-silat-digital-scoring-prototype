<?php

namespace App\Support\Scoring;

use App\Enums\JawabanVerifikasi;
use App\Enums\JenisVerifikasi;
use App\Enums\Sudut;
use App\Enums\TingkatPelanggaran;
use App\Models\JudgeVerification;
use App\Models\JudgeVerificationAnswer;
use App\Models\MatchOfficial;
use App\Models\Penalty;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verifikasi juri -- Pasal 13.
 *
 * Wasit atau Ketua Pertandingan yang ragu sudut mana yang menjatuhkan atau
 * melanggar menghentikan pertandingan dan menanyakannya ke tiga juri.
 *
 * Bedanya dengan ConsensusEvaluator, yang menilai penekanan tombol biasa:
 *
 * - Tidak ada window waktu. Juri diberi pertanyaan dan menjawabnya kapan pun
 *   ia siap; yang menutup polling adalah tercapainya ambang, bukan lewatnya
 *   dua detik. Verifikasi memang menghentikan pertandingan -- tidak ada yang
 *   dikejar.
 * - "Tidak ada" ikut dihitung sebagai jawaban penuh dan bisa menang. Lihat
 *   catatan di App\Enums\JawabanVerifikasi.
 * - Hasilnya tidak langsung jadi nilai. Ia dinyatakan lebih dulu, lalu
 *   diterapkan sebagai langkah terpisah oleh yang meminta -- supaya akibatnya
 *   terbaca sebelum terjadi, bukan sesudah.
 *
 * Dua jenis pertanyaan berakhir berbeda. Pelanggaran menerbitkan sanksinya
 * sendiri saat diterapkan. Jatuhan TIDAK: nilainya mutlak, keputusan Dewan
 * Wasit Juri, jadi jawaban juri di sini berhenti sebagai masukan yang dibaca
 * dewan -- dan tetap tercatat, karena Pasal 15 membolehkan pelatih memprotes
 * jawaban itu.
 *
 * Ambangnya sama, dibaca dari setelan peraturan turnamen.
 */
class PollingVerifikasi
{
    public function __construct(
        private readonly TanggaHukuman $tangga,
    ) {}

    /**
     * Wasit atau Ketua Pertandingan mengajukan pertanyaan ke juri.
     */
    public function minta(
        SilatMatch $match,
        User $peminta,
        int $babak,
        JenisVerifikasi $jenis,
        ?TingkatPelanggaran $tingkat = null,
        ?ScoreEvent $scoreEvent = null,
        ?Penalty $penalty = null,
    ): JudgeVerification {
        if ($match->disahkan()) {
            throw ValidationException::withMessages([
                'match' => 'Hasil partai ini sudah disahkan, jadi tidak ada lagi yang bisa diverifikasi.',
            ]);
        }

        if ($jenis === JenisVerifikasi::Pelanggaran && $tingkat === null) {
            throw ValidationException::withMessages([
                'tingkat_pelanggaran' => 'Pilih dulu tingkat pelanggarannya, supaya juri tahu sanksi apa yang akan dijatuhkan.',
            ]);
        }

        return DB::transaction(function () use ($match, $peminta, $babak, $jenis, $tingkat, $scoreEvent, $penalty) {
            /*
             * Baris partai dikunci: dua permintaan yang tiba nyaris bersamaan
             * -- wasit dan Ketua Pertandingan menekan hampir serentak -- akan
             * membuat dua pertanyaan berjalan sekaligus, dan panel juri tidak
             * punya cara menampilkan keduanya.
             */
            SilatMatch::whereKey($match->id)->lockForUpdate()->first();

            $berjalan = JudgeVerification::query()
                ->where('match_id', $match->id)
                ->berjalan()
                ->exists();

            if ($berjalan) {
                throw ValidationException::withMessages([
                    'match' => 'Masih ada verifikasi yang menunggu jawaban juri. Selesaikan atau batalkan dulu yang itu.',
                ]);
            }

            return JudgeVerification::create([
                'match_id' => $match->id,
                'round' => $babak,
                'jenis' => $jenis,
                'tingkat_pelanggaran' => $tingkat,
                'score_event_id' => $scoreEvent?->id,
                'penalty_id' => $penalty?->id,
                'diminta_oleh' => $peminta->id,
                'diminta_at' => now(),
                'status' => JudgeVerification::BERJALAN,
            ]);
        });
    }

    /**
     * Satu juri menjawab.
     *
     * Mengembalikan verifikasi yang sudah disegarkan, supaya pemanggilnya bisa
     * melihat apakah jawaban ini yang menutup polling.
     */
    public function jawab(JudgeVerification $verifikasi, User $juri, JawabanVerifikasi $jawaban): JudgeVerification
    {
        if (! $verifikasi->berjalan()) {
            throw ValidationException::withMessages([
                'verifikasi' => 'Verifikasi ini sudah ditutup.',
            ]);
        }

        $petugas = MatchOfficial::query()
            ->where('match_id', $verifikasi->match_id)
            ->where('user_id', $juri->id)
            ->where('role', MatchOfficial::ROLE_JURI)
            ->first();

        if ($petugas === null) {
            throw ValidationException::withMessages([
                'verifikasi' => 'Hanya juri yang ditugaskan di partai ini yang bisa menjawab verifikasinya.',
            ]);
        }

        return DB::transaction(function () use ($verifikasi, $juri, $jawaban, $petugas) {
            $terkunci = JudgeVerification::whereKey($verifikasi->id)->lockForUpdate()->first();

            if (! $terkunci->berjalan()) {
                throw ValidationException::withMessages([
                    'verifikasi' => 'Verifikasi ini sudah ditutup.',
                ]);
            }

            /*
             * Jawaban pertama yang menang, bukan yang terakhir. Juri yang
             * menekan dua kali karena panelnya terasa lambat tidak boleh
             * mengubah jawabannya sendiri diam-diam -- kalau ia benar-benar
             * keliru, Wasit membatalkan verifikasinya dan bertanya ulang,
             * sehingga ralatnya ikut tercatat.
             */
            $sudah = JudgeVerificationAnswer::query()
                ->where('judge_verification_id', $terkunci->id)
                ->where('judge_user_id', $juri->id)
                ->exists();

            if ($sudah) {
                throw ValidationException::withMessages([
                    'verifikasi' => 'Jawabanmu sudah masuk. Kalau keliru, Wasit yang membatalkan verifikasi ini dan bertanya ulang.',
                ]);
            }

            JudgeVerificationAnswer::create([
                'judge_verification_id' => $terkunci->id,
                'judge_user_id' => $juri->id,
                'judge_number' => $petugas->number,
                'jawaban' => $jawaban,
                'server_ts' => now(),
            ]);

            $this->nilaiUlang($terkunci);

            return $terkunci->fresh(['answers']);
        });
    }

    /**
     * Menetapkan hasil kalau salah satu pilihan sudah mencapai ambang.
     *
     * Dipanggil di dalam transaksi `jawab()`, dengan baris verifikasi sudah
     * terkunci.
     */
    private function nilaiUlang(JudgeVerification $verifikasi): void
    {
        if ($verifikasi->hasil !== null) {
            return;
        }

        $ambang = $verifikasi->match->bracket->weightClass->tournament->peraturan()->ambang_sepakat;

        $hitungan = $this->hitungan($verifikasi);

        foreach ($hitungan as $nilai => $jumlah) {
            if ($jumlah >= $ambang) {
                $verifikasi->forceFill([
                    'hasil' => JawabanVerifikasi::from($nilai),
                    'hasil_at' => now(),
                ])->save();

                return;
            }
        }

        /*
         * Semua juri sudah menjawab tapi tidak ada yang mencapai ambang --
         * tiga jawaban berbeda. Hasilnya "tidak ada": tidak ada satu pun
         * pilihan yang didukung mayoritas, dan menerbitkan nilai atas dasar
         * suara terbanyak yang di bawah ambang berarti memakai aturan yang
         * berbeda dari penilaian biasa.
         */
        if (array_sum($hitungan) >= $this->jumlahJuri($verifikasi)) {
            $verifikasi->forceFill([
                'hasil' => JawabanVerifikasi::TidakAda,
                'hasil_at' => now(),
            ])->save();
        }
    }

    /**
     * Jumlah suara per pilihan, urut dari terbanyak.
     *
     * @return array<string, int>
     */
    public function hitungan(JudgeVerification $verifikasi): array
    {
        $hitung = ['red' => 0, 'blue' => 0, 'tidak_ada' => 0];

        /*
         * Dibaca langsung dari basis data, bukan dari relasi `answers`.
         * nilaiUlang() memanggil ini tepat setelah satu jawaban disimpan, dan
         * relasi yang sudah pernah dimuat tidak ikut berubah -- jawaban ketiga
         * tidak akan pernah terhitung, sehingga polling menggantung selamanya.
         */
        $jawaban = JudgeVerificationAnswer::query()
            ->where('judge_verification_id', $verifikasi->id)
            ->pluck('jawaban');

        foreach ($jawaban as $satu) {
            $kunci = $satu instanceof JawabanVerifikasi ? $satu->value : $satu;
            $hitung[$kunci]++;
        }

        arsort($hitung);

        return $hitung;
    }

    /** Berapa juri yang ditugaskan di partai ini. */
    public function jumlahJuri(JudgeVerification $verifikasi): int
    {
        return MatchOfficial::query()
            ->where('match_id', $verifikasi->match_id)
            ->where('role', MatchOfficial::ROLE_JURI)
            ->count();
    }

    /**
     * Juri yang belum menjawab, untuk ditampilkan sebagai "menunggu jawaban".
     *
     * @return Collection<int, MatchOfficial>
     */
    public function belumMenjawab(JudgeVerification $verifikasi): Collection
    {
        $sudah = JudgeVerificationAnswer::query()
            ->where('judge_verification_id', $verifikasi->id)
            ->pluck('judge_user_id')
            ->filter()
            ->all();

        return MatchOfficial::query()
            ->where('match_id', $verifikasi->match_id)
            ->where('role', MatchOfficial::ROLE_JURI)
            ->whereNotIn('user_id', $sudah)
            ->with('user')
            ->orderBy('number')
            ->get();
    }

    /**
     * Kalimat yang menyatakan apa yang akan terjadi kalau hasilnya diterapkan.
     *
     * Ditulis di satu tempat karena dipakai dua kali dengan makna yang harus
     * persis sama: sebagai peringatan sebelum tombol ditekan, dan sebagai
     * catatan riwayat setelahnya. Kalau keduanya ditulis terpisah, yang satu
     * akan berubah lebih dulu dan panitia membaca dua akibat berbeda untuk
     * satu kejadian.
     */
    public function akibat(JudgeVerification $verifikasi): string
    {
        $hasil = $verifikasi->hasil;

        if ($hasil === null) {
            return 'Belum ada hasil.';
        }

        if ($hasil === JawabanVerifikasi::TidakAda) {
            return 'Tidak ada nilai maupun hukuman yang diterbitkan. Pertandingan lanjut dari posisi terakhir.';
        }

        $sudut = $hasil->sudut();

        if ($verifikasi->jenis === JenisVerifikasi::Jatuhan) {
            return "Jawaban juri dicatat: {$hasil->label()} — ".$this->namaPesilat($verifikasi, $sudut).
                '. Tidak ada nilai yang terbit dari sini; nilai mutlak jatuhan diterbitkan Dewan Wasit Juri.';
        }

        $tingkat = $verifikasi->tingkat_pelanggaran?->label() ?? 'Pelanggaran';

        return "Sanksi {$tingkat} dijatuhkan ke {$hasil->label()} — ".$this->namaPesilat($verifikasi, $sudut).'.';
    }

    /**
     * Menerapkan hasil: menerbitkan nilai atau sanksi, lalu menutup verifikasi.
     */
    public function terapkan(JudgeVerification $verifikasi, User $penerap): JudgeVerification
    {
        if ($verifikasi->hasil === null) {
            throw ValidationException::withMessages([
                'verifikasi' => 'Juri belum cukup menjawab. Tunggu sampai ambang tercapai.',
            ]);
        }

        if ($verifikasi->sudahDiterapkan()) {
            throw ValidationException::withMessages([
                'verifikasi' => 'Hasil verifikasi ini sudah diterapkan.',
            ]);
        }

        return DB::transaction(function () use ($verifikasi, $penerap) {
            $terkunci = JudgeVerification::whereKey($verifikasi->id)->lockForUpdate()->first();

            if ($terkunci->sudahDiterapkan()) {
                throw ValidationException::withMessages([
                    'verifikasi' => 'Hasil verifikasi ini sudah diterapkan.',
                ]);
            }

            /*
             * Statusnya diperiksa ulang DI DALAM kunci, bukan hanya
             * `diterapkan_at`-nya. Verifikasi yang dibatalkan wasit sepersekian
             * detik lebih dulu masih berstatus belum diterapkan, dan tanpa
             * pemeriksaan ini penerapan tetap jalan lalu menstempel baris yang
             * sudah dibatalkan.
             */
            if ($terkunci->status === JudgeVerification::DIBATALKAN) {
                throw ValidationException::withMessages([
                    'verifikasi' => 'Verifikasi ini sudah dibatalkan dari panel lain.',
                ]);
            }

            $sudut = $terkunci->hasil->sudut();

            /*
             * Verifikasi jatuhan TIDAK menerbitkan nilai.
             *
             * Nilai mutlak jatuhan bukan penilaian yang dikonsensuskan juri,
             * melainkan keputusan Dewan Wasit Juri. Yang dihasilkan polling ini
             * adalah jawaban juri atas pertanyaan wasit -- masukan yang dibaca
             * dewan sebelum menekan sudutnya, dan yang tetap tercatat di berita
             * acara supaya pelatih bisa memprotes jawabannya sesuai Pasal 15.
             * Nilainya sendiri terbit lewat PartaiScoringController::jatuhan(),
             * yang menautkan dirinya kembali ke baris verifikasi ini.
             */
            if ($sudut !== null && $terkunci->jenis === JenisVerifikasi::Pelanggaran) {
                $penalty = $this->tangga->catat(
                    $terkunci->match,
                    $sudut,
                    $terkunci->round,
                    $terkunci->tingkat_pelanggaran,
                    'Hasil verifikasi juri.',
                    $penerap,
                );

                $terkunci->penalty_id = $penalty->id;
            }

            $terkunci->forceFill([
                'status' => JudgeVerification::SELESAI,
                'diterapkan_at' => now(),
                'diterapkan_oleh' => $penerap->id,
            ])->save();

            return $terkunci->fresh(['answers']);
        });
    }

    /**
     * Membatalkan verifikasi tanpa menerapkan apa pun.
     *
     * Barisnya tetap ada beserta jawaban yang sudah masuk. Verifikasi yang
     * dibatalkan adalah bagian dari yang terjadi di gelanggang, dan pelatih
     * berhak melihatnya saat mengajukan protes.
     */
    public function batalkan(JudgeVerification $verifikasi, User $pembatal, ?string $alasan = null): JudgeVerification
    {
        /*
         * Dikunci seperti jalur terapkan().
         *
         * Sebelum ini pembatalan berjalan telanjang, tanpa transaksi maupun
         * kunci, sementara penerapan memegang kunci baris. Ketua menekan
         * "Terapkan" dan wasit menekan "Batalkan" pada detik yang sama:
         * keduanya dijawab berhasil, dan barisnya berakhir dengan status
         * "dibatalkan" tapi `hasil` dan `diterapkan_at` terisi -- berita
         * acara lalu memuat hasil verifikasi yang secara resmi sudah
         * dibatalkan. Yang menang harus satu, dan yang kalah harus tahu.
         */
        return DB::transaction(function () use ($verifikasi, $pembatal, $alasan) {
            $terkunci = JudgeVerification::whereKey($verifikasi->id)->lockForUpdate()->first();

            if ($terkunci === null || ! $terkunci->berjalan()) {
                throw ValidationException::withMessages([
                    'verifikasi' => 'Verifikasi ini sudah ditutup.',
                ]);
            }

            $terkunci->forceFill([
                'status' => JudgeVerification::DIBATALKAN,
                'diterapkan_oleh' => $pembatal->id,
                'catatan' => $alasan,
            ])->save();

            return $terkunci->fresh(['answers']);
        });
    }

    private function namaPesilat(JudgeVerification $verifikasi, ?Sudut $sudut): string
    {
        if ($sudut === null) {
            return '—';
        }

        $pendaftaran = $sudut === Sudut::Merah
            ? $verifikasi->match->red
            : $verifikasi->match->blue;

        return $pendaftaran?->athletes->pluck('name')->implode(', ') ?: 'pesilat';
    }
}
