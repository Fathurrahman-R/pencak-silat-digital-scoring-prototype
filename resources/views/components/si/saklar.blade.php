@props([
    'name',
    'label' => null,
    'dicentang' => false,
    'value' => '1',
    'bantuan' => null,
])

@php($id = $attributes->get('id', $name))

{{--
    Saklar hidup-mati.

    Bedanya dengan <x-si.centang>: centang menyatakan PILIHAN yang dikirim
    bersama formulir ("kirim salinan ke email"), saklar menyatakan KEADAAN yang
    menyala atau padam ("gelanggang aktif"). Keduanya checkbox di balik layar;
    yang berbeda apa yang dibaca orang dari bentuknya.

    Yang bisa ditekan adalah seluruh baris setinggi 44px, bukan tuas 38px-nya
    saja — alasan yang sama dengan centang: sasaran sekecil itu meleset terus
    di layar sentuh.
--}}

<div>
    <label for="{{ $id }}" class="flex min-h-[44px] cursor-pointer items-center gap-3 py-1">
        {{-- Nilai "0" dikirim lebih dulu supaya isian tetap terkirim saat
             saklarnya mati; checkbox yang tidak dicentang tidak ikut terkirim,
             dan server membacanya sebagai "tidak disentuh" alih-alih
             "dimatikan". --}}
        <input type="hidden" name="{{ $name }}" value="0">

        <input type="checkbox"
               id="{{ $id }}"
               name="{{ $name }}"
               value="{{ $value }}"
               class="peer sr-only"
               @checked($dicentang)
               {{ $attributes }}>

        {{-- Tuasnya ::after supaya tetap saudara kandung input — syarat agar
             varian peer-checked mengenainya. --}}
        <span class="relative h-[24px] w-[42px] shrink-0 rounded-full border-2 border-line-strong bg-surface-inset
                     peer-checked:border-accent peer-checked:bg-accent
                     peer-focus-visible:ring-2 peer-focus-visible:ring-accent peer-focus-visible:ring-offset-2
                     after:absolute after:top-[2px] after:left-[2px] after:size-[16px] after:rounded-full
                     after:bg-ink-muted after:transition-transform
                     peer-checked:after:translate-x-[18px] peer-checked:after:bg-accent-on"></span>

        <span class="min-w-0">
            @if ($label)
                <span class="block text-[15px] text-ink">{{ $label }}</span>
            @endif
            @if ($bantuan)
                <span class="block text-[13px] leading-relaxed text-ink-muted">{{ $bantuan }}</span>
            @endif
        </span>
    </label>
</div>
