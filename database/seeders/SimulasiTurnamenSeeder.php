<?php

namespace Database\Seeders;

use App\Actions\Keuangan\KelolaInvoice;
use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisBerkas;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Enums\KategoriPertandingan;
use App\Enums\StatusPendaftaran;
use App\Enums\StatusTurnamen;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\FeeSchedule;
use App\Models\JurusEvent;
use App\Models\ManualPayment;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\WeightClass;
use App\Models\WeightIn;
use App\Support\Bagan\BracketGenerator;
use App\Support\Bagan\PenjadwalPartai;
use App\Support\Keuangan\InvoiceBuilder;
use App\Support\Pendaftaran\DaftarkanPeserta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Kejuaraan siap-uji untuk simulasi manual.
 *
 * Menyiapkan seluruh tahap pra-acara sampai titik tepat sebelum gong pertama:
 * akun tiap peran, tarif, kontingen beserta atlet dan berkasnya, tagihan yang
 * sudah lunas, pendaftaran terverifikasi, timbang badan, bagan terkunci,
 * jadwal, dan penugasan aparat.
 *
 * Yang sengaja TIDAK dikerjakan seeder ini: menjalankan partai, memasukkan
 * nilai juri, menjatuhkan hukuman, membuat penampilan Jurus, dan mengesahkan
 * hasil. Justru itulah yang mau diuji manual — kalau seeder ikut mengerjakannya,
 * yang tersisa untuk diuji tinggal membaca angka yang sudah jadi.
 *
 * Seluruh data dibuat lewat kelas yang sama dengan yang dipakai controller
 * (DaftarkanPeserta, InvoiceBuilder, BracketGenerator, PenjadwalPartai), bukan
 * lewat insert langsung. Kalau aturan domainnya berubah, seeder ini ikut gagal
 * — dan itu memang yang diinginkan.
 *
 * Jalankan:  php artisan silat:simulasi          (tambahkan --reset untuk mengulang dari bersih)
 */
class SimulasiTurnamenSeeder extends Seeder
{
    /** Penanda kejuaraan simulasi, dipakai juga oleh perintah silat:simulasi. */
    public const SLUG = 'simulasi-manual';

    private const KATA_SANDI = 'password';

    private const DOMAIN = 'silat.test';

    /** Disk tempat berkas peserta dan bukti bayar disimpan (lihat AthleteController). */
    private const DISK = 'local';

    private Tournament $tournament;

    /** @var array<string, User> */
    private array $akun = [];

    /** @var array<string, Contingent> */
    private array $kontingen = [];

    public function run(): void
    {
        $this->callOnce([
            ResourceSeeder::class,
            RoleSeeder::class,
            SilatResourceSeeder::class,
            SilatRoleSeeder::class,
        ]);

        if (Tournament::withTrashed()->where('slug', self::SLUG)->exists()) {
            $this->command?->warn('Kejuaraan simulasi sudah ada — dilewati.');
            $this->command?->warn('Jalankan "php artisan silat:simulasi --reset" untuk membuangnya dan menyusun ulang dari bersih.');

            return;
        }

        $this->buatAkun();
        $this->buatKejuaraan();
        $this->buatGelanggang();
        $this->buatTarif();
        $this->buatKontingen();
        $this->buatPeserta();
        $this->lunasiTagihan();
        $this->verifikasiPendaftaran();
        $this->timbangBadan();
        $this->susunBagan();
        $this->jadwalkanPartai();
        $this->tugaskanAparat();

        $this->ringkasan();
    }

    // ---------------------------------------------------------------- akun

