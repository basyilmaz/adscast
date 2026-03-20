"use client";

import Link from "next/link";
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

function formatCurrency(value: number) {
  return `$${value.toFixed(2)}`;
}

function formatNumber(value: number) {
  return value.toFixed(value % 1 === 0 ? 0 : 2);
}

function priorityVariant(priority: string) {
  if (priority === "high" || priority === "critical") return "danger" as const;
  if (priority === "medium" || priority === "warning") return "warning" as const;
  if (priority === "healthy" || priority === "success") return "success" as const;
  if (priority === "idle") return "neutral" as const;

  return "neutral" as const;
}

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
      {
        label: "Toplam Harcama",
        value: formatCurrency(data.metrics.total_spend),
        note: "Secili tarih araligindaki toplam medya harcamasi.",
      },
      {
        label: "Toplam Sonuc",
        value: formatNumber(data.metrics.total_results),
        note: "Secili donemde olusan sonuc adedi.",
      },
      {
        label: "CPA / CPL",
        value: formatCurrency(data.metrics.cpa_cpl),
        note: "Bir sonuc icin ortalama maliyet.",
      },
      {
        label: "CTR",
        value: `${data.metrics.ctr.toFixed(2)}%`,
        note: "Reklamlarin tiklanma/etkilesim gucu.",
      },
      {
        label: "CPM",
        value: formatCurrency(data.metrics.cpm),
        note: "Bin gosterim basina maliyet.",
      },
      {
        label: "Frekans",
        value: `${data.metrics.frequency.toFixed(2)}`,
        note: "Ayni kisinin reklami ortalama kac kez gordugu.",
      },
    ];
  }, [data]);

  const quickFacts = useMemo(() => {
    if (!data) return [];

    return [
      `${data.workspace_health.active_accounts}/${data.workspace_health.total_accounts} reklam hesabi aktif`,
      `${data.workspace_health.active_campaigns} aktif kampanya gorunuyor`,
      `${data.workspace_health.campaigns_requiring_attention} kampanya yakin takip istiyor`,
      `${data.workspace_health.open_recommendations} acik operasyon onerisi var`,
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

      <Card className="overflow-hidden">
        <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
          <div>
            <CardTitle>Bugun Ne Oluyor?</CardTitle>
            <p className="mt-3 text-lg font-semibold leading-7">
              {data?.workspace_health.summary ?? "Workspace ozeti yukleniyor."}
            </p>
            <div className="mt-4 flex flex-wrap gap-2">
              {quickFacts.map((fact) => (
                <span
                  key={fact}
                  className="rounded-full bg-[var(--surface-2)] px-3 py-2 text-sm font-medium"
                >
                  {fact}
                </span>
              ))}
            </div>
          </div>

          <div className="rounded-xl border border-[var(--border)] bg-[var(--surface-2)] p-4">
            <p className="text-xs font-semibold uppercase tracking-wide muted-text">Operasyon Durumu</p>
            <div className="mt-3 space-y-3 text-sm">
              <div>
                <p className="muted-text">Aktif Uyari</p>
                <p className="text-xl font-bold">{data?.workspace_health.open_alerts ?? 0}</p>
              </div>
              <div>
                <p className="muted-text">Son Senkron</p>
                <p>{data?.workspace_health.last_synced_at ?? "Bilinmiyor"}</p>
              </div>
              <div>
                <p className="muted-text">Tarih Araligi</p>
                <p>
                  {data?.range.start_date ?? "-"} / {data?.range.end_date ?? "-"}
                </p>
              </div>
            </div>
          </div>
        </div>
      </Card>

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
            <p className="mt-2 text-sm muted-text">{metric.note}</p>
          </Card>
        ))}
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.25fr_1fr]">
        <Card>
          <CardTitle>Hemen Bakilmasi Gerekenler</CardTitle>
          <div className="mt-3 space-y-3">
            {(data?.urgent_actions ?? []).map((item) => (
              <div key={item.id} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label={item.source === "alert" ? "Uyari" : "Oneri"} variant="neutral" />
                  <Badge label={item.priority} variant={priorityVariant(item.priority)} />
                  <span className="text-xs muted-text">{item.detected_at ?? "-"}</span>
                </div>
                <p className="mt-2 font-semibold">{item.title}</p>
                <p className="mt-1 text-sm muted-text">
                  {item.entity_label}
                  {item.context_label ? ` / ${item.context_label}` : ""}
                </p>
                <p className="mt-2 text-sm">{item.detail ?? "Detay kaydi bulunmuyor."}</p>
              </div>
            ))}
            {(data?.urgent_actions ?? []).length === 0 ? (
              <p className="text-sm muted-text">Su anda acil aksiyon gerektiren kayit gorunmuyor.</p>
            ) : null}
          </div>
        </Card>

        <Card>
          <CardTitle>Reklam Hesabi Sagligi</CardTitle>
          <div className="mt-3 space-y-3">
            {(data?.account_health ?? []).map((account) => (
              <div key={account.id} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold">{account.name}</p>
                    <p className="text-xs muted-text">{account.account_id}</p>
                  </div>
                  <Badge label={account.health_status} variant={priorityVariant(account.health_status)} />
                </div>
                <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <p className="muted-text">Aktif Kampanya</p>
                    <p className="font-semibold">{account.active_campaigns}</p>
                  </div>
                  <div>
                    <p className="muted-text">Acil Uyari</p>
                    <p className="font-semibold">{account.open_alerts}</p>
                  </div>
                  <div>
                    <p className="muted-text">Harcama</p>
                    <p className="font-semibold">{formatCurrency(account.spend)}</p>
                  </div>
                  <div>
                    <p className="muted-text">Sonuc</p>
                    <p className="font-semibold">{formatNumber(account.results)}</p>
                  </div>
                </div>
                <p className="mt-3 text-sm muted-text">{account.health_summary}</p>
              </div>
            ))}
            {(data?.account_health ?? []).length === 0 ? (
              <p className="text-sm muted-text">Hesap sagligi icin gosterilecek veri bulunmuyor.</p>
            ) : null}
            <Link href="/ad-accounts" className="inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
              Tum reklam hesaplarini ac
            </Link>
          </div>
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.4fr_1fr]">
        <Card>
          <CardTitle>Aktif Kampanyalar</CardTitle>
          <div className="mt-3 overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] text-left">
                  <th className="px-3 py-2">Kampanya</th>
                  <th className="px-3 py-2">Hesap</th>
                  <th className="px-3 py-2">Durum</th>
                  <th className="px-3 py-2">Harcama</th>
                  <th className="px-3 py-2">Sonuc</th>
                  <th className="px-3 py-2">Uyari</th>
                </tr>
              </thead>
              <tbody>
                {(data?.active_campaigns ?? []).map((campaign) => (
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
                      <p className="font-medium">{campaign.account_name}</p>
                      <p className="text-xs muted-text">{campaign.account_external_id ?? "-"}</p>
                    </td>
                    <td className="px-3 py-3">
                      <div className="flex flex-col gap-2">
                        <Badge label={campaign.status} variant={campaign.status === "active" ? "success" : "warning"} />
                        <Badge label={campaign.health_status} variant={priorityVariant(campaign.health_status)} />
                      </div>
                    </td>
                    <td className="px-3 py-3">{formatCurrency(campaign.spend)}</td>
                    <td className="px-3 py-3">{formatNumber(campaign.results)}</td>
                    <td className="px-3 py-3">
                      <p className="font-semibold">{campaign.open_alerts}</p>
                      <p className="mt-1 text-xs muted-text">{campaign.health_summary}</p>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {(data?.active_campaigns ?? []).length === 0 ? (
            <p className="mt-3 text-sm muted-text">Aktif kampanya gorunmuyor.</p>
          ) : null}
        </Card>

        <Card>
          <CardTitle>Harcama / Sonuc Trendi</CardTitle>
          <div className="mt-3">
            <SpendResultChart data={data?.trend ?? []} />
          </div>
          <div className="mt-4 space-y-3 text-sm">
            <div>
              <p className="muted-text">En Iyi Kampanya</p>
              <p className="font-semibold">{data?.best_campaign?.name ?? "-"}</p>
            </div>
            <div>
              <p className="muted-text">En Zayif Kampanya</p>
              <p className="font-semibold">{data?.worst_campaign?.name ?? "-"}</p>
            </div>
            <div>
              <p className="muted-text">Son Senkron</p>
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
