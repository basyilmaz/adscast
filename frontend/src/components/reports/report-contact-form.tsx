"use client";

import { type ReactNode, useState } from "react";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";

type Props = {
  onCreated?: () => Promise<void> | void;
};

export function ReportContactForm({ onCreated }: Props) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [companyName, setCompanyName] = useState("");
  const [roleLabel, setRoleLabel] = useState("");
  const [tags, setTags] = useState("");
  const [notes, setNotes] = useState("");
  const [isPrimary, setIsPrimary] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const parsedTags = tags
    .split(/[\n,;]+/)
    .map((item) => item.trim())
    .filter(Boolean);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!name.trim() || !email.trim()) {
      setError("Ad ve e-posta zorunlu.");
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setMessage(null);

    try {
      await apiRequest("/reports/contacts", {
        method: "POST",
        requireWorkspace: true,
        body: {
          name: name.trim(),
          email: email.trim(),
          company_name: companyName.trim() || null,
          role_label: roleLabel.trim() || null,
          tags: parsedTags,
          notes: notes.trim() || null,
          is_primary: isPrimary,
        },
      });

      setMessage("Kisi havuzu kaydi olusturuldu.");
      setName("");
      setEmail("");
      setCompanyName("");
      setRoleLabel("");
      setTags("");
      setNotes("");
      setIsPrimary(false);
      await onCreated?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Kisi kaydi olusturulamadi.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form className="space-y-3" onSubmit={handleSubmit}>
      <div className="grid gap-3 md:grid-cols-2">
        <Field label="Kisi Adi">
          <input
            type="text"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Orn. Merve Kaya"
          />
        </Field>

        <Field label="E-posta">
          <input
            type="email"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            placeholder="musteri@ornek.com"
          />
        </Field>

        <Field label="Sirket / Marka">
          <input
            type="text"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={companyName}
            onChange={(event) => setCompanyName(event.target.value)}
            placeholder="Castintech"
          />
        </Field>

        <Field label="Rol">
          <input
            type="text"
            className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={roleLabel}
            onChange={(event) => setRoleLabel(event.target.value)}
            placeholder="CMO, CEO, Marka Yoneticisi"
          />
        </Field>
      </div>

      <Field label="Etiketler">
        <input
          type="text"
          className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
          value={tags}
          onChange={(event) => setTags(event.target.value)}
          placeholder="musteri, haftalik, performans"
        />
      </Field>

      <Field label="Not">
        <textarea
          className="min-h-[84px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          placeholder="Raporu once gormek isteyen kisi"
        />
      </Field>

      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" checked={isPrimary} onChange={(event) => setIsPrimary(event.target.checked)} />
        Birincil musteri kisisi olarak isaretle
      </label>

      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}

      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting ? "Kaydediliyor..." : "Kisi Kaydet"}
      </Button>
    </form>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="flex flex-col gap-1">
      <span className="text-xs font-semibold uppercase tracking-wide muted-text">{label}</span>
      {children}
    </label>
  );
}
