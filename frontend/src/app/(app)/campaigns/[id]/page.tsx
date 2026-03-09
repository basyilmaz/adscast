"use client";

import { useEffect, useState } from "react";
import dynamic from "next/dynamic";
import { useParams } from "next/navigation";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { apiRequest } from "@/lib/api";

const SpendResultChart = dynamic(
  () => import("@/components/charts/spend-result-chart").then((mod) => mod.SpendResultChart),
  {
    ssr: false,
    loading: () => <div className="h-[280px] w-full rounded-md bg-[var(--surface-2)]" />,
  },
);

type CampaignDetailResponse = {
  data: {
    campaign: {
      id: string;
      name: string;
      objective: string | null;
      status: string;
      meta_campaign_id: string;
    };
    summary: {
      spend: number;
      results: number;
      cpa_cpl: number | null;
      ctr: number;
      cpm: number;
      frequency: number;
    };
    trend: Array<{
      date: string;
      spend: number;
      results: number;
      ctr: number;
      cpm: number;
      frequency: number;
    }>;
    ad_sets: Array<{
      id: string;
      name: string;
      status: string;
      optimization_goal: string | null;
      daily_budget: number | null;
    }>;
    ads: Array<{
      id: string;
      name: string;
      status: string;
      effective_status: string | null;
    }>;
    alerts: Array<{
      id: string;
      code: string;
      severity: string;
      summary: string;
      recommended_action: string | null;
    }>;
  };
};

export default function CampaignDetailPage() {
  const params = useParams<{ id: string }>();
  const [data, setData] = useState<CampaignDetailResponse["data"] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const response = await apiRequest<CampaignDetailResponse>(`/campaigns/${params.id}`, {
          requireWorkspace: true,
        });
        setData(response.data);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Kampanya detayi alinamadi.");
      }
    };

    if (params.id) {
      load();
    }
  }, [params.id]);

  if (error) {
    return <p className="text-sm text-[var(--danger)]">{error}</p>;
  }

  if (!data) {
    return <p className="text-sm muted-text">Yukleniyor...</p>;
  }

  return (
    <div className="space-y-4">
      <Card>
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h3 className="text-xl font-bold">{data.campaign.name}</h3>
            <p className="text-sm muted-text">Objective: {data.campaign.objective ?? "-"}</p>
          </div>
          <Badge label={data.campaign.status} variant={data.campaign.status === "active" ? "success" : "warning"} />
        </div>
      </Card>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card>
          <CardTitle>Toplam Harcama</CardTitle>
          <CardValue>${data.summary.spend.toFixed(2)}</CardValue>
        </Card>
        <Card>
          <CardTitle>Toplam Sonuc</CardTitle>
          <CardValue>{data.summary.results.toFixed(0)}</CardValue>
        </Card>
        <Card>
          <CardTitle>CPA/CPL</CardTitle>
          <CardValue>{data.summary.cpa_cpl ? `$${data.summary.cpa_cpl.toFixed(2)}` : "-"}</CardValue>
        </Card>
      </section>

      <Card>
        <CardTitle>Trend</CardTitle>
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

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <Card>
          <CardTitle>Ad Set Dagilimi</CardTitle>
          <div className="mt-3 space-y-2">
            {data.ad_sets.map((item) => (
              <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
                <p className="font-semibold">{item.name}</p>
                <p className="text-xs muted-text">
                  Goal: {item.optimization_goal ?? "-"} | Gunluk Butce:{" "}
                  {item.daily_budget ? `$${Number(item.daily_budget).toFixed(2)}` : "-"}
                </p>
              </div>
            ))}
            {data.ad_sets.length === 0 && <p className="text-sm muted-text">Ad set bulunmuyor.</p>}
          </div>
        </Card>

        <Card>
          <CardTitle>Kampanya Uyarilari</CardTitle>
          <div className="mt-3 space-y-2">
            {data.alerts.map((item) => (
              <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
                <div className="mb-1 flex items-center justify-between">
                  <p className="font-semibold">{item.summary}</p>
                  <Badge
                    label={item.severity}
                    variant={
                      item.severity === "high"
                        ? "danger"
                        : item.severity === "medium"
                          ? "warning"
                          : "success"
                    }
                  />
                </div>
                <p className="text-xs muted-text">{item.recommended_action ?? "-"}</p>
              </div>
            ))}
            {data.alerts.length === 0 && <p className="text-sm muted-text">Aktif alert bulunmuyor.</p>}
          </div>
        </Card>
      </section>
    </div>
  );
}
