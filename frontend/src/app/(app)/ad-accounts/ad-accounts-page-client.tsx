"use client";

import Link from "next/link";
import { useMemo } from "react";
import { useSearchParams } from "next/navigation";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { PageBreadcrumbs } from "@/components/layout/page-breadcrumbs";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { PageErrorState } from "@/components/ui/page-state";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { buildApiPathWithFilters, buildHrefWithFilters, GLOBAL_DATE_FILTER_KEYS } from "@/lib/filters";
import { AdAccountListResponse } from "@/lib/types";

function formatCurrency(value: number) {
  return `$${value.toFixed(2)}`;
}

function formatNumber(value: number) {
  return value.toFixed(value % 1 === 0 ? 0 : 2);
}

function statusVariant(status: string) {
  if (status === "critical" || status === "restricted" || status === "lagging") return "danger" as const;
  if (status === "warning" || status === "stale") return "warning" as const;
  if (status === "healthy" || status === "active" || status === "fresh") return "success" as const;

  return "neutral" as const;
}

export default function AdAccountsPage() {
  const searchParams = useSearchParams();
  const {
    data,
    error,
    isLoading,
    isRefreshing,
    reload,
  } = useApiQuery<AdAccountListResponse, AdAccountListResponse["data"]>(
    buildApiPathWithFilters("/meta/ad-accounts", searchParams, GLOBAL_DATE_FILTER_KEYS),
    {
      requestOptions: {
        requireWorkspace: true,
      },
      ttlMs: QUERY_TTLS.adAccounts,
      select: (response) => response.data,
    },
  );

  const statusFilter = searchParams.get("status");
  const filteredAccounts = useMemo(() => {
    const items = data?.data ?? [];

    if (!statusFilter) {
      return items;
    }

    return items.filter((item) => item.status === statusFilter || item.health_status === statusFilter);
  }, [data?.data, statusFilter]);

  const summaryCards = useMemo(() => {
    if (!data) return [];

    const totalSpend = filteredAccounts.reduce((carry, item) => carry + item.spend, 0);
    const totalResults = filteredAccounts.reduce((carry, item) => carry + item.results, 0);
    const activeAccounts = filteredAccounts.filter((item) => item.status === "active").length;
    const accountsRequiringAttention = filteredAccounts.filter(
      (item) => item.health_status === "warning" || item.health_status === "critical",
    ).length;
    const openAlerts = filteredAccounts.reduce((carry, item) => carry + item.open_alerts, 0);

    return [
      {
        label: "Toplam Hesap",
        value: `${filteredAccounts.length}`,
        note: `${activeAccounts} hesap aktif teslim veriyor.`,
      },
      {
        label: "Dikkat Gerektiren",
        value: `${accountsRequiringAttention}`,
        note: "Uyari, kisit veya sonucsuz harcama olan hesaplar.",
      },
      {
        label: "Toplam Harcama",
        value: formatCurrency(totalSpend),
        note: `${data.range.start_date} - ${data.range.end_date}`,
      },
      {
        label: "Toplam Sonuc",
        value: formatNumber(totalResults),
        note: `${openAlerts} acik uyari bulunuyor.`,
      },
    ];
  }, [data, filteredAccounts]);

  const spotlightAccount = useMemo(() => {
    const items = filteredAccounts;

    return items.find((item) => item.health_status === "warning" || item.health_status === "critical") ?? items[0] ?? null;
  }, [filteredAccounts]);

  return (
    <div className="space-y-4">
      <PageBreadcrumbs
        items={[
          { label: "Workspace", href: "/workspaces" },
          { label: "Reklam Hesaplari" },
        ]}
      />

      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="text-sm muted-text">Workspace &gt; Reklam Hesaplari</p>
          <h2 className="text-2xl font-bold">Reklam Hesaplari</h2>
          <p className="text-sm muted-text">
            Her reklam hesabini ayri bir operasyon birimi gibi izleyin; hesap icinden kampanya, uyari ve rapor akisina inin.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-sm muted-text">
            {isLoading ? "Yukleniyor..." : isRefreshing ? "Arka planda yenileniyor..." : "Son hesap ozeti gosteriliyor."}
          </span>
          <Button variant="secondary" onClick={() => void reload()}>
            Yenile
          </Button>
        </div>
      </div>

      {error ? <PageErrorState title="Reklam hesaplari alinamadi" detail={error} /> : null}

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        {isLoading && summaryCards.length === 0
          ? Array.from({ length: 4 }).map((_, index) => (
              <Card key={`ad-account-summary-${index}`} className="min-h-[116px] animate-pulse">
                <CardTitle>Yukleniyor</CardTitle>
                <CardValue>...</CardValue>
              </Card>
            ))
          : null}
        {summaryCards.map((card) => (
          <Card key={card.label}>
            <CardTitle>{card.label}</CardTitle>
            <CardValue>{card.value}</CardValue>
            <p className="mt-2 text-sm muted-text">{card.note}</p>
          </Card>
        ))}
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.2fr_1fr]">
        <Card>
          <CardTitle>Bugun Bu Ekranda Ne Gormelisiniz?</CardTitle>
          <div className="mt-3 space-y-3 text-sm">
            <p>
              Once hangi hesaplarin kritik veya takip isteyen durumda oldugunu kontrol edin. Sonra hesap detayina girerek sadece o hesaba bagli
              kampanya, uyari ve onerileri inceleyin.
            </p>
            <div className="grid gap-3 md:grid-cols-3">
              <div className="rounded-lg border border-[var(--border)] p-3">
                <p className="font-semibold">1. Hesabi Sec</p>
                <p className="mt-1 muted-text">Durum, son senkron ve acik uyari sayisina bak.</p>
              </div>
              <div className="rounded-lg border border-[var(--border)] p-3">
                <p className="font-semibold">2. Kampanyaya In</p>
                <p className="mt-1 muted-text">Sadece o hesaba bagli kampanyalari ve performansini gor.</p>
              </div>
              <div className="rounded-lg border border-[var(--border)] p-3">
                <p className="font-semibold">3. Raporu Hazirla</p>
                <p className="mt-1 muted-text">Hesap detayindaki rapor ozeti ile musteri anlatimini hazirla.</p>
              </div>
            </div>
          </div>
        </Card>

        <Card>
          <CardTitle>Ilk Odak Hesabi</CardTitle>
          {spotlightAccount ? (
            <div className="mt-3 space-y-3">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-lg font-semibold">{spotlightAccount.name}</p>
                  <p className="text-xs muted-text">{spotlightAccount.account_id}</p>
                </div>
                <Badge label={spotlightAccount.health_status} variant={statusVariant(spotlightAccount.health_status)} />
              </div>
              <p className="text-sm">{spotlightAccount.health_summary}</p>
              <div className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <p className="muted-text">Aktif Kampanya</p>
                  <p className="font-semibold">{spotlightAccount.active_campaigns}</p>
                </div>
                <div>
                  <p className="muted-text">Acik Uyari</p>
                  <p className="font-semibold">{spotlightAccount.open_alerts}</p>
                </div>
                <div>
                  <p className="muted-text">Harcama</p>
                  <p className="font-semibold">{formatCurrency(spotlightAccount.spend)}</p>
                </div>
                <div>
                  <p className="muted-text">Sonuc</p>
                  <p className="font-semibold">{formatNumber(spotlightAccount.results)}</p>
                </div>
              </div>
              <Link
                href={buildHrefWithFilters(
                  `/ad-accounts/detail?id=${encodeURIComponent(spotlightAccount.id)}`,
                  searchParams,
                  GLOBAL_DATE_FILTER_KEYS,
                )}
                className="inline-flex text-sm font-semibold text-[var(--accent)] hover:underline"
              >
                Hesap detayina git
              </Link>
            </div>
          ) : (
            <p className="mt-3 text-sm muted-text">Henuz gosterilecek reklam hesabi bulunmuyor.</p>
          )}
        </Card>
      </section>

      <Card>
        <CardTitle>Hesap Operasyon Merkezi</CardTitle>
        <div className="mt-3 overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--border)] text-left">
                <th className="px-3 py-2">Hesap</th>
                <th className="px-3 py-2">Saglik</th>
                <th className="px-3 py-2">Aktif Kampanya</th>
                <th className="px-3 py-2">Harcama</th>
                <th className="px-3 py-2">Sonuc</th>
                <th className="px-3 py-2">CTR / CPM</th>
                <th className="px-3 py-2">Uyari / Oneri</th>
                <th className="px-3 py-2">Son Senkron</th>
              </tr>
            </thead>
            <tbody>
              {filteredAccounts.map((item) => (
                <tr key={item.id} className="border-b border-[var(--border)] align-top">
                  <td className="px-3 py-3">
                    <Link
                      href={buildHrefWithFilters(
                        `/ad-accounts/detail?id=${encodeURIComponent(item.id)}`,
                        searchParams,
                        GLOBAL_DATE_FILTER_KEYS,
                      )}
                      className="font-semibold text-[var(--accent)] hover:underline"
                    >
                      {item.name}
                    </Link>
                    <p className="mt-1 text-xs muted-text">
                      {item.account_id}
                      {item.currency ? ` / ${item.currency}` : ""}
                    </p>
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex flex-col gap-2">
                      <Badge label={item.status} variant={statusVariant(item.status)} />
                      <Badge label={item.health_status} variant={statusVariant(item.health_status)} />
                    </div>
                    <p className="mt-2 text-xs muted-text">{item.health_summary}</p>
                  </td>
                  <td className="px-3 py-3">
                    <p className="font-semibold">{item.active_campaigns}</p>
                    <p className="text-xs muted-text">Toplam {item.total_campaigns}</p>
                  </td>
                  <td className="px-3 py-3">{formatCurrency(item.spend)}</td>
                  <td className="px-3 py-3">{formatNumber(item.results)}</td>
                  <td className="px-3 py-3">
                    <p className="font-semibold">CTR {item.ctr.toFixed(2)}%</p>
                    <p className="text-xs muted-text">CPM {formatCurrency(item.cpm)}</p>
                  </td>
                  <td className="px-3 py-3">
                    <p className="font-semibold">{item.open_alerts} uyari</p>
                    <p className="text-xs muted-text">{item.open_recommendations} acik oneri</p>
                  </td>
                  <td className="px-3 py-3">
                    <Badge label={item.sync_status} variant={statusVariant(item.sync_status)} />
                    <p className="mt-2 text-xs muted-text">{item.last_synced_at ?? "Bilinmiyor"}</p>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {isLoading && filteredAccounts.length === 0 ? (
          <p className="mt-4 text-sm muted-text">Reklam hesaplari yukleniyor.</p>
        ) : null}
        {!isLoading && filteredAccounts.length === 0 ? (
          <p className="mt-4 text-sm muted-text">Bu workspace icin henuz reklam hesabi bulunmuyor.</p>
        ) : null}
      </Card>
    </div>
  );
}
