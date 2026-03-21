# AdsCast - MVP Gelistirme Is Listesi

Bu plan, AdsCast'i teknik olarak calisan bir MVP'den kullanici tarafinda anlasilir, account bazli izole, operator ve musteri raporlamasina hazir bir urune tasimak icin hazirlandi.

## Faz Durumu

- Tamamlandi: Faz 1 - Dashboard V2 ve Ilk Bakista Anlasilabilirlik
- Tamamlandi: Faz 2 - Reklam Hesabi Merkezli Yonetim
- Tamamlandi: Faz 3 - Campaign Drill-Down
- Tamamlandi: Faz 4 - Alert ve Oneri Deneyimi
- Tamamlandi: Faz 5 - Musteri Raporlama
- Tamamlandi: Faz 6 - Kullanilabilirlik ve Kurumsallasma
- Devam Ediyor: Faz 7 - Rapor Teslim Operasyonlari ve Musteri Iletisim Katmani

## Planlama Ilkeleri

1. Ilk giriste ne oldugu 30 saniyede anlasilmali.
2. Veri her zaman baglamiyla gorulmeli:
   - Workspace
   - Ad Account
   - Campaign
   - Ad Set
   - Ad
3. Her ekranda "Ne oluyor?", "Ne riskli?", "Ne yapmaliyim?" sorulari cevaplanmali.
4. Once additive, dusuk riskli gelistirmeler yapilmali.
5. API sozlesmeleri mumkun oldugunca kirilmayacak sekilde genisletilmeli.

## Faz 1 - Dashboard V2 ve Ilk Bakista Anlasilabilirlik

Amac: Dashboard'a ilk kez giren bir kullanici sistemin ne gosterdigini ve nereyi yonetmesi gerektigini hemen anlayabilsin.

### Is Listesi

1. Dashboard endpoint'ini additive olarak genislet:
   - workspace health
   - account health
   - urgent actions
   - active campaigns
   - gercek trend verisi
2. Dashboard ekranini yeni bilgi mimarisine tasarla:
   - Bugun ne oluyor?
   - Hemen aksiyon gerekenler
   - Reklam hesabi sagligi
   - Aktif kampanyalar
   - son AI onerileri
3. Dashboard KPI kartlarina acik aciklama metinleri ekle.
4. Aktif kampanya satirlarinda durum, uyarilar ve hesap baglamini goster.
5. Dashboard'dan ilgili detay sayfalarina gecis baglantilari ekle.

### Kabul Kriterleri

- Dashboard tek basina operasyonel tablo gorevi gorur.
- Ilk bakista aktif hesap, aktif kampanya ve acik uyarilar anlasilir.
- Placeholder trend yerine gercek veri kullanilir.

## Faz 2 - Reklam Hesabi Merkezli Yonetim

Amac: `Reklam Hesaplari` sayfasi yalnizca liste degil, account bazli operasyon merkezi olsun.

### Is Listesi

1. `Ad Accounts` listesini kart + tablo hibrit yap.
2. Hesap kartlarina su alanlari ekle:
   - aktif kampanya sayisi
   - harcama
   - sonuc
   - acik uyarilar
   - sync durumu
3. `Ad Account Detail` route'u ekle:
   - `/ad-accounts/detail?id=...`
4. Ad account detail ekranina sekmeler ekle:
   - Genel Bakis
   - Kampanyalar
   - Uyarilar
   - Oneriler
   - Raporlar
5. Hesap bazli filtreleme ve breadcrumb akisini tamamla.

### Kabul Kriterleri

- Kullanici bir hesaba tikladiginda sadece o hesaba ait veriyi gorur.
- Hesaba bagli tum kampanyalara asamali olarak ulasilabilir.

## Faz 3 - Campaign Drill-Down

Amac: Kampanya bazinda karar almak kolaylasin.

### Is Listesi

1. Campaign detail ekranini sekmeli yap:
   - Genel Bakis
   - Ad Setler
   - Reklamlar
   - Uyarilar
   - Rapor
2. Kampanya genel bakista su bloklari goster:
   - performans ozeti
   - riskler
   - son degisimler
   - AI yorumu
3. `Ad Set` breakdown ekranini derinlestir:
   - butce
   - optimization goal
   - performans
   - sibling comparison
4. `Ad` detay ve creative gorunumu ekle:
   - primary text
   - headline
   - CTA
   - performans ve fatigue sinyalleri

### Kabul Kriterleri

- Kampanya -> ad set -> ad akisi kullanilabilir olur.
- Her seviyede performans baglami korunur.

## Faz 4 - Alert ve Oneri Deneyimi

Amac: Kurallar ve AI ciktilari yalnizca liste degil, aksiyon motoru gibi calissin.

### Is Listesi

1. Alertleri entity bazli grupla:
   - account
   - campaign
   - ad set
   - ad
2. Alert detayina `neden`, `etki`, `onerilen aksiyon` bolumleri ekle.
3. Recommendations merkezine:
   - operator view
   - client view
   - aksiyon durumu
ekle.
4. Dashboard ve detail ekranlarinda `next best action` paneli ekle.

### Kabul Kriterleri

- Kullanici alerti gordugunde sonraki adimi anlar.
- Oneriler musteriye uygun ve operatora uygun iki dilde sunulabilir.

## Faz 5 - Musteri Raporlama

Amac: Bir kampanya veya hesap icin musteriye verilecek rapor sistem icinde uretilsin.

