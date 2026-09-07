@php
    /*
     * Papan hasil partai — kembaran panel dari `overlay/result.blade.php`.
     *
     * Kembaran, dan sejak sekarang benar-benar kembar: susunan, urutan, dan
     * bahasa rupanya mengikuti papan hasil siaran baris demi baris — bidang
     * kertas terang, nama pemenang di bidang sudut penuh, poin akhir raksasa,
     * lalu rincian teknik dan hukuman. Sebelumnya papan ini berupa kartu gelap
     * dengan huruf kecil: isinya sama, tapi petugas yang memeriksa hasil di
     * panel dan penonton yang melihatnya di siaran membaca dua benda yang
     * tampak berbeda, dan yang membandingkan keduanya harus mencocokkan angka
     * satu per satu untuk yakin itu partai yang sama.
     *
     * Bidang terangnya disengaja, sama seperti di siaran: ia satu-satunya
     * permukaan kertas di panel yang seluruhnya gelap, dan itulah yang membuat
     * "partai ini sudah selesai" terbaca dari jarak beberapa meter tanpa
     * membaca satu kata pun.
     *
     * Yang ADA di sini dan tidak ada di siaran: skor per babak. Siaran
     * menayangkan papannya beberapa detik lalu berganti, jadi ia hanya boleh
     * memuat yang paling penting. Panel dibaca selama masih diperlukan, dan
     * pertanyaan pertama sesudah partai selesai hampir selalu "babak mana yang
     * menentukan".
     *
     * Ukuran huruf memakai clamp() dengan satuan `cqw`, dan akar komponen ini
     * dijadikan wadah pengukurnya lewat `@container`. Komponen dipasang di tiga
     * tempat yang lebarnya jauh berbeda -- kolom penuh panel papan, kolom
     * samping panel Dewan Wasit Juri, dan panel Ketua Pertandingan -- jadi yang
     * harus diikuti ukuran hurufnya adalah lebar KOLOM, bukan lebar jendela.
     *
     * Sebelumnya satuannya `vw`, dan itu mengukur sumbu yang salah: diukur di
     * peramban pada satu viewport 1440px yang sama, ketiga tempat itu memakai
     * huruf yang identik sampai dua desimal (nama 24,48px, poin akhir 77,76px)
     * padahal wadahnya 1088px, 826px, dan 420px -- beda 2,6 kali. Di layar
     * 2560px angka poin menyentuh batas atasnya, 84px, di dalam kartu selebar
     * 420px: seperlima lebar kartu untuk satu digit, sementara nama pesilat dan
     * kontingennya pecah dua baris.
     *
     * Tidak ada yang meluap, jadi ini soal proporsi, bukan kerusakan -- tapi
     * proporsi itulah satu-satunya alasan clamp() dipasang di sini.
     *
     * `cqw` menjadikannya benar-benar kembar dengan papan siaran: 5,4cqw di
     * kartu selebar 1088px menghasilkan angka yang sama besar dengan 5,4vw di
     * layar selebar 1088px.
     *
     * Satu blok @php untuk seluruh berkas: Blade tidak mengompilasi blok @php
     * yang berdiri di berkas yang sudah memakai bentuk sebaris @php(...).
     */
    $sebabLabel = App\Support\Scoring\AlasanMenang::peta();

    // Urut sesuai naskah: tiga nilai prestasi teknik dulu (Pasal 11.6.e),
    // lalu tangga hukuman (Pasal 11.6.d.4).
    $barisRincian = [
        ['teknik', 'pukulan', 'Pukulan'],
        ['teknik', 'tendangan', 'Tendangan'],
        ['teknik', 'jatuhan', 'Jatuhan'],
        ['hukuman', 'pembinaan', 'Pembinaan'],
        ['hukuman', 'teguran', 'Teguran'],
        ['hukuman', 'peringatan', 'Peringatan'],
    ];
@endphp

