"use client";

import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";

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
      approvable_route: string | null;
      publish_state: {
        status: string | null;
        success: boolean | null;
        message: string | null;
        meta_campaign_id: string | null;
        meta_ad_set_id: string | null;
        partial_publish_detected: boolean;
        cleanup_attempted: boolean;
        cleanup_success: boolean | null;
        cleanup_message: string | null;
        manual_check_required: boolean;
        manual_check_completed: boolean;
        manual_check_completed_at: string | null;
        manual_check_completed_by: string | null;
        manual_check_note: string | null;
        recommended_action_code: string | null;
        recommended_action_label: string | null;
        operator_guidance: string | null;
      } | null;
    } | null;
  };
};

export function DraftDetailClient() {
  const searchParams = useSearchParams();
  const draftId = searchParams.get("id");
  const hasDraftId = Boolean(draftId);
  const [submitting, setSubmitting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const {
    data: draft,
    error,
    isLoading,
    reload,
  } = useApiQuery<DraftDetailResponse, DraftDetailResponse["data"]>(`/drafts/${draftId ?? ""}`, {
    enabled: hasDraftId,
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.draftDetail,
    select: (response) => response.data,
  });

  const submitForReview = async () => {
    if (!hasDraftId) {
      setActionError("Draft id eksik.");
      return;
    }

    setSubmitting(true);
    setActionError(null);
    try {
      await apiRequest(`/drafts/${draftId as string}/submit-review`, {
        method: "POST",
        requireWorkspace: true,
      });
      invalidateApiCache(`/drafts/${draftId as string}`, { requireWorkspace: true });
      invalidateApiCache("/drafts", { requireWorkspace: true });
      invalidateApiCache("/approvals", { requireWorkspace: true });
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Review adimi basarisiz.");
    } finally {
      setSubmitting(false);
    }
  };

  if (!hasDraftId) return <p className="text-sm text-[var(--danger)]">Draft id eksik.</p>;
  if (error) return <p className="text-sm text-[var(--danger)]">{error}</p>;
  if (isLoading && !draft) return <p className="text-sm muted-text">Yukleniyor...</p>;
  if (!draft) return <p className="text-sm text-[var(--danger)]">Draft bulunamadi.</p>;

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
        {draft.approval?.publish_state ? (
          <div className={`mt-4 rounded-md border p-3 ${draft.approval.publish_state.manual_check_required ? "border-[var(--danger)] bg-[var(--surface-2)]" : "border-[var(--border)] bg-[var(--surface-2)]"}`}>
            <div className="flex flex-wrap items-center gap-2">
              <Badge
                label={
                  draft.approval.publish_state.manual_check_required
                    ? "Manuel Kontrol Gerekli"
                    : draft.approval.publish_state.manual_check_completed
                      ? "Manuel Kontrol Tamamlandi"
                    : draft.approval.publish_state.partial_publish_detected
                      ? "Partial Publish Tespit Edildi"
                      : draft.approval.publish_state.success
                        ? "Publish Basarili"
                        : "Publish Hatasi"
                }
                variant={
                  draft.approval.publish_state.manual_check_required
                    ? "danger"
                    : draft.approval.publish_state.manual_check_completed
                      ? "success"
                    : draft.approval.publish_state.success
                      ? "success"
                      : "warning"
                }
              />
              {draft.approval.publish_state.recommended_action_label ? (
                <Badge label={draft.approval.publish_state.recommended_action_label} variant="neutral" />
              ) : null}
            </div>
            {draft.approval.publish_state.message ? <p className="mt-2 text-sm">{draft.approval.publish_state.message}</p> : null}
            {draft.approval.publish_state.operator_guidance ? <p className="mt-2 text-sm muted-text">{draft.approval.publish_state.operator_guidance}</p> : null}
            <div className="mt-2 flex flex-wrap gap-3 text-xs muted-text">
              {draft.approval.publish_state.meta_campaign_id ? <span>Campaign: <strong>{draft.approval.publish_state.meta_campaign_id}</strong></span> : null}
              {draft.approval.publish_state.meta_ad_set_id ? <span>Ad Set: <strong>{draft.approval.publish_state.meta_ad_set_id}</strong></span> : null}
              {draft.approval.publish_state.cleanup_attempted ? (
                <span>Cleanup: <strong>{draft.approval.publish_state.cleanup_success ? "Basarili" : "Basarisiz"}</strong></span>
              ) : null}
              {draft.approval.publish_state.manual_check_completed_at ? (
                <span>Kontrol: <strong>{new Date(draft.approval.publish_state.manual_check_completed_at).toLocaleString("tr-TR")}</strong></span>
              ) : null}
            </div>
            {draft.approval.publish_state.cleanup_message ? <p className="mt-2 text-xs text-[var(--danger)]">{draft.approval.publish_state.cleanup_message}</p> : null}
            {draft.approval.publish_state.manual_check_note ? (
              <p className="mt-2 text-xs muted-text">Kontrol notu: {draft.approval.publish_state.manual_check_note}</p>
            ) : null}
          </div>
        ) : null}

        {draft.status === "draft" ? (
          <div className="mt-4">
            <Button onClick={submitForReview} disabled={submitting}>
              {submitting ? "Gonderiliyor..." : "Incelemeye Gonder"}
            </Button>
          </div>
        ) : null}
        {actionError ? <p className="mt-3 text-sm text-[var(--danger)]">{actionError}</p> : null}
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
