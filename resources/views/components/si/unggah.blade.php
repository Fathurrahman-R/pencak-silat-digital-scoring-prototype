@props([
    'name',
    'label' => null,
    'bantuan' => null,
    'accept' => null,
    'wajib' => false,
])

@php
    $kunciGalat = str_replace(['[', ']'], ['.', ''], $name);
    $galat = $errors->has($kunciGalat);
    $id = $attributes->get('id', $name);
@endphp

{{--
    Unggah berkas.

    Input berkas bawaan peramban disembunyikan dan diganti tombol berkata,
    karena tombol bawaannya berbunyi "Choose File" — bahasa Inggris yang
    mengikuti bahasa peramban, bukan bahasa aplikasi, dan tidak bisa diubah.

    Nama berkas yang terpilih DITULIS setelah dipilih. Tanpa itu, official yang
    salah memilih berkas tidak punya cara tahu sebelum mengirim, dan yang
    menolaknya baru panitia sehari kemudian.
--}}

<div class="flex flex-col gap-2" x-data="{ namaBerkas: '' }">
    @if ($label)
        <label for="{{ $id }}" class="text-[13px] font-medium text-ink">
            {{ $label }}
            @if ($wajib)
                <span class="font-normal text-ink-muted">— wajib diisi</span>
            @endif
        </label>
    @endif

    <div @class([
        'flex items-center gap-3 rounded-[var(--radius)] border px-3.5 py-2.5',
        'border-danger-line' => $galat,
        'border-line-strong' => ! $galat,
    ])>
        <label for="{{ $id }}"
               class="inline-flex h-9 shrink-0 cursor-pointer items-center rounded-[var(--radius)] border border-line-strong bg-surface-raised px-3.5 text-[13.5px] font-medium text-ink">
            Pilih berkas
        </label>

        <input type="file"
               id="{{ $id }}"
               name="{{ $name }}"
               @if ($accept) accept="{{ $accept }}" @endif
               @required($wajib)
               @if ($galat) aria-invalid="true" aria-describedby="{{ $id }}-galat" @endif
               x-on:change="namaBerkas = $event.target.files[0]?.name ?? ''"
               class="sr-only"
               {{ $attributes }}>

        <span class="min-w-0 flex-1 truncate text-[13.5px]"
              x-bind:class="namaBerkas ? 'text-ink' : 'text-ink-muted'"
              x-text="namaBerkas || 'Belum ada berkas dipilih'"></span>
    </div>

    @error($kunciGalat)
        <p id="{{ $id }}-galat" class="text-[12.5px] leading-relaxed text-danger">{{ $message }}</p>
    @enderror

    @if ($bantuan)
        <p class="text-[12.5px] leading-relaxed text-ink-muted">{{ $bantuan }}</p>
    @endif
</div>
