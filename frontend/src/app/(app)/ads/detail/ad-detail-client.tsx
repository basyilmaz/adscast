"use client";

import Link from "next/link";
import dynamic from "next/dynamic";
import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { PageBreadcrumbs } from "@/components/layout/page-breadcrumbs";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { PageErrorState, PageLoadingState } from "@/components/ui/page-state";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { buildApiPathWithFilters, buildHrefWithFilters, GLOBAL_DATE_FILTER_KEYS } from "@/lib/filters";
import { AdDetailResponse } from "@/lib/types";

const SpendResultChart = dynamic(
  () => import("@/components/charts/spend-result-chart").then((mod) => mod.SpendResultChart),
  {
    ssr: false,
    loading: () => <div className="h-[280px] w-full rounded-md bg-[var(--surface-2)]" />,
  },
);

function formatCurrency(value: number | null) {
  if (value === null) return "-";
  return `$${value.toFixed(2)}`;
}

function formatNumber(value: number | null) {
  if (value === null) return "-";
  return value.toFixed(value % 1 === 0 ? 0 : 2);
}

type SortKey = "spend" | "results" | "cpa_cpl" | "ctr" | "cpm";
type SortDir = "asc" | "desc";

function sortByMetric<T extends Record<string, unknown>>(items: T[], key: SortKey, dir: SortDir): T[] {
  return [...items].sort((a, b) => {
    const av = (a[key] as number | null) ?? (dir === "asc" ? Infinity : -Infinity);
    const bv = (b[key] as number | null) ?? (dir === "asc" ? Infinity : -Infinity);
    return dir === "desc" ? bv - av : av - bv;
  });
}

function SortButton({ label, sortKey, activeKey, dir, onSort }: {
  label: string; sortKey: SortKey; activeKey: SortKey; dir: SortDir; onSort: (key: SortKey) => void;
}) {
  const isActive = sortKey === activeKey;
  return (
    <button type="button" onClick={() => onSort(sortKey)} className="flex items-center gap-1 font-semibold hover:text-[var(--accent)]">
      {label}{isActive ? (dir === "desc" ? " ↓" : " ↑") : ""}
    </button>
  );
}

function formatPercent(value: number | null) {
  if (value === null) return "-";
  return `${value.toFixed(2)}%`;
}

function variantFor(value: string) {
  if (value === "critical" || value === "high") return "danger" as const;
  if (value === "warning" || value === "medium") return "warning" as const;
  if (value === "healthy" || value === "active") return "success" as const;
  return "neutral" as const;
}

