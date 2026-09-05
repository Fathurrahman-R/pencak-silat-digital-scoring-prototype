<#
.SYNOPSIS
    Mematikan Nginx dan kolam php-cgi yang dinyalakan jalankan-server.ps1.

.DESCRIPTION
    php-cgi dimatikan lewat daftar PID yang ditulis saat dinyalakan, bukan
    dengan menyapu seluruh proses bernama php-cgi -- di mesin yang juga
    memakai Laragon atau XAMPP, sapuan itu ikut mematikan situs lain.

    -Semua memaksa sapuan menyeluruh, untuk keadaan daftar PID-nya hilang.
#>
[CmdletBinding()]
param(
    [string] $NginxDir = '',
    [switch] $Semua
)

$ErrorActionPreference = 'Continue'

$akarSkrip = Split-Path -Parent $MyInvocation.MyCommand.Path
$confJalan = Join-Path $akarSkrip '.nginx-jalan.conf'
$berkasPid = Join-Path $akarSkrip '.php-cgi-pid.txt'

# --- Nginx -----------------------------------------------------------------
if ($NginxDir -eq '') {
    $kandidat = @('C:\nginx-1.31.0', 'C:\nginx', 'C:\laragon\bin\nginx\nginx-1.27.3')

    foreach ($k in $kandidat) {
        if (Test-Path (Join-Path $k 'nginx.exe')) { $NginxDir = $k; break }
    }
}

if ($NginxDir -ne '' -and (Test-Path (Join-Path $NginxDir 'nginx.exe'))) {
    # -c wajib ikut disebut: `nginx -s stop` membaca konfigurasi lebih dulu
    # untuk menemukan berkas PID-nya, dan conf bawaan menunjuk ke tempat lain.
    if (Test-Path $confJalan) {
        & (Join-Path $NginxDir 'nginx.exe') -p "$NginxDir\" -c $confJalan -s stop
    } else {
        & (Join-Path $NginxDir 'nginx.exe') -p "$NginxDir\" -s stop
    }

    Write-Host 'nginx: dihentikan'
}

# --- php-cgi ---------------------------------------------------------------
$dimatikan = 0

if ((Test-Path $berkasPid) -and (-not $Semua)) {
    Get-Content $berkasPid | ForEach-Object {
        $id = $_.Trim()

        if ($id -ne '') {
            try {
                Stop-Process -Id ([int]$id) -Force -ErrorAction Stop
                $dimatikan++
            } catch {
                # Sudah mati sendiri, atau PID-nya sudah dipakai proses lain.
            }
        }
    }

    Remove-Item $berkasPid -ErrorAction SilentlyContinue
} else {
    Get-Process php-cgi -ErrorAction SilentlyContinue | ForEach-Object {
        try {
            Stop-Process -Id $_.Id -Force -ErrorAction Stop
            $dimatikan++
        } catch {}
    }

    Remove-Item $berkasPid -ErrorAction SilentlyContinue
}

Write-Host "php-cgi: $dimatikan proses dimatikan"
