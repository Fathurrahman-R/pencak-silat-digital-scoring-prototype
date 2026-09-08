@php
    use App\Enums\ResourceAction;

    $terkunci = ! $tournament->status->bolehUbahAturan();

    // Kunci WMP: satu baris bawaan plus golongan yang naskahnya memberi angka
    // berbeda. Diambil dari setelan yang tersimpan, bukan dikarang di sini.
    $kunciWmp = array_keys($setelan->wmp_selisih);

    $namaWmp = fn (string $kunci): string => $kunci === 'bawaan'
        ? 'Semua golongan lain'
        : (\App\Enums\GolonganUsia::tryFrom($kunci)?->label() ?? $kunci);

    // Tahap hukuman dibaca lewat helper setelan, bukan langsung dari kolom
    // JSON: baris kejuaraan lama tidak memuat kunci yang baru diperkenalkan,
    // dan formulir yang membacanya mentah akan menampilkan pilihan kosong.
    $pembinaan = $setelan->hukumanTahap('pembinaan');
    $teguran = $setelan->hukumanTahap('teguran');
    $peringatan = $setelan->hukumanTahap('peringatan');
    $hitungan = $setelan->hitunganTeknik();

    // Kunci waktu Jurus: baris bawaan plus golongan yang naskahnya memberi
    // angka berbeda. Diambil dari setelan tersimpan, sama seperti WMP.
    $waktuJurus = $setelan->jurus_waktu ?? [
        'toleransi_detik' => config('scoring.jurus.toleransi_detik'),
        'diskualifikasi_lewat_detik' => config('scoring.jurus.diskualifikasi_lewat_detik'),
    ];
    $kunciJurus = array_keys($waktuJurus['toleransi_detik']);

    $pilihanCakupan = [
        'babak' => 'Per babak — hitungan kembali nol tiap babak baru',
        'partai' => 'Per partai — menumpuk sampai partai selesai',
    ];

    // Pilihan yang sama untuk baris pengecualian, ditambah satu baris kosong
    // yang berarti "ikut setelan umum" — bukan berarti mematikan cakupannya.
    $pilihanCakupanGolongan = ['' => 'Ikut setelan umum'] + [
        'babak' => 'Per babak',
        'partai' => 'Per partai',
    ];

    // Nilai tersimpan per golongan, dibaca apa adanya dari kolom pengecualian:
    // yang kosong memang harus tampil kosong, bukan terisi angka umum, supaya
    // terlihat mana yang sengaja dikecualikan dan mana yang menumpang.
    $kecuali = fn (\App\Enums\GolonganUsia $g) => $setelan->pengecualian($g);
@endphp

