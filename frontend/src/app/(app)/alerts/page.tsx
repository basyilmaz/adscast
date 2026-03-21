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
import { AlertEntityGroup, AlertIndexResponse } from "@/lib/types";

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

export default function AlertsPage() {
  const [loading, setLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const alertQuery = useApiQuery<AlertIndexResponse, AlertIndexResponse>("/alerts", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.alerts,
  });
  const data = alertQuery.data;
  const groups = data?.entity_groups ?? [];
  const { error: queryError, isLoading, reload } = alertQuery;

  const evaluateRules = async () => {
    setLoading(true);
    setActionError(null);
    try {
      await apiRequest("/alerts/evaluate", {
        method: "POST",
        requireWorkspace: true,
      });
      invalidateApiCache("/alerts", { requireWorkspace: true });
      invalidateApiCache("/dashboard/overview", { requireWorkspace: true });
      invalidateApiCache("/recommendations", { requireWorkspace: true });
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Rules engine calistirilamadi.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <CardTitle>Uyari Merkezi</CardTitle>
            <p className="mt-2 text-sm muted-text">
              Deterministic rules engine ciktilari artik entity bazli gruplanir; ne oldugu, neden onemli oldugu ve bir sonraki adim ayni yerde gorunur.
            </p>
          </div>
          <Button variant="secondary" onClick={evaluateRules} disabled={loading}>
            {loading ? "Calisiyor..." : "Rules Engine Calistir"}
          </Button>
        </div>
        {queryError || actionError ? <p className="mt-4 text-sm text-[var(--danger)]">{actionError ?? queryError}</p> : null}
      </Card>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card>
          <CardTitle>Acik Uyari</CardTitle>
          <CardValue>{data?.summary.open_total ?? 0}</CardValue>
          <p className="mt-2 text-sm muted-text">Secili workspace icindeki acik kurallar.</p>
        </Card>
        <Card>
          <CardTitle>Kritik / Yuksek</CardTitle>
          <CardValue>{data?.summary.critical_total ?? 0}</CardValue>
          <p className="mt-2 text-sm muted-text">Oncelikli olarak kapanmasi gereken kayitlar.</p>
        </Card>
        <Card>
          <CardTitle>Entity Tipi</CardTitle>
          <CardValue>{data?.summary.entity_types ?? 0}</CardValue>
          <p className="mt-2 text-sm muted-text">Workspace, hesap, kampanya, ad set veya reklam etkisi.</p>
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.1fr_1fr]">
        <NextBestActionsPanel
          title="Once Ele Alinacak Uyarilar"
          items={data?.next_best_actions ?? []}
          emptyText="Su anda acil uyarilar gorunmuyor."
        />

        <Card>
          <CardTitle>En Onemli Sonraki Adim</CardTitle>
          <div className="mt-3 rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-4">
            <p className="text-sm font-semibold">
              {data?.summary.top_recommended_action ?? "Kayitli bir sonraki adim bulunmuyor."}
            </p>
            <p className="mt-2 text-sm muted-text">
              Bu alan acik alertler arasindaki en yuksek oncelikli aksiyon notunu one cikarir.
            </p>
          </div>
        </Card>
      </section>

      {isLoading && !data ? <p className="text-sm muted-text">Uyarilar yukleniyor.</p> : null}

      <section className="space-y-4">
        {groups.map((group: AlertEntityGroup) => (
          <Card key={group.entity_type}>
            <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div>
                <CardTitle>{entityTypeLabel(group.entity_type)}</CardTitle>
                <p className="mt-1 text-sm muted-text">
                  {group.count} kayit, {group.critical_count} kritik/yuksek oncelik
                </p>
              </div>
              <Badge label={group.entity_type} variant="neutral" />
            </div>

            <div className="mt-4 space-y-3">
              {group.items.map((item) => (
                <div key={item.id} className="rounded-lg border border-[var(--border)] p-4">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge label={item.severity} variant={variantFor(item.severity)} />
                    <Badge label={item.status} variant="neutral" />
                    <span className="text-xs muted-text">{item.date_detected ?? "-"}</span>
                  </div>
                  <p className="mt-2 font-semibold">{item.summary}</p>
                  <p className="mt-1 text-sm muted-text">
                    {item.entity_label ?? "Varlik"}
                    {item.context_label ? ` / ${item.context_label}` : ""}
                  </p>
                  <div className="mt-3 grid gap-3 xl:grid-cols-2">
                    <div>
                      <p className="text-sm font-semibold">Neden Onemli?</p>
                      <p className="mt-1 text-sm muted-text">{item.impact_summary}</p>
                    </div>
                    <div>
                      <p className="text-sm font-semibold">Onerilen Aksiyon</p>
                      <p className="mt-1 text-sm">{item.next_step}</p>
                    </div>
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

      {!isLoading && groups.length === 0 ? <p className="text-sm muted-text">Henuz alert yok.</p> : null}
    </div>
  );
}
