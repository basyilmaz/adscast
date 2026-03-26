"use client";

import { Badge } from "@/components/ui/badge";
import {
  ReportFailureResolutionActionAnalyticsItem,
  ReportFailureResolutionActionAnalyticsSummary,
} from "@/lib/types";

type Props = {
  summary: ReportFailureResolutionActionAnalyticsSummary | null;
  items: ReportFailureResolutionActionAnalyticsItem[];
};

export function ReportFailureResolutionActionAnalyticsPanel({ summary, items }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">Failure resolution aksiyonu analytics verisi henuz olusmadi.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <SummaryMetric label="Izlenen Aksiyon" value={summary.observed_actions} />
          <SummaryMetric label="API Denemesi" value={summary.api_attempts} />
          <SummaryMetric label="Route Kullanimi" value={summary.route_interactions} />
          <SummaryMetric label="Basarili" value={summary.successful_executions} />
          <SummaryMetric label="Kismi" value={summary.partial_executions} />
          <SummaryMetric label="Basarisiz" value={summary.failed_executions} />
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.action_code} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={item.label} variant={variantForSeverity(item.severity)} />
                  <Badge label={actionKindLabel(item.action_kind)} variant="neutral" />
                  <Badge label={`${item.observed_uses} kullanim`} variant="neutral" />
                  {item.api_attempts > 0 ? (
                    <Badge label={`%${formatRateValue(item.success_rate)} basari`} variant={successRateVariant(item.success_rate)} />
                  ) : null}
                </div>

                <p className="text-sm muted-text">{item.outcome_summary}</p>
                <p className="text-xs muted-text">{item.health_summary}</p>
                <p className="text-xs muted-text">
                  Son tiklama: {item.last_tracked_at ?? "-"} / Son execution: {item.last_executed_at ?? "-"}
                </p>
                {item.top_reason_code ? (
                  <p className="text-xs muted-text">
                    En cok bagli neden: {item.top_reason_code} ({item.top_reason_count})
                  </p>
                ) : null}
              </div>

              <div className="xl:w-[360px]">
                <p className="text-xs font-semibold uppercase tracking-wide muted-text">En Cok Gozlenen Varliklar</p>
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
                  {item.entities.length === 0 ? <p className="text-sm muted-text">Bu aksiyon icin entity izi henuz yok.</p> : null}
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function actionKindLabel(value: string): string {
  switch (value) {
    case "api":
      return "API";
    case "route":
      return "Yonlendirme";
    case "focus_tab":
      return "Sekme Odagi";
    default:
      return value;
  }
}

function variantForSeverity(value: string): "success" | "warning" | "danger" | "neutral" {
  if (value === "critical" || value === "high") return "danger";
  if (value === "warning" || value === "medium") return "warning";
  if (value === "success") return "success";

  return "neutral";
}

function successRateVariant(value: number | null): "success" | "warning" | "danger" | "neutral" {
  if (value === null) {
    return "neutral";
  }

  if (value >= 80) {
    return "success";
  }

  if (value >= 40) {
    return "warning";
  }

  return "danger";
}

function formatRateValue(value: number | null): string {
  return value === null ? "-" : value.toFixed(1);
}

function SummaryMetric({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
