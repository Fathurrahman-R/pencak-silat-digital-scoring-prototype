<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisBerkas;
use App\Http\Controllers\Concerns\ScopesContingents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAthleteRequest;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\RegistrationDocument;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AthleteController extends Controller
{
    use ScopesContingents;

    /**
     * Berkas peserta disimpan di disk privat, bukan di storage publik.
     *
     * Isinya akta kelahiran dan surat keterangan sehat — dokumen pribadi anak
     * di bawah umur. Menaruhnya di direktori yang bisa diakses lewat URL
     * berarti siapa pun yang menebak nama berkasnya bisa mengunduhnya tanpa
     * login, dan kejuaraan ini sengaja mengekspos sebagian dirinya ke internet
     * lewat tunnel halaman siaran langsung.
     */
    private const DISK = 'local';

    public function index(Tournament $tournament, Contingent $contingent): View
    {
        $this->pastikanBolehAkses($contingent);

        return view('admin.atlet.index', [
            'tournament' => $tournament,
            'contingent' => $contingent,
            'athletes' => $contingent->athletes()
                ->with('documents')
                ->withCount('registrations')
                ->search(request('q'))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function store(StoreAthleteRequest $request, Tournament $tournament, Contingent $contingent): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);

        $athlete = $contingent->athletes()->create($request->validated());

        return redirect()
            ->route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent])
            ->with('success', "Atlet “{$athlete->name}” ditambahkan.");
    }

    public function update(StoreAthleteRequest $request, Tournament $tournament, Contingent $contingent, Athlete $athlete): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);
        $this->pastikanMilik($contingent, $athlete);

        $athlete->update($request->validated());

        return redirect()
            ->route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent])
            ->with('success', "Atlet “{$athlete->name}” diperbarui.");
    }

    public function destroy(Tournament $tournament, Contingent $contingent, Athlete $athlete): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);
        $this->pastikanMilik($contingent, $athlete);

        $athlete->delete();

        return redirect()
            ->route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent])
            ->with('success', 'Atlet dihapus.');
    }

    public function storeDocument(Request $request, Tournament $tournament, Contingent $contingent, Athlete $athlete): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);
        $this->pastikanMilik($contingent, $athlete);

        $data = $request->validate([
            'jenis' => ['required', 'string', 'in:'.implode(',', array_column(JenisBerkas::cases(), 'value'))],
            'berkas' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,pdf'],
        ], [
            'berkas.max' => 'Ukuran berkas paling besar 4 MB. Foto dari kamera ponsel biasanya perlu dikecilkan lebih dulu.',
            'berkas.mimes' => 'Berkas harus berupa JPG, PNG, atau PDF.',
        ], [
            'jenis' => 'Jenis berkas',
            'berkas' => 'Berkas',
        ]);

        $berkas = $data['berkas'];
        $jenis = JenisBerkas::from($data['jenis']);

        $path = $berkas->store("peserta/{$tournament->id}/{$athlete->id}", self::DISK);

        // Satu jenis berkas satu berlaku: unggahan baru menggantikan yang lama,
        // supaya panitia tidak perlu menebak mana surat sehat yang berlaku.
        $lama = $athlete->documents()->where('jenis', $jenis)->get();

        $athlete->documents()->create([
            'jenis' => $jenis,
            'path' => $path,
            'original_name' => $berkas->getClientOriginalName(),
            'size_bytes' => $berkas->getSize(),
            'mime' => $berkas->getClientMimeType(),
            'uploaded_by' => auth()->id(),
        ]);

        foreach ($lama as $berkasLama) {
            Storage::disk(self::DISK)->delete($berkasLama->path);
            $berkasLama->delete();
        }

        return back()->with('success', "{$jenis->label()} untuk {$athlete->name} tersimpan.");
    }

    public function showDocument(Tournament $tournament, Contingent $contingent, Athlete $athlete, RegistrationDocument $document): StreamedResponse
    {
        $this->pastikanBolehAkses($contingent);
        $this->pastikanMilik($contingent, $athlete);

        abort_unless($document->athlete_id === $athlete->id, 404);
        abort_unless(Storage::disk(self::DISK)->exists($document->path), 404);

        // Ditampilkan lewat aplikasi, bukan lewat tautan langsung ke disk,
        // supaya tiap kali dibuka tetap melewati pemeriksaan hak akses.
        return Storage::disk(self::DISK)->response($document->path, $document->original_name);
    }

    public function destroyDocument(Tournament $tournament, Contingent $contingent, Athlete $athlete, RegistrationDocument $document): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);
        $this->pastikanMilik($contingent, $athlete);

        abort_unless($document->athlete_id === $athlete->id, 404);

        Storage::disk(self::DISK)->delete($document->path);
        $document->delete();

        return back()->with('success', 'Berkas dihapus.');
    }

    private function pastikanMilik(Contingent $contingent, Athlete $athlete): void
    {
        abort_unless($athlete->contingent_id === $contingent->id, 404);
    }
}
