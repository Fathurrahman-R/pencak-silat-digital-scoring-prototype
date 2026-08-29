<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Bagan\BracketGenerator;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Panel bagan: menyusun, mengoreksi, dan mengunci bagan gugur tunggal per
 * kelas tanding.
 *
 * Bersarang di bawah kejuaraan seperti gelanggang dan tarif — tiap aksi
 * memastikan kelas tanding yang disebut memang milik kejuaraan di alamatnya.
 */
class BracketController extends Controller
{
    public function __construct(private readonly BracketGenerator $generator) {}

    public function index(Request $request, Tournament $tournament): View
    {
        $semua = $tournament->weightClasses()->aktif()->get()
            ->map(function (WeightClass $k) {
                $k->setRelation('bracket', $k->bracket()->withCount('slots')->first());
                $k->peserta_sah = $this->generator->pesertaSah($k)->count();

                return $k;
            })
            ->sortBy('sort_order')
            ->values();

        /*
         * Naskah menurunkan 174 kelas tanding, dan satu kejuaraan biasa hanya
         * memakai belasan di antaranya. Menampilkan seluruhnya berarti daftar
         * sepanjang belasan layar yang 99% barisnya berbunyi "0 peserta sah",
         * dengan kelas yang benar-benar dipakai terkubur di tengahnya.
         *
         * Karena itu bawaannya menyaring ke kelas yang sudah punya peserta atau
         * sudah punya bagan. Kelas yang disembunyikan tetap dihitung dan
         * jumlahnya disebut di layar, supaya penyaringan ini tidak pernah
         * terasa seperti data yang hilang.
         */
        $tampil = $request->query('tampil', 'terpakai');
        $cari = trim((string) $request->query('q', ''));

        $kelas = $semua
            ->when($tampil === 'terpakai', fn ($daftar) => $daftar->filter(
                fn (WeightClass $k) => $k->peserta_sah > 0 || $k->bracket !== null
            ))
            ->when($tampil === 'tersusun', fn ($daftar) => $daftar->filter(
                fn (WeightClass $k) => $k->bracket !== null
            ))
            ->when($cari !== '', fn ($daftar) => $daftar->filter(
                fn (WeightClass $k) => str_contains(
                    mb_strtolower($k->name.' '.$k->jenis_kelamin->label().' '.$k->golongan_usia->label()),
                    mb_strtolower($cari)
                )
            ))
            ->values();

        return view('admin.bagan.index', [
            'tournament' => $tournament,
            'kelas' => $kelas,
            'jumlahSemua' => $semua->count(),
            'jumlahTerpakai' => $semua->filter(fn (WeightClass $k) => $k->peserta_sah > 0 || $k->bracket !== null)->count(),
            'tampil' => $tampil,
            'cari' => $cari,
        ]);
    }

    public function susun(Tournament $tournament, WeightClass $weightClass): RedirectResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        try {
            $this->generator->untukKelas($weightClass);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.turnamen.bagan.show', [$tournament, $weightClass])
            ->with('success', "Bagan {$weightClass->name} disusun.");
    }

    public function show(Tournament $tournament, WeightClass $weightClass): View
    {
        $this->pastikanMilik($tournament, $weightClass);

        $bracket = $weightClass->bracket()->with([
            'slots.registration.athletes',
            'slots.registration.contingent',
            'matches.red.athletes',
            'matches.red.contingent',
            'matches.blue.athletes',
            'matches.blue.contingent',
            'matches.winner',
        ])->first();

        abort_unless($bracket, 404);

        return view('admin.bagan.show', [
            'tournament' => $tournament,
            'weightClass' => $weightClass,
            'bracket' => $bracket,
            'babak' => $bracket->matches->groupBy('round'),
            'pohon' => $this->pohon($bracket),
        ]);
    }

    /*
     * Ukuran yang mengikat tata letak pohon. Dihitung di sini, bukan di Blade,
     * karena garis penghubung harus bertemu TEPAT di tengah slot pasangannya:
     * satu-dua piksel meleset terbaca sebagai bagan yang salah sambung, dan
     * itu kesalahan paling mahal di layar ini.
     */
    private const SLOT_TINGGI = 40;

    private const SLOT_JARAK = 6;    // antar dua slot dalam satu partai

    private const LANGKAH = 116;     // dari tengah partai ke tengah partai berikutnya

