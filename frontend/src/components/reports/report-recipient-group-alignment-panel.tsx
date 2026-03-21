"use client";

import { Badge } from "@/components/ui/badge";
import {
  ReportRecipientGroupAlignmentItem,
  ReportRecipientGroupAlignmentSummary,
} from "@/lib/types";

type Props = {
  summary: ReportRecipientGroupAlignmentSummary | null;
  items: ReportRecipientGroupAlignmentItem[];
};

export function ReportRecipientGroupAlignmentPanel({ summary, items }: Props) {
  if (items.length === 0) {
    return <p className="text-sm muted-text">Oneri-secilim sapma verisi henuz olusmadi.</p>;
  }

  return (
    <div className="space-y-4">
      {summary ? (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
          <SummaryMetric label="Izlenen Karar" value={summary.tracked_decisions} />
          <SummaryMetric label="Uyumlu Secim" value={summary.aligned_decisions} />
          <SummaryMetric label="Override" value={summary.overridden_decisions} />
          <SummaryMetric label="Onerisiz" value={summary.no_recommendation_decisions} />
          <SummaryMetric label="Bilinmeyen" value={summary.unknown_decisions} />
        </div>
      ) : null}

      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.schedule_id} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  <Badge label={alignmentLabel(item.alignment.status)} variant={alignmentVariant(item.alignment.status)} />
                  {item.entity_type ? <Badge label={item.entity_type} variant="neutral" /> : null}
                  <Badge label={item.cadence_label} variant="neutral" />
                  {item.last_status ? (
                    <Badge label={item.last_status} variant={item.last_status === "failed" ? "danger" : "neutral"} />
                  ) : null}
                </div>
                <div>
                  <p className="font-semibold">{item.entity_label ?? item.template_name ?? "Teslim karari"}</p>
                  {item.context_label ? <p className="mt-1 text-sm muted-text">{item.context_label}</p> : null}
                </div>
                <p className="text-sm muted-text">{item.alignment.reason}</p>
                <p className="text-xs muted-text">
                  Secilen: {item.selected_group?.name ?? "-"} / Onerilen: {item.recommended_group?.name ?? "-"}
                </p>
                <p className="text-xs muted-text">
                  Sonraki run: {item.next_run_at ?? "-"} / Olusturma: {item.created_at ?? "-"}
                </p>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function alignmentLabel(status: string): string {
  switch (status) {
    case "aligned":
      return "Oneriyle Uyumlu";
    case "override":
      return "Override";
    case "no_recommendation":
      return "Oneri Yok";
    case "missing_selection":
      return "Secim Eksik";
    default:
      return "Bilinmiyor";
  }
}

function alignmentVariant(status: string): "success" | "warning" | "danger" | "neutral" {
  switch (status) {
    case "aligned":
      return "success";
    case "override":
      return "warning";
    case "missing_selection":
      return "danger";
    default:
      return "neutral";
  }
}

function SummaryMetric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-[var(--border)] px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );
}
