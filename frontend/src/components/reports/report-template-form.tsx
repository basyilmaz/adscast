"use client";

import { type ReactNode, useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportIndexResponse } from "@/lib/types";

type Props = {
  builders: ReportIndexResponse["data"]["builders"];
  onCreated?: () => Promise<void> | void;
};

const REPORT_TYPES = {
  account: "client_account_summary_v1",
  campaign: "client_campaign_summary_v1",
} as const;

export function ReportTemplateForm({ builders, onCreated }: Props) {
  const [entityType, setEntityType] = useState<"account" | "campaign">(
    builders.accounts.length > 0 ? "account" : "campaign",
  );
  const [entityId, setEntityId] = useState("");
  const [name, setName] = useState("");
  const [defaultRangeDays, setDefaultRangeDays] = useState("30");
  const [layoutPreset, setLayoutPreset] = useState("standard");
  const [notes, setNotes] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const entityOptions = useMemo(() => (
    entityType === "account" ? builders.accounts : builders.campaigns
  ), [builders.accounts, builders.campaigns, entityType]);

  useEffect(() => {
    if (entityOptions.some((item) => item.id === entityId)) {
      return;
    }

    setEntityId(entityOptions[0]?.id ?? "");
  }, [entityId, entityOptions]);

  const isDisabled = entityOptions.length === 0 || isSubmitting;

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!entityId || !name.trim()) {
      setError("Sablon adi ve hedef kayit zorunlu.");
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setMessage(null);

    try {
      await apiRequest("/reports/templates", {
        method: "POST",
        requireWorkspace: true,
        body: {
          name: name.trim(),
          entity_type: entityType,
          entity_id: entityId,
          report_type: REPORT_TYPES[entityType],
          default_range_days: Number(defaultRangeDays),
          layout_preset: layoutPreset,
          notes: notes.trim() || null,
          configuration: {
            created_from: "reports_index",
          },
        },
      });

      setMessage("Rapor sablonu kaydedildi.");
      setName("");
      setNotes("");

      await onCreated?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Sablon kaydedilemedi.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form className="space-y-3" onSubmit={handleSubmit}>
      <div className="grid gap-3 md:grid-cols-2">
        <Field label="Sablon Adi">
          <input
            type="text"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Orn. Haftalik Musteri Raporu"
          />
        </Field>

        <Field label="Varlik Turu">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={entityType}
            onChange={(event) => setEntityType(event.target.value as "account" | "campaign")}
          >
            <option value="account">Reklam Hesabi</option>
            <option value="campaign">Kampanya</option>
          </select>
        </Field>

        <Field label={entityType === "account" ? "Reklam Hesabi" : "Kampanya"}>
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={entityId}
            onChange={(event) => setEntityId(event.target.value)}
            disabled={entityOptions.length === 0}
          >
            {entityOptions.length === 0 ? <option value="">Kayit bulunmuyor</option> : null}
            {entityOptions.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </Field>

        <Field label="Varsayilan Aralik">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={defaultRangeDays}
            onChange={(event) => setDefaultRangeDays(event.target.value)}
          >
            <option value="7">Son 7 gun</option>
            <option value="14">Son 14 gun</option>
            <option value="30">Son 30 gun</option>
            <option value="60">Son 60 gun</option>
          </select>
        </Field>

        <Field label="Layout Preset">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={layoutPreset}
            onChange={(event) => setLayoutPreset(event.target.value)}
          >
            <option value="standard">Standard</option>
            <option value="client_digest">Client Digest</option>
          </select>
        </Field>
      </div>

      <Field label="Notlar">
        <textarea
          className="min-h-[96px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          placeholder="Bu sablonun hangi ekip ritminde kullanilacagini not edin."
        />
      </Field>

      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}

      <Button type="submit" disabled={isDisabled}>
        {isSubmitting ? "Kaydediliyor..." : "Sablon Kaydet"}
      </Button>
    </form>
  );
}

function Field({
  label,
  children,
}: {
  label: string;
  children: ReactNode;
}) {
  return (
    <label className="flex flex-col gap-1">
      <span className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</span>
      {children}
    </label>
  );
}
