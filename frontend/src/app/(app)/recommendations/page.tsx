"use client";

import Link from "next/link";
import { useState } from "react";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { NextBestActionsPanel } from "@/components/operations/next-best-actions-panel";
import { apiRequest } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";
import { RecommendationEntityGroup, RecommendationIndexResponse } from "@/lib/types";

type ViewMode = "operator" | "client";

function variantFor(value: string) {
  if (value === "critical" || value === "high") return "danger" as const;
  if (value === "warning" || value === "medium") return "warning" as const;
  if (value === "low") return "success" as const;

  return "neutral" as const;
}

function entityTypeLabel(type: string) {
  return (
    {
      workspace: "Workspace",
      account: "Reklam Hesaplari",
      campaign: "Kampanyalar",
      ad_set: "Ad Setler",
      ad: "Reklamlar",
    }[type] ?? "Diger"
  );
}

export default function RecommendationsPage() {
  const [loading, setLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [viewMode, setViewMode] = useState<ViewMode>("operator");
  const recommendationQuery = useApiQuery<RecommendationIndexResponse, RecommendationIndexResponse>("/recommendations", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.recommendations,
  });
  const data = recommendationQuery.data;
  const groups = data?.entity_groups ?? [];
  const { error: queryError, isLoading, reload } = recommendationQuery;

  const generate = async () => {
    setLoading(true);
    setActionError(null);
    try {
      await apiRequest("/recommendations/generate", {
        method: "POST",
        requireWorkspace: true,
      });
      invalidateApiCache("/recommendations", { requireWorkspace: true });
      invalidateApiCache("/dashboard/overview", { requireWorkspace: true });
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "AI onerisi olusturulamadi.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <CardTitle>Oneri Merkezi</CardTitle>
            <p className="mt-2 text-sm muted-text">
              Rules + AI pipeline ciktilari operator ve musteri dilinde ayrisiyor. Her kayit hangi entity icin uretildigi ve sonraki test notu ile gelir.
            </p>
          </div>
          <Button onClick={generate} disabled={loading}>
            {loading ? "Olusturuluyor..." : "AI Onerisi Uret"}
          </Button>
        </div>
        {queryError || actionError ? <p className="mt-4 text-sm text-[var(--danger)]">{actionError ?? queryError}</p> : null}
      </Card>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card>
          <CardTitle>Acik Oneri</CardTitle>
          <CardValue>{data?.summary.open_total ?? 0}</CardValue>
          <p className="mt-2 text-sm muted-text">Kayitli operator aksiyonlari.</p>
        </Card>
        <Card>
          <CardTitle>Yuksek Oncelik</CardTitle>
          <CardValue>{data?.summary.high_priority_total ?? 0}</CardValue>
          <p className="mt-2 text-sm muted-text">Hemen ele alinmasi gereken AI/rules ciktilari.</p>
        </Card>
        <Card>
          <CardTitle>Manual Review</CardTitle>
          <CardValue>{data?.summary.manual_review_total ?? 0}</CardValue>
          <p className="mt-2 text-sm muted-text">Insan onayi ve operator karari gerektiren kayitlar.</p>
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.1fr_1fr]">
        <NextBestActionsPanel
          title="Siradaki En Degerli Oneriler"
          items={data?.next_best_actions ?? []}
          emptyText="Kayitli acik oneri yok."
        />

        <Card>
          <CardTitle>Gorunum Modu</CardTitle>
          <div className="mt-3 flex flex-wrap gap-2">
            <Button
              type="button"
              variant={viewMode === "operator" ? "primary" : "secondary"}
              size="sm"
              onClick={() => setViewMode("operator")}
            >
              Operator View
            </Button>
            <Button
              type="button"
              variant={viewMode === "client" ? "primary" : "secondary"}
              size="sm"
              onClick={() => setViewMode("client")}
            >
              Client View
            </Button>
          </div>
          <p className="mt-3 text-sm muted-text">
            Operator gorunumu ic ekip kararlarini, client gorunumu ise musteriye aktarilabilir sade aciklamayi one cikarir.
          </p>
        </Card>
      </section>

      {isLoading && !data ? <p className="text-sm muted-text">Oneriler yukleniyor.</p> : null}

      <section className="space-y-4">
        {groups.map((group: RecommendationEntityGroup) => (
          <Card key={group.entity_type}>
            <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div>
                <CardTitle>{entityTypeLabel(group.entity_type)}</CardTitle>
                <p className="mt-1 text-sm muted-text">
                  {group.count} kayit, {group.high_priority_count} yuksek oncelik
                </p>
              </div>
              <Badge label={group.entity_type} variant="neutral" />
            </div>

            <div className="mt-4 space-y-3">
              {group.items.map((item) => (
                <div key={item.id} className="rounded-lg border border-[var(--border)] p-4">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge label={item.priority} variant={variantFor(item.priority)} />
                    <Badge label={item.action_status.label} variant="neutral" />
                    <Badge label={item.source} variant="neutral" />
                    <span className="text-xs muted-text">{item.generated_at ?? "-"}</span>
                  </div>
                  <p className="mt-2 font-semibold">{item.summary}</p>
                  <p className="mt-1 text-sm muted-text">
                    {item.entity_label ?? "Varlik"}
                    {item.context_label ? ` / ${item.context_label}` : ""}
                  </p>
                  <div className="mt-3 grid gap-3 xl:grid-cols-2">
                    {viewMode === "operator" ? (
                      <>
                        <div>
                          <p className="text-sm font-semibold">Operator Yorumu</p>
                          <p className="mt-1 text-sm muted-text">{item.operator_view.summary}</p>
                        </div>
                        <div>
                          <p className="text-sm font-semibold">Sonraki Test</p>
                          <p className="mt-1 text-sm">{item.operator_view.next_test ?? "-"}</p>
                          <p className="mt-2 text-xs muted-text">
                            Butce: {item.operator_view.budget_note ?? "-"} | Kreatif: {item.operator_view.creative_note ?? "-"}
                          </p>
                        </div>
                      </>
                    ) : (
                      <>
                        <div>
                          <p className="text-sm font-semibold">{item.client_view.headline}</p>
                          <p className="mt-1 text-sm muted-text">{item.client_view.summary}</p>
                        </div>
                        <div>
                          <p className="text-sm font-semibold">Durum</p>
                          <p className="mt-1 text-sm muted-text">{item.action_status.label}</p>
                          <p className="mt-2 text-xs muted-text">
                            Bu adim operator tarafinda manuel review gerektirir: {item.action_status.manual_review_required ? "Evet" : "Hayir"}
                          </p>
                        </div>
                      </>
                    )}
                  </div>
                  {item.route ? (
                    <Link href={item.route} className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
                      Ilgili kaydi ac
                    </Link>
                  ) : null}
                </div>
              ))}
            </div>
          </Card>
        ))}
      </section>

      {!isLoading && groups.length === 0 ? <p className="text-sm muted-text">Henuz oneriler bulunmuyor.</p> : null}
    </div>
  );
}
