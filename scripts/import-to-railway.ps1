# Imports scripts/backups/local-dump.sql into your Railway MySQL database.
#
# Get connection values from Railway:
#   MySQL service -> Connect (or Variables tab)
#   Enable "Public Networking" / TCP proxy if you connect from your PC.
#
# Usage:
#   .\scripts\import-to-railway.ps1 -DbHost "kodama.proxy.rlwy.net" -Port 51926 -User root -Password "..." -Database railway

param(
    # DbHost only — do NOT use -Host (PowerShell reserves that name)
    [Parameter(Mandatory = $true)]
    [string]$DbHost,

    [Parameter(Mandatory = $true)]
    [int]$Port,

    [Parameter(Mandatory = $true)]
    [string]$User,

    [Parameter(Mandatory = $true)]
    [string]$Password,

    [Parameter(Mandatory = $true)]
    [string]$Database
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$DumpFile = Join-Path $ProjectRoot "scripts\backups\local-dump.sql"

if (-not (Test-Path $DumpFile)) {
    Write-Host "ERROR: Dump not found. Run scripts\export-local-database.ps1 first." -ForegroundColor Red
    exit 1
}

Write-Host "Importing into Railway MySQL at ${DbHost}:${Port} / $Database ..."
Write-Host "(This replaces data in those tables — Railway staging will match your local DB.)"

Get-Content $DumpFile -Raw | docker run -i --rm mysql:8.0 mysql `
    -h $DbHost -P $Port -u $User "-p$Password" $Database --default-character-set=utf8mb4

if ($LASTEXITCODE -ne 0) {
    Write-Host "Import failed. Check host/port/user/password and that Public Networking is enabled on MySQL." -ForegroundColor Red
    exit 1
}

Write-Host "Import finished. Refresh Railway -> MySQL -> Database -> Data." -ForegroundColor Green
