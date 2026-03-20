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

$remoteScript = @'
set -euo pipefail

DOMAIN_ROOT="$HOME/domains/__DOMAIN__"
REPO_DIR="$DOMAIN_ROOT/adscast"
BACKEND_DIR="$REPO_DIR/backend"
PUB_DIR="$DOMAIN_ROOT/public_html"
APP_URL="__APP_URL__"

if [ ! -d "$DOMAIN_ROOT" ]; then
  echo "Domain klasoru bulunamadi: $DOMAIN_ROOT" >&2
  exit 1
fi

if [ ! -d "$REPO_DIR/.git" ]; then
  git clone https://github.com/basyilmaz/adscast.git "$REPO_DIR"
else
  cd "$REPO_DIR"
  git fetch origin
  git reset --hard origin/main
fi

cd "$BACKEND_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

SQLITE_PATH="$BACKEND_DIR/database/database.sqlite"
touch "$SQLITE_PATH"

if [ ! -f ".env" ]; then
  APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
  cat > .env <<EOF
APP_NAME=AdsCast
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_TIMEZONE=Europe/Istanbul
APP_URL=$APP_URL
FRONTEND_ORIGIN=$APP_URL

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
APP_MAINTENANCE_STORE=database

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=sqlite
DB_DATABASE=$SQLITE_PATH

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.castintech.com

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=database
CACHE_PREFIX=adscast

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

META_APP_ID=
META_APP_SECRET=
META_REDIRECT_URI=$APP_URL/settings/meta/callback
META_API_VERSION=v20.0
META_WEBHOOK_VERIFY_TOKEN=

AI_PROVIDER=mock
AI_API_KEY=
AI_MODEL=gpt-4.1-mini
AI_TEMPERATURE=0.2

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="AdsCast"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="AdsCast"
EOF
fi

php artisan migrate --force
php artisan optimize:clear
php artisan optimize

mkdir -p "$PUB_DIR"
BACKUP_DIR="$DOMAIN_ROOT/public_html_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

shopt -s dotglob
if [ -d "$PUB_DIR/_next" ] && [ -f "$PUB_DIR/index.html" ]; then
  echo "Mevcut static frontend deploy tespit edildi; public_html korunuyor."
else
  if [ -n "$(ls -A "$PUB_DIR" 2>/dev/null)" ]; then
    mv "$PUB_DIR"/* "$BACKUP_DIR"/
  fi

  cp "$BACKEND_DIR/public/.htaccess" "$PUB_DIR/.htaccess"
  cp "$BACKEND_DIR/public/favicon.ico" "$PUB_DIR/favicon.ico"
  cp "$BACKEND_DIR/public/robots.txt" "$PUB_DIR/robots.txt"

  cat > "$PUB_DIR/index.php" <<'PHP'
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
fi

ln -sfn ../adscast/backend/storage/app/public "$PUB_DIR/storage"
chmod -R 775 "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache"

echo "Deploy tamamlandi."
echo "Kontrol URL: $APP_URL/up"
'@

$remoteScript = $remoteScript.
    Replace("__DOMAIN__", $Domain).
    Replace("__APP_URL__", $AppUrl)

$encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($remoteScript))
$sshCmd = "echo $encoded | base64 -d | bash -s"

ssh -i $SshKey -p $Port "$User@$ServerHost" $sshCmd
