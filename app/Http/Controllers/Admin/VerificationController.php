<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pintu terakhir sebelum peserta masuk bagan.
 *
 * Dua syarat harus dipenuhi bersamaan: berkas persyaratannya lengkap, dan
 * tagihan kontingennya lunas. Keduanya diperiksa di sini, bukan hanya
 * disembunyikan tombolnya — panel ini yang menentukan siapa berhak bertanding,
 * dan satu permintaan yang disusun tangan tidak boleh cukup untuk melewatinya.
 */
class VerificationController extends Controller
{
    public function index(Request $request, Tournament $tournament): View
    {
        $status = $request->string('status')->toString() ?: StatusPendaftaran::Diajukan->value;

        $registrations = Registration::query()
            ->whereHas('contingent', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->with(['athletes.documents', 'contingent.invoice', 'weightClass', 'jurusEvent', 'verifier'])
            ->get()
            ->sortBy(fn (Registration $r): string => $r->contingent->name.$r->namaNomor())
            ->values();

        return view('admin.verifikasi.index', [
            'tournament' => $tournament,
            'registrations' => $registrations,
            'status' => $status,
            'statuses' => StatusPendaftaran::options(),
            'jumlahMenunggu' => Registration::query()
                ->whereHas('contingent', fn ($query) => $query->where('tournament_id', $tournament->id))
                ->where('status', StatusPendaftaran::Diajukan)
                ->count(),
        ]);
    }

    public function setujui(Tournament $tournament, Registration $registration): RedirectResponse
    {
        $this->pastikanMilik($tournament, $registration);
        $this->pastikanMenunggu($registration);
        $this->pastikanLunas($registration);
        $this->pastikanBerkasLengkap($registration, $tournament);

        DB::transaction(function () use ($registration) {
            $registration->update([
                'status' => StatusPendaftaran::Terverifikasi,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            AuditLog::catat(
                action: 'pendaftaran.verifikasi',
                description: "Pendaftaran {$registration->namaNomor()} disahkan.",
                auditable: $registration,
                properties: [
                    'kontingen' => $registration->contingent->name,
                    'pesilat' => $registration->athletes->pluck('name')->all(),
                ],
            );
        });

        return back()->with('success', "Pendaftaran {$registration->namaNomor()} disahkan.");
    }

    public function tolak(Request $request, Tournament $tournament, Registration $registration): RedirectResponse
    {
        $this->pastikanMilik($tournament, $registration);
        $this->pastikanMenunggu($registration);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min' => 'Alasan penolakan harus cukup jelas untuk bisa diperbaiki '
                .'kontingen — paling sedikit 10 karakter.',
        ], ['rejection_reason' => 'Alasan penolakan']);

        DB::transaction(function () use ($registration, $data) {
            $registration->update([
                'status' => StatusPendaftaran::Ditolak,
                'rejection_reason' => $data['rejection_reason'],
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);

            AuditLog::catat(
                action: 'pendaftaran.tolak',
                description: "Pendaftaran {$registration->namaNomor()} ditolak.",
                auditable: $registration,
                properties: [
                    'kontingen' => $registration->contingent->name,
                    'alasan' => $data['rejection_reason'],
                ],
            );
        });

        return back()->with('success', 'Pendaftaran ditolak beserta alasannya.');
    }

    /**
     * Mengembalikan pendaftaran yang sudah diperiksa ke antrean.
     *
     * Panitia keliru memutus adalah hal yang terjadi, dan memperbaikinya harus
     * meninggalkan jejak — bukan tampak seolah keputusan pertama tidak pernah
     * ada.
     */
    public function tinjauUlang(Tournament $tournament, Registration $registration): RedirectResponse
    {
        $this->pastikanMilik($tournament, $registration);

        abort_if($registration->status === StatusPendaftaran::Diajukan, 403);

        $sebelumnya = $registration->status;

        DB::transaction(function () use ($registration, $sebelumnya) {
            $registration->update([
                'status' => StatusPendaftaran::Diajukan,
                'verified_by' => null,
                'verified_at' => null,
                'rejection_reason' => null,
            ]);

            AuditLog::catat(
                action: 'pendaftaran.tinjau_ulang',
                description: "Pendaftaran {$registration->namaNomor()} dikembalikan ke antrean verifikasi.",
                auditable: $registration,
                properties: ['status_sebelumnya' => $sebelumnya->value],
            );
        });

        return back()->with('success', 'Pendaftaran dikembalikan ke antrean verifikasi.');
    }

    private function pastikanMilik(Tournament $tournament, Registration $registration): void
    {
        abort_unless($registration->contingent->tournament_id === $tournament->id, 404);
    }

    private function pastikanMenunggu(Registration $registration): void
    {
        if ($registration->status === StatusPendaftaran::Diajukan) {
            return;
        }

        throw ValidationException::withMessages([
            'verifikasi' => "Pendaftaran ini berstatus {$registration->status->label()}, "
                .'bukan menunggu pemeriksaan. Kembalikan ke antrean lebih dulu bila memang perlu ditinjau ulang.',
        ]);
    }

    /**
     * Pendaftaran tidak bisa disahkan sebelum tagihan kontingennya lunas.
     *
     * Ini satu-satunya hal yang memaksa pembayaran benar-benar terjadi. Kalau
     * verifikasi bisa jalan tanpanya, tagihan hanya jadi catatan yang boleh
     * diabaikan sampai kejuaraan usai.
     */
    private function pastikanLunas(Registration $registration): void
    {
        $invoice = $registration->contingent->invoice;

        if ($invoice?->lunas()) {
            return;
        }

        $status = $invoice?->status->label() ?? 'belum terbit';

        throw ValidationException::withMessages([
            'verifikasi' => "Tagihan kontingen {$registration->contingent->name} berstatus {$status}. "
                .'Pendaftaran baru bisa disahkan setelah tagihannya lunas.',
        ]);
    }

    private function pastikanBerkasLengkap(Registration $registration, Tournament $tournament): void
    {
        $kurang = [];

        foreach ($registration->athletes as $athlete) {
            foreach ($athlete->berkasKurang($tournament) as $jenis) {
                $kurang[] = "{$athlete->name}: {$jenis->label()}";
            }
        }

        if ($kurang === []) {
            return;
        }

        throw ValidationException::withMessages([
            'verifikasi' => 'Berkas wajib belum lengkap — '.implode('; ', $kurang).'.',
        ]);
    }
}
