"use client";

import Link from "next/link";
import type { ReadonlyURLSearchParams } from "next/navigation";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { buildHrefWithFilters, GLOBAL_DATE_FILTER_KEYS } from "@/lib/filters";
import { ReportDeliveryRunListItem } from "@/lib/types";

type ReportDeliveryHistoryPanelProps = {
  runs: ReportDeliveryRunListItem[];
  searchParams: ReadonlyURLSearchParams;
  activeActionKey: string | null;
  onRetry: (run: ReportDeliveryRunListItem) => Promise<void>;
};

export function ReportDeliveryHistoryPanel({
  runs,
  searchParams,
  activeActionKey,
  onRetry,
}: ReportDeliveryHistoryPanelProps) {
  return (
    <div className="space-y-3">
      {runs.map((run) => (
        <div key={run.id} className="rounded-lg border border-[var(--border)] p-3">
          <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
            <div className="space-y-2">
              <div className="flex flex-wrap gap-2">
                <Badge label={run.status} variant={run.status === "failed" ? "danger" : "neutral"} />
                <Badge label={run.trigger_mode} variant="neutral" />
                {run.schedule ? <Badge label={run.schedule.cadence_label} variant="neutral" /> : null}
                {run.can_retry ? <Badge label="Retry Uygun" variant="warning" /> : null}
                {run.retry_of_run_id ? <Badge label="Retry Run" variant="success" /> : null}
              </div>
              <p className="font-semibold">
                {run.schedule?.template.name ?? run.snapshot_title ?? "Teslim kaydi"}
              </p>
              <p className="text-xs muted-text">
                {run.schedule?.template.entity_label ?? "Varlik"}
                {run.schedule?.template.context_label ? ` / ${run.schedule.template.context_label}` : ""}
              </p>
              <p className="text-sm muted-text">
                Hazirlandi: {run.prepared_at ?? "-"}
                {run.delivered_at ? ` / Teslim: ${run.delivered_at}` : ""}
                {run.schedule?.next_run_at ? ` / Sonraki: ${run.schedule.next_run_at}` : ""}
              </p>
              {run.delivery ? (
                <p className="text-sm muted-text">
                  {run.delivery.channel_label} / {run.delivery.mailer ?? "-"} / {run.delivery.recipients_count} alici
                </p>
              ) : null}
              {run.failure_reason ? (
                <div className="space-y-1">
                  <div className="flex flex-wrap gap-2">
                    <Badge
                      label={run.failure_reason.label}
                      variant={
                        run.failure_reason.severity === "critical"
                          ? "danger"
                          : run.failure_reason.severity === "warning"
                            ? "warning"
                            : "neutral"
                      }
                    />
                    <Badge label={run.failure_reason.provider_label} variant="neutral" />
                    <Badge label={run.failure_reason.delivery_stage_label} variant="neutral" />
                    {run.failure_reason.is_unknown ? <Badge label="Siniflandirilamadi" variant="neutral" /> : null}
                  </div>
                  <p className="text-sm muted-text">{run.failure_reason.summary}</p>
                  <p className="text-xs muted-text">Oneri: {run.failure_reason.suggested_action}</p>
                </div>
              ) : null}
              {run.error_message ? <p className="text-sm text-[var(--danger)]">{run.error_message}</p> : null}
              {run.retried_by_run_id ? (
                <p className="text-xs muted-text">Bu kayit icin retry run olusturuldu.</p>
              ) : null}
              {run.snapshot_url ? (
                <Link
                  href={run.snapshot_url}
                  className="inline-flex text-sm font-semibold text-[var(--accent)] hover:underline"
                >
                  Snapshot detayini ac
                </Link>
              ) : null}
              {run.schedule?.template.report_url ? (
                <Link
                  href={buildHrefWithFilters(run.schedule.template.report_url, searchParams, GLOBAL_DATE_FILTER_KEYS)}
                  className="ml-4 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline"
                >
                  Canli raporu ac
                </Link>
              ) : null}
              {run.share_link?.share_url ? (
                <div className="flex flex-wrap gap-3 text-sm">
                  <a
                    href={run.share_link.share_url}
                    target="_blank"
                    rel="noreferrer"
                    className="font-semibold text-[var(--accent)] hover:underline"
                  >
                    Musteri paylasim linki
                  </a>
                  {run.share_link.export_csv_url ? (
                    <a
                      href={run.share_link.export_csv_url}
                      target="_blank"
                      rel="noreferrer"
                      className="font-semibold text-[var(--accent)] hover:underline"
                    >
                      CSV indir
                    </a>
                  ) : null}
                </div>
              ) : null}
            </div>

            <div className="flex flex-wrap gap-2">
              {run.can_retry ? (
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => void onRetry(run)}
                  disabled={activeActionKey !== null}
                >
                  {activeActionKey === `retry:${run.id}` ? "Retry calisiyor..." : "Retry"}
                </Button>
              ) : null}
            </div>
          </div>
        </div>
      ))}
      {runs.length === 0 ? <p className="text-sm muted-text">Henuz teslim gecmisi kaydi yok.</p> : null}
    </div>
  );
}
