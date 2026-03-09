# AdsCast - Hostinger Cloud Startup + Cloudflare Deployment

Bu rehber `adscast.castintech.com` alan adini Cloudflare DNS ile yonetilen bir Hostinger Cloud Startup sunucusuna yayinlamak icindir.

## 1. On Kosullar

1. Hostinger VPS/Cloud sunucuya SSH erisimi
2. Domain DNS yonetimi Cloudflare'da aktif
3. Sunucuda Ubuntu 22.04/24.04 benzeri bir dagitim

## 2. Cloudflare DNS

Cloudflare DNS tarafinda su kaydi olusturun:

- Type: `A`
- Name: `adscast`
- Content: `SUNUCU_IP`
- Proxy status: `Proxied` (turuncu bulut)

Ilk kurulumda SSL/TLS modu icin:

- Hemen yayin almak icin: `Flexible`
- Uretim guvenlik seviyesi icin hedef: `Full (strict)` + Origin sertifika

## 3. Sunucu Hazirligi

Sunucuya baglandiktan sonra:

```bash
sudo bash infra/hostinger/bootstrap-ubuntu.sh
```

Bu komut Docker Engine + Docker Compose plugin kurar.

## 4. Proje Kopyalama

```bash
sudo mkdir -p /opt
sudo chown -R $USER:$USER /opt
cd /opt
git clone https://github.com/basyilmaz/adscast.git
cd adscast
cp .env.production.example .env.production
```

## 5. Production Degiskenleri

`.env.production` dosyasinda en az su alanlari doldurun:

- `APP_KEY` (zorunlu)
- `POSTGRES_PASSWORD`
- `REDIS_PASSWORD`
- `META_APP_ID`
- `META_APP_SECRET`
- `META_REDIRECT_URI`
- `NEXT_PUBLIC_API_BASE_URL`

`APP_KEY` icin ornek uretim:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

## 6. Deployment

```bash
chmod +x scripts/deploy-prod.sh
./scripts/deploy-prod.sh
```

Bu komut:

1. image build eder
2. container'lari ayaga kaldirir
3. migrationlari backend container icinde calistirir

## 7. Servis Kontrolu

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml ps
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f proxy
```

Uygulama kontrol adresleri:

- `http://SUNUCU_IP/nginx-health`
- `http://SUNUCU_IP/up`

Cloudflare DNS propagasyonu sonrasi:

- `https://adscast.castintech.com`

## 8. Update / Yeni Sürüm

```bash
cd /opt/adscast
git pull origin main
./scripts/deploy-prod.sh
```

## 9. Notlar

1. Horizon bu deploymentta ayri container olarak calisir (`adscast-horizon`).
2. Scheduler ayri container olarak `php artisan schedule:work` calistirir.
3. Production ortaminda yalnizca rol/izin taban seed calistirilir; demo veri seed'i yoktur.
