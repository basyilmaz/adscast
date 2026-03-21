"use client";

import { useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ReportRecipientTemplateProfileFields } from "@/components/reports/report-recipient-template-profile-fields";
import { apiRequest } from "@/lib/api";
import { ReportContactListItem, ReportRecipientPresetListItem } from "@/lib/types";

type Props = {
  presets: ReportRecipientPresetListItem[];
  contacts: ReportContactListItem[];
  onChanged?: () => Promise<void> | void;
};

export function ReportRecipientPresetManager({ presets, contacts, onChanged }: Props) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [name, setName] = useState("");
  const [recipients, setRecipients] = useState("");
  const [contactTags, setContactTags] = useState<string[]>([]);
  const [templateKind, setTemplateKind] = useState("client_reporting");
  const [targetEntityTypes, setTargetEntityTypes] = useState<string[]>([]);
  const [matchingCompaniesInput, setMatchingCompaniesInput] = useState("");
  const [priority, setPriority] = useState(50);
  const [isRecommendedDefault, setIsRecommendedDefault] = useState(false);
  const [notes, setNotes] = useState("");
  const [isSubmitting, setIsSubmitting] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const sortedPresets = useMemo(
    () => [...presets].sort((left, right) => Number(right.is_active) - Number(left.is_active) || left.name.localeCompare(right.name)),
    [presets],
  );

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

  const parsedRecipients = recipients
    .split(/[\n,;]+/)
    .map((item) => item.trim())
    .filter(Boolean);

  const taggedContacts = useMemo(
    () =>
      contactTags.length === 0
        ? []
        : contacts.filter(
            (item) => item.is_active && item.tags.some((tag) => contactTags.includes(tag)),
          ),
    [contactTags, contacts],
  );

  const resolvedRecipients = useMemo(
    () =>
      Array.from(
        new Set([
          ...parsedRecipients.map((item) => item.toLowerCase()),
          ...taggedContacts.map((item) => item.email.toLowerCase()),
        ]),
      ),
    [parsedRecipients, taggedContacts],
  );

  const startEdit = (preset: ReportRecipientPresetListItem) => {
    setEditingId(preset.id);
    setName(preset.name);
    setRecipients(preset.recipients.join(", "));
    setContactTags(preset.contact_tags);
    setTemplateKind(preset.template_profile.kind);
    setTargetEntityTypes(preset.template_profile.target_entity_types);
    setMatchingCompaniesInput(preset.template_profile.matching_companies.join(", "));
    setPriority(preset.template_profile.priority);
    setIsRecommendedDefault(preset.template_profile.is_recommended_default);
    setNotes(preset.notes ?? "");
    setMessage(null);
    setError(null);
  };

  const cancelEdit = () => {
    setEditingId(null);
    setName("");
    setRecipients("");
    setContactTags([]);
    setTemplateKind("client_reporting");
    setTargetEntityTypes([]);
    setMatchingCompaniesInput("");
    setPriority(50);
    setIsRecommendedDefault(false);
    setNotes("");
  };

  const matchingCompanies = useMemo(
    () =>
      Array.from(
        new Set(
          matchingCompaniesInput
            .split(/[,\n;]+/)
            .map((item) => item.trim())
            .filter(Boolean),
        ),
      ),
    [matchingCompaniesInput],
  );

  const handleUpdate = async (presetId: string) => {
    if (!name.trim() || (parsedRecipients.length === 0 && contactTags.length === 0)) {
      setError("Grup adi ve en az bir statik alici veya kisi etiketi zorunlu.");
      return;
    }

    setIsSubmitting(`update:${presetId}`);
    setError(null);
    setMessage(null);

    try {
      await apiRequest(`/reports/recipient-presets/${presetId}`, {
        method: "PUT",
        requireWorkspace: true,
        body: {
          name: name.trim(),
          recipients: parsedRecipients.length > 0 ? parsedRecipients : null,
          contact_tags: contactTags.length > 0 ? contactTags : null,
          template_kind: templateKind,
          target_entity_types: targetEntityTypes.length > 0 ? targetEntityTypes : null,
          matching_companies: matchingCompanies.length > 0 ? matchingCompanies : null,
          priority,
          is_recommended_default: isRecommendedDefault,
          notes: notes.trim() || null,
        },
      });

      setMessage("Alici grubu guncellendi.");
      cancelEdit();
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Alici grubu guncellenemedi.");
    } finally {
      setIsSubmitting(null);
    }
  };

  const handleToggle = async (preset: ReportRecipientPresetListItem) => {
    setIsSubmitting(`toggle:${preset.id}`);
    setError(null);
    setMessage(null);

    try {
      await apiRequest(`/reports/recipient-presets/${preset.id}/toggle`, {
        method: "POST",
        requireWorkspace: true,
        body: {
          is_active: !preset.is_active,
        },
      });

      setMessage(preset.is_active ? "Alici grubu pasife alindi." : "Alici grubu tekrar aktif edildi.");
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Alici grubu guncellenemedi.");
    } finally {
      setIsSubmitting(null);
    }
  };

  const handleDelete = async (preset: ReportRecipientPresetListItem) => {
    setIsSubmitting(`delete:${preset.id}`);
    setError(null);
    setMessage(null);

    try {
      await apiRequest(`/reports/recipient-presets/${preset.id}`, {
        method: "DELETE",
        requireWorkspace: true,
      });

      setMessage("Alici grubu silindi.");
      if (editingId === preset.id) {
        cancelEdit();
      }
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Alici grubu silinemedi.");
    } finally {
      setIsSubmitting(null);
    }
  };

  return (
    <div className="space-y-3">
      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}

      {sortedPresets.map((preset) => (
        <div key={preset.id} className="rounded-lg border border-[var(--border)] p-3">
          <div className="flex flex-wrap items-center gap-2">
            <Badge label={preset.is_active ? "active" : "inactive"} variant={preset.is_active ? "success" : "warning"} />
            <Badge label={`${preset.recipient_group_summary.static_recipients_count} statik`} variant="neutral" />
            <Badge label={`${preset.recipient_group_summary.dynamic_contacts_count} dinamik`} variant="neutral" />
            <Badge label={`${preset.resolved_recipients_count} cozumlenen`} variant="neutral" />
            <Badge label={preset.template_profile.kind_label} variant="neutral" />
            {preset.template_profile.is_recommended_default ? <Badge label="Varsayilan Oneri" variant="success" /> : null}
          </div>

          {editingId === preset.id ? (
            <div className="mt-3 space-y-3">
              <input
                type="text"
                className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                value={name}
                onChange={(event) => setName(event.target.value)}
              />
              <textarea
                className="min-h-[84px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
                value={recipients}
                onChange={(event) => setRecipients(event.target.value)}
                placeholder="musteri@ornek.com, ekip@ornek.com"
              />
              <ReportRecipientTemplateProfileFields
                templateKind={templateKind}
                onTemplateKindChange={setTemplateKind}
                targetEntityTypes={targetEntityTypes}
                onTargetEntityTypesChange={setTargetEntityTypes}
                matchingCompaniesInput={matchingCompaniesInput}
                onMatchingCompaniesInputChange={setMatchingCompaniesInput}
                priority={priority}
                onPriorityChange={setPriority}
                isRecommendedDefault={isRecommendedDefault}
                onIsRecommendedDefaultChange={setIsRecommendedDefault}
                contacts={contacts}
              />
              {availableContactTags.length > 0 ? (
                <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
                  <p className="font-semibold">Kisi Segmentleri</p>
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
                  {contactTags.length > 0 ? (
                    <p className="mt-2 text-xs muted-text">
                      Eslesen kisi: {taggedContacts.length} / Toplam cozumlenen alici: {resolvedRecipients.length}
                    </p>
                  ) : null}
                </div>
              ) : null}
              <input
                type="text"
                className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                placeholder="Not"
              />
              <div className="flex flex-wrap gap-2">
                <Button type="button" onClick={() => void handleUpdate(preset.id)} disabled={isSubmitting !== null}>
                  {isSubmitting === `update:${preset.id}` ? "Kaydediliyor..." : "Kaydet"}
                </Button>
                <Button type="button" variant="secondary" onClick={cancelEdit} disabled={isSubmitting !== null}>
                  Vazgec
                </Button>
              </div>
            </div>
          ) : (
            <>
              <p className="mt-2 font-semibold">{preset.name}</p>
              <p className="mt-1 text-sm muted-text">{preset.recipient_group_summary.label}</p>
              <p className="mt-1 text-xs muted-text">
                Statik: {preset.recipient_group_summary.static_recipients_count} / Dinamik: {preset.recipient_group_summary.dynamic_contacts_count}
              </p>
              <p className="mt-1 text-xs muted-text">
                {preset.template_rule_summary.entity_scope_label} / {preset.template_rule_summary.company_scope_label} / {preset.template_profile.priority_label}
              </p>
              {preset.contact_tags.length > 0 ? (
                <p className="mt-1 text-xs muted-text">Etiketler: {preset.contact_tags.join(", ")}</p>
              ) : null}
              {preset.resolved_recipients.length > 0 ? (
                <p className="mt-1 text-xs muted-text">Cozumlenen alicilar: {preset.resolved_recipients.join(", ")}</p>
              ) : null}
              {preset.notes ? <p className="mt-2 text-xs muted-text">{preset.notes}</p> : null}

              <div className="mt-3 flex flex-wrap gap-2">
                <Button type="button" variant="secondary" size="sm" onClick={() => startEdit(preset)} disabled={isSubmitting !== null}>
                  Duzenle
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => void handleToggle(preset)} disabled={isSubmitting !== null}>
                  {isSubmitting === `toggle:${preset.id}` ? "Guncelleniyor..." : preset.is_active ? "Pasife Al" : "Aktif Et"}
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => void handleDelete(preset)} disabled={isSubmitting !== null}>
                  {isSubmitting === `delete:${preset.id}` ? "Siliniyor..." : "Sil"}
                </Button>
              </div>
            </>
          )}
        </div>
      ))}

      {sortedPresets.length === 0 ? <p className="text-sm muted-text">Henuz kayitli alici grubu yok.</p> : null}
    </div>
  );
}
