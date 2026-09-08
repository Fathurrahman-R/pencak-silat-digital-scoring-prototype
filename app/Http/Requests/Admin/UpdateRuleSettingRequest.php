<?php

namespace App\Http\Requests\Admin;

use App\Enums\GolonganUsia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Setelan peraturan satu kejuaraan.
 *
 * Formulirnya memakai satuan yang dipakai orang di gelanggang — detik untuk
 * durasi babak, milidetik hanya untuk jendela konsensus yang memang dibaca
 * dalam milidetik. Penerjemahan ke bentuk simpanan terjadi di sini, bukan di
 * Blade, supaya mesin scoring selalu menerima satu bentuk saja.
 */
class UpdateRuleSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Pasal 16.1.a menetapkan 3 juri. Dibuat bisa diubah karena
            // kejuaraan kecil kadang berjalan dengan aparat seadanya, dan
            // memaksakan angka naskah di situ berarti sistemnya tidak terpakai.
            'jumlah_juri_tanding' => ['required', 'integer', 'min:1', 'max:9'],
            'ambang_sepakat' => ['required', 'integer', 'min:1', 'max:9'],
            'window_konsensus_ms' => ['required', 'integer', 'min:200', 'max:10000'],

            // Pasal 16.1.b: minimal 4 orang dan harus genap.
            'jumlah_juri_jurus' => ['required', 'integer', 'min:4', 'max:12'],

            'istirahat_detik' => ['required', 'integer', 'min:10', 'max:300'],

            // Pasal 11.6.e. Nilainya bulat; tidak ada nilai pecahan pada Tanding.
            'nilai' => ['required', 'array'],
            'nilai.pukulan' => ['required', 'integer', 'min:1', 'max:10'],
            'nilai.tendangan' => ['required', 'integer', 'min:1', 'max:10'],
            'nilai.jatuhan' => ['required', 'integer', 'min:1', 'max:10'],

            // Pasal 11.6.d.4. Semuanya pengurangan, jadi tidak boleh positif.
            'hukuman' => ['required', 'array'],
            'hukuman.teguran.1' => ['required', 'integer', 'min:-20', 'max:0'],
            'hukuman.teguran.2' => ['required', 'integer', 'min:-20', 'max:0'],
            'hukuman.peringatan.1' => ['required', 'integer', 'min:-30', 'max:0'],
            'hukuman.peringatan.2' => ['required', 'integer', 'min:-30', 'max:0'],
            'hukuman.pembinaan_ambang' => ['required', 'integer', 'min:1', 'max:5'],

            /*
             * Cakupan tiap tahap: 'babak' berarti hitungannya kembali nol tiap
             * babak baru, 'partai' berarti terus menumpuk sampai partai usai.
             *
             * Dulu ketiganya dipatok di config dan tidak ditawarkan sebagai
             * pilihan. Praktiknya berbeda antar penyelenggara -- terutama
             * Teguran -- dan satu-satunya cara mengikutinya adalah menyunting
             * berkas kode di tiap laptop gelanggang, yang berarti pula seluruh
             * kejuaraan di basis data itu ikut berubah.
             */
            'hukuman.pembinaan_cakupan' => ['required', 'in:babak,partai'],
            'hukuman.teguran_cakupan' => ['required', 'in:babak,partai'],
            'hukuman.peringatan_cakupan' => ['required', 'in:babak,partai'],

            'hukuman.teguran_naik_peringatan' => ['required', 'integer', 'min:1', 'max:5'],
            'hukuman.peringatan_diskualifikasi' => ['required', 'integer', 'min:2', 'max:5'],

            // Pasal 11.6.g.2 dan 11.6.g.3.
            'hitungan' => ['required', 'array'],
            'hitungan.teguran_pada' => ['required', 'integer', 'min:1', 'max:10'],
            'hitungan.mutlak_pada' => ['required', 'integer', 'min:2', 'max:10'],
            'hitungan.beruntun' => ['required', 'integer', 'min:2', 'max:10'],
            'hitungan.cakupan_beruntun' => ['required', 'in:babak,partai'],

            // Pasal 11 ayat 3. Kuncinya golongan usia yang mengenal Tanding.
            'babak' => ['required', 'array'],
            'babak.*.jumlah' => ['required', 'integer', 'min:1', 'max:5'],
            'babak.*.durasi_detik' => ['required', 'integer', 'min:30', 'max:600'],

            // Pasal 11.6.g.4.b.
            'wmp' => ['required', 'array'],
            'wmp.*.selisih' => ['required', 'integer', 'min:5', 'max:100'],
            'wmp.*.mulai_babak' => ['required', 'integer', 'min:1', 'max:5'],

            /*
             * Pengecualian per golongan usia.
             *
             * Semuanya `nullable`: kolom yang dikosongkan berarti golongan itu
             * ikut setelan umum, bukan berarti nol. Itu sebabnya angkanya tidak
             * boleh `required` -- formulir yang menuntut tujuh golongan diisi
             * lengkap akan membuat panitia menyalin angka yang sama tujuh kali
             * dan kehilangan kemampuan mengubahnya dari satu tempat.
             */
            'golongan' => ['nullable', 'array'],
            'golongan.*.pembinaan_cakupan' => ['nullable', 'in:babak,partai'],
            'golongan.*.teguran_cakupan' => ['nullable', 'in:babak,partai'],
            'golongan.*.peringatan_cakupan' => ['nullable', 'in:babak,partai'],
            'golongan.*.teguran_naik_peringatan' => ['nullable', 'integer', 'min:1', 'max:5'],
            'golongan.*.hitungan_teguran_pada' => ['nullable', 'integer', 'min:1', 'max:10'],
            'golongan.*.hitungan_mutlak_pada' => ['nullable', 'integer', 'min:2', 'max:10'],
            'golongan.*.hitungan_beruntun' => ['nullable', 'integer', 'min:2', 'max:10'],
            'golongan.*.cakupan_beruntun' => ['nullable', 'in:babak,partai'],

            /*
             * Pasal 12.1.e.1.a. Kuncinya golongan usia, plus baris 'bawaan'
             * untuk golongan yang tidak diberi angka sendiri -- pola yang sama
             * dengan WMP.
             */
            'jurus_waktu' => ['required', 'array'],
            'jurus_waktu.*.toleransi_detik' => ['required', 'integer', 'min:0', 'max:60'],
            'jurus_waktu.*.diskualifikasi_lewat_detik' => ['required', 'integer', 'min:1', 'max:120'],

            // Pasal 15.
            'kartu_protes_tanding' => ['required', 'integer', 'min:0', 'max:5'],
            'kartu_protes_jurus' => ['required', 'integer', 'min:0', 'max:5'],
            'tenggat_var_detik' => ['required', 'integer', 'min:30', 'max:1800'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /*
             * Ambang yang melebihi jumlah juri berarti tidak ada nilai yang
             * bisa terbit sama sekali. Pertandingan tetap berjalan, tetapi
             * papan skornya diam terus dan tidak ada yang tahu penyebabnya
             * sampai babak berakhir 0-0.
             */
            $jumlah = (int) $this->input('jumlah_juri_tanding');
            $ambang = (int) $this->input('ambang_sepakat');

            if ($ambang > $jumlah) {
                $validator->errors()->add(
                    'ambang_sepakat',
                    "Ambang sepakat tidak boleh melebihi jumlah juri ({$jumlah}). "
                    ."Kalau dilanggar, tidak ada nilai yang bisa terbit sama sekali.",
                );
            }

            // Pasal 16.1.b menyebut jumlah juri Jurus harus genap, karena
            // mediannya diambil dari rata-rata dua nilai tengah.
            if ((int) $this->input('jumlah_juri_jurus') % 2 !== 0) {
                $validator->errors()->add(
                    'jumlah_juri_jurus',
                    'Jumlah juri kategori Jurus harus genap (Pasal 16 ayat 1 huruf b).',
                );
            }

            /*
             * Teguran II harus memotong lebih dalam daripada Teguran I, begitu
             * pula Peringatan II terhadap Peringatan I. Tangga hukuman yang
             * mendatar atau terbalik membuat pelanggaran berulang jadi lebih
             * ringan daripada yang pertama.
             */
            foreach (['teguran', 'peringatan'] as $jenis) {
                $pertama = (int) $this->input("hukuman.{$jenis}.1");
                $kedua = (int) $this->input("hukuman.{$jenis}.2");

                // Sama dalam pun ditolak: sanksi kedua yang tidak lebih berat
                // membuat pelanggaran berulang tidak berkonsekuensi apa pun.
                if ($kedua >= $pertama) {
                    $validator->errors()->add(
                        "hukuman.{$jenis}.2",
                        ucfirst($jenis).' II harus memotong nilai lebih dalam daripada '
                        .ucfirst($jenis).' I.',
                    );
                }
            }

            /*
             * Hitungan yang menerbitkan Teguran harus jatuh SEBELUM hitungan
             * yang mengakhiri partai. Dibalik, partai berakhir mutlak lebih
             * dulu dan tegurannya tidak pernah terjadi -- petak Teguran di
             * panel tidak akan pernah menyala oleh hitungan teknik, dan tidak
             * ada satu pun pesan yang menjelaskan kenapa.
             */
            if ((int) $this->input('hitungan.mutlak_pada') <= (int) $this->input('hitungan.teguran_pada')) {
                $validator->errors()->add(
                    'hitungan.mutlak_pada',
                    'Hitungan menang mutlak harus lebih besar daripada hitungan yang menerbitkan Teguran.',
                );
            }

            /*
             * Peringatan yang berarti diskualifikasi tidak boleh melebihi
             * jumlah tingkat yang punya angka pengurangan ditambah satu.
             * Bawaannya tiga: Peringatan I dan II mengurangi nilai, yang ketiga
             * mengeluarkan pesilat. Disetel lebih tinggi, tingkat di atas II
             * tidak punya pengurangan maupun akibat -- hukuman yang tidak
             * berbuat apa-apa.
             */
            if ((int) $this->input('hukuman.peringatan_diskualifikasi') > 3) {
                $validator->errors()->add(
                    'hukuman.peringatan_diskualifikasi',
                    'Hanya Peringatan I dan II yang punya pengurangan nilai, jadi diskualifikasi paling lambat pada tingkat 3.',
                );
            }

            /*
             * Pengecualian golongan diperiksa terhadap angka yang BENAR-BENAR
             * berlaku di golongan itu, yaitu pengecualiannya kalau ada dan
             * setelan umum kalau tidak. Diperiksa hanya terhadap isian
             * pengecualian saja, kombinasi "mutlak dikecualikan jadi 8,
             * teguran ikut umum 9" akan lolos padahal justru itu yang membuat
             * teguran tidak pernah terjadi di golongan tersebut.
             */
            foreach ((array) $this->input('golongan', []) as $kunci => $baris) {
                $teguranPada = $baris['hitungan_teguran_pada'] ?? null;
                $mutlakPada = $baris['hitungan_mutlak_pada'] ?? null;

                if ($teguranPada === null && $mutlakPada === null) {
                    continue;
                }

                $teguranPada = (int) ($teguranPada ?? $this->input('hitungan.teguran_pada'));
                $mutlakPada = (int) ($mutlakPada ?? $this->input('hitungan.mutlak_pada'));

                if ($mutlakPada <= $teguranPada) {
                    $validator->errors()->add(
                        "golongan.{$kunci}.hitungan_mutlak_pada",
                        'Hitungan menang mutlak harus lebih besar daripada hitungan yang menerbitkan Teguran.',
                    );
                }
            }

            /*
             * Penampilan tidak boleh dinyatakan gugur sebelum toleransinya
             * habis. Dibalik, seluruh penampilan yang lewat sedetik pun langsung
             * gugur, dan toleransi yang tertulis di layar tidak pernah berlaku.
             */
            foreach ((array) $this->input('jurus_waktu', []) as $kunci => $baris) {
                if ((int) ($baris['diskualifikasi_lewat_detik'] ?? 0) <= (int) ($baris['toleransi_detik'] ?? 0)) {
                    $validator->errors()->add(
                        "jurus_waktu.{$kunci}.diskualifikasi_lewat_detik",
                        'Ambang diskualifikasi harus lebih besar daripada toleransinya.',
                    );
                }
            }

            // Nilai jatuhan yang tidak lebih tinggi daripada tendangan, atau
            // tendangan yang tidak lebih tinggi daripada pukulan, membalik
            // urutan penghargaan teknik yang jadi dasar pemecah seri.
            $nilai = $this->input('nilai');

            if ((int) $nilai['tendangan'] <= (int) $nilai['pukulan']
                || (int) $nilai['jatuhan'] <= (int) $nilai['tendangan']) {
                $validator->errors()->add(
                    'nilai.jatuhan',
                    'Urutan nilai harus menaik: pukulan < tendangan < jatuhan. '
                    .'Urutan ini juga dipakai sebagai pemecah seri (Pasal 11.6.g.1.b).',
                );
            }
        });
    }

    /**
     * Bentuk siap simpan, dengan satuan yang dipakai mesin scoring.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        $babak = [];
        foreach ($data['babak'] as $golongan => $baris) {
            $babak[$golongan] = [
                'jumlah' => (int) $baris['jumlah'],
                'durasi_ms' => (int) $baris['durasi_detik'] * 1000,
            ];
        }

        $wmp = [];
        foreach ($data['wmp'] as $kunci => $baris) {
            $wmp[$kunci] = [
                'selisih' => (int) $baris['selisih'],
                'mulai_babak' => (int) $baris['mulai_babak'],
            ];
        }

        return [
            'override_golongan' => $this->pengecualianGolongan($data['golongan'] ?? []),
            'hitungan_teknik' => [
                'teguran_pada_hitungan' => (int) $data['hitungan']['teguran_pada'],
                'mutlak_pada_hitungan' => (int) $data['hitungan']['mutlak_pada'],
                'menang_teknik_setelah_hitungan_beruntun' => (int) $data['hitungan']['beruntun'],
                'cakupan_beruntun' => $data['hitungan']['cakupan_beruntun'],
            ],
            'jumlah_juri_tanding' => $data['jumlah_juri_tanding'],
            'ambang_sepakat' => $data['ambang_sepakat'],
            'window_konsensus_ms' => $data['window_konsensus_ms'],
            'jumlah_juri_jurus' => $data['jumlah_juri_jurus'],
            'istirahat_ms' => $data['istirahat_detik'] * 1000,
            'nilai' => array_map('intval', $data['nilai']),
            'hukuman' => $this->hukuman($data['hukuman']),
            'babak' => $babak,
            'wmp_selisih' => $wmp,
            'jurus_waktu' => [
                'toleransi_detik' => array_map(
                    fn (array $baris): int => (int) $baris['toleransi_detik'],
                    $data['jurus_waktu'],
                ),
                'diskualifikasi_lewat_detik' => array_map(
                    fn (array $baris): int => (int) $baris['diskualifikasi_lewat_detik'],
                    $data['jurus_waktu'],
                ),
            ],
            'kartu_protes_tanding' => $data['kartu_protes_tanding'],
            'kartu_protes_jurus' => $data['kartu_protes_jurus'],
            'tenggat_var_detik' => $data['tenggat_var_detik'],
        ];
    }

    /**
     * Menyusun pengecualian per golongan usia dari isian formulir.
     *
     * Kolom kosong DIBUANG, tidak disimpan sebagai null: kunci yang hadir
     * dengan nilai null tetap menimpa setelan umum saat ditumpuk, dan hasilnya
     * golongan itu kehilangan angkanya sama sekali. Golongan yang seluruh
     * kolomnya kosong tidak menyisakan baris apa pun -- ia ikut setelan umum,
     * dan tetap ikut kalau setelan umumnya diubah nanti.
     *
     * @param  array<string, array<string, mixed>>  $input
     * @return array<string, array<string, mixed>>
     */
    private function pengecualianGolongan(array $input): array
    {
        $hasil = [];

        foreach ($input as $golongan => $baris) {
            $hukuman = [];
            $hitungan = [];

            foreach (['pembinaan', 'teguran', 'peringatan'] as $tahap) {
                if (($baris["{$tahap}_cakupan"] ?? null) !== null) {
                    $hukuman[$tahap]['cakupan'] = $baris["{$tahap}_cakupan"];
                }
            }

            if (($baris['teguran_naik_peringatan'] ?? null) !== null) {
                $hukuman['teguran']['naik_ke_peringatan_dalam_babak_pada'] = (int) $baris['teguran_naik_peringatan'];
            }

            foreach ([
                'hitungan_teguran_pada' => 'teguran_pada_hitungan',
                'hitungan_mutlak_pada' => 'mutlak_pada_hitungan',
                'hitungan_beruntun' => 'menang_teknik_setelah_hitungan_beruntun',
            ] as $dariFormulir => $keSimpanan) {
                if (($baris[$dariFormulir] ?? null) !== null) {
                    $hitungan[$keSimpanan] = (int) $baris[$dariFormulir];
                }
            }

            if (($baris['cakupan_beruntun'] ?? null) !== null) {
                $hitungan['cakupan_beruntun'] = $baris['cakupan_beruntun'];
            }

            $satu = array_filter([
                'hukuman' => $hukuman,
                'hitungan_teknik' => $hitungan,
            ]);

            if ($satu !== []) {
                $hasil[$golongan] = $satu;
            }
        }

        return $hasil;
    }

    /**
     * Menyusun ulang tangga hukuman utuh.
     *
     * Berangkat dari struktur naskah, lalu menimpanya dengan yang disunting
     * panitia. Yang TIDAK ditawarkan cuma `jumlah_kolom` -- banyaknya petak di
     * panel -- karena itu bentuk tampilan, bukan aturan, dan menggesernya cuma
     * membuat petak yang tidak pernah menyala.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function hukuman(array $input): array
    {
        $bawaan = config('scoring.tanding.hukuman');

        $bawaan['pembinaan']['ambang_naik_ke_teguran'] = (int) $input['pembinaan_ambang'];
        $bawaan['pembinaan']['cakupan'] = $input['pembinaan_cakupan'];

        $bawaan['teguran']['cakupan'] = $input['teguran_cakupan'];
        $bawaan['teguran']['naik_ke_peringatan_dalam_babak_pada'] = (int) $input['teguran_naik_peringatan'];
        $bawaan['teguran']['pengurangan'] = [
            1 => (int) $input['teguran'][1],
            2 => (int) $input['teguran'][2],
        ];

        $bawaan['peringatan']['cakupan'] = $input['peringatan_cakupan'];
        $bawaan['peringatan']['tingkat_diskualifikasi'] = (int) $input['peringatan_diskualifikasi'];
        $bawaan['peringatan']['pengurangan'] = [
            1 => (int) $input['peringatan'][1],
            2 => (int) $input['peringatan'][2],
            3 => null, // Peringatan III berarti diskualifikasi, bukan pengurangan.
        ];

        return $bawaan;
    }

    public function attributes(): array
    {
        $label = [
            'jumlah_juri_tanding' => 'Jumlah juri Tanding',
            'ambang_sepakat' => 'Ambang sepakat',
            'window_konsensus_ms' => 'Jendela konsensus',
            'jumlah_juri_jurus' => 'Jumlah juri Jurus',
            'istirahat_detik' => 'Istirahat antar babak',
            'nilai.pukulan' => 'Nilai pukulan',
            'nilai.tendangan' => 'Nilai tendangan',
            'nilai.jatuhan' => 'Nilai jatuhan',
            'hukuman.teguran.1' => 'Teguran I',
            'hukuman.teguran.2' => 'Teguran II',
            'hukuman.peringatan.1' => 'Peringatan I',
            'hukuman.peringatan.2' => 'Peringatan II',
            'hukuman.pembinaan_ambang' => 'Ambang pembinaan',
            'hukuman.pembinaan_cakupan' => 'Cakupan Pembinaan',
            'hukuman.teguran_cakupan' => 'Cakupan Teguran',
            'hukuman.peringatan_cakupan' => 'Cakupan Peringatan',
            'hukuman.teguran_naik_peringatan' => 'Teguran sebelum naik ke Peringatan',
            'hukuman.peringatan_diskualifikasi' => 'Peringatan yang berarti diskualifikasi',
            'hitungan.teguran_pada' => 'Hitungan yang menerbitkan Teguran',
            'hitungan.mutlak_pada' => 'Hitungan menang mutlak',
            'hitungan.beruntun' => 'Hitungan beruntun untuk menang teknik',
            'hitungan.cakupan_beruntun' => 'Cakupan hitungan beruntun',
            'kartu_protes_tanding' => 'Kartu protes Tanding',
            'kartu_protes_jurus' => 'Kartu protes Jurus',
            'tenggat_var_detik' => 'Tenggat keputusan VAR',
        ];

        foreach (GolonganUsia::cases() as $golongan) {
            $label["babak.{$golongan->value}.jumlah"] = "Jumlah babak {$golongan->label()}";
            $label["babak.{$golongan->value}.durasi_detik"] = "Durasi babak {$golongan->label()}";
            $label["jurus_waktu.{$golongan->value}.toleransi_detik"] = "Toleransi Jurus {$golongan->label()}";
            $label["jurus_waktu.{$golongan->value}.diskualifikasi_lewat_detik"] = "Ambang gugur Jurus {$golongan->label()}";
        }

        foreach (GolonganUsia::cases() as $golongan) {
            $label["golongan.{$golongan->value}.teguran_cakupan"] = "Cakupan Teguran {$golongan->label()}";
            $label["golongan.{$golongan->value}.pembinaan_cakupan"] = "Cakupan Pembinaan {$golongan->label()}";
            $label["golongan.{$golongan->value}.peringatan_cakupan"] = "Cakupan Peringatan {$golongan->label()}";
            $label["golongan.{$golongan->value}.teguran_naik_peringatan"] = "Teguran sebelum Peringatan {$golongan->label()}";
            $label["golongan.{$golongan->value}.hitungan_teguran_pada"] = "Hitungan Teguran {$golongan->label()}";
            $label["golongan.{$golongan->value}.hitungan_mutlak_pada"] = "Hitungan mutlak {$golongan->label()}";
            $label["golongan.{$golongan->value}.hitungan_beruntun"] = "Hitungan beruntun {$golongan->label()}";
            $label["golongan.{$golongan->value}.cakupan_beruntun"] = "Cakupan beruntun {$golongan->label()}";
        }

        $label['jurus_waktu.bawaan.toleransi_detik'] = 'Toleransi Jurus golongan lain';
        $label['jurus_waktu.bawaan.diskualifikasi_lewat_detik'] = 'Ambang gugur Jurus golongan lain';

        return $label;
    }
}
