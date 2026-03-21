"use client";

import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportDeliveryProfileSuggestion } from "@/lib/types";

type Props = {
  suggestion: ReportDeliveryProfileSuggestion | null;
  entityLabel: string;
  entityType: "account" | "campaign";
  entityId: string;
  onApplied?: () => Promise<void> | void;
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

export function ReportDeliveryProfileSuggestionCard({
  suggestion,
  entityLabel,
  entityType,
  entityId,
  onApplied,
}: Props) {
  const [isApplying, setIsApplying] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

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

  const handleApply = async () => {
    if (!suggestion.can_apply) {
      return;
    }

    setIsApplying(true);
    setError(null);
    setMessage(null);

    try {
      await apiRequest(`/reports/delivery-profiles/${entityType}/${entityId}`, {
        method: "PUT",
        requireWorkspace: true,
        body: {
          ...suggestion.apply_payload,
          is_active: true,
        },
      });

      setMessage("Onerilen teslim profili uygulandi.");
      await onApplied?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Onerilen profil uygulanamadi.");
    } finally {
      setIsApplying(false);
    }
  };

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
      {error ? <p className="mt-2 text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="mt-2 text-sm text-[var(--accent)]">{message}</p> : null}

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

      <div className="mt-4 flex flex-wrap gap-2">
        <Button
          type="button"
          size="sm"
          variant="secondary"
          onClick={() => void handleApply()}
          disabled={!suggestion.can_apply || isApplying}
        >
          {isApplying ? "Uygulaniyor..." : suggestion.can_apply ? "Oneriyi Uygula" : "Zaten Uygulaniyor"}
        </Button>
      </div>
    </div>
  );
}
