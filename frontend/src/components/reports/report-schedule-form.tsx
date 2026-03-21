"use client";

import { type ReactNode, useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportTemplateListItem } from "@/lib/types";

type Props = {
  templates: ReportTemplateListItem[];
  onCreated?: () => Promise<void> | void;
};

const WEEKDAY_OPTIONS = [
  { value: "1", label: "Pazartesi" },
  { value: "2", label: "Sali" },
  { value: "3", label: "Carsamba" },
  { value: "4", label: "Persembe" },
  { value: "5", label: "Cuma" },
  { value: "6", label: "Cumartesi" },
  { value: "7", label: "Pazar" },
];

export function ReportScheduleForm({ templates, onCreated }: Props) {
  const activeTemplates = useMemo(
    () => templates.filter((item) => item.is_active),
    [templates],
  );

  const [templateId, setTemplateId] = useState(activeTemplates[0]?.id ?? "");
  const [cadence, setCadence] = useState<"daily" | "weekly" | "monthly">("weekly");
  const [weekday, setWeekday] = useState("1");
  const [monthDay, setMonthDay] = useState("1");
  const [sendTime, setSendTime] = useState("09:00");
  const [timezone, setTimezone] = useState("Europe/Istanbul");
  const [recipients, setRecipients] = useState("client@castintech.com");
  const [autoShareEnabled, setAutoShareEnabled] = useState(true);
  const [shareLabelTemplate, setShareLabelTemplate] = useState("{template_name} / {end_date}");
  const [shareExpiresInDays, setShareExpiresInDays] = useState("7");
  const [shareAllowCsvDownload, setShareAllowCsvDownload] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (activeTemplates.some((item) => item.id === templateId)) {
      return;
    }

    setTemplateId(activeTemplates[0]?.id ?? "");
  }, [activeTemplates, templateId]);

  const parsedRecipients = recipients
    .split(/[\n,;]+/)
    .map((item) => item.trim())
    .filter(Boolean);

  const isDisabled = activeTemplates.length === 0 || isSubmitting;

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!templateId || parsedRecipients.length === 0) {
      setError("Sablon ve en az bir alici zorunlu.");
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setMessage(null);

    try {
      await apiRequest("/reports/delivery-schedules", {
        method: "POST",
        requireWorkspace: true,
        body: {
          report_template_id: templateId,
          cadence,
          weekday: cadence === "weekly" ? Number(weekday) : null,
          month_day: cadence === "monthly" ? Number(monthDay) : null,
          send_time: sendTime,
          timezone,
          recipients: parsedRecipients,
          delivery_channel: "email_stub",
          auto_share_enabled: autoShareEnabled,
          share_label_template: autoShareEnabled ? shareLabelTemplate.trim() || null : null,
          share_expires_in_days: autoShareEnabled ? Number(shareExpiresInDays) : null,
          share_allow_csv_download: autoShareEnabled ? shareAllowCsvDownload : false,
        },
      });

      setMessage("Zamanlanmis teslim kaydi olusturuldu.");

      await onCreated?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Schedule kaydedilemedi.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form className="space-y-3" onSubmit={handleSubmit}>
      <div className="grid gap-3 md:grid-cols-2">
        <Field label="Rapor Sablonu">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={templateId}
            onChange={(event) => setTemplateId(event.target.value)}
            disabled={activeTemplates.length === 0}
          >
            {activeTemplates.length === 0 ? <option value="">Aktif sablon bulunmuyor</option> : null}
            {activeTemplates.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </Field>

        <Field label="Cadence">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={cadence}
            onChange={(event) => setCadence(event.target.value as "daily" | "weekly" | "monthly")}
          >
            <option value="daily">Gunluk</option>
            <option value="weekly">Haftalik</option>
            <option value="monthly">Aylik</option>
          </select>
        </Field>

        {cadence === "weekly" ? (
          <Field label="Haftanin Gunu">
            <select
              className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
              value={weekday}
              onChange={(event) => setWeekday(event.target.value)}
            >
              {WEEKDAY_OPTIONS.map((item) => (
                <option key={item.value} value={item.value}>
                  {item.label}
                </option>
              ))}
            </select>
          </Field>
        ) : null}

        {cadence === "monthly" ? (
          <Field label="Ayin Gunu">
            <input
              type="number"
              min={1}
              max={28}
              className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
              value={monthDay}
              onChange={(event) => setMonthDay(event.target.value)}
            />
          </Field>
        ) : null}

        <Field label="Gonderim Saati">
          <input
            type="time"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={sendTime}
            onChange={(event) => setSendTime(event.target.value)}
          />
        </Field>

        <Field label="Timezone">
          <input
            type="text"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={timezone}
            onChange={(event) => setTimezone(event.target.value)}
          />
        </Field>
      </div>

      <Field label="Alicilar">
        <textarea
          className="min-h-[96px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
          value={recipients}
          onChange={(event) => setRecipients(event.target.value)}
          placeholder="client@castintech.com, ops@castintech.com"
        />
      </Field>

      <div className="rounded-lg border border-[var(--border)] p-3">
        <p className="text-xs font-semibold uppercase tracking-wide muted-text">Otomatik Musteri Linki</p>
        <label className="mt-2 flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={autoShareEnabled}
            onChange={(event) => setAutoShareEnabled(event.target.checked)}
          />
          Her schedule run tamamlandiginda snapshot icin paylasim linki de olustur
        </label>

        {autoShareEnabled ? (
          <div className="mt-3 grid gap-3 md:grid-cols-3">
            <Field label="Link Etiketi">
              <input
                type="text"
                className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                value={shareLabelTemplate}
                onChange={(event) => setShareLabelTemplate(event.target.value)}
                placeholder="{template_name} / {end_date}"
              />
            </Field>

            <Field label="Link Suresi">
              <select
                className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                value={shareExpiresInDays}
                onChange={(event) => setShareExpiresInDays(event.target.value)}
              >
                <option value="3">3 gun</option>
                <option value="7">7 gun</option>
                <option value="14">14 gun</option>
                <option value="30">30 gun</option>
              </select>
            </Field>

            <Field label="CSV Indirme">
              <label className="flex h-10 items-center gap-2 rounded-md border border-[var(--border)] bg-white px-3 text-sm">
                <input
                  type="checkbox"
                  checked={shareAllowCsvDownload}
                  onChange={(event) => setShareAllowCsvDownload(event.target.checked)}
                />
                CSV indirilebilsin
              </label>
            </Field>
          </div>
        ) : null}

        {autoShareEnabled ? (
          <p className="mt-2 text-xs muted-text">
            Kullanilabilir degiskenler: {"{template_name}"}, {"{snapshot_title}"}, {"{start_date}"}, {"{end_date}"}.
          </p>
        ) : null}
      </div>

      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}

      <Button type="submit" disabled={isDisabled}>
        {isSubmitting ? "Kaydediliyor..." : "Schedule Kaydet"}
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
