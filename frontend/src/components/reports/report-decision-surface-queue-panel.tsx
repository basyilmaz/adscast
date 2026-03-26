"use client";

import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card, CardTitle } from "@/components/ui/card";
import { ReportDecisionSurfaceQueueItem, ReportDecisionSurfaceQueueSummary } from "@/lib/types";

type Props = {
  summary: ReportDecisionSurfaceQueueSummary | null;
  items: ReportDecisionSurfaceQueueItem[];
  routeBuilder: (route: string) => string;
};

export function ReportDecisionSurfaceQueuePanel({ summary, items, routeBuilder }: Props) {
  return (
    <Card>
      <CardTitle>Operasyon Karar Kuyrugu</CardTitle>
      <p className="mt-2 text-sm muted-text">
        Detail ekranlarinda isaretlenen featured fix, retry rehberi ve profil onerisi durumlarini workspace genelinde tek listede izleyin.
      </p>

      <div className="mt-3 flex flex-wrap gap-3 text-sm muted-text">
        <span>Takipte entity: {summary?.tracked_entities ?? 0}</span>
        <span>Acik yuzey: {summary?.open_items ?? 0}</span>
        <span>Beklemede: {summary?.pending_items ?? 0}</span>
        <span>Ertelenen: {summary?.deferred_items ?? 0}</span>
        <span>Tamamlanan: {summary?.completed_items ?? 0}</span>
      </div>

      <div className="mt-4 space-y-3">
        {items.map((item) => (
          <div key={`${item.entity_type}:${item.entity_id}:${item.surface_key}`} className="rounded-lg border border-[var(--border)] p-4">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label={item.surface_label} variant="neutral" />
                  <Badge label={item.status_label} variant={variantForStatus(item.status)} />
                  <Badge label={item.entity_type} variant="neutral" />
                </div>
                <p className="mt-3 text-sm font-semibold">{item.entity_label ?? "Bilinmeyen varlik"}</p>
                <p className="mt-1 text-xs muted-text">
                  {item.context_label ?? "-"}
                  {item.updated_at ? ` / Son guncelleme: ${item.updated_at}` : " / Henuz operator isareti yok"}
                  {item.updated_by_name ? ` / ${item.updated_by_name}` : ""}
                </p>
              </div>

              <div className="flex flex-wrap gap-2">
                {item.route ? (
                  <Link
                    href={routeBuilder(item.route)}
                    className="inline-flex h-10 items-center rounded-md border border-[var(--border)] px-4 text-sm font-semibold hover:bg-[var(--surface-2)]"
                  >
                    Detaya git
                  </Link>
                ) : null}
              </div>
            </div>
          </div>
        ))}

        {items.length === 0 ? (
          <p className="text-sm muted-text">
            Henuz reports merkezine tasinmis operator takip kaydi yok. Detail ekranlarindaki karar yuzeyleri isaretlendikce bu kuyruk dolacak.
          </p>
        ) : null}
      </div>
    </Card>
  );
}

function variantForStatus(status: string) {
  if (status === "completed") return "success" as const;
  if (status === "deferred") return "warning" as const;
  if (status === "reviewed") return "neutral" as const;

  return "neutral" as const;
}
