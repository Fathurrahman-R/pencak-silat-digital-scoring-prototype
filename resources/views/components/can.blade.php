@props([
    'resource' => null,
    'any' => null,
    'all' => null,
])

{{--
    Menyembunyikan sepotong UI kalau pengguna tidak punya resource key-nya.

        <x-can resource="turnamen.delete"> ... </x-can>
        <x-can :any="['turnamen.update', 'turnamen.delete']"> ... </x-can>
        <x-can :all="['turnamen.view', 'turnamen.export']"> ... </x-can>

    Ini murni soal tampilan. Route dan controller tetap harus dijaga sendiri —
    menyembunyikan tombol bukan pengamanan.
--}}

@php
    $gate = resource_gate();

    $visible = match (true) {
        $resource !== null => $gate->allows($resource),
        $any !== null => $gate->any($any),
        $all !== null => $gate->all($all),
        default => false,
    };
@endphp

@if ($visible){{ $slot }}@endif
