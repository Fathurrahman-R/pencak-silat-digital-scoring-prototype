@php
    use App\Enums\ResourceAction;
    use App\Support\Resources\ResourceGate;

    /*
     * Tiga halaman yang membicarakan satu kontingen yang sama. Ditampilkan
     * sebagai tab supaya panitia bisa berpindah langsung, bukan kembali ke
     * daftar kontingen lebih dulu.
     *
     * Tab yang tidak boleh diakses tidak ditampilkan sama sekali — official
     * kontingen dan bendahara melihat susunan tab yang berbeda.
     */
    $gate = app(ResourceGate::class);
    $items = [];

    if ($gate->allows(rk('atlet', ResourceAction::View))) {
        $items['Atlet'] = [
            route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent]),
            'admin/turnamen/*/kontingen/*/atlet*',
        ];
    }

    if ($gate->allows(rk('pendaftaran', ResourceAction::View))) {
        $items['Pendaftaran nomor'] = [
            route('admin.turnamen.kontingen.pendaftaran.index', [$tournament, $contingent]),
            'admin/turnamen/*/kontingen/*/pendaftaran*',
        ];
    }

    /*
     * Impor duduk sebagai tab, bukan tombol di halaman pendaftaran: ia
     * mengubah dua hal sekaligus -- daftar atlet DAN daftar nomor -- jadi
     * menaruhnya di bawah salah satu dari keduanya membuatnya terbaca sebagai
     * milik yang satu saja.
     */
    if ($gate->allows(rk('pendaftaran', ResourceAction::Create))) {
        $items['Impor'] = [
            route('admin.turnamen.kontingen.impor.form', [$tournament, $contingent]),
            'admin/turnamen/*/kontingen/*/impor*',
        ];
    }

    if ($gate->allows(rk('invoice', ResourceAction::View))) {
        $items['Tagihan'] = [
            route('admin.turnamen.kontingen.tagihan.show', [$tournament, $contingent]),
            'admin/turnamen/*/kontingen/*/tagihan*',
        ];
    }
@endphp

@if (count($items) > 1)
    <x-si.tab-halaman :daftar="$items" class="mb-6" />
@endif
