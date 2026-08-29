<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Panel petugas timbang badan — Pasal 2 ayat 4.
 *
 * Hanya melayani kategori Tanding pada golongan yang memang menjalani timbang
 * badan. Pra Usia Dini dan Usia Dini 1 dikecualikan naskah, dan kategori Jurus
 * tidak mengenal kelas berat sama sekali.
 */
class WeightInController extends Controller
{
    /** Penyaring yang dikenali, beserta cara menghitungnya. */
    private const SARINGAN = ['belum', 'lolos', 'gugur', 'semua'];

    public function index(Request $request, Tournament $tournament): View
    {
        $cari = $request->string('q')->toString();
        $saringan = $request->string('saringan')->toString();
        $saringan = in_array($saringan, self::SARINGAN, true) ? $saringan : 'belum';

        $semua = $this->pesertaTimbang($tournament, $cari);

        /*
         * Hitungan dibuat dari koleksi yang sama, sebelum penyaringan.
         * Chip penyaring wajib membawa angkanya sendiri: petugas timbang perlu
         * tahu masih ada berapa yang belum ditimbang SEBELUM menekan chipnya,
         * karena itulah satu-satunya angka yang menentukan ia boleh pulang
         * atau tidak.
         */
        $hitungan = [
            'belum' => $semua->filter(fn (Registration $r) => $r->weightIns->isEmpty())->count(),
            'lolos' => $semua->filter(fn (Registration $r) => $r->weightIns->first()?->passed === true)->count(),
            'gugur' => $semua->filter(fn (Registration $r) => $r->weightIns->first()?->passed === false)->count(),
            'semua' => $semua->count(),
        ];

        $tersaring = (match ($saringan) {
            'belum' => $semua->filter(fn (Registration $r) => $r->weightIns->isEmpty()),
            'lolos' => $semua->filter(fn (Registration $r) => $r->weightIns->first()?->passed === true),
            'gugur' => $semua->filter(fn (Registration $r) => $r->weightIns->first()?->passed === false),
            default => $semua,
        })->values();

        /*
         * Peserta yang sedang ditimbang dibawa terpisah, bukan cuma ditandai
         * di daftar. Panel penimbangan menampilkan batas kelasnya besar-besar,
         * dan batas itu harus datang dari peserta yang benar-benar dipilih --
         * bukan dari baris pertama daftar yang kebetulan terlihat.
         */
        $terpilih = $request->integer('peserta') > 0
            ? $tersaring->firstWhere('id', $request->integer('peserta'))
            : $tersaring->first();

        return view('admin.timbang.index', [
            'tournament' => $tournament,
            'registrations' => $tersaring,
            'terpilih' => $terpilih,
            'batas' => $terpilih ? $this->batasKelas($terpilih) : null,
            'cari' => $cari,
            'saringan' => $saringan,
            'hitungan' => $hitungan,
        ]);
    }