    private const KOLOM_AWAL = 300;  // babak pertama memuat nama + kontingen

    private const KOLOM_LANJUT = 260;

    private const PENGHUBUNG = 40;   // lebar kolom garis antar babak

    /**
     * Susunan pohon siap gambar: posisi tiap slot dan tiap garis penghubung.
     *
     * Slot diletakkan dengan koordinat mutlak, bukan flex. `space-around`
     * mendekati posisi yang benar tapi meleset begitu tinggi slot atau jumlah
     * peserta berubah, dan pohon yang garisnya meleset menyesatkan pembacanya
     * tentang siapa bertemu siapa.
     *
     * Sudut ditentukan posisi slot, bukan hasil partai: slot bernomor ganjil
     * adalah sudut merah. Itu berlaku bahkan sebelum partainya dijadwalkan,
     * dan kontingen memakai nomor itu untuk menyiapkan sudutnya.
     *
     * @return array<string, mixed>
     */
    private function pohon(Bracket $bracket): array
    {
        $slots = $bracket->slots->sortBy('position')->values();
        $ukuran = max(2, $slots->count() ?: $bracket->size);
        $jumlahBabak = (int) ceil(log($ukuran, 2));

        // Tengah tiap slot babak pertama, lalu rata-rata berpasangan ke atas.
        $tengah = [];
        for ($i = 0; $i < $ukuran; $i++) {
            $tengah[0][$i] = intdiv($i, 2) * self::LANGKAH
                + ($i % 2) * (self::SLOT_TINGGI + self::SLOT_JARAK)
                + intdiv(self::SLOT_TINGGI, 2);
        }

        /*
         * jumlahBabak = log2(ukuran): bagan 4 peserta punya DUA babak, yaitu
         * penyisihan dan final. Kolom terakhir bernomor jumlahBabak-1, dan
         * tengah dihitung sampai situ saja -- satu babak kelebihan akan
         * menggambar kolom kosong berlabel "Final" di sebelah final yang
         * sebenarnya.
         */
        for ($r = 1; $r < $jumlahBabak; $r++) {
            foreach (array_chunk($tengah[$r - 1], 2) as $j => [$atas, $bawah]) {
                $tengah[$r][$j] = intdiv($atas + $bawah, 2);
            }
        }

        $partaiPerBabak = $bracket->matches->groupBy('round');

        $kolom = [];
        $x = 0;

        for ($r = 0; $r < $jumlahBabak; $r++) {
            $lebar = $r === 0 ? self::KOLOM_AWAL : self::KOLOM_LANJUT;

            $kolom[] = [
                'judul' => $bracket->namaBabak($r + 1),
                'x' => $x,
                'lebar' => $lebar,
                'slot' => $r === 0
                    ? $this->slotBabakPertama($slots, $tengah[0], $ukuran)
                    : $this->slotBabakLanjut($partaiPerBabak->get($r + 1), $tengah[$r]),
            ];

            $x += $lebar + self::PENGHUBUNG;
        }

        return [
            'tinggi' => intdiv($ukuran, 2) * self::LANGKAH - (self::LANGKAH - self::SLOT_TINGGI * 2 - self::SLOT_JARAK),
            'lebar' => $x - self::PENGHUBUNG,
            'slot_tinggi' => self::SLOT_TINGGI,
            'kolom' => $kolom,
            'garis' => $this->garis($tengah, $jumlahBabak),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function slotBabakPertama($slots, array $tengah, int $ukuran): array
    {
        $hasil = [];

        for ($i = 0; $i < $ukuran; $i++) {
            $slot = $slots->get($i);
            $peserta = $slot?->registration;

            $hasil[] = [
                'y' => $tengah[$i] - intdiv(self::SLOT_TINGGI, 2),
                'nomor' => $slot?->position ?? $i + 1,
                // Slot ganjil adalah sudut merah -- berlaku sebelum partainya
                // dijadwalkan, dan kontingen memakainya untuk menyiapkan sudut.
                'sudut' => $i % 2 === 0 ? 'merah' : 'biru',
                'nama' => $peserta?->athletes->pluck('name')->implode(', '),
                'kontingen' => $peserta?->contingent->name,
                'kosong' => $peserta === null,
            ];
        }

        return $hasil;
    }

    /** @return array<int, array<string, mixed>> */
    private function slotBabakLanjut(?Collection $partai, array $tengah): array
    {
        $urut = ($partai ?? collect())->sortBy('position')->values();
        $hasil = [];

        foreach ($tengah as $j => $y) {
            /*
             * Satu slot babak lanjut = satu SISI dari satu partai. Partai ke-n
             * di babak itu memuat slot 2n dan 2n+1.
             */
            $p = $urut->get(intdiv($j, 2));
            $peserta = $j % 2 === 0 ? $p?->red : $p?->blue;

            $hasil[] = [
                'y' => $y - intdiv(self::SLOT_TINGGI, 2),
                'nama' => $peserta?->athletes->pluck('name')->implode(', '),
                'kontingen' => $peserta?->contingent->name,
                'sudut' => $j % 2 === 0 ? 'merah' : 'biru',
                // Sudut baru diwarnai setelah penghuninya pasti. Mewarnai slot
                // yang masih menunggu berarti menjanjikan sesuatu yang belum
                // diputuskan.
                'menunggu' => $peserta === null,
                'bye' => $peserta !== null && $p?->bye(),
            ];
        }

        return $hasil;
    }

    /**
     * Garis penghubung antar babak: keluar dari tiap slot, menyatu, lalu masuk.
     *
     * @return array<int, array<string, mixed>>
     */
    private function garis(array $tengah, int $jumlahBabak): array
    {
        $garis = [];
        $x = self::KOLOM_AWAL;

        // Penghubung ada di ANTARA kolom, jadi jumlahnya satu kurang dari
        // jumlah babak.
        for ($r = 0; $r < $jumlahBabak - 1; $r++) {
            $separuh = intdiv(self::PENGHUBUNG, 2);

            foreach (array_chunk($tengah[$r], 2) as $j => [$atas, $bawah]) {
                $garis[] = ['jenis' => 'h', 'x' => $x, 'y' => $atas, 'panjang' => $separuh];
                $garis[] = ['jenis' => 'h', 'x' => $x, 'y' => $bawah, 'panjang' => $separuh];
                $garis[] = ['jenis' => 'v', 'x' => $x + $separuh, 'y' => $atas, 'panjang' => $bawah - $atas];
                $garis[] = ['jenis' => 'h', 'x' => $x + $separuh, 'y' => $tengah[$r + 1][$j], 'panjang' => $separuh];
            }

            $x += self::PENGHUBUNG + self::KOLOM_LANJUT;
        }

        return $garis;
    }

    public function tukar(Request $request, Tournament $tournament, WeightClass $weightClass): RedirectResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        $data = $request->validate([
            'posisi_a' => ['required', 'integer'],
            'posisi_b' => ['required', 'integer', 'different:posisi_a'],
        ], [
            'posisi_b.different' => 'Pilih dua tempat yang berbeda untuk ditukar.',
        ]);

        $bracket = $weightClass->bracket()->firstOrFail();

        try {
            $this->generator->tukar($bracket, (int) $data['posisi_a'], (int) $data['posisi_b']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tempat ditukar.');
    }

    public function kunci(Tournament $tournament, WeightClass $weightClass): RedirectResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        $bracket = $weightClass->bracket()->firstOrFail();

        try {
            $this->generator->kunci($bracket, auth()->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::catat(
            action: 'bagan.kunci',
            description: "Bagan {$weightClass->name} dikunci.",
            auditable: $bracket,
            properties: ['kelas_tanding' => $weightClass->name, 'ukuran' => $bracket->size],
        );

        return back()->with('success', "Bagan {$weightClass->name} dikunci.");
    }

    public function bukaKunci(Request $request, Tournament $tournament, WeightClass $weightClass): RedirectResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:255'],
        ]);

        $bracket = $weightClass->bracket()->firstOrFail();

        $this->generator->bukaKunci($bracket);

        AuditLog::catat(
            action: 'bagan.buka_kunci',
            description: "Bagan {$weightClass->name} dibuka kembali: {$data['alasan']}",
            auditable: $bracket,
            properties: ['kelas_tanding' => $weightClass->name, 'alasan' => $data['alasan']],
        );

        return back()->with('warning', 'Bagan dibuka kembali. Perubahan berikutnya tercatat di jejak audit.');
    }

    private function pastikanMilik(Tournament $tournament, WeightClass $weightClass): void
    {
        abort_unless($weightClass->tournament_id === $tournament->id, 404);
    }
}
