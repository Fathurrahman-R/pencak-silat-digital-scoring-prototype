<?php

namespace Database\Seeders;

use App\Actions\Keuangan\KelolaInvoice;
use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\FormatJurus;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\KategoriPertandingan;
use App\Enums\ModeBagan;
use App\Enums\StatusPendaftaran;
use App\Enums\StatusTurnamen;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\FeeSchedule;
use App\Models\JurusEvent;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\WeightClass;
use App\Support\Bagan\BracketGenerator;
use App\Support\Bagan\PenjadwalPartai;
use App\Support\Bagan\SusunBaganJurus;
use App\Support\Keuangan\InvoiceBuilder;
use App\Support\Pendaftaran\DaftarkanPeserta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Kejuaraan berskala penuh: SELURUH kelas tanding dan SELURUH nomor Jurus
 * terisi peserta.
 *
 * Berbeda tujuan dari SimulasiTurnamenSeeder, dan karena itu berdiri sendiri
 * alih-alih menambah satu saklar lagi di sana. Yang itu menyiapkan seratus
 * pesilat pada lima kelas Dewasa -- ukuran yang pas untuk menelusuri satu
 * partai dengan tangan. Yang ini menyiapkan kejuaraan seukuran kejuaraan
 * sungguhan, dan yang diuji dengannya adalah hal-hal yang tidak pernah
 * muncul pada data kecil:
 *
 *   - halaman jadwal, bagan, dan rekap yang memuat ribuan baris;
 *   - penjadwalan ke dua atau tiga gelanggang sekaligus;
 *   - golongan usia dini yang kelasnya banyak tapi pesertanya sedikit;
 *   - nomor Jurus ganda dan regu, yang satu pendaftarannya berisi dua sampai
 *     tiga pesilat;
 *   - kedua mode bagan berdampingan di kejuaraan yang sama.
 *
 * Yang SENGAJA tidak dikerjakan seeder ini, berbeda dari seeder simulasi
 * kecil: berkas peserta tidak ditulis ke disk. Tiga ribu pesilat berarti
 * enam ribu berkas contoh yang tidak dibaca siapa pun, dan seluruh
 * pendaftarannya toh langsung disahkan. Kalau yang diuji justru unduhan
 * berkas di panel verifikasi, pakai seeder simulasi kecil.
 */
class KejuaraanSkalaSeeder extends Seeder
{
    public const SLUG_SEDANG = 'simulasi-sedang';

    public const SLUG_BESAR = 'simulasi-besar';

    /**
     * Ukuran tiap skala.
     *
     *   kontingen        jumlah kontingen; juga banyaknya pilihan asal peserta
     *                    tiap kelas, supaya tidak ada kontingen yang bertemu
     *                    dirinya sendiri di babak pertama.
     *   tanding_perkelas peserta per kelas tanding. Dikalikan 174 kelas.
     *   jurus_pernomor   pendaftaran per nomor Jurus. Dikalikan 64 nomor, dan
     *                    tiap pendaftaran berisi 1-3 pesilat menurut jenisnya.
     *   gelanggang       jumlah gelanggang yang partainya dibagi rata.
     *
     * @var array<string, array<string, int|string>>
     */
    public const PROFIL = [
        'sedang' => [
            'slug' => self::SLUG_SEDANG,
            'nama' => 'Kejuaraan Skala Sedang',
            'kontingen' => 6,
            'tanding_perkelas' => 2,
            'jurus_pernomor' => 2,
            'gelanggang' => 2,
        ],
        'besar' => [
            'slug' => self::SLUG_BESAR,
            'nama' => 'Kejuaraan Skala Besar',
            'kontingen' => 12,
            'tanding_perkelas' => 12,
            'jurus_pernomor' => 6,
            'gelanggang' => 3,
        ],
    ];

    private string $skala = 'sedang';

    private bool $tanpaBagan = false;

    private Tournament $tournament;

    /** @var array<string, User> */
    private array $akun = [];

    /** @var array<int, Contingent> */
    private array $kontingen = [];

