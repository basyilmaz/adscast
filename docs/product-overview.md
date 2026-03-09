# AdsCast - Urun Genel Bakis

## Vizyon

AdsCast, ajanslarin ve performans ekiplerinin birden fazla musteri hesabini tek bir operasyon katmaninda yonetmesini saglayan cok kiracili bir Meta Ads isletim sistemidir.

## MVP Amaclari

1. Birden fazla organization/workspace altinda Meta hesap baglantisi
2. Campaign, ad set, ad, creative ve gunluk insight verisi senkronizasyonu
3. Hesap/kampanya performansinin okunabilir dashboard'larda sunulmasi
4. Deterministic rules engine ile risk/firsat sinyali uretimi
5. AI destekli icgoruler ve aksiyon onerileri
6. Yonlendirmeli form ile campaign draft uretilmesi
7. Meta'ya publish oncesi zorunlu manuel approval
8. Kritik islemler icin denetlenebilir audit log kaydi

## Tenant Hiyerarsisi

- Platform
- Organization
- Workspace
- User
- Role

## Rol Seti

- Super Admin
- Agency Admin
- Account Manager
- Analyst
- Client Viewer

## MVP Disi

- Google Ads entegrasyonu
- Tam faturalama/abonelik motoru
- White-label theme motoru
- Gelismis CRM
- Omnichannel full reporting

## Basari Kriterleri

- Workspace bazli veri izolasyonu testlerle dogrulanmis olmali
- Senkronizasyon idempotent ve izlenebilir olmali
- AI ciktilari prompt baglami ve zaman damgasi ile saklanmali
- Publish akisinda approval atlanamamalidir
