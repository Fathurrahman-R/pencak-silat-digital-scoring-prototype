<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `score_events` pindah ke ULID, beserta tiga kolom yang menunjuknya.
 *
 * Tabel kedelapan, dan yang paling berisiko sejauh ini. Tiga penunjuk, dan
 * salah satunya tidak punya foreign key sama sekali:
 *
 *   judge_verifications.score_event_id   foreign key, ON DELETE set null
 *   var_reviews.score_event_id           foreign key, ON DELETE set null
 *   judge_inputs.score_event_id          TANPA foreign key
 *
 * Yang ketiga itu sengaja dibiarkan tanpa constraint sejak migrasi aslinya --
 * tabel score_events baru lahir di migrasi berikutnya, dan constraint
 * melingkar di sana hanya menambah kerumitan urutan tanpa manfaat.
 *
 * Justru karena tidak punya foreign key, ia tidak akan mengeluh saat tipenya
 * tidak lagi cocok. Sebuah bigint yang menunjuk ULID diterima MySQL tanpa
 * sepatah kata pun; yang terjadi cuma riwayat nilai di panel dewan juri
 * berhenti menemukan penekan tombolnya, di layar yang justru dibuka saat
 * hasil pertandingan sedang disengketakan. Ia harus disebutkan di sini
 * karena tidak ada yang akan mengingatkan kalau lupa.
 */
return new class extends Migration
{
    private const PENUNJUK = [
        ['judge_verifications', 'score_event_id'],
        ['var_reviews', 'score_event_id'],
        ['judge_inputs', 'score_event_id'],
    ];

    public function up(): void
    {
        KonversiKunciUlid::keUlid('score_events', self::PENUNJUK);
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('score_events', self::PENUNJUK);
    }
};