    public function skala(string $skala): self
    {
        if (! isset(self::PROFIL[$skala])) {
            throw new \InvalidArgumentException("Skala tidak dikenal: {$skala}.");
        }

        $this->skala = $skala;

        return $this;
    }

    /**
     * Berhenti sesudah pendaftaran sah dan lunas, sebelum bagan disusun.
     *
     * Dipakai saat yang dilatih justru penyusunan bagannya sendiri -- memilih
     * mode gugur atau pemasalan, mengundi ulang, menukar tempat, mengunci --
     * yang pada data siap-pakai sudah terlanjur dikerjakan seeder.
     */
    public function tanpaBagan(bool $tanpaBagan = true): self
    {
        $this->tanpaBagan = $tanpaBagan;

        return $this;
    }

    public function run(): void
    {
        $this->callOnce([
            ResourceSeeder::class,
            RoleSeeder::class,
            SilatResourceSeeder::class,
            SilatRoleSeeder::class,
        ]);

        $profil = self::PROFIL[$this->skala];

        if (Tournament::withTrashed()->where('slug', $profil['slug'])->exists()) {
            $this->command?->warn("Kejuaraan {$profil['nama']} sudah ada — dilewati.");
            $this->command?->warn("Jalankan \"php artisan silat:simulasi --skala={$this->skala} --reset\" untuk menyusun ulang dari bersih.");

            return;
        }

        $mulai = microtime(true);

        $this->buatAkun($profil);
        $this->buatKejuaraan($profil);
        $this->buatGelanggang($profil);
        $this->buatTarif();
        $this->buatKontingen($profil);
        $this->buatPesertaTanding($profil);
        $this->buatPesertaJurus($profil);
        $this->sahkanPendaftaran();
        $this->lunasiTagihan();
        $this->timbangBadan();

        if (! $this->tanpaBagan) {
            $this->susunBaganTanding();
            $this->susunBaganJurus();
            $this->jadwalkanPartai($profil);
            $this->tugaskanAparat($profil);
        }

        $this->ringkasan($profil, microtime(true) - $mulai);
    }

    // ---------------------------------------------------------------- akun

    /**
     * Akun dipakai bersama dengan kejuaraan simulasi kecil.
     *
     * Panitia yang berlatih tidak perlu menghafal kata sandi yang berbeda per
     * ukuran data: `juri1@silat.test` tetap juri1. Yang ditambahkan di sini
     * hanya yang belum ada -- gelanggang ketiga menuntut operator ketiga dan
     * juri 7-9, dan keduanya tidak dibuat seeder kecil.
     */
    private function buatAkun(array $profil): void
    {
        $daftar = [
            'ketua' => ['Hendra Wijaya', 'ketua-pertandingan'],
            'pengawas' => ['Siti Rahayu', 'pengawas-wasit-juri'],
            'komisi' => ['Agus Salim', 'wasit-komisi-protes'],
            'sekretariat' => ['Dewi Lestari', 'sekretariat'],
        ];

        foreach (range(1, $profil['gelanggang']) as $nomor) {
            $kunci = $nomor === 1 ? 'operator' : "operator{$nomor}";
            $daftar[$kunci] = ["Operator Gelanggang {$nomor}", 'operator-it'];
            $daftar["pengendali{$nomor}"] = ["Pengendali Gelanggang {$nomor}", 'pengendali-gelanggang'];
            $daftar["wasit{$nomor}"] = ["Wasit {$nomor}", 'wasit'];
        }

        foreach (range(1, $profil['gelanggang'] * 3) as $nomor) {
            $daftar["juri{$nomor}"] = ["Juri {$nomor}", 'juri'];
        }

        foreach (range(1, $profil['kontingen']) as $nomor) {
            $daftar["official{$nomor}"] = ["Official Kontingen {$nomor}", 'official-kontingen'];
        }

        foreach ($daftar as $kunci => [$nama, $peran]) {
            $user = User::firstOrCreate(
                ['email' => "{$kunci}@silat.test"],
                ['name' => $nama, 'password' => Hash::make('password'), 'is_active' => true],
            );

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$peran]);

