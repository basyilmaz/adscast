"use client";

import Link from "next/link";
import dynamic from "next/dynamic";
import { useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { PageBreadcrumbs } from "@/components/layout/page-breadcrumbs";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { PageErrorState, PageLoadingState } from "@/components/ui/page-state";
import { NextBestActionsPanel } from "@/components/operations/next-best-actions-panel";
import { ReportDeliveryProfileManager } from "@/components/reports/report-delivery-profile-manager";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { buildApiPathWithFilters, buildHrefWithFilters, GLOBAL_DATE_FILTER_KEYS } from "@/lib/filters";
import { CampaignDetailResponse } from "@/lib/types";

const SpendResultChart = dynamic(
  () => import("@/components/charts/spend-result-chart").then((mod) => mod.SpendResultChart),
  {
    ssr: false,
    loading: () => <div className="h-[280px] w-full rounded-md bg-[var(--surface-2)]" />,
  },
);

const TABS = [
  { id: "overview", label: "Genel Bakis" },
  { id: "adsets", label: "Ad Setler" },
  { id: "ads", label: "Reklamlar" },
  { id: "alerts", label: "Uyarilar" },
  { id: "report", label: "Rapor" },
] as const;

type TabId = (typeof TABS)[number]["id"];

function formatCurrency(value: number | null) {
  if (value === null) return "-";
  return `$${value.toFixed(2)}`;
}

function formatNumber(value: number | null) {
  if (value === null) return "-";
  return value.toFixed(value % 1 === 0 ? 0 : 2);
}

function variantFor(value: string) {
  if (value === "critical" || value === "high") return "danger" as const;
  if (value === "warning" || value === "medium") return "warning" as const;
  if (value === "healthy" || value === "active") return "success" as const;
  return "neutral" as const;
}

function targetingLabel(item: CampaignDetailResponse["data"]["ad_sets"][number]) {
  const countries = item.targeting_summary.countries.join(", ");
  const cities = item.targeting_summary.cities.join(", ");
  const locations = countries || cities || "Lokasyon yok";
  const ageMin = item.targeting_summary.age_range.min;
  const ageMax = item.targeting_summary.age_range.max;
  const ageRange = ageMin || ageMax ? `${ageMin ?? "?"}-${ageMax ?? "?"}` : "Yas araligi yok";

  return `${locations} / ${ageRange}`;
}

export function CampaignDetailClient() {
  const searchParams = useSearchParams();
  const campaignId = searchParams.get("id");
  const hasCampaignId = Boolean(campaignId);
  const [activeTab, setActiveTab] = useState<TabId>("overview");

  const { data, error, isLoading, isRefreshing, reload } = useApiQuery<CampaignDetailResponse, CampaignDetailResponse["data"]>(
    buildApiPathWithFilters(`/campaigns/${campaignId ?? ""}`, searchParams, GLOBAL_DATE_FILTER_KEYS),
    {
      enabled: hasCampaignId,
      requestOptions: {
        requireWorkspace: true,
      },
      ttlMs: QUERY_TTLS.campaignDetail,
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
        note: "Kampanya seviyesinde normalize veri.",
      },
      {
        label: "CPA / CPL",
        value: data.summary.cpa_cpl ? formatCurrency(data.summary.cpa_cpl) : "-",
        note: "Bir sonuc icin ortalama maliyet.",
      },
      {
        label: "Aktif Yapi",
        value: `${data.summary.active_ad_sets} / ${data.summary.active_ads}`,
        note: "Ad Set / Reklam",
      },
    ];
  }, [data]);

  if (!hasCampaignId) {
    return <PageErrorState title="Kampanya acilamadi" detail="Kampanya id eksik." />;
  }

  if (error) {
    return <PageErrorState title="Kampanya acilamadi" detail={error} />;
  }

  if (isLoading && !data) {
    return <PageLoadingState title="Kampanya yukleniyor" detail="Kampanya, ad set ve reklam baglami hazirlaniyor." />;
  }

  if (!data) {
    return <PageErrorState title="Kampanya bulunamadi" detail="Secili kampanya kaydi artik mevcut degil." />;
  }

  return (
    <div className="space-y-4">
      <PageBreadcrumbs
        items={[
          { label: "Workspace", href: "/workspaces" },
          { label: "Reklam Hesaplari", href: "/ad-accounts" },
          {
            label: data.campaign.ad_account.name ?? "Hesap",
            href: buildHrefWithFilters(
              `/ad-accounts/detail?id=${encodeURIComponent(data.campaign.ad_account.id ?? "")}`,
              searchParams,
              GLOBAL_DATE_FILTER_KEYS,
            ),
          },
          { label: "Kampanya", href: "/campaigns" },
          { label: data.campaign.name },
        ]}
      />

      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-bold">{data.campaign.name}</h2>
          <p className="text-sm muted-text">
            {data.campaign.objective ?? "Objective yok"}
            {data.campaign.ad_account.account_id ? ` / ${data.campaign.ad_account.account_id}` : ""}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge label={data.campaign.status} variant={variantFor(data.campaign.status)} />
          <Badge label={data.health.status} variant={variantFor(data.health.status)} />
          <Button variant="secondary" onClick={() => void reload()}>
            {isRefreshing ? "Yenileniyor..." : "Yenile"}
          </Button>
        </div>
      </div>

      <Card>
        <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
          <div>
            <CardTitle>Bu Kampanyada Ne Oluyor?</CardTitle>
            <p className="mt-3 text-lg font-semibold leading-7">{data.health.summary}</p>
            <div className="mt-4 flex flex-wrap gap-2">
              <span className="rounded-full bg-[var(--surface-2)] px-3 py-2 text-sm font-medium">
                {data.summary.open_alerts} acik uyari
              </span>
              <span className="rounded-full bg-[var(--surface-2)] px-3 py-2 text-sm font-medium">
                {data.summary.open_recommendations} acik oneri
              </span>
              <span className="rounded-full bg-[var(--surface-2)] px-3 py-2 text-sm font-medium">
                {data.summary.active_ad_sets} aktif ad set
              </span>
            </div>
          </div>

          <div className="rounded-xl border border-[var(--border)] bg-[var(--surface-2)] p-4 text-sm">
            <p className="text-xs font-semibold uppercase tracking-wide muted-text">Kampanya Baglami</p>
            <div className="mt-3 space-y-3">
              <div>
                <p className="muted-text">Hesap</p>
                <p>{data.campaign.ad_account.name ?? "-"}</p>
              </div>
              <div>
                <p className="muted-text">Son Senkron</p>
                <p>{data.campaign.last_synced_at ?? "Bilinmiyor"}</p>
              </div>
              <div>
                <p className="muted-text">Butce</p>
                <p>{data.campaign.daily_budget ? formatCurrency(data.campaign.daily_budget) : "-"}</p>
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
        <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.45fr_1fr]">
          <Card>
            <CardTitle>Harcama / Sonuc Trendi</CardTitle>
            <div className="mt-3">
              <SpendResultChart
                data={data.trend.map((item) => ({
                  date: item.date,
                  spend: item.spend,
                  results: item.results,
                }))}
              />
            </div>
          </Card>

          <div className="space-y-4">
            <NextBestActionsPanel
              items={data.next_best_actions}
              emptyText="Bu kampanya icin kayitli sonraki adim bulunmuyor."
            />

            <Card>
              <CardTitle>Analiz</CardTitle>
              <div className="mt-3 space-y-3 text-sm">
                <div>
                  <p className="font-semibold">En Buyuk Risk</p>
                  <p className="muted-text">{data.analysis.biggest_risk ?? "Kayitli kritik risk yok."}</p>
                </div>
                <div>
                  <p className="font-semibold">En Buyuk Firsat</p>
                  <p className="muted-text">{data.analysis.biggest_opportunity ?? "Kayitli buyutme firsati yok."}</p>
                </div>
                <div>
                  <p className="font-semibold">Operator Notu</p>
                  <p className="muted-text">{data.analysis.operator_note ?? "-"}</p>
                </div>
                <div>
                  <p className="font-semibold">Musteri Dili</p>
                  <p className="muted-text">{data.analysis.client_note ?? "-"}</p>
                </div>
              </div>
            </Card>

            <Card>
              <CardTitle>Rapor Ozet Taslagi</CardTitle>
              <div className="mt-3 space-y-3 text-sm">
                <p className="font-semibold">{data.report_preview.headline}</p>
                <p className="muted-text">{data.report_preview.client_summary}</p>
                <p className="muted-text">{data.report_preview.operator_summary}</p>
                <div>
                  <p className="font-semibold">Bir Sonraki Test</p>
                  <p className="muted-text">{data.report_preview.next_test}</p>
                </div>
              </div>
            </Card>

            <Card>
              <CardTitle>Varsayilan Rapor Teslim Profili</CardTitle>
              <ReportDeliveryProfileManager
                entityType="campaign"
                entityId={data.campaign.id}
                currentProfile={data.delivery_profile}
                suggestedGroups={data.suggested_recipient_groups}
                reportCenterHref={buildHrefWithFilters("/reports", searchParams, GLOBAL_DATE_FILTER_KEYS)}
                onChanged={reload}
              />
            </Card>
          </div>
        </section>
      ) : null}

      {activeTab === "adsets" ? (
        <Card>
          <CardTitle>Ad Set Drill-Down</CardTitle>
          <div className="mt-3 overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] text-left">
                  <th className="px-3 py-2">Ad Set</th>
                  <th className="px-3 py-2">Durum</th>
                  <th className="px-3 py-2">Hedefleme</th>
                  <th className="px-3 py-2">Performans</th>
                  <th className="px-3 py-2">Reklamlar</th>
                </tr>
              </thead>
              <tbody>
                {data.ad_sets.map((item) => (
                  <tr key={item.id} className="border-b border-[var(--border)] align-top">
                    <td className="px-3 py-3">
                      <Link
                        href={buildHrefWithFilters(
                          `/ad-sets/detail?id=${encodeURIComponent(item.id)}`,
                          searchParams,
                          GLOBAL_DATE_FILTER_KEYS,
                        )}
                        className="font-semibold text-[var(--accent)] hover:underline"
                      >
                        {item.name}
                      </Link>
                      <p className="mt-1 text-xs muted-text">{item.optimization_goal ?? "Optimization goal yok"}</p>
                    </td>
                    <td className="px-3 py-3">
                      <div className="flex flex-col gap-2">
                        <Badge label={item.status} variant={variantFor(item.status)} />
                        <Badge label={item.health_status} variant={variantFor(item.health_status)} />
                      </div>
                      <p className="mt-2 text-xs muted-text">{item.health_summary}</p>
                    </td>
                    <td className="px-3 py-3">
                      <p className="font-medium">{targetingLabel(item)}</p>
                      <p className="text-xs muted-text">{item.targeting_summary.platforms.join(", ") || "Platform yok"}</p>
                    </td>
                    <td className="px-3 py-3">
                      <p className="font-semibold">Harcama {formatCurrency(item.spend)}</p>
                      <p className="text-xs muted-text">
                        Sonuc {formatNumber(item.results)} / {item.has_performance_data ? "ad set metric" : "kampanya baglami"}
                      </p>
                    </td>
                    <td className="px-3 py-3">
                      <p className="font-semibold">{item.active_ads} aktif</p>
                      <p className="text-xs muted-text">Toplam {item.ads_count}</p>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data.ad_sets.length === 0 ? <p className="mt-3 text-sm muted-text">Ad set bulunmuyor.</p> : null}
        </Card>
      ) : null}

      {activeTab === "ads" ? (
        <Card>
          <CardTitle>Reklam Drill-Down</CardTitle>
          <div className="mt-3 overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] text-left">
                  <th className="px-3 py-2">Reklam</th>
                  <th className="px-3 py-2">Baglam</th>
                  <th className="px-3 py-2">Kreatif</th>
                  <th className="px-3 py-2">Performans</th>
                </tr>
              </thead>
              <tbody>
                {data.ads.map((item) => (
                  <tr key={item.id} className="border-b border-[var(--border)] align-top">
                    <td className="px-3 py-3">
                      <Link
                        href={buildHrefWithFilters(
                          `/ads/detail?id=${encodeURIComponent(item.id)}`,
                          searchParams,
                          GLOBAL_DATE_FILTER_KEYS,
                        )}
                        className="font-semibold text-[var(--accent)] hover:underline"
                      >
                        {item.name}
                      </Link>
                      <p className="mt-1 text-xs muted-text">{item.health_summary}</p>
                    </td>
                    <td className="px-3 py-3">
                      <Badge label={item.status} variant={variantFor(item.status)} />
                      <p className="mt-2 text-xs muted-text">{item.ad_set.name ?? "Ad set yok"}</p>
                    </td>
                    <td className="px-3 py-3">
                      <p className="font-medium">{item.creative.headline ?? item.creative.name ?? "Kreatif bilgisi yok"}</p>
                      <p className="text-xs muted-text">{item.creative.call_to_action ?? item.creative.asset_type ?? "-"}</p>
                    </td>
                    <td className="px-3 py-3">
                      <p className="font-semibold">Harcama {formatCurrency(item.spend)}</p>
                      <p className="text-xs muted-text">
                        Sonuc {formatNumber(item.results)} / {item.has_performance_data ? "ad metric" : "kampanya baglami"}
                      </p>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data.ads.length === 0 ? <p className="mt-3 text-sm muted-text">Reklam bulunmuyor.</p> : null}
        </Card>
      ) : null}

      {activeTab === "alerts" ? (
        <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <Card>
            <CardTitle>Kampanya Uyarilari</CardTitle>
            <div className="mt-3 space-y-3">
              {data.alerts.map((item) => (
                <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
                  <div className="mb-1 flex items-center justify-between gap-3">
                    <p className="font-semibold">{item.summary}</p>
                    <Badge label={item.severity} variant={variantFor(item.severity)} />
                  </div>
                  <p className="text-xs muted-text">{item.date_detected ?? "-"}</p>
                  <p className="mt-1 text-xs muted-text">
                    {item.entity_label ?? "Kampanya"}
                    {item.context_label ? ` / ${item.context_label}` : ""}
                  </p>
                  <div className="mt-3 space-y-2 text-sm">
                    <div>
                      <p className="font-semibold">Neden Onemli?</p>
                      <p className="muted-text">{item.impact_summary}</p>
                    </div>
                    <div>
                      <p className="font-semibold">Sonraki Adim</p>
                      <p>{item.next_step}</p>
                    </div>
                  </div>
                  {item.route ? (
                    <Link href={item.route} className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
                      Ilgili kaydi ac
                    </Link>
                  ) : null}
                </div>
              ))}
              {data.alerts.length === 0 ? <p className="text-sm muted-text">Aktif kampanya uyarisi yok.</p> : null}
            </div>
          </Card>

          <Card>
            <CardTitle>Kampanya Onerileri</CardTitle>
            <div className="mt-3 space-y-3">
              {data.recommendations.map((item) => (
                <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
                  <div className="mb-1 flex items-center justify-between gap-3">
                    <p className="font-semibold">{item.summary}</p>
                    <Badge label={item.priority} variant={variantFor(item.priority)} />
                  </div>
                  <div className="flex flex-wrap items-center gap-2 text-xs muted-text">
                    <span>{item.generated_at ?? "-"}</span>
                    <span>•</span>
                    <span>{item.action_status.label}</span>
                  </div>
                  <p className="mt-1 text-xs muted-text">
                    {item.entity_label ?? "Kampanya"}
                    {item.context_label ? ` / ${item.context_label}` : ""}
                  </p>
                  <div className="mt-3 grid gap-3 xl:grid-cols-2">
                    <div>
                      <p className="font-semibold">Operator View</p>
                      <p className="mt-1 text-sm muted-text">{item.operator_view.summary}</p>
                      <p className="mt-2 text-xs muted-text">Sonraki test: {item.operator_view.next_test ?? "-"}</p>
                    </div>
                    <div>
                      <p className="font-semibold">Client View</p>
                      <p className="mt-1 text-sm muted-text">{item.client_view.summary}</p>
                    </div>
                  </div>
                  {item.route ? (
                    <Link href={item.route} className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
                      Ilgili kaydi ac
                    </Link>
                  ) : null}
                </div>
              ))}
              {data.recommendations.length === 0 ? <p className="text-sm muted-text">Kayitli kampanya onerisi yok.</p> : null}
            </div>
          </Card>
        </section>
      ) : null}

      {activeTab === "report" ? (
        <Card>
          <CardTitle>Musteri Raporu Hazirlik Bloku</CardTitle>
          <div className="mt-3 grid gap-4 xl:grid-cols-2">
            <div className="rounded-lg border border-[var(--border)] p-4">
              <p className="text-sm font-semibold">Musteri Ozet Basligi</p>
              <p className="mt-2 text-sm">{data.report_preview.headline}</p>
            </div>
            <div className="rounded-lg border border-[var(--border)] p-4">
              <p className="text-sm font-semibold">Musteri Dili</p>
              <p className="mt-2 text-sm">{data.report_preview.client_summary}</p>
            </div>
            <div className="rounded-lg border border-[var(--border)] p-4">
              <p className="text-sm font-semibold">Operasyon Ozet</p>
              <p className="mt-2 text-sm">{data.report_preview.operator_summary}</p>
            </div>
            <div className="rounded-lg border border-[var(--border)] p-4">
              <p className="text-sm font-semibold">Bir Sonraki Test</p>
              <p className="mt-2 text-sm">{data.report_preview.next_test}</p>
              <Link
                href={buildHrefWithFilters(
                  `/reports/campaign?id=${encodeURIComponent(data.campaign.id)}`,
                  searchParams,
                  GLOBAL_DATE_FILTER_KEYS,
                )}
                className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline"
              >
                Tam campaign raporunu ac
              </Link>
            </div>
            <div className="rounded-lg border border-[var(--border)] p-4 xl:col-span-2">
              <p className="text-sm font-semibold">Teslim Profili</p>
              {data.delivery_profile ? (
                <>
                  <div className="mt-2 flex flex-wrap gap-2">
                    <Badge label={data.delivery_profile.is_active ? "active" : "inactive"} variant={data.delivery_profile.is_active ? "success" : "warning"} />
                    <Badge label={data.delivery_profile.cadence_label} variant="neutral" />
                  </div>
                  <p className="mt-2 text-sm">
                    {data.delivery_profile.cadence_label} / {data.delivery_profile.delivery_channel_label}
                  </p>
                  <p className="mt-1 text-xs muted-text">
                    Grup: {data.delivery_profile.recipient_group_summary.label}
                  </p>
                  <p className="mt-1 text-xs muted-text">
                    Statik: {data.delivery_profile.recipient_group_summary.static_recipients_count} / Dinamik: {data.delivery_profile.recipient_group_summary.dynamic_contacts_count}
                  </p>
                </>
              ) : (
                <p className="mt-2 text-sm muted-text">Bu kampanya icin varsayilan teslim profili tanimli degil.</p>
              )}
            </div>
          </div>
        </Card>
      ) : null}
    </div>
  );
}
