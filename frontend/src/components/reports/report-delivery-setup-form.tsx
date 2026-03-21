"use client";

import { type ReactNode, useCallback, useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportContactListItem, ReportDeliveryProfileListItem, ReportIndexResponse } from "@/lib/types";

type Props = {
  builders: ReportIndexResponse["data"]["builders"];
  deliveryCapabilities: ReportIndexResponse["data"]["delivery_capabilities"] | null;
  contacts: ReportContactListItem[];
  recipientPresets: ReportIndexResponse["data"]["recipient_presets"];
  deliveryProfiles: ReportIndexResponse["data"]["delivery_profiles"];
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

export function ReportDeliverySetupForm({
  builders,
  deliveryCapabilities,
  contacts,
  recipientPresets,
  deliveryProfiles,
  onCreated,
}: Props) {
  const [entityType, setEntityType] = useState<"account" | "campaign">(
    builders.campaigns.length > 0 ? "campaign" : "account",
  );
  const [entityId, setEntityId] = useState("");
  const [recipientPresetId, setRecipientPresetId] = useState("");
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
  const [recipients, setRecipients] = useState("");
  const [contactTags, setContactTags] = useState<string[]>([]);
  const [autoShareEnabled, setAutoShareEnabled] = useState(true);
  const [shareLabelTemplate, setShareLabelTemplate] = useState("{template_name} / {end_date}");
  const [shareExpiresInDays, setShareExpiresInDays] = useState("7");
  const [shareAllowCsvDownload, setShareAllowCsvDownload] = useState(false);
  const [saveAsDefaultProfile, setSaveAsDefaultProfile] = useState(true);
  const [loadedProfileId, setLoadedProfileId] = useState<string | null>(null);
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

  const availableContactTags = useMemo(
    () =>
      Array.from(
        new Set(
          contacts
            .filter((item) => item.is_active)
            .flatMap((item) => item.tags),
        ),
      ).sort((left, right) => left.localeCompare(right, "tr")),
    [contacts],
  );

  const selectedEntity = entityOptions.find((item) => item.id === entityId) ?? null;
  const selectedPreset = recipientPresets.find((item) => item.id === recipientPresetId) ?? null;
  const selectedProfile = useMemo(
    () => deliveryProfiles.find((item) => item.entity_type === entityType && item.entity_id === entityId) ?? null,
    [deliveryProfiles, entityId, entityType],
  );
  const mergedContactTags = useMemo(
    () => Array.from(new Set([...(selectedPreset?.contact_tags ?? []), ...contactTags])),
    [contactTags, selectedPreset?.contact_tags],
  );

  const taggedContacts = useMemo(
    () =>
      mergedContactTags.length === 0
        ? []
        : contacts.filter(
            (item) => item.is_active && item.tags.some((tag) => mergedContactTags.includes(tag)),
          ),
    [contacts, mergedContactTags],
  );

  const resolvedRecipientPreview = useMemo(
    () =>
      Array.from(
        new Set([
          ...parsedRecipients.map((item) => item.toLowerCase()),
          ...(selectedPreset?.recipients ?? []).map((item) => item.toLowerCase()),
          ...taggedContacts.map((item) => item.email.toLowerCase()),
        ]),
      ),
    [parsedRecipients, selectedPreset?.recipients, taggedContacts],
  );
  const isDisabled = entityOptions.length === 0 || isSubmitting;

  const resetProfileDefaults = useCallback(() => {
    setRecipientPresetId("");
    setDefaultRangeDays("7");
    setDeliveryChannel(deliveryCapabilities?.real_email_available ? "email" : "email_stub");
    setCadence("weekly");
    setWeekday("1");
    setMonthDay("1");
    setSendTime("09:00");
    setTimezone("Europe/Istanbul");
    setRecipients("");
    setContactTags([]);
    setAutoShareEnabled(true);
    setShareLabelTemplate("{template_name} / {end_date}");
    setShareExpiresInDays("7");
    setShareAllowCsvDownload(false);
    setSaveAsDefaultProfile(true);
  }, [deliveryCapabilities?.real_email_available]);

  const applyProfile = useCallback((profile: ReportDeliveryProfileListItem) => {
    setDefaultRangeDays(String(profile.default_range_days));
    setDeliveryChannel(
      profile.delivery_channel === "email" && !deliveryCapabilities?.real_email_available
        ? "email_stub"
        : (profile.delivery_channel as "email_stub" | "email"),
    );
    setCadence(profile.cadence as "daily" | "weekly" | "monthly");
    setWeekday(String(profile.weekday ?? 1));
    setMonthDay(String(profile.month_day ?? 1));
    setSendTime(profile.send_time);
    setTimezone(profile.timezone);
    setRecipientPresetId(profile.recipient_preset_id ?? "");
    setRecipients(profile.recipients.join(", "));
    setContactTags(profile.contact_tags);
    setAutoShareEnabled(profile.share_delivery.enabled);
    setShareLabelTemplate(profile.share_delivery.label_template ?? "{template_name} / {end_date}");
    setShareExpiresInDays(String(profile.share_delivery.expires_in_days ?? 7));
    setShareAllowCsvDownload(profile.share_delivery.allow_csv_download);
    setSaveAsDefaultProfile(true);
    setLoadedProfileId(profile.id);
  }, [deliveryCapabilities?.real_email_available]);

  useEffect(() => {
    if (!selectedProfile) {
      if (loadedProfileId !== null) {
        resetProfileDefaults();
      }
      setLoadedProfileId(null);
      return;
    }

    if (selectedProfile.id === loadedProfileId) {
      return;
    }

    applyProfile(selectedProfile);
  }, [applyProfile, loadedProfileId, resetProfileDefaults, selectedProfile]);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!entityId || (!recipientPresetId && parsedRecipients.length === 0 && contactTags.length === 0)) {
      setError("Hedef kayit ve en az bir alici, alici grubu veya kisi etiketi zorunlu.");
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
          profile_saved: boolean;
        };
      }>("/reports/delivery-setups", {
        method: "POST",
        requireWorkspace: true,
        body: {
          entity_type: entityType,
          entity_id: entityId,
          recipient_preset_id: recipientPresetId || null,
          template_name: templateName.trim() || null,
          default_range_days: Number(defaultRangeDays),
          layout_preset: "client_digest",
          delivery_channel: deliveryChannel,
          cadence,
          weekday: cadence === "weekly" ? Number(weekday) : null,
          month_day: cadence === "monthly" ? Number(monthDay) : null,
          send_time: sendTime,
          timezone,
          recipients: parsedRecipients.length > 0 ? parsedRecipients : null,
          contact_tags: contactTags.length > 0 ? contactTags : null,
          save_as_default_profile: saveAsDefaultProfile,
          auto_share_enabled: autoShareEnabled,
          share_label_template: autoShareEnabled ? shareLabelTemplate.trim() || null : null,
          share_expires_in_days: autoShareEnabled ? Number(shareExpiresInDays) : null,
          share_allow_csv_download: autoShareEnabled ? shareAllowCsvDownload : false,
        },
      });

      setMessage(
        response.data.template_created
          ? `Yeni sablon ve schedule olusturuldu: ${response.data.template_name}${response.data.profile_saved ? " / varsayilan profil de kaydedildi." : ""}`
          : `Mevcut sablon kullanilarak schedule olusturuldu: ${response.data.template_name}${response.data.profile_saved ? " / varsayilan profil guncellendi." : ""}`,
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

        <Field label="Kayitli Alici Grubu">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={recipientPresetId}
            onChange={(event) => setRecipientPresetId(event.target.value)}
          >
            <option value="">Ozel Alici Girisi</option>
            {recipientPresets
              .filter((item) => item.is_active)
              .map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name} ({item.resolved_recipients_count})
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

      <Field label="Ek Manuel Alicilar">
        <textarea
          className="min-h-[96px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
          value={recipients}
          onChange={(event) => setRecipients(event.target.value)}
          placeholder="musteri@ornek.com, ekip@ornek.com"
        />
      </Field>

      {selectedPreset ? (
        <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
          <p className="font-semibold">Secili Alici Grubu</p>
          <p className="mt-1 muted-text">{selectedPreset.recipient_group_summary.label}</p>
          <p className="mt-1 text-xs muted-text">
            Statik: {selectedPreset.recipient_group_summary.static_recipients_count} / Dinamik: {selectedPreset.recipient_group_summary.dynamic_contacts_count} / Cozumlenen: {selectedPreset.resolved_recipients_count}
          </p>
          {selectedPreset.contact_tags.length > 0 ? (
            <p className="mt-1 text-xs muted-text">Etiketler: {selectedPreset.contact_tags.join(", ")}</p>
          ) : null}
        </div>
      ) : null}

      {availableContactTags.length > 0 ? (
        <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
          <p className="font-semibold">Ek Kisi Segmentleri</p>
          <p className="mt-1 muted-text">
            Secilen etiketler, varsa kayitli grup etiketleriyle birlesip aktif kisi havuzundan dinamik alici cozer.
          </p>
          <div className="mt-2 flex flex-wrap gap-2">
            {availableContactTags.map((tag) => {
              const isSelected = contactTags.includes(tag);

              return (
                <button
                  key={tag}
                  type="button"
                  className={`rounded-full border px-3 py-1 text-xs font-medium ${
                    isSelected
                      ? "border-[var(--accent)] bg-[var(--surface-2)] text-[var(--accent)]"
                      : "border-[var(--border)] hover:border-[var(--accent)] hover:text-[var(--accent)]"
                  }`}
                  onClick={() =>
                    setContactTags((current) =>
                      current.includes(tag) ? current.filter((item) => item !== tag) : [...current, tag],
                    )
                  }
                >
                  {tag}
                </button>
              );
            })}
          </div>
          {mergedContactTags.length > 0 ? (
            <p className="mt-2 text-xs muted-text">
              Eslesen kisi: {taggedContacts.length} / Toplam cozumlenen alici: {resolvedRecipientPreview.length}
            </p>
          ) : null}
        </div>
      ) : null}

      {contacts.filter((item) => item.is_active).length > 0 ? (
        <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
          <p className="font-semibold">Kisi Havuzundan Alici Ekle</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {contacts
              .filter((item) => item.is_active)
              .slice(0, 10)
              .map((contact) => (
                <button
                  key={contact.id}
                  type="button"
                  className="rounded-full border border-[var(--border)] px-3 py-1 text-xs font-medium hover:border-[var(--accent)] hover:text-[var(--accent)]"
                  onClick={() => {
                    const existing = recipients
                      .split(/[\n,;]+/)
                      .map((item) => item.trim().toLowerCase())
                      .filter(Boolean);

                    if (existing.includes(contact.email.toLowerCase())) {
                      return;
                    }

                    setRecipients((current) => {
                      const normalized = current.trim();
                      return normalized === "" ? contact.email : `${normalized}, ${contact.email}`;
                    });
                  }}
                >
                  {contact.name}
                </button>
              ))}
          </div>
        </div>
      ) : null}

      {mergedContactTags.length > 0 ? (
        <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
          <p className="font-semibold">Etiket Eslesme Onizlemesi</p>
          <p className="mt-1 muted-text">
            {taggedContacts.length > 0
              ? taggedContacts.map((contact) => `${contact.name} <${contact.email}>`).join(", ")
              : "Secili etiketlerle eslesen aktif kisi yok."}
          </p>
        </div>
      ) : null}

      {selectedProfile ? (
        <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
          <p className="font-semibold">Varsayilan Profil Yuklendi</p>
          <p className="mt-1 muted-text">
            {selectedProfile.entity_label ?? "Varlik"} icin kayitli profil bulundu. Form alanlari bu profile gore dolduruldu.
          </p>
        </div>
      ) : null}

      <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
        <p className="font-semibold">Mail Delivery Durumu</p>
        <p className="mt-1 muted-text">
          Mailer: {deliveryCapabilities?.default_mailer ?? "-"}
          {deliveryCapabilities?.from_address ? ` / From: ${deliveryCapabilities.from_address}` : ""}
        </p>
        <p className="mt-1 muted-text">{deliveryCapabilities?.note ?? "Mailer durumu okunamadi."}</p>
      </div>

      <div className="rounded-lg border border-[var(--border)] p-3">
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={saveAsDefaultProfile}
            onChange={(event) => setSaveAsDefaultProfile(event.target.checked)}
          />
          {selectedProfile ? "Bu kapsam icin varsayilan teslim profilini guncelle" : "Bu kapsam icin varsayilan teslim profili olarak kaydet"}
        </label>
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