### Is Listesi

1. `Reports` modulu ekle.
2. Account-level report builder ekle.
3. Campaign-level report builder ekle.
4. Rapor ciktilarina su bloklari ekle:
   - performans ozeti
   - o donemde ne denendi
   - en buyuk risk
   - en buyuk firsat
   - bir sonraki test
5. Export temeli ekle:
   - CSV
   - PDF foundation
6. Rapor gecmisi ve snapshot kaydi ekle.

### Kabul Kriterleri

- Bir account veya campaign icin tarih aralikli rapor alinabilir.
- Rapor hem operator hem musteri kullanimi icin uygun olur.

## Faz 6 - Kullanilabilirlik ve Kurumsallasma

Amac: Urunun gunluk operasyon araci gibi kullanilmasi.

### Is Listesi

1. Global filtre bar:
   - workspace
   - ad account
   - tarih araligi
   - objective
   - status
2. Breadcrumb zorunlulugu:
   - Workspace > Account > Campaign > Ad Set > Ad
3. Empty state, loading state ve error state'leri urun diline uygun hale getir.
4. Kaydedilmis rapor sablonlari ekle.
5. Scheduled report delivery temeli ekle.
6. Shareable client report foundation ekle.

### Kabul Kriterleri

- Urun operator tarafinda gunluk kullanim icin yeterince anlasilir olur.
- Temel musteri raporlama ihtiyaci sistem icinden karsilanir.

## Faz 7 - Rapor Teslim Operasyonlari ve Musteri Iletisim Katmani

Amac: Musteri raporlarini yalnizca uretmek degil, dogru kisilere, dogru ritimde ve operasyonel izlenebilirlikle teslim etmek.

### Is Listesi

1. Musteri kisi havuzu / contact book ekle.
2. Kisi kayitlarina su alanlari ekle:
   - ad
   - e-posta
   - marka / sirket
   - rol
   - etiketler
   - primary isareti
3. Reports ekraninda kisi havuzu CRUD akisini ekle.
4. Hizli teslim ve alici preset formlarinda kisi havuzundan alici ekleme yardimi ekle.
5. Delivery history merkezini zenginlestir:
   - basarili teslim
   - basarisiz teslim
   - hata nedeni
   - retry aksiyonu
6. Schedule kurulumunu kisi havuzu etiketleriyle secilebilir hale getir.

### Kabul Kriterleri

- Operator musteri kisilerini tek havuzdan yonetebilir.
- Alici preset ve hizli teslim formu kisi havuzunu kullanabilir.
- Bir teslim aksami oldugunda neden oldugu ve ne yapilacagi sistem icinde gorulebilir.

## Uygulama Sirasi

En dusuk riskli ve en yuksek etkili ilerleme sirasi:

1. Dashboard V2
2. Ad Account Detail
3. Campaign Tabs + Drill-Down
4. Alert/Oneri deneyimi
5. Client report builder
6. Export ve schedule

## Bu Turda Tamamlanan Is

1. Faz 1 dashboard backlog'unu additive API ve yeni bilgi mimarisi ile tamamlamak
2. Reklam hesaplari ekranini operasyon merkezi haline getirmek
3. Ad account detail ekranini sekmeli drill-down ile eklemek
4. Hesap bazli rapor hazirlik blogunu olusturmak
5. Campaign detail ekranini sekmeli drill-down yapisina tasimak
6. Ad set ve reklam detail ekranlarini eklemek
7. Alert merkezini entity bazli aksiyon paneline donusturmek
8. Recommendation merkezine operator/client view ayrimi eklemek
9. Dashboard, account ve campaign detaylarina ortak next best action paneli eklemek
10. Reports modulu, account/campaign report builder ve snapshot gecmisi eklemek
11. Authenticated CSV export ve browser-print PDF foundation eklemek
12. Faz 6 icin ortak global filtre bar temelini eklemek
13. Kampanya listesine additive account/objective/status filtreleri eklemek
14. Breadcrumb ve state gorunumlerini dashboard, account, campaign ve report akislarinda standardize etmek
15. Kaydedilmis rapor sablonlari veri modelini ve indeks gorunumunu eklemek
16. Scheduled report delivery foundation icin schedule/run kayitlarini eklemek
17. Reports merkezine template olusturma, schedule tanimlama ve manual run aksiyonlarini eklemek
18. Snapshot tabanli shareable client report foundation eklemek
19. Schedule seviyesinde otomatik musteri paylasim linki konfigurasyonunu eklemek
20. Delivery run metadata'sinda olusan share link'i operator paneline tasimak
21. Scheduled delivery icin gercek email kanali ve mailer readiness gorunurlugu eklemek
22. Kampanya veya hesap secilerek tek adimda musteri rapor teslimi kurulumunu eklemek
23. Kayitli alici listeleri ve entity bazli varsayilan teslim profilleri eklemek
24. Kayitli alici listeleri icin duzenle/pasife al/sil akisini ve detail ekranlarinda teslim profili gorunurlugunu eklemek
25. Hesap ve kampanya detail ekranlarina inline varsayilan teslim profili yonetimini eklemek
26. Faz 7'yi resmi backlog olarak acip workspace bazli musteri kisi havuzu / contact book temelini eklemek
27. Delivery history merkezine basarili/basarisiz teslim ozeti, hata gorunurlugu ve failed run retry aksiyonunu eklemek
