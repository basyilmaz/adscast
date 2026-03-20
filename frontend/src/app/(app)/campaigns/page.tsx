"use client";

import Link from "next/link";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { useApiQuery } from "@/hooks/use-api-query";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { prefetchApiResponse } from "@/lib/api-cache";

type CampaignItem = {
  id: string;
  name: string;
  objective: string | null;
  status: string;
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

export default function CampaignListPage() {
  const campaignQuery = useApiQuery<CampaignResponse, CampaignItem[]>("/campaigns", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.campaigns,
    select: (response) => response.data.items ?? [],
  });
  const items = campaignQuery.data ?? [];
  const { error, isLoading } = campaignQuery;

  const prefetchCampaignDetail = (campaignId: string) => {
    void prefetchApiResponse(
      `/campaigns/${campaignId}`,
      {
        requireWorkspace: true,
      },
      {
        ttlMs: QUERY_TTLS.campaignDetail,
      },
    );
  };

  return (
    <Card>
      {error ? <p className="mb-4 text-sm text-[var(--danger)]">{error}</p> : null}
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b border-[var(--border)] text-left">
              <th className="px-3 py-2">Kampanya</th>
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
              <tr key={item.id} className="border-b border-[var(--border)]">
                <td className="px-3 py-2">
                  <Link
                    href={`/campaigns/detail?id=${encodeURIComponent(item.id)}`}
                    className="font-semibold text-[var(--accent)] hover:underline"
                    onMouseEnter={() => prefetchCampaignDetail(item.id)}
                    onFocus={() => prefetchCampaignDetail(item.id)}
                  >
                    {item.name}
                  </Link>
                </td>
                <td className="px-3 py-2">{item.objective ?? "-"}</td>
                <td className="px-3 py-2">${item.spend.toFixed(2)}</td>
                <td className="px-3 py-2">{item.results.toFixed(0)}</td>
                <td className="px-3 py-2">{item.cpa_cpl ? `$${item.cpa_cpl.toFixed(2)}` : "-"}</td>
                <td className="px-3 py-2">{item.ctr.toFixed(2)}%</td>
                <td className="px-3 py-2">${item.cpm.toFixed(2)}</td>
                <td className="px-3 py-2">
                  <Badge label={item.status} variant={item.status === "active" ? "success" : "warning"} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {isLoading && items.length === 0 ? (
        <p className="mt-4 text-sm muted-text">Kampanyalar yukleniyor.</p>
      ) : null}

      {!isLoading && items.length === 0 ? (
        <p className="mt-4 text-sm muted-text">Kampanya verisi bulunamadi.</p>
      ) : null}
    </Card>
  );
}
