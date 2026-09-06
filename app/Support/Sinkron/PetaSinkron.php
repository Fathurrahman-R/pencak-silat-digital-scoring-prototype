<?php

namespace App\Support\Sinkron;

/**
 * Siapa yang berhak menulis apa, dan lewat mana sebuah baris terhubung ke
 * gelanggangnya.
 *
 * # Kenapa peta ini berdiri sendiri
 *
 * Tiga hal berbeda menanyakan pertanyaan yang sama. Kepemilikan menanyakannya
 * untuk memutuskan baris mana yang boleh diekspor dan mana yang harus ditolak
 * saat diimpor. Pembungkus paket menanyakannya untuk memilih tabel yang
 * dikirim. Penerap paket menanyakannya untuk menerapkan tabel dalam urutan
 * yang tidak melanggar foreign key. Tiga jawaban yang harus selalu sama, di
 * tiga berkas, adalah tiga jawaban yang suatu saat berbeda.
 *
 * # Tiga golongan
 *
 * GLOBAL -- ditulis hanya oleh node global. Atlet, kontingen, bagan, jadwal,
 * pengguna, peran. Node gelanggang menerimanya read-only. Aturan satu penulis
 * inilah yang menggantikan resolusi konflik: tidak ada dua salinan yang bisa
 * bertengkar kalau cuma satu yang boleh mengubah.
 *
 * PENGHUBUNG -- baris kejuaraan yang MENUNJUK gelanggang lewat `arena_id`.
 * Dibuat node global (generator bagan dan penjadwal), lalu diperbarui node
 * gelanggang selama partai berjalan: status, babak berjalan, pemenang,
 * pengesahan. Satu-satunya golongan yang ditulis dua pihak, dan itu aman
 * karena keduanya menyentuh kolom yang berbeda -- node gelanggang tidak
 * pernah menyisipkan baris baru di sini.
 *
 * LOKAL -- lahir di gelanggang selama partai berlangsung. Tiap barisnya milik
 * satu gelanggang saja, ditentukan oleh partai atau penampilan induknya.
 *
 * # judge_inputs sengaja tidak ada di daftar mana pun
 *
 * Ia tidak ikut sinkron peer-to-peer sama sekali. Gelanggang tetangga tidak
 * punya kepentingan atas penekanan tombol mentah gelanggang lain, dan tabel
 * itu sendirian menyumbang sekitar sembilan puluh persen volume seluruh
 * basis data. Ia mengalir satu arah saja -- gelanggang ke node global, lewat
 * paket arsip bukti, bukan lewat sinkron.
 */
class PetaSinkron
{
    /**
     * Tabel yang hanya boleh ditulis node global, dalam urutan aman diterapkan.
     *
     * Urutannya bukan selera: penerap paket menuliskannya berurutan, dan
     * foreign key menolak baris yang menunjuk sesuatu yang belum ada. Yang
     * ditunjuk selalu lebih dulu daripada yang menunjuk.
     *
     * @var list<string>
     */
    public const GLOBAL = [
        'users',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'resources',
        'resource_permissions',
        'tournaments',
        'tournament_rule_settings',
        'arenas',
        'arena_operators',
        'arena_pengendali',
        'arena_officials',
        'weight_classes',
        'jurus_events',
        'contingents',
        'athletes',
        'registrations',
        'registration_athlete',
        'registration_documents',
        'weight_ins',
        'fee_schedules',
        'invoices',
        'invoice_items',
        'manual_payments',
        'brackets',
        'bracket_slots',
        'jurus_brackets',
        'jurus_bracket_slots',
    ];

    /**
     * Tabel kejuaraan yang menunjuk gelanggang. Disisipkan node global,
     * diperbarui node gelanggang yang memegang arenanya.
     *
     * @var list<string>
     */
    public const PENGHUBUNG = [
        'matches',
        'jurus_performances',
        'jurus_battles',
    ];

