# Panduan Operasional — Hari-H

> Untuk panitia. Langkah teknis instalasi ada di `docs/INSTALASI-LAN.md`; konfigurasi tunnel ada di `docs/TUNNELING.md`. Dokumen ini adalah urutan langkah dan siapa mengerjakan apa.

## H-1: Persiapan

1. **Ketua Tim Teknologi Informasi** memastikan server sudah terpasang (`docs/INSTALASI-LAN.md`) dan diuji dari HP di jaringan venue, bukan cuma dari mesin server.
2. **Operator IT** membuat akun juri massal per gelanggang lewat panel **Manajemen Akses → Pengguna**, kredensial pendek dan mudah diketik di HP (FR-A-04).
3. Pastikan bagan sudah **dikunci** (menu Bagan → Kunci) untuk setiap kelas yang akan bertanding -- setelah dikunci, penyusunan ulang wajib beralasan dan tercatat di jejak audit.
4. Pastikan jadwal partai sudah ditetapkan ke tiap gelanggang (menu Jadwal).
5. Cetak atau siapkan daftar kredensial juri per gelanggang untuk dibagikan pagi hari-H.

## Pagi hari-H: nyalakan sistem

1. Nyalakan empat proses server (lihat `docs/INSTALASI-LAN.md` §6): `serve`, `reverb:start`, `queue:listen`, dan proxy tunnel kalau live score publik dipakai.
2. **Operator IT** tiap gelanggang membuka panel Operator di laptop gelanggangnya masing-masing (`/admin/turnamen/{id}/partai/{match}/operator` untuk partai pertama).
3. Juri membuka panel juri di HP masing-masing lewat URL yang dibagikan, login, dan menambahkan ke layar utama (PWA) supaya tidak perlu buka browser lagi tiap partai.
4. Uji satu nilai percobaan sebelum partai pertama sungguhan dimulai -- indikator koneksi harus hijau di seluruh perangkat.

## Alur satu partai Tanding

| Langkah | Siapa | Di mana |
|---|---|---|
| Pilih partai aktif, mulai babak | Operator IT | Panel Operator |
| Menjatuhkan pembinaan/teguran/peringatan, hitungan teknik | Wasit | Panel Wasit (atau Operator IT atas instruksi wasit) |
| Menilai serangan yang masuk | Juri 1–3 | PWA Juri |
| Mengakhiri partai (KO, WMP, mutlak, dst.) | Operator IT | Panel Operator |
| Meninjau riwayat, membatalkan nilai/hukuman keliru, **mengesahkan hasil** | Dewan Juri | Panel Dewan Juri |
| Mencetak berita acara | Ketua Pertandingan / Dewan Juri | Tombol "Berita acara (PDF)" di Panel Dewan Juri |

**Hasil partai belum final sebelum disahkan dewan juri.** `winner_registration_id` yang muncul sebelum pengesahan bersifat sementara dan masih bisa dikoreksi.

## Alur satu penampilan Jurus

| Langkah | Siapa | Di mana |
|---|---|---|
| Buat penampilan dari pendaftaran terverifikasi | Operator IT | Menu Kategori Jurus → pilih nomor → Buat penampilan |
| Mulai/hentikan timer penampilan | Operator IT | Panel Operator Jurus |
| Memberi nilai 9.00–10.00, mencatat pengurangan 0.01 | Juri Jurus | Panel Juri Jurus |
| Mencatat pengurangan 0.50, menetapkan diskualifikasi | Pengawas/Dewan Wasit Juri | Panel Operator Jurus (bagian Pengurangan) |
| **Mengesahkan skor akhir** | Ketua Pertandingan | Panel Operator Jurus |

Pengesahan **ditolak sistem** kalau jumlah juri yang sudah menilai kurang dari setelan turnamen atau jumlahnya ganjil (Pasal 16.1.b) -- kecuali penampilan itu didiskualifikasi.

## Protes VAR dan Protes Manajer

1. Pelatih mengangkat kartu protes VAR di pinggir gelanggang (fisik, di luar sistem).
2. **Operator IT atau Ketua Pertandingan** memasukkan protes ke sistem lewat Panel Keberatan (`/admin/turnamen/{id}/partai/{match}/keberatan`), memilih sudut dan menuliskan kejadian yang disengketakan.
3. **Wasit Komisi Protes** meninjau dalam tenggat 5 menit yang ditampilkan sistem, lalu menetapkan Sah/Tidak Sah dari panel yang sama.
4. Lewat tenggat, sistem hanya menampilkan peringatan -- prosesnya dilanjutkan secara manual lewat verifikasi juri yang dipimpin Ketua Pertandingan (di luar sistem).
5. Protes Manajer (setelah hasil diumumkan) diajukan dan diputus dari panel yang sama, tingkat pertama oleh Ketua Pertandingan, banding oleh Delegasi Teknik.

## Setup vMix Pro

Lima halaman overlay dipasang sebagai **Web Browser Input** terpisah (bukan satu input untuk semuanya), supaya bisa ditoggle sendiri-sendiri dari vMix:

| Overlay Channel | URL | Isi |
|---|---|---|
| 1 | `http://localhost:8000/overlay/scorebug/{arena_id}` | Skor, timer, babak |
| 2 | `http://localhost:8000/overlay/athlete/{arena_id}/red` dan `/blue` | Lower third nama & kontingen |
| 3 | `http://localhost:8000/overlay/breakdown/{arena_id}` | Rincian nilai & hukuman, kilat saat nilai baru |
| 4 | `http://localhost:8000/overlay/result/{arena_id}` | Papan hasil akhir partai |
| Input terpisah | `http://localhost:8000/overlay/bracket/{tournament_id}?kelas={weight_class_id}` | Bagan untuk tayangan antar partai |

Langkah di vMix: **Add Input → Web Browser → masukkan URL di atas → centang "Transparent Background"**. Resolusi Browser Input diset 1920×1080 mengikuti kanvas overlay.

`{arena_id}` dilihat dari URL panel operator gelanggang bersangkutan (`/admin/turnamen/{tournament}/gelanggang`). Overlay otomatis mengikuti partai aktif gelanggang itu -- tidak perlu dipilih manual ulang tiap partai.

**Sebelum siaran dimulai**, uji checklist T6.8 di `docs/RENCANA.md` (transparansi latar, latensi dengan panel operator, kestabilan 3 jam, FPS output vMix) -- ini butuh vMix Pro sungguhan dan tidak bisa diuji dari lingkungan pengembangan.

## Live score publik (opsional, kalau tunnel dipasang)

Bagikan URL `https://<domain-tunnel>/live/turnamen/{tournament_id}` ke penonton. Halaman ini otomatis menampilkan daftar gelanggang, kelas tanding beserta juara, nomor Jurus, dan tautan ke rekap medali. Lihat `docs/TUNNELING.md` untuk konfigurasi proxy-nya.

**Matikan tunnel di tengah pertandingan tidak masalah** -- panel operator/wasit/juri/dewan juri semuanya berjalan di LAN dan tidak pernah memanggil URL tunnel.

## Setelah turnamen selesai

1. **Sekretaris Pertandingan / Ketua Pertandingan** membuka menu **Rekap & Laporan**, memeriksa peringkat umum dan juara tiap kelas/nomor.
2. Ekspor CSV medali, peserta, dan jadwal untuk arsip panitia.
3. Cetak berita acara tiap partai yang belum dicetak (dari Panel Dewan Juri masing-masing partai).
