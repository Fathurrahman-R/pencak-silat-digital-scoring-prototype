# Parameter Peraturan — Pemetaan ke Naskah 2025

> T8.8. Tiap nilai di `config/scoring.php` dipetakan ke pasal naskah **Peraturan Pertandingan Pencak Silat Nasional Tahun 2025** (SK Ketua Umum PB IPSI Skep-70/III/2025). Yang **tidak** punya rujukan pasal ditandai terang-terangan sebagai keputusan implementasi, bukan angka peraturan — supaya siapa pun yang mengaudit tahu persis mana yang boleh diperdebatkan dan mana yang tidak.

## Kategori Tanding — Pasal 11

| Parameter | Nilai bawaan | Pasal | Catatan |
|---|---|---|---|
| `juri.tanding.jumlah` | 3 | 16.1.a | 1 Wasit + 3 Juri per gelanggang |
| `juri.tanding.ambang_sepakat` | 2 dari 3 | **tidak diatur** | Naskah menetapkan jumlah juri, tidak menetapkan ambang sepakat. Mayoritas (2/3) dipilih sebagai bawaan yang wajar, tapi ini keputusan implementasi digital scoring, bisa diubah per turnamen lewat setelan peraturan |
| `juri.tanding.window_ms` | 2000 | **tidak diatur** | Lebar jendela waktu konsensus juga tidak disebut naskah. 2 detik mengikuti praktik lazim sistem digital scoring sejenis |
| `tanding.nilai.pukulan/tendangan/jatuhan` | 1 / 2 / 3 | 11.6.e | Hanya tiga nilai. Naskah 2025 **tidak** mengenal nilai 4 (kuncian) maupun nilai gabungan 1+1/1+2/1+3 dari edisi lama |
| `tanding.hukuman.pembinaan.pengurangan` | 0 | 11.6.d.4 | Tidak mengurangi nilai, akumulatif tanpa membedakan jenis pelanggaran |
| `tanding.hukuman.pembinaan.ambang_naik_ke_teguran` | 2 | 11.6.d.4 | Pembinaan ketiga otomatis naik jadi Teguran |
| `tanding.hukuman.teguran.pengurangan` | Teguran I −1, II −2 | 11.6.d.4 | |
| `tanding.hukuman.teguran.naik_ke_peringatan_pada` | 3 | 11.6.d.4 | Teguran ketiga tidak pernah tercatat sebagai teguran — otomatis Peringatan I |
| `tanding.hukuman.peringatan.pengurangan` | I −5, II −10, III null | 11.6.d.4 | Peringatan III berarti diskualifikasi, bukan pengurangan nilai |
| `tanding.hukuman.peringatan.cakupan` | `partai` | 11.6.d.4.c | "Berlaku untuk seluruh babak", tidak pernah mereset |
| `tanding.babak.*` | lihat tabel golongan usia | 9.3, 11.3 | Jumlah dan durasi babak per golongan usia |
| `tanding.istirahat_ms` | 60.000 | 9.3 | Istirahat antar babak 1 menit |
| `tanding.wmp_selisih.bawaan` | 30, mulai babak II | 11.6.g.4.b, 9.4.3 | |
| `tanding.wmp_selisih` (Usia Dini) | 20, mulai babak I | 11.6.g.4.b | |
| `tanding.hitungan_teknik.teguran_pada_hitungan` | 9 | 11.6.g.2 | |
| `tanding.hitungan_teknik.mutlak_pada_hitungan` | 10 | 11.6.g.3 | |
| `tanding.hitungan_teknik.menang_teknik_setelah_hitungan_beruntun` | 3 | 11.6.g.2 | |
| `tanding.pemeriksaan_dokter_detik` | 120 | 11.6.g.2.b.1 | |
| `tanding.undur_diri` | 3 panggilan, interval 30 detik | 11.6.g.5 | |
| `tanding.pemecah_seri` | urutan 5 kriteria | 11.6.g.1.b | Berhenti di kriteria pertama yang memisahkan |
| `tanding.sasaran_bernilai` / `sasaran_terlarang` | lihat config | 11.6.c | |

## Kategori Jurus — Pasal 12

