"use client";

import { type ReactNode, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle } from "@/components/ui/card";
import { apiRequest } from "@/lib/api";
import { ReportShareLinkListItem } from "@/lib/types";

type Props = {
  snapshotId: string;
  shareLinks: ReportShareLinkListItem[];
  onChanged?: () => Promise<void> | void;
};

export function ReportShareLinksPanel({ snapshotId, shareLinks, onChanged }: Props) {
  const [label, setLabel] = useState("");
  const [expiresInDays, setExpiresInDays] = useState("7");
  const [allowCsvDownload, setAllowCsvDownload] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [activeActionKey, setActiveActionKey] = useState<string | null>(null);

  const handleCreate = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setActiveActionKey("create");
    setMessage(null);
    setError(null);

    try {
      const response = await apiRequest<{
        data: ReportShareLinkListItem;
      }>(`/reports/snapshots/${snapshotId}/share-links`, {
        method: "POST",
        requireWorkspace: true,
        body: {
          label: label.trim() || null,
          expires_in_days: Number(expiresInDays),
          allow_csv_download: allowCsvDownload,
        },
      });

      if (response.data.share_url && typeof navigator !== "undefined" && navigator.clipboard) {
        await navigator.clipboard.writeText(response.data.share_url);
      }

      setLabel("");
      setMessage("Paylasim linki olusturuldu ve panoya kopyalandi.");
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Paylasim linki olusturulamadi.");
    } finally {
      setActiveActionKey(null);
    }
  };

  const handleRevoke = async (shareLinkId: string) => {
    setActiveActionKey(`revoke:${shareLinkId}`);
    setMessage(null);
    setError(null);

    try {
      await apiRequest(`/reports/share-links/${shareLinkId}/revoke`, {
        method: "POST",
        requireWorkspace: true,
      });
      setMessage("Paylasim linki iptal edildi.");
      await onChanged?.();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Paylasim linki iptal edilemedi.");
    } finally {
      setActiveActionKey(null);
    }
  };

  const handleCopy = async (shareUrl: string | null) => {
    if (!shareUrl || typeof navigator === "undefined" || !navigator.clipboard) {
      setError("Link panoya kopyalanamadi.");
      return;
    }

    try {
      await navigator.clipboard.writeText(shareUrl);
      setMessage("Paylasim linki panoya kopyalandi.");
      setError(null);
    } catch {
      setError("Link panoya kopyalanamadi.");
    }
  };

  return (
    <Card>
      <CardTitle>Musteri Paylasim Linkleri</CardTitle>
      <p className="mt-2 text-sm muted-text">
        Bu linkler sadece kaydedilmis snapshot&apos;i acar. Canli veriye degil, sabit rapor kopyasina gider.
      </p>

      <form className="mt-4 space-y-3" onSubmit={handleCreate}>
        <div className="grid gap-3 md:grid-cols-3">
          <Field label="Link Etiketi">
            <input
              type="text"
              className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
              value={label}
              onChange={(event) => setLabel(event.target.value)}
              placeholder="Orn. Nisan musteri linki"
            />
          </Field>

          <Field label="Sure">
            <select
              className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
              value={expiresInDays}
              onChange={(event) => setExpiresInDays(event.target.value)}
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
                checked={allowCsvDownload}
                onChange={(event) => setAllowCsvDownload(event.target.checked)}
              />
              CSV indirilebilsin
            </label>
          </Field>
        </div>

        {message ? <p className="text-sm text-[var(--accent)]">{message}</p> : null}
        {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}

        <Button type="submit" disabled={activeActionKey !== null}>
          {activeActionKey === "create" ? "Olusturuluyor..." : "Paylasim Linki Olustur"}
        </Button>
      </form>

      <div className="mt-4 space-y-3">
        {shareLinks.map((shareLink) => (
          <div key={shareLink.id} className="rounded-lg border border-[var(--border)] p-3">
            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
              <div>
                <div className="flex flex-wrap gap-2">
                  <Badge label={shareLink.status} variant={shareLink.status === "active" ? "success" : "warning"} />
                  <Badge label={shareLink.allow_csv_download ? "CSV acik" : "CSV kapali"} variant="neutral" />
                </div>
                <p className="mt-2 font-semibold">{shareLink.label ?? "Paylasim linki"}</p>
                <p className="mt-1 text-xs muted-text">
                  Son erisim: {shareLink.last_accessed_at ?? "-"} / Erisim sayisi: {shareLink.access_count}
                </p>
                <p className="mt-1 text-xs muted-text">
                  Gecerlilik: {shareLink.expires_at ?? "-"}
                </p>
              </div>

              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => void handleCopy(shareLink.share_url)}
                  disabled={!shareLink.share_url || activeActionKey !== null}
                >
                  Linki Kopyala
                </Button>
                {shareLink.status === "active" ? (
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => void handleRevoke(shareLink.id)}
                    disabled={activeActionKey !== null}
                  >
                    {activeActionKey === `revoke:${shareLink.id}` ? "Iptal ediliyor..." : "Iptal Et"}
                  </Button>
                ) : null}
              </div>
            </div>
          </div>
        ))}

        {shareLinks.length === 0 ? (
          <p className="text-sm muted-text">Bu snapshot icin henuz olusturulmus paylasim linki yok.</p>
        ) : null}
      </div>
    </Card>
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
