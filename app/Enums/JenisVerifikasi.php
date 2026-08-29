<?php

namespace App\Enums;

/**
 * Apa yang ditanyakan Wasit atau Ketua Pertandingan kepada juri.
 *
 * Pasal 13 menugaskan Juri "memberi jawaban tentang verifikasi dari Ketua
 * Pertandingan maupun Wasit", tapi tidak merinci pertanyaan apa saja yang
 * boleh diajukan. Dua ini dipilih karena keduanya yang benar-benar terjadi di
 * gelanggang: wasit ragu sudut mana yang menjatuhkan, atau ragu sudut mana
 * yang melanggar. Keduanya berakibat langsung pada angka, jadi keduanya perlu
 * jawaban yang tercatat.
 *
 * Pertanyaan lain tidak ditambahkan sampai ada yang benar-benar dibutuhkan
 * gelanggang -- pilihan yang tidak pernah dipakai hanya memperlambat wasit
 * yang sedang menghentikan pertandingan.
 */
enum JenisVerifikasi: string
{
    case Jatuhan = 'jatuhan';
    case Pelanggaran = 'pelanggaran';

    /** Kalimat yang dibaca juri di panelnya. Berupa pertanyaan utuh, bukan label. */
    public function pertanyaan(): string
    {
        return match ($this) {
            self::Jatuhan => 'Sudut mana yang menjatuhkan?',
            self::Pelanggaran => 'Sudut mana yang melanggar?',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Jatuhan => 'Jatuhan',
            self::Pelanggaran => 'Pelanggaran',
        };
    }

    /**
     * Bunyi pilihan "tidak ada" untuk pertanyaan ini.
     *
     * Ditulis lengkap, bukan cuma "Tidak ada". Juri menekan tombol ini
     * justru saat ia yakin -- yakin tidak ada jatuhan yang sah, atau yakin
     * tidak ada yang melanggar -- dan kata "tidak ada" sendirian terbaca
     * seperti "saya tidak tahu".
     */
    public function pilihanTidakAda(): string
    {
        return match ($this) {
            self::Jatuhan => 'Tidak ada yang menjatuhkan',
            self::Pelanggaran => 'Tidak ada yang melanggar',
        };
    }
}
