param(
  [Parameter(Mandatory = $true)]
  [string]$Host,

  [Parameter(Mandatory = $true)]
  [string]$User,

  [string]$Port = "22",
  [string]$ProjectPath = "/opt/adscast",
  [string]$RepoUrl = "https://github.com/basyilmaz/adscast.git",
  [string]$Branch = "main"
)

$ErrorActionPreference = "Stop"

Write-Host "Hostinger deployment baslatiliyor..." -ForegroundColor Cyan

$remoteScript = @"
set -euo pipefail
if [ ! -d "$ProjectPath/.git" ]; then
  mkdir -p "$ProjectPath"
  git clone "$RepoUrl" "$ProjectPath"
fi
cd "$ProjectPath"
git fetch --all --prune
git checkout "$Branch"
git pull origin "$Branch"
if [ ! -f ".env.production" ]; then
  cp .env.production.example .env.production
  echo ".env.production olusturuldu. Degiskenleri doldurup tekrar deploy edin."
  exit 2
fi
chmod +x scripts/deploy-prod.sh
./scripts/deploy-prod.sh
"@

ssh -p $Port "$User@$Host" $remoteScript
