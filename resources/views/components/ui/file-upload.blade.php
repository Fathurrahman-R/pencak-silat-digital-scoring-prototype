@props([
    'name',
    'label' => null,
    'hint' => null,
    'accept' => null,

    // Ditandai di label, sejalan dengan input, select, dan textarea. Tanpa ini
    // berkas wajib tampak sama saja dengan yang boleh dikosongkan.
    'required' => false,
])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $invalid = $errors->has($errorKey);
    $id = $attributes->get('id', $name);
@endphp

<div x-data="{ fileName: '' }">
    @if ($label)
        <x-ui.label :for="$id" :required="$required" :invalid="$invalid">{{ $label }}</x-ui.label>
    @endif

    {{-- Tombol dan nama berkas dipisah supaya keduanya tetap terbaca; tampilan
         bawaan browser berbeda-beda dan sering terpotong. --}}
    <label for="{{ $id }}"
           @class([
               'flex h-control cursor-pointer items-center gap-3 rounded-md border bg-surface-sunken pe-3 text-sm shadow-well transition',
               'focus-within:border-accent focus-within:ring-3 focus-within:ring-accent-soft',
               'border-line' => ! $invalid,
               'border-danger' => $invalid,
           ])>
        <span class="flex h-full shrink-0 items-center gap-2 rounded-s-md border-e border-line bg-[image:var(--mat-raised)] px-3.5 font-medium whitespace-nowrap text-ink shadow-[var(--bevel)]">
            <x-ui.icon name="upload" class="size-4 shrink-0" />
            Pilih berkas
        </span>

        <span class="min-w-0 flex-1 truncate text-ink-muted" x-text="fileName || 'Belum ada berkas dipilih'"></span>

        <input type="file"
               id="{{ $id }}"
               name="{{ $name }}"
               class="sr-only"
               x-on:change="fileName = $event.target.files[0]?.name ?? ''"
               @required($required)
               @if ($accept) accept="{{ $accept }}" @endif
               @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
               {{ $attributes }}>
    </label>

    <x-ui.field-note :id="$id.'-error'" :error="$invalid ? $errors->first($errorKey) : null" :hint="$hint" />
</div>
