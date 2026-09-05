<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `judge_verifications` pindah ke ULID, beserta kolom jawabannya.
 *
 * Tabel kesepuluh. `judge_verification_answers.judge_verification_id` ikut,
 * dan pasangan itu tidak boleh putus sedetik pun: jawaban yang kehilangan
 * verifikasinya adalah suara juri yang tercatat tapi tidak lagi terhitung,
 * pada polling yang justru diadakan karena kejadiannya diragukan.
 *
 * Unique(judge_verification_id, judge_user_id) di tabel jawaban ikut
 * dipulihkan helper setelah kolomnya ditukar. Unique itu yang menjaga satu
 * juri tidak menjawab dua kali untuk polling yang sama.
 */
return new class extends Migration
{
    private const PENUNJUK = [
        ['judge_verification_answers', 'judge_verification_id'],
    ];

    public function up(): void
    {
        KonversiKunciUlid::keUlid('judge_verifications', self::PENUNJUK);
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('judge_verifications', self::PENUNJUK);
    }
};
