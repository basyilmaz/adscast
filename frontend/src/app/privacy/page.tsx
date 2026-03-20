import type { Metadata } from "next";
import { LegalPage } from "@/components/public/legal-page";

export const metadata: Metadata = {
  title: "Gizlilik Politikasi | AdsCast",
};

export default function PrivacyPage() {
  return (
    <LegalPage
      eyebrow="AdsCast"
      title="Gizlilik Politikasi"
      summary="AdsCast, Castintech tarafindan gelistirilen cok kiracili bir reklam operasyon platformudur. Bu sayfa, platform uzerinde hangi verilerin nasil toplandigini, nasil korundugunu ve hangi durumlarda silindigini aciklar."
      sections={[
        {
          title: "Toplanan Veriler",
          paragraphs: [
            "AdsCast; kullanici hesabi, workspace uyeligi, audit log kayitlari, Meta baglanti metadata'si, performans metrikleri, taslaklar ve approval kayitlari gibi operasyonel verileri toplar.",
            "Meta entegrasyonu aktif oldugunda access token benzeri hassas degerler sifreli olarak saklanir. Tokenlar plaintext olarak loglanmaz ve raw payload kayitlarinda maskeleme uygulanir.",
          ],
        },
        {
          title: "Verilerin Kullanimi",
          paragraphs: [
            "Toplanan veriler; tenant izolasyonu, reklam raporlamasi, uyarilar, AI destekli ozetler, approval akislari ve audit izlenebilirligi icin kullanilir.",
            "AdsCast, kullanici verilerini ucuncu taraflara satis amacli aktarmaz. Meta API uzerinden alinmis veriler yalnizca bagli workspace kapsaminda islenir.",
          ],
        },
        {
          title: "Saklama ve Guvenlik",
          paragraphs: [
            "Hassas sirlar encrypted-at-rest mantigi ile saklanir. Uygulama workspace bazli erisim kontrolleri ve audit log mekanizmalari ile korunur.",
            "Raw API payload kayitlari operasyonel denetim ve hata ayiklama amaciyla sinirli sure boyunca tutulur. Bu sure varsayilan olarak 90 gundur.",
          ],
        },
        {
          title: "Haklariniz ve Iletisim",
          paragraphs: [
            "Veri duzeltme, silme veya erisim talepleriniz icin veri silme talimatlari sayfasini kullanabilir ya da AdsCast operatoru ile iletisime gecebilirsiniz.",
            "Iletisim: admin@castintech.com",
          ],
        },
      ]}
    />
  );
}
