# AdsCast - Hostinger Shared/LiteSpeed Deployment

Bu dokuman, Docker veya root erisimi olmayan Hostinger Cloud Startup benzeri paylasimli ortamlarda AdsCast backend yayinlamak icindir.

## Ozet

- Sunucu tipi: LiteSpeed + PHP (SSH kullanici seviyesi)
- Docker: yok
- sudo/root: yok
- `crontab` komutu: cogu pakette yok (panel uzerinden yonetim)
- Sonuc: Laravel backend yayinlanir, queue `sync` modda calisir

## Dosya Yerlesimi

- Repo: `~/domains/adscast.castintech.com/adscast`
- Backend: `~/domains/adscast.castintech.com/adscast/backend`
- Web root: `~/domains/adscast.castintech.com/public_html`

`public_html/index.php` Laravel backend'e baglanacak sekilde ayarlanir:
- `../adscast/backend/vendor/autoload.php`
- `../adscast/backend/bootstrap/app.php`

## Otomatik Deploy Scripti

Lokal makineden calistirin:

```powershell
./scripts/deploy-hostinger-shared.ps1 `
  -ServerHost 76.13.34.119 `
  -Port 65002 `
  -User u473759453 `
  -SshKey "$HOME/.ssh/adscast_deploy" `
  -Domain adscast.castintech.com `
  -AppUrl https://adscast.castintech.com
```

Script ne yapar:

1. Repo clone/pull (`origin/main`)
2. `composer install --no-dev`
3. `.env` yoksa production varsayilanlari ile olusturur
4. `php artisan migrate --force`
5. `optimize:clear` + `optimize`
6. `public_html` yedegi alip Laravel front controller baglar

## Ayrik MySQL Tavsiyesi

Shared ortamda production icin `DB_PREFIX` ile ortak veritabani kullanmak yerine hPanel uzerinden ayrik bir MySQL veritabani acilmasi onerilir.

Meta connector icin pratik ayar:

- `META_MODE=live`
- `META_GRAPH_BASE_URL=https://graph.facebook.com`
- `META_RAW_PAYLOAD_RETENTION_DAYS=90`
- Manuel access token ile baglaniyorsaniz `META_APP_ID` / `META_APP_SECRET` beklenmeden sync akisi calisabilir

Ilk kurulumdan sonra bootstrap komutu ile tenant olusturulabilir:

```bash
php artisan adscast:bootstrap-workspace \
  --admin-email=admin@castintech.com \
  --admin-password='<strong-password>' \
  --organization-name='Castintech' \
  --organization-slug=castintech \
  --workspace-name='Castintech Main' \
  --workspace-slug=castintech-main \
  --currency=TRY \
  --force
```

## Tum Fonksiyonlar (Frontend + Backend) Canli Deploy

Panel UI dahil tum akisi tek domaine almak icin:

```powershell
./scripts/deploy-hostinger-full.ps1 `
  -ServerHost 76.13.34.119 `
  -Port 65002 `
  -User u473759453 `
  -SshKey "$HOME/.ssh/adscast_deploy" `
  -Domain adscast.castintech.com `
  -AppUrl https://adscast.castintech.com
```

Bu script:

1. Backend deploy eder (migration + optimize)
2. Frontend'i static export olarak build eder
3. Export ciktisini `public_html` altina yayinlar
4. `/api/*` ve `/up` yollarini Laravel backend'e bridge eder

## Dogrulama

DNS hazir olmadan lokalden test:

```bash
curl --resolve adscast.castintech.com:80:76.13.34.119 http://adscast.castintech.com/up
```

Beklenen:
- HTTP `200`
- "Application up" icerigi

API login smoke testi:

```bash
curl --resolve adscast.castintech.com:80:76.13.34.119 \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@castintech.com","password":"<admin-password>"}' \
  http://adscast.castintech.com/api/v1/auth/login
```

## Cloudflare DNS

Cloudflare DNS'te su kayit olmali:

- Type: `A`
- Name: `adscast`
- Content: `76.13.34.119`
- Proxy status: `DNS only` veya `Proxied` (sertifika hazirligina gore)

Not: DNS kaydi yoksa `adscast.castintech.com` NXDOMAIN doner.

## SSL

- Hostinger tarafinda domain SSL aktif olmali
- Cloudflare SSL/TLS modu:
  - gecici: `Flexible`
  - onerilen: `Full (strict)` (Origin cert ile)

## Isletim Notlari

- Bu modda Horizon/Redis queue worker calistirilmadi.
- Queue isleri `QUEUE_CONNECTION=sync` ile request icinde calisir.
- Cron gerekiyorsa Hostinger panel cron ekranindan girilmelidir.

## Sonraki Adim

Uretim olceginde onerilen mimari:

1. Backend: VPS + Docker (PostgreSQL + Redis + Horizon + scheduler)
2. Frontend: Vercel (Next.js)
3. Domain:
   - `adscast.castintech.com` -> Frontend
   - `api.adscast.castintech.com` -> Backend API
