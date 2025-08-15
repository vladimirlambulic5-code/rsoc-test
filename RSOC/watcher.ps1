$ErrorActionPreference = 'Stop'
$Root = $PSScriptRoot
if (-not $Root) { $Root = Split-Path -Parent $MyInvocation.MyCommand.Path }
Set-Location $Root

$ProjectName = Split-Path $Root -Leaf
$BackupDir   = Join-Path $Root '_backups'
if (-not (Test-Path $BackupDir)) { New-Item -ItemType Directory -Path $BackupDir | Out-Null }

# IGNORE: _backups ili logs (i kad su folder sami, i kad je fajl unutra), .zip i temp fajlovi
#  - \\(_backups|logs)(\\|$)  → poklapa "...\<name>\_backups\" ILI "...\<name>\_backups" (bez trailing slash)
$IgnoreRegex = [regex]'\\(_backups|logs)(\\|$)|\.zip$|~\$|\.tmp$|\.swp$|\.DS_Store$'

$fsw = New-Object System.IO.FileSystemWatcher $Root, '*'
$fsw.IncludeSubdirectories = $true
$fsw.NotifyFilter = [IO.NotifyFilters]'FileName, DirectoryName, LastWrite, Size, LastAccess'

Write-Host ("Watching: {0}" -f $Root) -ForegroundColor Green
Write-Host "Events: Changed, Created, Renamed, Deleted" -ForegroundColor Green
Write-Host "Backups: $BackupDir" -ForegroundColor Green
Write-Host "Zaustavi sa CTRL+C.`n" -ForegroundColor Yellow

$script:LastEventPerPath = @{}
$script:LastZipTime = Get-Date
$MinGapSeconds = 0.5
$MinZipGapSec  = 0.5

function New-ZipBackup {
  $now = Get-Date
  if (($now - $script:LastZipTime).TotalSeconds -lt $MinZipGapSec) { return }
  $script:LastZipTime = $now

  $timestamp = $now.ToString('yyyy-MM-dd_HH-mm-ss')
  $zipPath = Join-Path $BackupDir ("{0}_{1}.zip" -f $ProjectName, $timestamp)

  try {
    $files = Get-ChildItem -LiteralPath $Root -File -Recurse | Where-Object {
      $IgnoreRegex.IsMatch($_.FullName) -eq $false
    }
    if ($files.Count -gt 0) {
      if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
      Compress-Archive -Path $files.FullName -DestinationPath $zipPath -CompressionLevel Optimal
      Write-Host "[BACKUP] Kreiran: $zipPath" -ForegroundColor Cyan
    }
    # rotacija na zadnjih 20
    $zips = Get-ChildItem $BackupDir -Filter '*.zip' | Sort-Object LastWriteTime -Descending
    if ($zips.Count -gt 20) { $zips | Select-Object -Skip 20 | Remove-Item -Force }
  }
  catch {
    Write-Host "[ERROR] Backup nije uspio: $($_.Exception.Message)" -ForegroundColor Red
  }
}

function Handle-ChangeEvent {
  param($e)
  $path = $null
  try { $path = $e.FullPath } catch { return }
  if (-not $path) { return }

  # Dodatni hard-guard: ako putanja pogađa _backups bilo kako, preskoči
  if ($path -like "*\_backups*" -or $IgnoreRegex.IsMatch($path)) { return }

  Write-Host ("[EVENT] {0}: {1}" -f $e.ChangeType, $path) -ForegroundColor Yellow

  $now = Get-Date
  $last = $script:LastEventPerPath[$path]
  if ($last -and (($now - $last).TotalSeconds -lt $MinGapSeconds)) { return }
  $script:LastEventPerPath[$path] = $now

  Start-Sleep -Milliseconds 150
  New-ZipBackup
}

$subs = @()
$subs += Register-ObjectEvent $fsw Changed -SourceIdentifier 'FSW_Changed' -Action { Handle-ChangeEvent $EventArgs }
$subs += Register-ObjectEvent $fsw Created -SourceIdentifier 'FSW_Created' -Action { Handle-ChangeEvent $EventArgs }
$subs += Register-ObjectEvent $fsw Renamed -SourceIdentifier 'FSW_Renamed' -Action { Handle-ChangeEvent $EventArgs }
$subs += Register-ObjectEvent $fsw Deleted -SourceIdentifier 'FSW_Deleted' -Action { Handle-ChangeEvent $EventArgs }

# inicijalni backup
New-ZipBackup

try { while ($true) { Start-Sleep -Seconds 1 } }
finally {
  foreach ($s in $subs) { Unregister-Event -SourceIdentifier $s.Name -ErrorAction SilentlyContinue }
  $fsw.EnableRaisingEvents = $false
  $fsw.Dispose()
  Write-Host "Watcher zaustavljen." -ForegroundColor DarkGray
}
