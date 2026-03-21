"use client";

import { type ReactNode, useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportIndexResponse } from "@/lib/types";

type Props = {
  builders: ReportIndexResponse["data"]["builders"];
  deliveryCapabilities: ReportIndexResponse["data"]["delivery_capabilities"] | null;
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

export function ReportDeliverySetupForm({ builders, deliveryCapabilities, onCreated }: Props) {
  const [entityType, setEntityType] = useState<"account" | "campaign">(
    builders.campaigns.length > 0 ? "campaign" : "account",
  );
  const [entityId, setEntityId] = useState("");
  const [templateName, setTemplateName] = useState("");
  const [defaultRangeDays, setDefaultRangeDays] = useState("7");
  const [deliveryChannel, setDeliveryChannel] = useState<"email_stub" | "email">(
    deliveryCapabilities?.real_email_available ? "email" : "email_stub",
  );
  const [cadence, setCadence] = useState<"daily" | "weekly" | "monthly">("weekly");
  const [weekday, setWeekday] = useState("1");
  const [monthDay, setMonthDay] = useState("1");
  const [sendTime, setSendTime] = useState("09:00");
  const [timezone, setTimezone] = useState("Europe/Istanbul");
  const [recipients, setRecipients] = useState("musteri@ornek.com");
  const [autoShareEnabled, setAutoShareEnabled] = useState(true);
  const [shareLabelTemplate, setShareLabelTemplate] = useState("{template_name} / {end_date}");
  const [shareExpiresInDays, setShareExpiresInDays] = useState("7");
  const [shareAllowCsvDownload, setShareAllowCsvDownload] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const entityOptions = useMemo(
    () => (entityType === "account" ? builders.accounts : builders.campaigns),
    [builders.accounts, builders.campaigns, entityType],
  );

  useEffect(() => {
    if (entityOptions.some((item) => item.id === entityId)) {
      return;
    }

    setEntityId(entityOptions[0]?.id ?? "");
  }, [entityId, entityOptions]);

  useEffect(() => {
    setDeliveryChannel(deliveryCapabilities?.real_email_available ? "email" : "email_stub");
  }, [deliveryCapabilities?.real_email_available]);

  const parsedRecipients = recipients
    .split(/[\n,;]+/)
    .map((item) => item.trim())
    .filter(Boolean);

  const selectedEntity = entityOptions.find((item) => item.id === entityId) ?? null;
  const isDisabled = entityOptions.length === 0 || isSubmitting;

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!entityId || parsedRecipients.length === 0) {
      setError("Hedef kayit ve en az bir alici zorunlu.");
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setMessage(null);

    try {
      const response = await apiRequest<{
        data: {
          template_created: boolean;
          template_name: string;
          schedule_id: string;
        };
      }>("/reports/delivery-setups", {
        method: "POST",
        requireWorkspace: true,
        body: {
          entity_type: entityType,
          entity_id: entityId,
          template_name: templateName.trim() || null,
          default_range_days: Number(defaultRangeDays),
          layout_preset: "client_digest",
          delivery_channel: deliveryChannel,
          cadence,
          weekday: cadence === "weekly" ? Number(weekday) : null,
          month_day: cadence === "monthly" ? Number(monthDay) : null,
          send_time: sendTime,
          timezone,
          recipients: parsedRecipients,
          auto_share_enabled: autoShareEnabled,
          share_label_template: autoShareEnabled ? shareLabelTemplate.trim() || null : null,
          share_expires_in_days: autoShareEnabled ? Number(shareExpiresInDays) : null,
          share_allow_csv_download: autoShareEnabled ? shareAllowCsvDownload : false,
        },
      });

      setMessage(
        response.data.template_created
          ? `Yeni sablon ve schedule olusturuldu: ${response.data.template_name}`
          : `Mevcut sablon kullanilarak schedule olusturuldu: ${response.data.template_name}`,
      );

      await onCreated?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Rapor teslim plani olusturulamadi.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form className="space-y-4" onSubmit={handleSubmit}>
      <div className="grid gap-3 md:grid-cols-2">
        <Field label="Kapsam">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={entityType}
            onChange={(event) => setEntityType(event.target.value as "account" | "campaign")}
          >
            <option value="campaign">Kampanya Bazli</option>
            <option value="account">Reklam Hesabi Bazli</option>
          </select>
        </Field>

        <Field label={entityType === "campaign" ? "Kampanya" : "Reklam Hesabi"}>
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

        <Field label="Musteri Rapor Adi">
          <input
            type="text"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={templateName}
            onChange={(event) => setTemplateName(event.target.value)}
            placeholder={selectedEntity ? `${selectedEntity.name} / Musteri Raporu` : "Musteri Raporu"}
          />
        </Field>

        <Field label="Rapor Araligi">
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

        <Field label="Teslim Kanali">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={deliveryChannel}
            onChange={(event) => setDeliveryChannel(event.target.value as "email_stub" | "email")}
          >
            <option value="email" disabled={!deliveryCapabilities?.real_email_available}>Gercek Email</option>
            <option value="email_stub">Email Stub</option>
          </select>
        </Field>

        <Field label="Siklik">
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

      <Field label="Musteri Alicilari">
        <textarea
          className="min-h-[96px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
          value={recipients}
          onChange={(event) => setRecipients(event.target.value)}
          placeholder="musteri@ornek.com, ekip@ornek.com"
        />
      </Field>

      <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
        <p className="font-semibold">Mail Delivery Durumu</p>
        <p className="mt-1 muted-text">
          Mailer: {deliveryCapabilities?.default_mailer ?? "-"}
          {deliveryCapabilities?.from_address ? ` / From: ${deliveryCapabilities.from_address}` : ""}
        </p>
        <p className="mt-1 muted-text">{deliveryCapabilities?.note ?? "Mailer durumu okunamadi."}</p>
      </div>

      <div className="rounded-lg border border-[var(--border)] p-3">
        <p className="text-xs font-semibold uppercase tracking-wide muted-text">Musteri Paylasim Linki</p>
        <label className="mt-2 flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={autoShareEnabled}
            onChange={(event) => setAutoShareEnabled(event.target.checked)}
          />
          Her rapor tesliminde musteriye acilabilir paylasim linki de olustur
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
      </div>

      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}

      <Button type="submit" disabled={isDisabled}>
        {isSubmitting ? "Kuruluyor..." : "Musteri Rapor Teslimi Kur"}
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
