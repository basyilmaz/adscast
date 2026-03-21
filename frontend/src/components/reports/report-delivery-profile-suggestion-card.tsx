"use client";

import { Badge } from "@/components/ui/badge";
import { ReportDeliveryProfileSuggestion } from "@/lib/types";

type Props = {
  suggestion: ReportDeliveryProfileSuggestion | null;
  entityLabel: string;
};

const CHANGE_LABELS: Record<string, string> = {
  recipient_group: "Alici grubu",
  cadence: "Cadence",
  schedule_slot: "Gonderim gunu",
  send_time: "Saat",
  range: "Rapor araligi",
  layout: "Layout",
  contact_tags: "Etiket baglami",
  share_delivery: "Paylasim ayari",
};

export function ReportDeliveryProfileSuggestionCard({ suggestion, entityLabel }: Props) {
  if (!suggestion) {
    return (
      <div className="rounded-lg border border-[var(--border)] p-4">
        <p className="text-sm font-semibold">Otomatik Teslim Profili Onerisi</p>
        <p className="mt-2 text-sm muted-text">
          {entityLabel} icin rule-managed template tabanli ek bir teslim profili onerisi bulunmuyor.
        </p>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-[var(--border)] p-4">
      <div className="flex flex-wrap gap-2">
        <Badge label={suggestion.status_label} variant={suggestion.status === "already_applied" ? "success" : "warning"} />
        <Badge label={suggestion.template_profile.kind_label} variant="neutral" />
        <Badge label={suggestion.template_profile.priority_label} variant="neutral" />
        {suggestion.recommendation_label ? <Badge label={suggestion.recommendation_label} variant="neutral" /> : null}
      </div>

      <p className="mt-3 text-sm font-semibold">{suggestion.recipient_preset_name}</p>
      <p className="mt-1 text-sm muted-text">{suggestion.reason}</p>

      <div className="mt-3 grid gap-3 md:grid-cols-2">
        <div className="rounded-md bg-[var(--surface-2)] p-3">
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Onerilen Profil</p>
          <p className="mt-2 text-sm">{suggestion.cadence_label}</p>
          <p className="mt-1 text-xs muted-text">
            {suggestion.delivery_channel_label} / {suggestion.default_range_days} gun / {suggestion.timezone}
          </p>
          <p className="mt-2 text-xs muted-text">{suggestion.recipient_group_summary?.label ?? "Alici grubu yok"}</p>
          <p className="mt-1 text-xs muted-text">
            Cozulmus alici: {suggestion.resolved_recipients_count}
            {suggestion.share_delivery.enabled ? " / Auto share acik" : " / Auto share kapali"}
          </p>
        </div>

        <div className="rounded-md bg-[var(--surface-2)] p-3">
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Farklar</p>
          {suggestion.changes.length > 0 ? (
            <div className="mt-2 flex flex-wrap gap-2">
              {suggestion.changes.map((change) => (
                <Badge key={change} label={CHANGE_LABELS[change] ?? change} variant="neutral" />
              ))}
            </div>
          ) : (
            <p className="mt-2 text-sm muted-text">Mevcut profil ile ayni kural seti zaten uygulaniyor.</p>
          )}
          {suggestion.template_rule_summary.badges.length > 0 ? (
            <p className="mt-3 text-xs muted-text">
              Kural: {suggestion.template_rule_summary.badges.join(" / ")}
            </p>
          ) : null}
        </div>
      </div>
    </div>
  );
}
