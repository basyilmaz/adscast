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
  node .\node_modules\eslint\bin\eslint.js .
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
  node .\scripts\smoke-check.mjs
  if ($LASTEXITCODE -ne 0) {
    throw "Frontend smoke testi basarisiz."
  }
}
finally {
  Pop-Location
}

Write-Host "`n[5/5] Frontend build smoke" -ForegroundColor Yellow
$qualityBuildRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("adscast-next-build-" + [System.Guid]::NewGuid().ToString("N"))
$qualityFrontend = Join-Path $qualityBuildRoot "frontend"

try {
  New-Item -ItemType Directory -Path $qualityFrontend -Force | Out-Null

  $robocopyArgs = @(
    $frontend,
    $qualityFrontend,
    "/MIR",
    "/XD", "node_modules", ".next", ".next-quality", "out"
  )

  & robocopy @robocopyArgs | Out-Null
  if ($LASTEXITCODE -gt 7) {
    throw "Frontend build smoke icin gecici kopya olusturulamadi."
  }

  New-Item -ItemType Junction -Path (Join-Path $qualityFrontend "node_modules") -Target (Join-Path $frontend "node_modules") -Force | Out-Null

  Push-Location $qualityFrontend
  try {
    $qualityDistDir = ".next-quality"
    $env:NEXT_DIST_DIR = $qualityDistDir
    node .\node_modules\next\dist\bin\next build --webpack
    if ($LASTEXITCODE -ne 0) {
      throw "Frontend build basarisiz."
    }
  }
  finally {
    Remove-Item Env:NEXT_DIST_DIR -ErrorAction SilentlyContinue
    Pop-Location
  }
}
finally {
  if (Test-Path $qualityBuildRoot) {
    Remove-Item $qualityBuildRoot -Recurse -Force -ErrorAction SilentlyContinue
  }
}

Write-Host "`nQuality gate basarili." -ForegroundColor Green
