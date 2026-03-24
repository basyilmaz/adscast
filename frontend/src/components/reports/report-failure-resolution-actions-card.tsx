"use client";

import Link from "next/link";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { apiRequest } from "@/lib/api";
import { ReportFailureResolutionActionItem, ReportFailureResolutionSummary } from "@/lib/types";

type Props = {
  entityType: "account" | "campaign";
  entityId: string;
  summary: ReportFailureResolutionSummary;
  actions: ReportFailureResolutionActionItem[];
  onReload?: () => Promise<void> | void;
  onFocusDeliveryProfile?: () => void;
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
  onReload,
  onFocusDeliveryProfile,
}: Props) {
  const [activeActionCode, setActiveActionCode] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleApiAction = async (action: ReportFailureResolutionActionItem) => {
    setActiveActionCode(action.code);
    setMessage(null);
    setError(null);

    try {
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

  const handleFocusAction = (action: ReportFailureResolutionActionItem) => {
    setError(null);
    setMessage(`${action.label} icin teslim profili editoru acildi.`);
    onFocusDeliveryProfile?.();
  };

  return (
    <Card>
      <CardTitle>Hizli Duzeltme Aksiyonlari</CardTitle>
      <p className="mt-2 text-sm muted-text">
        Bu kayittaki teslim sorunlarini tek tikla toparlamak icin onerilen operator aksiyonlari.
      </p>

      <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Metric label="Aksiyon" value={summary.total_actions} />
        <Metric label="Retry Uygun" value={summary.retryable_runs} />
        <Metric label="Hata Tipi" value={summary.reason_types} />
        <div className="rounded-lg border border-[var(--border)] px-3 py-3">
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Baskin Neden</p>
          <p className="mt-2 text-sm font-semibold">{summary.top_reason_label ?? "-"}</p>
        </div>
      </div>

      {error ? <p className="mt-4 text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="mt-4 text-sm text-[var(--accent)]">{message}</p> : null}

      <div className="mt-4 space-y-3">
        {actions.map((action) => (
          <div key={action.id} className="rounded-lg border border-[var(--border)] p-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge label={action.label} variant={variantForSeverity(action.severity)} />
              <Badge label={action.action_kind} variant="neutral" />
              {action.metadata?.retryable_runs ? (
                <Badge label={`${action.metadata.retryable_runs} retry`} variant="neutral" />
              ) : null}
            </div>

            <p className="mt-3 text-sm">{action.detail}</p>

            {action.metadata?.affected_reason_codes?.length ? (
              <p className="mt-2 text-xs muted-text">
                Etkilenen nedenler: {action.metadata.affected_reason_codes.join(", ")}
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
                <Button type="button" size="sm" variant="secondary" onClick={() => handleFocusAction(action)}>
                  {action.button_label}
                </Button>
              ) : null}

              {action.action_kind === "route" && action.route ? (
                <Link href={action.route} className="inline-flex">
                  <Button type="button" size="sm" variant="secondary">
                    {action.button_label}
                  </Button>
                </Link>
              ) : null}
            </div>
          </div>
        ))}

        {actions.length === 0 ? (
          <p className="text-sm muted-text">Bu kayit icin otomatik onerilen hizli duzeltme aksiyonu yok.</p>
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
