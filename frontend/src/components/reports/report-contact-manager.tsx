"use client";

import { useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportContactListItem } from "@/lib/types";

type Props = {
  contacts: ReportContactListItem[];
  onChanged?: () => Promise<void> | void;
};

export function ReportContactManager({ contacts, onChanged }: Props) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [companyName, setCompanyName] = useState("");
  const [roleLabel, setRoleLabel] = useState("");
  const [tags, setTags] = useState("");
  const [notes, setNotes] = useState("");
  const [isPrimary, setIsPrimary] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const sortedContacts = useMemo(
    () =>
      [...contacts].sort(
        (left, right) =>
          Number(right.is_active) - Number(left.is_active) ||
          Number(right.is_primary) - Number(left.is_primary) ||
          left.name.localeCompare(right.name),
      ),
    [contacts],
  );

  const startEdit = (contact: ReportContactListItem) => {
    setEditingId(contact.id);
    setName(contact.name);
    setEmail(contact.email);
    setCompanyName(contact.company_name ?? "");
    setRoleLabel(contact.role_label ?? "");
    setTags(contact.tags.join(", "));
    setNotes(contact.notes ?? "");
    setIsPrimary(contact.is_primary);
    setMessage(null);
    setError(null);
  };

  const cancelEdit = () => {
    setEditingId(null);
    setName("");
    setEmail("");
    setCompanyName("");
    setRoleLabel("");
    setTags("");
    setNotes("");
    setIsPrimary(false);
  };

  const handleUpdate = async (contactId: string) => {
    const parsedTags = tags
      .split(/[\n,;]+/)
      .map((item) => item.trim())
      .filter(Boolean);

    if (!name.trim() || !email.trim()) {
      setError("Ad ve e-posta zorunlu.");
      return;
    }

    setIsSubmitting(`update:${contactId}`);
    setError(null);
    setMessage(null);

    try {
      await apiRequest(`/reports/contacts/${contactId}`, {
        method: "PUT",
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

      setMessage("Kisi havuzu kaydi guncellendi.");
      cancelEdit();
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Kisi guncellenemedi.");
    } finally {
      setIsSubmitting(null);
    }
  };

  const handleToggle = async (contact: ReportContactListItem) => {
    setIsSubmitting(`toggle:${contact.id}`);
    setError(null);
    setMessage(null);

    try {
      await apiRequest(`/reports/contacts/${contact.id}/toggle`, {
        method: "POST",
        requireWorkspace: true,
        body: {
          is_active: !contact.is_active,
        },
      });

      setMessage(contact.is_active ? "Kisi pasife alindi." : "Kisi tekrar aktif edildi.");
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Kisi durumu guncellenemedi.");
    } finally {
      setIsSubmitting(null);
    }
  };

  const handleDelete = async (contact: ReportContactListItem) => {
    setIsSubmitting(`delete:${contact.id}`);
    setError(null);
    setMessage(null);

    try {
      await apiRequest(`/reports/contacts/${contact.id}`, {
        method: "DELETE",
        requireWorkspace: true,
      });

      setMessage("Kisi havuzu kaydi silindi.");
      if (editingId === contact.id) {
        cancelEdit();
      }
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Kisi silinemedi.");
    } finally {
      setIsSubmitting(null);
    }
  };

  return (
    <div className="space-y-3">
      {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}
      {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}

      {sortedContacts.map((contact) => (
        <div key={contact.id} className="rounded-lg border border-[var(--border)] p-3">
          <div className="flex flex-wrap items-center gap-2">
            <Badge label={contact.is_active ? "active" : "inactive"} variant={contact.is_active ? "success" : "warning"} />
            {contact.is_primary ? <Badge label="primary" variant="neutral" /> : null}
            {contact.company_name ? <Badge label={contact.company_name} variant="neutral" /> : null}
          </div>

          {editingId === contact.id ? (
            <div className="mt-3 space-y-3">
              <div className="grid gap-3 md:grid-cols-2">
                <input
                  type="text"
                  className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                />
                <input
                  type="email"
                  className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                />
                <input
                  type="text"
                  className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                  value={companyName}
                  onChange={(event) => setCompanyName(event.target.value)}
                  placeholder="Sirket / Marka"
                />
                <input
                  type="text"
                  className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                  value={roleLabel}
                  onChange={(event) => setRoleLabel(event.target.value)}
                  placeholder="Rol"
                />
              </div>
              <input
                type="text"
                className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
                value={tags}
                onChange={(event) => setTags(event.target.value)}
                placeholder="Etiketler"
              />
              <textarea
                className="min-h-[84px] w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-sm"
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                placeholder="Not"
              />
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={isPrimary} onChange={(event) => setIsPrimary(event.target.checked)} />
                Birincil kisi
              </label>
              <div className="flex flex-wrap gap-2">
                <Button type="button" onClick={() => void handleUpdate(contact.id)} disabled={isSubmitting !== null}>
                  {isSubmitting === `update:${contact.id}` ? "Kaydediliyor..." : "Kaydet"}
                </Button>
                <Button type="button" variant="secondary" onClick={cancelEdit} disabled={isSubmitting !== null}>
                  Vazgec
                </Button>
              </div>
            </div>
          ) : (
            <>
              <p className="mt-2 font-semibold">{contact.name}</p>
              <p className="mt-1 text-sm muted-text">{contact.email}</p>
              <p className="mt-1 text-xs muted-text">
                {[contact.company_name, contact.role_label].filter(Boolean).join(" / ") || "Baglam girilmedi"}
              </p>
              {contact.tags.length > 0 ? (
                <div className="mt-2 flex flex-wrap gap-2">
                  {contact.tags.map((tag) => (
                    <Badge key={tag} label={tag} variant="neutral" />
                  ))}
                </div>
              ) : null}
              {contact.notes ? <p className="mt-2 text-xs muted-text">{contact.notes}</p> : null}

              <div className="mt-3 flex flex-wrap gap-2">
                <Button type="button" variant="secondary" size="sm" onClick={() => startEdit(contact)} disabled={isSubmitting !== null}>
                  Duzenle
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => void handleToggle(contact)} disabled={isSubmitting !== null}>
                  {isSubmitting === `toggle:${contact.id}` ? "Guncelleniyor..." : contact.is_active ? "Pasife Al" : "Aktif Et"}
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => void handleDelete(contact)} disabled={isSubmitting !== null}>
                  {isSubmitting === `delete:${contact.id}` ? "Siliniyor..." : "Sil"}
                </Button>
              </div>
            </>
          )}
        </div>
      ))}

      {sortedContacts.length === 0 ? <p className="text-sm muted-text">Henuz kisi havuzu kaydi yok.</p> : null}
    </div>
  );
}
