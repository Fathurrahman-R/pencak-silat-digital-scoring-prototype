<#
.SYNOPSIS
    Menyalakan Nginx + kolam php-cgi untuk gelanggang, menggantikan `php artisan serve`.

.DESCRIPTION
    `php artisan serve` melayani satu permintaan pada satu waktu. Skrip ini
    menyalakan beberapa proses php-cgi dan meletakkan Nginx di depannya, supaya
    permintaan yang datang berbarengan -- tekanan tombol juri, tarikan state
    tiap panel, halaman overlay vMix -- dikerjakan berbarengan.

    Reverb TIDAK dijalankan di sini kecuali diminta lewat -DenganReverb. Ia
    proses yang berumur panjang dan lognya perlu terlihat; jalankan di jendela
    PowerShell sendiri:

        php artisan reverb:start --host=0.0.0.0 --port=8080

.PARAMETER Pekerja
    Jumlah proses php-cgi. Tiap proses melayani satu permintaan pada satu
    waktu -- di Windows php-cgi tidak bisa fork. Bawaan 8.

.PARAMETER PortAwal
    Port pertama kolam php-cgi. Bawaan 9001, jadi 8 pekerja memakai 9001-9008.

.PARAMETER DenganReverb
    Ikut menyalakan `php artisan reverb:start` di jendela terpisah.

.EXAMPLE
    .\scripts\server\jalankan-server.ps1
    .\scripts\server\jalankan-server.ps1 -Pekerja 12 -DenganReverb
#>
[CmdletBinding()]
param(
    [int] $Pekerja = 8,
    [int] $PortAwal = 9001,
    [string] $PhpCgi = '',
    [string] $NginxDir = '',
    [int] $Port = 8000,
    [switch] $DenganReverb
)

$ErrorActionPreference = 'Stop'

$akarSkrip = Split-Path -Parent $MyInvocation.MyCommand.Path
$akarProyek = Resolve-Path (Join-Path $akarSkrip '..\..')
$docRoot = Join-Path $akarProyek 'public'

if (-not (Test-Path (Join-Path $docRoot 'index.php'))) {
    throw "Tidak menemukan public\index.php di $akarProyek -- skrip ini harus tetap berada di scripts\server\."
}

# --- Menemukan php-cgi.exe -------------------------------------------------
#
# Diambil dari instalasi PHP yang SAMA dengan `php` di PATH. Dua instalasi
# berbeda berarti dua php.ini berbeda: ekstensi yang aktif di baris perintah
# belum tentu aktif di yang melayani web, dan galatnya baru muncul sebagai
# halaman kosong di tengah kejuaraan.
if ($PhpCgi -eq '') {
    $php = Get-Command php -ErrorAction SilentlyContinue

    if ($null -eq $php) {
        throw 'php tidak ada di PATH. Sebutkan -PhpCgi "C:\path\ke\php-cgi.exe".'
    }

    $PhpCgi = Join-Path (Split-Path -Parent $php.Source) 'php-cgi.exe'
}

if (-not (Test-Path $PhpCgi)) {
    throw "php-cgi.exe tidak ditemukan di $PhpCgi. Sebutkan -PhpCgi secara eksplisit."
}

# --- Menemukan nginx.exe ---------------------------------------------------
if ($NginxDir -eq '') {
    $kandidat = @(
        'C:\nginx-1.31.0',
        'C:\nginx',
        'C:\laragon\bin\nginx\nginx-1.27.3'
    )

    foreach ($k in $kandidat) {
        if (Test-Path (Join-Path $k 'nginx.exe')) { $NginxDir = $k; break }
    }

    if ($NginxDir -eq '') {
        # Laragon memberi versi nginx nama folder yang berubah tiap pembaruan.
        $laragon = Get-ChildItem 'C:\laragon\bin\nginx' -Directory -ErrorAction SilentlyContinue |
            Where-Object { Test-Path (Join-Path $_.FullName 'nginx.exe') } |
            Select-Object -First 1

        if ($null -ne $laragon) { $NginxDir = $laragon.FullName }
    }
}

