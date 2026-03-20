$ErrorActionPreference = "Stop"

Write-Host "== AdsCast Quality Gate ==" -ForegroundColor Cyan

$root = Split-Path -Parent $PSScriptRoot
$backend = Join-Path $root "backend"
$frontend = Join-Path $root "frontend"

if (-not (Test-Path $backend)) {
  throw "Backend dizini bulunamadi: $backend"
}

if (-not (Test-Path $frontend)) {
  throw "Frontend dizini bulunamadi: $frontend"
}

Write-Host "`n[1/5] PHP syntax check" -ForegroundColor Yellow
$phpFiles = Get-ChildItem -Path $backend -Recurse -Filter *.php |
  Where-Object { $_.FullName -notmatch "\\vendor\\" }

foreach ($file in $phpFiles) {
  php -l $file.FullName | Out-Null
  if ($LASTEXITCODE -ne 0) {
    throw "PHP syntax hatasi: $($file.FullName)"
  }
}

Write-Host "`n[2/5] Backend tests" -ForegroundColor Yellow
Push-Location $backend
try {
  php artisan test --testsuite=Unit,Feature
  if ($LASTEXITCODE -ne 0) {
    throw "Backend testleri basarisiz."
  }
}
finally {
  Pop-Location
}

Write-Host "`n[3/5] Frontend lint" -ForegroundColor Yellow
Push-Location $frontend
try {
  npm run lint
  if ($LASTEXITCODE -ne 0) {
    throw "Frontend lint basarisiz."
  }
}
finally {
  Pop-Location
}

Write-Host "`n[4/5] Frontend smoke routes" -ForegroundColor Yellow
Push-Location $frontend
try {
  npm run smoke
  if ($LASTEXITCODE -ne 0) {
    throw "Frontend smoke testi basarisiz."
  }
}
finally {
  Pop-Location
}

Write-Host "`n[5/5] Frontend build smoke" -ForegroundColor Yellow
Push-Location $frontend
try {
  if (Test-Path ".next") {
    Remove-Item ".next" -Recurse -Force -ErrorAction SilentlyContinue
  }
  if (Test-Path "out") {
    Remove-Item "out" -Recurse -Force -ErrorAction SilentlyContinue
  }
  npm run build
  if ($LASTEXITCODE -ne 0) {
    Write-Host "Ilk build denemesi basarisiz. Temizleyip tekrar deneniyor..." -ForegroundColor DarkYellow
    Start-Sleep -Seconds 1
    if (Test-Path ".next") {
      Remove-Item ".next" -Recurse -Force -ErrorAction SilentlyContinue
    }
    if (Test-Path "out") {
      Remove-Item "out" -Recurse -Force -ErrorAction SilentlyContinue
    }
    npm run build
    if ($LASTEXITCODE -ne 0) {
      throw "Frontend build basarisiz."
    }
  }
}
finally {
  Pop-Location
}

Write-Host "`nQuality gate basarili." -ForegroundColor Green
