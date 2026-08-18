<?php

namespace App\Http\Controllers;

use App\Enums\ResourceAction;
use App\Models\MatchOfficial;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\ResourcePermission;
use App\Models\Role;
use App\Models\SilatMatch;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'penugasan' => $this->penugasanSaya(),
            // Ringkasan isi aplikasi hanya berarti bagi yang mengurusnya.
            // Wasit dan juri tidak punya urusan dengan jumlah permission, dan
            // menampilkannya membuat halaman depan mereka terasa salah alamat.
            'tampilkanRingkasan' => resource_allows(rk('users', ResourceAction::View)),
            'stats' => [
                ['label' => 'Pengguna', 'value' => User::count(), 'icon' => 'users'],
                ['label' => 'Role', 'value' => Role::count(), 'icon' => 'shield-check'],
                ['label' => 'Permission', 'value' => Permission::count(), 'icon' => 'key'],
                ['label' => 'Resource', 'value' => Resource::count(), 'icon' => 'file-text'],
            ],
            // Key tanpa permission berarti ada pintu yang tertutup untuk semua
            // orang tanpa penjelasan — layak muncul di halaman depan.
            'unmappedCount' => ResourcePermission::whereNull('permission_id')->count(),
            'signups' => $this->monthlySignups(),
            'activity' => $this->recentActivity(),
        ]);
    }

    /**
     * Partai tempat pengguna yang sedang login ditugaskan sebagai wasit atau juri.
     *
     * Tanpa ini, wasit dan juri yang baru login berhenti di halaman kosong:
     * panel mereka terikat ke satu partai (`/partai/{match}/juri`), dan sidebar
     * hanya bisa membentuk alamat berparameter kejuaraan — tidak ada satu
     * alamat "panel juri" yang benar tanpa tahu partai mana. Selama ini
     * alamatnya dibagikan Operator IT lewat pesan; kartu ini membuat mereka
     * menemukannya sendiri.
     *
     * Kategori Jurus tidak ikut: penampilan Jurus tidak punya penugasan per
     * orang, siapa pun berperan juri boleh menilai, dan pintu masuknya sudah
     * berdiri sebagai menu tersendiri.
     *
     * @return array<int, array<string, mixed>>
     */
    private function penugasanSaya(): array
    {
        $penugasan = MatchOfficial::query()
            ->where('user_id', auth()->id())
            ->whereHas('match', fn ($query) => $query->where('status', '!=', SilatMatch::STATUS_SELESAI))
            ->with([
                'match.arena',
                'match.red.athletes',
                'match.blue.athletes',
                'match.bracket.weightClass.tournament',
            ])
            ->get();

        return $penugasan
            ->sortBy(fn (MatchOfficial $tugas) => $tugas->match->scheduled_at ?? now()->addCentury())
            ->map(function (MatchOfficial $tugas): array {
                $match = $tugas->match;
                $tournament = $match->bracket->weightClass->tournament;

                return [
                    'sebutan' => $tugas->sebutan(),
                    'kelas' => $match->bracket->weightClass->name,
                    'kejuaraan' => $tournament->name,
                    'gelanggang' => $match->arena?->name,
                    'waktu' => $match->scheduled_at?->translatedFormat('D, d M H:i'),
                    'merah' => $match->red?->athletes->pluck('name')->implode(', '),
                    'biru' => $match->blue?->athletes->pluck('name')->implode(', '),
                    'berlangsung' => $match->status === SilatMatch::STATUS_BERLANGSUNG,
                    'url' => route(
                        $tugas->role === MatchOfficial::ROLE_WASIT
                            ? 'admin.turnamen.partai.wasit'
                            : 'admin.turnamen.partai.juri',
                        [$tournament, $match],
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Pengguna baru per bulan, 6 bulan terakhir. Dihitung per bulan lewat
     * `whereBetween`, bukan `groupBy` tanggal mentah, supaya hasilnya sama di
     * MySQL maupun SQLite (yang dipakai saat pengujian).
     *
     * @return array<int, array<string, mixed>>
     */
    private function monthlySignups(): array
    {
        $months = collect(range(5, 0))->map(fn (int $ago) => now()->subMonths($ago)->startOfMonth());

        return $months->map(fn (Carbon $month) => [
            'label' => $month->translatedFormat('M'),
            'value' => User::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count(),
        ])->all();
    }

    /**
     * Catatan terbaru lintas modul, digabung dan diurutkan ulang di memori.
     * Bukan audit log sungguhan — cukup untuk menunjukkan sesuatu sedang
     * terjadi di aplikasi.
     *
     * @return array<int, array<string, string>>
     */
    private function recentActivity(): array
    {
        $entries = collect()
            ->concat(User::latest()->take(3)->get()->map(fn (User $user) => [
                'text' => "Pengguna {$user->name} ditambahkan",
                'at' => $user->created_at,
            ]))
            ->concat(Role::latest()->take(3)->get()->map(fn (Role $role) => [
                'text' => "Role {$role->name} dibuat",
                'at' => $role->created_at,
            ]))
            ->concat(Resource::latest()->take(3)->get()->map(fn (Resource $resource) => [
                'text' => "Resource {$resource->key} dibuat",
                'at' => $resource->created_at,
            ]));

        return $entries
            ->filter(fn (array $entry) => $entry['at'] !== null)
            ->sortByDesc('at')
            ->take(6)
            ->map(fn (array $entry) => [
                'text' => $entry['text'],
                'time' => $entry['at']->diffForHumans(),
            ])
            ->values()
            ->all();
    }
}
