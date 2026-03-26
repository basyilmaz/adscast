"use client";

import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import {
  ReportFeaturedFailureResolutionDecisionItem,
  ReportFeaturedFailureResolutionDecisionSummary,
} from "@/lib/types";

type Props = {
  summary: ReportFeaturedFailureResolutionDecisionSummary | null;
  items: ReportFeaturedFailureResolutionDecisionItem[];
};

export function ReportFeaturedFailureResolutionDecisionPanel({ summary, items }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">Featured karar mantigi icin henuz yeterli veri yok.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <SummaryMetric label="Hata Tipi" value={summary.total_reasons} />
          <SummaryMetric label="Override Tercihi" value={summary.analytics_override_preferred} />
          <SummaryMetric label="Calisan Featured" value={summary.working_featured} />
          <SummaryMetric label="Manuel Takip" value={summary.manual_followup} />
          <SummaryMetric label="Varsayilan Oneri" value={summary.default_recommendation} />
          <SummaryMetric label="En Cok Secilen" value={summary.top_selected_action_label ?? "-"} />
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.reason_code} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={item.reason_label} variant={decisionVariant(item.decision_status)} />
                  <Badge label={item.decision_status_label} variant={decisionVariant(item.decision_status)} />
                  {item.provider_label ? <Badge label={item.provider_label} variant="neutral" /> : null}
                  {item.delivery_stage_label ? <Badge label={item.delivery_stage_label} variant="neutral" /> : null}
                  <Badge label={`${item.failed_runs} fail`} variant="warning" />
                </div>

                <p className="text-sm font-semibold">
                  Secilen: {item.selected_action_label}
                  {item.recommended_action_label && item.recommended_action_label !== item.selected_action_label
                    ? ` / Varsayilan: ${item.recommended_action_label}`
                    : ""}
                </p>
                <p className="text-sm muted-text">{item.why_selected}</p>

                <p className="text-xs muted-text">
                  Kaynak: {sourceLabel(item.source)}
                  {item.top_observed_action_label ? ` / En cok gozlenen: ${item.top_observed_action_label}` : ""}
                  {item.top_override_action_label ? ` / Override: ${item.top_override_action_label}` : ""}
                </p>

                {item.primary_entity?.route ? (
                  <div className="flex flex-wrap items-center gap-2 text-xs">
                    <span className="muted-text">
                      Odak entity: {item.primary_entity.label ?? "Bilinmeyen varlik"}
                      {item.primary_entity.context_label ? ` / ${item.primary_entity.context_label}` : ""}
                    </span>
                    <Link
                      href={buildFocusedDetailHref(item)}
                      className="inline-flex items-center rounded-md border border-[var(--border)] px-2 py-1 font-semibold hover:bg-[var(--surface-2)]"
                    >
                      Detaya Git
                    </Link>
                  </div>
                ) : null}
              </div>

              <div className="xl:w-[360px]">
                <div className="grid grid-cols-2 gap-2">
                  <Metric label="Takip" value={item.tracked_interactions} />
                  <Metric label="Featured" value={item.featured_interactions} />
                  <Metric label="Override" value={item.override_interactions} />
                  <Metric label="Uyum %" value={formatRate(item.follow_rate)} />
                  <Metric label="Featured %" value={formatRate(item.featured_success_rate)} />
                  <Metric label="Override %" value={formatRate(item.override_success_rate)} />
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function buildFocusedDetailHref(item: ReportFeaturedFailureResolutionDecisionItem): string {
  const baseRoute = item.primary_entity?.route;

  if (!baseRoute) {
    return "#";
  }

  const [pathname, rawQuery = ""] = baseRoute.split("?");
  const params = new URLSearchParams(rawQuery);

  if (item.reason_code) {
    params.set("focus_reason_code", item.reason_code);
  }

  if (item.selected_action_code) {
    params.set("focus_action_code", item.selected_action_code);
  }

  params.set("focus_source", "featured_decision");

  const query = params.toString();

  return query ? `${pathname}?${query}` : pathname;
}

function decisionVariant(value: string): "success" | "warning" | "danger" | "neutral" {
  switch (value) {
    case "working_featured":
      return "success";
    case "manual_followup":
      return "warning";
    case "analytics_override_preferred":
      return "danger";
    default:
      return "neutral";
  }
}

function sourceLabel(value: string): string {
  switch (value) {
    case "analytics_feedback":
      return "Analytics Geri Besleme";
    case "featured_analytics":
      return "Featured Analytics";
    case "effectiveness":
      return "Etkinlik Verisi";
    default:
      return value;
  }
}

function formatRate(value: number | null): string {
  return value === null ? "-" : `%${value.toFixed(1)}`;
}

function Metric({ label, value }: { label: string; value: number | string }) {
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
