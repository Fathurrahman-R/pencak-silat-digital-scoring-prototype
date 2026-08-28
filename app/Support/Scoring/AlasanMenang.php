<?php

namespace App\Support\Scoring;

/**
 * Satu-satunya tempat `win_reason` diterjemahkan jadi kalimat yang dibaca orang.
 *
 * Berdiri sebagai kelas sendiri karena nilai mentahnya menipu kalau ditampilkan
 * apa adanya: `diskualifikasi` yang tersimpan di partai berarti "pihak ini MENANG
 * karena lawannya didiskualifikasi", bukan "pihak ini didiskualifikasi". Halaman
 * live publik dulu merendernya mentah tepat di sebelah nama pemenang, sehingga
 * penonton membaca kebalikan dari kenyataan — juara tampak seperti yang dihukum.
 *
 * Peta yang sama sebelumnya hidup sebagai array lokal di overlay/result.blade.php.
 * Menyalinnya ke tiap layar berarti cepat atau lambat ada satu layar yang tertinggal;
 * itulah yang sudah terjadi sekali.
 */
final class AlasanMenang
{
    /** @var array<string, string> */
    private const LABEL = [
        'angka' => 'Menang Angka',
        'teknik' => 'Menang Teknik',
        'mutlak' => 'Menang Mutlak',
        'wmp' => 'Menang WMP',
        'undur_diri' => 'Menang Undur Diri',
        'cedera' => 'Menang (Cedera)',
        'wo' => 'Menang WO',
        'diskualifikasi' => 'Menang Diskualifikasi',
    ];

    /** @return array<string, string> */
    public static function peta(): array
    {
        return self::LABEL;
    }

    /**
     * Alasan yang belum dikenal dikembalikan apa adanya, bukan diganti tanda hubung:
     * lebih baik panitia melihat kata asing yang bisa dilaporkan daripada layar
     * yang diam-diam menyembunyikan sebab kemenangan.
     */
    public static function label(?string $alasan): ?string
    {
        if ($alasan === null || $alasan === '') {
            return null;
        }

        return self::LABEL[$alasan] ?? $alasan;
    }
}
