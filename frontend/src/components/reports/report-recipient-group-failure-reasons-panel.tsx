"use client";

import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import {
  ReportRecipientGroupFailureReasonItem,
  ReportRecipientGroupFailureReasonSummary,
} from "@/lib/types";

type Props = {
  summary: ReportRecipientGroupFailureReasonSummary | null;
  items: ReportRecipientGroupFailureReasonItem[];
};

export function ReportRecipientGroupFailureReasonsPanel({ summary, items }: Props) {
  return (
    <div className="space-y-4">
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Metric label="Hata Tipi" value={summary?.total_reason_types ?? 0} />
        <Metric label="Fail Run" value={summary?.total_failed_runs ?? 0} />
        <Metric label="Etkilenen Grup" value={summary?.affected_groups_count ?? 0} />
        <Metric label="Bilinmeyen" value={summary?.unknown_failed_runs ?? 0} />
      </div>

      <div className="space-y-3">
        {items.map((item) => (
          <Card key={item.reason_code} className="p-4">
            <div className="flex flex-wrap gap-2">
              <Badge label={item.label} variant={item.severity === "critical" ? "danger" : item.severity === "warning" ? "warning" : "neutral"} />
              <Badge label={`${item.failed_runs} fail`} variant="neutral" />
              <Badge label={`${item.affected_groups_count} grup`} variant="neutral" />
              {item.is_unknown ? <Badge label="Yeni sinif gerekli" variant="warning" /> : null}
            </div>
            <p className="mt-3 text-sm">{item.summary}</p>
            <p className="mt-2 text-sm muted-text">Oneri: {item.suggested_action}</p>
            <div className="mt-3 grid gap-3 text-xs muted-text md:grid-cols-2">
              <div>
                <p>En cok etkilenen grup: {item.top_group_label ?? "-"}</p>
                <p>En cok etkilenen entity: {item.top_entity_label ?? "-"}</p>
                <p>Son gorulum: {item.last_seen_at ?? "-"}</p>
              </div>
              <div>
                <p>Ornek hata: {item.sample_error_message ?? "-"}</p>
              </div>
            </div>
          </Card>
        ))}
        {items.length === 0 ? <p className="text-sm muted-text">Secili pencerede siniflanmis teslim hatasi yok.</p> : null}
      </div>
    </div>
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