| Parameter | Nilai bawaan | Pasal | Catatan |
|---|---|---|---|
| `juri.jurus.jumlah_minimal` | 4 | 16.1.b | Ditegakkan saat **pengesahan** skor, bukan saat submit nilai — lihat `JurusScoringController::sahkan()` |
| `juri.jurus.harus_genap` | true | 16.1.b | Median dari jumlah genap = rata-rata dua nilai tengah |
| `jurus.skala` | 9.00–10.00, langkah 0.01 | 12.1.f | |
| `jurus.agregasi` | `median` | 12.1.f | **Bukan** buang tertinggi-terendah lalu jumlahkan — itu anggapan dari edisi peraturan lama yang tidak berlaku di naskah 2025 |
| `jurus.pengurangan.juri` | 0.01 | 12.1.e | Kesalahan rincian gerak, urutan, gerakan tertinggal, senjata terlepas tanpa menyentuh matras |
| `jurus.pengurangan.pengawas` | 0.50 | 12.1.e | Pelanggaran waktu, keluar gelanggang, senjata menyentuh lantai, pakaian, menahan gerakan >5 detik |
| `jurus.skor_diskualifikasi` | 0.00 | 12.1.e.4.h | Ditetapkan eksplisit oleh Pengawas, bukan otomatis dari selisih waktu — naskah menyebut beberapa sebab yang semuanya butuh penilaian manusia |
| `jurus.toleransi_detik` / `diskualifikasi_lewat_detik` | lihat config | 12.1.e.1.a | Berbeda per golongan usia |
| `jurus.waktu_acuan_ms` | lihat config | 12.1.c | Hanya Tunggal & Tunggal Bebas yang berbeda per tahap (penyisihan/semifinal/final) |
| `jurus.pemecah_seri` | urutan 4 kriteria | 12.1.f.2 | Standar deviasi **populasi**, bukan sampel — tidak dirinci naskah, dipilih sebagai konvensi statistik yang lebih umum untuk data lengkap (bukan sampel dari populasi lebih besar) |

## VAR & Protes Manajer — Pasal 15

| Parameter | Nilai bawaan | Pasal | Catatan |
|---|---|---|---|
| `var.kartu_protes.tanding` | 2 per partai | 15.2.a | Berlaku sepanjang tiga babak — dihitung per sudut per partai, bukan per babak |
| `var.kartu_protes.jurus` | 1 per penampilan | 15.3.a | Skema `protest_cards` sudah generik lewat `match_id`; kartu Jurus belum dipakai UI karena kategori Jurus tidak memakai alur protes bernomor partai yang sama — menyusul kalau dibutuhkan |
| `var.tenggat_keputusan_detik` | 300 | 15 | Lewat tenggat, sistem hanya menampilkan peringatan visual — proses lanjutan (verifikasi juri dipimpin Ketua Pertandingan) di luar cakupan sistem digital ini |
| `protes_manajer.tingkat_pertama` | 10/20/120 menit | 15 ayat 4 | Ambil formulir, kembalikan formulir, keputusan |
| `protes_manajer.banding` | 10/20/120 menit | 15 ayat 4 | Keputusan banding final, tidak bisa dibanding lagi |

## Gelanggang — Pasal 8

| Parameter | Nilai bawaan | Pasal |
|---|---|---|
| `gelanggang.ukuran_m` | 10×10 | 8 |
| `gelanggang.diameter_bidang_tanding_m` | 8 | 8 |

---

## Ringkasan: yang benar-benar tidak diatur naskah

Hanya dua parameter yang murni keputusan implementasi tanpa rujukan pasal sama sekali:

1. **`ambang_sepakat`** (berapa juri harus sepakat untuk Tanding)
2. **`window_ms`** (selebar apa jendela waktu konsensus)

Keduanya tetap dapat diubah per turnamen lewat menu **Setelan Peraturan**, dan naskah sendiri mengakui keberadaan sistem digital score secara resmi (menyebut Ketua Tim Teknologi Informasi, Operator IT, dan Laptop Scoring Digital sebagai bagian dari susunan aparat dan perlengkapan wajib) — jadi keleluasaan ini bukan celah, melainkan ruang yang memang diberikan peraturan kepada penyelenggara sistem digital.
