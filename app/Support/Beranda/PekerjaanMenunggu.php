<?php

namespace App\Support\Beranda;

use App\Enums\ResourceAction;
use App\Enums\StatusInvoice;
use App\Enums\StatusPendaftaran;
use App\Models\Invoice;
use App\Models\Registration;
use App\Models\ResourcePermission;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\WeightClass;

/**
 * Pekerjaan yang menunggu dikerjakan panitia, untuk beranda.
 *
 * Menggantikan empat ubin angka — Kontingen, Pendaftaran terverifikasi, Partai
 * hari ini, Menunggu verifikasi. Ubin angka menjawab "berapa", padahal
 * pertanyaan yang dibawa panitia ke layar depan adalah "apa yang harus saya
 * kerjakan sekarang". Angka 4 di ubin "Menunggu verifikasi" tidak memberi tahu
 * bahwa empat pendaftaran itu menahan penyusunan bagan, dan tidak
 * mengantarkan siapa pun ke layarnya.
 *
 * Tiap baris menyebut TIGA hal: berapa banyak, kenapa mendesak, dan ke mana
 * pergi. Yang jumlahnya nol tidak muncul sama sekali — daftar yang penuh
 * baris bernilai nol menyembunyikan yang benar-benar menunggu.
 *
 * Baris disaring resource key, jadi Sekretariat melihat tagihan dan timbangan
 * sedangkan Ketua Pertandingan tidak melihat keduanya. Satu layar, isi menyesuaikan
 * izin — bukan beranda berbeda per role, yang berarti tiap kebutuhan baru
 * harus ditambahkan di beberapa tempat.
 */
class PekerjaanMenunggu
{
    /** @return array<int, array<string, mixed>> */
    public function untuk(?Tournament $turnamen): array
    {
        $baris = array_filter([
            ...($turnamen ? $this->dalamKejuaraan($turnamen) : []),
            $this->keyBelumDipetakan(),
        ]);

        return array_values($baris);
    }

    /** @return array<int, array<string, mixed>|null> */
    private function dalamKejuaraan(Tournament $turnamen): array
    {
        $idKelas = $turnamen->weightClasses()->select('id');

        return [
            $this->verifikasi($turnamen, $idKelas),
            $this->timbangBadan($turnamen, $idKelas),
            $this->tagihan($turnamen),
            $this->bagan($turnamen),
            $this->jadwal($turnamen, $idKelas),
            $this->pengesahan($turnamen, $idKelas),
        ];
    }

    /** @return array<string, mixed>|null */
    private function verifikasi(Tournament $turnamen, $idKelas): ?array
    {
        if (! resource_allows(rk('pendaftaran', ResourceAction::View))) {
            return null;
        }

        $jumlah = Registration::whereIn('weight_class_id', $idKelas)
            ->where('status', StatusPendaftaran::Diajukan)
            ->count();

        return $this->baris(
            $jumlah,
            'pendaftaran menunggu diverifikasi',
            'Bagan tidak bisa disusun sebelum pesertanya disahkan.',
            route('admin.turnamen.verifikasi.index', $turnamen),
            'user-check',
        );
    }

    /** @return array<string, mixed>|null */
    private function timbangBadan(Tournament $turnamen, $idKelas): ?array
    {
        if (! resource_allows(rk('timbang-badan', ResourceAction::View))) {
            return null;
        }

        /*
         * Yang dihitung: pendaftaran yang SUDAH terverifikasi tapi belum punya
         * catatan timbang. Yang belum terverifikasi tidak ikut — menimbang
         * orang yang pendaftarannya belum sah hanya menghasilkan catatan yang
         * harus dibuang lagi.
         */
        $jumlah = Registration::whereIn('weight_class_id', $idKelas)
            ->where('status', StatusPendaftaran::Terverifikasi)
            ->whereDoesntHave('weightIns')
            ->count();

        return $this->baris(
            $jumlah,
            'pesilat belum ditimbang',
            'Hasil timbang di venue yang menentukan kelasnya, bukan berat yang diakui saat mendaftar.',
            route('admin.turnamen.timbang.index', $turnamen),
            'scale',
        );
    }

    /** @return array<string, mixed>|null */
    private function tagihan(Tournament $turnamen): ?array
    {
        if (! resource_allows(rk('invoice', ResourceAction::View))) {
            return null;
        }

        /*
         * Tagihan menempel ke KONTINGEN, bukan ke kejuaraan: satu kontingen
         * satu tagihan, dan kontingennya yang tahu ia ikut kejuaraan mana.
         */
        $jumlah = Invoice::whereHas('contingent', fn ($q) => $q->where('tournament_id', $turnamen->id))
            ->where('status', '!=', StatusInvoice::Lunas)
            ->count();

        return $this->baris(
            $jumlah,
            'tagihan kontingen belum lunas',
            'Pendaftaran kontingen terkunci sampai tagihannya lunas.',
            route('admin.turnamen.bendahara.index', $turnamen),
            'wallet',
        );
    }

