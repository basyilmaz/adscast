"use client";

import { Badge } from "@/components/ui/badge";
import { Card, CardTitle } from "@/components/ui/card";
import {
  actionLabelForCode,
  focusSourceLabel,
  focusedRetryExplanation,
  isFocusedRetryRecommendation,
  prioritizeFocusedRetryRecommendations,
  reasonLabelForCode,
} from "@/lib/report-failure-focus";
import {
  ReportDeliveryRetryRecommendationItem,
  ReportDeliveryRetryRecommendationSummary,
  ReportFeaturedFailureResolution,
} from "@/lib/types";

type Props = {
  summary: ReportDeliveryRetryRecommendationSummary;
  items: ReportDeliveryRetryRecommendationItem[];
  entityLabel: string;
  featuredRecommendation?: ReportFeaturedFailureResolution | null;
  focusActionCode?: string | null;
  focusReasonCode?: string | null;
  focusSource?: string | null;
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

export function ReportDeliveryRetryRecommendationsPanel({
  summary,
  items,
  entityLabel,
  featuredRecommendation,
  focusActionCode,
  focusReasonCode,
  focusSource,
}: Props) {
  const prioritizedItems = prioritizeFocusedRetryRecommendations(
    items,
    focusActionCode,
    focusReasonCode,
    featuredRecommendation,
  );

  return (
    <Card>
      <CardTitle>Retry Rehberi</CardTitle>
      <p className="mt-2 text-sm muted-text">
        {entityLabel} icin provider ve teslim asamasi bazli retry politikasi. Bu panel, hangi hata tipinde retry
        acik kalmali sorusunu netlestirir.
      </p>

      {(focusActionCode || focusReasonCode) ? (
        <div className="mt-3 rounded-lg border border-[var(--accent)]/30 bg-[var(--accent)]/5 px-3 py-2 text-sm">
          <p className="font-semibold">Rapor merkezinden odaklandi</p>
          <p className="mt-1 muted-text">
            {focusReasonCode ? `Hata nedeni: ${reasonLabelForCode(focusReasonCode)}` : "Belirli retry odagi"}
            {focusActionCode ? ` / Aksiyon: ${actionLabelForCode(focusActionCode)}` : ""}
            {focusSource ? ` / Kaynak: ${focusSourceLabel(focusSource)}` : ""}
          </p>
        </div>
      ) : null}

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
        {prioritizedItems.map((item) => {
          const isFocused = isFocusedRetryRecommendation(item, focusActionCode, focusReasonCode, featuredRecommendation);
          const isFeaturedAligned = Boolean(
            featuredRecommendation
            && (featuredRecommendation.reason_code === item.reason_code
              || featuredRecommendation.action_code === item.primary_action_code),
          );

          return (
          <div
            key={`${item.reason_code}:${item.provider}:${item.delivery_stage}`}
            className={`rounded-lg border p-4 ${
              isFocused
                ? "border-[var(--accent)] bg-[var(--accent)]/10"
                : isFeaturedAligned
                  ? "border-[var(--accent)]/40 bg-[var(--accent)]/5"
                  : "border-[var(--border)]"
            }`}
          >
            <div className="flex flex-wrap items-center gap-2">
              <Badge label={item.label} variant={variantForSeverity(item.severity)} />
              <Badge label={item.retry_policy_label} variant={variantForPolicy(item.retry_policy)} />
              <Badge label={item.provider_label} variant="neutral" />
              <Badge label={item.delivery_stage_label} variant="neutral" />
              <Badge label={`${item.failed_runs} fail`} variant="neutral" />
              {isFocused ? <Badge label="Odakta" variant="warning" /> : null}
              {!isFocused && isFeaturedAligned ? <Badge label="Featured ile hizali" variant="success" /> : null}
            </div>

            <p className="mt-3 text-sm">{item.operator_note}</p>
            <p className="mt-2 text-xs muted-text">Aksiyon kodu: {item.primary_action_code}</p>

            {(isFocused || isFeaturedAligned) ? (
              <div className="mt-3 rounded-md border border-[var(--accent)]/30 bg-[var(--surface-2)] px-3 py-2 text-sm">
                <p className="font-semibold">Bu retry karari neden odakta?</p>
                <p className="mt-1 muted-text">
                  {focusedRetryExplanation(
                    item,
                    focusActionCode,
                    focusReasonCode,
                    focusSource,
                    featuredRecommendation,
                  )}
                </p>
              </div>
            ) : null}

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
          );
        })}

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
