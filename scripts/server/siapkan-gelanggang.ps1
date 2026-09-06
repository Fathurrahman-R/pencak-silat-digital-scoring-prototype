<#
.SYNOPSIS
    Menyiapkan satu laptop untuk hari-H: bertanya, menulis .env, memigrasikan,
    lalu menyalakan server.

.DESCRIPTION
    Yang digantikannya adalah menyunting .env dengan tangan di lima laptop,
    pagi-pagi, dengan lima nilai berbeda -- pekerjaan yang salah ketiknya baru
    ketahuan setelah gelanggang tidak bisa menarik data, dan yang paling sering
    keliru justru bagian yang paling tidak kelihatan: token yang beda satu
    huruf, atau alamat IP laptop tetangga yang berubah semalam.

    Skrip ini menanyakannya satu per satu, menunjukkan nilai yang sudah ada
    sebagai bawaan, lalu menulisnya. Menjalankannya ulang aman: yang sudah
    benar tinggal ditekan Enter.

    Yang TIDAK dilakukannya, dan itu disengaja:

      * Tidak pernah menghapus data. `migrate` dijalankan, `migrate:fresh`
        tidak, dan tidak ada satu pun jalan di sini yang menuju ke sana.
      * Tidak menyeed kejuaraan. Data peserta datang dari node global lewat
        sinkron, bukan dari skrip di laptop gelanggang.
      * Tidak memangkas riwayat juri. Itu menghapus bukti; jalannya lewat
        perintah tersendiri yang ditekan sadar.

.PARAMETER LewatiServer
    Hanya menyiapkan konfigurasi dan basis data, tanpa menyalakan server.
    Berguna sehari sebelumnya, saat laptop disiapkan tapi belum dipakai.

.PARAMETER Peran
    Mengisi jawaban di muka, sehingga skrip tidak bertanya apa pun. Berguna
    untuk memasang ulang laptop yang setelannya sudah diketahui -- dan untuk
    mengujinya tanpa tangan manusia.

    Menyebut -Peran mengaktifkan mode tanpa tanya untuk SELURUH pertanyaan;
    yang tidak disebutkan memakai nilai yang sudah ada di .env.

.EXAMPLE
    .\scripts\server\siapkan-gelanggang.ps1
    .\scripts\server\siapkan-gelanggang.ps1 -LewatiServer
    .\scripts\server\siapkan-gelanggang.ps1 -Peran gelanggang -Node gelanggang-a -Arena A -Token abc123 -Peer 'global|http://192.168.1.10:8000'
#>
[CmdletBinding()]
param(
    [switch] $LewatiServer,
    [int] $Pekerja = 8,
    [int] $Port = 8000,

    [ValidateSet('gelanggang', 'global')]
    [string] $Peran,

    [string] $Node,
    [string] $Arena,
    [string] $Token,

    # Daftar peer, dipisah koma: "nama|alamat". Tokennya diisi otomatis dari
    # -Token, karena kelima laptop memakai token yang sama.
    [string[]] $Peer
)

# Menyebut -Peran berarti seluruh jawaban datang dari parameter, bukan dari
# pertanyaan. Setengah-setengah -- sebagian ditanya, sebagian tidak -- adalah
# cara paling mudah membuat skrip ini menggantung menunggu jawaban di jendela
# yang tidak ada orangnya.
$tanpaTanya = -not [string]::IsNullOrWhiteSpace($Peran)

$ErrorActionPreference = 'Stop'

$akarSkrip = Split-Path -Parent $MyInvocation.MyCommand.Path
$akarProyek = (Resolve-Path (Join-Path $akarSkrip '..\..')).Path
$berkasEnv = Join-Path $akarProyek '.env'

function Tulis-Judul($teks) {
    Write-Host ''
    Write-Host $teks -ForegroundColor Cyan
    Write-Host ('-' * $teks.Length) -ForegroundColor DarkGray
}

# --- Membaca .env yang sudah ada -------------------------------------------
#
# Nilai lama dipakai sebagai bawaan tiap pertanyaan. Itu yang membuat skrip ini
# aman dijalankan ulang: panitia yang cuma ingin mengganti satu alamat IP tidak
# perlu mengetik ulang empat nilai lain yang sudah benar.
function Baca-Env {
    $peta = @{}

    if (-not (Test-Path $berkasEnv)) { return $peta }

    foreach ($baris in Get-Content $berkasEnv -Encoding UTF8) {
        if ($baris -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)$') {
            $peta[$Matches[1]] = $Matches[2].Trim('"')
        }
    }

    return $peta
}

