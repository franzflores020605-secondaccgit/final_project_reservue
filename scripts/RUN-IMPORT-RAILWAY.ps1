# Copy this file, edit the 3 lines marked EDIT below, then run:
#   .\scripts\RUN-IMPORT-RAILWAY.ps1

$RailwayHost = "kodama.proxy.rlwy.net"
$RailwayPort = 51926
$RailwayUser = "root"
$RailwayPassword = "EDIT_PUT_MYSQLPASSWORD_HERE"
$RailwayDatabase = "railway"

if ($RailwayPassword -like "EDIT_*") {
    Write-Host "ERROR: Open scripts\RUN-IMPORT-RAILWAY.ps1 and set your Railway MYSQLPASSWORD." -ForegroundColor Red
    exit 1
}

& "$PSScriptRoot\import-to-railway.ps1" `
    -DbHost $RailwayHost `
    -Port $RailwayPort `
    -User $RailwayUser `
    -Password $RailwayPassword `
    -Database $RailwayDatabase
