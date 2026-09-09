{{--
    Hasil verifikasi juri, sesudah Wasit MENERAPKANNYA — Pasal 13.

    # Kenapa modal, dan kenapa baru sesudah diterapkan

    Sebelumnya hasilnya tumbuh diam-diam di sudut blok verifikasi begitu ambang
    suara tercapai, sementara Wasit belum menekan apa pun. Dua hal salah dari
    situ: ia terbaca sebagai keputusan yang sudah jadi — dan orang di sekitar
    meja mengumumkannya lebih dulu — lalu ia tenggelam di antara enam petak
    jawaban juri tepat pada detik ia benar-benar berarti.

    Keputusan yang sudah diterapkan adalah satu-satunya hal yang perlu dibaca
    seluruh meja pada saat itu, dan pertandingan memang sedang berhenti. Maka
    ia mengambil layar, bukan sepotong sudutnya.

    # Warnanya

    Bidang penuh berwarna sudut yang menang suara — arti yang sama dengan papan
    skor, bagan, dan overlay. Dari jarak meja operator, warnanya sampai lebih
    dulu daripada hurufnya, dan itu memang yang paling sering ditanyakan:
    "merah atau biru".

    Netral kalau hasilnya "tidak ada". Mewarnainya merah atau biru di situ akan
    menjanjikan sudut yang justru baru saja dinyatakan tidak ada.

    # Kenapa ia menutup sendiri

    Tidak ada yang menekan apa pun di panel ini selama verifikasi — operator
    sedang ditanyai semua orang di sekitarnya. Modal yang menunggu ditekan akan
    menutupi papan skor sampai seseorang ingat, dan yang ia tutupi adalah
    angka yang dibaca gelanggang berikutnya.
--}}
<template x-if="hasilVerifikasiTampil">
    <div class="fixed inset-0 z-80 grid place-items-center bg-black/70 p-6"
         role="dialog" aria-modal="true" aria-labelledby="judul-hasil-verifikasi"
         x-init="$nextTick(() => setTimeout(() => tutupHasilVerifikasi(), 9000))"
         x-on:click="tutupHasilVerifikasi()"
         x-on:keydown.escape.window="tutupHasilVerifikasi()">

        <div class="w-full max-w-[680px] rounded-silat-besar border p-8 text-center"
             x-bind:class="{
                 'border-silat-merah bg-silat-merah-dalam': verifikasi?.hasil === 'red',
                 'border-silat-biru bg-silat-biru-dalam': verifikasi?.hasil === 'blue',
                 'border-silat-garis bg-silat-panel': verifikasi?.hasil !== 'red' && verifikasi?.hasil !== 'blue',
             }">

            <p class="silat-angka text-[13px] tracking-[.16em] text-silat-teks-samar uppercase"
               x-text="'Verifikasi ' + (verifikasi?.jenis_label ?? 'Juri')"></p>

            {{-- Kalimat yang diucapkan announcer, bukan istilah basis data.
                 "Jatuhan Valid" dan "Pelanggaran Valid" adalah bunyi yang sudah
                 dipakai di gelanggang; menuliskannya lain di layar membuat yang
                 membacanya menerjemahkan sendiri sebelum mengumumkan. --}}
            <p id="judul-hasil-verifikasi"
               class="silat-angka mt-2 text-[52px] leading-[1.1] font-semibold tracking-[-0.02em] text-silat-teks"
               x-text="(verifikasi?.jenis_label ?? '') + (verifikasi?.hasil === 'tidak_ada' ? ' Tidak Valid' : ' Valid')"></p>

            {{-- Sudutnya disebut dengan kata, bukan diserahkan pada warnanya
                 saja: proyektor gelanggang yang warnanya pudar membuat merah
                 tua dan biru tua nyaris sama, dan yang mengumumkan tidak boleh
                 menebak. --}}
            <template x-if="verifikasi?.hasil === 'red' || verifikasi?.hasil === 'blue'">
                <p class="mt-3 text-[26px] leading-snug font-medium text-silat-teks"
                   x-text="'Sudut ' + (verifikasi?.hasil === 'red' ? 'Merah' : 'Biru')"></p>
            </template>

            {{-- Akibatnya, dengan kalimat yang sama persis dengan yang dibaca
                 Wasit sebelum menekan Terapkan dan yang tercatat di riwayat --
                 ketiganya dari satu sumber. --}}
            <p class="mt-3 text-[17px] leading-relaxed text-silat-teks-kedua" x-text="verifikasi?.akibat"></p>

            <p class="mt-6 text-[13px] text-silat-teks-redup">Menutup sendiri — atau ketuk di mana saja.</p>
        </div>
    </div>
</template>