    /**
     * Peserta Tanding yang menjalani timbang badan, urut menurut jadwal.
     *
     * @return Collection<int, Registration>
     */
    private function pesertaTimbang(Tournament $tournament, string $cari): Collection
    {
        return Registration::query()
            ->tanding()
            ->whereHas('contingent', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->when($cari !== '', fn ($query) => $query->where(fn ($q) => $q
                ->whereHas('athletes', fn ($athlete) => $athlete->where('name', 'like', "%{$cari}%"))
                ->orWhereHas('contingent', fn ($k) => $k->where('name', 'like', "%{$cari}%"))))
            ->with([
                'athletes', 'contingent', 'weightClass',
                'weightIns' => fn ($q) => $q->latest('weighed_at'),
                // Jadwal partai terdekat menentukan urutan antrean.
                'matchesAsRed' => fn ($q) => $q->whereNotNull('scheduled_at')->orderBy('scheduled_at'),
                'matchesAsBlue' => fn ($q) => $q->whereNotNull('scheduled_at')->orderBy('scheduled_at'),
            ])
            ->get()
            // Golongan yang tidak menjalani timbang badan disaring di sini,
            // bukan di kueri, karena aturannya melekat pada enum golongan usia.
            ->filter(fn (Registration $r): bool => $r->weightClass->golongan_usia->adaTimbangBadan())
            /*
             * Urut menurut partai paling awal, bukan menurut abjad nama.
             * Petugas timbang bekerja mengikuti antrean gelanggang: yang
             * bertanding jam sepuluh harus ditimbang sebelum yang bertanding
             * jam satu, dan daftar abjad tidak membawa satu pun petunjuk itu.
             * Yang belum terjadwal jatuh ke belakang.
             */
            /*
             * Satu kunci gabungan, bukan sortBy([fn, fn]). Bentuk array pada
             * sortBy() menerima COMPARATOR, bukan dua pengambil kunci -- dua
             * closure yang dioper begitu saja tidak pernah dipakai sebagai
             * urutan berjenjang, dan daftarnya diam-diam kembali ke urutan
             * kueri. Ketahuan lewat uji, bukan lewat membaca kode.
             */
            ->sortBy(fn (Registration $r) => sprintf(
                '%020d|%s',
                $this->jadwalTerdekat($r)?->getTimestamp() ?? PHP_INT_MAX,
                $r->athletes->first()?->name ?? '',
            ))
            ->values();
    }

    private function jadwalTerdekat(Registration $registration): ?\Illuminate\Support\Carbon
    {
        return $registration->matchesAsRed->merge($registration->matchesAsBlue)
            ->pluck('scheduled_at')->filter()->sort()->first();
    }

    /**
     * Batas kelas dalam bentuk yang bisa dinilai di peramban.
     *
     * Dikirim sebagai angka, bukan cuma kalimat "50–54 kg", supaya panel bisa
     * menyatakan lolos atau tidak SAAT ANGKANYA DIKETIK -- sebelum tangan
     * petugas berpindah ke tombol. Aturan batas terbuka/tertutup ikut dibawa
     * karena naskah memakai keduanya: "di atas 50 s.d 54" berarti batas bawah
     * eksklusif dan batas atas inklusif.
     *
     * @return array<string, mixed>
     */
    private function batasKelas(Registration $registration): array
    {
        $kelas = $registration->weightClass;

        return [
            'min' => $kelas->weight_min === null ? null : (float) $kelas->weight_min,
            'max' => $kelas->weight_max === null ? null : (float) $kelas->weight_max,
            'min_eksklusif' => (bool) $kelas->weight_min_exclusive,
            'rentang' => $kelas->rentang(),
            'nama' => $kelas->name,
            'golongan' => $kelas->jenis_kelamin->label().' '.$kelas->golongan_usia->label(),
            'pesilat' => $registration->athletes->first()?->name,
        ];
    }

    public function store(Request $request, Tournament $tournament, Registration $registration): RedirectResponse
    {
        abort_unless($registration->contingent->tournament_id === $tournament->id, 404);
        abort_unless($registration->kategori()->value === 'tanding', 404);

        $data = $request->validate([
            'weight' => ['required', 'numeric', 'min:10', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], attributes: ['weight' => 'Berat badan', 'notes' => 'Keterangan']);

        $kelas = $registration->weightClass;
        $athlete = $registration->athletes->first();

        // Hasil lolos ditetapkan sekarang, terhadap kelas yang berlaku saat
        // ini — bukan dihitung ulang saat dibaca. Kelas boleh disunting panitia
        // sesudahnya, dan hasil yang sudah ditandatangani tidak ikut berubah.
        $lolos = $kelas->memuatBerat((float) $data['weight']);

        DB::transaction(function () use ($registration, $athlete, $data, $lolos) {
            $registration->weightIns()->create([
                'athlete_id' => $athlete->id,
                'weight' => $data['weight'],
                'passed' => $lolos,
                'weighed_at' => now(),
                'recorded_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            /*
             * Tidak lolos berarti gugur, dan lawannya menang tanpa bertanding.
             * Lolos setelah sebelumnya gugur mengembalikan status ke keadaan
             * sebelum penimbangan — penimbangan ulang memang dimaksudkan untuk
             * memberi kesempatan kedua, dan status yang tidak ikut pulih
             * membuat kesempatan itu tidak berarti apa-apa.
             */
            $registration->update([
                'status' => $lolos
                    ? ($registration->verified_at ? StatusPendaftaran::Terverifikasi : StatusPendaftaran::Diajukan)
                    : StatusPendaftaran::Gugur,
            ]);
        });

        $pesan = $lolos
            ? "{$athlete->name} lolos timbang badan di {$kelas->name}."
            : "{$athlete->name} tidak lolos {$kelas->name} ({$kelas->rentang()}) dan dinyatakan gugur.";

        return back()->with($lolos ? 'success' : 'warning', $pesan);
    }
}
