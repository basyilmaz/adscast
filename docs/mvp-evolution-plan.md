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
7. Delivery formunda etiket secimiyle cozumlenen alicilari onizleyip dinamik recipient listesi olarak calistir.
8. Kisi etiketlerini segment ozeti olarak gorunur hale getir ve entity bazli varsayilan alici grubunu daha okunur sun.
9. Contact segmentlerini kaydedilmis alici grubu sablonlarina tasiyip schedule ve hizli teslimde grup secimini ana akisa cevir.
10. Alici grubu katalogu olusturup hesap/kampanya baglamina gore onerilen gruplari detail ekranlarina tasimak.
11. Kisi havuzundaki marka / sirket alanindan akilli alici gruplari uretip katalog ve entity onerilerine tasimak.
12. Quick delivery ve schedule formlarinda onerilen alici gruplarini ana secim akisina tasiyip manuel override alanlarini ikinci plana indirmek.

### Kabul Kriterleri

- Operator musteri kisilerini tek havuzdan yonetebilir.
- Alici preset ve hizli teslim formu kisi havuzunu kullanabilir.
- Bir teslim aksami oldugunda neden oldugu ve ne yapilacagi sistem icinde gorulebilir.
- Schedule ve hizli teslim formlari kisi havuzu etiketlerinden dinamik alici cozebilir.
- Operator hangi entity'nin preset, manuel liste veya segment ile teslim edildigini tek bakista anlayabilir.

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
28. Schedule ve hizli teslim akisini kisi havuzu etiketleriyle dinamik alici cozebilecek hale getirmek
29. Kisi etiketlerini segment ozeti olarak sunup entity bazli varsayilan alici grubu gorunurlugunu guclendirmek
30. Kayitli alici presetlerini segment destekli alici grubu sablonlarina donusturup quick delivery ve schedule formlarinda grup secimini ana akis haline getirmek
31. Alici grubu katalogu ve entity bazli onerilen alici gruplari ile profile yonetimini tek tikla hizlandirmak
32. Kisi havuzundaki sirket/marka alanindan akilli alici gruplari uretip katalog ve entity onerilerine tasimak
33. Quick delivery ve schedule formlarinda onerilen alici gruplarini ana secim akisi yapip manuel alici/etiket ayarlarini ileri seviye override'a tasimak
34. Alici gruplarinin kullanim, teslim basarisi ve entity yayilimini gosteren recipient group analytics katmanini eklemek
35. Onerilen alici grubu ile operator secimi arasindaki sapmayi schedule kararlarinda izleyip alignment analytics paneline tasimak
36. Kayitli alici presetlerini kural yonetilen recipient group template kataloguna tasiyip entity tipi, sirket eslesmesi ve oncelik metadata'si ile suggestion akisini guclendirmek
37. Account ve campaign detail ekranlarina entity-scope recipient group analytics ve alignment ozetini tasiyip rapor sekmesini operasyonel icgoru paneli haline getirmek
38. Onerilen alici grubu uyumu ile gercek teslim basarisi arasindaki farki recipient group correlation analytics olarak reports merkezine eklemek
39. Rule-managed template verisinden entity-specific varsayilan teslim profili onerisi uretip account/campaign detail ekranlarina tasimak
40. Account ve campaign detail ekranlarindaki teslim profili onerilerini tek tikla uygulanabilir hale getirip suggestion durumunu mevcut profil ile senkronlamak
41. Recipient group bazli teslim hata nedenlerini siniflandirip reports merkezi ve entity detail ekranlarina tasimak
42. Onerilen alici grubu secimi ile failure reason dagilimi arasindaki baglantiyi recipient group failure alignment analytics olarak reports merkezine eklemek
43. Failure reason siniflarini provider ve teslim asamasi metadata'si ile genisletip reports merkezinde daha operasyonel hale getirmek
44. Account ve campaign detail ekranlarina failure alignment ozetini tasiyip report sekmesini secim kaynakli teslim risklerini gosterecek sekilde genisletmek
45. Failure reason bazli tek tik duzeltme aksiyonlarini entity detail ekranlarina tasiyip retry uygun run'lar icin bulk retry akisi eklemek
46. Provider ve teslim asamasi bazli retry onerilerini entity detail ekranlarina tasiyip failure resolution aksiyonlarini sadece retry-safe reason'larda acik tutmak
47. Recipient ve contact kaynakli hata tipleri icin detail ekranlarina daha spesifik tek tik duzeltme aksiyonlari ve prefiltered reports yonlendirmeleri eklemek
48. Failure resolution aksiyonlarinin kullanim ve sonuc verisini analytics katmanina geri besleyip reports merkezinde hangi duzeltmenin gercekten calistigini gorunur hale getirmek
49. Failure reason, provider/asama ve fiili duzeltme sonucunu birlestirip reports merkezinde hangi fix'in gercekten ise yaradigini gosteren effectiveness katmani eklemek
50. Failure resolution effectiveness ozetini account ve campaign detail ekranlarinin report sekmesine tasiyip entity scope'unda gorunur hale getirmek
51. Effectiveness, retry policy ve mevcut action inventory verisini birlestirip entity detail ekranlarinda otomatik one cikan duzeltme aksiyonunu secmek ve vurgulamak
52. Entity detail ekranlarinda one cikan duzeltmenin gercekte takip edilip edilmedigini ve featured/override sonuc farkini reports merkezinde analytics olarak geri beslemek
53. Featured failure resolution secimini entity bazli takip ve basari verisiyle adaptif hale getirip detail ekraninda statik kural yerine gozlenen sonuc kalitesine gore guncellemek
54. Reports merkezine featured karar mantigi paneli ekleyip effectiveness ve featured analytics verisini birlestirerek hangi fix'in neden one cikarildigini aciklanabilir hale getirmek
55. Featured karar panelinden ilgili account/campaign detail ekranina derin link verip operatoru analytics'ten dogrudan aksiyona tasimak
56. Featured karar panelinden gelen reason/action odagini detail ekraninda ilgili rapor sekmesi ve hizli duzeltme aksiyonu uzerine tasimak
57. Focus ile acilan detail ekraninda hizli duzeltme kartinin aciklamasini hata nedeni ve aksiyon baglamina gore context-aware hale getirmek
58. Detail ekraninda retry rehberi ve featured fix yuzeyini de ayni focus baglamina hizalayip tek bir operasyonel odak dili olusturmak
59. Ayni focus baglamini delivery profile suggestion kartina da tasiyip detail ekranindaki uc karar yuzeyini tek bir aciklanabilir karar akisinda birlestirmek
60. Detail ekraninin report sekmesinde uc karar yuzeyini tek bir "once ne yapmaliyim" cevap bloğunda birlestiren operasyon karari ozeti katmani eklemek
61. Operasyon karari ozetinden ilgili alt karta sayfa ici derin odak verip operatoru summary'den dogrudan dogru karara indirmek
62. Hash ile acilan veya summary'den inilen karar yuzeyini kisa sureli gorsel vurguya alip operatorun geldigi hedefi aninda fark etmesini saglamak
63. Featured fix, retry rehberi ve profil onerisi yuzeylerine operator takip durumu ekleyip detail report sekmesini sadece onerilerden degil is takibinden de sorumlu hale getirmek
64. Decision surface durumlarini reports merkezine workspace-scope operasyon kuyrugu olarak tasiyip hangi account/campaign'de hangi karar yuzeyinin bekledigini tek ekranda gostermek
65. Reports merkezindeki operasyon kuyruguna bulk filtre, gorunenleri secme ve toplu durum guncelleme ekleyip operatorun birden cok karar yuzeyini tek akista kapatabilmesini saglamak
66. Operasyon kuyruguna operator notu ve erteleme nedeni ekleyip neden bekliyor bilgisini reports merkezinden toplu yonetilebilir hale getirmek
67. Operasyon kuyruguna not/reason bazli filtre ve blok nedeni gruplama ekleyip hangi islerin neden takildigini reports merkezinde ust seviyede gorunur hale getirmek
68. Operasyon kuyrugunda blok nedenlerini onceliklendirip varsayilan siralamayi "once cozulmesi gereken bloklar" mantigina gore kurmak
69. Operasyon kuyrugunda "once cozulmeli" bloklari tek tikla secilebilir hale getirip bulk aksiyon akisina dogrudan baglamak
70. Operasyon kuyrugunda "once cozulmeli" bloklari icin baglama gore onerilen bulk aksiyonu uretip operatoru dogru toplu karara yonlendirmek
