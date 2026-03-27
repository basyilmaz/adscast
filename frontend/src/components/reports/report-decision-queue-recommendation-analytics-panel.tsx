"use client";

import { Badge } from "@/components/ui/badge";
import {
  ReportDecisionQueueRecommendationAnalyticsItem,
  ReportDecisionQueueRecommendationAnalyticsSummary,
} from "@/lib/types";

type Props = {
  summary: ReportDecisionQueueRecommendationAnalyticsSummary | null;
  items: ReportDecisionQueueRecommendationAnalyticsItem[];
};

export function ReportDecisionQueueRecommendationAnalyticsPanel({ summary, items }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">Queue onerileri icin henuz analytics verisi olusmadi.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <SummaryMetric label="Izlenen Oneri" value={summary.tracked_recommendations} />
          <SummaryMetric label="Secim" value={summary.selection_only_recommendations} />
          <SummaryMetric label="Uygulama" value={summary.applied_recommendations} />
          <SummaryMetric label="Basarili" value={summary.successful_applications} />
          <SummaryMetric label="Kismi" value={summary.partial_applications} />
          <SummaryMetric label="Basarisiz" value={summary.failed_applications} />
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.recommendation_code} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={item.label} variant={variantForGuidance(item.guidance_variant)} />
                  {item.suggested_status_label ? <Badge label={item.suggested_status_label} variant="neutral" /> : null}
                  <Badge label={`${item.tracked_interactions} izleme`} variant="neutral" />
                  {item.item_success_rate !== null ? (
                    <Badge label={`%${formatRateValue(item.item_success_rate)} kayit basarisi`} variant={rateVariant(item.item_success_rate)} />
                  ) : null}
                </div>

                <p className="text-sm muted-text">{item.guidance_message ?? item.outcome_summary}</p>
                <p className="text-xs muted-text">{item.health_summary}</p>
                <p className="text-xs muted-text">
                  Son izleme: {item.last_tracked_at ?? "-"}
                  {item.top_priority_group_label ? ` / Oncelik grubu: ${item.top_priority_group_label}` : ""}
                  {item.dominant_reason_code ? ` / Baskin neden: ${item.dominant_reason_code}` : ""}
                </p>
                <div className="flex flex-wrap gap-2 text-xs muted-text">
                  <span>Secim: {item.selection_only_interactions}</span>
                  <span>Uygulama: {item.applied_interactions}</span>
                  <span>Hedef kayit: {item.total_target_items}</span>
                  <span>Denenen: {item.total_attempted_items}</span>
                  <span>Basarili kayit: {item.total_successful_items}</span>
                  <span>Hatali kayit: {item.total_failed_items}</span>
                </div>
              </div>

              <div className="xl:w-[360px]">
                <p className="text-xs font-semibold uppercase tracking-wide muted-text">En Cok Etkilenen Varliklar</p>
                <div className="mt-2 space-y-2">
                  {item.entities.map((entity) => (
                    <div key={`${entity.entity_type}:${entity.entity_id}:${entity.surface_key}`} className="rounded-md bg-[var(--surface-2)] px-3 py-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge label={entityTypeLabel(entity.entity_type)} variant="neutral" />
                        <Badge label={surfaceLabel(entity.surface_key)} variant="neutral" />
                        <span className="text-xs muted-text">{entity.uses_count} iz</span>
                      </div>
                      <p className="mt-1 text-sm font-semibold">{entity.label ?? "Bilinmeyen varlik"}</p>
                      {entity.context_label ? <p className="text-xs muted-text">{entity.context_label}</p> : null}
                    </div>
                  ))}
                  {item.entities.length === 0 ? <p className="text-sm muted-text">Entity baglami henuz olusmadi.</p> : null}
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function variantForGuidance(value: string): "success" | "warning" | "danger" | "neutral" {
  if (value === "success") return "success";
  if (value === "danger") return "danger";
  if (value === "warning") return "warning";

  return "neutral";
}

function rateVariant(value: number | null): "success" | "warning" | "danger" | "neutral" {
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

function entityTypeLabel(value: string): string {
  if (value === "account") return "Reklam Hesabi";
  if (value === "campaign") return "Kampanya";

  return value;
}

function surfaceLabel(value: string): string {
  if (value === "featured_fix") return "Hizli Duzeltme";
  if (value === "retry") return "Retry Rehberi";
  if (value === "profile") return "Profil Onerisi";

  return value;
}

function SummaryMetric({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
