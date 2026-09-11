<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArenaRequest;
use App\Http\Requests\Admin\UpdateArenaRequest;
use App\Models\Arena;
use App\Models\ArenaOfficial;
use App\Models\Tournament;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gelanggang selalu hidup di dalam satu kejuaraan.
 *
 * Route-nya bersarang, dan tiap aksi memastikan gelanggang yang disebut
 * memang milik kejuaraan di alamatnya — kalau tidak, mengganti satu angka di
 * URL berarti menyunting gelanggang kejuaraan lain.
 */
class ArenaController extends Controller
{
    public function index(Tournament $tournament): View
    {
        return view('admin.gelanggang.index', [
            'tournament' => $tournament,
            'arenas' => $tournament->arenas()
                ->with(['operators:id,name', 'pengendali:id,name', 'aparat.user:id,name'])
                ->get(),
            // Daftar calon dipakai modal penugasan. Yang nonaktif tidak
            // ditawarkan -- akunnya tidak bisa masuk.
            'calonOperator' => $this->calonBerperan('operator-it'),
            'calonPengendali' => $this->calonBerperan('pengendali-gelanggang'),

            /*
             * Kursi matras. Wasit, Dewan Wasit Juri, Komisi Protes, dan Ketua
             * sama-sama berperan `ketua-pertandingan` sejak ketiganya lebur ke
             * sana -- yang membedakan kursinya, dan nama akunnya ("Wasit
             * Gelanggang A").
             */
            'calonKetua' => $this->calonBerperan('ketua-pertandingan'),
            'calonJuri' => $this->calonBerperan('juri'),
            /*
             * Kejuaraan yang setelan peraturannya belum tersusun tetap harus
             * bisa membuka halaman ini -- gelanggang disiapkan lebih dulu, dan
             * yang meledak di sini akan meledak tepat di layar pertama panitia.
             */
            // Tiga adalah bawaan kolomnya di migrasi, bukan angka karangan.
            'jumlahJuri' => $tournament->ruleSetting()->value('jumlah_juri_tanding') ?? 3,
        ]);
    }

    /** @return Collection<int, User> */
    private function calonBerperan(string $peran): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', $peran))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function store(StoreArenaRequest $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validated();

        // Gelanggang baru masuk ke urutan paling belakang bila panitia tidak
        // menentukannya sendiri.
        $data['sort_order'] ??= (int) $tournament->arenas()->max('sort_order') + 1;

