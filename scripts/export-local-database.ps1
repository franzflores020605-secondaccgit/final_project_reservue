# Exports your LOCAL Docker MySQL (the database you use on 127.0.0.1:3306)
# into scripts/backups/local-dump.sql

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$BackupDir = Join-Path $ProjectRoot "scripts\backups"
$DumpFile = Join-Path $BackupDir "local-dump.sql"

$ContainerName = "final_reservue_travel-mysql-1"
$DbUser = "flores_user_reservue"
$DbPass = "flores_password_reservue"
$DbName = "flores_db_reservue"
$ContainerDump = "/tmp/local-dump.sql"

New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null

$running = docker ps --format "{{.Names}}" 2>$null | Select-String -SimpleMatch $ContainerName
if (-not $running) {
    Write-Host "ERROR: Start local MySQL first:" -ForegroundColor Red
    Write-Host "  cd `"$ProjectRoot`"" -ForegroundColor Yellow
    Write-Host "  docker compose up -d mysql" -ForegroundColor Yellow
    exit 1
}

Write-Host "Exporting $DbName from $ContainerName ..."
docker exec $ContainerName sh -c "mysqldump -u$DbUser -p$DbPass $DbName --single-transaction --no-tablespaces 2>/dev/null > $ContainerDump"
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: mysqldump failed inside container." -ForegroundColor Red
    exit 1
}

docker cp "${ContainerName}:${ContainerDump}" $DumpFile
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $DumpFile) -or (Get-Item $DumpFile).Length -lt 100) {
    Write-Host "ERROR: Could not copy dump to $DumpFile" -ForegroundColor Red
    exit 1
}

docker exec $ContainerName rm -f $ContainerDump 2>$null | Out-Null

$size = (Get-Item $DumpFile).Length
Write-Host "Done: $DumpFile ($size bytes)" -ForegroundColor Green
Write-Host ""
Write-Host "Next: run scripts\import-to-railway.ps1 with Railway MySQL connection details."