<div {{ $attributes->merge(['class' => '@container overflow-hidden rounded-silat-besar bg-silat-siaran-kertas']) }}
     x-data="{
         sebabLabel: @js($sebabLabel),
         get pemenangMerah() {
             return match?.winner_registration_id !== null
                 && match?.winner_registration_id === match?.red?.registration_id;
         },
         get pemenangBiru() {
             return match?.winner_registration_id !== null
                 && match?.winner_registration_id === match?.blue?.registration_id;
         },
     }">

    {{-- Kepala: identitas partai lalu sebab kemenangan, urut seperti di
         siaran. Nomor partai berdiri paling depan karena itulah yang dipakai
         announcer dan papan jadwal untuk menyebut pertandingan ini. --}}
    <div class="px-[clamp(16px,3cqw,44px)] pt-[clamp(14px,2.2cqw,34px)] pb-[clamp(10px,1.6cqw,26px)] text-center">
        <p class="silat-angka text-[clamp(9px,0.9cqw,15px)] font-bold tracking-[.2em] text-ink-secondary uppercase"
           x-text="[
               identitas?.partai ? 'Partai ' + identitas.partai : null,
               identitas?.babak_bagan,
               identitas?.kelas ? ([identitas.jenis_kelamin, identitas.golongan].filter(Boolean).join(' ') + ' — ' + identitas.kelas) : null,
           ].filter(Boolean).join(' · ')"></p>

        <p class="silat-angka mt-[clamp(8px,1.2cqw,22px)] text-[clamp(15px,1.5cqw,22px)] font-semibold tracking-[.12em] text-ink uppercase"
           x-text="sebabLabel[match?.win_reason] ?? match?.win_reason ?? 'Belum ditetapkan'"></p>
    </div>

    {{-- Bidang sudut penuh untuk pemenang, kertas redup untuk yang kalah.
         Lebar keduanya sama supaya papan tidak bergeser saat pemenangnya
         berganti sudut. --}}
    <div class="flex items-stretch">
        <div class="flex-1 px-[clamp(14px,2.4cqw,42px)] py-[clamp(10px,1.4cqw,24px)] text-left"
             x-bind:class="pemenangMerah ? 'bg-silat-merah-dalam' : 'bg-silat-siaran-kertas-redup'">
            <p class="text-[clamp(15px,1.7cqw,26px)] leading-[1.15] tracking-[-0.025em]"
               x-bind:class="pemenangMerah ? 'font-bold text-white' : 'font-semibold text-ink'"
               x-text="(match?.red?.athletes ?? []).join(', ') || 'Sudut merah'"></p>
            <p class="mt-1 text-[clamp(11px,1cqw,15px)] leading-[1.3]"
               x-bind:class="pemenangMerah ? 'text-silat-teks-merah-redup' : 'text-ink-secondary'"
               x-text="match?.red?.contingent ?? ''"></p>
        </div>

        <div class="flex-1 px-[clamp(14px,2.4cqw,42px)] py-[clamp(10px,1.4cqw,24px)] text-right"
             x-bind:class="pemenangBiru ? 'bg-silat-biru-dalam' : 'bg-silat-siaran-kertas-redup'">
            <p class="text-[clamp(15px,1.7cqw,26px)] leading-[1.15] tracking-[-0.025em]"
               x-bind:class="pemenangBiru ? 'font-bold text-white' : 'font-semibold text-ink'"
               x-text="(match?.blue?.athletes ?? []).join(', ') || 'Sudut biru'"></p>
            <p class="mt-1 text-[clamp(11px,1cqw,15px)] leading-[1.3]"
               x-bind:class="pemenangBiru ? 'text-silat-teks-biru' : 'text-ink-secondary'"
               x-text="match?.blue?.contingent ?? ''"></p>
        </div>
    </div>

    {{-- Poin akhir. Angka pemenang berwarna penuh, yang kalah diredupkan --
         sama seperti di siaran, supaya arah kemenangan terbaca sebelum
         angkanya sempat dibandingkan. --}}
    <div class="grid grid-cols-[1fr_auto_1fr] items-center px-[clamp(16px,3cqw,44px)] pt-[clamp(12px,1.8cqw,30px)] pb-[clamp(4px,0.6cqw,10px)]">
        <p class="silat-angka text-left text-[clamp(40px,5.4cqw,84px)] leading-[0.9] font-semibold"
           x-bind:class="pemenangMerah ? 'text-ink' : 'text-ink-muted'"
           x-text="skorTotal.merah"></p>
        <p class="silat-angka px-[clamp(10px,1.6cqw,26px)] text-[clamp(9px,0.95cqw,16px)] font-bold tracking-[.16em] text-ink uppercase">Poin akhir</p>
        <p class="silat-angka text-right text-[clamp(40px,5.4cqw,84px)] leading-[0.9] font-semibold"
           x-bind:class="pemenangBiru ? 'text-ink' : 'text-ink-muted'"
           x-text="skorTotal.biru"></p>
    </div>

    {{--
        Skor per babak.

        Babak yang belum dimainkan tidak digambar sama sekali, bukan digambar
        sebagai 0–0: nol yang berarti "belum" dan nol yang berarti "tidak ada
        nilai" tidak boleh terlihat sama.
    --}}
    <div class="flex flex-col px-[clamp(16px,3cqw,44px)] pt-[clamp(10px,1.4cqw,22px)]">
        {{-- Penanda bagian. Tanpa keduanya, "Babak 1" dan "Pukulan" berdiri di
             deret baris yang bentuknya sama persis, dan pembacanya menghitung
             sendiri di mana skor babak berhenti dan rincian teknik mulai --
             tepat pada papan yang dibaca justru untuk berhenti menghitung. --}}
        <p class="silat-angka pb-[clamp(3px,0.5cqw,7px)] text-center text-[clamp(8px,0.8cqw,13px)] font-bold tracking-[.18em] text-ink-secondary uppercase">
            Skor per babak
        </p>

        <template x-for="babak in (rounds ?? []).filter(r => r.status !== 'belum_mulai')" :key="babak.round">
            <div class="grid grid-cols-[1fr_minmax(90px,220px)_1fr] items-center border-t border-silat-siaran-kertas-redup py-[clamp(4px,0.7cqw,9px)]">
                <span class="silat-angka text-left text-[clamp(14px,1.4cqw,22px)] leading-none font-medium"
                      x-bind:class="babak.skor_merah === 0 ? 'text-ink-muted' : 'text-ink'"
                      x-text="babak.skor_merah"></span>
                <span class="text-center text-[clamp(11px,1.05cqw,17px)] leading-none tracking-[-0.01em] text-ink-secondary"
                      x-text="'Babak ' + babak.round"></span>
                <span class="silat-angka text-right text-[clamp(14px,1.4cqw,22px)] leading-none font-medium"
                      x-bind:class="babak.skor_biru === 0 ? 'text-ink-muted' : 'text-ink'"
                      x-text="babak.skor_biru"></span>
            </div>
        </template>
    </div>

    {{--
        RINCIAN: dari mana angka akhir itu datang.

        "Menang angka 21–14" tidak menjelaskan apa pun sampai pembacanya tahu
        21 itu tersusun dari berapa pukulan, tendangan, dan jatuhan — dan
        berapa hukuman yang menggerusnya.

        Yang nol ditulis "—" dan diredupkan, bukan "0": deretan angka nol
        menuntut pembacanya memindai dua kali untuk menemukan baris yang
        benar-benar berisi.
    --}}
    <div class="flex flex-col px-[clamp(16px,3cqw,44px)] pt-[clamp(10px,1.4cqw,22px)] pb-[clamp(12px,1.8cqw,28px)]">
        <p class="silat-angka pb-[clamp(3px,0.5cqw,7px)] text-center text-[clamp(8px,0.8cqw,13px)] font-bold tracking-[.18em] text-ink-secondary uppercase">
            Rincian
        </p>

        @foreach ($barisRincian as [$sumber, $kunci, $label])
            <div class="grid grid-cols-[1fr_minmax(90px,220px)_1fr] items-center border-t border-silat-siaran-kertas-redup py-[clamp(4px,0.7cqw,9px)]">
                <span class="silat-angka text-left text-[clamp(14px,1.4cqw,22px)] leading-none font-medium"
                      x-bind:class="({{ $sumber }}?.merah?.{{ $kunci }} ?? 0) === 0 ? 'text-ink-muted' : 'text-ink'"
                      x-text="({{ $sumber }}?.merah?.{{ $kunci }} ?? 0) || '—'"></span>

                {{-- Token PANITIA, bukan token gelanggang: papan ini berbidang
                     kertas, dan `silat-teks-*` dirancang untuk teks putih di
                     atas bidang gelap -- di atas kertas ia nyaris tak terbaca. --}}
                <span class="text-center text-[clamp(11px,1.05cqw,17px)] leading-none tracking-[-0.01em] text-ink-secondary">{{ $label }}</span>

                <span class="silat-angka text-right text-[clamp(14px,1.4cqw,22px)] leading-none font-medium"
                      x-bind:class="({{ $sumber }}?.biru?.{{ $kunci }} ?? 0) === 0 ? 'text-ink-muted' : 'text-ink'"
                      x-text="({{ $sumber }}?.biru?.{{ $kunci }} ?? 0) || '—'"></span>
            </div>
        @endforeach
    </div>

    {{-- Selalu tampil, dua-duanya. Hasil yang belum disahkan masih bisa
         berubah, dan panel gelanggang tidak boleh menyembunyikan itu. --}}
    <p class="border-t border-silat-siaran-kertas-redup px-[clamp(16px,3cqw,44px)] py-[clamp(8px,1.2cqw,18px)] text-center text-[clamp(10px,1cqw,16px)] text-ink-secondary"
       x-text="match?.ratified ? 'Hasil sudah disahkan Dewan Wasit Juri.' : 'Menunggu pengesahan Dewan Wasit Juri.'"></p>
</div>