        $arena = $tournament->arenas()->create($data);

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', "Gelanggang “{$arena->name}” ditambahkan.");
    }

    public function update(UpdateArenaRequest $request, Tournament $tournament, Arena $arena): RedirectResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $arena->update($request->validated());

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', "Gelanggang “{$arena->name}” diperbarui.");
    }

    /**
     * Menetapkan siapa saja operator gelanggang ini.
     *
     * Operator ditugaskan per gelanggang, bukan per partai: ia duduk di satu
     * gelanggang sepanjang hari, dan penugasannya ikut berlaku untuk partai
     * yang baru dijadwalkan ke sini kemudian. Tanpa penugasan ini, panel
     * gelanggang menolak seluruh aksinya.
     */
    public function simpanOperator(Request $request, Tournament $tournament, Arena $arena): RedirectResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $data = $request->validate([
            'operator_id' => ['nullable', 'array'],
            'operator_id.*' => [
                'required',
                Rule::exists('users', 'id')->where('is_active', true),
                // Peran diperiksa di sini, bukan cuma keberadaan penggunanya:
                // menugaskan bendahara jadi operator gelanggang akan lolos
                // kalau yang dicek hanya `exists`.
                function (string $atribut, mixed $nilai, Closure $gagal) {
                    if (! User::find($nilai)?->hasRole('operator-it')) {
                        $gagal('Pengguna yang dipilih bukan Operator IT.');
                    }
                },
            ],
        ]);

        $arena->operators()->sync($data['operator_id'] ?? []);

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', "Operator “{$arena->name}” diperbarui.");
    }

    /**
     * Pengendali gelanggang -- cermin simpanOperator(), peran yang berbeda.
     *
     * Dipisah, bukan digabung dengan satu formulir bertingkat: keduanya
     * memegang gelanggang yang sama tapi menjawab pertanyaan berbeda. Operator
     * menjalankan papan tampilan dan siaran; pengendali memimpin jalannya
     * partai. Menugaskan orang yang salah pada yang kedua berarti timer
     * gelanggang dipegang orang yang tidak duduk di sana.
     */
    public function simpanPengendali(Request $request, Tournament $tournament, Arena $arena): RedirectResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $data = $request->validate([
            'pengendali_id' => ['nullable', 'array'],
            'pengendali_id.*' => [
                'required',
                Rule::exists('users', 'id')->where('is_active', true),
                function (string $atribut, mixed $nilai, Closure $gagal) {
                    if (! User::find($nilai)?->hasRole('pengendali-gelanggang')) {
                        $gagal('Pengguna yang dipilih bukan Pengendali Gelanggang.');
                    }
                },
            ],
        ]);

        $arena->pengendali()->sync($data['pengendali_id'] ?? []);

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', "Pengendali “{$arena->name}” diperbarui.");
    }

    /**
     * Aparat yang bertugas di gelanggang ini SEPANJANG HARI.
     *
     * Inilah satu-satunya tempat penugasan aparat sekarang. Menugaskan partai
     * demi partai adalah beban yang sama besarnya untuk meja panitia -- empat
     * baris dikali empat puluh partai -- padahal orang yang duduk di kursi
     * juri 1 Gelanggang A pagi ini akan duduk di sana sampai sore.
     *
     * `match_officials` tidak digantikan: saat pengendali menunjuk sebuah
     * partai, baris di sini disalin ke sana (PointerTayang::salinAparatGelanggang).
     * Empat jalur bergantung pada salinan itu -- nomor juri pada tiap nilai
     * masuk, otorisasi tiap tekanan tombol, penjawab polling verifikasi, dan
     * berita acara -- dan semuanya tetap membaca catatan PER PARTAI, bukan
     * menyimpulkannya belakangan dari penugasan gelanggang yang bisa berubah
     * di tengah hari.
     *
     * Kursi kosong diperbolehkan. Gelanggang yang juri ketiganya belum datang
     * tetap harus bisa disimpan; yang menolak partai berjalan tanpa juri
     * lengkap adalah penjagaan di panel, bukan formulir ini.
     */
    public function simpanAparat(Request $request, Tournament $tournament, Arena $arena): RedirectResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $jumlahJuri = $tournament->ruleSetting()->value('jumlah_juri_tanding') ?? 3;

        $data = $request->validate([
            'wasit_id' => ['nullable', $this->calonBerperanRule('ketua-pertandingan', 'Wasit')],
            'dewan_id' => ['nullable', $this->calonBerperanRule('ketua-pertandingan', 'Dewan Wasit Juri')],
            'komisi_id' => ['nullable', $this->calonBerperanRule('ketua-pertandingan', 'Komisi Protes')],
            'ketua_id' => ['nullable', $this->calonBerperanRule('ketua-pertandingan', 'Ketua Pertandingan')],
            'juri_id' => ['nullable', 'array', 'max:'.$jumlahJuri],
            'juri_id.*' => ['nullable', 'distinct', $this->calonBerperanRule('juri', 'Juri')],
        ], [
            'juri_id.*.distinct' => 'Satu orang tidak bisa menduduki dua kursi juri di gelanggang yang sama.',
        ]);

        $baris = collect([
            ['role' => 'wasit', 'user_id' => $data['wasit_id'] ?? null, 'number' => null],
            ['role' => 'dewan-juri', 'user_id' => $data['dewan_id'] ?? null, 'number' => null],
            ['role' => 'komisi-protes', 'user_id' => $data['komisi_id'] ?? null, 'number' => null],
            ['role' => 'ketua-pertandingan', 'user_id' => $data['ketua_id'] ?? null, 'number' => null],
        ]);

        foreach (range(1, $jumlahJuri) as $nomor) {
            $baris->push([
                'role' => 'juri',
                'user_id' => $data['juri_id'][$nomor - 1] ?? null,
                'number' => $nomor,
            ]);
        }

        $terisi = $baris->filter(fn (array $satu) => $satu['user_id'] !== null)->values();

        /*
         * Satu orang satu kursi. Kolomnya sudah unik `[arena_id, user_id]`,
         * jadi tanpa penjagaan ini yang muncul galat basis data -- kalimat
         * yang tidak menyebut siapa yang rangkap maupun di kursi mana.
         */
        $rangkap = $terisi->pluck('user_id')->duplicates();

        if ($rangkap->isNotEmpty()) {
            $nama = User::whereIn('id', $rangkap)->pluck('name')->implode(', ');

            throw ValidationException::withMessages([
                'wasit_id' => "{$nama} ditugaskan lebih dari satu kursi di gelanggang ini.",
            ]);
        }

        /*
         * Satu orang tidak bisa duduk di dua gelanggang.
         *
         * Dulu penjagaan ini hidup di layar penugasan per partai
         * (KetersediaanAparat) dan memeriksa bentrok waktu tayang. Sekarang
         * pertanyaannya lebih sederhana dan lebih keras: kursi gelanggang
         * berlaku SEPANJANG HARI, jadi orang yang sama di dua gelanggang
         * berarti satu kursi pasti kosong saat kedua matras berjalan
         * bersamaan -- dan itu baru ketahuan di depan penonton.
         */
        $diTempatLain = ArenaOfficial::query()
            ->whereIn('user_id', $terisi->pluck('user_id'))
            ->where('arena_id', '!=', $arena->id)
            ->whereHas('arena', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->with(['user:id,name', 'arena:id,name'])
            ->get();

        if ($diTempatLain->isNotEmpty()) {
            $sebut = $diTempatLain
                ->map(fn (ArenaOfficial $satu) => "{$satu->user?->name} sudah bertugas di {$satu->arena?->name}")
                ->implode('; ');

            throw ValidationException::withMessages(['wasit_id' => $sebut.'.']);
        }

        DB::transaction(function () use ($arena, $terisi) {
            ArenaOfficial::where('arena_id', $arena->id)->delete();

            $terisi->each(fn (array $satu) => ArenaOfficial::create([
                'arena_id' => $arena->id,
                'user_id' => $satu['user_id'],
                'role' => $satu['role'],
                'number' => $satu['number'],
            ]));
        });

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', "Aparat “{$arena->name}” diperbarui.");
    }

    /**
     * Penjaga "orang ini memang berperan begitu".
     *
     * `$sebutan` jabatan di matras, `$peran` nama peran sistem -- dan sejak
     * tiga peran matras lebur ke Ketua Pertandingan (September 2026) keduanya
     * tidak lagi sama. Pesan galatnya menyebut jabatannya, karena yang
     * membacanya sedang menyusun kursi gelanggang.
     */
    private function calonBerperanRule(string $peran, string $sebutan): Closure
    {
        return function (string $atribut, mixed $nilai, Closure $gagal) use ($peran, $sebutan) {
            if ($nilai === null || $nilai === '') {
                return;
            }

            $pengguna = User::where('is_active', true)->find($nilai);

            if ($pengguna === null) {
                $gagal('Pengguna yang dipilih tidak ada atau sedang nonaktif.');

                return;
            }

            if (! $pengguna->hasRole($peran)) {
                $gagal("{$pengguna->name} tidak berperan {$peran}, jadi tidak bisa ditugaskan sebagai {$sebutan}.");
            }
        };
    }

    public function destroy(Tournament $tournament, Arena $arena): RedirectResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $arena->delete();

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', 'Gelanggang dihapus.');
    }

    private function pastikanMilik(Tournament $tournament, Arena $arena): void
    {
        abort_unless($arena->tournament_id === $tournament->id, 404);
    }
}
