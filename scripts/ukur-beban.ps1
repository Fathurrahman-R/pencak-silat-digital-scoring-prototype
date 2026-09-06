<#
.SYNOPSIS
    Mengukur berapa lama satu endpoint membalas saat ditarik beberapa panel sekaligus.

.DESCRIPTION
    Yang dijawab skrip ini satu pertanyaan: apakah permintaan yang datang
    berbarengan dikerjakan berbarengan, atau berbaris?

    Server bawaan PHP (`php artisan serve`) melayani satu permintaan pada satu
    waktu, jadi lima tarikan bersamaan memakan waktu kira-kira lima kali satu
    tarikan. Nginx dengan kolam php-cgi tidak. Selisih itulah yang menempel di
    setiap tekanan tombol juri saat gelanggang ramai, dan skrip ini yang
    mengukurnya -- sebelum dan sesudah pindah server, dengan cara yang sama.

    Endpoint bawaannya overlay state: ia menghitung state partai yang sama
    dengan yang ditarik panel, tapi tidak butuh login, jadi pengukurannya bisa
    diulang siapa pun tanpa menyiapkan sesi lebih dulu.

.PARAMETER Alamat
    URL yang ditarik. Bawaan http://127.0.0.1:8000/overlay/state/1

.PARAMETER Bersamaan
    Daftar tingkat kebersamaan yang diuji. Bawaan 1, 5, 10.

.PARAMETER Ulang
    Berapa putaran per tingkat. Bawaan 5. Angka yang dilaporkan diambil dari
    seluruh permintaan di seluruh putaran.

.PARAMETER Cookie
    Header Cookie mentah, untuk mengukur endpoint yang butuh login (mis. state
    panel operator). Salin dari DevTools > Network > Request Headers.

.EXAMPLE
    .\scripts\ukur-beban.ps1
    .\scripts\ukur-beban.ps1 -Alamat http://127.0.0.1:8000/live/gelanggang/1/state
    .\scripts\ukur-beban.ps1 -Bersamaan 1,5,10,20 -Ulang 10
#>
[CmdletBinding()]
param(
    [string] $Alamat = 'http://127.0.0.1:8000/overlay/state/1',
    [int[]] $Bersamaan = @(1, 5, 10),
    [int] $Ulang = 5,
    [string] $Cookie = ''
)

$ErrorActionPreference = 'Stop'

# Satu permintaan pemanasan. Yang pertama selalu lebih lambat -- rute, config,
# dan tampilan baru dikompilasi atau dibaca dari cache di situ, dan angkanya
# tidak menggambarkan apa pun yang terjadi di tengah pertandingan.
try {
    $kepala = @{}
    if ($Cookie -ne '') { $kepala['Cookie'] = $Cookie }

    $awal = Invoke-WebRequest -Uri $Alamat -Headers $kepala -UseBasicParsing -TimeoutSec 30

    if ($awal.StatusCode -ne 200) {
        throw "Pemanasan membalas $($awal.StatusCode)."
    }
} catch {
    Write-Host "Tidak bisa menarik $Alamat"
    Write-Host $_.Exception.Message
    Write-Host ''
    Write-Host 'Periksa: servernya hidup, gelanggangnya ada, dan alamatnya benar.'
    Write-Host 'Overlay dibatasi jaringan lokal -- ukur dari mesin server sendiri.'
    exit 1
}

# Satu permintaan per runspace, jadi yang diukur benar-benar berbarengan.
# Start-Job tidak dipakai: tiap job proses PowerShell baru, dan ongkos
# menyalakannya jauh lebih besar daripada permintaan yang mau diukur.
$kerja = {
    param($url, $cookie)

    $jam = [System.Diagnostics.Stopwatch]::StartNew()

    try {
        $permintaan = [System.Net.HttpWebRequest]::Create($url)
        $permintaan.Method = 'GET'
        $permintaan.Timeout = 60000
        $permintaan.Accept = 'application/json'

        if ($cookie -ne '') { $permintaan.Headers.Add('Cookie', $cookie) }

        $balasan = $permintaan.GetResponse()
        $aliran = New-Object System.IO.StreamReader($balasan.GetResponseStream())
        $null = $aliran.ReadToEnd()
        $aliran.Close()
        $balasan.Close()

        $jam.Stop()

        return [pscustomobject]@{ Ms = $jam.Elapsed.TotalMilliseconds; Galat = $null }
    } catch {
        $jam.Stop()

        return [pscustomobject]@{ Ms = $jam.Elapsed.TotalMilliseconds; Galat = $_.Exception.Message }
    }
}

function Persentil($angka, $p) {
    $urut = $angka | Sort-Object
    $i = [Math]::Ceiling(($p / 100) * $urut.Count) - 1

    if ($i -lt 0) { $i = 0 }

    return $urut[$i]
}

Write-Host ''
Write-Host "Alamat : $Alamat"
Write-Host "Putaran: $Ulang per tingkat"
Write-Host ''

$hasil = @()

foreach ($n in $Bersamaan) {
    $kolam = [runspacefactory]::CreateRunspacePool(1, [Math]::Max($n, 1))
    $kolam.Open()

    $waktu = @()
    $galat = 0
    $jamTotal = [System.Diagnostics.Stopwatch]::StartNew()

    for ($putaran = 0; $putaran -lt $Ulang; $putaran++) {
        $jalan = @()

        for ($i = 0; $i -lt $n; $i++) {
            $ps = [powershell]::Create()
            $ps.RunspacePool = $kolam
            $null = $ps.AddScript($kerja).AddArgument($Alamat).AddArgument($Cookie)
            $jalan += [pscustomobject]@{ Ps = $ps; Tunggu = $ps.BeginInvoke() }
        }

        foreach ($j in $jalan) {
            $keluar = $j.Ps.EndInvoke($j.Tunggu)
            $j.Ps.Dispose()

            foreach ($k in $keluar) {
                $waktu += $k.Ms
                if ($null -ne $k.Galat) { $galat++ }
            }
        }
    }

    $jamTotal.Stop()
    $kolam.Close()
    $kolam.Dispose()

    $hasil += [pscustomobject]@{
        'Bersamaan'   = $n
        'p50 (ms)'    = [Math]::Round((Persentil $waktu 50), 0)
        'p95 (ms)'    = [Math]::Round((Persentil $waktu 95), 0)
        'Maks (ms)'   = [Math]::Round(($waktu | Measure-Object -Maximum).Maximum, 0)
        'Permintaan'  = $waktu.Count
        'Galat'       = $galat
    }

    Write-Host "  $n bersamaan selesai ($($waktu.Count) permintaan, $([Math]::Round($jamTotal.Elapsed.TotalSeconds, 1)) detik)"
}

Write-Host ''
$hasil | Format-Table -AutoSize
Write-Host 'Yang dibaca: p50 pada 5 dan 10 bersamaan dibandingkan p50 pada 1.'
Write-Host 'Berbaris -> naik sebanding jumlahnya. Berbarengan -> naik jauh lebih landai.'
Write-Host ''
