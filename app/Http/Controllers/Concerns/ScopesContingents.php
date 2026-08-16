<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ResourceAction;
use App\Models\Contingent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Membatasi official kontingen pada kontingennya sendiri.
 *
 * Pembedanya adalah hak ubah kontingen: panitia memegangnya dan melihat semua,
 * official tidak memegangnya dan hanya melihat miliknya. Dipakai satu aturan
 * ini di semua tempat, bukan pemeriksaan terpisah per halaman — modul
 * pendaftaran punya banyak pintu (atlet, berkas, pendaftaran nomor, tagihan),
 * dan satu pintu yang lupa dijaga sudah cukup untuk membocorkan data peserta
 * kontingen lain.
 */
trait ScopesContingents
{
    protected function bolehLihatSemuaKontingen(): bool
    {
        return auth()->user()?->can(rk('kontingen', ResourceAction::Update)) ?? false;
    }

    /** @param  Builder<Contingent>  $query */
    protected function scopeKontingen(Builder $query): Builder
    {
        return $this->bolehLihatSemuaKontingen()
            ? $query
            : $query->where('user_id', auth()->id());
    }

    /**
     * Menghentikan akses ke kontingen yang bukan miliknya.
     *
     * Dibalas 404, bukan 403: keberadaan kontingen lain beserta jumlah
     * atletnya bukan hal yang perlu diketahui official kontingen sebelah.
     */
    protected function pastikanBolehAkses(Contingent $contingent): void
    {
        abort_unless(
            $this->bolehLihatSemuaKontingen() || $contingent->user_id === auth()->id(),
            404,
        );
    }
}
