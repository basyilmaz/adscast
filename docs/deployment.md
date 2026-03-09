# AdsCast - Deployment Rehberi

## Hedef Topoloji

- Frontend: Vercel veya Hostinger container
- Backend API: VPS/container
- DB: PostgreSQL
- Queue/Cache: Redis
- Queue dashboard: Horizon (Linux ortam)

## Ortam Degiskenleri

Backend:

- `APP_ENV`, `APP_KEY`, `APP_URL`
- `DB_CONNECTION=pgsql` + PG host/port/db/user/pass
- `QUEUE_CONNECTION=redis`
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
- `META_APP_ID`, `META_APP_SECRET`, `META_REDIRECT_URI`
- `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`

Frontend:

- `NEXT_PUBLIC_APP_NAME`
- `NEXT_PUBLIC_API_BASE_URL`

## Backend Deploy Adimlari

1. `composer install --no-dev --optimize-autoloader`
2. `php artisan key:generate` (ilk kurulum)
3. `php artisan migrate --force`
4. `php artisan config:cache && php artisan route:cache`
5. Queue worker/Horizon ayaga kaldir
6. Scheduler icin cron:
   - `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`

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