if ($NginxDir -eq '' -or -not (Test-Path (Join-Path $NginxDir 'nginx.exe'))) {
    throw 'nginx.exe tidak ditemukan. Sebutkan -NginxDir "C:\nginx-1.31.0".'
}

# --- Menyusun konfigurasi yang sudah terisi --------------------------------
#
# Nginx tidak membaca variabel lingkungan di berkas konfigurasinya, jadi jalur
# proyek dan daftar port disisipkan ke salinan yang dipakai berjalan. Templat
# di repo tetap bersih dan bisa dipakai di mesin dengan jalur berbeda.
$templat = Join-Path $akarSkrip 'nginx-digiscoring.conf'
$confJalan = Join-Path $akarSkrip '.nginx-jalan.conf'

$portPekerja = $PortAwal..($PortAwal + $Pekerja - 1)
$barisUpstream = ($portPekerja | ForEach-Object { "        server 127.0.0.1:$_;" }) -join "`n"

$isi = Get-Content $templat -Raw -Encoding UTF8
$isi = $isi -replace 'PLACEHOLDER_ROOT', ($docRoot -replace '\\', '/')
$isi = $isi -replace 'PLACEHOLDER_NGINX_CONF', ((Join-Path $NginxDir 'conf') -replace '\\', '/')
$isi = $isi -replace '(?s)(upstream php_gelanggang \{\r?\n).*?(\r?\n    \})', ("`${1}$barisUpstream`${2}")
$isi = $isi -replace 'listen       8000;', "listen       $Port;"

# Tanpa BOM. `Set-Content -Encoding utf8` di Windows PowerShell 5.1 menempelkan
# tanda urutan byte di awal berkas, dan Nginx membacanya sebagai arahan tak
# dikenal: "unknown directive" di baris pertama yang sebenarnya sebuah komentar.
[System.IO.File]::WriteAllText($confJalan, $isi, (New-Object System.Text.UTF8Encoding($false)))

# --- Kolam php-cgi ---------------------------------------------------------
#
# PHP_FCGI_MAX_REQUESTS wajib 0. Bawaannya 500: tanpa ini tiap proses php-cgi
# berhenti sendiri setelah 500 permintaan, dan Nginx membalas 502 sampai
# prosesnya dijalankan ulang -- yang tidak akan terjadi, karena di Windows
# tidak ada manajer proses yang melakukannya. Di gelanggang, 500 permintaan
# habis dalam hitungan menit.
$env:PHP_FCGI_MAX_REQUESTS = 0

$berkasPid = Join-Path $akarSkrip '.php-cgi-pid.txt'
$dirLog = Join-Path $akarSkrip '.log'

if (-not (Test-Path $dirLog)) {
    $null = New-Item -ItemType Directory -Path $dirLog
}

$daftarPid = @()

foreach ($p in $portPekerja) {
    # Keluaran tiap proses dialihkan ke berkasnya sendiri, bukan diwariskan
    # dari jendela ini. Proses latar yang masih memegang stdout jendela induk
    # membuat jendela itu tidak pernah dianggap selesai oleh pemanggilnya --
    # skrip ini akan terlihat menggantung padahal servernya sudah hidup.
    $proses = Start-Process -FilePath $PhpCgi -ArgumentList '-b', "127.0.0.1:$p" `
        -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $dirLog "php-cgi-$p.out.log") `
        -RedirectStandardError (Join-Path $dirLog "php-cgi-$p.err.log")

    $daftarPid += $proses.Id
}

Set-Content -Path $berkasPid -Value ($daftarPid -join "`n") -Encoding UTF8

Write-Host "php-cgi: $Pekerja proses di port $PortAwal-$($PortAwal + $Pekerja - 1)"
Write-Host "         $PhpCgi"

