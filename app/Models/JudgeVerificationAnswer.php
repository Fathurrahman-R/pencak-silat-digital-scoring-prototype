<?php

namespace App\Models;

use App\Enums\JawabanVerifikasi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban satu juri atas satu verifikasi.
 *
 * Tidak pernah disunting maupun dihapus. Juri yang salah tekan tidak bisa
 * meralat sendiri -- yang bisa dilakukan Wasit atau Ketua Pertandingan adalah
 * membatalkan seluruh verifikasi dan mengulanginya, sehingga jejak ralatnya
 * ikut tercatat.
 */
class JudgeVerificationAnswer extends Model
{
    use HasFactory;

    /** Sama dengan JudgeInput: urutan jawaban dibedakan sampai milidetik. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    public $timestamps = true;

    protected $fillable = [
        'judge_verification_id',
        'judge_user_id',
        'judge_number',
        'jawaban',
        'server_ts',
    ];

    protected function casts(): array
    {
        return [
            'judge_number' => 'integer',
            'jawaban' => JawabanVerifikasi::class,
            'server_ts' => 'datetime',
        ];
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(JudgeVerification::class, 'judge_verification_id');
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_user_id');
    }

    public function sebutan(): string
    {
        return $this->judge_number ? "Juri {$this->judge_number}" : 'Juri';
    }
}
