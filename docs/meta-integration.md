# AdsCast - Meta Entegrasyon Siniri (MVP)

Bu dokuman, AdsCast icin Meta entegrasyonunun teknik sinirlarini ve MVP kapsamini tanimlar. UI ve domain kodu dogrudan Meta Graph response semasina baglanmaz; adapter katmani uzerinden normalize veri elde edilir.

## 1. Auth Flow Varsayimlari

1. OAuth tabanli Meta login akisindan short-lived token alinabilir.
2. Short-lived token, backend tarafinda secure exchange ile long-lived tokena cevrilir.
3. Workspace ile iliskili tekil `meta_connections` kaydi tutulur.
4. Cagri yetkisi bu baglanti kaydi uzerinden saglanir; UI token bilmez.
5. Mevcut implementasyonda redirect frontend callback sayfasina gelir; frontend `code/state` bilgisini backend exchange endpoint'ine teslim eder.

## 2. Token Saklama Stratejisi

1. `access_token` ve varsa `refresh_token` sifreli kolonlarda saklanir (`*_encrypted`).
2. Encryption Laravel `Crypt` servisi uzerinden application key ile yapilir.
3. Tokenlar loglara plaintext olarak yazilmaz.
4. Token degisimleri audit loga "connection refreshed" olayi olarak metadata ile yazilir.

## 3. Erişim Seviyesi Varsayimlari

1. MVP icin en az read odakli izinler: ad account, campaign/ad set/ad listesi, insights.
2. Publish islemleri approval sonrasinda ayrica write scope gerektirir.
3. Workspace ve organization seviyesinde hangi hesaplara erisildigi baglanti metadata'sinda tutulur.

## 4. Business Verification Bagimliligi

1. Bazi endpointler (ozellikle olcekli publish ve ileri seviye API erisimleri) business verification gerektirebilir.
2. MVP bu bagimliligi "soft dependency" olarak ele alir:
   - verification yoksa read/sync akisi calisir
   - publish tarafinda kismi kisitlar metadata olarak raporlanir

## 5. Rate Limit Yonetimi

1. Adapter seviyesi response header bilgilerini (`x-app-usage`, `x-business-use-case-usage`) parse eder.
2. Sync isleri exponential backoff + jitter ile retry edilir.
3. `sync_runs` tablosunda rate-limit nedeniyle ertelenen kosular isaretlenir.
4. Toplu full-sync yerine artimli sync tercih edilir.

## 6. Sync Cadence Stratejisi

1. Asset sync (campaign/ad set/ad/creative): saatlik veya manuel tetik.
2. Insight daily sync: gunde en az 1 kez + son 7 gunluk duzeltme penceresi.
3. Backfill: tarih araligi bazli ayri job serisi.
4. Stale connection check: periyodik cron ile baglanti saglik kontrolu.
5. Zamanlanmis calisma komutu `php artisan adscast:run-meta-automation` ile asset sync, insight sync, rules evaluation ve recommendation generation tek zincirde yonetilir.

## 7. Raw Payload Saklama Stratejisi

1. Debug ve denetlenebilirlik icin secili endpoint response'lari `raw_api_payloads` tablosuna yazilir.
2. Saklama suresi MVP icin varsayilan 90 gun.
3. PII/secret alanlar maskeleme kuralindan gecirilir.
4. Payload hash ile duplike yazim azaltilir.

## 8. Adapter Versionlama Stratejisi

1. `MetaApiAdapter` interface sabit kalir.
2. Uygulama tarafinda versiyon bazli adapter implementasyonlari bulunur:
   - `MetaGraphV20Adapter`
   - `MetaGraphV21Adapter` (gelecek)
3. `meta_connections.api_version` adapter secimini belirler.
4. Yeni versiyon gecisinde mapping katmani geri uyumluluk saglar.

## 9. MVP vs Sonraki Fazlar

MVP kapsaminda:

- Connection kaydi acma/yenileme/iptal altyapisi
- Workspace bazli connector preflight/status endpointi
- Manuel access token ile baglanti kaydi ve canli token dogrulama
- Ad account/page/pixel listeleme
- Campaign/ad set/ad/creative sync
- Daily insights sync
- Raw payload saklama
- Approval-gated publish scaffold

Sonraki faz:

- Lead webhook tam akisi
- Gelismis breakdown setleri
- Offline conversion upload
- Otomatik budget reallocation aksiyonlari
- Multi-provider ad connector (Google/TikTok)

## 10. Guvenlik ve Uyumluluk

1. Tokenlar encrypted-at-rest.
2. Workspace isolation middleware zorunlu.
3. Audit ve AI generation kayitlari immutable operasyonel iz birakir.
4. Publish adiminda approval bypass edilmez.

## 11. Uygulama Notlari (Mevcut Implementasyon)

1. Backend `META_MODE=live|stub` ile calisir.
2. Production icin varsayilan mod `live`, test/local icin varsayilan mod `stub`.
3. `GET /api/v1/meta/connector-status` endpoint'i workspace baglaminda app-level Meta hazirligini raporlar.
4. `POST /api/v1/meta/connections` canli modda `me` ve `me/permissions` cagrilari ile tokeni kayit oncesi dogrular.
5. Asset sync canli modda normalize edilmis `meta_businesses`, `meta_ad_accounts`, `meta_pages`, `meta_pixels`, `campaigns`, `ad_sets`, `ads`, `creatives` kayitlarini uretir.
6. `raw_api_payloads` yaziminda token benzeri alanlar maskeleme kuralindan gecer.
7. `GET /api/v1/meta/oauth/start` state uretir ve auth URL doner.
8. `POST /api/v1/meta/oauth/exchange` code -> short-lived token -> long-lived token zincirini calistirir ve `meta_connections` kaydini gunceller.
9. Scheduler acik oldugunda `routes/console.php` saatlik `adscast:run-meta-automation` ve ayri stale-check gorevini planlar.
