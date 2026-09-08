<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesContingents;
use App\Http\Controllers\Controller;
use App\Models\Contingent;
use App\Models\Tournament;
use App\Support\Pendaftaran\ImporPendaftaran;
use App\Support\Unggah\BatasUnggah;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor daftar peserta satu kontingen dari CSV atau Google Spreadsheet.
 *
 * Dua langkah, dan langkah pertamanya tidak bisa dilewati: berkas dibaca dan
 * dijalankan lebih dulu sebagai PRATINJAU -- impor sungguhan yang diputar
 * balik -- lalu panitia menekan Terapkan pada hasil yang persis sama. Daftar
 * peserta adalah dasar seluruh bagan; berkas yang salah kolom tidak boleh
 * ketahuan setelah tiga ratus baris masuk.
 *
 * Berkas yang sudah diunggah disimpan sementara supaya langkah kedua tidak
 * menuntut unggah ulang. Ia dibuang begitu diterapkan, dan `model:prune`
 * harian menyapu yang tertinggal karena pratinjaunya ditinggalkan.
 */
class ImporPendaftaranController extends Controller
{
    use ScopesContingents;

    private const DISK = 'local';

    private const FOLDER = 'impor-pendaftaran';

    public function __construct(private readonly ImporPendaftaran $impor) {}

    public function form(Tournament $tournament, Contingent $contingent): View
    {
        $this->pastikanBolehAkses($contingent);

        return view('admin.pendaftaran.impor', [
            'tournament' => $tournament,
            'contingent' => $contingent,
            'hasil' => null,
            'token' => null,
        ]);
    }

    /** Berkas contoh, sekaligus penjelasan bentuk kolom yang paling tidak bisa salah dibaca. */
    public function contoh(Tournament $tournament, Contingent $contingent): StreamedResponse
    {
        $this->pastikanBolehAkses($contingent);

        $isi = $this->impor->berkasContoh();
        $nama = 'contoh-impor-peserta-'.Str::slug($contingent->name).'.csv';

        return response()->streamDownload(fn () => print($isi), $nama, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Langkah 1: baca, jalankan, putar balik, tampilkan hasilnya.
     */
    public function pratinjau(Request $request, Tournament $tournament, Contingent $contingent): View|RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);

        if ($beku = $this->tolakBilaBeku($contingent)) {
            return $beku;
        }

        $batas = BatasUnggah::kilobyte(2048);

        $data = $request->validate([
            'berkas' => ['nullable', 'file', 'max:'.$batas, 'mimes:csv,txt'],
            'url' => ['nullable', 'string', 'max:2048'],
        ], [
            'berkas.mimes' => 'Berkasnya harus CSV. Di Excel pilih “Simpan sebagai → CSV (Comma delimited)”, '
                .'di Google Spreadsheet pilih “Berkas → Unduh → Nilai yang dipisahkan koma”.',
            'berkas.max' => 'Ukuran berkas paling besar '.BatasUnggah::label($batas).'.',
        ], [
            'berkas' => 'Berkas CSV',
            'url' => 'Alamat spreadsheet',
        ]);

        if (($data['berkas'] ?? null) === null && trim((string) ($data['url'] ?? '')) === '') {
            return back()->withErrors(['berkas' => 'Unggah berkas CSV, atau tempelkan alamat Google Spreadsheet.']);
        }

        try {
            $isi = ($data['berkas'] ?? null) !== null
                ? (string) file_get_contents($data['berkas']->getRealPath())
                : $this->impor->unduh($this->impor->alamatEksporSpreadsheet(trim((string) $data['url'])));

            $baris = $this->impor->baca($isi);
        } catch (RuntimeException $e) {
            return back()->withErrors(['berkas' => $e->getMessage()]);
        }

        if ($baris === []) {
            return back()->withErrors(['berkas' => 'Tidak ada satu pun baris isi di bawah baris kepala.']);
        }

        $hasil = $this->impor->jalankan($tournament, $contingent, $baris, simpan: false);

        // Disimpan APA ADANYA, bukan hasil pembacaannya: langkah kedua membaca
        // ulang dari nol supaya keduanya benar-benar menjalankan jalur yang sama.
        $token = (string) Str::ulid();
        Storage::disk(self::DISK)->put($this->jalur($contingent, $token), $isi);

        return view('admin.pendaftaran.impor', [
            'tournament' => $tournament,
            'contingent' => $contingent,
            'hasil' => $hasil,
            'token' => $token,
        ]);
    }

    /**
     * Langkah 2: baca berkas yang sama, jalankan, simpan.
     */
    public function terapkan(Request $request, Tournament $tournament, Contingent $contingent): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);

        if ($beku = $this->tolakBilaBeku($contingent)) {
            return $beku;
        }

        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);

        $jalur = $this->jalur($contingent, $data['token']);

        if (! Storage::disk(self::DISK)->exists($jalur)) {
            return back()->withErrors([
                'berkas' => 'Berkas pratinjaunya sudah tidak ada. Unggah ulang berkasnya, '
                    .'lalu terapkan dari halaman pratinjau yang baru.',
            ]);
        }

        try {
            $baris = $this->impor->baca((string) Storage::disk(self::DISK)->get($jalur));
        } catch (RuntimeException $e) {
            return back()->withErrors(['berkas' => $e->getMessage()]);
        }

        $hasil = $this->impor->jalankan($tournament, $contingent, $baris, simpan: true);

        Storage::disk(self::DISK)->delete($jalur);

        $r = $hasil['ringkas'];

        $pesan = "Impor selesai: {$r['atlet_baru']} atlet baru, {$r['atlet_lama']} atlet dipakai ulang, "
            ."{$r['nomor']} pendaftaran nomor dibuat.";

        $redirect = redirect()
            ->route('admin.turnamen.kontingen.pendaftaran.index', [$tournament, $contingent])
            ->with('success', $pesan);

        return $r['ditolak'] === 0 && $r['catatan'] === 0
            ? $redirect
            : $redirect->with('warning', "{$r['ditolak']} baris ditolak dan {$r['catatan']} baris punya catatan — "
                .'periksa halaman Pendaftaran nomor untuk yang belum masuk.');
    }

    /**
     * Impor tunduk pada pembekuan yang sama dengan tombol Daftarkan.
     *
     * Tanpa ini, impor jadi pintu belakang: tagihan sudah dikunci pada nominal
     * delapan nomor, lalu satu berkas CSV menambah tiga puluh nomor lagi yang
     * tidak pernah ditagih. Diperiksa di KEDUA langkah -- pratinjau ditolak
     * supaya panitia tahu sebelum menyiapkan berkasnya, dan penerapan ditolak
     * lagi karena tagihan bisa dikunci di antara dua langkah itu.
     */
    private function tolakBilaBeku(Contingent $contingent): ?RedirectResponse
    {
        if (! $contingent->pendaftaranBeku()) {
            return null;
        }

        return back()->withErrors([
            'berkas' => 'Pendaftaran dibekukan karena tagihan berstatus '
                .$contingent->invoice->status->label()
                .'. Batalkan sesi pembayaran lebih dulu bila daftar peserta masih harus diubah.',
        ]);
    }

    /** Berkas pratinjau dikurung per kontingen supaya token satu kontingen tidak berlaku di kontingen lain. */
    private function jalur(Contingent $contingent, string $token): string
    {
        return self::FOLDER."/{$contingent->id}/".basename($token).'.csv';
    }
}
