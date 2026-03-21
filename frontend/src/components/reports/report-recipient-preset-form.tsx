"use client";

import { type ReactNode, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportContactListItem } from "@/lib/types";

type Props = {
  contacts?: ReportContactListItem[];
  onCreated?: () => Promise<void> | void;
};

export function ReportRecipientPresetForm({ contacts = [], onCreated }: Props) {
  const [name, setName] = useState("");
  const [recipients, setRecipients] = useState("");
  const [contactTags, setContactTags] = useState<string[]>([]);
  const [notes, setNotes] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

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

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!name.trim() || (parsedRecipients.length === 0 && contactTags.length === 0)) {
      setError("Grup adi ve en az bir statik alici veya kisi etiketi zorunlu.");
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setMessage(null);

    try {
      await apiRequest("/reports/recipient-presets", {
        method: "POST",
        requireWorkspace: true,
        body: {
          name: name.trim(),
          recipients: parsedRecipients.length > 0 ? parsedRecipients : null,
          contact_tags: contactTags.length > 0 ? contactTags : null,
          notes: notes.trim() || null,
        },
      });

      setMessage("Kayitli alici grubu olusturuldu.");
      setName("");
      setRecipients("");
      setContactTags([]);
      setNotes("");

      await onCreated?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Alici grubu kaydedilemedi.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form className="space-y-3" onSubmit={handleSubmit}>
      <div className="grid gap-3 md:grid-cols-2">
        <Field label="Grup Adi">
          <input
            type="text"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Orn. Merva K / Haftalik Rapor Grubu"
          />
        </Field>

        <Field label="Not">
          <input
            type="text"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            placeholder="Marka ekibi + musteri CEO"
          />
        </Field>
      </div>

      <Field label="Statik Alicilar (Opsiyonel)">
        <textarea
          className="min-h-[96px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
          value={recipients}
          onChange={(event) => setRecipients(event.target.value)}
          placeholder="musteri@ornek.com, ekip@ornek.com"
        />
      </Field>

      {availableContactTags.length > 0 ? (
        <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
          <p className="font-semibold">Kisi Segmentleri</p>
          <p className="mt-1 muted-text">
            Bu grup secildiginde aktif kisi havuzundan secili etiketlere gore dinamik alici cozulur.
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
          {contactTags.length > 0 ? (
            <p className="mt-2 text-xs muted-text">
              Eslesen kisi: {taggedContacts.length} / Toplam cozumlenen alici: {resolvedRecipients.length}
            </p>
          ) : null}
        </div>
      ) : null}

      {contacts.filter((item) => item.is_active).length > 0 ? (
        <div className="rounded-lg border border-[var(--border)] p-3">
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Kisi Havuzundan Statik Alici Ekle</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {contacts
              .filter((item) => item.is_active)
              .slice(0, 8)
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

      {contactTags.length > 0 ? (
        <div className="rounded-lg border border-[var(--border)] p-3 text-sm">
          <p className="font-semibold">Segment Eslesme Onizlemesi</p>
          <p className="mt-1 muted-text">
            {taggedContacts.length > 0
              ? taggedContacts.map((contact) => `${contact.name} <${contact.email}>`).join(", ")
              : "Secili etiketlerle eslesen aktif kisi yok."}
          </p>
        </div>
      ) : null}

      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}

      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting ? "Kaydediliyor..." : "Alici Grubu Kaydet"}
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
