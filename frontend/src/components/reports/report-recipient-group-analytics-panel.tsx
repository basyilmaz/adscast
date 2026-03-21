"use client";

import { Badge } from "@/components/ui/badge";
import {
  ReportRecipientGroupAnalyticsItem,
  ReportRecipientGroupAnalyticsSummary,
} from "@/lib/types";

type Props = {
  summary: ReportRecipientGroupAnalyticsSummary | null;
  items: ReportRecipientGroupAnalyticsItem[];
};

export function ReportRecipientGroupAnalyticsPanel({ summary, items }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">Alici grubu analytics verisi henuz olusmadi.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
          <SummaryMetric label="Toplam Grup" value={summary.total_groups} />
          <SummaryMetric label="Hata Ureten Grup" value={summary.groups_with_failures} />
          <SummaryMetric label="Aktif Schedule Grubu" value={summary.active_schedule_groups} />
          <SummaryMetric label="Run Izlenen Grup" value={summary.tracked_run_groups} />
          <SummaryMetric label="Akilli Grup" value={summary.smart_groups} />
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.key} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={sourceLabel(item.source_type, item.source_subtype)} variant="neutral" />
                  <Badge label={item.health_status} variant={healthVariant(item.health_status)} />
                  <Badge label={`${item.run_uses_count} run`} variant="neutral" />
                  <Badge label={`${item.configured_schedules_count} schedule`} variant="neutral" />
                  {item.failed_runs > 0 ? <Badge label={`${item.failed_runs} hata`} variant="danger" /> : null}
                </div>
                <div>
                  <p className="font-semibold">{item.label}</p>
                  <p className="mt-1 text-sm muted-text">{item.health_summary}</p>
                </div>
                <p className="text-xs muted-text">
                  Basari orani: {item.success_rate !== null ? `%${item.success_rate}` : "-"} / Retry: {item.retry_runs} / Son kullanim: {item.last_used_at ?? "-"}
                </p>
                <p className="text-xs muted-text">
                  Aktif schedule: {item.active_schedules_count} / Yayildigi varlik: {item.unique_entities_count}
                </p>
                {item.sample_recipients.length > 0 ? (
                  <p className="text-xs muted-text">Ornek alicilar: {item.sample_recipients.join(", ")}</p>
                ) : null}
              </div>

              <div className="xl:w-[360px]">
                <p className="text-xs font-semibold uppercase tracking-wide muted-text">En Cok Kullanan Varliklar</p>
                <div className="mt-2 space-y-2">
                  {item.entities.map((entity) => (
                    <div key={`${entity.entity_type}:${entity.entity_id}`} className="rounded-md bg-[var(--surface-2)] px-3 py-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge label={entity.entity_type} variant="neutral" />
                        <span className="text-xs muted-text">{entity.uses_count} kullanim</span>
                      </div>
                      <p className="mt-1 text-sm font-semibold">{entity.label}</p>
                      {entity.context_label ? <p className="text-xs muted-text">{entity.context_label}</p> : null}
                    </div>
                  ))}
                  {item.entities.length === 0 ? <p className="text-sm muted-text">Bu grup icin henuz entity izi yok.</p> : null}
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function sourceLabel(sourceType: string, sourceSubtype?: string | null) {
  switch (sourceType) {
    case "preset":
      return "Kayitli Grup";
    case "segment":
      return "Segment";
    case "smart":
      if (sourceSubtype === "company") {
        return "Sirket Akilli Grup";
      }

      if (sourceSubtype === "primary") {
        return "Primary Akilli Grup";
      }

      return "Akilli Grup";
    case "manual":
      return "Manuel Grup";
    default:
      return sourceType;
  }
}

function healthVariant(status: string): "success" | "warning" | "danger" | "neutral" {
  switch (status) {
    case "healthy":
      return "success";
    case "warning":
      return "warning";
    case "critical":
      return "danger";
    default:
      return "neutral";
  }
}

function SummaryMetric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
