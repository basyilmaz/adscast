"use client";

import { Badge } from "@/components/ui/badge";
import { Card, CardTitle } from "@/components/ui/card";
import {
  ReportRecipientGroupAlignmentItem,
  ReportRecipientGroupAlignmentSummary,
  ReportRecipientGroupAnalyticsItem,
  ReportRecipientGroupAnalyticsSummary,
} from "@/lib/types";

type Props = {
  analyticsSummary: ReportRecipientGroupAnalyticsSummary;
  analyticsItems: ReportRecipientGroupAnalyticsItem[];
  alignmentSummary: ReportRecipientGroupAlignmentSummary;
  alignmentItems: ReportRecipientGroupAlignmentItem[];
  entityLabel: string;
};

export function ReportRecipientGroupEntityInsightsPanel({
  analyticsSummary,
  analyticsItems,
  alignmentSummary,
  alignmentItems,
  entityLabel,
}: Props) {
  return (
    <Card>
      <CardTitle>Alici Grubu Icgorusu</CardTitle>
      <p className="mt-2 text-sm muted-text">
        {entityLabel} icin rapor teslim gruplarinin kullanim, basari ve oneriden sapma gorunumu.
      </p>

      <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Metric label="Izlenen Grup" value={analyticsSummary.total_groups} />
        <Metric label="Fail Ureten Grup" value={analyticsSummary.groups_with_failures} />
        <Metric label="Izlenen Karar" value={alignmentSummary.tracked_decisions} />
        <Metric label="Override" value={alignmentSummary.overridden_decisions} />
      </div>

      <div className="mt-4 grid gap-4 xl:grid-cols-2">
        <div className="rounded-lg border border-[var(--border)] p-4">
          <p className="text-sm font-semibold">En Cok Kullanilan Gruplar</p>
          <div className="mt-3 space-y-3">
            {analyticsItems.slice(0, 3).map((item) => (
              <div key={item.key} className="rounded-md border border-[var(--border)] p-3">
                <div className="flex flex-wrap gap-2">
                  <Badge label={item.label} variant="neutral" />
                  <Badge label={`${item.run_uses_count} run`} variant="neutral" />
                  <Badge label={item.health_status} variant={item.health_status === "critical" ? "danger" : item.health_status === "warning" ? "warning" : "success"} />
                </div>
                <p className="mt-2 text-sm muted-text">{item.health_summary}</p>
                <p className="mt-2 text-xs muted-text">
                  Basari: {item.success_rate ?? 0}% / Fail: {item.failed_runs} / Entity: {item.unique_entities_count}
                </p>
              </div>
            ))}
            {analyticsItems.length === 0 ? <p className="text-sm muted-text">Bu kayit icin teslim grubu kullanimi yok.</p> : null}
          </div>
        </div>

        <div className="rounded-lg border border-[var(--border)] p-4">
          <p className="text-sm font-semibold">Oneri - Secim Sapmasi</p>
          <div className="mt-3 space-y-3">
            {alignmentItems.slice(0, 3).map((item) => (
              <div key={item.schedule_id} className="rounded-md border border-[var(--border)] p-3">
                <div className="flex flex-wrap gap-2">
                  <Badge label={item.alignment.status} variant={item.alignment.status === "override" ? "warning" : item.alignment.status === "aligned" ? "success" : "neutral"} />
                  <Badge label={item.cadence_label} variant="neutral" />
                </div>
                <p className="mt-2 text-sm muted-text">{item.alignment.reason}</p>
                <p className="mt-2 text-xs muted-text">
                  Secilen: {item.selected_group?.name ?? "-"} / Onerilen: {item.recommended_group?.name ?? "-"}
                </p>
              </div>
            ))}
            {alignmentItems.length === 0 ? <p className="text-sm muted-text">Bu kayit icin alignment karari yok.</p> : null}
          </div>
        </div>
      </div>
    </Card>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
