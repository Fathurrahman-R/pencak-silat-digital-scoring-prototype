<?php

namespace App\Support\Pendaftaran;

use RuntimeException;

/**
 * Pendaftaran yang tidak lolos syarat naskah 2025.
 *
 * Sengaja bukan ValidationException: pemanggilnya yang memutuskan apakah ini
 * berarti formulir ditolak (formulir pendaftaran nomor) atau sekadar catatan
 * bahwa bagian opsionalnya dilewati (atlet baru yang sekalian didaftarkan).
 */
class PendaftaranDitolak extends RuntimeException
{
    /** @param  list<string>  $alasan */
    public function __construct(public readonly array $alasan)
    {
        parent::__construct(implode(' ', $alasan));
    }
}
