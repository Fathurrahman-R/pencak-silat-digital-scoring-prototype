<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModeBagan;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Bagan\BracketGenerator;
use App\Support\Bagan\PohonBagan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
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
    public function __construct(
        private readonly BracketGenerator $generator,
        private readonly PohonBagan $pohon,
    ) {}

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

    public function susun(Request $request, Tournament $tournament, WeightClass $weightClass): RedirectResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        /*
         * Mode dipilih saat menyusun, bukan disimpan sebagai setelan
         * kejuaraan: satu kejuaraan lazim memakai pemasalan untuk usia dini
         * dan gugur untuk dewasa, di hari yang sama. Bawaannya gugur --
         * bentuk yang dikenal pembaca bagan -- jadi tombol lama yang tidak
         * mengirim apa pun tetap menghasilkan bagan yang sama seperti dulu.
         */
        $data = $request->validate([
            'mode' => ['sometimes', Rule::enum(ModeBagan::class)],
        ], attributes: ['mode' => 'Mode bagan']);

        $mode = ModeBagan::tryFrom($data['mode'] ?? '') ?? ModeBagan::Gugur;

        try {
            $this->generator->untukKelas($weightClass, mode: $mode);
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
            'pohon' => ($this->pohon)($bracket),
            /*
             * Peserta sah SEKARANG, bukan jumlah tempat yang terisi di bagan.
             * Keduanya bisa berbeda: seorang pesilat bisa gugur di timbang
             * badan setelah bagan disusun, dan dialog susun ulang menjanjikan
             * undian dari peserta yang ada saat tombolnya ditekan.
             */
            'pesertaSah' => $this->generator->pesertaSah($weightClass)->count(),
        ]);
    }

    /**
     * Bagan siap cetak.
     *
     * Ukuran kertas dihitung dari pohonnya, bukan dipatok A4. Bagan 16 tempat
     * selebar 1160 piksel; dipaksa masuk A4 lanskap, nama pesilat mengecil
     * sampai delapan piksel dan bagan yang dipaku di papan pengumuman tidak
     * terbaca dari jarak berdiri. Kertas yang mengikuti pohonnya membuat nama
     * tetap seukuran layar, dan peramban maupun mesin cetak sudah punya
     * "sesuaikan halaman" untuk mengecilkannya kalau kertasnya memang cuma A4.
     */
    public function cetak(Tournament $tournament, WeightClass $weightClass): HttpResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        $bracket = $weightClass->bracket()->with([
            'slots.registration.athletes',
            'slots.registration.contingent',
            'matches.red.athletes',
            'matches.red.contingent',
            'matches.blue.athletes',
            'matches.blue.contingent',
            'locker',
        ])->first();

        abort_unless($bracket, 404);

        $pohon = ($this->pohon)($bracket);

        // Piksel ke titik: 96 dpi layar, 72 titik per inci kertas.
        $keTitik = fn (float $px): float => round($px * 0.75, 1);

        $lebar = $keTitik($pohon['lebar'] + self::MARGIN_CETAK * 2);
        $tinggi = $keTitik($pohon['tinggi'] + self::KEPALA_CETAK + self::MARGIN_CETAK * 2);

        $pdf = Pdf::loadView('admin.bagan.cetak-pdf', [
            'tournament' => $tournament,
            'weightClass' => $weightClass,
            'bracket' => $bracket,
            'pohon' => $pohon,
            'margin' => self::MARGIN_CETAK,
            'kepala' => self::KEPALA_CETAK,
        ])->setPaper([0, 0, $lebar, $tinggi]);

        /*
         * Jangan disimpan peramban. Alamatnya tetap sama sepanjang kejuaraan
         * sementara isinya berubah tiap undian ditukar, tempat diisi, atau
         * bagan disusun ulang -- dan peramban yang menyajikan salinan lama
         * memberi panitia bagan yang sudah usang tanpa satu pun tanda. Itu
         * juga yang membuat perbaikan tampilan cetak seolah tidak berlaku:
         * yang terbuka berkas kemarin, bukan yang baru digambar.
         */
        return $pdf->stream('bagan-'.str($weightClass->namaLengkap())->slug().'.pdf')
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
    }

    /** Tepi kertas di sekeliling pohon, dalam piksel pohon. */
    private const MARGIN_CETAK = 32;

    /** Ruang kepala halaman di atas pohon, dalam piksel pohon. */
    private const KEPALA_CETAK = 72;

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

        /*
         * Ditolak di sini juga, bukan cuma saat penyusunan ulang.
         *
         * Bagan yang terbuka menampilkan tombol "Susun ulang" dan formulir
         * tukar tempat, dan keduanya menjanjikan sesuatu yang tidak akan
         * dikerjakan pada bagan yang partainya sudah dinilai. Pengendali yang
         * membuka kunci lalu menekan Susun ulang baru mengetahuinya dari
         * penolakan -- sesudah bagan berstatus terbuka di layar semua orang.
         */
        if ($this->generator->adaHasil($bracket)) {
            return back()->with(
                'error',
                "Bagan {$weightClass->name} sudah punya partai yang dinilai atau disahkan, jadi kuncinya tidak dibuka. "
                .'Batalkan hasil partainya lewat Dewan Wasit Juri lebih dulu bila undiannya memang harus diulang.',
            );
        }

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
