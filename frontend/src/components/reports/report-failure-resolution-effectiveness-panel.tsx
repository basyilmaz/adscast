"use client";

import { Badge } from "@/components/ui/badge";
import {
  ReportFailureResolutionEffectivenessItem,
  ReportFailureResolutionEffectivenessSummary,
} from "@/lib/types";

type Props = {
  summary: ReportFailureResolutionEffectivenessSummary | null;
  items: ReportFailureResolutionEffectivenessItem[];
};

export function ReportFailureResolutionEffectivenessPanel({ summary, items }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">Duzeltme etkinligi icin henuz yeterli veri yok.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <SummaryMetric label="Izlenen Neden" value={summary.total_reasons} />
          <SummaryMetric label="Uygulanan Fix" value={summary.reasons_with_observed_fix} />
          <SummaryMetric label="Calisan Fix" value={summary.working_recommended_fixes} />
          <SummaryMetric label="Manuel Takip" value={summary.manual_followup_reasons} />
          <SummaryMetric label="Takilan Fix" value={summary.stalled_recommended_fixes} />
          <SummaryMetric label="En Iyi Fix" value={summary.top_working_fix_label ?? "-"} />
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.reason_code} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={item.label} variant={effectivenessVariant(item.effectiveness_status)} />
                  <Badge label={item.provider_label} variant="neutral" />
                  <Badge label={item.delivery_stage_label} variant="neutral" />
                  <Badge label={`${item.failed_runs} fail`} variant="warning" />
                </div>

                <div>
                  <p className="text-sm font-semibold">Onerilen Fix: {item.recommended_action.label}</p>
                  <p className="mt-1 text-sm muted-text">{item.effectiveness_summary}</p>
                </div>

                <p className="text-xs muted-text">
                  Politika: {item.recommended_action.retry_policy_label}
                  {item.recommended_action.recommended_wait_minutes !== null
                    ? ` / Bekleme: ${item.recommended_action.recommended_wait_minutes} dk`
                    : ""}
                  {item.recommended_action.recommended_max_attempts > 0
                    ? ` / Maks deneme: ${item.recommended_action.recommended_max_attempts}`
                    : ""}
                </p>

                {item.recommended_action_metrics ? (
                  <p className="text-xs muted-text">
                    Onerilen aksiyon: {item.recommended_action_metrics.observed_uses} kullanim /{" "}
                    {item.recommended_action_metrics.successful_executions} basarili /{" "}
                    {item.recommended_action_metrics.partial_executions} kismi /{" "}
                    {item.recommended_action_metrics.failed_executions} basarisiz
                    {item.recommended_action_metrics.success_rate !== null
                      ? ` / %${item.recommended_action_metrics.success_rate.toFixed(1)} basari`
                      : ""}
                  </p>
                ) : (
                  <p className="text-xs muted-text">Onerilen aksiyon henuz kullanilmadi.</p>
                )}

                {item.top_observed_action ? (
                  <p className="text-xs muted-text">
                    En cok gozlenen fiili aksiyon: {item.top_observed_action.label} / {item.top_observed_action.observed_uses} kullanim
                    {item.top_observed_action.success_rate !== null
                      ? ` / %${item.top_observed_action.success_rate.toFixed(1)} basari`
                      : ""}
                  </p>
                ) : null}
              </div>

              <div className="xl:w-[360px]">
                <p className="text-xs font-semibold uppercase tracking-wide muted-text">Gozlenen Duzeltmeler</p>
                <div className="mt-2 space-y-2">
                  {item.actions.map((action) => (
                    <div key={action.action_code} className="rounded-md bg-[var(--surface-2)] px-3 py-2">
                      <div className="flex flex-wrap gap-2">
                        <Badge label={action.label} variant="neutral" />
                        <Badge label={actionKindLabel(action.action_kind)} variant="neutral" />
                      </div>
                      <p className="mt-1 text-xs muted-text">
                        {action.observed_uses} kullanim / {action.successful_executions} basarili / {action.partial_executions} kismi / {action.failed_executions} basarisiz
                      </p>
                      <p className="text-xs muted-text">
                        {action.success_rate !== null ? `%${action.success_rate.toFixed(1)} basari / ` : ""}
                        Son gorulum: {action.last_seen_at ?? "-"}
                      </p>
                    </div>
                  ))}
                  {item.actions.length === 0 ? <p className="text-sm muted-text">Bu hata tipi icin duzeltme aksiyonu izlenmedi.</p> : null}
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

function effectivenessVariant(value: string): "success" | "warning" | "danger" | "neutral" {
  switch (value) {
    case "working_well":
      return "success";
    case "partially_working":
    case "manual_followup_active":
      return "warning";
    case "needs_attention":
    case "alternate_action_dominant":
      return "danger";
    default:
      return "neutral";
  }
}

function SummaryMetric({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
