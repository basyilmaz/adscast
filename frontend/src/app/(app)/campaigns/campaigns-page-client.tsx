"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { PageBreadcrumbs } from "@/components/layout/page-breadcrumbs";
import { PageEmptyState, PageErrorState, PageLoadingState } from "@/components/ui/page-state";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { prefetchApiResponse } from "@/lib/api-cache";
import { buildApiPathWithFilters, buildHrefWithFilters, GLOBAL_CAMPAIGN_FILTER_KEYS, GLOBAL_DATE_FILTER_KEYS } from "@/lib/filters";

type CampaignItem = {
  id: string;
  name: string;
  objective: string | null;
  status: string;
  ad_account_id: string | null;
  ad_account_name: string | null;
  ad_account_external_id: string | null;
  spend: number;
  results: number;
  cpa_cpl: number | null;
  ctr: number;
  cpm: number;
};

type CampaignResponse = {
  data: {
    items: CampaignItem[];
  };
};

function badgeVariant(status: string) {
  return status === "active" ? "success" : "warning";
}

export default function CampaignListPage() {
  const searchParams = useSearchParams();
  const path = buildApiPathWithFilters("/campaigns", searchParams, GLOBAL_CAMPAIGN_FILTER_KEYS);
  const campaignQuery = useApiQuery<CampaignResponse, CampaignItem[]>(path, {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.campaigns,
    select: (response) => response.data.items ?? [],
  });
  const items = campaignQuery.data ?? [];
  const { error, isLoading } = campaignQuery;

  const activeCount = items.filter((item) => item.status === "active").length;

  const prefetchCampaignDetail = (campaignId: string) => {
    const detailPath = buildApiPathWithFilters(
      `/campaigns/${campaignId}`,
      searchParams,
      GLOBAL_DATE_FILTER_KEYS,
    );

    void prefetchApiResponse(
      detailPath,
      {
        requireWorkspace: true,
      },
      {
        ttlMs: QUERY_TTLS.campaignDetail,
      },
    );
  };

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <PageBreadcrumbs
          items={[
            { label: "Workspace", href: "/workspaces" },
            { label: "Kampanyalar" },
          ]}
        />
        <div>
          <h2 className="text-2xl font-bold">Kampanyalar</h2>
          <p className="text-sm muted-text">
            Global filtrelerle kampanya listesini daraltin, sonra ilgili kampanyadan ad set ve reklam seviyesine inin.
          </p>
        </div>
      </div>

      <section className="grid gap-4 md:grid-cols-3">
        <Card>
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Toplam Kampanya</p>
          <p className="mt-3 text-3xl font-extrabold">{items.length}</p>
          <p className="mt-2 text-sm muted-text">Filtrelenmis liste boyutu.</p>
        </Card>
        <Card>
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Aktif Kampanya</p>
          <p className="mt-3 text-3xl font-extrabold">{activeCount}</p>
          <p className="mt-2 text-sm muted-text">Durumu `active` olan kayitlar.</p>
        </Card>
        <Card>
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Filtre Durumu</p>
          <p className="mt-3 text-sm font-semibold">
            {searchParams.toString() ? "Global filtreler uygulanmis durumda." : "Varsayilan tarih baglami kullaniliyor."}
          </p>
          <p className="mt-2 text-sm muted-text">Tarih, hesap, objective ve durum filtreleri bu sayfada etkili.</p>
        </Card>
      </section>

      {error ? <PageErrorState title="Kampanya verisi alinamadi" detail={error} /> : null}
      {isLoading && items.length === 0 ? (
        <PageLoadingState title="Kampanyalar yukleniyor" detail="Liste ve filtrelenmis operasyon gorunumu hazirlaniyor." />
      ) : null}

      {!isLoading && !error && items.length === 0 ? (
        <PageEmptyState
          title="Filtreye uyan kampanya bulunmuyor"
          detail="Tarih araligini veya kampanya filtrelerini gevsetip tekrar deneyin."
        />
      ) : null}

      {items.length > 0 ? (
        <Card>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] text-left">
                  <th className="px-3 py-2">Kampanya</th>
                  <th className="px-3 py-2">Hesap</th>
                  <th className="px-3 py-2">Hedef</th>
                  <th className="px-3 py-2">Harcama</th>
                  <th className="px-3 py-2">Sonuc</th>
                  <th className="px-3 py-2">CPA/CPL</th>
                  <th className="px-3 py-2">CTR</th>
                  <th className="px-3 py-2">CPM</th>
                  <th className="px-3 py-2">Durum</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id} className="border-b border-[var(--border)] align-top">
                    <td className="px-3 py-3">
                      <Link
                        href={buildHrefWithFilters(
                          `/campaigns/detail?id=${encodeURIComponent(item.id)}`,
                          searchParams,
                          GLOBAL_DATE_FILTER_KEYS,
                        )}
                        className="font-semibold text-[var(--accent)] hover:underline"
                        onMouseEnter={() => prefetchCampaignDetail(item.id)}
                        onFocus={() => prefetchCampaignDetail(item.id)}
                      >
                        {item.name}
                      </Link>
                    </td>
                    <td className="px-3 py-3">
                      <p className="font-medium">{item.ad_account_name ?? "-"}</p>
                      <p className="text-xs muted-text">{item.ad_account_external_id ?? "-"}</p>
                    </td>
                    <td className="px-3 py-3">{item.objective ?? "-"}</td>
                    <td className="px-3 py-3">${item.spend.toFixed(2)}</td>
                    <td className="px-3 py-3">{item.results.toFixed(0)}</td>
                    <td className="px-3 py-3">{item.cpa_cpl ? `$${item.cpa_cpl.toFixed(2)}` : "-"}</td>
                    <td className="px-3 py-3">{item.ctr.toFixed(2)}%</td>
                    <td className="px-3 py-3">${item.cpm.toFixed(2)}</td>
                    <td className="px-3 py-3">
                      <Badge label={item.status} variant={badgeVariant(item.status)} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      ) : null}
    </div>
  );
}
