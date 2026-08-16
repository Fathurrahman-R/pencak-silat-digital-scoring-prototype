@props([
    // Id baris. Diisi kalau tabelnya `selectable` — kolom centang digambar di
    // sini supaya urutan selnya tidak perlu diatur ulang di tiap halaman.
    // Baris yang terkunci memberi id null: kolomnya tetap digambar (kosong)
    // supaya jumlah kolom tidak bergeser dibanding baris lain.
    'id' => null,
    // URL fragmen panel detail. Diisi berarti seluruh baris bisa diklik.
    'panel' => null,
])

{{--
    Yang menentukan ada-tidaknya kolom centang adalah tabel induknya, bukan
    barisnya. Kalau baris yang memutuskan sendiri, header tabel — yang digambar
    tabel — bisa punya kolom lebih sedikit daripada barisnya, dan seluruh
    kolomnya bergeser satu.

    Klik di dalam elemen ber-`data-row-action` (tombol, tautan, centang) tidak
    ikut membuka panel — kalau tidak, menekan tombol Hapus akan selalu membuka
    detailnya lebih dulu.
--}}

@aware(['selectable' => []])

@php($isSelectable = $selectable !== [])

<tr @if ($panel)
        x-on:click="$event.target.closest('[data-row-action]') || $dispatch('drawer-remote-open', @js($panel))"
    @endif
    @if ($isSelectable && $id !== null)
        :class="has(@js($id)) && 'bg-accent-soft'"
    @endif
    {{ $attributes->class([
        'border-t border-line transition-colors duration-140 first:border-t-0 hover:bg-surface-inset',
        'cursor-pointer' => (bool) $panel,
    ]) }}>

    @if ($isSelectable)
        <td class="w-11 py-2.5 ps-4 pe-0 align-middle" data-row-action>
            @if ($id !== null)
                <input type="checkbox" class="form-check"
                       aria-label="Pilih baris"
                       :checked="has(@js($id))" x-on:change="toggle(@js($id))">
            @endif
        </td>
    @endif

    {{ $slot }}

    @if ($panel)
        <td class="w-11 py-2.5 pe-4 ps-0 text-end align-middle">
            <x-ui.icon name="chevron-right" class="inline size-4 text-ink-muted" />
        </td>
    @endif
</tr>