function Tanya($pesan, $bawaan) {
    if ($script:tanpaTanya) {
        Write-Host ('  {0}: {1}' -f $pesan, $bawaan) -ForegroundColor DarkGray

        return $bawaan
    }

    $petunjuk = if ($bawaan) { "$pesan [$bawaan]" } else { $pesan }
    $jawab = Read-Host $petunjuk

    if ([string]::IsNullOrWhiteSpace($jawab)) { return $bawaan }

    return $jawab.Trim()
}

# --- Menulis kembali .env --------------------------------------------------
#
# Baris yang kuncinya sudah ada diganti di tempat; yang belum ada ditambahkan
# di ujung. Menulis ulang seluruh berkas dari templat akan membuang setelan
# lain yang sudah disesuaikan mesin ini -- jalur PHP, kunci Reverb, saklar
# siaran.
function Simpan-Env($nilaiBaru) {
    $cadangan = Join-Path $akarProyek ('.env.cadangan-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
    Copy-Item $berkasEnv $cadangan -ErrorAction SilentlyContinue

    # ArrayList dibangun lalu diisi, bukan hasil cast.
    # `[ArrayList](Get-Content ...)` di PowerShell 5.1 menghasilkan pembungkus
    # berukuran TETAP -- Add() di bawah akan gagal dengan "Collection was of a
    # fixed size", dan gagalnya baru terjadi pada .env yang belum punya kunci
    # sinkron sama sekali, yaitu tepat pada pemasangan pertama.
    $baris = New-Object System.Collections.ArrayList

    if (Test-Path $berkasEnv) {
        $null = $baris.AddRange(@(Get-Content $berkasEnv -Encoding UTF8))
    }
    $sudah = @{}

    for ($i = 0; $i -lt $baris.Count; $i++) {
        if ($baris[$i] -match '^\s*([A-Z0-9_]+)\s*=') {
            $kunci = $Matches[1]

            # Contains, bukan ContainsKey: $nilaiBaru dibuat dengan [ordered]
            # supaya urutannya terjaga saat ditampilkan, dan OrderedDictionary
            # tidak punya ContainsKey.
            if ($nilaiBaru.Contains($kunci)) {
                $baris[$i] = "$kunci=$($nilaiBaru[$kunci])"
                $sudah[$kunci] = $true
            }
        }
    }

    $belum = $nilaiBaru.Keys | Where-Object { -not $sudah.ContainsKey($_) }

    if ($belum.Count -gt 0) {
        $null = $baris.Add('')
        $null = $baris.Add('# Sinkron antar gelanggang -- lihat docs/MULTI-GELANGGANG.md')

        foreach ($kunci in $belum) {
            $null = $baris.Add("$kunci=$($nilaiBaru[$kunci])")
        }
    }

    [System.IO.File]::WriteAllLines($berkasEnv, $baris, (New-Object System.Text.UTF8Encoding($false)))

    return $cadangan
}

# ===========================================================================

if (-not (Test-Path $berkasEnv)) {
    $contoh = Join-Path $akarProyek '.env.example'

    if (-not (Test-Path $contoh)) {
        throw "Tidak ada .env maupun .env.example di $akarProyek."
    }

    Copy-Item $contoh $berkasEnv
    Write-Host '.env dibuat dari .env.example.' -ForegroundColor Yellow
    & php (Join-Path $akarProyek 'artisan') key:generate --ansi
}

$env0 = Baca-Env

$ipLan = Get-NetIPConfiguration -ErrorAction SilentlyContinue |
    Where-Object { $null -ne $_.IPv4DefaultGateway -and $null -ne $_.IPv4Address } |
    Select-Object -First 1 -ExpandProperty IPv4Address |
    Select-Object -First 1 -ExpandProperty IPAddress

Tulis-Judul 'Laptop ini'

if ($ipLan) {
    Write-Host "Alamat LAN terdeteksi: $ipLan" -ForegroundColor Green
    Write-Host 'Catat alamat ini -- laptop lain memakainya untuk menarik data dari sini.'
} else {
    Write-Host 'Alamat LAN tidak terdeteksi. Pastikan laptop tersambung ke WiFi venue.' -ForegroundColor Yellow
}

Write-Host ''
Write-Host 'Peran laptop ini:'
Write-Host '  1) Gelanggang  -- menilai partai satu gelanggang'
Write-Host '  2) Global      -- tidak melayani gelanggang; memegang data kejuaraan dan arsip bukti'

if ($tanpaTanya) {
    $peranDipakai = $Peran
    Write-Host "  Peran: $peranDipakai" -ForegroundColor DarkGray
} else {
    $peranLama = if ($env0['SINKRON_PERAN'] -eq 'global') { '2' } else { '1' }
    $pilihan = Tanya 'Pilih (1/2)' $peranLama
    $peranDipakai = if ($pilihan -eq '2') { 'global' } else { 'gelanggang' }
}

$nodeBawaan = if ($Node) { $Node } elseif ($env0['SINKRON_NODE']) { $env0['SINKRON_NODE'] } elseif ($peranDipakai -eq 'global') { 'global' } else { 'gelanggang-a' }
$nodeDipakai = Tanya 'Nama laptop ini (dipakai di log dan arsip)' $nodeBawaan

$kodeArena = ''

if ($peranDipakai -eq 'gelanggang') {
    Write-Host ''
    Write-Host 'Kode gelanggang harus SAMA PERSIS dengan yang terdaftar di menu Gelanggang.' -ForegroundColor DarkGray
    Write-Host 'Kode, bukan nama: "A", bukan "Gelanggang A".' -ForegroundColor DarkGray
    $arenaBawaan = if ($Arena) { $Arena } else { $env0['SINKRON_ARENA'] }
    $kodeArena = Tanya 'Kode gelanggang yang dipegang laptop ini' $arenaBawaan

    if ([string]::IsNullOrWhiteSpace($kodeArena)) {
        throw 'Laptop gelanggang harus menyebut kode gelanggangnya.'
    }
}

Tulis-Judul 'Token sinkron'

Write-Host 'Token adalah kata sandi antar-laptop. NILAINYA HARUS SAMA di kelima laptop.'
Write-Host 'Buat sekali di laptop pertama, lalu salin apa adanya ke laptop lain.'
Write-Host ''

$tokenLama = if ($Token) { $Token } else { $env0['SINKRON_TOKEN'] }

if ([string]::IsNullOrWhiteSpace($tokenLama)) {
    $usul = -join ((1..32) | ForEach-Object { '{0:x2}' -f (Get-Random -Minimum 0 -Maximum 256) })
    Write-Host "Usulan token baru: $usul" -ForegroundColor Green
    Write-Host 'Salin nilai itu ke laptop lain, atau tempel token yang sudah dipakai laptop pertama.'
    $tokenDipakai = Tanya 'Token' $usul
} else {
    Write-Host 'Token sudah terpasang di .env. Tekan Enter untuk memakainya.' -ForegroundColor DarkGray
    $tokenDipakai = Tanya 'Token' $tokenLama
}

Tulis-Judul 'Laptop lain'

Write-Host 'Sebutkan laptop yang akan ditarik datanya dari sini.'
Write-Host 'Laptop gelanggang cukup mengenal node global; tambahkan gelanggang lain'
Write-Host 'hanya kalau baganmu lintas gelanggang dan hasilnya ingin ditarik langsung.'
Write-Host ''
Write-Host 'Kosongkan alamat untuk berhenti menambah.' -ForegroundColor DarkGray

$daftarPeer = @()

if ($tanpaTanya) {
    # Tiap entri ditulis "nama|alamat"; tokennya disisipkan di sini supaya
    # pemanggil tidak perlu mengulanginya sebanyak jumlah peer.
    foreach ($satu in $Peer) {
        foreach ($bagian in ($satu -split ',')) {
            $bagian = $bagian.Trim()
            if ([string]::IsNullOrWhiteSpace($bagian)) { continue }

            $potong = $bagian -split '\|'
            if ($potong.Count -lt 2) { continue }

            $nama = $potong[0].Trim()
            $alamat = $potong[1].Trim()
            if ($alamat -notmatch '^https?://') { $alamat = "http://$alamat" }

            $daftarPeer += "$nama|$alamat|$tokenDipakai"
            Write-Host "  Peer: $nama -> $alamat" -ForegroundColor DarkGray
        }
    }

    if ($peranDipakai -eq 'gelanggang' -and -not ($daftarPeer -match '^global\|')) {
        Write-Host 'Tanpa node global, arsip bukti partai tidak akan pernah terkirim.' -ForegroundColor Yellow
    }
} else {
    if ($peranDipakai -eq 'gelanggang') {
        $alamatGlobal = Tanya 'Alamat node global (contoh: 192.168.1.10:8000)' ''

        if ($alamatGlobal) {
            if ($alamatGlobal -notmatch '^https?://') { $alamatGlobal = "http://$alamatGlobal" }
            $daftarPeer += "global|$alamatGlobal|$tokenDipakai"
        } else {
            Write-Host 'Tanpa node global, arsip bukti partai tidak akan pernah terkirim.' -ForegroundColor Yellow
        }
    }

    while ($true) {
        $nama = Tanya 'Nama laptop lain (kosongkan untuk selesai)' ''
        if ([string]::IsNullOrWhiteSpace($nama)) { break }

        $alamat = Tanya "  Alamat $nama (contoh: 192.168.1.12:8000)" ''
        if ([string]::IsNullOrWhiteSpace($alamat)) { continue }
        if ($alamat -notmatch '^https?://') { $alamat = "http://$alamat" }

        $daftarPeer += "$nama|$alamat|$tokenDipakai"
    }
}

# --- Menulis .env ----------------------------------------------------------

$nilai = [ordered]@{
    'SINKRON_PERAN' = $peranDipakai
    'SINKRON_NODE'  = $nodeDipakai
    'SINKRON_ARENA' = $kodeArena
    'SINKRON_TOKEN' = $tokenDipakai
    'SINKRON_PEER'  = '"' + ($daftarPeer -join ',') + '"'
}

$cadangan = Simpan-Env $nilai

Tulis-Judul 'Tersimpan'
Write-Host "Cadangan .env sebelumnya: $(Split-Path -Leaf $cadangan)" -ForegroundColor DarkGray

foreach ($k in $nilai.Keys) {
    $tampil = if ($k -eq 'SINKRON_TOKEN') { ($tokenDipakai.Substring(0, [Math]::Min(8, $tokenDipakai.Length)) + '...') } else { $nilai[$k] }
    Write-Host ('  {0,-14} {1}' -f $k, $tampil)
}

# --- Menyiapkan aplikasi ---------------------------------------------------

Tulis-Judul 'Menyiapkan aplikasi'

Push-Location $akarProyek

try {
    # config:clear wajib lebih dulu: config yang ter-cache membuat seluruh
    # nilai yang barusan ditulis tidak terbaca sama sekali.
    & php artisan config:clear --ansi
    & php artisan migrate --force --ansi

    # Idempoten, dan membuang cache izinnya sendiri. Tanpa ini pengendali
    # gelanggang menemukan 403 di panelnya sendiri.
    & php artisan silat:pindah-pengendali --ansi

    & php artisan optimize --ansi

    Tulis-Judul 'Kesehatan'
    & php artisan silat:kesehatan --ansi
} finally {
    Pop-Location
}

if ($LewatiServer) {
    Write-Host ''
    Write-Host 'Selesai. Server belum dinyalakan (-LewatiServer).' -ForegroundColor Green
    Write-Host "Nyalakan nanti dengan: .\scripts\server\jalankan-server.ps1"
    return
}

# --- Menyalakan server -----------------------------------------------------

Tulis-Judul 'Menyalakan server'

& (Join-Path $akarSkrip 'jalankan-server.ps1') -Pekerja $Pekerja -Port $Port

Write-Host ''
Write-Host 'Reverb belum jalan. Di jendela PowerShell LAIN, jalankan:' -ForegroundColor Yellow
Write-Host '  php artisan reverb:start --host=0.0.0.0 --port=8080'
Write-Host ''
Write-Host 'Setelah itu, dari laptop ini buka http://127.0.0.1:8000 dan masuk sebagai pengendali.' -ForegroundColor Green
Write-Host ''
Write-Host 'Konfigurasi sekarang DI-CACHE (php artisan optimize). Menyunting .env dengan' -ForegroundColor DarkGray
Write-Host 'tangan sesudah ini tidak akan berlaku sampai cache-nya dibuang. Cara amannya:' -ForegroundColor DarkGray
Write-Host 'jalankan skrip ini lagi -- ia membuang cache-nya sendiri di awal.' -ForegroundColor DarkGray

if ($peranDipakai -eq 'gelanggang' -and $daftarPeer.Count -gt 0) {
    Write-Host 'Lalu buka menu Sinkron Gelanggang dan tarik dari node global -- bagan dan jadwal masuk dari sana.'
}
