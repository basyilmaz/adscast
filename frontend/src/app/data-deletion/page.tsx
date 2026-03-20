import type { Metadata } from "next";
import { LegalPage } from "@/components/public/legal-page";

export const metadata: Metadata = {
  title: "Veri Silme Talimatlari | AdsCast",
};

export default function DataDeletionPage() {
  return (
    <LegalPage
      eyebrow="AdsCast"
      title="Kullanici Verilerinin Silinmesi"
      summary="AdsCast uzerindeki kullanici veya entegrasyon verilerinizin silinmesini talep etmek icin asagidaki adimlari izleyebilirsiniz. Talepler tenant ve workspace kayitlariyla iliskili olarak degerlendirilir."
      sections={[
        {
          title: "Talep Yontemi",
          paragraphs: [
            "Silme talebi icin admin@castintech.com adresine, ilgili organization/workspace bilgileri ve silinmesini istediginiz veri kapsamini aciklayarak e-posta gonderin.",
            "Talepte bulunan kisinin yetkili oldugunu dogrulamak icin ek kimlik veya hesap dogrulama bilgileri istenebilir.",
          ],
        },
        {
          title: "Silinebilecek Veri Turleri",
          paragraphs: [
            "Kullanici hesabiniz, workspace uyelikleriniz, Meta baglanti kayitlari, audit metadata'sinin ilgili bolumleri ve uygulama ici taslak/veri kayitlari operasyonel olarak degerlendirilir.",
            "Yasal saklama veya guvenlik gerekleri nedeniyle tamamen silinemeyen kayitlar anonimlestirilerek tutulabilir.",
          ],
        },
        {
          title: "Islem Suresi",
          paragraphs: [
            "Silme talepleri dogrulama sonrasinda makul sure icinde isleme alinir. Entegrasyon baglantilari iptal edilir, ilgili tokenlar kullanilamaz hale getirilir ve operasyonel kayitlar gozden gecirilir.",
            "Meta tarafindaki veriler icin ek olarak ilgili Meta hesap yonetim akislari uygulanabilir.",
          ],
        },
        {
          title: "Iletisim",
          paragraphs: [
            "Veri silme veya erisim talepleriniz icin iletisim: admin@castintech.com",
            "Uygulama: AdsCast | Saglayici: Castintech",
          ],
        },
      ]}
    />
  );
}
