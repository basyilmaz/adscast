"use client";

import Link from "next/link";
import { type ReactNode, useEffect, useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { PageLoadingState } from "@/components/ui/page-state";
import { useApiQuery } from "@/hooks/use-api-query";
import { apiRequest } from "@/lib/api";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { ReportDeliveryProfileListItem, ReportIndexResponse } from "@/lib/types";

type Props = {
  entityType: "account" | "campaign";
  entityId: string;
  currentProfile: ReportDeliveryProfileListItem | null;
  reportCenterHref: string;
  onChanged?: () => Promise<void> | void;
};

type DeliveryOptions = {
  recipientPresets: ReportIndexResponse["data"]["recipient_presets"];
  deliveryCapabilities: ReportIndexResponse["data"]["delivery_capabilities"];
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

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block space-y-1">
      <span className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</span>
      {children}
    </label>
  );
}

export function ReportDeliveryProfileManager({
  entityType,
  entityId,
  currentProfile,
  reportCenterHref,
  onChanged,
}: Props) {
  const [isEditing, setIsEditing] = useState(false);
  const [recipientPresetId, setRecipientPresetId] = useState("");
  const [defaultRangeDays, setDefaultRangeDays] = useState("7");
  const [deliveryChannel, setDeliveryChannel] = useState<"email_stub" | "email">("email_stub");
  const [cadence, setCadence] = useState<"daily" | "weekly" | "monthly">("weekly");
  const [weekday, setWeekday] = useState("1");
  const [monthDay, setMonthDay] = useState("1");
  const [sendTime, setSendTime] = useState("09:00");
  const [timezone, setTimezone] = useState("Europe/Istanbul");
  const [recipients, setRecipients] = useState("");
  const [autoShareEnabled, setAutoShareEnabled] = useState(true);
  const [shareLabelTemplate, setShareLabelTemplate] = useState("{template_name} / {end_date}");
  const [shareExpiresInDays, setShareExpiresInDays] = useState("7");
  const [shareAllowCsvDownload, setShareAllowCsvDownload] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState<null | "save" | "toggle" | "delete">(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const { data: options, isLoading: isOptionsLoading } = useApiQuery<ReportIndexResponse, DeliveryOptions>("/reports", {
    enabled: isEditing,
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.reports,
    select: (response) => ({
      recipientPresets: response.data.recipient_presets,
      deliveryCapabilities: response.data.delivery_capabilities,
    }),
  });

  const visiblePresets = useMemo(() => {
    return (options?.recipientPresets ?? []).filter(
      (item) => item.is_active || item.id === currentProfile?.recipient_preset_id,
    );
  }, [currentProfile?.recipient_preset_id, options?.recipientPresets]);
  const selectedPreset = visiblePresets.find((item) => item.id === recipientPresetId) ?? null;

  useEffect(() => {
    if (!isEditing) {
      return;
    }

    if (currentProfile) {
      setRecipientPresetId(currentProfile.recipient_preset_id ?? "");
      setDefaultRangeDays(String(currentProfile.default_range_days));
      setDeliveryChannel(
        currentProfile.delivery_channel === "email" && !options?.deliveryCapabilities.real_email_available
          ? "email_stub"
          : (currentProfile.delivery_channel as "email_stub" | "email"),
      );
      setCadence(currentProfile.cadence as "daily" | "weekly" | "monthly");
      setWeekday(String(currentProfile.weekday ?? 1));
      setMonthDay(String(currentProfile.month_day ?? 1));
      setSendTime(currentProfile.send_time);
      setTimezone(currentProfile.timezone);
      setRecipients(currentProfile.recipients.join(", "));
      setAutoShareEnabled(currentProfile.share_delivery.enabled);
      setShareLabelTemplate(currentProfile.share_delivery.label_template ?? "{template_name} / {end_date}");
      setShareExpiresInDays(String(currentProfile.share_delivery.expires_in_days ?? 7));
      setShareAllowCsvDownload(currentProfile.share_delivery.allow_csv_download);

      return;
    }

    setRecipientPresetId("");
    setDefaultRangeDays("7");
    setDeliveryChannel(options?.deliveryCapabilities.real_email_available ? "email" : "email_stub");
    setCadence("weekly");
    setWeekday("1");
    setMonthDay("1");
    setSendTime("09:00");
    setTimezone("Europe/Istanbul");
    setRecipients("");
    setAutoShareEnabled(true);
    setShareLabelTemplate("{template_name} / {end_date}");
    setShareExpiresInDays("7");
    setShareAllowCsvDownload(false);
  }, [currentProfile, isEditing, options?.deliveryCapabilities.real_email_available]);

  useEffect(() => {
    if (!selectedPreset) {
      return;
    }

    setRecipients(selectedPreset.recipients.join(", "));
  }, [selectedPreset]);

  const parsedRecipients = recipients
    .split(/[\n,;]+/)
    .map((item) => item.trim())
    .filter(Boolean);

  const closeEditor = () => {
    setIsEditing(false);
    setMessage(null);
    setError(null);
  };

  const handleSave = async () => {
    if (!recipientPresetId && parsedRecipients.length === 0) {
      setError("En az bir alici veya kayitli alici listesi secilmelidir.");
      return;
    }

    setIsSubmitting("save");
    setError(null);
    setMessage(null);

    try {
      const response = await apiRequest<{ data: ReportDeliveryProfileListItem }>(
        `/reports/delivery-profiles/${entityType}/${entityId}`,
        {
          method: "PUT",
          requireWorkspace: true,
          body: {
            recipient_preset_id: recipientPresetId || null,
            delivery_channel: deliveryChannel,
            cadence,
            weekday: cadence === "weekly" ? Number(weekday) : null,
            month_day: cadence === "monthly" ? Number(monthDay) : null,
            send_time: sendTime,
            timezone,
            default_range_days: Number(defaultRangeDays),
            layout_preset: "client_digest",
            recipients: parsedRecipients.length > 0 ? parsedRecipients : null,
            auto_share_enabled: autoShareEnabled,
            share_label_template: autoShareEnabled ? shareLabelTemplate.trim() || null : null,
            share_expires_in_days: autoShareEnabled ? Number(shareExpiresInDays) : null,
            share_allow_csv_download: autoShareEnabled ? shareAllowCsvDownload : false,
            is_active: currentProfile?.is_active ?? true,
          },
        },
      );

      setMessage(
        response.data.is_active
          ? "Varsayilan teslim profili kaydedildi."
          : "Profil guncellendi ancak pasif durumda tutuldu.",
      );
      setIsEditing(false);
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Teslim profili kaydedilemedi.");
    } finally {
      setIsSubmitting(null);
    }
  };

  const handleToggle = async () => {
    if (!currentProfile) {
      return;
    }

    setIsSubmitting("toggle");
    setError(null);
    setMessage(null);

    try {
      const response = await apiRequest<{ data: ReportDeliveryProfileListItem }>(
        `/reports/delivery-profiles/${entityType}/${entityId}/toggle`,
        {
          method: "POST",
          requireWorkspace: true,
          body: {
            is_active: !currentProfile.is_active,
          },
        },
      );

      setMessage(response.data.is_active ? "Teslim profili tekrar aktif edildi." : "Teslim profili pasife alindi.");
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Teslim profili durumu guncellenemedi.");
    } finally {
      setIsSubmitting(null);
    }
  };

  const handleDelete = async () => {
    if (!currentProfile) {
      return;
    }

    setIsSubmitting("delete");
    setError(null);
    setMessage(null);

    try {
      await apiRequest(`/reports/delivery-profiles/${entityType}/${entityId}`, {
        method: "DELETE",
        requireWorkspace: true,
      });

      setMessage("Teslim profili kaldirildi.");
      setIsEditing(false);
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Teslim profili silinemedi.");
    } finally {
      setIsSubmitting(null);
    }
  };

  return (
    <div className="mt-3 space-y-3 text-sm">
      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}

      {!isEditing ? (
        <>
          {currentProfile ? (
            <>
              <div className="flex flex-wrap gap-2">
                <Badge label={currentProfile.cadence_label} variant="neutral" />
                <Badge label={currentProfile.delivery_channel_label} variant="neutral" />
                <Badge label={currentProfile.is_active ? "active" : "inactive"} variant={currentProfile.is_active ? "success" : "warning"} />
                {currentProfile.share_delivery.enabled ? <Badge label="Auto Share" variant="success" /> : null}
              </div>
              <p className="muted-text">
                Alicilar: {currentProfile.recipient_preset_name ?? currentProfile.recipients.join(", ")}
              </p>
              <p className="muted-text">
                {currentProfile.default_range_days} gun / {currentProfile.timezone}
              </p>
              <div className="flex flex-wrap gap-2">
                <Button type="button" size="sm" variant="secondary" onClick={() => setIsEditing(true)} disabled={isSubmitting !== null}>
                  Duzenle
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={() => void handleToggle()} disabled={isSubmitting !== null}>
                  {isSubmitting === "toggle" ? "Guncelleniyor..." : currentProfile.is_active ? "Pasife Al" : "Aktif Et"}
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={() => void handleDelete()} disabled={isSubmitting !== null}>
                  {isSubmitting === "delete" ? "Siliniyor..." : "Kaldir"}
                </Button>
              </div>
            </>
          ) : (
            <>
              <p className="muted-text">Bu kayit icin kayitli varsayilan teslim profili yok.</p>
              <Button type="button" size="sm" variant="secondary" onClick={() => setIsEditing(true)}>
                Profil Olustur
              </Button>
            </>
          )}
          <Link href={reportCenterHref} className="inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
            Rapor merkezinde yonet
          </Link>
        </>
      ) : (
        <>
          {isOptionsLoading && !options ? (
            <PageLoadingState title="Teslim profili hazirlaniyor" detail="Kayitli alici listeleri getiriliyor." />
          ) : (
            <div className="space-y-4 rounded-lg border border-[var(--border)] p-4">
              <div className="grid gap-3 md:grid-cols-2">
                <Field label="Kayitli Alici Listesi">
                  <select
                    className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                    value={recipientPresetId}
                    onChange={(event) => setRecipientPresetId(event.target.value)}
                  >
                    <option value="">Ozel Alici Girisi</option>
                    {visiblePresets.map((preset) => (
                      <option key={preset.id} value={preset.id}>
                        {preset.name} ({preset.recipients_count})
                      </option>
                    ))}
                  </select>
                </Field>

                <Field label="Teslim Kanali">
                  <select
                    className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                    value={deliveryChannel}
                    onChange={(event) => setDeliveryChannel(event.target.value as "email_stub" | "email")}
                  >
                    <option value="email" disabled={!options?.deliveryCapabilities.real_email_available}>
                      Gercek Email
                    </option>
                    <option value="email_stub">Email Stub</option>
                  </select>
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
                  className="min-h-[84px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
                  value={recipients}
                  onChange={(event) => setRecipients(event.target.value)}
                  placeholder="musteri@ornek.com, ekip@ornek.com"
                />
              </Field>

              <p className="text-xs muted-text">
                {options?.deliveryCapabilities.real_email_available
                  ? `Mailer hazir: ${options.deliveryCapabilities.from_address ?? "tanimsiz"}`
                  : "Gercek email gonderimi su an hazir degil. Profil email_stub ile kaydedilebilir."}
              </p>

              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={autoShareEnabled}
                  onChange={(event) => setAutoShareEnabled(event.target.checked)}
                />
                Otomatik musteri paylasim linki uret
              </label>

              {autoShareEnabled ? (
                <div className="grid gap-3 md:grid-cols-2">
                  <Field label="Paylasim Etiketi">
                    <input
                      type="text"
                      className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                      value={shareLabelTemplate}
                      onChange={(event) => setShareLabelTemplate(event.target.value)}
                    />
                  </Field>
                  <Field label="Link Gecerlilik (gun)">
                    <input
                      type="number"
                      min={1}
                      max={30}
                      className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                      value={shareExpiresInDays}
                      onChange={(event) => setShareExpiresInDays(event.target.value)}
                    />
                  </Field>
                  <label className="flex items-center gap-2 text-sm md:col-span-2">
                    <input
                      type="checkbox"
                      checked={shareAllowCsvDownload}
                      onChange={(event) => setShareAllowCsvDownload(event.target.checked)}
                    />
                    Paylasilan linkte CSV indirimi acik olsun
                  </label>
                </div>
              ) : null}

              <div className="flex flex-wrap gap-2">
                <Button type="button" onClick={() => void handleSave()} disabled={isSubmitting !== null}>
                  {isSubmitting === "save" ? "Kaydediliyor..." : currentProfile ? "Profili Kaydet" : "Profili Olustur"}
                </Button>
                <Button type="button" variant="secondary" onClick={closeEditor} disabled={isSubmitting !== null}>
                  Vazgec
                </Button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