    /** @return array<string, mixed>|null */
    private function bagan(Tournament $turnamen): ?array
    {
        if (! resource_allows(rk('bagan', ResourceAction::View))) {
            return null;
        }

        /*
         * Kelas yang pesertanya sudah cukup tapi bagannya belum ada. Dua
         * peserta adalah batas terkecil yang masih menghasilkan satu partai;
         * di bawah itu tidak ada yang bisa disusun.
         *
         * Dihitung lewat pendaftaran, bukan lewat relasi di WeightClass --
         * kelas tidak punya relasi ke pendaftarannya, dan menambahkannya hanya
         * demi hitungan di beranda berarti satu relasi yang harus dijaga tanpa
         * pemakai lain.
         */
        $kelasSiap = Registration::whereIn('weight_class_id', $turnamen->weightClasses()->select('id'))
            ->where('status', StatusPendaftaran::Terverifikasi)
            ->groupBy('weight_class_id')
            ->havingRaw('COUNT(*) >= 2')
            ->pluck('weight_class_id');

        $jumlah = WeightClass::whereIn('id', $kelasSiap)
            ->whereDoesntHave('bracket')
            ->count();

        return $this->baris(
            $jumlah,
            'kelas siap disusun bagannya',
            'Pesertanya sudah disahkan dan cukup untuk dipertandingkan.',
            route('admin.turnamen.bagan.index', $turnamen),
            'network',
        );
    }

    /** @return array<string, mixed>|null */
    private function jadwal(Tournament $turnamen, $idKelas): ?array
    {
        if (! resource_allows(rk('jadwal', ResourceAction::View))) {
            return null;
        }

        /*
         * Hanya partai yang KEDUA sudutnya sudah pasti. Partai yang masih
         * menunggu pemenang babak sebelumnya memang belum bisa dijadwalkan,
         * dan menghitungnya di sini membuat angkanya tidak pernah turun ke
         * nol.
         */
        $jumlah = SilatMatch::whereHas('bracket', fn ($q) => $q->whereIn('weight_class_id', $idKelas))
            ->whereNotNull('red_registration_id')
            ->whereNotNull('blue_registration_id')
            ->whereNull('arena_id')
            ->count();

        return $this->baris(
            $jumlah,
            'partai belum dijadwalkan',
            'Kedua sudutnya sudah pasti, jadi ia sudah bisa ditempatkan di gelanggang.',
            route('admin.turnamen.jadwal.index', $turnamen),
            'calendar-days',
        );
    }

    /** @return array<string, mixed>|null */
    private function pengesahan(Tournament $turnamen, $idKelas): ?array
    {
        if (! resource_allows(rk('hasil-partai', ResourceAction::Approve))) {
            return null;
        }

        $jumlah = SilatMatch::whereHas('bracket', fn ($q) => $q->whereIn('weight_class_id', $idKelas))
            ->where('status', SilatMatch::STATUS_SELESAI)
            ->whereNull('ratified_at')
            ->count();

        return $this->baris(
            $jumlah,
            'hasil partai menunggu disahkan',
            'Sebelum disahkan, pemenangnya belum masuk bagan dan babak berikutnya tertahan.',
            route('admin.turnamen.rekap.index', $turnamen),
            'stamp',
        );
    }

    /** @return array<string, mixed>|null */
    private function keyBelumDipetakan(): ?array
    {
        if (! resource_allows(rk('mappings', ResourceAction::View))) {
            return null;
        }

        return $this->baris(
            ResourcePermission::whereNull('permission_id')->count(),
            'resource key belum dipetakan',
            'Pintu yang dijaganya tertutup untuk semua orang kecuali super admin.',
            route('admin.mappings.index'),
            'link',
        );
    }

    /**
     * Baris yang jumlahnya nol tidak pernah muncul.
     *
     * @return array<string, mixed>|null
     */
    private function baris(int $jumlah, string $benda, string $sebab, string $tautan, string $ikon): ?array
    {
        if ($jumlah === 0) {
            return null;
        }

        return [
            'jumlah' => $jumlah,
            'benda' => $benda,
            'sebab' => $sebab,
            'tautan' => $tautan,
            'ikon' => $ikon,
        ];
    }
}
