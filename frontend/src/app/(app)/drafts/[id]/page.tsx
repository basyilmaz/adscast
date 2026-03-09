"use client";

import { useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";

type DraftDetailResponse = {
  data: {
    id: string;
    objective: string;
    product_service: string;
    target_audience: string;
    status: string;
    notes: string | null;
    items: Array<{
      id: string;
      item_type: string;
      title: string | null;
      content: Record<string, unknown>;
    }>;
    approval: {
      id: string;
      status: string;
    } | null;
  };
};

export default function DraftDetailPage() {
  const params = useParams<{ id: string }>();
  const [draft, setDraft] = useState<DraftDetailResponse["data"] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const loadDraft = useCallback(async () => {
    try {
      const response = await apiRequest<DraftDetailResponse>(`/drafts/${params.id}`, {
        requireWorkspace: true,
      });
      setDraft(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Draft detayi alinamadi.");
    }
  }, [params.id]);

  useEffect(() => {
    if (params.id) {
      void loadDraft();
    }
  }, [loadDraft, params.id]);

  const submitForReview = async () => {
    setSubmitting(true);
    setError(null);
    try {
      await apiRequest(`/drafts/${params.id}/submit-review`, {
        method: "POST",
        requireWorkspace: true,
      });
      await loadDraft();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Review adimi basarisiz.");
    } finally {
      setSubmitting(false);
    }
  };

  if (error) return <p className="text-sm text-[var(--danger)]">{error}</p>;
  if (!draft) return <p className="text-sm muted-text">Yukleniyor...</p>;

  return (
    <div className="space-y-4">
      <Card>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="text-xl font-bold">{draft.objective}</h3>
            <p className="text-sm muted-text">{draft.product_service}</p>
          </div>
          <div className="flex items-center gap-2">
            <Badge label={draft.status} variant="warning" />
            {draft.approval ? <Badge label={`Approval: ${draft.approval.status}`} variant="neutral" /> : null}
          </div>
        </div>

        <p className="mt-3 text-sm">{draft.target_audience}</p>
        {draft.notes ? <p className="mt-2 text-xs muted-text">{draft.notes}</p> : null}

        {draft.status === "draft" ? (
          <div className="mt-4">
            <Button onClick={submitForReview} disabled={submitting}>
              {submitting ? "Gonderiliyor..." : "Incelemeye Gonder"}
            </Button>
          </div>
        ) : null}
      </Card>

      <Card>
        <h4 className="text-sm font-bold uppercase tracking-wide">Draft Oge Detaylari</h4>
        <div className="mt-3 space-y-2">
          {draft.items.map((item) => (
            <div key={item.id} className="rounded-md border border-[var(--border)] p-3">
              <p className="font-semibold">{item.title ?? item.item_type}</p>
              <pre className="mt-2 overflow-x-auto rounded bg-[var(--surface-2)] p-2 text-xs">
                {JSON.stringify(item.content, null, 2)}
              </pre>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}
