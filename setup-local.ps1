$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = Join-Path $projectRoot '.tools\php\php.exe'
$composer = Join-Path $projectRoot '.tools\composer.phar'
$nodeRoot = Get-ChildItem (Join-Path $projectRoot '.tools\node-dist') -Directory | Select-Object -First 1 -ExpandProperty FullName
if (-not (Test-Path $php) -or -not (Test-Path $composer) -or -not $nodeRoot) { throw 'Runtime lokal .tools tidak lengkap.' }
Set-Location $projectRoot
$env:PATH = $nodeRoot + ';' + $env:PATH
$env:COMPOSER_CACHE_DIR = Join-Path $projectRoot '.tools\composer-cache'
$env:npm_config_cache = Join-Path $projectRoot '.tools\npm-cache'
& $php $composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
& $php artisan package:discover
& $php artisan key:generate --force
if (-not (Test-Path 'database\database.sqlite')) { New-Item -ItemType File 'database\database.sqlite' | Out-Null }
& $php artisan migrate:fresh --seed --force
& (Join-Path $nodeRoot 'npm.cmd') install
& (Join-Path $nodeRoot 'npm.cmd') run build
& $php artisan test
