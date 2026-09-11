<?php

namespace App\Listeners;

use App\Support\Sinkron\CatatanKeluar;
use App\Support\Sinkron\PetaSinkron;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Mencatat perubahan pivot peran supaya ikut berpindah antar node.
 *
 * `model_has_roles`, `model_has_permissions`, dan `role_has_permissions`
 * adalah tabel pivot murni: tidak punya model Eloquent, jadi SinkronObserver
 * tidak pernah menyentuhnya. Akibatnya terbaca seperti kesalahan izin, bukan
 * kesalahan sinkron -- akun tiba di node gelanggang lengkap dengan nama dan
 * kata sandinya, tapi tanpa satu pun peran, lalu tiap panel membalas "Akses
 * ditolak" dan yang membacanya mencari kesalahan di daftar peran.
 *
 * Spatie menembakkan event saat peran dipasang dan dilepas; di sinilah
 * keduanya jadi catatan sinkron. Penyemaian (`silat:sinkron-semai`) membawa
 * keadaan yang sudah ada; listener ini menjaga perubahan SESUDAHNYA tidak
 * tertinggal.
 */
class CatatPivotPeran
{
    public function __construct(private readonly CatatanKeluar $catatan) {}

    public function handleRoleAttached(RoleAttachedEvent $peristiwa): void
    {
        $this->catatPivot('model_has_roles', $peristiwa->model, 'role_id', $peristiwa->rolesOrIds);
    }

    public function handleRoleDetached(RoleDetachedEvent $peristiwa): void
    {
        $this->catatPivot('model_has_roles', $peristiwa->model, 'role_id', $peristiwa->rolesOrIds);
    }

    public function handlePermissionAttached(PermissionAttachedEvent $peristiwa): void
    {
        $this->catatIzin($peristiwa->model, $peristiwa->permissionsOrIds);
    }

    public function handlePermissionDetached(PermissionDetachedEvent $peristiwa): void
    {
        $this->catatIzin($peristiwa->model, $peristiwa->permissionsOrIds);
    }

    /**
     * Izin yang dipasang ke PERAN disimpan di tabel yang berbeda.
     *
     * Spatie memakai `role_has_permissions` (permission_id + role_id) untuk
     * peran, dan `model_has_permissions` (permission_id + model_type +
     * model_id) untuk pengguna. Event-nya satu dan sama, jadi yang membedakan
     * cuma jenis modelnya -- menulis keduanya ke tabel yang sama menghasilkan
     * baris yang menunjuk ke mana-mana, dan galatnya baru muncul di node
     * penerima.
     *
     * @param  mixed  $permissionsOrIds
     */
    private function catatIzin(Model $model, $permissionsOrIds): void
    {
        if ($model instanceof SpatieRole) {
            foreach ($this->idDari($permissionsOrIds) as $id) {
                $this->catatan->catatPenanda('role_has_permissions', PetaSinkron::penandaBaris(
                    'role_has_permissions',
                    ['permission_id' => $id, 'role_id' => (string) $model->getKey()],
                ));
            }

            return;
        }

        $this->catatPivot('model_has_permissions', $model, 'permission_id', $permissionsOrIds);
    }

    /**
     * Mencatat SELURUH baris pivot model itu, bukan cuma yang disebut event.
     *
     * Event pelepasan menyebut peran yang baru saja dilepas, dan baris
     * pivotnya sudah tidak ada saat listener ini berjalan. Mencatat baris yang
     * masih ada DITAMBAH yang disebut event membuat kedua arah tertangani:
     * yang masih ada terkirim sebagai simpan, yang sudah hilang terkirim
     * sebagai hapus -- PembungkusPaket membaca barisnya segar dan mengubah
     * sendiri aksinya saat barisnya tidak ditemukan.
     *
     * @param  mixed  $rolesOrIds
     */
    private function catatPivot(string $tabel, Model $model, string $kolom, $rolesOrIds): void
    {
        $penanda = [];

        $adaSekarang = DB::table($tabel)
            ->where('model_type', $model::class)
            ->where('model_id', $model->getKey())
            ->get();

        foreach ($adaSekarang as $baris) {
            $penanda[] = PetaSinkron::penandaBaris($tabel, (array) $baris);
        }

        foreach ($this->idDari($rolesOrIds) as $id) {
            $penanda[] = PetaSinkron::penandaBaris($tabel, [
                $kolom => $id,
                'model_type' => $model::class,
                'model_id' => (string) $model->getKey(),
            ]);
        }

        foreach (array_unique($penanda) as $satu) {
            $this->catatan->catatPenanda($tabel, $satu);
        }
    }

    /**
     * Id dari muatan event, yang bentuknya bisa bermacam-macam.
     *
     * Spatie sendiri memperingatkan ini di docblock event-nya: trait-nya
     * meneruskan array id, tapi pemanggil lain bisa meneruskan model atau
     * koleksi. Yang bukan id diabaikan -- baris pivot yang bersangkutan toh
     * sudah ikut terjaring lewat pembacaan tabel di atas.
     *
     * @param  mixed  $rolesOrIds
     * @return list<string>
     */
    private function idDari($rolesOrIds): array
    {
        $hasil = [];

        foreach ((is_iterable($rolesOrIds) ? $rolesOrIds : [$rolesOrIds]) as $satu) {
            if (is_numeric($satu) || is_string($satu)) {
                $hasil[] = (string) $satu;

                continue;
            }

            if ($satu instanceof Model) {
                $hasil[] = (string) $satu->getKey();
            }
        }

        return $hasil;
    }
}
