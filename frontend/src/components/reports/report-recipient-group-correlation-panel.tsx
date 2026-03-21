"use client";

import { Badge } from "@/components/ui/badge";
import {
  ReportRecipientGroupCorrelationItem,
  ReportRecipientGroupCorrelationSummary,
} from "@/lib/types";

type Props = {
  summary: ReportRecipientGroupCorrelationSummary | null;
  items: ReportRecipientGroupCorrelationItem[];
};

export function ReportRecipientGroupCorrelationPanel({ summary, items }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">Oneri-teslim korelasyon verisi henuz olusmadi.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <SummaryMetric label="Izlenen Run" value={summary.tracked_runs} />
          <SummaryMetric label="Aligned Basari" value={formatRate(summary.aligned_success_rate)} />
          <SummaryMetric label="Override Basari" value={formatRate(summary.override_success_rate)} />
          <SummaryMetric label="Basari Farki" value={formatDelta(summary.success_rate_gap)} />
          <SummaryMetric label="Oneri Ustun Grup" value={summary.recommendation_outperforming_groups} />
          <SummaryMetric label="Override Ustun Grup" value={summary.override_outperforming_groups} />
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.key} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={sourceLabel(item.source_type, item.source_subtype)} variant="neutral" />
                  <Badge label={correlationLabel(item.correlation_status)} variant={correlationVariant(item.correlation_status)} />
                  <Badge label={`${item.tracked_runs} run`} variant="neutral" />
                  {item.top_override_group_label ? <Badge label={`Override: ${item.top_override_group_label}`} variant="warning" /> : null}
                </div>
                <div>
                  <p className="font-semibold">{item.label}</p>
                  <p className="mt-1 text-sm muted-text">{item.correlation_summary}</p>
                </div>
                <p className="text-xs muted-text">
                  Aligned: {item.aligned_runs} run / %{formatRateValue(item.aligned_success_rate)} basari / {item.aligned_failed_runs} fail
                </p>
                <p className="text-xs muted-text">
                  Override: {item.overridden_runs} run / %{formatRateValue(item.override_success_rate)} basari / {item.override_failed_runs} fail
                </p>
                <p className="text-xs muted-text">
                  Fark: {formatDelta(item.success_rate_delta)} / Son run: {item.last_run_at ?? "-"}
                </p>
              </div>

              <div className="xl:w-[360px]">
                <p className="text-xs font-semibold uppercase tracking-wide muted-text">En Cok Gozlenen Varliklar</p>
                <div className="mt-2 space-y-2">
                  {item.entities.map((entity) => (
                    <div key={`${entity.entity_type}:${entity.entity_id}`} className="rounded-md bg-[var(--surface-2)] px-3 py-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge label={entity.entity_type} variant="neutral" />
                        <span className="text-xs muted-text">{entity.uses_count} run</span>
                      </div>
                      <p className="mt-1 text-sm font-semibold">{entity.label}</p>
                      {entity.context_label ? <p className="text-xs muted-text">{entity.context_label}</p> : null}
                    </div>
                  ))}
                  {item.entities.length === 0 ? <p className="text-sm muted-text">Bu korelasyon icin henuz entity izi yok.</p> : null}
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

function correlationLabel(status: string): string {
  switch (status) {
    case "recommendation_outperforms":
      return "Oneri Daha Basarili";
    case "override_outperforms":
      return "Override Daha Basarili";
    case "aligned_only":
      return "Sadece Oneri";
    case "override_only":
      return "Sadece Override";
    case "neutral":
      return "Notr";
    default:
      return "Veri Zayif";
  }
}

function correlationVariant(status: string): "success" | "warning" | "danger" | "neutral" {
  switch (status) {
    case "recommendation_outperforms":
      return "success";
    case "override_outperforms":
      return "danger";
    case "override_only":
      return "warning";
    default:
      return "neutral";
  }
}

function formatRate(value: number | null): string {
  return value === null ? "-" : `%${formatRateValue(value)}`;
}

function formatRateValue(value: number | null): string {
  return value === null ? "-" : value.toFixed(1);
}

function formatDelta(value: number | null): string {
  if (value === null) {
    return "-";
  }

  const prefix = value > 0 ? "+" : "";

  return `${prefix}${value.toFixed(1)} puan`;
}

function SummaryMetric({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
