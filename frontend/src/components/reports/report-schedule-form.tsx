"use client";

import { type ReactNode, useCallback, useEffect, useMemo, useState } from "react";
import { ReportRecipientGroupCatalog } from "@/components/reports/report-recipient-group-catalog";
import { useApiQuery } from "@/hooks/use-api-query";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { buildRecipientGroupSelectionPayload } from "@/lib/report-recipient-group-selection";
import {
  RecipientGroupCatalogItem,
  RecipientGroupSuggestionsResponse,
  ReportContactListItem,
  ReportIndexResponse,
  ReportTemplateListItem,
} from "@/lib/types";

type Props = {
  templates: ReportTemplateListItem[];
  contacts: ReportContactListItem[];
  recipientPresets: ReportIndexResponse["data"]["recipient_presets"];
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

export function ReportScheduleForm({ templates, contacts, recipientPresets, deliveryCapabilities, onCreated }: Props) {
  const activeTemplates = useMemo(
    () => templates.filter((item) => item.is_active),
    [templates],
  );

  const [templateId, setTemplateId] = useState(activeTemplates[0]?.id ?? "");
  const [deliveryChannel, setDeliveryChannel] = useState<"email_stub" | "email">(
    deliveryCapabilities?.real_email_available ? "email" : "email_stub",
  );
  const [cadence, setCadence] = useState<"daily" | "weekly" | "monthly">("weekly");
  const [weekday, setWeekday] = useState("1");
  const [monthDay, setMonthDay] = useState("1");
  const [sendTime, setSendTime] = useState("09:00");
  const [timezone, setTimezone] = useState("Europe/Istanbul");
  const [recipientPresetId, setRecipientPresetId] = useState("");
  const [recipients, setRecipients] = useState("");
  const [contactTags, setContactTags] = useState<string[]>([]);
  const [selectedRecipientGroupId, setSelectedRecipientGroupId] = useState("");
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

  const selectedPreset = recipientPresets.find((item) => item.id === recipientPresetId) ?? null;
  const selectedTemplate = activeTemplates.find((item) => item.id === templateId) ?? null;
  const recipientSuggestionPath = useMemo(() => {
    if (!selectedTemplate?.entity_type || !selectedTemplate.entity_id) {
      return null;
    }

    return `/reports/recipient-group-suggestions?entity_type=${selectedTemplate.entity_type}&entity_id=${selectedTemplate.entity_id}&limit=4`;
  }, [selectedTemplate?.entity_id, selectedTemplate?.entity_type]);
  const { data: suggestionData, isLoading: isSuggestionsLoading } = useApiQuery<
    RecipientGroupSuggestionsResponse,
    RecipientGroupSuggestionsResponse["data"]
  >(recipientSuggestionPath ?? "/reports/recipient-group-suggestions", {
    enabled: recipientSuggestionPath !== null,
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.recipientGroupSuggestions,
    select: (response) => response.data,
  });
  const suggestedGroups = useMemo(
    () => suggestionData?.suggested_groups ?? [],
    [suggestionData?.suggested_groups],
  );
  const selectedSuggestedGroup = useMemo(
    () => suggestedGroups.find((item) => item.id === selectedRecipientGroupId) ?? null,
    [selectedRecipientGroupId, suggestedGroups],
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

  const isDisabled = activeTemplates.length === 0 || isSubmitting;

  const resetRecipientSelection = useCallback(() => {
    setSelectedRecipientGroupId("");
    setRecipientPresetId("");
    setRecipients("");
    setContactTags([]);
  }, []);

  const applyRecipientGroup = useCallback((group: RecipientGroupCatalogItem) => {
    setSelectedRecipientGroupId(group.id);
    setError(null);
    setMessage(null);

    if (group.source_type === "preset" && group.recipient_preset_id) {
      setRecipientPresetId(group.recipient_preset_id);
      setRecipients("");
      setContactTags([]);

      return;
    }

    setRecipientPresetId("");
    setRecipients(group.recipients.join(", "));
    setContactTags(group.contact_tags);
  }, []);

  useEffect(() => {
    if (!templateId) {
      return;
    }

    resetRecipientSelection();
  }, [resetRecipientSelection, templateId]);

  useEffect(() => {
    if (suggestedGroups.length === 0) {
      return;
    }

    if (selectedRecipientGroupId || recipientPresetId || recipients.trim() !== "" || contactTags.length > 0) {
      return;
    }

    applyRecipientGroup(suggestedGroups[0]);
  }, [
    applyRecipientGroup,
    contactTags.length,
    recipientPresetId,
    recipients,
    selectedRecipientGroupId,
    suggestedGroups,
  ]);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!templateId || (!recipientPresetId && parsedRecipients.length === 0 && contactTags.length === 0)) {
      setError("Sablon ve en az bir alici, alici grubu veya kisi etiketi zorunlu.");
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setMessage(null);

    try {
      const recipientGroupSelection = buildRecipientGroupSelectionPayload({
        selectedSuggestedGroup,
        selectedPreset,
        parsedRecipients,
        contactTags,
      });

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
          recipient_preset_id: recipientPresetId || null,
          recipients: parsedRecipients.length > 0 ? parsedRecipients : null,
          contact_tags: contactTags.length > 0 ? contactTags : null,
          recipient_group_selection: recipientGroupSelection,
          delivery_channel: deliveryChannel,
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

        <Field label="Teslim Kanali">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={deliveryChannel}
            onChange={(event) => setDeliveryChannel(event.target.value as "email_stub" | "email")}
          >
            <option value="email_stub">Email Stub</option>
            <option value="email" disabled={!deliveryCapabilities?.real_email_available}>
              Gercek Email
            </option>
          </select>
        </Field>

        <Field label="Kayitli Alici Grubu">
          <select
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={recipientPresetId}
            onChange={(event) => {
              setSelectedRecipientGroupId("");
              setRecipientPresetId(event.target.value);
              setRecipients("");
              setContactTags([]);
            }}
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

      <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
        <p className="font-semibold">Ana Alici Secimi</p>
        <p className="mt-1 muted-text">
          Sablonun bagli oldugu hesap veya kampanya icin onerilen gruplardan secin. Manuel alici ve etiket ayarlari alt bolumde override icin durur.
        </p>
        <div className="mt-3">
          <ReportRecipientGroupCatalog
            items={suggestedGroups}
            emptyText={
              isSuggestionsLoading
                ? "Onerilen alici gruplari hazirlaniyor."
                : "Bu sablon kapsami icin onerilen alici grubu bulunamadi."
            }
            onApply={applyRecipientGroup}
          />
        </div>
        {selectedSuggestedGroup ? (
          <div className="mt-3 rounded-lg border border-[var(--border)] bg-[var(--surface-2)] p-3">
            <p className="font-semibold">Secili Grup</p>
            <p className="mt-1 muted-text">{selectedSuggestedGroup.name}</p>
            <p className="mt-1 text-xs muted-text">{selectedSuggestedGroup.recipient_group_summary.label}</p>
          </div>
        ) : null}
      </div>

      {selectedPreset ? (
        <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
          <p className="font-semibold">Secili Alici Grubu</p>
          <p className="mt-1 muted-text">{selectedPreset.recipient_group_summary.label}</p>
          <p className="mt-1 text-xs muted-text">
            Statik: {selectedPreset.recipient_group_summary.static_recipients_count} / Dinamik: {selectedPreset.recipient_group_summary.dynamic_contacts_count} / Cozumlenen: {selectedPreset.resolved_recipients_count}
          </p>
        </div>
      ) : null}

      <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
        <p className="font-semibold">Ileri Seviye Alici Ayarlari</p>
        <p className="mt-1 muted-text">
          Onerilen grup yeterli degilse kayitli grup degistirin, ek kisi segmenti secin veya manuel alici girin.
        </p>

        <div className="mt-3 space-y-3">
          <Field label="Ek Manuel Alicilar">
            <textarea
              className="min-h-[96px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
              value={recipients}
              onChange={(event) => {
                setSelectedRecipientGroupId("");
                setRecipients(event.target.value);
              }}
              placeholder="client@castintech.com, ops@castintech.com"
            />
          </Field>

          {availableContactTags.length > 0 ? (
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide muted-text">Ek Kisi Segmentleri</p>
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
                      onClick={() => {
                        setSelectedRecipientGroupId("");
                        setContactTags((current) =>
                          current.includes(tag) ? current.filter((item) => item !== tag) : [...current, tag],
                        );
                      }}
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
        </div>
      </div>

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

      <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
        <p className="font-semibold">Mail Delivery Durumu</p>
        <p className="mt-1 muted-text">
          Mailer: {deliveryCapabilities?.default_mailer ?? "-"}
          {deliveryCapabilities?.from_address ? ` / From: ${deliveryCapabilities.from_address}` : ""}
        </p>
        <p className="mt-1 muted-text">{deliveryCapabilities?.note ?? "Mailer durumu okunamadi."}</p>
      </div>

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
