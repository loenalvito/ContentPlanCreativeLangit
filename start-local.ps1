$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = Join-Path $projectRoot '.tools\php\php.exe'
if (-not (Test-Path $php)) { throw 'PHP lokal belum tersedia. Jalankan setup-local.ps1 terlebih dahulu.' }
Set-Location $projectRoot
& $php artisan serve --host=127.0.0.1 --port=8000
