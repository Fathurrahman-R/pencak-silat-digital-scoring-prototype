<?php

namespace App\Observers;

use App\Support\Sinkron\CatatanKeluar;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat tiap perubahan yang layak dikirim ke gelanggang lain.
 *
 * Dipasang lewat observer, bukan lewat panggilan di tiap penulis, dengan
 * alasan yang sama seperti SnapshotSkorObserver: jalur yang mengubah data ada
 * banyak, dan yang lupa mencatat tidak akan terlihat sebagai galat. Yang
 * terlihat cuma satu nilai yang tidak pernah sampai ke node lain, ditemukan
 * saat rekap medali disusun dan angkanya tidak cocok.
 *
 * `saved` menangkap penyisipan dan pembaruan sekaligus; keduanya dicatat
 * sebagai `simpan` karena penerap paket memakai upsert dan tidak peduli
 * bedanya. Yang perlu dibedakan cuma penghapusan.
 */
class SinkronObserver
{
    public function __construct(private readonly CatatanKeluar $catatan) {}

    public function saved(Model $model): void
    {
        $this->catatan->catat($model, CatatanKeluar::SIMPAN);
    }

    public function deleted(Model $model): void
    {
        $this->catatan->catat($model, CatatanKeluar::HAPUS);
    }
}
