<?php

namespace App\Enums;

/**
 * Daur hidup satu kejuaraan.
 *
 * Statusnya menentukan apa yang masih boleh berubah, bukan sekadar label:
 * setelan peraturan dan tarif hanya bisa disunting selama masih Draf, karena
 * mengubah aturan main di tengah kejuaraan berarti mengubah dasar perhitungan
 * partai yang sudah terlanjur dinilai.
 */
enum StatusTurnamen: string
{
    case Draf = 'draf';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Berjalan => 'Berjalan',
            self::Selesai => 'Selesai',
        };
    }

    /** Nama varian <x-si.badge> untuk status ini. */
    public function varian(): string
    {
        return match ($this) {
            self::Draf => 'netral',
            self::Berjalan => 'sukses',
            self::Selesai => 'netral',
        };
    }

    /** Setelan peraturan, kelas, dan tarif hanya boleh diubah selama draf. */
    /**
     * Setelan peraturan masih boleh disunting.
     *
     * Kejuaraan yang SEDANG BERJALAN ikut terbuka, dan itu disengaja. Setelan
     * yang terkunci sejak hari pertama berarti satu-satunya cara menyesuaikan
     * aturan di tengah kejuaraan -- durasi babak yang ternyata terlalu panjang,
     * cakupan teguran yang berbeda dari kebiasaan penyelenggara -- adalah
     * menyunting `config/scoring.php` di tiap laptop gelanggang. Itu lebih
     * berbahaya daripada formulir yang terbuka: perubahannya tidak tercatat,
     * tidak seragam antar mesin, dan ikut mengenai kejuaraan lain di basis data
     * yang sama.
     *
     * Yang tidak ikut berubah adalah partai yang sudah dinilai. Nilai dan
     * hukuman menyimpan angkanya sendiri saat tercatat, jadi setelan baru hanya
     * berlaku untuk yang terjadi sesudahnya.
     *
     * Kejuaraan yang sudah SELESAI tetap terkunci: tidak ada lagi partai
     * berikutnya, jadi satu-satunya akibat menyuntingnya adalah membuat
     * dokumen hasil tidak lagi cocok dengan setelan yang tercatat.
     */
    public function bolehUbahAturan(): bool
    {
        return $this !== self::Selesai;
    }

    /**
     * Tarif pendaftaran masih boleh diubah.
     *
     * Syaratnya LEBIH KETAT daripada setelan peraturan, dan itu disengaja.
     * Setelan peraturan yang diubah di tengah kejuaraan hanya berlaku untuk
     * partai berikutnya; tarif yang diubah sesudah pendaftaran dibuka berarti
     * dua kontingen membayar harga berbeda untuk nomor yang sama, dan yang
     * membayar lebih dulu tidak punya cara mengetahuinya.
     *
     * Berdiri sendiri, bukan menumpang bolehUbahAturan(): keduanya pernah
     * memakai predikat yang sama, dan melonggarkan yang satu diam-diam ikut
     * membuka yang lain.
     */
    public function bolehUbahTarif(): bool
    {
        return $this === self::Draf;
    }

    /**
     * Transisi yang sah dari status ini.
     *
     * Sengaja searah. Menarik kejuaraan yang sudah berjalan kembali ke draf
     * akan membuka kunci setelan peraturan sementara ada partai yang sudah
     * dinilai memakai setelan lama.
     *
     * @return array<int, self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Draf => [self::Berjalan],
            self::Berjalan => [self::Selesai],
            self::Selesai => [],
        };
    }

    public function bisaPindahKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $status): string => $status->label(), self::cases()),
        );
    }
}
