"use client";

import { useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { ReportRecipientPresetListItem } from "@/lib/types";

type Props = {
  presets: ReportRecipientPresetListItem[];
  onChanged?: () => Promise<void> | void;
};

export function ReportRecipientPresetManager({ presets, onChanged }: Props) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [name, setName] = useState("");
  const [recipients, setRecipients] = useState("");
  const [notes, setNotes] = useState("");
  const [isSubmitting, setIsSubmitting] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const sortedPresets = useMemo(
    () => [...presets].sort((left, right) => Number(right.is_active) - Number(left.is_active) || left.name.localeCompare(right.name)),
    [presets],
  );

  const startEdit = (preset: ReportRecipientPresetListItem) => {
    setEditingId(preset.id);
    setName(preset.name);
    setRecipients(preset.recipients.join(", "));
    setNotes(preset.notes ?? "");
    setMessage(null);
    setError(null);
  };

  const cancelEdit = () => {
    setEditingId(null);
    setName("");
    setRecipients("");
    setNotes("");
  };

  const handleUpdate = async (presetId: string) => {
    const parsedRecipients = recipients
      .split(/[\n,;]+/)
      .map((item) => item.trim())
      .filter(Boolean);

    if (!name.trim() || parsedRecipients.length === 0) {
      setError("Liste adi ve en az bir alici zorunlu.");
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
          recipients: parsedRecipients,
          notes: notes.trim() || null,
        },
      });

      setMessage("Alici listesi guncellendi.");
      cancelEdit();
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Alici listesi guncellenemedi.");
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

      setMessage(preset.is_active ? "Alici listesi pasife alindi." : "Alici listesi tekrar aktif edildi.");
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Alici listesi guncellenemedi.");
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

      setMessage("Alici listesi silindi.");
      if (editingId === preset.id) {
        cancelEdit();
      }
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Alici listesi silinemedi.");
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
            <Badge label={`${preset.recipients_count} alici`} variant="neutral" />
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
              />
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
              <p className="mt-1 text-sm muted-text">{preset.recipients.join(", ")}</p>
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

      {sortedPresets.length === 0 ? <p className="text-sm muted-text">Henuz kayitli alici listesi yok.</p> : null}
    </div>
  );
}
