@php
    use App\Enums\ResourceAction;

    $terkunci = ! $tournament->status->bolehUbahAturan();

    // Kunci WMP: satu baris bawaan plus golongan yang naskahnya memberi angka
    // berbeda. Diambil dari setelan yang tersimpan, bukan dikarang di sini.
    $kunciWmp = array_keys($setelan->wmp_selisih);

    $namaWmp = fn (string $kunci): string => $kunci === 'bawaan'
        ? 'Semua golongan lain'
        : (\App\Enums\GolonganUsia::tryFrom($kunci)?->label() ?? $kunci);
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
                <x-ui.button type="button" variant="secondary" size="sm"
                             x-on:click="$dispatch('modal-open', 'reset-peraturan')">
                    <x-ui.icon name="rotate-ccw" class="h-4 w-4" />
                    Kembalikan ke naskah
                </x-ui.button>
            @endresource
        @endif
    </x-slot:actions>

    <div class="space-y-6">
        @if ($terkunci)
            <x-ui.alert variant="warning" title="Setelan terkunci">
                Kejuaraan sudah {{ strtolower($tournament->status->label()) }}. Setelan peraturan tidak
                bisa diubah lagi karena partai yang sudah dinilai tidak boleh berubah dasar
                perhitungannya — termasuk yang hasilnya sudah disahkan dan diumumkan.
            </x-ui.alert>
        @else
            <x-ui.alert variant="info" title="Angka bawaan mengikuti naskah 2025">
                Seluruh nilai di bawah ini berasal dari Peraturan Pertandingan Pencak Silat Nasional
                Tahun 2025 (Skep-70/III/2025). Tiap kolom menyebutkan pasalnya, dan yang tidak punya
                rujukan pasal ditandai terang-terangan.
            </x-ui.alert>
        @endif

        <form method="POST" action="{{ route('admin.turnamen.peraturan.update', $tournament) }}"
              class="space-y-6" @disabled($terkunci)>
            @csrf
            @method('PUT')

            <fieldset @disabled($terkunci) class="space-y-6">
                <x-ui.card title="Komposisi wasit juri">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input type="number" name="jumlah_juri_tanding" label="Juri kategori Tanding"
                                    :value="old('jumlah_juri_tanding', $setelan->jumlah_juri_tanding)" required
                                    hint="Pasal 16 ayat 1 huruf a: 1 wasit dan 3 juri per gelanggang." />

                        <x-ui.input type="number" name="jumlah_juri_jurus" label="Juri kategori Jurus"
                                    :value="old('jumlah_juri_jurus', $setelan->jumlah_juri_jurus)" required
                                    hint="Pasal 16 ayat 1 huruf b: minimal 4 orang dan harus genap, karena nilainya diambil dari median." />
                    </div>
                </x-ui.card>

                {{--
                    Dua kolom di kartu ini adalah satu-satunya di halaman ini yang
                    tidak punya rujukan pasal, dan itu dinyatakan terus terang.
                    Menyamarkannya seolah berasal dari naskah akan membuat panitia
                    ragu mengubahnya padahal justru di sinilah mereka berwenang.
                --}}
                <x-ui.card title="Keabsahan nilai Tanding">
                    <x-ui.alert variant="warning" title="Tidak diatur naskah" class="mb-4">
                        Naskah 2025 menetapkan jumlah jurinya, tetapi tidak menyebut berapa juri harus
                        sepakat maupun selebar apa jendela waktunya. Kedua angka di bawah adalah
                        keputusan penyelenggaraan, bukan ketentuan peraturan{{ $terkunci ? '.' : ' — dan karena itu memang boleh Anda tetapkan sendiri.' }}
                    </x-ui.alert>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input type="number" name="ambang_sepakat" label="Juri yang harus sepakat"
                                    :value="old('ambang_sepakat', $setelan->ambang_sepakat)" required
                                    hint="Nilai terbit begitu sebanyak ini juri berbeda menekan tombol yang sama. Tidak boleh melebihi jumlah juri." />

                        <x-ui.input type="number" name="window_konsensus_ms" label="Jendela konsensus (milidetik)"
                                    :value="old('window_konsensus_ms', $setelan->window_konsensus_ms)" required
                                    hint="Selang waktu antar tekanan juri agar masih dihitung menilai kejadian yang sama." />
                    </div>
                </x-ui.card>

                <x-ui.card title="Nilai prestasi teknik">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 11.6.e. Naskah 2025 hanya mengenal tiga nilai — tidak ada nilai 4 untuk
                        kuncian, dan tidak ada nilai gabungan. Urutannya wajib menaik, karena urutan
                        itu juga dipakai sebagai pemecah seri.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input type="number" name="nilai[pukulan]" label="Pukulan"
                                    :value="old('nilai.pukulan', $setelan->nilai['pukulan'])" required
                                    hint="Serangan tangan yang masuk sasaran sah." />

                        <x-ui.input type="number" name="nilai[tendangan]" label="Tendangan"
                                    :value="old('nilai.tendangan', $setelan->nilai['tendangan'])" required
                                    hint="Serangan kaki yang masuk sasaran sah." />

                        <x-ui.input type="number" name="nilai[jatuhan]" label="Jatuhan"
                                    :value="old('nilai.jatuhan', $setelan->nilai['jatuhan'])" required
                                    hint="Tangkapan, sapuan, ungkitan, kaitan, guntingan, serangan balik." />
                    </div>
                </x-ui.card>

                <x-ui.card title="Tangga hukuman">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 11.6.d.4. Urutannya Pembinaan, Teguran, Peringatan, lalu Diskualifikasi.
                        Cakupan tiap sanksi dan tingkat yang berarti diskualifikasi mengikuti naskah
                        dan tidak dapat diubah di sini.
                    </p>

                    <div class="space-y-4">
                        <x-ui.input type="number" name="hukuman[pembinaan_ambang]" label="Pembinaan sebelum naik ke Teguran"
                                    :value="old('hukuman.pembinaan_ambang', $setelan->hukuman['pembinaan']['ambang_naik_ke_teguran'])"
                                    required
                                    hint="Pembinaan tidak mengurangi nilai. Setelah sebanyak ini pembinaan, pelanggaran ringan berikutnya wajib naik menjadi Teguran." />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input type="number" name="hukuman[teguran][1]" label="Teguran I"
                                        :value="old('hukuman.teguran.1', $setelan->hukuman['teguran']['pengurangan'][1])" required
                                        hint="Pengurangan nilai. Diisi negatif." />

                            <x-ui.input type="number" name="hukuman[teguran][2]" label="Teguran II"
                                        :value="old('hukuman.teguran.2', $setelan->hukuman['teguran']['pengurangan'][2])" required
                                        hint="Teguran ketiga tidak pernah terjadi — ia langsung menjadi Peringatan I." />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input type="number" name="hukuman[peringatan][1]" label="Peringatan I"
                                        :value="old('hukuman.peringatan.1', $setelan->hukuman['peringatan']['pengurangan'][1])" required
                                        hint="Berlaku untuk seluruh babak dan tidak pernah mereset." />

                            <x-ui.input type="number" name="hukuman[peringatan][2]" label="Peringatan II"
                                        :value="old('hukuman.peringatan.2', $setelan->hukuman['peringatan']['pengurangan'][2])" required
                                        hint="Peringatan III berarti diskualifikasi, bukan pengurangan nilai." />
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card title="Babak dan waktu">
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

                                <x-ui.input type="number" :name="'babak['.$golongan->value.'][jumlah]'" label="Jumlah babak"
                                            :id="'babak-jumlah-'.$golongan->value"
                                            :value="old('babak.'.$golongan->value.'.jumlah', $baris['jumlah'])" required />

                                <x-ui.input type="number" :name="'babak['.$golongan->value.'][durasi_detik]'" label="Durasi (detik)"
                                            :id="'babak-durasi-'.$golongan->value"
                                            :value="old('babak.'.$golongan->value.'.durasi_detik', intdiv($baris['durasi_ms'], 1000))" required />
                            </div>
                        @endforeach

                        <div class="border-t border-line pt-4">
                            <x-ui.input type="number" name="istirahat_detik" label="Istirahat antar babak (detik)"
                                        :value="old('istirahat_detik', intdiv($setelan->istirahat_ms, 1000))" required />
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card title="Menang mutlak karena selisih nilai">
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

                                <x-ui.input type="number" :name="'wmp['.$kunci.'][selisih]'" label="Selisih nilai"
                                            :id="'wmp-selisih-'.$kunci"
                                            :value="old('wmp.'.$kunci.'.selisih', $baris['selisih'])" required />

                                <x-ui.input type="number" :name="'wmp['.$kunci.'][mulai_babak]'" label="Mulai babak"
                                            :id="'wmp-babak-'.$kunci"
                                            :value="old('wmp.'.$kunci.'.mulai_babak', $baris['mulai_babak'])" required />
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card title="VAR dan kartu protes">
                    <p class="mb-4 text-base2 text-ink-muted">
                        Pasal 15. Sistem tidak memutar video — ia menandai momen yang disengketakan,
                        mencatat keputusannya, dan menegakkan tenggat waktunya.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input type="number" name="kartu_protes_tanding" label="Kartu protes Tanding"
                                    :value="old('kartu_protes_tanding', $setelan->kartu_protes_tanding)" required
                                    hint="Per pertandingan, berlaku sepanjang tiga babak." />

                        <x-ui.input type="number" name="kartu_protes_jurus" label="Kartu protes Jurus"
                                    :value="old('kartu_protes_jurus', $setelan->kartu_protes_jurus)" required
                                    hint="Per penampilan, diajukan sebelum pemenang diumumkan." />

                        <x-ui.input type="number" name="tenggat_var_detik" label="Tenggat keputusan VAR (detik)"
                                    :value="old('tenggat_var_detik', $setelan->tenggat_var_detik)" required
                                    hint="Lewat tenggat, proses dilanjutkan dengan verifikasi juri." />
                    </div>
                </x-ui.card>
            </fieldset>

            @unless ($terkunci)
                <div class="flex items-center gap-2">
                    <x-ui.button type="submit">Simpan setelan</x-ui.button>
                    <x-ui.button :href="route('admin.turnamen.edit', $tournament)" variant="secondary">Kembali</x-ui.button>
                </div>
            @endunless
        </form>
    </div>

    @unless ($terkunci)
        @resource(rk('peraturan-turnamen', ResourceAction::Update))
            <x-ui.modal id="reset-peraturan" title="Kembalikan ke naskah 2025" size="sm">
                Seluruh setelan kejuaraan ini dikembalikan ke angka naskah 2025. Perubahan yang sudah
                Anda buat akan hilang.

                <x-slot:footer>
                    <x-ui.button variant="secondary" type="button"
                                 x-on:click="$dispatch('modal-close', 'reset-peraturan')">Batal</x-ui.button>

                    <form method="POST" action="{{ route('admin.turnamen.peraturan.reset', $tournament) }}">
                        @csrf
                        <x-ui.button type="submit">Kembalikan</x-ui.button>
                    </form>
                </x-slot:footer>
            </x-ui.modal>
        @endresource
    @endunless
</x-layouts.admin>