    /**
     * Satu akun per peran, plus juri sebanyak yang dibutuhkan kategori Jurus.
     *
     * Enam juri, bukan tiga: kategori Jurus mensyaratkan minimal 4 dan wajib
     * genap (Pasal 16.1.b), dan enam juri yang sama bisa dipecah dua untuk
     * menjalankan dua gelanggang Tanding sekaligus.
     */
    private function buatAkun(): void
    {
        $daftar = [
            'delegasi' => ['Budi Santoso', 'delegasi-teknik'],
            'ketua' => ['Hendra Wijaya', 'ketua-pertandingan'],
            'pengawas' => ['Siti Rahayu', 'pengawas-wasit-juri'],
            'komisi' => ['Agus Salim', 'wasit-komisi-protes'],
            'sekretaris' => ['Dewi Lestari', 'sekretaris-pertandingan'],
            'bendahara' => ['Rina Kartika', 'bendahara'],
            'timbang' => ['Joko Prasetyo', 'petugas-timbang'],
            'operator' => ['Fajar Nugroho', 'operator-it'],
            'operator2' => ['Yudi Hartono', 'operator-it'],
            'wasit1' => ['Bambang Sutrisno', 'wasit'],
            'wasit2' => ['Rudi Hermawan', 'wasit'],
        ];

        foreach (range(1, 6) as $nomor) {
            $daftar["juri{$nomor}"] = ["Juri {$nomor}", 'juri'];
        }

        foreach (range(1, 4) as $nomor) {
            $daftar["official{$nomor}"] = ["Official Kontingen {$nomor}", 'official-kontingen'];
        }

        foreach ($daftar as $kunci => [$nama, $peran]) {
            $this->akun[$kunci] = $this->akunDenganPeran($nama, "{$kunci}@".self::DOMAIN, $peran);
        }

        $this->command?->info('Akun dibuat: '.count($this->akun).' pengguna, kata sandi "'.self::KATA_SANDI.'".');
    }

