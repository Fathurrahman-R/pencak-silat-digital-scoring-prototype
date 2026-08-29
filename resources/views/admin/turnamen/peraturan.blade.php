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
                Kejuaraan sudah {{ strtolower($tournament->status->label()) }}. Setelan peraturan tidak
                bisa diubah lagi karena partai yang sudah dinilai tidak boleh berubah dasar
                perhitungannya — termasuk yang hasilnya sudah disahkan dan diumumkan.
            </x-si.callout>
        @else
            <x-si.callout varian="keterangan" judul="Angka bawaan mengikuti naskah 2025">
                Seluruh nilai di bawah ini berasal dari Peraturan Pertandingan Pencak Silat Nasional
                Tahun 2025 (Skep-70/III/2025). Tiap kolom menyebutkan pasalnya, dan yang tidak punya
                rujukan pasal ditandai terang-terangan.
            </x-si.callout>
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
                        Cakupan tiap sanksi dan tingkat yang berarti diskualifikasi mengikuti naskah
                        dan tidak dapat diubah di sini.
                    </p>

                    <div class="space-y-4">
                        <x-si.isian tipe="number" name="hukuman[pembinaan_ambang]" label="Pembinaan sebelum naik ke Teguran"
                                    :value="old('hukuman.pembinaan_ambang', $setelan->hukuman['pembinaan']['ambang_naik_ke_teguran'])"
                                    wajib
                                    bantuan="Pembinaan tidak mengurangi nilai. Setelah sebanyak ini pembinaan, pelanggaran ringan berikutnya wajib naik menjadi Teguran." />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-si.isian tipe="number" name="hukuman[teguran][1]" label="Teguran I"
                                        :value="old('hukuman.teguran.1', $setelan->hukuman['teguran']['pengurangan'][1])" wajib
                                        bantuan="Pengurangan nilai. Diisi negatif." />

                            <x-si.isian tipe="number" name="hukuman[teguran][2]" label="Teguran II"
                                        :value="old('hukuman.teguran.2', $setelan->hukuman['teguran']['pengurangan'][2])" wajib
                                        bantuan="Teguran ketiga tidak pernah terjadi — ia langsung menjadi Peringatan I." />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-si.isian tipe="number" name="hukuman[peringatan][1]" label="Peringatan I"
                                        :value="old('hukuman.peringatan.1', $setelan->hukuman['peringatan']['pengurangan'][1])" wajib
                                        bantuan="Berlaku untuk seluruh babak dan tidak pernah mereset." />

                            <x-si.isian tipe="number" name="hukuman[peringatan][2]" label="Peringatan II"
                                        :value="old('hukuman.peringatan.2', $setelan->hukuman['peringatan']['pengurangan'][2])" wajib
                                        bantuan="Peringatan III berarti diskualifikasi, bukan pengurangan nilai." />
                        </div>
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