# --- Nginx -----------------------------------------------------------------
#
# Konfigurasinya diperiksa lebih dulu. Nginx yang gagal membaca konfigurasi
# keluar diam-diam di Windows, dan tanpa langkah ini yang terlihat cuma
# halaman yang tidak pernah membalas.
$nginxExe = Join-Path $NginxDir 'nginx.exe'
$logPeriksa = Join-Path $dirLog 'nginx-periksa.log'

$periksa = Start-Process -FilePath $nginxExe `
    -ArgumentList '-p', "$NginxDir\", '-c', $confJalan, '-t' `
    -WindowStyle Hidden -Wait -PassThru `
    -RedirectStandardOutput (Join-Path $dirLog 'nginx-periksa.out.log') `
    -RedirectStandardError $logPeriksa

if ($periksa.ExitCode -ne 0) {
    Write-Host 'Konfigurasi Nginx ditolak -- kolam php-cgi dimatikan lagi.'

    if (Test-Path $logPeriksa) { Get-Content $logPeriksa | ForEach-Object { Write-Host "  $_" } }

    $daftarPid | ForEach-Object { try { Stop-Process -Id $_ -Force -ErrorAction Stop } catch {} }
    Remove-Item $berkasPid -ErrorAction SilentlyContinue
    throw 'Perbaiki scripts\server\nginx-digiscoring.conf lalu jalankan lagi.'
}

# Nginx untuk Windows adalah aplikasi konsol biasa, bukan layanan: ia TIDAK
# melepaskan diri ke latar seperti di Linux. Dijalankan langsung, ia menahan
# jendela PowerShell ini selamanya.
$null = Start-Process -FilePath $nginxExe `
    -ArgumentList '-p', "$NginxDir\", '-c', $confJalan `
    -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $dirLog 'nginx.out.log') `
    -RedirectStandardError (Join-Path $dirLog 'nginx.err.log')

Start-Sleep -Milliseconds 700

if (-not (Get-Process nginx -ErrorAction SilentlyContinue)) {
    Write-Host 'Nginx berhenti seketika sesudah dijalankan -- kolam php-cgi dimatikan lagi.'
    $daftarPid | ForEach-Object { try { Stop-Process -Id $_ -Force -ErrorAction Stop } catch {} }
    Remove-Item $berkasPid -ErrorAction SilentlyContinue
    throw "Periksa $NginxDir\logs\digiscoring-error.log. Port $Port mungkin sudah dipakai proses lain."
}

Write-Host "nginx:   $NginxDir\nginx.exe, port $Port"
Write-Host ''

# Alamat yang dipakai HP juri dan vMix -- bukan localhost, yang di HP menunjuk
# ke HP itu sendiri.
#
# Dipilih dari adapter yang punya gerbang bawaan. Mesin pengembangan lazim
# punya beberapa adapter virtual (WSL, Hyper-V, VirtualBox) yang alamatnya ikut
# terdaftar tapi tidak dijangkau HP siapa pun; mengambil yang pertama saja
# menghasilkan alamat yang terlihat benar dan tidak pernah bisa dibuka.
$ipLan = Get-NetIPConfiguration -ErrorAction SilentlyContinue |
    Where-Object { $null -ne $_.IPv4DefaultGateway -and $null -ne $_.IPv4Address } |
    Select-Object -First 1 -ExpandProperty IPv4Address |
    Select-Object -First 1 -ExpandProperty IPAddress

Write-Host "Dari mesin ini : http://127.0.0.1:$Port"

if ($null -ne $ipLan) {
    Write-Host "Dari HP di LAN : http://${ipLan}:$Port"
}

if ($DenganReverb) {
    Start-Process powershell -ArgumentList '-NoExit', '-Command', `
        "Set-Location '$akarProyek'; php artisan reverb:start --host=0.0.0.0 --port=8080"
    Write-Host 'reverb:  jendela terpisah, port 8080'
} else {
    Write-Host ''
    Write-Host 'Reverb belum jalan. Di jendela lain:'
    Write-Host '  php artisan reverb:start --host=0.0.0.0 --port=8080'
}

Write-Host ''
Write-Host 'Mematikan semuanya: .\scripts\server\hentikan-server.ps1'
