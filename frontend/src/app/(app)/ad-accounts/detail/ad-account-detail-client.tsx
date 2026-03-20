"use client";

import Link from "next/link";
import dynamic from "next/dynamic";
import { useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { AdAccountDetailResponse } from "@/lib/types";

const SpendResultChart = dynamic(
  () => import("@/components/charts/spend-result-chart").then((mod) => mod.SpendResultChart),
  {
    ssr: false,
    loading: () => <div className="h-[280px] w-full rounded-md bg-[var(--surface-2)]" />,
  },
);

const TABS = [
  { id: "overview", label: "Genel Bakis" },
  { id: "campaigns", label: "Kampanyalar" },
  { id: "alerts", label: "Uyarilar" },
  { id: "recommendations", label: "Oneriler" },
  { id: "reports", label: "Raporlar" },
] as const;

type TabId = (typeof TABS)[number]["id"];

function formatCurrency(value: number) {
  return `$${value.toFixed(2)}`;
}

function formatNumber(value: number) {
  return value.toFixed(value % 1 === 0 ? 0 : 2);
}

function variantFor(value: string) {
  if (value === "critical" || value === "high" || value === "lagging") return "danger" as const;
  if (value === "warning" || value === "medium" || value === "stale") return "warning" as const;
  if (value === "healthy" || value === "active" || value === "fresh") return "success" as const;

  return "neutral" as const;
}

export function AdAccountDetailClient() {
  const searchParams = useSearchParams();
  const adAccountId = searchParams.get("id");
  const hasAdAccountId = Boolean(adAccountId);
  const [activeTab, setActiveTab] = useState<TabId>("overview");

  const {
    data,
    error,
    isLoading,
    isRefreshing,
    reload,
  } = useApiQuery<AdAccountDetailResponse, AdAccountDetailResponse["data"]>(
    `/meta/ad-accounts/${adAccountId ?? ""}`,
    {
      enabled: hasAdAccountId,
      requestOptions: {
        requireWorkspace: true,
      },
      ttlMs: QUERY_TTLS.adAccountDetail,
      select: (response) => response.data,
    },
  );

  const metricCards = useMemo(() => {
    if (!data) return [];

    return [
      {
        label: "Toplam Harcama",
        value: formatCurrency(data.summary.spend),
        note: `${data.range.start_date} - ${data.range.end_date}`,
      },
      {
        label: "Toplam Sonuc",
        value: formatNumber(data.summary.results),
        note: "Sadece bu hesaba bagli kampanyalar.",
      },
      {
        label: "CPA / CPL",
        value: data.summary.cpa_cpl ? formatCurrency(data.summary.cpa_cpl) : "-",
        note: "Bir sonuc icin ortalama maliyet.",
      },
      {
        label: "Aktif Varlik",
        value: `${data.summary.active_campaigns} / ${data.summary.active_ad_sets} / ${data.summary.active_ads}`,
        note: "Kampanya / Ad Set / Reklam",
      },
    ];
  }, [data]);

  if (!hasAdAccountId) {
    return <p className="text-sm text-[var(--danger)]">Reklam hesabi id eksik.</p>;
  }

  if (error) {
    return <p className="text-sm text-[var(--danger)]">{error}</p>;
  }

  if (isLoading && !data) {
    return <p className="text-sm muted-text">Yukleniyor...</p>;
  }

  if (!data) {
    return <p className="text-sm text-[var(--danger)]">Reklam hesabi bulunamadi.</p>;
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="text-sm muted-text">
            <Link href="/ad-accounts" className="hover:underline">
              Reklam Hesaplari
            </Link>{" "}
            / {data.ad_account.name}
          </p>
          <h2 className="text-2xl font-bold">{data.ad_account.name}</h2>
          <p className="text-sm muted-text">
            {data.ad_account.account_id}
            {data.ad_account.currency ? ` / ${data.ad_account.currency}` : ""}
            {data.ad_account.timezone_name ? ` / ${data.ad_account.timezone_name}` : ""}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge label={data.ad_account.status} variant={variantFor(data.ad_account.status)} />
          <Badge label={data.health.status} variant={variantFor(data.health.status)} />
          <Badge label={data.health.sync_status} variant={variantFor(data.health.sync_status)} />
          <Button variant="secondary" onClick={() => void reload()}>
            {isRefreshing ? "Yenileniyor..." : "Yenile"}
          </Button>
        </div>
      </div>

      <Card>
        <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
          <div>
            <CardTitle>Bu Hesapta Ne Oluyor?</CardTitle>
            <p className="mt-3 text-lg font-semibold leading-7">{data.health.summary}</p>
            <div className="mt-4 flex flex-wrap gap-2">
              <span className="rounded-full bg-[var(--surface-2)] px-3 py-2 text-sm font-medium">
                {data.summary.active_campaigns} aktif kampanya
              </span>
              <span className="rounded-full bg-[var(--surface-2)] px-3 py-2 text-sm font-medium">
                {data.summary.open_alerts} acik uyari
              </span>
              <span className="rounded-full bg-[var(--surface-2)] px-3 py-2 text-sm font-medium">
                {data.summary.open_recommendations} hesap ici oneri
              </span>
            </div>
          </div>

          <div className="rounded-xl border border-[var(--border)] bg-[var(--surface-2)] p-4">
            <p className="text-xs font-semibold uppercase tracking-wide muted-text">Operasyon Durumu</p>
            <div className="mt-3 space-y-3 text-sm">
              <div>
                <p className="muted-text">Son Senkron</p>
                <p>{data.ad_account.last_synced_at ?? "Bilinmiyor"}</p>
              </div>
              <div>
                <p className="muted-text">Acik Uyari</p>
                <p className="text-xl font-bold">{data.summary.open_alerts}</p>
              </div>
              <div>
                <p className="muted-text">Tarih Araligi</p>
                <p>
                  {data.range.start_date} / {data.range.end_date}
                </p>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        {metricCards.map((card) => (
          <Card key={card.label}>
            <CardTitle>{card.label}</CardTitle>
            <CardValue>{card.value}</CardValue>
            <p className="mt-2 text-sm muted-text">{card.note}</p>
          </Card>
        ))}
      </section>

      <Card>
        <div className="flex flex-wrap gap-2">
          {TABS.map((tab) => (
            <Button
              key={tab.id}
              type="button"
              variant={activeTab === tab.id ? "primary" : "secondary"}
              size="sm"
              onClick={() => setActiveTab(tab.id)}
            >
              {tab.label}
            </Button>
          ))}
        </div>
      </Card>

      {activeTab === "overview" ? (
        <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.4fr_1fr]">
          <Card>
            <CardTitle>Harcama / Sonuc Trendi</CardTitle>
            <div className="mt-3">
              <SpendResultChart data={data.trend} />
            </div>
          </Card>

          <Card>
            <CardTitle>Rapor Ozet Taslagi</CardTitle>
            <div className="mt-3 space-y-3 text-sm">
              <p className="font-semibold">{data.report_preview.headline}</p>
              <div>
                <p className="font-semibold">Musteri Dili</p>
                <p className="muted-text">{data.report_preview.client_summary}</p>
              </div>
              <div>
                <p className="font-semibold">Operasyon Notu</p>
                <p className="muted-text">{data.report_preview.operator_summary}</p>
              </div>
              <div>
                <p className="font-semibold">Sonraki Adim</p>
                <p className="muted-text">{data.report_preview.next_step}</p>
              </div>
            </div>
          </Card>
        </section>
      ) : null}

      {activeTab === "campaigns" ? (
        <Card>
          <CardTitle>Bu Hesaba Bagli Kampanyalar</CardTitle>
          <div className="mt-3 overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] text-left">
                  <th className="px-3 py-2">Kampanya</th>
                  <th className="px-3 py-2">Durum</th>
                  <th className="px-3 py-2">Harcama</th>
                  <th className="px-3 py-2">Sonuc</th>
                  <th className="px-3 py-2">CTR / CPM</th>
                  <th className="px-3 py-2">Uyari / Oneri</th>
                </tr>
              </thead>
              <tbody>
                {data.campaigns.map((campaign) => (
                  <tr key={campaign.id} className="border-b border-[var(--border)] align-top">
                    <td className="px-3 py-3">
                      <Link
                        href={`/campaigns/detail?id=${encodeURIComponent(campaign.id)}`}
                        className="font-semibold text-[var(--accent)] hover:underline"
                      >
                        {campaign.name}
                      </Link>
                      <p className="mt-1 text-xs muted-text">{campaign.objective ?? "-"}</p>
                    </td>
                    <td className="px-3 py-3">
                      <div className="flex flex-col gap-2">
                        <Badge label={campaign.status} variant={variantFor(campaign.status)} />
                        <Badge label={campaign.health_status} variant={variantFor(campaign.health_status)} />
                      </div>
                      <p className="mt-2 text-xs muted-text">{campaign.health_summary}</p>
                    </td>
                    <td className="px-3 py-3">{formatCurrency(campaign.spend)}</td>
                    <td className="px-3 py-3">{formatNumber(campaign.results)}</td>
                    <td className="px-3 py-3">
                      <p className="font-semibold">CTR {campaign.ctr.toFixed(2)}%</p>
                      <p className="text-xs muted-text">CPM {formatCurrency(campaign.cpm)}</p>
                    </td>
                    <td className="px-3 py-3">
                      <p className="font-semibold">{campaign.open_alerts} uyari</p>
                      <p className="text-xs muted-text">{campaign.open_recommendations} oneri</p>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data.campaigns.length === 0 ? <p className="mt-3 text-sm muted-text">Bu hesaba bagli kampanya bulunmuyor.</p> : null}
        </Card>
      ) : null}

      {activeTab === "alerts" ? (
        <Card>
          <CardTitle>Bu Hesabi Etkileyen Uyarilar</CardTitle>
          <div className="mt-3 space-y-3">
            {data.alerts.map((alert) => (
              <div key={alert.id} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label={alert.severity} variant={variantFor(alert.severity)} />
                  <span className="text-xs muted-text">{alert.date_detected ?? "-"}</span>
                </div>
                <p className="mt-2 font-semibold">{alert.summary}</p>
                <p className="mt-1 text-sm muted-text">{alert.campaign_name ?? "Hesap seviyesi"}</p>
                <p className="mt-2 text-sm">{alert.recommended_action ?? "-"}</p>
              </div>
            ))}
            {data.alerts.length === 0 ? <p className="text-sm muted-text">Bu hesap icin acik uyari bulunmuyor.</p> : null}
          </div>
        </Card>
      ) : null}

      {activeTab === "recommendations" ? (
        <Card>
          <CardTitle>Bu Hesaba Bagli Oneriler</CardTitle>
          <div className="mt-3 space-y-3">
            {data.recommendations.map((recommendation) => (
              <div key={recommendation.id} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label={recommendation.priority} variant={variantFor(recommendation.priority)} />
                  <span className="text-xs muted-text">{recommendation.generated_at ?? "-"}</span>
                </div>
                <p className="mt-2 font-semibold">{recommendation.summary}</p>
                <p className="mt-1 text-sm muted-text">{recommendation.campaign_name ?? "Hesap seviyesi"}</p>
                <p className="mt-2 text-sm">{recommendation.details ?? "-"}</p>
              </div>
            ))}
            {data.recommendations.length === 0 ? (
              <p className="text-sm muted-text">Bu hesap icin kampanya bazli kayitli oneri bulunmuyor.</p>
            ) : null}
          </div>
        </Card>
      ) : null}

      {activeTab === "reports" ? (
        <Card>
          <CardTitle>Musteri Raporu Hazirlik Bloku</CardTitle>
          <div className="mt-3 grid gap-4 xl:grid-cols-2">
            <div className="rounded-lg border border-[var(--border)] p-4">
              <p className="text-sm font-semibold">Musteriye Soylenecek Ozet</p>
              <p className="mt-2 text-sm">{data.report_preview.client_summary}</p>
            </div>
            <div className="rounded-lg border border-[var(--border)] p-4">
              <p className="text-sm font-semibold">Ic Operasyon Ozeti</p>
              <p className="mt-2 text-sm">{data.report_preview.operator_summary}</p>
            </div>
            <div className="rounded-lg border border-[var(--border)] p-4 xl:col-span-2">
              <p className="text-sm font-semibold">Sonraki Adim</p>
              <p className="mt-2 text-sm">{data.report_preview.next_step}</p>
              <p className="mt-3 text-xs muted-text">
                Bu blok account bazli musteri raporlamanin temeli olarak tasarlandi. Ayrintili export ve PDF olusturma bir sonraki fazda eklenecek.
              </p>
            </div>
          </div>
        </Card>
      ) : null}
    </div>
  );
}