            $this->akun[$kunci] = $user;
        }
    }

    // ----------------------------------------------------------- kejuaraan

    private function buatKejuaraan(array $profil): void
    {
        $this->tournament = Tournament::create([
            'name' => $profil['nama'],
            'slug' => $profil['slug'],
            'organizer' => 'Pengurus Besar IPSI',
            'venue' => 'GOR Simulasi',
            'starts_on' => now()->startOfDay(),
            'ends_on' => now()->addDays(3)->startOfDay(),
            'registration_opens_at' => now()->subMonth(),
            'registration_closes_at' => now()->subDay(),
            'status' => StatusTurnamen::Berjalan,
            'description' => 'Data uji berskala penuh: seluruh kelas tanding dan seluruh nomor Jurus terisi.',
        ]);

        (new SusunMasterDataTurnamen)($this->tournament);
    }

    private function buatGelanggang(array $profil): void
    {
        foreach (range(1, $profil['gelanggang']) as $urutan) {
            $kode = chr(64 + $urutan);

            $arena = Arena::create([
                'tournament_id' => $this->tournament->id,
                'name' => "Gelanggang {$kode}",
                'code' => $kode,
                'sort_order' => $urutan - 1,
                'is_active' => true,
            ]);

            $arena->operators()->attach($this->akun[$urutan === 1 ? 'operator' : "operator{$urutan}"]);

            /*
             * DUA tabel penugasan yang berbeda, dan yang kedua ini tidak boleh
             * dilewatkan: gelanggang tanpa pengendali membalas 403 di panel
             * kendali, dan tanpa panel kendali tidak ada satu partai pun yang
             * bisa dimulai. Data uji yang tidak bisa dijalankan tidak menguji
             * apa pun.
             */
            $arena->pengendali()->attach($this->akun["pengendali{$urutan}"]);
        }
    }

    /**
     * Tarif dipasang untuk SELURUH golongan usia, bukan dua seperti seeder
     * kecil: kejuaraan ini memang berisi peserta dari pra usia dini sampai
     * master, dan tagihan yang tidak punya tarif akan dihitung nol tanpa satu
     * pun tanda di layar bendahara.
     */
    private function buatTarif(): void
    {
        foreach (GolonganUsia::cases() as $usia) {
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

    private function buatKontingen(array $profil): void
    {
        $daerah = [
            ['Perisai Diri Jakarta', 'DKI Jakarta'],
            ['Merpati Putih Bandung', 'Jawa Barat'],
            ['Tapak Suci Yogyakarta', 'DI Yogyakarta'],
            ['Setia Hati Terate Surabaya', 'Jawa Timur'],
            ['Pagar Nusa Semarang', 'Jawa Tengah'],
            ['Perisai Putih Denpasar', 'Bali'],
            ['Kera Sakti Medan', 'Sumatera Utara'],
            ['Silat Minang Padang', 'Sumatera Barat'],
            ['Bina Raga Makassar', 'Sulawesi Selatan'],
            ['Garuda Sakti Pontianak', 'Kalimantan Barat'],
            ['Panglipur Cianjur', 'Jawa Barat'],
            ['Baringin Sakti Batam', 'Kepulauan Riau'],
        ];

        foreach (range(1, $profil['kontingen']) as $nomor) {
            [$nama, $wilayah] = $daerah[($nomor - 1) % count($daerah)];

            $this->kontingen[$nomor] = Contingent::create([
                'tournament_id' => $this->tournament->id,
                'user_id' => $this->akun["official{$nomor}"]->id,
                'name' => $nomor > count($daerah) ? "{$nama} {$nomor}" : $nama,
                'region' => $wilayah,
                'contact_name' => $this->akun["official{$nomor}"]->name,
                'contact_phone' => '0812'.str_pad((string) $nomor, 8, '0', STR_PAD_LEFT),
            ]);
        }
    }

    /**
     * Peserta tanding: tiap kelas diisi dari kontingen yang BERGILIR.
     *
     * Bergilir, bukan selalu kontingen 1..n: kalau kelas manapun selalu diisi
     * kontingen yang sama, seluruh bagan kejuaraan ini berbentuk identik dan
     * pola undian yang tidak wajar itu ikut terbawa ke tiap uji yang memakai
     * datanya. Yang tetap dijaga: dalam satu kelas tidak ada dua peserta dari
     * kontingen yang sama.
     */
    private function buatPesertaTanding(array $profil): void
    {
        $daftarkan = app(DaftarkanPeserta::class);
        $kelas = WeightClass::where('tournament_id', $this->tournament->id)->orderBy('id')->get();
        $dibuat = 0;

        foreach ($kelas as $urutan => $satu) {
            foreach (range(0, $profil['tanding_perkelas'] - 1) as $ke) {
                $nomorKontingen = (($urutan + $ke) % $profil['kontingen']) + 1;
                $kontingen = $this->kontingen[$nomorKontingen];

                $atlet = $this->buatAtlet(
                    $kontingen,
                    $satu->golongan_usia,
                    $satu->jenis_kelamin,
                    'T'.$satu->id.'-'.($ke + 1),
                    $this->beratDalamKelas($satu, $ke),
                );

                $daftarkan->tanding($kontingen, $satu, $atlet);
                $dibuat++;
            }
        }

        $this->command?->info("Tanding: {$dibuat} pendaftaran pada {$kelas->count()} kelas.");
    }

    /**
     * Peserta Jurus: seluruh nomor terisi, termasuk ganda dan regu.
     *
     * Nomor ganda berisi dua pesilat dan regu tiga, dan pesilatnya dibuat
     * khusus untuk pendaftaran itu. Memakai ulang atlet tanding memang lebih
     * menyerupai kejuaraan sungguhan, tapi menghadirkan satu orang di dua
     * gelanggang pada jam yang sama -- persis bentrok yang selalu dikeluhkan
     * panitia, dan bukan hal yang pantas dijadikan bawaan data uji.
     */
    private function buatPesertaJurus(array $profil): void
    {
        $daftarkan = app(DaftarkanPeserta::class);
        $nomorJurus = JurusEvent::where('tournament_id', $this->tournament->id)->orderBy('id')->get();
        $dibuat = 0;

        foreach ($nomorJurus as $urutan => $nomor) {
            $jumlahPesilat = $nomor->jenis->jumlahPesilat();

            foreach (range(0, $profil['jurus_pernomor'] - 1) as $ke) {
                $nomorKontingen = (($urutan + $ke) % $profil['kontingen']) + 1;
                $kontingen = $this->kontingen[$nomorKontingen];

                $atlet = collect(range(1, $jumlahPesilat))->map(fn (int $ke2) => $this->buatAtlet(
                    $kontingen,
                    $nomor->golongan_usia,
                    $nomor->jenis_kelamin,
                    'J'.$nomor->id.'-'.($ke + 1).'-'.$ke2,
                ));

                $daftarkan->jurus($kontingen, $nomor, $atlet);
                $dibuat++;
            }
        }

        $this->command?->info("Jurus: {$dibuat} pendaftaran pada {$nomorJurus->count()} nomor.");
    }

    private function buatAtlet(
        Contingent $kontingen,
        GolonganUsia $usia,
        JenisKelamin $jenisKelamin,
        string $penanda,
        ?float $berat = null,
    ): Athlete {
        return Athlete::create([
            'contingent_id' => $kontingen->id,
            'name' => $this->namaPesilat($jenisKelamin, $penanda),
            'jenis_kelamin' => $jenisKelamin,
            'birth_date' => $this->tanggalLahir($usia),
            'weight_claim' => $berat,
        ]);
    }

    /**
     * Tanggal lahir yang jatuh di TENGAH rentang umur golongannya.
     *
     * Di tengah, bukan di batas: peserta yang umurnya persis di batas bawah
     * atau atas menjadikan tiap uji ikut menguji pembulatan umur, dan
     * kegagalan pembulatan itu akan terbaca sebagai kegagalan hal lain yang
     * sedang diuji.
     */
    private function tanggalLahir(GolonganUsia $usia): Carbon
    {
        [$bawah, $atas] = $usia->batasUmur();

        $umur = match (true) {
            $bawah === null => max(1, (int) $atas - 1),
            $atas === null => (int) $bawah + 5,
            default => (int) floor(($bawah + $atas) / 2),
        };

        return $this->tournament->starts_on->clone()->subYears($umur)->subMonths(3);
    }

    private function namaPesilat(JenisKelamin $jenisKelamin, string $penanda): string
    {
        $putra = ['Andi', 'Bagus', 'Candra', 'Dimas', 'Eko', 'Fajar', 'Galih', 'Hadi', 'Irfan', 'Joko'];
        $putri = ['Ayu', 'Bunga', 'Citra', 'Dinda', 'Eka', 'Fitri', 'Gita', 'Hana', 'Intan', 'Jelita'];

        $depan = $jenisKelamin === JenisKelamin::Putri ? $putri : $putra;

        return $depan[crc32($penanda) % count($depan)].' '.$penanda;
    }

    /** Berat yang pasti masuk kelasnya, dan berbeda-beda antar peserta. */
    private function beratDalamKelas(WeightClass $kelas, int $ke): ?float
    {
        if (! $kelas->golongan_usia->pakaiKelasBerat()) {
            return null;
        }

        $bawah = $kelas->weight_min;
        $atas = $kelas->weight_max;

        /*
         * Kelas tanpa batas atas maupun bawah tetap punya angka.
         *
         * Golongannya tetap ditimbang, dan baris timbang badan tanpa berat
         * ditolak basis data. Enam puluh kilogram bukan angka yang berarti
         * apa-apa di sini -- yang berarti adalah bahwa penimbangannya tercatat.
         */
        if ($bawah === null && $atas === null) {
            return round(60 + ($ke % 3) * 0.1, 1);
        }

        $tengah = match (true) {
            $bawah === null => (float) $atas - 1,
            $atas === null => (float) $bawah + 3,
            default => ((float) $bawah + (float) $atas) / 2,
        };

        return round($tengah + ($ke % 3) * 0.1, 1);
    }

    // ------------------------------------------------- pengesahan & keuangan

    private function sahkanPendaftaran(): void
    {
        Registration::whereIn('contingent_id', $this->idKontingen())->update([
            'status' => StatusPendaftaran::Terverifikasi,
            'submitted_at' => now(),
            'verified_by' => $this->akun['sekretariat']->id,
            'verified_at' => now(),
        ]);
    }

    private function lunasiTagihan(): void
    {
        $builder = app(InvoiceBuilder::class);
        $kelola = app(KelolaInvoice::class);
        $total = 0;

        foreach ($this->kontingen as $kontingen) {
            $invoice = $kelola->tandaiLunas($kelola->kunci($builder->untuk($kontingen)), 'manual');
            $total += $invoice->total_amount;
        }

        $this->command?->info('Tagihan lunas: '.count($this->kontingen).' kontingen, total Rp'.number_format($total, 0, ',', '.').'.');
    }

    /**
     * Timbang badan ditulis borongan, bukan satu per satu lewat model.
     *
     * Dua ribu baris yang isinya sama persis bentuknya tidak menuntut satu pun
     * kejadian model, dan menyimpannya satu-satu menambah menit ke waktu
     * penyusunan data tanpa menambah satu pun hal yang bisa diuji.
     */
    private function timbangBadan(): void
    {
        $baris = [];

        Registration::whereIn('contingent_id', $this->idKontingen())
            ->whereNotNull('weight_class_id')
            ->with('weightClass', 'athletes')
            ->chunk(500, function (Collection $pendaftaran) use (&$baris): void {
                foreach ($pendaftaran as $satu) {
                    if (! $satu->weightClass?->golongan_usia->adaTimbangBadan()) {
                        continue;
                    }

                    foreach ($satu->athletes as $atlet) {
                        $baris[] = [
                            'registration_id' => $satu->id,
                            'athlete_id' => $atlet->id,
                            'weight' => $atlet->weight_claim,
                            'passed' => true,
                            'weighed_at' => now(),
                            'recorded_by' => $this->akun['sekretariat']->id,
                            'notes' => 'Timbang badan data uji.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            });

        foreach (array_chunk($baris, 500) as $potongan) {
            DB::table('weight_ins')->insert($potongan);
        }

        $this->command?->info('Timbang badan: '.count($baris).' atlet, semuanya lolos.');
    }

    // ------------------------------------------------------- bagan & jadwal

    /**
     * Bagan tanding, dan di sinilah KEDUA mode dipakai berdampingan.
     *
     * Golongan usia dini dan pra remaja disusun dengan mode pemasalan --
     * bentuk yang memang dipakai kejuaraan usia dini, dan yang membuat seluruh
     * pesertanya bertanding di babak pertama. Golongan remaja ke atas memakai
     * bagan gugur pangkat dua seperti kejuaraan resmi. Satu kejuaraan berisi
     * keduanya, persis seperti di lapangan.
     */
    private function susunBaganTanding(): void
    {
        $generator = app(BracketGenerator::class);
        $pemasalan = [GolonganUsia::PraUsiaDini, GolonganUsia::UsiaDini1, GolonganUsia::UsiaDini2, GolonganUsia::PraRemaja];
        $hitung = ['gugur' => 0, 'pemasalan' => 0];

        WeightClass::where('tournament_id', $this->tournament->id)
            ->orderBy('id')
            ->chunk(50, function (Collection $kelas) use ($generator, $pemasalan, &$hitung): void {
                foreach ($kelas as $satu) {
                    if ($generator->pesertaSah($satu)->count() < 2) {
                        continue;
                    }

                    $mode = in_array($satu->golongan_usia, $pemasalan, strict: true)
                        ? ModeBagan::Pemasalan
                        : ModeBagan::Gugur;

                    $generator->kunci(
                        $generator->untukKelas($satu, acak: true, mode: $mode),
                        $this->akun['ketua'],
                    );

                    $hitung[$mode->value]++;
                }
            });

        $this->command?->info("Bagan tanding: {$hitung['gugur']} kelas gugur, {$hitung['pemasalan']} kelas pemasalan.");
    }

    /**
     * Nomor Jurus disusun bersistem gugur (battle), bentuk naskah 2025.
     *
     * Penampilan ronde pertamanya ikut dibuat, jadi panel juri Jurus punya
     * sesuatu untuk dinilai tanpa satu pun langkah manual lebih dulu.
     */
    private function susunBaganJurus(): void
    {
        $susun = app(SusunBaganJurus::class);
        $bagan = 0;
        $penampilan = 0;

        foreach (JurusEvent::where('tournament_id', $this->tournament->id)->orderBy('id')->get() as $nomor) {
            if ($susun->pesertaSah($nomor)->count() < 2) {
                continue;
            }

            $nomor->update(['format' => FormatJurus::Battle]);

            $bracket = $susun->untukNomor($nomor->refresh(), acak: true);
            $bagan++;

            foreach ($bracket->battles()->whereNotNull('red_registration_id')->whereNotNull('blue_registration_id')->get() as $battle) {
                $penampilan += $susun->siapkanPenampilan($battle)->count();
            }
        }

        $this->command?->info("Bagan Jurus: {$bagan} nomor, {$penampilan} penampilan disiapkan.");
    }

    private function jadwalkanPartai(array $profil): void
    {
        $penjadwal = app(PenjadwalPartai::class);
        $gelanggang = Arena::where('tournament_id', $this->tournament->id)->orderBy('sort_order')->get();
        $terjadwal = 0;

        $this->partaiSiap()->each(function (SilatMatch $partai, int $indeks) use ($penjadwal, $gelanggang, &$terjadwal): void {
            $penjadwal->tetapkan($partai, $gelanggang[$indeks % $gelanggang->count()]);
            $terjadwal++;
        });

        $this->command?->info("Jadwal: {$terjadwal} partai ke {$gelanggang->count()} gelanggang.");
    }

    /**
     * Aparat ditugaskan per gelanggang, bukan per partai satu-satu.
     *
     * Gelanggang ke-n memakai wasit ke-n dan juri (3n-2..3n), jadi ketiga
     * gelanggang bisa berjalan bersamaan tanpa satu orang pun merangkap --
     * syarat yang baru terasa saat gelanggangnya lebih dari dua.
     */
    private function tugaskanAparat(array $profil): void
    {
        $jumlahJuri = $this->tournament->peraturan()->jumlah_juri_tanding;
        $gelanggang = Arena::where('tournament_id', $this->tournament->id)->orderBy('sort_order')->get();
        $baris = [];

        foreach ($gelanggang as $urutan => $satu) {
            $nomorGelanggang = $urutan + 1;

            foreach ($this->partaiSiap()->where('arena_id', $satu->id) as $partai) {
                // Kunci match_officials adalah ULID, bukan bilangan berurut,
                // jadi penulisan borongan harus membawanya sendiri.
                $baris[] = [
                    'id' => (string) Str::ulid(),
                    'match_id' => $partai->id,
                    'user_id' => $this->akun["wasit{$nomorGelanggang}"]->id,
                    'role' => MatchOfficial::ROLE_WASIT,
                    'number' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                foreach (range(1, $jumlahJuri) as $nomor) {
                    $baris[] = [
                        'id' => (string) Str::ulid(),
                        'match_id' => $partai->id,
                        'user_id' => $this->akun['juri'.(($urutan * 3) + $nomor)]->id,
                        'role' => MatchOfficial::ROLE_JURI,
                        'number' => $nomor,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($baris, 500) as $potongan) {
            DB::table('match_officials')->insert($potongan);
        }

        $this->command?->info('Aparat: '.count($baris).' penugasan.');
    }

    // ------------------------------------------------------------- bantuan

    /** @return Collection<int, SilatMatch> */
    private function partaiSiap(): Collection
    {
        return SilatMatch::query()
            ->whereHas('bracket.weightClass', fn ($q) => $q->where('tournament_id', $this->tournament->id))
            ->whereNotNull('red_registration_id')
            ->whereNotNull('blue_registration_id')
            ->where('status', SilatMatch::STATUS_TERJADWAL)
            ->orderBy('bracket_id')->orderBy('round')->orderBy('position')
            ->get();
    }

    /** @return array<int, int> */
    private function idKontingen(): array
    {
        return collect($this->kontingen)->pluck('id')->all();
    }

    private function ringkasan(array $profil, float $detik): void
    {
        $atlet = Athlete::whereIn('contingent_id', $this->idKontingen())->count();
        $pendaftaran = Registration::whereIn('contingent_id', $this->idKontingen())->count();
        $partai = SilatMatch::whereHas('bracket.weightClass', fn ($q) => $q->where('tournament_id', $this->tournament->id))->count();

        $this->command?->newLine();
        $this->command?->info("{$profil['nama']} #{$this->tournament->id} siap dalam ".number_format($detik, 1).' detik.');
        $this->command?->line("  Kontingen   {$profil['kontingen']}");
        $this->command?->line("  Atlet       {$atlet}");
        $this->command?->line("  Pendaftaran {$pendaftaran}");
        $this->command?->line("  Partai      {$partai}");
        $this->command?->line("  Gelanggang  {$profil['gelanggang']}");
        $this->command?->line('  Masuk dengan akun apa pun berakhiran @silat.test, kata sandi "password".');

        if ($this->tanpaBagan) {
            $this->command?->warn('  Bagan dan jadwal SENGAJA belum disusun (--tanpa-bagan).');
        }
    }
}
