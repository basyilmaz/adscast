"use client";

import { Badge } from "@/components/ui/badge";
import { Card, CardTitle } from "@/components/ui/card";
import {
  ReportDeliveryRetryRecommendationItem,
  ReportDeliveryRetryRecommendationSummary,
} from "@/lib/types";

type Props = {
  summary: ReportDeliveryRetryRecommendationSummary;
  items: ReportDeliveryRetryRecommendationItem[];
  entityLabel: string;
};

function variantForSeverity(value: string) {
  if (value === "critical" || value === "high") return "danger" as const;
  if (value === "warning" || value === "medium") return "warning" as const;
  if (value === "success") return "success" as const;

  return "neutral" as const;
}

function variantForPolicy(value: string) {
  if (value === "auto_retry") return "success" as const;
  if (value === "manual_retry" || value === "retry_after_fix") return "warning" as const;
  if (value === "do_not_retry") return "danger" as const;

  return "neutral" as const;
}

export function ReportDeliveryRetryRecommendationsPanel({ summary, items, entityLabel }: Props) {
  return (
    <Card>
      <CardTitle>Retry Rehberi</CardTitle>
      <p className="mt-2 text-sm muted-text">
        {entityLabel} icin provider ve teslim asamasi bazli retry politikasi. Bu panel, hangi hata tipinde retry
        acik kalmali sorusunu netlestirir.
      </p>

      <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <Metric label="Toplam Oneri" value={summary.total_recommendations} />
        <Metric label="Auto Retry" value={summary.auto_retry_recommendations} />
        <Metric label="Manual Retry" value={summary.manual_retry_recommendations} />
        <Metric label="Fix Sonrasi" value={summary.retry_after_fix_recommendations} />
        <Metric label="Bloklu Retry" value={summary.blocked_retry_recommendations} />
      </div>

      <div className="mt-4 rounded-lg border border-[var(--border)] px-4 py-3">
        <p className="text-xs font-semibold uppercase tracking-wide muted-text">Baskin Politika</p>
        <p className="mt-2 text-sm font-semibold">{summary.top_policy_label ?? "-"}</p>
      </div>

      <div className="mt-4 space-y-3">
        {items.map((item) => (
          <div key={`${item.reason_code}:${item.provider}:${item.delivery_stage}`} className="rounded-lg border border-[var(--border)] p-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge label={item.label} variant={variantForSeverity(item.severity)} />
              <Badge label={item.retry_policy_label} variant={variantForPolicy(item.retry_policy)} />
              <Badge label={item.provider_label} variant="neutral" />
              <Badge label={item.delivery_stage_label} variant="neutral" />
              <Badge label={`${item.failed_runs} fail`} variant="neutral" />
            </div>

            <p className="mt-3 text-sm">{item.operator_note}</p>
            <p className="mt-2 text-xs muted-text">Aksiyon kodu: {item.primary_action_code}</p>

            <div className="mt-3 grid gap-3 text-sm md:grid-cols-3">
              <div className="rounded-md border border-[var(--border)] p-3">
                <p className="text-xs font-semibold uppercase tracking-wide muted-text">Bekleme</p>
                <p className="mt-2 font-semibold">
                  {item.recommended_wait_minutes === null ? "Duzeltme Sonrasi" : `${item.recommended_wait_minutes} dk`}
                </p>
              </div>
              <div className="rounded-md border border-[var(--border)] p-3">
                <p className="text-xs font-semibold uppercase tracking-wide muted-text">Maks Deneme</p>
                <p className="mt-2 font-semibold">{item.recommended_max_attempts}</p>
              </div>
              <div className="rounded-md border border-[var(--border)] p-3">
                <p className="text-xs font-semibold uppercase tracking-wide muted-text">Ozet</p>
                <p className="mt-2 font-semibold">{item.summary}</p>
              </div>
            </div>
          </div>
        ))}

        {items.length === 0 ? (
          <p className="text-sm muted-text">Bu kayit icin retry politikasi gerektiren hata sinifi bulunmuyor.</p>
        ) : null}
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
