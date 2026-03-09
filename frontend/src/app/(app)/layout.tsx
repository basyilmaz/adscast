"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { AppShell } from "@/components/layout/app-shell";
import { getToken } from "@/lib/session";

const titleMap: Array<{ startsWith: string; title: string; subtitle: string }> = [
  {
    startsWith: "/dashboard",
    title: "Dashboard Genel Bakis",
    subtitle: "Harcama, sonuc, risk ve firsat sinyallerini tek bakista izleyin.",
  },
  {
    startsWith: "/workspaces",
    title: "Workspace Switcher",
    subtitle: "Calisma baglaminizi secin ve tenant izolasyonunu koruyun.",
  },
  {
    startsWith: "/ad-accounts",
    title: "Reklam Hesaplari",
    subtitle: "Workspace altindaki Meta hesaplari ve baglanti durumu.",
  },
  {
    startsWith: "/campaigns",
    title: "Kampanya Yonetimi",
    subtitle: "Kampanya listesi, detay metrikleri ve optimizasyon sinyalleri.",
  },
  {
    startsWith: "/alerts",
    title: "Uyarilar Merkezi",
    subtitle: "Deterministic rules engine ile uretile n risk/firsat sinyalleri.",
  },
  {
    startsWith: "/recommendations",
    title: "Oneriler Merkezi",
    subtitle: "Rules + AI pipeline ciktilarini operasyonel aksiyona cevirin.",
  },
  {
    startsWith: "/draft-builder",
    title: "Draft Builder",
    subtitle: "Yonlendirmeli form ile kampanya taslagi olusturun.",
  },
  {
    startsWith: "/drafts",
    title: "Draft Inceleme",
    subtitle: "Taslak detaylari, onay durumu ve publish hazirligi.",
  },
  {
    startsWith: "/approvals",
    title: "Onay Kuyrugu",
    subtitle: "Publish oncesi zorunlu review adimlarini yonetin.",
  },
  {
    startsWith: "/audit-logs",
    title: "Audit Loglari",
    subtitle: "Tum kritik aksiyonlarin actor ve metadata izleri.",
  },
  {
    startsWith: "/settings",
    title: "Ayarlar",
    subtitle: "Meta baglantisi, API ayarlari ve workspace konfigurasyonu.",
  },
];

function resolveTitle(pathname: string) {
  return (
    titleMap.find((item) => pathname.startsWith(item.startsWith)) ?? {
      title: "AdsCast",
      subtitle: "Operasyon paneli",
    }
  );
}

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    const token = getToken();
    if (!token) {
      router.replace("/login");
    }
  }, [router]);

  const titleData = resolveTitle(pathname);

  return (
    <AppShell title={titleData.title} subtitle={titleData.subtitle}>
      {children}
    </AppShell>
  );
}
