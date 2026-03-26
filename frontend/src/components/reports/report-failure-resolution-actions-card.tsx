"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { apiRequest } from "@/lib/api";
import {
  actionLabelForCode,
  featuredRecommendationExplanation,
  focusSourceLabel,
  focusedActionExplanation,
  reasonLabelForCode,
} from "@/lib/report-failure-focus";
import {
  ReportFailureResolutionActionItem,
  ReportFailureResolutionSummary,
  ReportFeaturedFailureResolution,
} from "@/lib/types";

type Props = {
  entityType: "account" | "campaign";
  entityId: string;
  summary: ReportFailureResolutionSummary;
  actions: ReportFailureResolutionActionItem[];
  featuredRecommendation?: ReportFeaturedFailureResolution | null;
  onReload?: () => Promise<void> | void;
  onFocusDeliveryProfile?: () => void;
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

export function ReportFailureResolutionActionsCard({
  entityType,
  entityId,
  summary,
  actions,
  featuredRecommendation,
  onReload,
  onFocusDeliveryProfile,
  focusActionCode,
  focusReasonCode,
  focusSource,
}: Props) {
  const router = useRouter();
  const [activeActionCode, setActiveActionCode] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const trackAction = async (action: ReportFailureResolutionActionItem) => {
    try {
      await apiRequest(`/reports/failure-resolution-actions/${entityType}/${entityId}/${action.code}/track`, {
        method: "POST",
        requireWorkspace: true,
      });
    } catch {
      // Analytics track failure should not block the operator action itself.
    }
  };

  const handleApiAction = async (action: ReportFailureResolutionActionItem) => {
    setActiveActionCode(action.code);
    setMessage(null);
    setError(null);

    try {
      await trackAction(action);

      const response = await apiRequest<{
        data: {
          retried_runs?: number;
          failed_retries?: number;
        };
      }>(`/reports/failure-resolution-actions/${entityType}/${entityId}/${action.code}`, {
        method: "POST",
        requireWorkspace: true,
      });

      const retriedRuns = response.data.retried_runs ?? 0;
      const failedRetries = response.data.failed_retries ?? 0;
      setMessage(
        failedRetries > 0
          ? `${retriedRuns} retry baslatildi, ${failedRetries} run tekrar denenemedi.`
          : `${retriedRuns} retry baslatildi.`,
      );
      await onReload?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Failure resolution aksiyonu calistirilamadi.");
    } finally {
      setActiveActionCode(null);
    }
  };

  const handleFocusAction = async (action: ReportFailureResolutionActionItem) => {
    await trackAction(action);
    setError(null);
    setMessage(`${action.label} icin teslim profili editoru acildi.`);
    onFocusDeliveryProfile?.();
  };

  const handleRouteAction = async (action: ReportFailureResolutionActionItem) => {
    if (!action.route) {
      return;
    }

    await trackAction(action);
    router.push(action.route);
  };

  return (
    <Card>
      <CardTitle>Hizli Duzeltme Aksiyonlari</CardTitle>
      <p className="mt-2 text-sm muted-text">
        Bu kayittaki teslim sorunlarini tek tikla toparlamak icin onerilen operator aksiyonlari.
      </p>

      {(focusActionCode || focusReasonCode) ? (
        <div className="mt-3 rounded-lg border border-[var(--accent)]/30 bg-[var(--accent)]/5 px-3 py-2 text-sm">
          <p className="font-semibold">Rapor merkezinden odaklandi</p>
          <p className="mt-1 muted-text">
            {focusReasonCode ? `Hata nedeni: ${reasonLabelForCode(focusReasonCode)}` : "Belirli aksiyon odagi"}
            {focusActionCode ? ` / Aksiyon: ${actionLabelForCode(focusActionCode)}` : ""}
            {focusSource ? ` / Kaynak: ${focusSourceLabel(focusSource)}` : ""}
          </p>
        </div>
      ) : null}

      <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Metric label="Aksiyon" value={summary.total_actions} />
        <Metric label="Retry Uygun" value={summary.retryable_runs} />
        <Metric label="Hata Tipi" value={summary.reason_types} />
        <div className="rounded-lg border border-[var(--border)] px-3 py-3">
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Baskin Neden</p>
          <p className="mt-2 text-sm font-semibold">{summary.top_reason_label ?? "-"}</p>
        </div>
      </div>

      {featuredRecommendation ? (
        <div className="mt-4 rounded-lg border border-[var(--accent)]/30 bg-[var(--accent)]/5 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <Badge label={featuredRecommendation.status_label} variant="success" />
            <Badge label={featuredRecommendation.action_label} variant={variantForSeverity("warning")} />
            {featuredRecommendation.reason_label ? <Badge label={featuredRecommendation.reason_label} variant="neutral" /> : null}
            {featuredRecommendation.retry_policy_label ? <Badge label={featuredRecommendation.retry_policy_label} variant="neutral" /> : null}
          </div>
          <p className="mt-3 text-sm">{featuredRecommendation.summary}</p>
          <p className="mt-2 text-xs muted-text">
            Onerilen duzeltme: {featuredRecommendation.action_label}
            {featuredRecommendation.provider_label ? ` / ${featuredRecommendation.provider_label}` : ""}
            {featuredRecommendation.delivery_stage_label ? ` / ${featuredRecommendation.delivery_stage_label}` : ""}
          </p>
          <div className="mt-3 rounded-md border border-[var(--accent)]/30 bg-[var(--surface-2)] px-3 py-2 text-sm">
            <p className="font-semibold">Bu fix neden one cikiyor?</p>
            <p className="mt-1 muted-text">
              {featuredRecommendationExplanation(featuredRecommendation, focusActionCode, focusReasonCode, focusSource)}
            </p>
          </div>
          {featuredRecommendation.analytics_guidance ? (
            <p className="mt-2 text-xs muted-text">{featuredRecommendation.analytics_guidance}</p>
          ) : null}
        </div>
      ) : null}

      {error ? <p className="mt-4 text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="mt-4 text-sm text-[var(--accent)]">{message}</p> : null}

      <div className="mt-4 space-y-3">
        {prioritizeFocusedActions(actions, focusActionCode, focusReasonCode).map((action) => {
          const isFocusedAction = action.code === focusActionCode
            || (focusReasonCode ? action.metadata?.affected_reason_codes?.includes(focusReasonCode) : false);

          return (
          <div
            key={action.id}
            className={`rounded-lg border p-4 ${
              isFocusedAction
                ? "border-[var(--accent)] bg-[var(--accent)]/10"
                : featuredRecommendation?.action_code === action.code
                  ? "border-[var(--accent)] bg-[var(--accent)]/5"
                : "border-[var(--border)]"
            }`}
          >
            <div className="flex flex-wrap items-center gap-2">
              <Badge label={action.label} variant={variantForSeverity(action.severity)} />
              <Badge label={action.action_kind} variant="neutral" />
              {isFocusedAction ? <Badge label="Odakta" variant="warning" /> : null}
              {featuredRecommendation?.action_code === action.code ? <Badge label="Onerilen" variant="success" /> : null}
              {action.metadata?.retryable_runs ? (
                <Badge label={`${action.metadata.retryable_runs} retry`} variant="neutral" />
              ) : null}
            </div>

            <p className="mt-3 text-sm">{action.detail}</p>

            {isFocusedAction ? (
              <div className="mt-3 rounded-md border border-[var(--accent)]/30 bg-[var(--surface-2)] px-3 py-2 text-sm">
                <p className="font-semibold">Bu aksiyon neden odakta?</p>
                <p className="mt-1 muted-text">
                  {focusedActionExplanation(action, focusActionCode, focusReasonCode, focusSource)}
                </p>
              </div>
            ) : null}

            {action.metadata?.affected_reason_codes?.length ? (
              <p className="mt-2 text-xs muted-text">
                Etkilenen nedenler: {action.metadata.affected_reason_codes.map(reasonLabelForCode).join(", ")}
              </p>
            ) : null}

            {action.metadata?.sample_recipients?.length ? (
              <p className="mt-2 text-xs muted-text">
                Ornek alicilar: {action.metadata.sample_recipients.join(", ")}
              </p>
            ) : null}

            {action.metadata?.affected_group_labels?.length ? (
              <p className="mt-2 text-xs muted-text">
                Etkilenen gruplar: {action.metadata.affected_group_labels.join(", ")}
              </p>
            ) : null}

            <div className="mt-3">
              {action.action_kind === "api" ? (
                <Button
                  type="button"
                  size="sm"
                  variant="secondary"
                  disabled={!action.is_available || activeActionCode !== null}
                  onClick={() => void handleApiAction(action)}
                >
                  {activeActionCode === action.code ? "Calisiyor..." : action.button_label}
                </Button>
              ) : null}

              {action.action_kind === "focus_tab" ? (
                <Button type="button" size="sm" variant="secondary" onClick={() => void handleFocusAction(action)}>
                  {action.button_label}
                </Button>
              ) : null}

              {action.action_kind === "route" && action.route ? (
                <Button type="button" size="sm" variant="secondary" onClick={() => void handleRouteAction(action)}>
                  {action.button_label}
                </Button>
              ) : null}
            </div>
          </div>
          );
        })}

        {actions.length === 0 ? (
          <p className="text-sm muted-text">Bu kayit icin otomatik onerilen hizli duzeltme aksiyonu yok.</p>
        ) : null}
      </div>
    </Card>
  );
}

function prioritizeFocusedActions(
  actions: ReportFailureResolutionActionItem[],
  focusActionCode?: string | null,
  focusReasonCode?: string | null,
) {
  if (!focusActionCode && !focusReasonCode) {
    return actions;
  }

  return [...actions].sort((left, right) => {
    const leftFocused = left.code === focusActionCode
      || (focusReasonCode ? left.metadata?.affected_reason_codes?.includes(focusReasonCode) : false);
    const rightFocused = right.code === focusActionCode
      || (focusReasonCode ? right.metadata?.affected_reason_codes?.includes(focusReasonCode) : false);

    if (leftFocused === rightFocused) {
      return 0;
    }

    return leftFocused ? -1 : 1;
  });
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
