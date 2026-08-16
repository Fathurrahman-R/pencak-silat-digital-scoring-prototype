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

            // Pasal 11 ayat 3. Kuncinya golongan usia yang mengenal Tanding.
            'babak' => ['required', 'array'],
            'babak.*.jumlah' => ['required', 'integer', 'min:1', 'max:5'],
            'babak.*.durasi_detik' => ['required', 'integer', 'min:30', 'max:600'],

            // Pasal 11.6.g.4.b.
            'wmp' => ['required', 'array'],
            'wmp.*.selisih' => ['required', 'integer', 'min:5', 'max:100'],
            'wmp.*.mulai_babak' => ['required', 'integer', 'min:1', 'max:5'],

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
            'jumlah_juri_tanding' => $data['jumlah_juri_tanding'],
            'ambang_sepakat' => $data['ambang_sepakat'],
            'window_konsensus_ms' => $data['window_konsensus_ms'],
            'jumlah_juri_jurus' => $data['jumlah_juri_jurus'],
            'istirahat_ms' => $data['istirahat_detik'] * 1000,
            'nilai' => array_map('intval', $data['nilai']),
            'hukuman' => $this->hukuman($data['hukuman']),
            'babak' => $babak,
            'wmp_selisih' => $wmp,
            'kartu_protes_tanding' => $data['kartu_protes_tanding'],
            'kartu_protes_jurus' => $data['kartu_protes_jurus'],
            'tenggat_var_detik' => $data['tenggat_var_detik'],
        ];
    }

    /**
     * Menyusun ulang tangga hukuman utuh.
     *
     * Formulir hanya menyunting angka yang memang boleh diatur panitia.
     * Struktur selebihnya — cakupan per babak atau per partai, jumlah kolom,
     * dan tingkat yang berarti diskualifikasi — datang dari naskah dan tidak
     * ditawarkan sebagai pilihan, karena mengubahnya berarti mengarang
     * peraturan sendiri.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function hukuman(array $input): array
    {
        $bawaan = config('scoring.tanding.hukuman');

        $bawaan['pembinaan']['ambang_naik_ke_teguran'] = (int) $input['pembinaan_ambang'];
        $bawaan['teguran']['pengurangan'] = [
            1 => (int) $input['teguran'][1],
            2 => (int) $input['teguran'][2],
        ];
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
            'kartu_protes_tanding' => 'Kartu protes Tanding',
            'kartu_protes_jurus' => 'Kartu protes Jurus',
            'tenggat_var_detik' => 'Tenggat keputusan VAR',
        ];

        foreach (GolonganUsia::cases() as $golongan) {
            $label["babak.{$golongan->value}.jumlah"] = "Jumlah babak {$golongan->label()}";
            $label["babak.{$golongan->value}.durasi_detik"] = "Durasi babak {$golongan->label()}";
        }

        return $label;
    }
}
