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
import { ReportDeliveryProfileSuggestionCard } from "@/components/reports/report-delivery-profile-suggestion-card";
import { ReportRecipientGroupEntityInsightsPanel } from "@/components/reports/report-recipient-group-entity-insights-panel";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { buildApiPathWithFilters, buildHrefWithFilters, GLOBAL_DATE_FILTER_KEYS } from "@/lib/filters";
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
    buildApiPathWithFilters(`/meta/ad-accounts/${adAccountId ?? ""}`, searchParams, GLOBAL_DATE_FILTER_KEYS),
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
    return <PageErrorState title="Reklam hesabi acilamadi" detail="Reklam hesabi id eksik." />;
  }

  if (error) {
    return <PageErrorState title="Reklam hesabi acilamadi" detail={error} />;
  }

  if (isLoading && !data) {
    return <PageLoadingState title="Reklam hesabi yukleniyor" detail="Hesap, kampanya ve aksiyon baglami hazirlaniyor." />;
  }

  if (!data) {
    return <PageErrorState title="Reklam hesabi bulunamadi" detail="Secili reklam hesabi kaydi artik mevcut degil." />;
  }

  return (
    <div className="space-y-4">
      <PageBreadcrumbs
        items={[
          { label: "Workspace", href: "/workspaces" },
          { label: "Reklam Hesaplari", href: "/ad-accounts" },
          { label: data.ad_account.name },
        ]}
      />

      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
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

          <div className="space-y-4">
            <NextBestActionsPanel
              items={data.next_best_actions}
              emptyText="Bu hesap icin kayitli sonraki adim bulunmuyor."
            />

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

            <Card>
              <CardTitle>Varsayilan Rapor Teslim Profili</CardTitle>
              <ReportDeliveryProfileManager
                entityType="account"
                entityId={data.ad_account.id}
                currentProfile={data.delivery_profile}
                suggestedGroups={data.suggested_recipient_groups}
                reportCenterHref={buildHrefWithFilters("/reports", searchParams, GLOBAL_DATE_FILTER_KEYS)}
                onChanged={reload}
              />
            </Card>
          </div>
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
                        href={buildHrefWithFilters(
                          `/campaigns/detail?id=${encodeURIComponent(campaign.id)}`,
                          searchParams,
                          GLOBAL_DATE_FILTER_KEYS,
                        )}
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
                <p className="mt-1 text-sm muted-text">
                  {alert.entity_label ?? "Hesap seviyesi"}
                  {alert.context_label ? ` / ${alert.context_label}` : ""}
                </p>
                <div className="mt-3 space-y-2 text-sm">
                  <div>
                    <p className="font-semibold">Neden Onemli?</p>
                    <p className="muted-text">{alert.impact_summary}</p>
                  </div>
                  <div>
                    <p className="font-semibold">Sonraki Adim</p>
                    <p>{alert.next_step}</p>
                  </div>
                </div>
                {alert.route ? (
                  <Link href={alert.route} className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
                    Ilgili kaydi ac
                  </Link>
                ) : null}
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
                  <Badge label={recommendation.action_status.label} variant="neutral" />
                  <span className="text-xs muted-text">{recommendation.generated_at ?? "-"}</span>
                </div>
                <p className="mt-2 font-semibold">{recommendation.summary}</p>
                <p className="mt-1 text-sm muted-text">
                  {recommendation.entity_label ?? "Hesap seviyesi"}
                  {recommendation.context_label ? ` / ${recommendation.context_label}` : ""}
                </p>
                <div className="mt-3 grid gap-3 xl:grid-cols-2">
                  <div>
                    <p className="text-sm font-semibold">Operator View</p>
                    <p className="mt-1 text-sm muted-text">{recommendation.operator_view.summary}</p>
                    <p className="mt-2 text-xs muted-text">Sonraki test: {recommendation.operator_view.next_test ?? "-"}</p>
                  </div>
                  <div>
                    <p className="text-sm font-semibold">Client View</p>
                    <p className="mt-1 text-sm muted-text">{recommendation.client_view.summary}</p>
                  </div>
                </div>
                {recommendation.route ? (
                  <Link href={recommendation.route} className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
                    Ilgili kaydi ac
                  </Link>
                ) : null}
              </div>
            ))}
            {data.recommendations.length === 0 ? (
              <p className="text-sm muted-text">Bu hesap icin kampanya bazli kayitli oneri bulunmuyor.</p>
            ) : null}
          </div>
        </Card>
      ) : null}

      {activeTab === "reports" ? (
        <section className="space-y-4">
          <ReportDeliveryProfileSuggestionCard
            suggestion={data.suggested_delivery_profile}
            entityLabel={data.ad_account.name}
            entityType="account"
            entityId={data.ad_account.id}
            onApplied={reload}
          />

          <ReportRecipientGroupEntityInsightsPanel
            analyticsSummary={data.recipient_group_analytics_summary}
            analyticsItems={data.recipient_group_analytics}
            alignmentSummary={data.recipient_group_alignment_summary}
            alignmentItems={data.recipient_group_alignment}
            failureAlignmentSummary={data.recipient_group_failure_alignment_summary}
            failureAlignmentItems={data.recipient_group_failure_alignment}
            failureReasonSummary={data.recipient_group_failure_reason_summary}
            failureReasonItems={data.recipient_group_failure_reasons}
            entityLabel={data.ad_account.name}
          />

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
                <Link
                  href={buildHrefWithFilters(
                    `/reports/account?id=${encodeURIComponent(data.ad_account.id)}`,
                    searchParams,
                    GLOBAL_DATE_FILTER_KEYS,
                  )}
                  className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline"
                >
                  Tam account raporunu ac
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
                  <p className="mt-2 text-sm muted-text">Bu hesap icin varsayilan teslim profili tanimli degil.</p>
                )}
              </div>
            </div>
          </Card>
        </section>
      ) : null}
    </div>
  );
}
