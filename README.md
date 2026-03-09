# AdsCast

AdsCast, ajanslar ve performans ekipleri icin cok kiracili (multi-tenant) bir Meta reklam operasyon sistemidir.

Bu repo, iki uygulamali bir SaaS yapisi olarak tasarlanmistir:

- `backend`: Laravel 11 API (PHP 8.3+ hedefi, lokalde 8.2 ile gelistirme uyumlulugu)
- `frontend`: Next.js (TypeScript + Tailwind + shadcn/ui tabanli UI iskeleti)

## Proje Dizini

- `backend/` Laravel API, Domain modulleri, queue job'lar, migration'lar, testler
- `frontend/` Next.js uygulamasi, SaaS panel sayfalari ve bilesenler
- `docs/` urun, mimari, veri modeli, Meta entegrasyonu, AI motoru, deployment, roadmap
- `infra/` altyapi notlari ve deploy yardimci dosyalari icin ayri alan
- `scripts/` kalite kapisi ve gelistirici otomasyon scriptleri

## Hizli Baslangic

### 1. Backend

```powershell
cd backend
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### 2. Frontend

```powershell
cd frontend
copy .env.example .env.local
npm install
npm run dev
```

## Zorunlu Kalite Kapisi

Bu repoda kod degisikligi sonrasinda asagidaki script calistirilmalidir:

```powershell
./scripts/run-quality-gate.ps1
```

Script; backend syntax/test kontrolleri, API contract temel kontrolleri ve frontend lint/build kontrollerini kosar.

## Dokumantasyon

- `docs/product-overview.md`
- `docs/architecture.md`
- `docs/data-model.md`
- `docs/meta-integration.md`
- `docs/ai-recommendation-engine.md`
- `docs/deployment.md`
- `docs/roadmap.md`
- `docs/api-routes.md`
- `docs/implementation-plan.md`