    /**
     * Tabel yang lahir di gelanggang, dipetakan ke cara menemukan arenanya.
     *
     * Nilainya adalah rantai relasi menuju kolom `arena_id`, ditulis sebagai
     * daftar [tabel_tujuan, kolom_penunjuk]. Rantai, bukan satu langkah,
     * karena sebagian baris baru bertemu gelanggangnya setelah dua lompatan:
     * jawaban verifikasi menempel ke verifikasinya, verifikasinya menempel ke
     * partai, dan partai itulah yang menyebut gelanggang.
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    public const LOKAL = [
        'score_events' => [['matches', 'match_id']],
        'penalties' => [['matches', 'match_id']],
        'technical_counts' => [['matches', 'match_id']],
        'match_rounds' => [['matches', 'match_id']],
        'match_round_reopens' => [['matches', 'match_id']],
        'match_officials' => [['matches', 'match_id']],
        'protest_cards' => [['matches', 'match_id']],
        'var_reviews' => [['matches', 'match_id']],
        'manager_protests' => [['matches', 'match_id']],
        'judge_verifications' => [['matches', 'match_id']],
        'judge_verification_answers' => [
            ['judge_verifications', 'judge_verification_id'],
            ['matches', 'match_id'],
        ],
        'jurus_scores' => [['jurus_performances', 'performance_id']],
        'jurus_deductions' => [['jurus_performances', 'performance_id']],
    ];

    /**
     * Tabel yang punya model Eloquent, dipetakan ke kelasnya.
     *
     * Dipakai memasang SinkronObserver. Tidak semua tabel yang disinkronkan
     * ada di sini: tabel pivot murni (registration_athlete, model_has_roles,
     * dan kerabatnya) tidak punya model, jadi perubahannya tidak tertangkap
     * observer. Itu keterbatasan yang disengaja untuk saat ini -- pivot hanya
     * berubah saat sekretariat menyunting pendaftaran dan peran, yaitu di node
     * global sebelum hari-H, dan ia ikut terbawa pada penarikan penuh pertama.
     * Yang belum tertangani adalah perubahan pivot SETELAH penarikan pertama.
     *
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    public const MODEL = [
        // Global
        'users' => \App\Models\User::class,
        'roles' => \App\Models\Role::class,
        'permissions' => \App\Models\Permission::class,
        'resources' => \App\Models\Resource::class,
        'resource_permissions' => \App\Models\ResourcePermission::class,
        'tournaments' => \App\Models\Tournament::class,
        'tournament_rule_settings' => \App\Models\TournamentRuleSetting::class,
        'arenas' => \App\Models\Arena::class,
        'arena_officials' => \App\Models\ArenaOfficial::class,
        'weight_classes' => \App\Models\WeightClass::class,
        'jurus_events' => \App\Models\JurusEvent::class,
        'contingents' => \App\Models\Contingent::class,
        'athletes' => \App\Models\Athlete::class,
        'registrations' => \App\Models\Registration::class,
        'registration_documents' => \App\Models\RegistrationDocument::class,
        'weight_ins' => \App\Models\WeightIn::class,
        'fee_schedules' => \App\Models\FeeSchedule::class,
        'invoices' => \App\Models\Invoice::class,
        'invoice_items' => \App\Models\InvoiceItem::class,
        'manual_payments' => \App\Models\ManualPayment::class,
        'brackets' => \App\Models\Bracket::class,
        'bracket_slots' => \App\Models\BracketSlot::class,
        'jurus_brackets' => \App\Models\JurusBracket::class,
        'jurus_bracket_slots' => \App\Models\JurusBracketSlot::class,

        // Penghubung
        'matches' => \App\Models\SilatMatch::class,
        'jurus_performances' => \App\Models\JurusPerformance::class,
        'jurus_battles' => \App\Models\JurusBattle::class,

        // Lokal
        'score_events' => \App\Models\ScoreEvent::class,
        'penalties' => \App\Models\Penalty::class,
        'technical_counts' => \App\Models\TechnicalCount::class,
        'match_rounds' => \App\Models\MatchRound::class,
        'match_round_reopens' => \App\Models\MatchRoundReopen::class,
        'match_officials' => \App\Models\MatchOfficial::class,
        'protest_cards' => \App\Models\ProtestCard::class,
        'var_reviews' => \App\Models\VarReview::class,
        'manager_protests' => \App\Models\ManagerProtest::class,
        'judge_verifications' => \App\Models\JudgeVerification::class,
        'judge_verification_answers' => \App\Models\JudgeVerificationAnswer::class,
        'jurus_scores' => \App\Models\JurusScore::class,
        'jurus_deductions' => \App\Models\JurusDeduction::class,
    ];

    /**
     * Tabel yang ikut paket sinkron, dalam urutan aman diterapkan.
     *
     * @return list<string>
     */
    public static function urutanTerapkan(): array
    {
        return array_merge(self::GLOBAL, self::PENGHUBUNG, array_keys(self::LOKAL));
    }

    /** Apakah tabel ini ikut disinkronkan sama sekali. */
    public static function disinkronkan(string $tabel): bool
    {
        return in_array($tabel, self::urutanTerapkan(), true);
    }
}
