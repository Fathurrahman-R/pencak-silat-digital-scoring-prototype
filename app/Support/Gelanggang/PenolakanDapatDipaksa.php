<?php

namespace App\Support\Gelanggang;

use RuntimeException;

/**
 * Penolakan yang boleh ditembus pengendali dengan menyatakannya sekali lagi.
 *
 * Dibedakan dari RuntimeException biasa karena panel harus memutuskan sesuatu
 * dari jenisnya: menawarkan tombol "paksa" atau tidak. Sebelum ini pesannya
 * memang sudah menyebut "pindah paksa", tapi satu-satunya cara panel
 * mengenalinya adalah mencocokkan potongan kalimat -- dan kalimat yang
 * diperbaiki redaksinya akan mematikan tombolnya tanpa ada yang menyadari.
 *
 * Yang TIDAK boleh mewarisi kelas ini: penolakan yang memang mutlak, seperti
 * partai yang dijadwalkan di gelanggang lain. Tombol paksa yang muncul di
 * situ menjanjikan jalan keluar yang tidak ada.
 */
class PenolakanDapatDipaksa extends RuntimeException {}
