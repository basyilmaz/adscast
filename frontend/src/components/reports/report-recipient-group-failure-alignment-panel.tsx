"use client";

import { Badge } from "@/components/ui/badge";
import {
  ReportRecipientGroupFailureAlignmentItem,
  ReportRecipientGroupFailureAlignmentSummary,
} from "@/lib/types";

type Props = {
  summary: ReportRecipientGroupFailureAlignmentSummary | null;
  items: ReportRecipientGroupFailureAlignmentItem[];
};

export function ReportRecipientGroupFailureAlignmentPanel({ summary, items }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">Hata nedeni - secim korelasyon verisi henuz olusmadi.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
          <SummaryMetric label="Fail Run" value={summary.tracked_failed_runs} />
          <SummaryMetric label="Oneriye Uyulan Fail" value={summary.aligned_failed_runs} />
          <SummaryMetric label="Override Fail" value={summary.overridden_failed_runs} />
          <SummaryMetric label="Override Agirlikli" value={summary.override_dominant_reasons} />
          <SummaryMetric label="Oneri Agirlikli" value={summary.recommendation_dominant_reasons} />
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.reason_code} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={item.label} variant={item.severity === "critical" ? "danger" : item.severity === "warning" ? "warning" : "neutral"} />
                  <Badge label={item.dominant_alignment_label} variant={item.dominant_alignment_status === "override_driven" ? "danger" : item.dominant_alignment_status === "recommendation_driven" ? "success" : "neutral"} />
                  <Badge label={`${item.tracked_failed_runs} fail`} variant="neutral" />
                </div>
                <p className="text-sm muted-text">{item.summary}</p>
                <p className="text-xs muted-text">
                  Oneriye uyulan fail: {item.aligned_failed_runs} / Override fail: {item.overridden_failed_runs} / Override orani: {formatRate(item.override_rate)}
                </p>
                <p className="text-xs muted-text">
                  En cok onerilen grup: {item.top_recommended_group_label ?? "-"} / En cok secilen override: {item.top_selected_override_group_label ?? "-"}
                </p>
                <p className="text-xs muted-text">Oneri: {item.suggested_action}</p>
              </div>
              <div className="xl:w-[320px] space-y-2 text-xs muted-text">
                <p>Son gorulum: {item.last_seen_at ?? "-"}</p>
                <p>Ornek hata: {item.sample_error_message ?? "-"}</p>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function formatRate(value: number | null): string {
  return value === null ? "-" : `%${value.toFixed(1)}`;
}

function SummaryMetric({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
