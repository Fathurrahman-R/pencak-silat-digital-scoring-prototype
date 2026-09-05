@props(['ringkas' => false])

{{--
    Pita "sedang mencatat susulan", dipakai panel juri, wasit, dan papan
    gelanggang.

    Satu komponen, bukan tiga salinan: yang paling berbahaya dari fitur ini
    adalah satu panel yang TIDAK tahu babak mana yang sedang dicatat, dan tiga
    salinan berarti tiga kesempatan bagi salah satunya tertinggal saat
    kalimatnya diperbaiki.

    Warna amber bergaris diagonal sengaja tidak dipakai keadaan lain mana pun
    di seluruh sistem: yang melihatnya sekilas pun tahu ini bukan babak biasa.
--}}
<template x-if="susulanTerbuka">
    <div {{ $attributes->merge([
        'class' => 'shrink-0 border-b-2 border-amber-400/60 bg-[repeating-linear-gradient(45deg,rgba(251,191,36,.16)_0_10px,transparent_10px_20px)] px-3 text-center '
            .($ringkas ? 'py-1.5' : 'py-2'),
    ]) }}>
        <p class="silat-angka font-bold tracking-[.14em] text-amber-300 uppercase {{ $ringkas ? 'text-[11px]' : 'text-[13px]' }}">
            Input susulan — Babak <span x-text="susulan?.round"></span>
        </p>

        @unless ($ringkas)
            <p class="mt-0.5 text-[11.5px] text-amber-200/80">
                Yang dicatat sekarang masuk ke babak <span x-text="susulan?.round"></span>,
                bukan babak berjalan. Babak berjalan sedang dijeda.
            </p>
        @endunless
    </div>
</template>
