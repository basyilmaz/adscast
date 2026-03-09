param(
    [string]$ServerHost = "76.13.34.119",
    [int]$Port = 65002,
    [string]$User = "u473759453",
    [string]$SshKey = "$HOME/.ssh/adscast_deploy",
    [string]$Domain = "adscast.castintech.com",
    [string]$AppUrl = "https://adscast.castintech.com"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not (Test-Path $SshKey)) {
    throw "SSH key bulunamadi: $SshKey"
}

$repoRoot = Split-Path -Parent $PSScriptRoot
$frontendDir = Join-Path $repoRoot "frontend"
$backendDeployScript = Join-Path $PSScriptRoot "deploy-hostinger-shared.ps1"

if (-not (Test-Path $backendDeployScript)) {
    throw "Backend deploy scripti bulunamadi: $backendDeployScript"
}

Write-Host "1/4 Backend deploy basliyor..." -ForegroundColor Cyan

& $backendDeployScript `
    -ServerHost $ServerHost `
    -Port $Port `
    -User $User `
    -SshKey $SshKey `
    -Domain $Domain `
    -AppUrl $AppUrl

Write-Host "2/4 Frontend static build basliyor..." -ForegroundColor Cyan

Push-Location $frontendDir
try {
    if (-not (Test-Path (Join-Path $frontendDir "node_modules"))) {
        npm ci
    }

    $env:NEXT_PUBLIC_API_BASE_URL = "/api/v1"
    npm run build
    if ($LASTEXITCODE -ne 0) {
        throw "Frontend build basarisiz."
    }
}
finally {
    Remove-Item Env:\NEXT_PUBLIC_API_BASE_URL -ErrorAction SilentlyContinue
    Pop-Location
}

$outDir = Join-Path $frontendDir "out"
if (-not (Test-Path $outDir)) {
    throw "Static export cikti dizini bulunamadi: $outDir"
}

$bundlePath = Join-Path $env:TEMP "adscast-frontend-out.tar.gz"
if (Test-Path $bundlePath) {
    Remove-Item $bundlePath -Force
}

tar -czf $bundlePath -C $outDir .
if ($LASTEXITCODE -ne 0) {
    throw "Frontend static bundle olusturulamadi."
}

$remoteBundle = "/tmp/adscast-frontend-out.tar.gz"

Write-Host "3/4 Frontend bundle sunucuya yukleniyor..." -ForegroundColor Cyan
scp -i $SshKey -P $Port $bundlePath "$User@$ServerHost`:$remoteBundle"

Write-Host "4/4 Sunucuda bridge ve publish adimi..." -ForegroundColor Cyan

$remoteScript = @'
set -euo pipefail

DOMAIN_ROOT="$HOME/domains/__DOMAIN__"
PUB_DIR="$DOMAIN_ROOT/public_html"
BACKEND_DIR="$DOMAIN_ROOT/adscast/backend"
BUNDLE_PATH="/tmp/adscast-frontend-out.tar.gz"

if [ ! -d "$DOMAIN_ROOT" ]; then
  echo "Domain klasoru bulunamadi: $DOMAIN_ROOT" >&2
  exit 1
fi

if [ ! -d "$BACKEND_DIR" ]; then
  echo "Backend klasoru bulunamadi: $BACKEND_DIR" >&2
  exit 1
fi

if [ ! -f "$BUNDLE_PATH" ]; then
  echo "Frontend bundle bulunamadi: $BUNDLE_PATH" >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
tar -xzf "$BUNDLE_PATH" -C "$TMP_DIR"

mkdir -p "$PUB_DIR"
BACKUP_DIR="$DOMAIN_ROOT/public_html_backup_frontend_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

shopt -s dotglob
if [ -n "$(ls -A "$PUB_DIR" 2>/dev/null)" ]; then
  mv "$PUB_DIR"/* "$BACKUP_DIR"/
fi

cp -a "$TMP_DIR"/. "$PUB_DIR"/

cat > "$PUB_DIR/backend-index.php" <<'PHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../adscast/backend/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../adscast/backend/vendor/autoload.php';

$app = require_once __DIR__.'/../adscast/backend/bootstrap/app.php';

$app->handleRequest(Request::capture());
PHP

cat > "$PUB_DIR/.htaccess" <<'HTACCESS'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    RewriteRule ^up$ backend-index.php [L,QSA]
    RewriteCond %{REQUEST_URI} ^/api(?:/.*)?$ [NC]
    RewriteRule ^api/?(.*)$ backend-index.php [L,QSA]

    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    RewriteRule ^ index.html [L]
</IfModule>
HTACCESS

ln -sfn ../adscast/backend/storage/app/public "$PUB_DIR/storage"
chmod -R 775 "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache"

echo "Frontend+backend bridge deploy tamamlandi."
'@

$remoteScript = $remoteScript.Replace("__DOMAIN__", $Domain)
$encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($remoteScript))
$sshCmd = "echo $encoded | base64 -d | bash -s"

try {
    ssh -i $SshKey -p $Port "$User@$ServerHost" $sshCmd
}
finally {
    if (Test-Path $bundlePath) {
        Remove-Item $bundlePath -Force
    }
}
