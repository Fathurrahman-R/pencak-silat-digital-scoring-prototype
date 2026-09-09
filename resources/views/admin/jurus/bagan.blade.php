@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin :heading="'Bagan '.$nomor->nama()"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Jurus' => route('admin.turnamen.jurus.nomor', $tournament),
                     $nomor->nama() => route('admin.turnamen.jurus.index', [$tournament, $nomor]),
                     'Bagan' => null,
                 ]">
    <x-slot:actions>
        @resource(rk('bagan', ResourceAction::Print))
            {{-- target=_blank: PDF dibuka di tab lain supaya halaman bagannya
                 tidak hilang dari layar panitia yang sedang memeriksanya. --}}
            <a href="{{ route('admin.turnamen.jurus.bagan.cetak', [$tournament, $nomor]) }}" target="_blank"
               class="inline-flex h-9 items-center rounded-[var(--radius)] border border-line-strong px-3 text-[13px] font-semibold text-ink">
                Cetak PDF
            </a>
        @endresource

        @if ($bagan->terkunci())
            <x-si.badge varian="sukses">
                Terkunci oleh {{ $bagan->locker?->name ?? '—' }} · {{ $bagan->locked_at->translatedFormat('d M Y, H:i') }}
            </x-si.badge>
        @endif
    </x-slot:actions>

    <div class="space-y-4">
        {{--
            Pohon yang SAMA dengan bagan Tanding, digambar komponen yang sama
            dari koordinat yang dihitung PohonBagan yang sama.

            Bagan yang digambar ulang khusus Jurus akan menyimpang diam-diam
            dari yang dilihat panitia di kelas tanding sebelah, dan dua bagan
            yang bentuknya berbeda di satu kejuaraan membuat pembacanya
            memeriksa dua kali sebelum percaya pada keduanya.
        --}}
        <x-si.kartu>
            <x-si.pohon-bagan :pohon="$pohon" />
        </x-si.kartu>

        <x-si.callout varian="keterangan" judul="Sudut biru tampil lebih dulu">
            Tiap pertemuan di bagan ini dimainkan sebagai dua penampilan berurutan di gelanggang
            yang sama — sudut biru lebih dulu, sesuai Pasal 12.1.d.7. Pemenangnya ditentukan skor
            akhir yang lebih tinggi; kalau skornya sama, Ketua Pertandingan yang menetapkannya
            dengan alasan tertulis.
        </x-si.callout>
    </div>
</x-layouts.admin>
