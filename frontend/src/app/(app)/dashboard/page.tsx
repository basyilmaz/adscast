"use client";

import { useMemo } from "react";
import dynamic from "next/dynamic";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { DashboardOverviewResponse } from "@/lib/types";

const SpendResultChart = dynamic(
  () => import("@/components/charts/spend-result-chart").then((mod) => mod.SpendResultChart),
  {
    ssr: false,
    loading: () => <div className="h-[280px] w-full rounded-md bg-[var(--surface-2)]" />,
  },
);

const fallbackTrend = Array.from({ length: 7 }).map((_, index) => ({
  date: `G-${6 - index}`,
  spend: 80 + index * 10,
  results: 8 + index,
}));

export default function DashboardPage() {
  const {
    data,
    error,
    isLoading,
    isRefreshing,
    reload,
  } = useApiQuery<DashboardOverviewResponse, DashboardOverviewResponse["data"]>("/dashboard/overview", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.dashboard,
    select: (response) => response.data,
  });

  const metricCards = useMemo(() => {
    if (!data) return [];
    return [
      { label: "Toplam Harcama", value: `$${data.metrics.total_spend.toFixed(2)}` },
      { label: "Toplam Sonuc", value: `${data.metrics.total_results.toFixed(0)}` },
      { label: "CPA / CPL", value: `$${data.metrics.cpa_cpl.toFixed(2)}` },
      { label: "CTR", value: `${data.metrics.ctr.toFixed(2)}%` },
      { label: "CPM", value: `$${data.metrics.cpm.toFixed(2)}` },
      { label: "Frekans", value: `${data.metrics.frequency.toFixed(2)}` },
    ];
  }, [data]);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="text-sm muted-text">
          {isLoading ? "Veriler yukleniyor..." : isRefreshing ? "Veriler arka planda yenileniyor..." : "Son senkron verisi gosteriliyor."}
        </div>
        <Button variant="secondary" onClick={() => void reload()}>
          Yenile
        </Button>
      </div>

      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {isLoading && metricCards.length === 0
          ? Array.from({ length: 6 }).map((_, index) => (
              <Card key={`dashboard-skeleton-${index}`} className="min-h-[116px] animate-pulse">
                <CardTitle>Yukleniyor</CardTitle>
                <CardValue>...</CardValue>
              </Card>
            ))
          : null}
        {metricCards.map((metric) => (
          <Card key={metric.label}>
            <CardTitle>{metric.label}</CardTitle>
            <CardValue>{metric.value}</CardValue>
          </Card>
        ))}
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <Card className="xl:col-span-2">
          <CardTitle>Harcama / Sonuc Trendi</CardTitle>
          <div className="mt-3">
            <SpendResultChart data={fallbackTrend} />
          </div>
        </Card>

        <Card>
          <CardTitle>Sinyal Ozetleri</CardTitle>
          <div className="mt-3 space-y-3 text-sm">
            <div>
              <p className="muted-text">Aktif Uyari</p>
              <p className="text-xl font-bold">{data?.active_alerts ?? 0}</p>
            </div>
            <div>
              <p className="muted-text">En Iyi Kampanya</p>
              <p className="font-semibold">{data?.best_campaign?.name ?? "-"}</p>
            </div>
            <div>
              <p className="muted-text">En Zayif Kampanya</p>
              <p className="font-semibold">{data?.worst_campaign?.name ?? "-"}</p>
            </div>
            <div>
              <p className="muted-text">Sync Freshness</p>
              <p>{data?.sync_freshness?.last_synced_at ?? "Bilinmiyor"}</p>
            </div>
          </div>
        </Card>
      </section>

      <Card>
        <CardTitle>Son AI Onerileri</CardTitle>
        <div className="mt-3 space-y-2">
          {(data?.recent_recommendations ?? []).map((item) => (
            <div key={item.id} className="flex flex-col gap-2 rounded-md border border-[var(--border)] p-3 md:flex-row md:items-center md:justify-between">
              <p className="text-sm">{item.summary}</p>
              <Badge
                label={item.priority}
                variant={
                  item.priority === "high"
                    ? "danger"
                    : item.priority === "medium"
                      ? "warning"
                      : "success"
                }
              />
            </div>
          ))}
          {(data?.recent_recommendations ?? []).length === 0 && (
            <p className="text-sm muted-text">Henuz recommendation kaydi bulunmuyor.</p>
          )}
        </div>
      </Card>
    </div>
  );
}
