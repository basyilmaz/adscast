"use client";

import { type ReactNode, useState } from "react";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportContactListItem } from "@/lib/types";

type Props = {
  contacts?: ReportContactListItem[];
  onCreated?: () => Promise<void> | void;
};

export function ReportRecipientPresetForm({ contacts = [], onCreated }: Props) {
  const [name, setName] = useState("");
  const [recipients, setRecipients] = useState("musteri@ornek.com");
  const [notes, setNotes] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const parsedRecipients = recipients
    .split(/[\n,;]+/)
    .map((item) => item.trim())
    .filter(Boolean);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!name.trim() || parsedRecipients.length === 0) {
      setError("Liste adi ve en az bir alici zorunlu.");
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
          recipients: parsedRecipients,
          notes: notes.trim() || null,
        },
      });

      setMessage("Kayitli alici listesi olusturuldu.");
      setName("");
      setRecipients("");
      setNotes("");

      await onCreated?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Alici listesi kaydedilemedi.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form className="space-y-3" onSubmit={handleSubmit}>
      <div className="grid gap-3 md:grid-cols-2">
        <Field label="Liste Adi">
          <input
            type="text"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Orn. Merva K / Haftalik Rapor"
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

      <Field label="Alicilar">
        <textarea
          className="min-h-[96px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
          value={recipients}
          onChange={(event) => setRecipients(event.target.value)}
          placeholder="musteri@ornek.com, ekip@ornek.com"
        />
      </Field>

      {contacts.filter((item) => item.is_active).length > 0 ? (
        <div className="rounded-lg border border-[var(--border)] p-3">
          <p className="text-xs font-semibold uppercase tracking-wide muted-text">Kisi Havuzundan Ekle</p>
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

      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}

      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting ? "Kaydediliyor..." : "Alici Listesi Kaydet"}
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
