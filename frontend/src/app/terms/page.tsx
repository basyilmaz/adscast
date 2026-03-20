import type { Metadata } from "next";
import { LegalPage } from "@/components/public/legal-page";

export const metadata: Metadata = {
  title: "Hizmet Sartlari | AdsCast",
};

export default function TermsPage() {
  return (
    <LegalPage
      eyebrow="AdsCast"
      title="Hizmet Sartlari"
      summary="Bu sartlar, AdsCast platformunu kullanan tum operatorler, ajans ekipleri ve musteri goruntuleyicileri icin gecerlidir. Platformu kullanarak bu kosullari kabul etmis olursunuz."
      sections={[
        {
          title: "Hizmet Kapsami",
          paragraphs: [
            "AdsCast; Meta reklam varliklarini baglama, senkronize etme, raporlama, deterministic alert uretme, AI destekli oneriler ve approval tabanli draft yonetimi saglar.",
            "Platform, approval olmadan otomatik reklam yayinlamaz. Publish akislari kasitli olarak manuel onay kapisi arkasinda calisir.",
          ],
        },
        {
          title: "Kullanici Yukumlulukleri",
          paragraphs: [
            "Kullanicilar, bagladiklari reklam hesaplari ve Meta varliklari uzerinde gerekli yetkilere sahip olduklarini beyan eder.",
            "Hatali, yetkisiz veya hukuka aykiri reklam iceriklerinden ve Meta tarafindaki hesap ihlallerinden ilgili operator sorumludur.",
          ],
        },
        {
          title: "Kisitlar ve Sorumluluk",
          paragraphs: [
            "AdsCast, ucuncu taraf servis kesintileri, Meta API limitleri veya reklam platformu tarafli policy degisikliklerinden dogan dolayli zararlardan sorumlu tutulamaz.",
            "MVP kapsamindaki ozellikler gelistirme surecinde olabilir; bazi publish ve ileri seviye breakdown ozellikleri sonraki fazlarda tamamlanir.",
          ],
        },
        {
          title: "Iletisim ve Guncellemeler",
          paragraphs: [
            "Bu sartlar operasyonel veya yasal gerekliliklere gore guncellenebilir. Guncel surum her zaman bu sayfada yayinlanir.",
            "Iletisim: admin@castintech.com",
          ],
        },
      ]}
    />
  );
}
