<?php

namespace Database\Seeders;

use App\Actions\Keuangan\KelolaInvoice;
use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisBerkas;
use App\Enums\JenisKelamin;
use App\Enums\KategoriPertandingan;
use App\Enums\StatusPendaftaran;
use App\Enums\StatusTurnamen;
use App\Models\Arena;
use App\Models\ArenaOfficial;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\FeeSchedule;
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
 * Ukurannya sengaja sebesar kejuaraan sungguhan: 100 pesilat, kelas A sampai E
 * golongan Dewasa, masing-masing 10 putra dan 10 putri, tersebar rata ke
 * sepuluh kontingen. Sepuluh bagan berukuran 16 yang enam tempatnya kosong,
 * jadi bye tersebar di babak pertama — ukuran itulah yang memunculkan hal-hal
 * yang tidak pernah terlihat
 * pada data empat peserta: halaman jadwal yang panjang, bagan yang harus
 * digulir, dan daftar verifikasi yang tidak muat satu layar.
 *
 * Seluruhnya kategori Tanding. Nomor Jurus tidak diikutkan supaya jumlah
 * pesilatnya bulat 100 dan tiap kelas benar-benar berisi sepuluh — mesin
 * penilaian Jurus diuji lewat test suite, bukan lewat data simulasi ini.
 *
 * Yang sengaja TIDAK dikerjakan seeder ini: menjalankan partai, memasukkan
 * nilai juri, menjatuhkan hukuman, dan mengesahkan hasil. Justru itulah yang
 * mau diuji manual — kalau seeder ikut mengerjakannya, yang tersisa untuk
 * diuji tinggal membaca angka yang sudah jadi.
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

    /**
     * Satu angka yang menentukan ukuran seluruh kejuaraan.
     *
     * Tiap kontingen mengirim tepat satu putra dan satu putri per kelas, jadi
     * angka ini sekaligus jumlah kontingen, jumlah peserta tiap kelas, dan
     * jumlah pesilat tiap kontingen (kali dua kelamin). Pembagian rata itulah
     * yang membuat tidak ada satu kontingen pun mengirim dua orang ke kelas
     * yang sama, dan tidak ada atlet yang punya dua partai bersamaan.
     *
     *   10  100 pesilat, bagan 16; babak pertama 2 partai sungguhan + 6 bye,
     *       lalu perempat final penuh 4 partai
     *    8   80 pesilat, bagan 8 tanpa bye sama sekali
     *   16  160 pesilat, bagan 16 penuh
     *
     * Bye hilang sama sekali hanya kalau angkanya pangkat dua. Pada 10 peserta,
     * enam tempat yang kosong disebar susunan unggulan baku ke partai yang
     * berbeda-beda, jadi tidak ada satu cabang pun yang melenggang ke babak
     * belakang tanpa bertanding. Lihat UrutanUnggulan dan
     * BracketGenerator::isiTempat().
     *
     * Daftar nama kontingen di buatKontingen() memuat 10 baris; menaikkan angka
     * ini di atas 10 perlu tambahan nama.
     */
    private const JUMLAH_KONTINGEN = 10;

    /** Kelas Dewasa yang dipertandingkan, untuk putra maupun putri. */
    private const KODE_KELAS = ['A', 'B', 'C', 'D', 'E'];

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
     * Satu akun per peran, ditambah juri dan official sebanyak yang dibutuhkan.
     *
     * Enam juri, bukan tiga: dua gelanggang berjalan bersamaan dan tiap partai
     * memakai juri sebanyak setelan kejuaraan, jadi juri 1-3 memegang
     * Gelanggang A dan juri 4-6 Gelanggang B tanpa satu orang pun merangkap.
     *
     * Tiga jabatan pra-acara — Sekretaris, Bendahara, Petugas Timbang Badan —
     * dan seluruh administrasi kejuaraan kini satu peran `operator-it`, jadi
     * satu akun saja yang menjalankan verifikasi berkas, penagihan, dan
     * timbang badan. Akunnya tetap bernama `sekretariat@` supaya orang yang
     * duduk di meja itu mengenali alamatnya.
     */
    private function buatAkun(): void
    {
        $daftar = [
            'ketua' => ['Hendra Wijaya', 'ketua-pertandingan'],
            'pengawas' => ['Siti Rahayu', 'ketua-pertandingan'],
            'komisi' => ['Agus Salim', 'ketua-pertandingan'],
            'sekretariat' => ['Dewi Lestari', 'operator-it'],
            'operator' => ['Fajar Nugroho', 'operator-it'],
            'operator2' => ['Yudi Hartono', 'operator-it'],

            /*
             * Kursi sendiri, bukan operator yang merangkap. Timer dan
             * pergantian jadwal sudah pindah dari Operator IT ke peran ini
             * (SilatRoleSeeder), jadi simulasi yang menggabungkan keduanya
             * melatih pembagian tugas yang tidak akan dipakai di hari-H.
             */
            'pengendali1' => ['Iwan Setiawan', 'pengendali-gelanggang'],
            'pengendali2' => ['Nur Hidayat', 'pengendali-gelanggang'],
            /*
             * Wasit lebur ke Ketua Pertandingan (SilatRoleSeeder), jadi yang
             * memimpin partai di tiap gelanggang memakai peran itu. Kunci
             * akunnya tetap `wasit1`/`wasit2` supaya yang berdiri di matras
             * mengenali alamatnya.
             */
            'wasit1' => ['Bambang Sutrisno', 'ketua-pertandingan'],
            'wasit2' => ['Rudi Hermawan', 'ketua-pertandingan'],
        ];

        foreach (range(1, 6) as $nomor) {
            $daftar["juri{$nomor}"] = ["Juri {$nomor}", 'juri'];
        }

        foreach (range(1, self::JUMLAH_KONTINGEN) as $nomor) {
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
         * Jendela konsensus dibiarkan pada bawaannya, 2 detik
         * (config/scoring.php). Sebelumnya seeder ini melebarkannya jadi 5
         * detik supaya satu orang bisa menguji sendirian sambil berpindah
         * antar tab -- kelonggaran yang membuat layar simulasi berperilaku
         * berbeda dari kejuaraan sungguhan, termasuk berapa lama indikator
         * juri menyala. Setelannya tetap bisa diubah per kejuaraan lewat
         * Setelan peraturan; yang tidak lagi dilakukan adalah mengubahnya
         * diam-diam di data simulasi.
         */

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
        $pengendali = ['A' => $this->akun['pengendali1'], 'B' => $this->akun['pengendali2']];

        foreach ([['Gelanggang A', 'A'], ['Gelanggang B', 'B']] as $urutan => [$nama, $kode]) {
            $arena = Arena::create([
                'tournament_id' => $this->tournament->id,
                'name' => $nama,
                'code' => $kode,
                'sort_order' => $urutan,
                'is_active' => true,
            ]);

            $arena->operators()->attach($operator[$kode]);

            /*
             * Dua tabel penugasan yang berbeda, dan yang ini tidak boleh
             * dilewatkan: gelanggang tanpa pengendali tidak bisa memulai babak
             * sama sekali. Migrasi arena_pengendali menyalinnya dari
             * arena_operators, tapi hanya atas data yang sudah ada saat migrasi
             * berjalan -- pemasangan baru menjalankannya di atas tabel kosong,
             * jadi tidak ada yang menambal kelalaian di sini.
             */
            $arena->pengendali()->attach($pengendali[$kode]);
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
            ['Pagar Nusa Semarang', 'Jawa Tengah'],
            ['Perisai Putih Denpasar', 'Bali'],
            ['Kera Sakti Medan', 'Sumatera Utara'],
            ['Silat Minang Padang', 'Sumatera Barat'],
            ['Bina Raga Makassar', 'Sulawesi Selatan'],
            ['Garuda Sakti Pontianak', 'Kalimantan Barat'],
        ];

        // Dipotong sesuai ukuran kejuaraan: menurunkan JUMLAH_KONTINGEN cukup
        // satu angka, tanpa perlu ikut memangkas daftar nama di atas.
        foreach (array_slice($daftar, 0, self::JUMLAH_KONTINGEN) as $indeks => [$nama, $daerah]) {
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
     * Seratus pesilat: kelas A sampai E, tiap kelas 10 putra dan 10 putri.
     *
     * Tiap kontingen mengirim tepat satu putra dan satu putri per kelas, jadi
     * tidak ada satu pun kontingen yang bertemu dirinya sendiri di babak
     * pertama, dan tidak ada atlet yang punya dua partai pada saat yang sama.
     *
     * Sepuluh peserta per kelas jatuh ke bagan berukuran 16, jadi enam tempat
     * dibiarkan kosong. Susunan unggulan baku menyebarnya sehingga babak
     * pertama berisi dua partai sungguhan dan enam bye yang langsung
     * diluluskan, lalu perempat final penuh empat partai — keadaan yang paling
     * mudah salah dibaca panel jadwal maupun panel bagan, dan justru itu yang
     * mau diuji.
     */
    private function buatPeserta(): void
    {
        $daftarkan = app(DaftarkanPeserta::class);

        foreach (self::KODE_KELAS as $urutanKelas => $kode) {
            foreach (JenisKelamin::cases() as $jenisKelamin) {
                $kelas = $this->kelasTanding($kode, $jenisKelamin);

                foreach (range(0, self::JUMLAH_KONTINGEN - 1) as $urutanKontingen) {
                    $kontingen = $this->kontingen['k'.($urutanKontingen + 1)];

                    $atlet = $this->buatAtlet(
                        $kontingen,
                        $this->namaPesilat($jenisKelamin, $urutanKontingen, $urutanKelas),
                        $jenisKelamin,
                        $this->beratDalamKelas($kelas, $urutanKontingen),
                    );

                    $daftarkan->tanding($kontingen, $kelas, $atlet);
                }
            }
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
                'recorded_by' => $this->akun['sekretariat']->id,
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
                'verified_by' => $this->akun['sekretariat']->id,
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
                    'recorded_by' => $this->akun['sekretariat']->id,
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

            /*
             * Diundi acak, bukan diurutkan menurut pendaftaran.
             *
             * Kalau tidak diacak, tempat unggulan diisi persis urutan
             * kontingen, sehingga kontingen terakhirlah yang selalu kebagian
             * partai babak pertama sementara kontingen pertama selalu
             * mendapat bye — di kesepuluh kelas sekaligus. Susunan bagannya
             * sah, tapi sebarannya tidak menyerupai undian sungguhan dan
             * membuat uji manual selalu bertemu pola yang sama.
             *
             * Bentuk bagannya sendiri tidak ikut berubah: berapa pun hasil
             * undian, jumlah bye dan jumlah partai tiap babak tetap sama.
             */
            $generator->kunci(
                $generator->untukKelas($kelas, acak: true),
                $this->akun['ketua'],
            );

            $tersusun++;
        }

        $this->command?->info("Bagan tersusun dan terkunci untuk {$tersusun} kelas tanding.");
    }

    /**
     * Partai yang sudah punya dua peserta dibagi ke dua gelanggang
     * berselang-seling, urut menurut nomor tayang tiap gelanggang.
     *
     * Berselang-seling, bukan sekelas sekaligus per gelanggang: dua gelanggang
     * yang berjalan bersamaan itulah yang memunculkan hal-hal yang tidak
     * pernah terlihat pada data satu gelanggang -- penugasan aparat yang
     * berebut orang, dan papan skor publik yang harus menampilkan dua partai
     * berbeda pada saat yang sama.
     */
    private function jadwalkanPartai(): void
    {
        $penjadwal = app(PenjadwalPartai::class);
        $gelanggang = Arena::where('tournament_id', $this->tournament->id)->orderBy('sort_order')->get();
        $terjadwal = 0;

        foreach ($this->partaiSiap() as $indeks => $partai) {
            $penjadwal->tetapkan($partai, $gelanggang[$indeks % $gelanggang->count()]);

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
        $gelanggang = Arena::where('tournament_id', $this->tournament->id)->orderBy('sort_order')->get();
        $gelanggangPertama = $gelanggang->first()?->id;

        /*
         * Kursi gelanggang lebih dulu, dan itu yang sesungguhnya dipakai
         * panitia sejak penugasan aparat pindah ke halaman Gelanggang
         * (September 2026). Baris per partai di bawah tetap dibuat supaya
         * simulasi punya partai yang sudah "pernah ditayangkan" lengkap dengan
         * catatan aparatnya -- di kejuaraan sungguhan baris itu lahir sendiri
         * saat pengendali menunjuk partainya.
         */
        foreach ($gelanggang as $satu) {
            $pertama = $satu->id === $gelanggangPertama;

            ArenaOfficial::where('arena_id', $satu->id)->delete();

            ArenaOfficial::create([
                'arena_id' => $satu->id,
                'user_id' => $this->akun[$pertama ? 'wasit1' : 'wasit2']->id,
                'role' => MatchOfficial::ROLE_WASIT,
            ]);

            foreach (range(1, $jumlahJuri) as $nomor) {
                ArenaOfficial::create([
                    'arena_id' => $satu->id,
                    'user_id' => $this->akun['juri'.($pertama ? $nomor : $nomor + 3)]->id,
                    'role' => MatchOfficial::ROLE_JURI,
                    'number' => $nomor,
                ]);
            }
        }

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

    private function kelasTanding(string $kode, JenisKelamin $jenisKelamin): WeightClass
    {
        return WeightClass::where('tournament_id', $this->tournament->id)
            ->where('golongan_usia', GolonganUsia::Dewasa)
            ->where('jenis_kelamin', $jenisKelamin)
            ->where('code', $kode)
            ->firstOrFail();
    }

    /**
     * Nama pesilat dari dua kolam kata, bukan seratus baris yang diketik satu
     * per satu.
     *
     * Nama depan bergeser mengikuti kelas dan nama belakang bergeser tiga kali
     * lebih cepat, sehingga lima puluh pasangan tiap jenis kelamin tidak ada
     * yang kembar, dan sepuluh pesilat satu kontingen tetap terbaca sebagai
     * sepuluh orang berbeda — bukan satu marga yang diulang.
     */
    private function namaPesilat(JenisKelamin $jenisKelamin, int $urutanKontingen, int $urutanKelas): string
    {
        $depan = $jenisKelamin === JenisKelamin::Putra
            ? ['Dimas', 'Andika', 'Bagus', 'Candra', 'Eko', 'Rizky', 'Ilham', 'Gilang', 'Arif', 'Deni']
            : ['Ayu', 'Nadia', 'Intan', 'Putri', 'Sari', 'Dinda', 'Fitri', 'Lestari', 'Mega', 'Rani'];

        $belakang = ['Prakoso', 'Saputra', 'Wicaksono', 'Setiawan', 'Nugraha',
            'Ramadhan', 'Maulana', 'Pratama', 'Budiman', 'Kurniawan'];

        $jumlah = self::JUMLAH_KONTINGEN;

        return $depan[($urutanKontingen + $urutanKelas) % $jumlah]
            .' '.$belakang[($urutanKontingen + 3 * $urutanKelas) % $jumlah];
    }

    /**
     * Berat yang pasti berada di dalam rentang kelas, sedikit berbeda tiap
     * pesilat.
     *
     * Dijaga di sepertiga sampai dua pertiga rentang, tidak pernah menyentuh
     * batasnya: kelas terendah memakai batas bawah inklusif sedangkan kelas
     * lain eksklusif, dan berat yang tepat di angka batas akan lolos di satu
     * kelas tapi ditolak di kelas berikutnya.
     */
    private function beratDalamKelas(WeightClass $kelas, int $urutan): float
    {
        $bawah = (float) $kelas->weight_min;
        $rentang = (float) $kelas->weight_max - $bawah;

        return round($bawah + $rentang * (1 / 3 + $urutan / (3 * self::JUMLAH_KONTINGEN)), 1);
    }

    private function ringkasan(): void
    {
        $id = $this->tournament->id;
        $gelanggang = Arena::where('tournament_id', $id)->orderBy('sort_order')->pluck('id');

        $this->command?->newLine();
        $this->command?->info('=== Kejuaraan simulasi siap dipakai ===');
        $this->command?->line("Kejuaraan  : #{$id} — {$this->tournament->name}");
        $this->command?->line('Gelanggang : '.$gelanggang->implode(', '));
        $this->command?->line('Peserta    : '.Athlete::whereIn('contingent_id', collect($this->kontingen)->pluck('id'))->count()
            .' pesilat Tanding, kelas '.self::KODE_KELAS[0].'–'.self::KODE_KELAS[count(self::KODE_KELAS) - 1]
            .' Dewasa, '.count($this->kontingen).' kontingen');
        $this->command?->line('Kata sandi : '.self::KATA_SANDI.' (seluruh akun)');
        $this->command?->newLine();
        $this->command?->line('Akun  ketua@'.self::DOMAIN.'  operator@'.self::DOMAIN.' (Gelanggang A)  operator2@'.self::DOMAIN.' (Gelanggang B)');
        $this->command?->line('      wasit1@'.self::DOMAIN.'  wasit2@'.self::DOMAIN.'  juri1@'.self::DOMAIN.' … juri6@'.self::DOMAIN);
        $this->command?->line('      sekretariat@ pengawas@ komisi@ official1@ … official'.self::JUMLAH_KONTINGEN.'@');
        $this->command?->newLine();
        $this->command?->line("Panel operator partai pertama tersedia di menu Jadwal kejuaraan #{$id}.");
        $this->command?->line("Live publik  : /live/turnamen/{$id}");
        $this->command?->line("Overlay vMix : /overlay/scorebug/{$gelanggang->first()}");
        $this->command?->line('Langkah uji berikutnya: docs/PANDUAN-WORKFLOW.md bagian B (Hari-H).');
    }
}