    private function akunDenganPeran(string $nama, string $email, string $peran): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $nama,
                'password' => Hash::make(self::KATA_SANDI),
                'is_active' => true,
            ],
        );

        // email_verified_at bukan kolom fillable, jadi tidak ikut terisi lewat
        // create di atas. Tanpa ini seluruh panel membalas 403.
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$peran]);

        return $user;
    }

    // ----------------------------------------------------------- kejuaraan

    private function buatKejuaraan(): void
    {
        $this->tournament = Tournament::create([
            'name' => 'Kejuaraan Simulasi Digital Scoring',
            'slug' => self::SLUG,
            'organizer' => 'Pengurus Besar IPSI',
            'venue' => 'GOR Simulasi',
            'starts_on' => now()->startOfDay(),
            'ends_on' => now()->addDays(2)->startOfDay(),
            'registration_opens_at' => now()->subMonth(),
            'registration_closes_at' => now()->subDay(),
            'status' => StatusTurnamen::Berjalan,
            'description' => 'Data siap-uji untuk simulasi manual. Seluruh tahap pra-acara sudah selesai; tinggal menjalankan partai.',
        ]);

        (new SusunMasterDataTurnamen)($this->tournament);

        /*
         * Window konsensus dilebarkan dari bawaan 2 detik menjadi 5 detik.
         * Uji manual dijalankan satu orang yang berpindah antar tab atau antar
         * HP, dan tiga tekanan tombol tidak mungkin masuk dalam dua detik
         * seperti tiga juri sungguhan yang duduk bersamaan. Ini setelan
         * kejuaraan yang memang boleh diubah -- naskah tidak mengaturnya.
         */
        $this->tournament->peraturan()->update(['window_konsensus_ms' => 5000]);

        $this->command?->info("Kejuaraan #{$this->tournament->id} dibuat beserta kelas tanding dan nomor Jurus dari naskah 2025.");
    }

    /**
     * Dua gelanggang, masing-masing dengan operatornya sendiri.
     *
     * Operator terikat gelanggang: tanpa penugasan ini panel gelanggang
     * menolak seluruh aksinya, jadi kejuaraan simulasi tidak akan bisa
     * dijalankan sama sekali.
     */
    private function buatGelanggang(): void
    {
        $operator = ['A' => $this->akun['operator'], 'B' => $this->akun['operator2']];

        foreach ([['Gelanggang A', 'A'], ['Gelanggang B', 'B']] as $urutan => [$nama, $kode]) {
            $arena = Arena::create([
                'tournament_id' => $this->tournament->id,
                'name' => $nama,
                'code' => $kode,
                'sort_order' => $urutan,
                'is_active' => true,
            ]);

            $arena->operators()->attach($operator[$kode]);
        }
    }

    private function buatTarif(): void
    {
        foreach ([GolonganUsia::Remaja, GolonganUsia::Dewasa] as $usia) {
            foreach (KategoriPertandingan::cases() as $kategori) {
                FeeSchedule::create([
                    'tournament_id' => $this->tournament->id,
                    'kind' => FeeSchedule::KIND_NOMOR,
                    'kategori' => $kategori,
                    'golongan_usia' => $usia,
                    'amount' => $kategori === KategoriPertandingan::Tanding ? 150_000 : 125_000,
                ]);
            }
        }

        FeeSchedule::create([
            'tournament_id' => $this->tournament->id,
            'kind' => FeeSchedule::KIND_KONTINGEN,
            'amount' => 250_000,
            'label' => 'Biaya tetap kontingen',
        ]);
    }

    // ------------------------------------------------------------- peserta

    private function buatKontingen(): void
    {
        $daftar = [
            ['Perisai Diri Jakarta', 'DKI Jakarta'],
            ['Merpati Putih Bandung', 'Jawa Barat'],
            ['Tapak Suci Yogyakarta', 'DI Yogyakarta'],
            ['Setia Hati Terate Surabaya', 'Jawa Timur'],
        ];

        foreach ($daftar as $indeks => [$nama, $daerah]) {
            $nomor = $indeks + 1;

            $this->kontingen["k{$nomor}"] = Contingent::create([
                'tournament_id' => $this->tournament->id,
                'user_id' => $this->akun["official{$nomor}"]->id,
                'name' => $nama,
                'region' => $daerah,
                'contact_name' => $this->akun["official{$nomor}"]->name,
                'contact_phone' => '0812'.str_pad((string) $nomor, 8, '0', STR_PAD_LEFT),
            ]);
        }
    }

    /**
     * Empat kelas dipertandingkan, dipilih supaya tiap bentuk bagan dan tiap
     * jenis penagihan ikut teruji:
     *
     *   Tanding putra   4 peserta  -> bagan penuh, dua semifinal dan satu final
     *   Tanding putri   5 peserta  -> bagan dengan bye, sekaligus menguji
     *                                 kewajiban surat tidak hamil
     *   Jurus Tunggal   3 peserta  -> penilaian median tanpa bagan
     *   Jurus Ganda     2 tim      -> tagihan per tim, bukan per orang
     */
    private function buatPeserta(): void
    {
        $daftarkan = app(DaftarkanPeserta::class);

        $kelasPutra = $this->kelasTanding(JenisKelamin::Putra);
        $kelasPutri = $this->kelasTanding(JenisKelamin::Putri);
        $tunggal = $this->nomorJurus(JenisJurus::Tunggal, JenisKelamin::Putra);
        $ganda = $this->nomorJurus(JenisJurus::Ganda, JenisKelamin::Putra);

        $namaPutra = ['Dimas Prakoso', 'Andika Saputra', 'Bagus Wicaksono', 'Candra Setiawan'];
        $namaPutri = ['Ayu Permatasari', 'Nadia Safitri', 'Intan Maharani', 'Putri Anggraini', 'Sari Wulandari'];
        $namaTunggal = ['Rizky Ramadhan', 'Ilham Maulana', 'Gilang Pratama'];
        $namaGanda = [['Arif Budiman', 'Deni Kurniawan'], ['Faisal Rahman', 'Galih Saputro']];

        // Tanding putra: satu peserta dari tiap kontingen.
        foreach ($namaPutra as $indeks => $nama) {
            $kontingen = $this->kontingen['k'.($indeks + 1)];
            $atlet = $this->buatAtlet($kontingen, $nama, JenisKelamin::Putra, $this->beratTengah($kelasPutra));

            $daftarkan->tanding($kontingen, $kelasPutra, $atlet);
        }

        /*
         * Tanding putri: lima peserta dari empat kontingen -- kontingen pertama
         * mengirim dua. Jumlah ganjil inilah yang memaksa generator bagan
         * menyebar bye, dan itu bagian yang paling mudah salah kalau tidak
         * pernah dijalankan dengan data sungguhan.
         */
        foreach ($namaPutri as $indeks => $nama) {
            $kontingen = $this->kontingen['k'.(($indeks % 4) + 1)];
            $atlet = $this->buatAtlet($kontingen, $nama, JenisKelamin::Putri, $this->beratTengah($kelasPutri));

            $daftarkan->tanding($kontingen, $kelasPutri, $atlet);
        }

        foreach ($namaTunggal as $indeks => $nama) {
            $kontingen = $this->kontingen['k'.($indeks + 1)];
            $atlet = $this->buatAtlet($kontingen, $nama, JenisKelamin::Putra);

            $daftarkan->jurus($kontingen, $tunggal, collect([$atlet]));
        }

        foreach ($namaGanda as $indeks => $pasangan) {
            $kontingen = $this->kontingen['k'.($indeks + 1)];

            $atlet = collect($pasangan)->map(
                fn (string $nama): Athlete => $this->buatAtlet($kontingen, $nama, JenisKelamin::Putra),
            );

            $daftarkan->jurus($kontingen, $ganda, $atlet);
        }

        // Seluruh pendaftaran diajukan sekaligus, seperti official yang menekan
        // "Ajukan" setelah berkasnya lengkap.
        Registration::query()
            ->whereIn('contingent_id', collect($this->kontingen)->pluck('id'))
            ->update(['status' => StatusPendaftaran::Diajukan, 'submitted_at' => now()]);

        $this->command?->info('Peserta dibuat: '.Athlete::whereIn('contingent_id', collect($this->kontingen)->pluck('id'))->count()
            .' atlet, '.Registration::whereIn('contingent_id', collect($this->kontingen)->pluck('id'))->count().' pendaftaran.');
    }

    private function buatAtlet(Contingent $kontingen, string $nama, JenisKelamin $jenisKelamin, ?float $berat = null): Athlete
    {
        $atlet = Athlete::create([
            'contingent_id' => $kontingen->id,
            'name' => $nama,
            'jenis_kelamin' => $jenisKelamin,
            // Umur 22 tahun pada tanggal kejuaraan dimulai -- masuk golongan
            // Dewasa (di atas 17, sampai 35) menurut Pasal 2.
            'birth_date' => $this->tournament->starts_on->clone()->subYears(22),
            'weight_claim' => $berat,
        ]);

        foreach ($atlet->berkasWajib($this->tournament) as $jenis) {
            $this->unggahBerkas($atlet, $jenis);
        }

        return $atlet->refresh()->load('documents');
    }

    /**
     * Menulis berkas contoh ke disk yang sama dengan unggahan sungguhan,
     * supaya tombol unduh berkas di panel verifikasi benar-benar berfungsi
     * dan tidak berakhir 404.
     */
    private function unggahBerkas(Athlete $atlet, JenisBerkas $jenis): void
    {
        $nama = Str::slug($atlet->name).'-'.$jenis->value.'.txt';
        $path = "peserta/{$this->tournament->id}/{$atlet->id}/{$nama}";
        $isi = "Berkas contoh untuk simulasi manual.\n{$jenis->label()} — {$atlet->name}\n";

        Storage::disk(self::DISK)->put($path, $isi);

        $atlet->documents()->create([
            'jenis' => $jenis,
            'path' => $path,
            'original_name' => $nama,
            'size_bytes' => strlen($isi),
            'mime' => 'text/plain',
            'uploaded_by' => $atlet->contingent->user_id,
        ]);
    }

    // ------------------------------------------------------------ keuangan

    private function lunasiTagihan(): void
    {
        $builder = app(InvoiceBuilder::class);
        $kelola = app(KelolaInvoice::class);
        $total = 0;

        foreach ($this->kontingen as $kontingen) {
            $invoice = $kelola->kunci($builder->untuk($kontingen));
            $invoice = $kelola->tandaiLunas($invoice, 'manual');

            $nama = 'bukti-'.Str::slug($kontingen->name).'.txt';
            $path = "bukti-bayar/{$this->tournament->id}/{$nama}";

            Storage::disk(self::DISK)->put($path, "Bukti pembayaran contoh — {$kontingen->name}\n");

            ManualPayment::create([
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total_amount,
                'note' => 'Transfer bank, dicatat oleh seeder simulasi.',
                'proof_path' => $path,
                'proof_original_name' => $nama,
                'paid_at' => now(),
                'recorded_by' => $this->akun['bendahara']->id,
            ]);

            $total += $invoice->total_amount;
        }

        $this->command?->info('Tagihan lunas: '.count($this->kontingen).' kontingen, total Rp'.number_format($total, 0, ',', '.').'.');
    }

    // ---------------------------------------------------- verifikasi & timbang

    private function verifikasiPendaftaran(): void
    {
        $this->pendaftaran()->each(function (Registration $pendaftaran): void {
            $pendaftaran->update([
                'status' => StatusPendaftaran::Terverifikasi,
                'verified_by' => $this->akun['sekretaris']->id,
                'verified_at' => now(),
            ]);
        });
    }

    /**
     * Timbang badan hanya untuk peserta Tanding pada golongan yang memang
     * ditimbang (Pasal 3-7). Nomor Jurus tidak punya kelas berat, jadi tidak
     * ada yang bisa dibandingkan.
     */
    private function timbangBadan(): void
    {
        $tertimbang = 0;

        foreach ($this->pendaftaran()->load('weightClass', 'athletes') as $pendaftaran) {
            $kelas = $pendaftaran->weightClass;

            if ($kelas === null || ! $kelas->golongan_usia->adaTimbangBadan()) {
                continue;
            }

            foreach ($pendaftaran->athletes as $atlet) {
                WeightIn::create([
                    'registration_id' => $pendaftaran->id,
                    'athlete_id' => $atlet->id,
                    'weight' => $atlet->weight_claim,
                    'passed' => true,
                    'weighed_at' => now(),
                    'recorded_by' => $this->akun['timbang']->id,
                    'notes' => 'Timbang badan simulasi.',
                ]);

                $tertimbang++;
            }
        }

        $this->command?->info("Timbang badan: {$tertimbang} atlet, semuanya lolos.");
    }

    // ------------------------------------------------------- bagan & jadwal

    private function susunBagan(): void
    {
        $generator = app(BracketGenerator::class);

        $kelasBerpeserta = $this->pendaftaran()
            ->whereNotNull('weight_class_id')
            ->pluck('weight_class_id')
            ->unique();

        $tersusun = 0;

        foreach (WeightClass::whereIn('id', $kelasBerpeserta)->get() as $kelas) {
            if ($generator->pesertaSah($kelas)->count() < 2) {
                continue;
            }

            $generator->kunci(
                $generator->untukKelas($kelas, acak: false),
                $this->akun['ketua'],
            );

            $tersusun++;
        }

        $this->command?->info("Bagan tersusun dan terkunci untuk {$tersusun} kelas tanding.");
    }

    /**
     * Partai babak pertama ditempatkan ke dua gelanggang berselang-seling,
     * dengan jarak 45 menit -- di atas jeda aman 30 menit yang dipakai
     * PenjadwalPartai untuk mendeteksi atlet bentrok.
     */
    private function jadwalkanPartai(): void
    {
        $penjadwal = app(PenjadwalPartai::class);
        $gelanggang = Arena::where('tournament_id', $this->tournament->id)->orderBy('sort_order')->get();
        $mulai = Carbon::now()->addHour()->startOfHour();
        $terjadwal = 0;

        foreach ($this->partaiSiap() as $indeks => $partai) {
            $penjadwal->tetapkan(
                $partai,
                $gelanggang[$indeks % $gelanggang->count()],
                $mulai->clone()->addMinutes(45 * intdiv($indeks, $gelanggang->count())),
            );

            $terjadwal++;
        }

        $this->command?->info("Jadwal: {$terjadwal} partai ditempatkan ke {$gelanggang->count()} gelanggang.");
    }

    /**
     * Aparat ditugaskan per partai: satu wasit dan juri sebanyak setelan
     * kejuaraan. Gelanggang A memakai juri 1-3, gelanggang B juri 4-6, supaya
     * dua gelanggang bisa dijalankan bersamaan tanpa satu orang pun merangkap.
     */
    private function tugaskanAparat(): void
    {
        $jumlahJuri = $this->tournament->peraturan()->jumlah_juri_tanding;
        $gelanggangPertama = Arena::where('tournament_id', $this->tournament->id)->orderBy('sort_order')->value('id');

        foreach ($this->partaiSiap()->whereNotNull('arena_id') as $partai) {
            $gelanggangA = $partai->arena_id === $gelanggangPertama;

            $partai->officials()->delete();

            $partai->officials()->create([
                'user_id' => $this->akun[$gelanggangA ? 'wasit1' : 'wasit2']->id,
                'role' => MatchOfficial::ROLE_WASIT,
            ]);

            foreach (range(1, $jumlahJuri) as $nomor) {
                $partai->officials()->create([
                    'user_id' => $this->akun['juri'.($gelanggangA ? $nomor : $nomor + 3)]->id,
                    'role' => MatchOfficial::ROLE_JURI,
                    'number' => $nomor,
                ]);
            }
        }
    }

    // ------------------------------------------------------------- bantuan

    /** @return Collection<int, Registration> */
    private function pendaftaran(): Collection
    {
        return Registration::whereIn('contingent_id', collect($this->kontingen)->pluck('id'))->get();
    }

    /**
     * Partai yang sudah punya dua peserta. Partai final baru terisi setelah
     * semifinalnya selesai, jadi ia memang belum bisa dijadwalkan sekarang.
     *
     * @return Collection<int, SilatMatch>
     */
    private function partaiSiap(): Collection
    {
        return SilatMatch::query()
            ->whereHas('bracket.weightClass', fn ($q) => $q->where('tournament_id', $this->tournament->id))
            ->where('status', SilatMatch::STATUS_TERJADWAL)
            ->with(['red.athletes', 'blue.athletes', 'bracket.weightClass'])
            ->orderBy('id')
            ->get()
            ->filter(fn (SilatMatch $partai): bool => $partai->siapDipertandingkan())
            ->values();
    }

    private function kelasTanding(JenisKelamin $jenisKelamin): WeightClass
    {
        return WeightClass::where('tournament_id', $this->tournament->id)
            ->where('golongan_usia', GolonganUsia::Dewasa)
            ->where('jenis_kelamin', $jenisKelamin)
            ->whereNotNull('weight_min')
            ->whereNotNull('weight_max')
            ->orderBy('sort_order')
            ->firstOrFail();
    }

    private function nomorJurus(JenisJurus $jenis, JenisKelamin $jenisKelamin): JurusEvent
    {
        return JurusEvent::where('tournament_id', $this->tournament->id)
            ->where('golongan_usia', GolonganUsia::Dewasa)
            ->where('jenis', $jenis)
            ->where('jenis_kelamin', $jenisKelamin)
            ->firstOrFail();
    }

    /** Berat di tengah rentang kelas, supaya lolos klaim maupun timbang badan. */
    private function beratTengah(WeightClass $kelas): float
    {
        return round(((float) $kelas->weight_min + (float) $kelas->weight_max) / 2, 1);
    }

    private function ringkasan(): void
    {
        $id = $this->tournament->id;
        $gelanggang = Arena::where('tournament_id', $id)->orderBy('sort_order')->pluck('id');

        $this->command?->newLine();
        $this->command?->info('=== Kejuaraan simulasi siap dipakai ===');
        $this->command?->line("Kejuaraan  : #{$id} — {$this->tournament->name}");
        $this->command?->line('Gelanggang : '.$gelanggang->implode(', '));
        $this->command?->line('Kata sandi : '.self::KATA_SANDI.' (seluruh akun)');
        $this->command?->newLine();
        $this->command?->line('Akun  ketua@'.self::DOMAIN.'  operator@'.self::DOMAIN.'  wasit1@'.self::DOMAIN.'  juri1@'.self::DOMAIN.' … juri6@'.self::DOMAIN);
        $this->command?->line('      sekretaris@ bendahara@ timbang@ pengawas@ komisi@ delegasi@ official1@ … official4@');
        $this->command?->newLine();
        $this->command?->line("Panel operator partai pertama tersedia di menu Jadwal kejuaraan #{$id}.");
        $this->command?->line("Live publik  : /live/turnamen/{$id}");
        $this->command?->line("Overlay vMix : /overlay/scorebug/{$gelanggang->first()}");
        $this->command?->line('Langkah uji berikutnya: docs/PANDUAN-WORKFLOW.md bagian B (Hari-H).');
    }
}
