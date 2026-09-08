@php
    use App\Enums\ResourceAction;
@endphp

<x-layouts.admin heading="Impor peserta"
                 :description="$contingent->name.' · '.$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kontingen' => route('admin.turnamen.kontingen.index', $tournament),
                     $contingent->name => route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent]),
                     'Impor' => null,
                 ]">

    @include('admin.kontingen.tabs')

    <div class="space-y-4">

        {{--
            Formulir tetap tampil di atas hasil pratinjau, tidak diganti.
            Berkas yang salah kolom hampir selalu disusul unggahan kedua
            beberapa detik kemudian, dan menyembunyikan formulirnya memaksa
            panitia menekan Kembali untuk sesuatu yang sudah ada di layar.
        --}}
        <x-si.kartu judul="Berkas peserta">
            <p class="mb-4 text-base2 text-ink-muted">
                Satu baris satu pesilat. Baris pertama berisi nama kolom. Unduh contohnya lebih dulu —
                bentuk tanggal dan cara menulis “ikut tanding” adalah dua hal yang paling sering salah
                pada impor pertama.
            </p>

            <div class="mb-4">
                <x-si.tombol :tautan="route('admin.turnamen.kontingen.impor.contoh', [$tournament, $contingent])"
                             varian="kedua" ukuran="kecil" ikon="download">
                    Unduh berkas contoh (CSV)
                </x-si.tombol>
            </div>

            <form method="POST"
                  action="{{ route('admin.turnamen.kontingen.impor.pratinjau', [$tournament, $contingent]) }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf

                <x-si.unggah name="berkas" label="Berkas CSV" accept=".csv,text/csv"
                             bantuan="Dari Excel: Simpan sebagai → CSV. Dari Google Spreadsheet: Berkas → Unduh → Nilai yang dipisahkan koma." />

                <div class="flex items-center gap-3">
                    <span class="h-px flex-1 bg-line"></span>
                    <span class="text-xs tracking-wide text-ink-muted uppercase">atau</span>
                    <span class="h-px flex-1 bg-line"></span>
                </div>

                {{--
                    Alamat spreadsheet dibaca langsung, tanpa kunci layanan
                    Google: yang dipakai jalur ekspor CSV bawaan Spreadsheet,
                    dan syaratnya cuma satu — berkasnya dibagikan sebagai
                    "siapa saja yang memiliki link". Di gelanggang yang tanpa
                    internet jalur ini tidak akan sampai, dan pesannya menyebut
                    itu terang-terangan alih-alih gagal diam-diam.
                --}}
                <x-si.isian name="url" label="Alamat Google Spreadsheet"
                            placeholder="https://docs.google.com/spreadsheets/d/…"
                            bantuan="Bagikan sebagai “Siapa saja yang memiliki link” lebih dulu. Butuh internet — di gelanggang, unduh CSV-nya dari kantor sekretariat." />

                <div class="flex items-center gap-2">
                    <x-si.tombol tipe="submit">Periksa berkas</x-si.tombol>
                    <x-si.tombol :tautan="route('admin.turnamen.kontingen.pendaftaran.index', [$tournament, $contingent])"
                                 varian="kedua">Kembali</x-si.tombol>
                </div>
            </form>
        </x-si.kartu>

        <x-si.kartu judul="Kolom yang dikenali">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] border-collapse text-left text-base2">
                    <thead>
                        <tr class="text-xs tracking-wide text-ink-muted">
                            <th class="py-2 pr-4 font-normal">Kolom</th>
                            <th class="px-2 py-2 font-normal">Wajib</th>
                            <th class="px-2 py-2 font-normal">Isi</th>
                        </tr>
                    </thead>
                    <tbody class="text-ink-secondary">
                        @foreach ([
                            ['nama', 'Ya', 'Nama pesilat sebagaimana dipanggil announcer.'],
                            ['jenis_kelamin', 'Ya', 'putra atau putri. L, P, laki-laki, dan perempuan juga terbaca.'],
                            ['tanggal_lahir', 'Ya', '2005-04-17 atau 17/04/2005. Golongan usia dihitung sendiri dari sini.'],
                            ['berat', 'Tidak', 'Berat klaim dalam kg. Menentukan kelas tanding yang dicarikan.'],
                            ['tanding', 'Tidak', 'Isi ya bila ikut Tanding. Kelasnya dicari dari gender, golongan usia, dan berat.'],
                            ['nomor_jurus', 'Tidak', 'Nama nomor persis seperti di menu Kategori Jurus.'],
                            ['regu', 'Tidak', 'Penanda satu tim untuk Ganda dan Regu — baris ber-regu sama jadi satu pendaftaran.'],
                        ] as [$kolom, $wajib, $isi])
                            <tr class="border-t border-line align-top">
                                <td class="py-2 pr-4 font-medium whitespace-nowrap text-ink">{{ $kolom }}</td>
                                <td class="px-2 py-2 whitespace-nowrap">{{ $wajib }}</td>
                                <td class="px-2 py-2">{{ $isi }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-base2 text-ink-muted">
                Berkas persyaratan tidak bisa datang lewat CSV, jadi peserta hasil impor tetap berstatus
                draf sampai berkasnya diunggah dan pendaftarannya diajukan. Impor memindahkan pengetikan,
                bukan verifikasi.
            </p>
        </x-si.kartu>

        @if ($hasil !== null)
            @php($r = $hasil['ringkas'])

            <x-si.kartu judul="Pratinjau — belum ada yang disimpan">
                <x-si.callout varian="keterangan" judul="Ini hasil impor sungguhan yang diputar balik" class="mb-4">
                    Seluruh baris di bawah sudah dijalankan lewat jalur yang sama persis dengan tombol
                    Terapkan, lalu dibatalkan. Yang tertulis di sini adalah yang akan terjadi — termasuk
                    kalimat penolakannya.
                </x-si.callout>

                <div class="mb-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
                    @foreach ([
                        ['Baris dibaca', $r['baris'], 'netral'],
                        ['Atlet baru', $r['atlet_baru'], 'sukses'],
                        ['Atlet dipakai ulang', $r['atlet_lama'], 'netral'],
                        ['Pendaftaran nomor', $r['nomor'], 'sukses'],
                        ['Baris ditolak', $r['ditolak'], $r['ditolak'] > 0 ? 'bahaya' : 'netral'],
                        ['Baris bercatatan', $r['catatan'], $r['catatan'] > 0 ? 'perhatian' : 'netral'],
                    ] as [$judul, $angka, $varian])
                        <div class="rounded-[var(--radius)] border border-line bg-surface px-4 py-3">
                            <p class="text-xs tracking-wide text-ink-muted">{{ $judul }}</p>
                            <p class="silat-angka mt-1 text-[26px] leading-none font-semibold text-ink">{{ $angka }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] border-collapse text-left text-base2">
                        <thead>
                            <tr class="text-xs tracking-wide text-ink-muted">
                                <th class="py-2 pr-3 font-normal">Baris</th>
                                <th class="px-2 py-2 font-normal">Nama</th>
                                <th class="px-2 py-2 font-normal">Golongan</th>
                                <th class="px-2 py-2 font-normal">Nomor yang dibuat</th>
                                <th class="px-2 py-2 font-normal">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($hasil['baris'] as $baris)
                                <tr class="border-t border-line align-top">
                                    <td class="py-2 pr-3 whitespace-nowrap text-ink-muted">{{ $baris['baris'] }}</td>
                                    <td class="px-2 py-2">
                                        <span class="font-medium text-ink">{{ $baris['nama'] !== '' ? $baris['nama'] : '—' }}</span>
                                        @if ($baris['status'] === 'tolak')
                                            <x-si.badge varian="bahaya" class="ms-2">Ditolak</x-si.badge>
                                        @elseif ($baris['atlet_baru'])
                                            <x-si.badge varian="sukses" class="ms-2">Baru</x-si.badge>
                                        @else
                                            <x-si.badge varian="netral" class="ms-2">Sudah ada</x-si.badge>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-ink-secondary">{{ $baris['golongan'] ?? '—' }}</td>
                                    <td class="px-2 py-2 text-ink-secondary">
                                        {{ $baris['nomor'] === [] ? '—' : implode(', ', $baris['nomor']) }}
                                    </td>
                                    <td class="px-2 py-2 text-ink-secondary">
                                        @if ($baris['catatan'] === [])
                                            —
                                        @else
                                            <ul class="list-disc space-y-1 ps-4">
                                                @foreach ($baris['catatan'] as $catatan)
                                                    <li>{{ $catatan }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @resource(rk('pendaftaran', ResourceAction::Create))
                    <form method="POST"
                          action="{{ route('admin.turnamen.kontingen.impor.terapkan', [$tournament, $contingent]) }}"
                          class="mt-5 flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <x-si.tombol tipe="submit">
                            Terapkan {{ $r['atlet_baru'] + $r['atlet_lama'] }} baris
                        </x-si.tombol>
                        <span class="text-base2 text-ink-muted">
                            Baris yang ditolak dilewati; sisanya disimpan.
                        </span>
                    </form>
                @endresource
            </x-si.kartu>
        @endif
    </div>
</x-layouts.admin>
