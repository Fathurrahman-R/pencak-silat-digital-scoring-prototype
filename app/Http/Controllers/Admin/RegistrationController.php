<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Concerns\ScopesContingents;
use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusEvent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Pendaftaran\PeriksaKelayakan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    use ScopesContingents;

    public function __construct(private readonly PeriksaKelayakan $periksa) {}

    public function index(Tournament $tournament, Contingent $contingent): View
    {
        $this->pastikanBolehAkses($contingent);

        $athletes = $contingent->athletes()->orderBy('name')->get();

        return view('admin.pendaftaran.index', [
            'tournament' => $tournament,
            'contingent' => $contingent,
            'registrations' => $contingent->registrations()
                ->with(['athletes', 'weightClass', 'jurusEvent'])
                ->latest('id')
                ->get(),
            'athletes' => $athletes,

            /*
             * Kelas yang cocok dihitung di server per atlet, lalu dipakai
             * formulir untuk menyaring pilihannya. Membiarkan official memilih
             * dari 174 kelas lalu ditolak validasi adalah cara paling cepat
             * membuat orang berhenti memakai sistemnya.
             */
            'kelasPerAtlet' => $athletes->mapWithKeys(fn (Athlete $a) => [
                $a->id => $this->periksa->kelasYangCocok($a, $contingent)
                    ->map(fn (WeightClass $k) => [
                        'id' => $k->id,
                        'label' => $k->name.' — '.$k->rentang(),
                    ])->all(),
            ]),

            'nomorJurus' => $tournament->jurusEvents()->aktif()->get(),
        ]);
    }

    public function storeTanding(Request $request, Tournament $tournament, Contingent $contingent): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);
        $this->pastikanTidakBeku($contingent);

        $data = $request->validate([
            'athlete_id' => ['required', 'integer'],
            'weight_class_id' => ['required', 'integer'],
        ], attributes: ['athlete_id' => 'Atlet', 'weight_class_id' => 'Kelas']);

        $athlete = $contingent->athletes()->findOrFail($data['athlete_id']);
        $kelas = $tournament->weightClasses()->findOrFail($data['weight_class_id']);

        $hasil = $this->periksa->untukKelasTanding($kelas, [$athlete]);

        if ($hasil->ditolakSemua()) {
            throw ValidationException::withMessages(['weight_class_id' => $hasil->alasan]);
        }

        DB::transaction(function () use ($contingent, $kelas, $athlete) {
            $pendaftaran = $contingent->registrations()->create([
                'weight_class_id' => $kelas->id,
                'status' => StatusPendaftaran::Draf,
            ]);

            $pendaftaran->athletes()->attach($athlete, ['position' => 1]);
        });

        return back()->with('success', "{$athlete->name} terdaftar di {$kelas->name}.");
    }

    public function storeJurus(Request $request, Tournament $tournament, Contingent $contingent): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);
        $this->pastikanTidakBeku($contingent);

        $data = $request->validate([
            'jurus_event_id' => ['required', 'integer'],
            'athlete_ids' => ['required', 'array', 'min:1', 'max:3'],
            'athlete_ids.*' => ['integer'],
        ], attributes: ['jurus_event_id' => 'Nomor', 'athlete_ids' => 'Pesilat']);

        $nomor = $tournament->jurusEvents()->findOrFail($data['jurus_event_id']);

        // Diambil lewat relasi kontingen, bukan lewat Athlete::find, supaya
        // id atlet kontingen lain yang disisipkan ke formulir tidak pernah
        // sampai ke pemeriksaan kelayakan.
        $athletes = $contingent->athletes()->whereIn('id', $data['athlete_ids'])->get();

        $hasil = $this->periksa->untukNomorJurus($nomor, $athletes);

        if ($hasil->ditolakSemua()) {
            throw ValidationException::withMessages(['jurus_event_id' => $hasil->alasan]);
        }

        DB::transaction(function () use ($contingent, $nomor, $athletes) {
            $pendaftaran = $contingent->registrations()->create([
                'jurus_event_id' => $nomor->id,
                'status' => StatusPendaftaran::Draf,
            ]);

            foreach ($athletes->values() as $urutan => $athlete) {
                $pendaftaran->athletes()->attach($athlete, ['position' => $urutan + 1]);
            }
        });

        return back()->with('success', "Pendaftaran {$nomor->nama()} tersimpan.");
    }

    /**
     * Mengajukan pendaftaran ke panitia.
     *
     * Kelengkapan berkas diperiksa di sini, bukan saat pendaftaran dibuat.
     * Official lazim mendaftarkan atlet lebih dulu lalu menyusulkan surat
     * sehatnya — memaksakan berkas lengkap sejak awal berarti pekerjaan
     * pendaftaran tidak bisa dimulai sampai dokter selesai.
     */
    public function submit(Tournament $tournament, Contingent $contingent, Registration $registration): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);
        $this->pastikanMilik($contingent, $registration);

        abort_unless($registration->status->bolehDisuntingKontingen(), 403);

        $kurang = [];

        foreach ($registration->athletes as $athlete) {
            foreach ($athlete->berkasKurang($tournament) as $jenis) {
                $kurang[] = "{$athlete->name}: {$jenis->label()}";
            }
        }

        if ($kurang !== []) {
            throw ValidationException::withMessages([
                'berkas' => ['Berkas wajib belum lengkap — '.implode('; ', $kurang).'.'],
            ]);
        }

        $registration->update([
            'status' => StatusPendaftaran::Diajukan,
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Pendaftaran diajukan ke panitia.');
    }

    public function destroy(Tournament $tournament, Contingent $contingent, Registration $registration): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);
        $this->pastikanMilik($contingent, $registration);

        // Pendaftaran yang sudah diperiksa panitia tidak boleh hilang begitu
        // saja dari catatan; yang menariknya kembali harus panitia.
        abort_unless(
            $registration->status->bolehDisuntingKontingen() || $this->bolehLihatSemuaKontingen(),
            403,
        );

        $registration->delete();

        return back()->with('success', 'Pendaftaran dibatalkan.');
    }

    private function pastikanMilik(Contingent $contingent, Registration $registration): void
    {
        abort_unless($registration->contingent_id === $contingent->id, 404);
    }

    /**
     * Pendaftaran dibekukan begitu sesi pembayaran dibuat.
     *
     * Ditolak dengan pesan, bukan dengan halaman galat, karena ini keadaan yang
     * wajar dan bisa diperbaiki sendiri official — tinggal batalkan sesi
     * pembayarannya kalau memang masih mau menambah atlet.
     */
    private function pastikanTidakBeku(Contingent $contingent): void
    {
        if (! $contingent->pendaftaranBeku()) {
            return;
        }

        $status = $contingent->invoice->status->label();

        throw ValidationException::withMessages([
            'pendaftaran' => [
                "Pendaftaran dibekukan karena tagihan berstatus {$status}. "
                .'Batalkan sesi pembayaran lebih dulu bila masih ingin menambah peserta.',
            ],
        ]);
    }
}
