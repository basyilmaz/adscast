# AdsCast - Deployment Rehberi

## Hedef Topoloji

- Frontend: Vercel veya Hostinger container
- Backend API: VPS/container
- DB: MySQL veya PostgreSQL
- Queue/Cache: Redis
- Queue dashboard: Horizon (Linux ortam)

## Ortam Degiskenleri

Backend:

- `APP_ENV`, `APP_KEY`, `APP_URL`
- `DB_CONNECTION=mysql|pgsql`
- MySQL icin `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- PostgreSQL icin PG host/port/db/user/pass
- `QUEUE_CONNECTION=redis`
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
- `META_APP_ID`, `META_APP_SECRET`, `META_REDIRECT_URI`
- `META_MODE=live|stub`
- `META_GRAPH_BASE_URL`, `META_DIALOG_BASE_URL`
- `META_RAW_PAYLOAD_RETENTION_DAYS`, `META_OAUTH_STATE_TTL_MINUTES`, `META_SCOPES`
- `META_SCHEDULE_ENABLED`, `META_ASSET_SYNC_INTERVAL_HOURS`
- `META_INSIGHTS_SYNC_INTERVAL_HOURS`, `META_INSIGHTS_LOOKBACK_DAYS`
- `META_RULES_WINDOW_DAYS`, `META_RECOMMENDATION_INTERVAL_HOURS`, `META_AUTOMATION_LOCK_SECONDS`
- `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`
- `AI_BASE_URL`, `AI_TIMEOUT_SECONDS`, `AI_USER_AGENT`
- `REPORT_DELIVERIES_ENABLED`, `REPORT_DELIVERIES_LOCK_SECONDS`
- `REPORT_SHARES_DEFAULT_EXPIRY_DAYS`, `REPORT_SHARES_MAX_EXPIRY_DAYS`

Frontend:

- `NEXT_PUBLIC_APP_NAME`
- `NEXT_PUBLIC_API_BASE_URL`

## Backend Deploy Adimlari

1. `composer install --no-dev --optimize-autoloader`
2. `php artisan key:generate` (ilk kurulum)
3. `php artisan migrate --force`
4. Ilk tenant/workspace bootstrap:
   - `php artisan adscast:bootstrap-workspace --admin-email=admin@castintech.com --admin-password=<strong-password> --force`
5. Meta connector modu:
   - production: `META_MODE=live`
   - local/test: `META_MODE=stub`
   - manuel access token akisi icin `META_APP_ID` zorunlu degil, OAuth callback fazi icin gereklidir
   - OAuth callback redirect URI frontend route ile eslesmelidir: `/settings/meta/callback`
6. `php artisan config:cache && php artisan route:cache`
7. Queue worker/Horizon ayaga kaldir
8. Scheduler icin cron:
   - `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`

Varsayilan Meta automation cadence:

- asset sync: 6 saatte bir
- insight duzeltme penceresi: son 7 gun
- insight sync: 24 saatte bir
- rules window: son 30 gun
- recommendation generation: 24 saatte bir

Report delivery foundation:

- `schedule:run` icinden `adscast:run-report-deliveries` her 15 dakikada bir tetiklenir
- gercek e-posta gonderimi yoktur; due schedule kaydi icin yeni `report_snapshot` ve `report_delivery_run` kaydi olusur

Shareable client report foundation:

- public linkler canli rapora degil, kaydedilmis `report_snapshot` kaydina baglanir
- public endpoint token hash ile dogrulanir
- operator panelinde link revoke edilebilir
- public CSV indirme sadece share link ayarinda aciksa kullanilabilir

## Docker Compose (Hostinger Cloud Startup)

Repoda production compose dosyasi bulunur:

- `docker-compose.prod.yml`
- `infra/docker/backend/Dockerfile`
- `infra/docker/frontend/Dockerfile`
- `infra/nginx/adscast.conf`

Calistirma:

1. `.env.production.example` -> `.env.production` kopyala
2. Degiskenleri doldur
3. `./scripts/deploy-prod.sh`

Detayli rehber:

- `docs/hostinger-cloudflare-deploy.md`
- `docs/hostinger-shared-deploy.md` (Docker/root olmayan shared-lite ortamlar)

## Frontend Deploy Adimlari

1. `npm ci`
2. `npm run build`
3. Vercel project env degiskenlerini tanimla
4. Production domain ve backend CORS ayarlarini eslestir

## Horizon Notu

Lokal Windows ortaminda `pcntl/posix` extension eksikligi nedeniyle Horizon calismayabilir. Production hedefi Linux tabanli container/VM'dir.

## Bilinen Sinirlar (MVP)

- Meta publish akisinda adapter seviyesinde scaffold/stub mevcut
- Gelismis breakdown raporlari sonraki fazda
- Billing sistemi MVP disi

## Shared Hosting Notu

Eger sunucuda `docker`, `sudo` veya `crontab` yoksa:

1. Laravel backend `public_html` bridge modeli ile yayinlanir
2. Hostinger hPanel uzerinden ayrik bir MySQL veritabani acilir
3. Queue `sync` modda calistirilir
4. Cron isleri panel uzerinden tanimlanir

Tekrarlanabilir kurulum icin:

- `scripts/deploy-hostinger-shared.ps1`
- `scripts/deploy-hostinger-full.ps1` (frontend static export + backend API bridge)
