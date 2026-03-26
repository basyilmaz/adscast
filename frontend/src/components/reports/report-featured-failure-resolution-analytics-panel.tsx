"use client";

import { Badge } from "@/components/ui/badge";
import {
  ReportFeaturedFailureResolutionAnalyticsItem,
  ReportFeaturedFailureResolutionAnalyticsSummary,
} from "@/lib/types";

type Props = {
  summary: ReportFeaturedFailureResolutionAnalyticsSummary | null;
  items: ReportFeaturedFailureResolutionAnalyticsItem[];
};

export function ReportFeaturedFailureResolutionAnalyticsPanel({ summary, items }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">One cikan duzeltme kullanimi icin henuz analytics verisi yok.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <SummaryMetric label="Izlenen Karar" value={summary.tracked_interactions} />
          <SummaryMetric label="Oneriye Uyum" value={summary.featured_interactions} />
          <SummaryMetric label="Override" value={summary.override_interactions} />
          <SummaryMetric label="Basarili Oneri" value={summary.successful_featured_executions} />
          <SummaryMetric label="Basarili Override" value={summary.successful_override_executions} />
          <SummaryMetric label="Featured API" value={summary.featured_api_attempts} />
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={`${item.featured_action_code}:${item.reason_code}:${item.featured_status}`} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={item.featured_action_label} variant="success" />
                  <Badge label={item.featured_status_label} variant="neutral" />
                  <Badge label={item.reason_label} variant="warning" />
                  {item.follow_rate !== null ? <Badge label={`%${formatRate(item.follow_rate)} uyum`} variant={rateVariant(item.follow_rate)} /> : null}
                  {item.featured_success_rate !== null ? (
                    <Badge label={`%${formatRate(item.featured_success_rate)} onerilen basari`} variant={rateVariant(item.featured_success_rate)} />
                  ) : null}
                </div>

                <p className="text-sm muted-text">{item.usage_summary}</p>
                <p className="text-xs muted-text">
                  Kaynak: {sourceLabel(item.featured_source)}
                  {item.provider_label ? ` / ${item.provider_label}` : ""}
                  {item.delivery_stage_label ? ` / ${item.delivery_stage_label}` : ""}
                </p>
                <p className="text-xs muted-text">Son gorulum: {item.last_seen_at ?? "-"}</p>
                {item.top_override_action_label ? (
                  <p className="text-xs muted-text">En cok secilen override: {item.top_override_action_label}</p>
                ) : null}
              </div>

              <div className="xl:w-[360px]">
                <div className="grid grid-cols-2 gap-2">
                  <Metric label="Takip" value={item.featured_interactions} />
                  <Metric label="Override" value={item.override_interactions} />
                  <Metric label="Featured Basari" value={item.successful_featured_executions} />
                  <Metric label="Featured Fail" value={item.failed_featured_executions} />
                  <Metric label="Override Basari" value={item.successful_override_executions} />
                  <Metric label="Override Fail" value={item.failed_override_executions} />
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-md bg-[var(--surface-2)] px-3 py-2">
      <p className="text-[11px] font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-1 text-lg font-semibold">{value}</p>
    </div>
  );
}

function SummaryMetric({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}

function rateVariant(value: number): "success" | "warning" | "danger" | "neutral" {
  if (value >= 80) return "success";
  if (value >= 40) return "warning";
  return "danger";
}

function formatRate(value: number): string {
  return value.toFixed(1);
}

function sourceLabel(value: string): string {
  switch (value) {
    case "effectiveness":
      return "Etkinlik Verisi";
    case "retry_policy":
      return "Retry Politikasi";
    case "action_inventory":
      return "Aksiyon Envanteri";
    default:
      return value;
  }
}