<x-layouts.admin heading="Setelan peraturan"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Peraturan' => null,
                 ]">
    <x-slot:actions>
        @if (! $terkunci)
            @resource(rk('peraturan-turnamen', ResourceAction::Update))
                <x-si.tombol tipe="button" varian="kedua" ukuran="kecil" ikon="rotate-ccw"
                             x-on:click="$dispatch('modal-open', 'reset-peraturan')">
                    Kembalikan ke naskah
                </x-si.tombol>
            @endresource
        @endif
    </x-slot:actions>

    <div class="space-y-4">
        @if ($terkunci)
            <x-si.callout varian="perhatian" judul="Setelan terkunci">
                Kejuaraan sudah {{ strtolower($tournament->status->label()) }}. Tidak ada lagi partai
                berikutnya yang bisa memakai setelan baru, jadi satu-satunya akibat mengubahnya
                adalah membuat dokumen hasil tidak lagi cocok dengan setelan yang tercatat.
            </x-si.callout>
        @else
            <x-si.callout varian="keterangan" judul="Angka bawaan mengikuti naskah 2025">
                Seluruh nilai di bawah ini berasal dari Peraturan Pertandingan Pencak Silat Nasional
                Tahun 2025 (Skep-70/III/2025). Tiap kolom menyebutkan pasalnya, dan yang tidak punya
                rujukan pasal ditandai terang-terangan.
            </x-si.callout>

            @if ($tournament->status === \App\Enums\StatusTurnamen::Berjalan)
                <x-si.callout varian="perhatian" judul="Kejuaraan sedang berjalan">
                    Setelan yang disimpan berlaku untuk partai yang dinilai SESUDAH ini. Nilai dan
                    hukuman yang sudah tercatat menyimpan angkanya masing-masing dan tidak dihitung
                    ulang — papan skor partai yang sudah selesai tidak akan berubah.
                    Ubah di jeda antar sesi, bukan saat ada partai yang sedang berjalan.
                </x-si.callout>
            @endif
        @endif

        <form method="POST" action="{{ route('admin.turnamen.peraturan.update', $tournament) }}"
              class="space-y-4" @disabled($terkunci)>
            @csrf
            @method('PUT')

            {{--
                Tujuh kartu bertumpuk satu kolom membuat halaman ini sepanjang
                tiga layar padahal separuh lebarnya kosong. Di layar lebar
                kartunya mengalir dua kolom.

                Multi-kolom, bukan grid: tinggi tiap kartu berbeda jauh, dan
                grid akan menyejajarkan barisnya sehingga kartu pendek
                meninggalkan lubang di bawahnya.
            --}}
            <fieldset @disabled($terkunci)
                      class="gap-4 xl:columns-2 [&>*]:mb-4 [&>*]:break-inside-avoid">
                <x-si.kartu judul="Komposisi wasit juri">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-si.isian tipe="number" name="jumlah_juri_tanding" label="Juri kategori Tanding"
                                    :value="old('jumlah_juri_tanding', $setelan->jumlah_juri_tanding)" wajib
                                    bantuan="Pasal 16 ayat 1 huruf a: 1 wasit dan 3 juri per gelanggang." />

                        <x-si.isian tipe="number" name="jumlah_juri_jurus" label="Juri kategori Jurus"
                                    :value="old('jumlah_juri_jurus', $setelan->jumlah_juri_jurus)" wajib
                                    bantuan="Pasal 16 ayat 1 huruf b: minimal 4 orang dan harus genap, karena nilainya diambil dari median." />
                    </div>
                </x-si.kartu>

                {{--
                    Dua kolom di kartu ini adalah satu-satunya di halaman ini yang
                    tidak punya rujukan pasal, dan itu dinyatakan terus terang.
                    Menyamarkannya seolah berasal dari naskah akan membuat panitia
                    ragu mengubahnya padahal justru di sinilah mereka berwenang.
                --}}
                <x-si.kartu judul="Keabsahan nilai Tanding">
                    <x-si.callout varian="perhatian" judul="Tidak diatur naskah" class="mb-4">
                        Naskah 2025 menetapkan jumlah jurinya, tetapi tidak menyebut berapa juri harus
                        sepakat maupun selebar apa jendela waktunya. Kedua angka di bawah adalah
                        keputusan penyelenggaraan, bukan ketentuan peraturan{{ $terkunci ? '.' : ' — dan karena itu memang boleh Anda tetapkan sendiri.' }}
                    </x-si.callout>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-si.isian tipe="number" name="ambang_sepakat" label="Juri yang harus sepakat"
                                    :value="old('ambang_sepakat', $setelan->ambang_sepakat)" wajib
                                    bantuan="Nilai terbit begitu sebanyak ini juri berbeda menekan tombol yang sama. Tidak boleh melebihi jumlah juri." />

                        <x-si.isian tipe="number" name="window_konsensus_ms" label="Jendela konsensus (milidetik)"
                                    :value="old('window_konsensus_ms', $setelan->window_konsensus_ms)" wajib
                                    bantuan="Selang waktu antar tekanan juri agar masih dihitung menilai kejadian yang sama." />
                    </div>
                </x-si.kartu>

                <x-si.kartu judul="Nilai prestasi teknik">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 11.6.e. Naskah 2025 hanya mengenal tiga nilai — tidak ada nilai 4 untuk
                        kuncian, dan tidak ada nilai gabungan. Urutannya wajib menaik, karena urutan
                        itu juga dipakai sebagai pemecah seri.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-si.isian tipe="number" name="nilai[pukulan]" label="Pukulan"
                                    :value="old('nilai.pukulan', $setelan->nilai['pukulan'])" wajib
                                    bantuan="Serangan tangan yang masuk sasaran sah." />

                        <x-si.isian tipe="number" name="nilai[tendangan]" label="Tendangan"
                                    :value="old('nilai.tendangan', $setelan->nilai['tendangan'])" wajib
                                    bantuan="Serangan kaki yang masuk sasaran sah." />

                        <x-si.isian tipe="number" name="nilai[jatuhan]" label="Jatuhan"
                                    :value="old('nilai.jatuhan', $setelan->nilai['jatuhan'])" wajib
                                    bantuan="Tangkapan, sapuan, ungkitan, kaitan, guntingan, serangan balik." />
                    </div>
                </x-si.kartu>

                <x-si.kartu judul="Tangga hukuman">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 11.6.d.4. Urutannya Pembinaan, Teguran, Peringatan, lalu Diskualifikasi.
                        Cakupan tiap sanksi menentukan apakah hitungannya kembali nol saat babak
                        berganti — bawaan naskah: Pembinaan dan Teguran per babak, Peringatan
                        sepanjang partai.
                    </p>

                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-si.pilihan name="hukuman[pembinaan_cakupan]" label="Cakupan Pembinaan"
                                          :options="$pilihanCakupan"
                                          :selected="old('hukuman.pembinaan_cakupan', $pembinaan['cakupan'])" wajib />

                            <x-si.isian tipe="number" name="hukuman[pembinaan_ambang]" label="Pembinaan sebelum naik ke Teguran"
                                        :value="old('hukuman.pembinaan_ambang', $pembinaan['ambang_naik_ke_teguran'])"
                                        wajib
                                        bantuan="Pembinaan tidak mengurangi nilai. Setelah sebanyak ini pembinaan, pelanggaran ringan berikutnya wajib naik menjadi Teguran." />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-si.pilihan name="hukuman[teguran_cakupan]" label="Cakupan Teguran"
                                          :options="$pilihanCakupan"
                                          :selected="old('hukuman.teguran_cakupan', $teguran['cakupan'])" wajib />

                            <x-si.isian tipe="number" name="hukuman[teguran_naik_peringatan]" label="Teguran sebelum naik ke Peringatan"
                                        :value="old('hukuman.teguran_naik_peringatan', $teguran['naik_ke_peringatan_dalam_babak_pada'])"
                                        wajib
                                        bantuan="Pasal 11.6.d.4.b.3. Sesudah sebanyak ini teguran dalam cakupan di sebelah kiri, pelanggaran berikutnya langsung menjadi Peringatan I." />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-si.pilihan name="hukuman[peringatan_cakupan]" label="Cakupan Peringatan"
                                          :options="$pilihanCakupan"
                                          :selected="old('hukuman.peringatan_cakupan', $peringatan['cakupan'])" wajib />

                            <x-si.isian tipe="number" name="hukuman[peringatan_diskualifikasi]" label="Peringatan yang berarti diskualifikasi"
                                        :value="old('hukuman.peringatan_diskualifikasi', $peringatan['tingkat_diskualifikasi'])"
                                        wajib
                                        bantuan="Tingkat Peringatan yang langsung mengakhiri partai. Diskualifikasi selalu dihitung sepanjang partai, apa pun cakupan di sebelah kiri." />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-si.isian tipe="number" name="hukuman[teguran][1]" label="Teguran I"
                                        :value="old('hukuman.teguran.1', $teguran['pengurangan'][1])" wajib
                                        bantuan="Pengurangan nilai. Diisi negatif." />

                            <x-si.isian tipe="number" name="hukuman[teguran][2]" label="Teguran II"
                                        :value="old('hukuman.teguran.2', $teguran['pengurangan'][2])" wajib
                                        bantuan="Teguran ketiga tidak pernah terjadi — ia langsung menjadi Peringatan I." />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-si.isian tipe="number" name="hukuman[peringatan][1]" label="Peringatan I"
                                        :value="old('hukuman.peringatan.1', $peringatan['pengurangan'][1])" wajib
                                        bantuan="Berlaku untuk seluruh babak dan tidak pernah mereset." />

                            <x-si.isian tipe="number" name="hukuman[peringatan][2]" label="Peringatan II"
                                        :value="old('hukuman.peringatan.2', $peringatan['pengurangan'][2])" wajib
                                        bantuan="Peringatan III berarti diskualifikasi, bukan pengurangan nilai." />
                        </div>
                    </div>
                </x-si.kartu>

                <x-si.kartu judul="Hitungan teknik">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 11.6.g.2 dan 11.6.g.3. Hitungan wasit terhadap pesilat yang jatuh punya
                        tiga akibat, dan ketiganya bisa jatuh pada hitungan yang sama.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-si.isian tipe="number" name="hitungan[teguran_pada]" label="Hitungan yang menerbitkan Teguran"
                                    :value="old('hitungan.teguran_pada', $hitungan['teguran_pada_hitungan'])" wajib
                                    bantuan="Pesilat masih bisa sikap pasang: hitungan lanjut sampai angka ini, lalu ia menerima Teguran." />

                        <x-si.isian tipe="number" name="hitungan[mutlak_pada]" label="Hitungan menang mutlak"
                                    :value="old('hitungan.mutlak_pada', $hitungan['mutlak_pada_hitungan'])" wajib
                                    bantuan="Tidak bisa bangkit sampai angka ini: lawannya menang mutlak. Harus lebih besar daripada hitungan Teguran." />

                        <x-si.isian tipe="number" name="hitungan[beruntun]" label="Hitungan beruntun untuk menang teknik"
                                    :value="old('hitungan.beruntun', $hitungan['menang_teknik_setelah_hitungan_beruntun'])" wajib
                                    bantuan="Sebanyak ini hitungan berturut-turut terhadap sudut yang sama, tanpa diselingi hitungan terhadap lawannya." />

                        <x-si.pilihan name="hitungan[cakupan_beruntun]" label="Cakupan hitungan beruntun"
                                      :options="$pilihanCakupan"
                                      :selected="old('hitungan.cakupan_beruntun', $hitungan['cakupan_beruntun'])" wajib />
                    </div>
                </x-si.kartu>

                <x-si.kartu judul="Babak dan waktu">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 11 ayat 3. Durasi dihitung sebagai waktu bersih — berhenti saat wasit
                        menghentikan pertandingan dan saat hitungan terhadap pesilat yang jatuh.
                    </p>

                    {{--
                        Label tiap kolom ditulis sekali di kepala tabel, lalu
                        disembunyikan dari mata pada baris-baris berikutnya —
                        tetap terbaca pembaca layar, tetapi tidak mengulang kata
                        yang sama tujuh kali di layar panitia.
                    --}}
                    <div class="space-y-2">
                        <div class="hidden gap-3 px-1 text-xs tracking-wide text-ink-muted sm:grid sm:grid-cols-[1fr_120px_140px]">
                            <span>Golongan usia</span>
                            <span>Jumlah babak</span>
                            <span>Durasi (detik)</span>
                        </div>

                        @foreach ($golonganTanding as $golongan)
                            @php($baris = $setelan->babak[$golongan->value] ?? ['jumlah' => 3, 'durasi_ms' => 120000])

                            <div class="grid items-center gap-3 sm:grid-cols-[1fr_120px_140px] sm:[&_label]:sr-only">
                                <p class="text-base2 text-ink">{{ $golongan->label() }}</p>

                                <x-si.isian tipe="number" :name="'babak['.$golongan->value.'][jumlah]'" label="Jumlah babak"
                                            :id="'babak-jumlah-'.$golongan->value"
                                            :value="old('babak.'.$golongan->value.'.jumlah', $baris['jumlah'])" wajib />

                                <x-si.isian tipe="number" :name="'babak['.$golongan->value.'][durasi_detik]'" label="Durasi (detik)"
                                            :id="'babak-durasi-'.$golongan->value"
                                            :value="old('babak.'.$golongan->value.'.durasi_detik', intdiv($baris['durasi_ms'], 1000))" wajib />
                            </div>
                        @endforeach

                        <div class="border-t border-line pt-4">
                            <x-si.isian tipe="number" name="istirahat_detik" label="Istirahat antar babak (detik)"
                                        :value="old('istirahat_detik', intdiv($setelan->istirahat_ms, 1000))" wajib />
                        </div>
                    </div>
                </x-si.kartu>

                <x-si.kartu judul="Menang mutlak karena selisih nilai">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 11.6.g.4.b. Sistem menawarkan penghentian partai kepada operator begitu
                        selisih nilai mencapai ambang ini pada babak yang ditentukan.
                    </p>

                    <div class="space-y-2">
                        <div class="hidden gap-3 px-1 text-xs tracking-wide text-ink-muted sm:grid sm:grid-cols-[1fr_120px_140px]">
                            <span>Golongan usia</span>
                            <span>Selisih nilai</span>
                            <span>Mulai babak</span>
                        </div>

                        @foreach ($kunciWmp as $kunci)
                            @php($baris = $setelan->wmp_selisih[$kunci])

                            <div class="grid items-center gap-3 sm:grid-cols-[1fr_120px_140px] sm:[&_label]:sr-only">
                                <p class="text-base2 text-ink">{{ $namaWmp($kunci) }}</p>

                                <x-si.isian tipe="number" :name="'wmp['.$kunci.'][selisih]'" label="Selisih nilai"
                                            :id="'wmp-selisih-'.$kunci"
                                            :value="old('wmp.'.$kunci.'.selisih', $baris['selisih'])" wajib />

                                <x-si.isian tipe="number" :name="'wmp['.$kunci.'][mulai_babak]'" label="Mulai babak"
                                            :id="'wmp-babak-'.$kunci"
                                            :value="old('wmp.'.$kunci.'.mulai_babak', $baris['mulai_babak'])" wajib />
                            </div>
                        @endforeach
                    </div>
                </x-si.kartu>


                <x-si.kartu judul="Waktu penampilan Jurus">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 12.1.e.1.a. Waktu acuan tiap nomor disetel di master data nomor Jurus;
                        yang di sini adalah berapa detik kelebihan waktu yang masih dimaafkan, dan
                        pada kelebihan berapa detik penampilan dinyatakan gugur.
                    </p>

                    <div class="space-y-2">
                        <div class="hidden gap-3 px-1 text-xs tracking-wide text-ink-muted sm:grid sm:grid-cols-[1fr_120px_140px]">
                            <span>Golongan usia</span>
                            <span>Toleransi (detik)</span>
                            <span>Gugur lewat (detik)</span>
                        </div>

                        @foreach ($kunciJurus as $kunci)
                            <div class="grid items-center gap-3 sm:grid-cols-[1fr_120px_140px] sm:[&_label]:sr-only">
                                <p class="text-base2 text-ink">{{ $namaWmp($kunci) }}</p>

                                <x-si.isian tipe="number" :name="'jurus_waktu['.$kunci.'][toleransi_detik]'" label="Toleransi (detik)"
                                            :id="'jurus-toleransi-'.$kunci"
                                            :value="old('jurus_waktu.'.$kunci.'.toleransi_detik', $waktuJurus['toleransi_detik'][$kunci])" wajib />

                                <x-si.isian tipe="number" :name="'jurus_waktu['.$kunci.'][diskualifikasi_lewat_detik]'" label="Gugur lewat (detik)"
                                            :id="'jurus-gugur-'.$kunci"
                                            :value="old('jurus_waktu.'.$kunci.'.diskualifikasi_lewat_detik', $waktuJurus['diskualifikasi_lewat_detik'][$kunci] ?? $waktuJurus['diskualifikasi_lewat_detik']['bawaan'])" wajib />
                            </div>
                        @endforeach
                    </div>
                </x-si.kartu>

                <x-si.kartu judul="VAR dan kartu protes">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 15. Sistem tidak memutar video — ia menandai momen yang disengketakan,
                        mencatat keputusannya, dan menegakkan tenggat waktunya.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-si.isian tipe="number" name="kartu_protes_tanding" label="Kartu protes Tanding"
                                    :value="old('kartu_protes_tanding', $setelan->kartu_protes_tanding)" wajib
                                    bantuan="Per pertandingan, berlaku sepanjang tiga babak." />

                        <x-si.isian tipe="number" name="kartu_protes_jurus" label="Kartu protes Jurus"
                                    :value="old('kartu_protes_jurus', $setelan->kartu_protes_jurus)" wajib
                                    bantuan="Per penampilan, diajukan sebelum pemenang diumumkan." />

                        <x-si.isian tipe="number" name="tenggat_var_detik" label="Tenggat keputusan VAR (detik)"
                                    :value="old('tenggat_var_detik', $setelan->tenggat_var_detik)" wajib
                                    bantuan="Lewat tenggat, proses dilanjutkan dengan verifikasi juri." />
                    </div>
                </x-si.kartu>
            </fieldset>

            {{--
                Fieldset KEDUA, di luar kolom-ganda di atas.
                ------------------------------------------------------------
                Tabel pengecualian selebar 1100px, dan CSS multi-kolom tidak
                bisa menyusut di bawah lebar isinya: ditaruh di dalam fieldset
                pertama, ia menarik seluruh halaman jadi 1158px di layar ponsel
                dan setiap kartu lain ikut meluber. Berdiri sendiri, tabelnya
                digulir di dalam wadahnya sendiri dan halamannya tetap selebar
                layar.
            --}}
            {{-- `min-w-0`: peramban memberi fieldset `min-width: min-content`
                 bawaan, dan itu menolak menyusut di bawah lebar tabel 1100px di
                 dalamnya -- halaman ikut melebar 1158px di layar ponsel walau
                 tabelnya sendiri sudah punya wadah bergulir. --}}
            <fieldset @disabled($terkunci) class="mb-4 w-full min-w-0">
                {{--
                    Pengecualian per golongan usia.
                    ------------------------------------------------------------
                    Kartu ini SENGAJA berdiri sesudah seluruh setelan umum, dan
                    seluruh isiannya boleh kosong. Bacaannya jadi satu arah:
                    kejuaraan punya satu aturan, lalu golongan tertentu
                    menyimpang seperlunya. Dibalik — tiap golongan diisi penuh —
                    setelan umum berhenti berarti apa pun, dan mengubah satu
                    angka menuntut menyunting tujuh baris.

                    Lebar penuh, tidak ikut dua kolom di atasnya: tabelnya
                    delapan kolom dan tidak terbaca di setengah lebar layar.
                --}}
                <x-si.kartu judul="Pengecualian per golongan usia">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Kosongkan untuk mengikuti setelan umum di atas. Yang diisi di sini hanya berlaku
                        untuk golongan pada barisnya — cakupan hukuman dan ambang hitungan teknik boleh
                        berbeda antara Usia Dini dan Dewasa dalam satu kejuaraan yang sama, tanpa
                        menyentuh berkas kode.
                    </p>

                    {{-- Digulir mendatar di dalam wadahnya sendiri: delapan kolom
                         tidak muat di layar sempit, dan halaman yang ikut
                         bergeser mendatar membuat tombol Simpan hilang. --}}
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1100px] border-collapse text-left">
                            <thead>
                                <tr class="text-xs tracking-wide text-ink-muted">
                                    <th class="py-2 pr-3 font-normal">Golongan usia</th>
                                    <th class="px-2 py-2 font-normal">Cakupan Pembinaan</th>
                                    <th class="px-2 py-2 font-normal">Cakupan Teguran</th>
                                    <th class="px-2 py-2 font-normal">Cakupan Peringatan</th>
                                    <th class="px-2 py-2 font-normal">Teguran → Peringatan</th>
                                    <th class="px-2 py-2 font-normal">Hitungan Teguran</th>
                                    <th class="px-2 py-2 font-normal">Hitungan mutlak</th>
                                    <th class="px-2 py-2 font-normal">Beruntun</th>
                                    <th class="px-2 py-2 font-normal">Cakupan beruntun</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($golonganTanding as $golongan)
                                    @php($nilai = $kecuali($golongan))
                                    @php($kunci = $golongan->value)

                                    <tr class="border-t border-line align-top [&_label]:sr-only">
                                        <td class="py-2 pr-3 text-base2 text-ink whitespace-nowrap">
                                            {{ $golongan->label() }}
                                            @if ($nilai !== [])
                                                <span class="ms-1 text-xs text-ink-muted">· dikecualikan</span>
                                            @endif
                                        </td>

                                        @foreach (['pembinaan', 'teguran', 'peringatan'] as $tahap)
                                            <td class="px-2 py-2">
                                                <x-si.pilihan :name="'golongan['.$kunci.']['.$tahap.'_cakupan]'"
                                                              :label="'Cakupan '.$tahap.' '.$golongan->label()"
                                                              :id="'g-'.$kunci.'-'.$tahap"
                                                              :options="$pilihanCakupanGolongan"
                                                              :selected="old('golongan.'.$kunci.'.'.$tahap.'_cakupan', $nilai['hukuman'][$tahap]['cakupan'] ?? '')" />
                                            </td>
                                        @endforeach

                                        <td class="px-2 py-2">
                                            <x-si.isian tipe="number" :name="'golongan['.$kunci.'][teguran_naik_peringatan]'"
                                                        :label="'Teguran sebelum Peringatan '.$golongan->label()"
                                                        :id="'g-'.$kunci.'-naik'" placeholder="umum"
                                                        :value="old('golongan.'.$kunci.'.teguran_naik_peringatan', $nilai['hukuman']['teguran']['naik_ke_peringatan_dalam_babak_pada'] ?? '')" />
                                        </td>

                                        @foreach ([
                                            ['hitungan_teguran_pada', 'teguran_pada_hitungan', 'Hitungan Teguran'],
                                            ['hitungan_mutlak_pada', 'mutlak_pada_hitungan', 'Hitungan mutlak'],
                                            ['hitungan_beruntun', 'menang_teknik_setelah_hitungan_beruntun', 'Hitungan beruntun'],
                                        ] as [$medan, $simpanan, $judul])
                                            <td class="px-2 py-2">
                                                <x-si.isian tipe="number" :name="'golongan['.$kunci.']['.$medan.']'"
                                                            :label="$judul.' '.$golongan->label()"
                                                            :id="'g-'.$kunci.'-'.$medan" placeholder="umum"
                                                            :value="old('golongan.'.$kunci.'.'.$medan, $nilai['hitungan_teknik'][$simpanan] ?? '')" />
                                            </td>
                                        @endforeach

                                        <td class="px-2 py-2">
                                            <x-si.pilihan :name="'golongan['.$kunci.'][cakupan_beruntun]'"
                                                          :label="'Cakupan beruntun '.$golongan->label()"
                                                          :id="'g-'.$kunci.'-beruntun'"
                                                          :options="$pilihanCakupanGolongan"
                                                          :selected="old('golongan.'.$kunci.'.cakupan_beruntun', $nilai['hitungan_teknik']['cakupan_beruntun'] ?? '')" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-4 text-base2 text-ink-muted">
                        <span class="font-medium text-ink">Beruntun</span> adalah hitungan wasit
                        berturut-turut terhadap pesilat yang jatuh — Pasal 11.6.g.3. Cakupannya
                        menentukan apakah hitungan itu kembali nol tiap babak atau berjalan sepanjang
                        partai.
                    </p>
                </x-si.kartu>
            </fieldset>

            @unless ($terkunci)
                <div class="flex items-center gap-2">
                    <x-si.tombol tipe="submit">Simpan setelan</x-si.tombol>
                    <x-si.tombol :tautan="route('admin.turnamen.edit', $tournament)" varian="kedua">Kembali</x-si.tombol>
                </div>
            @endunless
        </form>
    </div>

    @unless ($terkunci)
        @resource(rk('peraturan-turnamen', ResourceAction::Update))
            {{--
                Dialog ini menghapus SELURUH penyesuaian setelan kejuaraan
                sekaligus — bukan satu angka. Karena itu ia menyebutkan apa
                yang kembali ke naskah dan apa yang hilang, bukan bertanya
                "yakin?".
            --}}
            <div x-data="{ terbuka: false }"
                 x-on:modal-open.window="if ($event.detail === 'reset-peraturan') terbuka = true"
                 x-on:keydown.escape.window="terbuka = false">
                <div x-show="terbuka" x-cloak
                     class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                     x-on:click.self="terbuka = false">
                    <div class="w-full max-w-[460px] rounded-[var(--radius)] border border-line bg-surface-raised p-5">
                        <p class="text-[20px] leading-tight font-semibold text-ink">Kembalikan ke naskah 2025?</p>

                        <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                            Seluruh setelan kejuaraan ini kembali ke angka naskah Peraturan Pertandingan 2025 —
                            komposisi wasit juri, ambang keabsahan nilai, tangga hukuman, durasi babak, dan
                            selisih WMP. Penyesuaian yang sudah kamu buat hilang seluruhnya.
                        </p>

                        <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                            Partai yang sudah dinilai tidak ikut dihitung ulang; setelan hanya berlaku untuk
                            partai berikutnya.
                        </p>

                        <div class="mt-4 flex items-center gap-2">
                            <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>

                            <form method="POST" action="{{ route('admin.turnamen.peraturan.reset', $tournament) }}">
                                @csrf
                                <x-si.tombol tipe="submit">Kembalikan ke naskah</x-si.tombol>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endresource
    @endunless
</x-layouts.admin>
