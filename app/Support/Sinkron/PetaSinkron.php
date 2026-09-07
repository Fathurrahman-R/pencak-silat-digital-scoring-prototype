<?php

namespace App\Support\Sinkron;

use App\Models\AdopsiJadwal;
use App\Models\Arena;
use App\Models\ArenaOfficial;
use App\Models\ArenaTayang;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Contingent;
use App\Models\FeeSchedule;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JudgeVerification;
use App\Models\JudgeVerificationAnswer;
use App\Models\JurusBattle;
use App\Models\JurusBracket;
use App\Models\JurusBracketSlot;
use App\Models\JurusDeduction;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\JurusScore;
use App\Models\ManagerProtest;
use App\Models\ManualPayment;
use App\Models\MatchOfficial;
use App\Models\MatchRound;
use App\Models\MatchRoundReopen;
use App\Models\Penalty;
use App\Models\Permission;
use App\Models\ProtestCard;
use App\Models\Registration;
use App\Models\RegistrationDocument;
use App\Models\ResourcePermission;
use App\Models\Role;
use App\Models\ScoreEvent;
use App\Models\SerahJadwal;
use App\Models\SilatMatch;
use App\Models\TechnicalCount;
use App\Models\Tournament;
use App\Models\TournamentRuleSetting;
use App\Models\User;
use App\Models\VarReview;
use App\Models\WeightClass;
use App\Models\WeightIn;
use Illuminate\Database\Eloquent\Model;

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
        /*
         * Rantai KOSONG: `arena_id` ada di baris ini sendiri, tidak perlu
         * dilompati ke tabel lain lebih dulu.
         *
         * Inilah tabel yang membuat pointer tayang berhenti jadi kolom di
         * `arenas` -- tabel GLOBAL yang penerapan paketnya menimpa seluruh
         * kolom, dan karena itu ikut menghapus partai aktif gelanggang yang
         * sedang bertanding tiap kali node global menyunting gelanggang apa
         * pun. Lihat migrasi `buat_tabel_arena_tayang`.
         */
        'arena_tayang' => [],

        /*
         * Serah-terima jadwal antar gelanggang, dua setengah-catatan.
         *
         * `serah_jadwal.arena_id` adalah gelanggang PELEPAS dan
         * `adopsi_jadwal.arena_id` gelanggang PENGADOPSI -- masing-masing
         * ditulis pemiliknya sendiri, jadi tidak ada satu baris pun yang
         * pernah ditulis dua node. Itulah yang membuat pemindahan antar
         * gelanggang tidak melanggar aturan satu penulis.
         */
        'serah_jadwal' => [],
        'adopsi_jadwal' => [],

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
     * @var array<string, class-string<Model>>
     */
    public const MODEL = [
        // Global
        'users' => User::class,
        'roles' => Role::class,
        'permissions' => Permission::class,
        'resources' => \App\Models\Resource::class,
        'resource_permissions' => ResourcePermission::class,
        'tournaments' => Tournament::class,
        'tournament_rule_settings' => TournamentRuleSetting::class,
        'arenas' => Arena::class,
        'arena_tayang' => ArenaTayang::class,
        'serah_jadwal' => SerahJadwal::class,
        'adopsi_jadwal' => AdopsiJadwal::class,
        'arena_officials' => ArenaOfficial::class,
        'weight_classes' => WeightClass::class,
        'jurus_events' => JurusEvent::class,
        'contingents' => Contingent::class,
        'athletes' => Athlete::class,
        'registrations' => Registration::class,
        'registration_documents' => RegistrationDocument::class,
        'weight_ins' => WeightIn::class,
        'fee_schedules' => FeeSchedule::class,
        'invoices' => Invoice::class,
        'invoice_items' => InvoiceItem::class,
        'manual_payments' => ManualPayment::class,
        'brackets' => Bracket::class,
        'bracket_slots' => BracketSlot::class,
        'jurus_brackets' => JurusBracket::class,
        'jurus_bracket_slots' => JurusBracketSlot::class,

        // Penghubung
        'matches' => SilatMatch::class,
        'jurus_performances' => JurusPerformance::class,
        'jurus_battles' => JurusBattle::class,

        // Lokal
        'score_events' => ScoreEvent::class,
        'penalties' => Penalty::class,
        'technical_counts' => TechnicalCount::class,
        'match_rounds' => MatchRound::class,
        'match_round_reopens' => MatchRoundReopen::class,
        'match_officials' => MatchOfficial::class,
        'protest_cards' => ProtestCard::class,
        'var_reviews' => VarReview::class,
        'manager_protests' => ManagerProtest::class,
        'judge_verifications' => JudgeVerification::class,
        'judge_verification_answers' => JudgeVerificationAnswer::class,
        'jurus_scores' => JurusScore::class,
        'jurus_deductions' => JurusDeduction::class,
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
