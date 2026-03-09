"use client";

import { useEffect, useMemo, useState } from "react";
import dynamic from "next/dynamic";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
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
  const [data, setData] = useState<DashboardOverviewResponse["data"] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiRequest<DashboardOverviewResponse>("/dashboard/overview", {
        requireWorkspace: true,
      });
      setData(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Dashboard verisi alinamadi.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

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
          {loading ? "Veriler yukleniyor..." : "Son senkron verisi gosteriliyor."}
        </div>
        <Button variant="secondary" onClick={loadData}>
          Yenile
        </Button>
      </div>

      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
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