export function AdDetailClient() {
  const searchParams = useSearchParams();
  const adId = searchParams.get("id");
  const hasAdId = Boolean(adId);
  const { data, error, isLoading, isRefreshing, reload } = useApiQuery<AdDetailResponse, AdDetailResponse["data"]>(
    buildApiPathWithFilters(`/ads/${adId ?? ""}`, searchParams, GLOBAL_DATE_FILTER_KEYS),
    {
      enabled: hasAdId,
      requestOptions: {
        requireWorkspace: true,
      },
      ttlMs: QUERY_TTLS.adDetail,
      select: (response) => response.data,
    },
  );

  const [siblingSort, setSiblingSort] = useState<{ key: SortKey; dir: SortDir }>({ key: "spend", dir: "desc" });
  const toggleSiblingSort = (key: SortKey) => setSiblingSort((prev) => ({ key, dir: prev.key === key && prev.dir === "desc" ? "asc" : "desc" }));

  if (!hasAdId) return <PageErrorState title="Reklam acilamadi" detail="Reklam id eksik." />;
  if (error) return <PageErrorState title="Reklam acilamadi" detail={error} />;
  if (isLoading && !data) return <PageLoadingState title="Reklam yukleniyor" detail="Kreatif, sibling ve inherited risk baglami hazirlaniyor." />;
  if (!data) return <PageErrorState title="Reklam bulunamadi" detail="Secili reklam kaydi artik mevcut degil." />;

  return (
    <div className="space-y-4">
      <PageBreadcrumbs
        items={[
          { label: "Workspace", href: "/workspaces" },
          { label: "Reklam Hesaplari", href: "/ad-accounts" },
          {
            label: data.ad.ad_account.name ?? "Hesap",
            href: buildHrefWithFilters(
              `/ad-accounts/detail?id=${encodeURIComponent(data.ad.ad_account.id ?? "")}`,
              searchParams,
              GLOBAL_DATE_FILTER_KEYS,
            ),
          },
          {
            label: data.ad.campaign.name ?? "Kampanya",
            href: buildHrefWithFilters(
              `/campaigns/detail?id=${encodeURIComponent(data.ad.campaign.id ?? "")}`,
              searchParams,
              GLOBAL_DATE_FILTER_KEYS,
            ),
          },
          {
            label: data.ad.ad_set.name ?? "Ad Set",
            href: buildHrefWithFilters(
              `/ad-sets/detail?id=${encodeURIComponent(data.ad.ad_set.id ?? "")}`,
              searchParams,
              GLOBAL_DATE_FILTER_KEYS,
            ),
          },
          { label: data.ad.name },
        ]}
      />

      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-bold">{data.ad.name}</h2>
          <p className="text-sm muted-text">{data.ad.meta_ad_id}</p>
        </div>
        <div className="flex items-center gap-2">
          <Badge label={data.ad.status} variant={variantFor(data.ad.status)} />
          <Button variant="secondary" onClick={() => void reload()}>
            {isRefreshing ? "Yenileniyor..." : "Yenile"}
          </Button>
        </div>
      </div>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardTitle>Harcama</CardTitle>
          <CardValue>{formatCurrency(data.summary.spend)}</CardValue>
          <p className="mt-2 text-sm muted-text">{data.summary.performance_scope === "ad" ? "Ad seviyesi veri" : "Kampanya baglami"}</p>
        </Card>
        <Card>
          <CardTitle>Sonuc</CardTitle>
          <CardValue>{formatNumber(data.summary.results)}</CardValue>
          <p className="mt-2 text-sm muted-text">Secili aralik performansi.</p>
        </Card>
        <Card>
          <CardTitle>CPA / CPL</CardTitle>
          <CardValue>{data.summary.cpa_cpl !== null ? formatCurrency(data.summary.cpa_cpl) : "-"}</CardValue>
          <p className="mt-2 text-sm muted-text">Bir sonuc icin ortalama maliyet.</p>
        </Card>
        <Card>
          <CardTitle>CTR / CPM</CardTitle>
          <CardValue>{data.summary.ctr !== null ? `${data.summary.ctr.toFixed(2)}%` : "-"}</CardValue>
          <p className="mt-2 text-sm muted-text">CPM {formatCurrency(data.summary.cpm)}</p>
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.3fr_1fr]">
        <Card>
          <CardTitle>Trend</CardTitle>
          <div className="mt-3">
            <SpendResultChart data={data.trend.map((item) => ({ date: item.date, spend: item.spend, results: item.results }))} />
          </div>
        </Card>

        <Card>
          <CardTitle>Kreatif Detayi</CardTitle>
          <div className="mt-3 space-y-3 text-sm">
            <div>
              <p className="font-semibold">Headline</p>
              <p className="muted-text">{data.creative.headline ?? "-"}</p>
            </div>
            <div>
              <p className="font-semibold">Body</p>
              <p className="muted-text">{data.creative.body ?? "-"}</p>
            </div>
            <div>
              <p className="font-semibold">CTA</p>
              <p className="muted-text">{data.creative.call_to_action ?? "-"}</p>
            </div>
            <div>
              <p className="font-semibold">Hedef URL</p>
              <p className="muted-text break-all">{data.creative.destination_url ?? "-"}</p>
            </div>
            {data.ad.preview_url ? (
              <Link href={data.ad.preview_url} target="_blank" className="inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
                Meta onizlemeyi ac
              </Link>
            ) : null}
          </div>
        </Card>
      </section>

      <Card>
        <CardTitle>Sibling Reklamlar</CardTitle>
        <div className="mt-3 overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--border)] text-left">
                <th className="px-3 py-2">Reklam</th>
                <th className="px-3 py-2">Kreatif</th>
                <th className="px-3 py-2"><SortButton label="Harcama" sortKey="spend" activeKey={siblingSort.key} dir={siblingSort.dir} onSort={toggleSiblingSort} /></th>
                <th className="px-3 py-2"><SortButton label="Sonuc" sortKey="results" activeKey={siblingSort.key} dir={siblingSort.dir} onSort={toggleSiblingSort} /></th>
                <th className="px-3 py-2"><SortButton label="CPA/CPL" sortKey="cpa_cpl" activeKey={siblingSort.key} dir={siblingSort.dir} onSort={toggleSiblingSort} /></th>
                <th className="px-3 py-2"><SortButton label="CTR" sortKey="ctr" activeKey={siblingSort.key} dir={siblingSort.dir} onSort={toggleSiblingSort} /></th>
                <th className="px-3 py-2"><SortButton label="CPM" sortKey="cpm" activeKey={siblingSort.key} dir={siblingSort.dir} onSort={toggleSiblingSort} /></th>
              </tr>
            </thead>
            <tbody>
              {sortByMetric(data.sibling_ads, siblingSort.key, siblingSort.dir).map((item) => (
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
                    <Badge label={item.status} variant={variantFor(item.status)} />
                  </td>
                  <td className="px-3 py-3">
                    <p className="font-medium">{item.creative.headline ?? item.creative.asset_type ?? "Kreatif yok"}</p>
                  </td>
                  <td className="px-3 py-3 font-semibold">{formatCurrency(item.spend)}</td>
                  <td className="px-3 py-3 font-semibold">{formatNumber(item.results)}</td>
                  <td className="px-3 py-3 font-semibold">{formatCurrency(item.cpa_cpl)}</td>
                  <td className="px-3 py-3">{formatPercent(item.ctr)}</td>
                  <td className="px-3 py-3">{formatCurrency(item.cpm)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {data.sibling_ads.length === 0 ? <p className="mt-3 text-sm muted-text">Sibling reklam yok.</p> : null}
      </Card>

      {data.own_alerts?.length > 0 ? (
        <Card>
          <CardTitle>Reklam Uyarilari</CardTitle>
          <div className="mt-3 space-y-3">
            {data.own_alerts.map((item: { id: string; severity: string; summary: string; recommended_action: string | null; date_detected: string | null }) => (
              <div key={item.id} className="rounded-md border-2 border-[var(--danger)] bg-[var(--surface-2)] p-3">
                <div className="mb-1 flex items-center justify-between gap-3">
                  <p className="font-semibold">{item.summary}</p>
                  <Badge label={item.severity} variant={variantFor(item.severity)} />
                </div>
                <p className="text-xs muted-text">{item.date_detected ?? "-"}</p>
                <p className="mt-2 text-sm font-medium">{item.recommended_action ?? "-"}</p>
              </div>
            ))}
          </div>
        </Card>
      ) : null}

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <Card>
          <CardTitle>Inherited Uyarilar (Kampanya)</CardTitle>
          <div className="mt-3 space-y-3">
            {data.inherited_alerts.map((item) => (
              <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
                <div className="mb-1 flex items-center justify-between gap-3">
                  <p className="font-semibold">{item.summary}</p>
                  <Badge label={item.severity} variant={variantFor(item.severity)} />
                </div>
                <p className="text-xs muted-text">{item.date_detected ?? "-"}</p>
                <p className="mt-2 text-sm">{item.recommended_action ?? "-"}</p>
              </div>
            ))}
            {data.inherited_alerts.length === 0 ? <p className="text-sm muted-text">Inherited uyari yok.</p> : null}
          </div>
        </Card>

        <Card>
          <CardTitle>Operasyon Rehberi</CardTitle>
          <div className="mt-3 space-y-3 text-sm">
            <p>{data.guidance.operator_summary}</p>
            <p className="muted-text">{data.guidance.data_scope_note}</p>
            <div>
              <p className="font-semibold">Kreatif Notu</p>
              <p className="muted-text">{data.guidance.creative_note}</p>
            </div>
            <div>
              <p className="font-semibold">Risk Notu</p>
              <p className="muted-text">{data.guidance.risk_note}</p>
            </div>
          </div>
        </Card>
      </section>
    </div>
  );
}
